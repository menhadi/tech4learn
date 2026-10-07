<?php

namespace App\Services;

use App\Models\AiAnswerDraft;
use App\Models\AiAnswerRun;
use App\Models\AiCurriculumEvent;
use App\Models\Configuration;
use App\Models\Diff;
use App\Models\Question;
use App\Models\QuestionLang;
use App\Models\QuestionVersion;
use App\Models\Stopic;
use App\Models\Subject;
use App\Models\Topic;
use App\Support\AiProvider;
use Illuminate\Support\Facades\DB;

class AiAnswerService
{
    public const EDITABLE_FIELDS = [
        'correct_option_indices', 'true_false', 'fill_blank', 'fill_blank_config',
        'nat_config', 'si_answer1', 'explanation', 'diff_id',
        'subject_id', 'topic_id', 'stopic_id',
    ];

    private array $difficultyIds = [];
    private array $curriculumContexts = [];

    public function snapshot(Question $question): array
    {
        return $question->only(self::EDITABLE_FIELDS);
    }

    private function assertFixedSubject(Question $question, array $classification): void
    {
        $question->loadMissing('subject');
        if (! $question->subject_id || ! $question->subject
            || (int) $question->subject->organization_id !== (int) $question->organization_id) {
            throw new \RuntimeException('Assign a subject to this question before generating AI answers.');
        }
        if ($this->normalizeCurriculumName($question->subject->subject_name) !== $this->normalizeCurriculumName($classification['subject'])) {
            throw new \RuntimeException('AI attempted to change the assigned subject. Only topic and subtopic classification is allowed.');
        }
    }

    private function validateAnswer(Question $question, array $payload): void
    {
        $type = app(QuestionAnswerEvaluator::class)->questionType($question);
        if (in_array($type, ['multiple_choice_radio', 'multiple_choice_checkbox'], true)) {
            $indices = $this->indices($payload['correct_option_indices'] ?? []);
            if ($type === 'multiple_choice_radio' && count($indices) !== 1) {
                throw new \RuntimeException('An MCQ must have exactly one correct option.');
            }
            foreach ($indices as $index) {
                if (trim((string) $question->{'option'.$index}) === '') {
                    throw new \RuntimeException('The answer references an empty option.');
                }
            }
        } elseif ($type === 'nat') {
            $this->natConfig($payload['nat_config'] ?? []);
        } elseif ($type === 'true_false') {
            $this->trueFalse($payload['true_false'] ?? null);
        } elseif ($type === 'fill_blank') {
            $blanks = data_get($payload, 'fill_blank_config.blanks', []);
            if ($blanks === []) $blanks = [['answers' => [$payload['fill_blank'] ?? '']]];
            foreach ($blanks as $blank) {
                if (collect($blank['answers'] ?? [])->filter(fn ($value) => is_scalar($value) && trim((string) $value) !== '')->isEmpty()) {
                    throw new \RuntimeException('Every fill-blank answer is required.');
                }
            }
        } elseif ($type === 'subjective') {
            $this->cleanRichText($payload['si_answer1'] ?? '', 1, 6000, 'reference answer');
        } else {
            throw new \RuntimeException('Unsupported question type.');
        }
    }

    public function mode(Question $question, array $verification = []): string
    {
        $evaluator = app(QuestionAnswerEvaluator::class);
        $answer = $evaluator->correctAnswerSnapshot($question);
        $type = $evaluator->questionType($question);
        $hasAnswer = in_array($type, ['multiple_choice_radio', 'multiple_choice_checkbox'], true)
            ? $question->correctOptionIndices() !== []
            : (is_array($answer) ? $answer !== [] : trim(strip_tags((string) $answer)) !== '');
        return $hasAnswer && ($verification['status'] ?? '') === 'verified'
            ? 'generate_explanation' : 'generate_answer_explanation';
    }

    public function processRun(AiAnswerRun $run): void
    {
        $run->update(['status' => 'running', 'started_at' => $run->started_at ?: now(), 'failure_message' => null]);
        try {
            $run->drafts()->where('status', 'queued')->with([
                'question.qtype', 'question.subject', 'question.topic', 'question.stopic', 'question.groups',
                'exam.groups', 'exam.category', 'exam.subcategory',
            ])->orderBy('id')
                ->chunkById(5, function ($drafts) use ($run) {
                    $this->processChunk($run, $drafts);
                });
            $this->refreshRun($run, true);
        } catch (\Throwable $exception) {
            $run->drafts()->whereIn('status', ['queued', 'processing'])->update([
                'status' => 'failed',
                'failure_message' => mb_substr($exception->getMessage(), 0, 6000),
            ]);
            $run->update([
                'status' => 'failed', 'failure_message' => mb_substr($exception->getMessage(), 0, 6000),
                'completed_at' => now(),
            ]);
            throw $exception;
        }
    }

    private function processChunk(AiAnswerRun $run, $drafts): void
    {
        $this->curriculumContexts = [];
        $verification = app(AiAnswerVerification::class)->forExam($run->exam, collect($drafts)->pluck('question'));
        $drafts = collect($drafts)->filter(function (AiAnswerDraft $draft) use ($verification) {
            try {
                $this->assertVerification($draft, $verification[$draft->question_id]);
                $this->curriculumContexts[$draft->id] = $this->curriculumContext($draft);
                return true;
            } catch (\Throwable $exception) {
                $draft->update(['status' => 'failed', 'failure_message' => mb_substr($exception->getMessage(), 0, 6000)]);
                return false;
            }
        })->values();
        [$visionDrafts, $textDrafts] = $drafts->partition(
            fn (AiAnswerDraft $draft) => $this->imageReferences($draft->question) !== []
        );

        if ($textDrafts->isNotEmpty()) $this->processProviderChunk($run, $textDrafts, false);
        foreach ($visionDrafts->chunk(2) as $visionChunk) {
            $this->processProviderChunk($run, $visionChunk->values(), true);
        }
        $this->refreshRun($run);
    }

    private function processProviderChunk(AiAnswerRun $run, $drafts, bool $requiresVision): void
    {
        $drafts = collect($drafts)->values();
        $drafts->each(fn (AiAnswerDraft $draft) => $draft->update(['status' => 'processing', 'failure_message' => null]));
        $providers = $this->providers($run, $requiresVision);
        if ($providers === []) throw new \RuntimeException('No compatible AI provider is configured in Admin AI Settings.');

        $images = [];
        if ($requiresVision) {
            $resolvedByDraft = [];
            foreach ($drafts as $draft) {
                try {
                    $resolvedByDraft[$draft->id] = $this->visionImages($draft);
                } catch (\Throwable $exception) {
                    $draft->update(['status' => 'failed', 'failure_message' => mb_substr($exception->getMessage(), 0, 6000)]);
                }
            }
            $drafts = $drafts->where('status', 'processing')->values();
            if ($drafts->isEmpty()) {
                $this->refreshRun($run);
                return;
            }
            foreach ($drafts as $draft) {
                $images = [...$images, ...($resolvedByDraft[$draft->id] ?? [])];
            }
        }
        $prompt = $this->prompt($run, $drafts);
        $errors = [];
        $decoded = null;
        $used = null;
        foreach ($providers as $provider) {
            try {
                $raw = $requiresVision
                    ? AiProvider::generateVision(
                        $provider, $prompt, $images,
                        'You are a precise exam answer editor with visual reasoning. Return strict JSON only.',
                        8000, 240
                    )
                    : AiProvider::generateText(
                        $provider, $prompt, 'You are a precise exam answer editor. Return strict JSON only.',
                        0.1, 8000, 150, true
                    );
                if (! is_string($raw) || trim($raw) === '') throw new \RuntimeException('Provider returned an empty response.');
                $decoded = $this->decodeJson($raw);
                if (! isset($decoded['results']) || ! is_array($decoded['results'])) throw new \RuntimeException('Response has no results array.');
                $used = $provider;
                break;
            } catch (\Throwable $exception) {
                $errors[] = ($provider['stored_name'] ?? $provider['provider']).': '.$exception->getMessage();
            }
        }

        if (! $decoded || ! $used) {
            $message = 'All configured answer-generation providers failed. '.implode(' | ', $errors);
            $drafts->each(fn (AiAnswerDraft $draft) => $draft->update(['status' => 'failed', 'failure_message' => mb_substr($message, 0, 6000)]));
            $this->refreshRun($run);
            return;
        }

        $results = collect($decoded['results'])->filter(fn ($item) => is_array($item) && isset($item['draft_id']))
            ->keyBy(fn ($item) => (int) $item['draft_id']);
        foreach ($drafts as $draft) {
            $result = $results->get((int) $draft->id);
            if (! $result) {
                $draft->update(['status' => 'failed', 'failure_message' => 'The AI response omitted this question.']);
                continue;
            }
            try {
                $this->applyResult($draft, $result, $used);
            } catch (\Throwable $exception) {
                $draft->update(['status' => 'failed', 'failure_message' => mb_substr($exception->getMessage(), 0, 6000)]);
            }
        }
        $this->refreshRun($run);
    }

    private function applyResult(AiAnswerDraft $draft, array $result, array $provider): void
    {
        $draft->update($this->resultAttributes($draft, $result, $provider));
    }

    private function resultAttributes(AiAnswerDraft $draft, array $result, array $provider): array
    {
        $question = $draft->question;
        $verification = (array) data_get($draft->original_payload, '_answer_verification', []);
        if (($verification['policy_version'] ?? null) !== 1) throw new \RuntimeException('Generate a new draft using the exam answer-verification status.');
        if (($verification['status'] ?? '') === 'review_required') throw new \RuntimeException($verification['reason']);
        $original = collect((array) $draft->original_payload)->only(self::EDITABLE_FIELDS)->all();
        $proposed = $original;
        $answer = (array) ($result['answer'] ?? []);
        $type = app(QuestionAnswerEvaluator::class)->questionType($question);
        $checkStoredAnswer = ($verification['status'] ?? '') === 'verified';
        if (! array_key_exists('stored_answer_consistent', $result) || ! is_bool($result['stored_answer_consistent'])) {
            throw new \RuntimeException('AI must explicitly confirm whether the answer can be justified.');
        }
        if ($checkStoredAnswer && ! $result['stored_answer_consistent']) {
            throw new \RuntimeException('The saved answer cannot be justified. Review the answer manually; AI has not changed it.');
        }
        $proposed['diff_id'] = $this->difficultyId($result['difficulty'] ?? null);
        $classification = $this->classification($result['classification'] ?? []);
        $this->assertFixedSubject($question, $classification);
        if (! $this->sameClassification($question, $classification)) {
            $proposed['_curriculum'] = $classification;
            $proposed['_curriculum_status'] = $this->curriculumStatus($classification, $draft);
        }

        if (! $checkStoredAnswer) {
            match ($type) {
                'multiple_choice_radio', 'multiple_choice_checkbox' => $proposed['correct_option_indices'] = $this->indices($answer['correct_option_indices'] ?? []),
                'nat' => $proposed['nat_config'] = $this->natConfig($answer['nat_config'] ?? []),
                'true_false' => $proposed['true_false'] = $this->trueFalse($answer['true_false'] ?? null),
                'fill_blank' => $this->applyFillBlank($proposed, $answer['fill_blank_answers'] ?? []),
                'subjective' => $proposed['si_answer1'] = $this->cleanRichText($answer['subjective_answer'] ?? '', 20, 6000, 'reference answer'),
                default => throw new \RuntimeException('Unsupported question type.'),
            };
        }

        $this->validateAnswer($question, $proposed);
        $proposed['explanation'] = $this->cleanRichText(
            $this->studentFacingExplanation($result['explanation'] ?? ''),
            20, 12000, 'explanation'
        );
        app(AiAnswerMathValidator::class)->validate((string) ($result['explanation'] ?? ''));
        app(AiAnswerMathValidator::class)->validate($proposed['explanation']);

        $discrepancies = collect((array) ($result['discrepancies'] ?? []))
            ->map(fn ($value) => trim((string) $value))->filter()->values()->all();
        $discrepancies = array_values(array_unique($discrepancies));
        if (! $checkStoredAnswer) {
            foreach (['correct_option_indices', 'nat_config', 'true_false', 'fill_blank', 'fill_blank_config', 'si_answer1'] as $field) {
                $before = $original[$field] ?? null;
                if ($before !== null && $before !== '' && $before !== [] && ($proposed[$field] ?? null) != $before) {
                    $discrepancies[] = 'The independently solved answer differs from the unverified saved answer.';
                    break;
                }
            }
        }

        $changes = collect($proposed)->filter(
            fn ($value, $field) => json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                !== json_encode($original[$field] ?? null, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        )->keys()->values()->all();

        return [
            'status' => $discrepancies !== [] ? 'discrepancy' : ($changes === [] ? 'verified' : 'ready'),
            'proposed_payload' => $proposed, 'changed_fields' => $changes, 'discrepancies' => $discrepancies,
            'provider' => $provider['provider'], 'model' => $provider['model'],
            'confidence' => max(0, min(100, (float) ($result['confidence'] ?? 70))),
            'question_updated_at' => $question->updated_at,
        ];
    }

    public function approve(AiAnswerDraft $draft, int $userId): void
    {
        if (! in_array($draft->status, ['discrepancy', 'ready'], true)) {
            throw new \RuntimeException('Only a ready or discrepancy draft can be approved.');
        }
        if (empty($draft->changed_fields)) throw new \RuntimeException('This draft contains no proposed changes.');
        $draft->update(['status' => 'ready', 'reviewed_by' => $userId, 'reviewed_at' => now()]);
        $this->refreshRun($draft->run);
    }

    public function publish(AiAnswerDraft $draft, int $userId): Question
    {
        $operation = function () use ($draft, $userId) {
            return DB::transaction(function () use ($draft, $userId) {
            $draft = AiAnswerDraft::whereKey($draft->id)->lockForUpdate()->firstOrFail();
            if ($draft->status !== 'ready') throw new \RuntimeException('Only an approved ready draft can be published.');
            $question = Question::whereKey($draft->question_id)->lockForUpdate()->firstOrFail();
            if ((int) $question->organization_id !== (int) $draft->organization_id) abort(404);
            if ($draft->question_updated_at && ! $question->updated_at->equalTo($draft->question_updated_at)) {
                throw new \RuntimeException('The question changed after this draft was generated. Generate a new draft before publishing.');
            }
            $verification = app(AiAnswerVerification::class)->forExam($draft->exam, [$question]);
            $this->assertVerification($draft, $verification[$question->id]);
            $changes = collect((array) $draft->proposed_payload)->only(self::EDITABLE_FIELDS)
                ->only((array) $draft->changed_fields)->all();
            $classification = (array) data_get($draft->proposed_payload, '_curriculum', []);
            if ($classification !== []) $this->assertFixedSubject($question, $classification);
            // Apply the same contract to older drafts that are still awaiting publication.
            if ($verification[$question->id]['status'] === 'verified') {
                foreach (['correct_option_indices', 'true_false', 'fill_blank', 'fill_blank_config', 'nat_config', 'si_answer1'] as $field) {
                    if (array_key_exists($field, $changes) && $changes[$field] != $question->{$field}) {
                        throw new \RuntimeException('This draft changes a saved answer. Generate a new explanation-only draft.');
                    }
                }
            }
            if (isset($changes['subject_id']) && (int) $changes['subject_id'] !== (int) $question->subject_id) {
                throw new \RuntimeException('AI publication cannot change the assigned subject.');
            }
            $curriculumResolution = ['assignments' => [], 'created' => []];
            if ($classification !== []) {
                $curriculumResolution = $this->resolveCurriculum($draft, $question, $classification);
                $changes = [...$changes, ...$curriculumResolution['assignments']];
            }
            if ($changes === []) throw new \RuntimeException('This draft has no approved changes.');
            if (array_key_exists('explanation', $changes)) {
                $changes['explanation'] = $this->cleanRichText(
                    $this->studentFacingExplanation($changes['explanation']),
                    20, 12000, 'explanation'
                );
            }
            $completePayload = [...$this->snapshot($question), ...$changes];
            $this->validateAnswer($question, $completePayload);
            foreach (['subject_id', 'topic_id', 'stopic_id', 'diff_id'] as $field) {
                if (empty($completePayload[$field])) throw new \RuntimeException("The {$field} field is required before publication.");
            }
            $this->cleanRichText($completePayload['explanation'] ?? '', 20, 12000, 'explanation');
            app(AiAnswerMathValidator::class)->validate($completePayload['explanation']);

            QuestionVersion::create([
                'organization_id' => $draft->organization_id, 'question_id' => $question->id,
                'ai_answer_draft_id' => $draft->id,
                'payload' => [
                    ...app(QuestionRepairService::class)->snapshot($question),
                    'diff_id' => $question->diff_id,
                    'subject_id' => $question->subject_id,
                    'topic_id' => $question->topic_id,
                    'stopic_id' => $question->stopic_id,
                ],
                'created_by' => $userId,
            ]);
            $question->update($changes);
            app(CurriculumTaxonomyService::class)->syncQuestion($question->fresh(['topic', 'stopic']), $question->groups()->pluck('groups.id')->all());
            foreach ($curriculumResolution['created'] as $created) {
                AiCurriculumEvent::create([
                    'organization_id' => $draft->organization_id,
                    'run_id' => $draft->run_id,
                    'draft_id' => $draft->id,
                    'question_id' => $question->id,
                    'entity_type' => $created['entity_type'],
                    'entity_id' => $created['entity_id'],
                    'name' => $created['name'],
                    'created_by' => $userId,
                ]);
            }
            $langChanges = collect($changes)->only(['explanation', 'fill_blank'])->all();
            if ($langChanges !== []) {
                QuestionLang::where('question_id', $question->id)->where('language_id', $question->language_id)->update($langChanges);
            }
            $draft->update(['status' => 'published', 'published_by' => $userId, 'published_at' => now()]);
            $this->refreshRun($draft->run);
            return $question;
            });
        };

        return data_get($draft->proposed_payload, '_curriculum')
            ? $this->withCurriculumLock('publication-global', $operation)
            : $operation();
    }

    public function restoreVersion(QuestionVersion $version, int $userId): Question
    {
        return DB::transaction(function () use ($version, $userId) {
            $version = QuestionVersion::whereKey($version->id)->lockForUpdate()->firstOrFail();
            if (! $version->ai_answer_draft_id) {
                throw new \RuntimeException('This is not an AI answer publication version.');
            }

            $question = Question::whereKey($version->question_id)->lockForUpdate()->firstOrFail();
            if ((int) $question->organization_id !== (int) $version->organization_id) abort(404);

            $payload = collect((array) $version->payload)->only(self::EDITABLE_FIELDS)->all();
            if ($payload === []) {
                throw new \RuntimeException('This saved version has no restorable answer or explanation content.');
            }

            QuestionVersion::create([
                'organization_id' => $version->organization_id,
                'question_id' => $question->id,
                'ai_answer_draft_id' => $version->ai_answer_draft_id,
                'payload' => [
                    ...app(QuestionRepairService::class)->snapshot($question),
                    'diff_id' => $question->diff_id,
                    'subject_id' => $question->subject_id,
                    'topic_id' => $question->topic_id,
                    'stopic_id' => $question->stopic_id,
                ],
                'created_by' => $userId,
            ]);

            $question->update($payload);
            $langChanges = collect($payload)->only(['explanation', 'fill_blank'])->all();
            if ($langChanges !== []) {
                QuestionLang::where('question_id', $question->id)
                    ->where('language_id', $question->language_id)
                    ->update($langChanges);
            }

            return $question->fresh();
        });
    }

    private function providers(AiAnswerRun $run, bool $requiresVision = false): array
    {
        $settings = Configuration::where('organization_id', $run->organization_id)->first();
        return AiProvider::available($settings, $requiresVision, 'answer_explanation');
    }

    private function assertVerification(AiAnswerDraft $draft, array $current): void
    {
        $saved = (array) data_get($draft->original_payload, '_answer_verification', []);
        if (($saved['policy_version'] ?? null) !== 1 || $saved !== $current) {
            throw new \RuntimeException('The official-answer verification state changed or this draft predates verification-aware processing. Generate a new draft.');
        }
        if ($current['status'] === 'review_required') throw new \RuntimeException($current['reason']);
    }

    private function prompt(AiAnswerRun $run, $drafts): string
    {
        $items = $drafts->map(function (AiAnswerDraft $draft) {
            $question = $draft->question;
            $evaluator = app(QuestionAnswerEvaluator::class);
            $verified = data_get($draft->original_payload, '_answer_verification.status') === 'verified';
            return [
                'draft_id' => $draft->id, 'mode' => $draft->mode, 'context_key' => $draft->id,
                'question_type' => $evaluator->questionType($question),
                'question' => $this->plain($question->question),
                'has_visual_content' => $this->imageReferences($question) !== [],
                'current_classification' => [
                    'subject' => $question->subject?->subject_name,
                ],
                'options' => collect(range(1, 6))->mapWithKeys(fn ($i) => [$i => $this->plain($question->{'option'.$i})])->filter()->all(),
                'answer_status' => $verified ? 'official_verified' : 'unverified_solve_independently',
                'stored_answer' => $verified ? [
                    'correct_option_indices' => $question->correctOptionIndices(), 'true_false' => $question->true_false,
                    'fill_blank_config' => $question->fill_blank_config, 'nat_config' => $question->nat_config,
                    'fill_blank' => $question->fill_blank,
                    'subjective_answer' => $this->plain($question->si_answer1),
                ] : null,
            ];
        })->values()->all();

        $instructions = <<<'PROMPT'
For generate_answer_explanation, independently solve the question and return its answer in the schema for its assigned question type. An existing unverified answer is not evidence and is deliberately withheld. MCQ requires exactly one option; MSQ requires every correct option; NAT requires a numerical value (zero is valid), never an option index.
For generate_explanation (and legacy validate_existing), the saved answer is fixed. Preserve it exactly and write a fresh explanation justifying that answer, even if an explanation already exists. Never propose a replacement answer. If the saved answer cannot be justified, set stored_answer_consistent to false and describe why in discrepancies; never fabricate a justification.
Missing content that this job asks you to generate is not a discrepancy. Only report a discrepancy for content that already exists or for a genuinely ambiguous/broken question.
Write a natural, self-contained solution as a knowledgeable teacher would. Let the problem determine the length: a few sentences for a simple question, detailed steps for a difficult one. There is no target word count. Include only the reasoning needed to understand the answer; avoid filler, repeated templates, generic introductions, and headings such as "Given Data".
The explanation is the final solution shown directly to students. Explain the solution naturally and confidently. Never mention AI, auditing, reviewing, stored answers, stored explanations, databases, mismatches, discrepancies, inconsistencies, or that an answer was corrected. Put all such comparison findings only in discrepancies.
Use safe HTML only: <p>, <br>, <ul>, <ol>, <li>, <strong>, <em>. Put inline mathematics in \( ... \) and display mathematics in \[ ... \]. Use valid LaTeX/MathJax, and never use Markdown fences or dollar delimiters.
Do not use emoji or decorative symbols. Inside the JSON string, escape every LaTeX backslash correctly: emit \\frac{a}{b}, never \frac{a}{b}.
Do not audit the old explanation. Return a complete new explanation for every question. All relevant answer fields, explanation, difficulty, subject, topic, and subtopic are mandatory; never return placeholders or empty required fields. Irrelevant answer types must be omitted.
Assess the intrinsic difficulty of every question for the intended exam level. Return exactly Easy, Medium, or Hard. Ignore the currently stored difficulty because it may be random.
For visual questions, inspect every attached image. An image may contain the question and all numbered choices, or the question and options may be separate images. If visible choices are numbered 1, 2, 3, 4 while stored option cells contain those numbers, map the correct visible number to the one-based position of the matching stored option cell. Never treat an image-only question as subjective merely because its HTML text is sparse.
Classify topic and subtopic using question content within ONLY its fixed assigned subject and supplied group/category context, matched by context_key. Never change or create a subject. Return the fixed subject name exactly. Reuse suitable existing topics/subtopics in that context; only propose a concise new name when necessary. The topic must belong to the fixed subject and subtopic to that topic. Do not infer classification from paper names, packages, years or other subjects. Never use generic labels such as Other, General, Miscellaneous, or Uncategorized.
Every mathematical expression, equation, formula, numerical calculation, and chemical formula/reaction MUST use renderable LaTeX inside \( ... \) or \[ ... \]. Chemistry MUST use mhchem, for example \( \ce{2H2 + O2 -> 2H2O} \). Do not use plain-text equations, Unicode super/subscripts, HTML sup/sub, images of formulas, Markdown, or unsupported LaTeX commands. Balance all delimiters, braces and environments. Use \text{} for prose inside math.
Return exactly:
{"results":[{"draft_id":1,"difficulty":"Easy|Medium|Hard","classification":{"subject":"...","topic":"...","subtopic":"..."},"answer":{"correct_option_indices":[1],"nat_config":{"version":1,"mode":"exact","value":1.5},"true_false":"True","fill_blank_answers":["x"],"subjective_answer":"..."},"explanation":"<p>...</p>","stored_answer_consistent":true,"stored_explanation_consistent":true,"discrepancies":[],"confidence":0-100}]}
Use only the answer property relevant to the question type. MCQ/MSQ indices are one-based stored option positions. NAT mode is exact, range, or tolerance.
PROMPT;

        $contexts = $drafts->mapWithKeys(
            fn (AiAnswerDraft $draft) => [(string) $draft->id => $this->curriculumContext($draft)]
        )->all();

        return $instructions."\nAdditional administrator instructions: ".($run->additional_instructions ?: 'None.')
            ."\nFixed subject and group/category contexts by context_key: ".json_encode($contexts, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            ."\nQuestions: ".json_encode($items, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function refreshRun(AiAnswerRun $run, bool $complete = false): void
    {
        $counts = $run->drafts()->selectRaw("COUNT(*) total, SUM(status NOT IN ('queued','processing')) processed, SUM(status = 'ready') ready_count, SUM(status = 'discrepancy') discrepancy_count, SUM(status = 'failed') failed_count")->first();
        $attributes = [
            'total_questions' => (int) ($counts->total ?? 0), 'processed_questions' => (int) ($counts->processed ?? 0),
            'ready_count' => (int) ($counts->ready_count ?? 0), 'discrepancy_count' => (int) ($counts->discrepancy_count ?? 0),
            'failed_count' => (int) ($counts->failed_count ?? 0),
        ];
        if ($complete || $attributes['processed_questions'] >= $attributes['total_questions']) {
            $attributes['status'] = $attributes['failed_count'] === $attributes['total_questions'] ? 'failed' : 'completed';
            $attributes['completed_at'] = now();
        }
        $run->update($attributes);
    }

    private function decodeJson(string $raw): array
    {
        $candidate = trim(preg_replace('/^```(?:json)?\s*|\s*```$/i', '', trim($raw)) ?? $raw);
        $decoded = json_decode($candidate, true);
        if (! is_array($decoded)) {
            $start = strpos($candidate, '{');
            $end = strrpos($candidate, '}');
            if ($start !== false && $end !== false && $end > $start) {
                $candidate = substr($candidate, $start, $end - $start + 1);
                $decoded = json_decode($candidate, true);
            }
        }
        if (! is_array($decoded)) {
            $candidate = preg_replace('/\\\\(?!["\\\\\/bfnrtu])/', '\\\\\\\\', $candidate) ?? $candidate;
            $candidate = preg_replace('/,\s*([}\]])/', '$1', $candidate) ?? $candidate;
            $decoded = json_decode($candidate, true);
        }
        if (! is_array($decoded)) throw new \RuntimeException('AI returned invalid JSON: '.json_last_error_msg().'.');
        return $decoded;
    }

    private function classification(mixed $value): array
    {
        $value = (array) $value;
        return [
            'subject' => $this->curriculumName($value['subject'] ?? null, 'subject'),
            'topic' => $this->curriculumName($value['topic'] ?? null, 'topic'),
            'subtopic' => $this->curriculumName($value['subtopic'] ?? null, 'subtopic'),
        ];
    }

    private function curriculumName(mixed $value, string $field): string
    {
        $name = trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags((string) $value))) ?? '');
        $name = trim($name, " \t\n\r\0\x0B-:;,.|");
        if (mb_strlen($name) < 2 || mb_strlen($name) > 150) {
            throw new \RuntimeException("AI did not provide a valid {$field} name.");
        }
        if (in_array($this->normalizeCurriculumName($name), ['other', 'general', 'miscellaneous', 'uncategorized'], true)) {
            throw new \RuntimeException("AI returned the generic {$field} name '{$name}'.");
        }
        return $name;
    }

    private function sameClassification(Question $question, array $classification): bool
    {
        $question->loadMissing(['subject', 'topic', 'stopic']);
        return $this->normalizeCurriculumName($question->subject?->subject_name) === $this->normalizeCurriculumName($classification['subject'])
            && $this->normalizeCurriculumName($question->topic?->name) === $this->normalizeCurriculumName($classification['topic'])
            && $this->normalizeCurriculumName($question->stopic?->name) === $this->normalizeCurriculumName($classification['subtopic'])
            && (int) $question->topic?->subject_id === (int) $question->subject_id
            && (int) $question->stopic?->subject_id === (int) $question->subject_id
            && (int) $question->stopic?->topic_id === (int) $question->topic_id;
    }

    private function curriculumContext(AiAnswerDraft $draft): array
    {
        if (isset($this->curriculumContexts[$draft->id])) return $this->curriculumContexts[$draft->id];
        $draft->loadMissing(['exam.groups', 'exam.category', 'exam.subcategory', 'question.groups']);
        $draft->question->loadMissing('subject');
        $this->assertFixedSubject($draft->question, ['subject' => $draft->question->subject?->subject_name]);
        $groupIds = $draft->exam->groups->pluck('id')
            ->merge($draft->question->groups->pluck('id'))->unique()->values();
        if ($groupIds->isEmpty()) {
            throw new \RuntimeException('The paper or question must be linked to a group before AI curriculum classification can run.');
        }
        $subjects = Subject::query()
            ->where('organization_id', $draft->organization_id)->whereKey($draft->question->subject_id)
            ->when($groupIds->isNotEmpty(), fn ($query) => $query->whereHas('groups', fn ($groups) => $groups->whereIn('groups.id', $groupIds)))
            ->with(['topics' => fn ($query) => $query->whereIn('group_id', $groupIds)->orderBy('name'), 'topics.stopics' => fn ($query) => $query->whereIn('group_id', $groupIds)->orderBy('name')])
            ->orderBy('subject_name')->get();

        return [
            'fixed_subject' => $draft->question->subject->subject_name,
            'category' => $draft->exam?->category?->title,
            'subcategory' => $draft->exam?->subcategory?->title,
            'groups' => $draft->exam->groups->merge($draft->question->groups)->unique('id')->map(fn ($group) => is_array($group->group_name)
                ? collect($group->group_name)->filter()->first()
                : $group->group_name)->filter()->values()->all(),
            'subjects' => $subjects->map(fn (Subject $subject) => [
                'name' => $subject->subject_name,
                'topics' => $subject->topics->unique('name')->map(fn (Topic $topic) => [
                    'name' => $topic->name,
                    'subtopics' => $topic->stopics->pluck('name')->values()->all(),
                ])->values()->all(),
            ])->values()->all(),
        ];
    }

    private function curriculumStatus(array $classification, AiAnswerDraft $draft): array
    {
        $classification = $this->classification($classification);
        $draft->loadMissing(['exam.groups', 'question.groups']);
        $groupIds = $draft->exam->groups->pluck('id')
            ->merge($draft->question->groups->pluck('id'))
            ->map(fn ($id) => (int) $id)->filter()->unique()->values();
        $this->assertFixedSubject($draft->question, $classification);
        $subject = $draft->question->subject;
        $subjectAvailable = true;
        $topics = $subject
            ? Topic::where('subject_id', $subject->id)->whereIn('group_id', $groupIds)->get()->filter(
                fn (Topic $candidate) => $this->normalizeCurriculumName($candidate->name) === $this->normalizeCurriculumName($classification['topic'])
            )
            : collect();
        $topic = $topics->first();
        $topicAvailable = $topic && $topics->pluck('group_id')->unique()->count() === $groupIds->count();
        $subtopics = $subject && $topics->isNotEmpty()
            ? Stopic::where('subject_id', $subject->id)->whereIn('group_id', $groupIds)->whereIn('topic_id', $topics->pluck('id'))->get()->filter(
                fn (Stopic $candidate) => $this->normalizeCurriculumName($candidate->name) === $this->normalizeCurriculumName($classification['subtopic'])
            )
            : collect();
        $subtopic = $subtopics->first();
        $subtopicAvailable = $subtopic && $subtopics->pluck('group_id')->unique()->count() === $groupIds->count();

        return [
            'subject' => ['name' => $classification['subject'], 'status' => $subjectAvailable ? 'existing' : 'new', 'id' => $subject?->id],
            'topic' => ['name' => $classification['topic'], 'status' => $topicAvailable ? 'existing' : 'new', 'id' => $topic?->id],
            'subtopic' => ['name' => $classification['subtopic'], 'status' => $subtopicAvailable ? 'existing' : 'new', 'id' => $subtopic?->id],
        ];
    }
    private function resolveCurriculum(AiAnswerDraft $draft, Question $question, array $classification): array
    {
        $classification = $this->classification($classification);
        $draft->loadMissing('exam.groups');
        $question->loadMissing('groups');
        $groupIds = $draft->exam->groups->pluck('id')
            ->merge($question->groups->pluck('id'))
            ->map(fn ($id) => (int) $id)->filter()->unique()->values();
        if ($groupIds->isEmpty()) {
            throw new \RuntimeException('The paper or question must be linked to a group before curriculum can be assigned.');
        }

        $this->assertFixedSubject($question, $classification);
        $subjectResult = ['model' => $question->subject, 'created' => false];
        $subject = $subjectResult['model'];

        $topicResult = $this->withCurriculumLock('topic|'.$subject->id.'|'.$this->normalizeCurriculumName($classification['topic']), function () use ($subject, $classification, $groupIds) {
            $created = false;
            $topics = $groupIds->map(function ($groupId) use ($subject, $classification, &$created) {
                $topic = Topic::where('subject_id', $subject->id)->where('group_id', $groupId)->get()->first(
                    fn (Topic $candidate) => $this->normalizeCurriculumName($candidate->name) === $this->normalizeCurriculumName($classification['topic'])
                );
                if (! $topic) {
                    $created = true;
                    $topic = Topic::create(['subject_id' => $subject->id, 'group_id' => $groupId, 'name' => $classification['topic'], 'display_order' => 0]);
                }
                return $topic;
            });
            return ['model' => $topics->first(), 'created' => $created];
        });
        $topic = $topicResult['model'];

        $subtopicResult = $this->withCurriculumLock('subtopic|'.$subject->id.'|'.$this->normalizeCurriculumName($classification['subtopic']), function () use ($subject, $classification, $groupIds) {
            $created = false;
            $subtopics = $groupIds->map(function ($groupId) use ($subject, $classification, &$created) {
                $topic = Topic::where('subject_id', $subject->id)->where('group_id', $groupId)->get()->first(
                    fn (Topic $candidate) => $this->normalizeCurriculumName($candidate->name) === $this->normalizeCurriculumName($classification['topic'])
                );
                $subtopic = Stopic::where('subject_id', $subject->id)->where('group_id', $groupId)->where('topic_id', $topic->id)->get()->first(
                    fn (Stopic $candidate) => $this->normalizeCurriculumName($candidate->name) === $this->normalizeCurriculumName($classification['subtopic'])
                );
                if (! $subtopic) {
                    $created = true;
                    $subtopic = Stopic::create([
                        'subject_id' => $subject->id, 'group_id' => $groupId, 'topic_id' => $topic->id,
                        'name' => $classification['subtopic'], 'display_order' => 0,
                    ]);
                }
                return $subtopic;
            });
            return ['model' => $subtopics->first(), 'created' => $created];
        });
        $subtopic = $subtopicResult['model'];
        $created = collect([
            ['result' => $subjectResult, 'entity_type' => 'subject', 'entity_id' => $subject->id, 'name' => $subject->subject_name],
            ['result' => $topicResult, 'entity_type' => 'topic', 'entity_id' => $topic->id, 'name' => $topic->name],
            ['result' => $subtopicResult, 'entity_type' => 'subtopic', 'entity_id' => $subtopic->id, 'name' => $subtopic->name],
        ])->filter(fn (array $item) => $item['result']['created'])
            ->map(fn (array $item) => collect($item)->except('result')->all())->values()->all();

        return [
            'assignments' => ['subject_id' => $subject->id, 'topic_id' => $topic->id, 'stopic_id' => $subtopic->id],
            'created' => $created,
        ];
    }

    private function withCurriculumLock(string $name, callable $callback): mixed
    {
        if (DB::connection()->getDriverName() !== 'mysql') return $callback();
        $lock = 'ai-curriculum:'.sha1($name);
        $result = DB::selectOne('SELECT GET_LOCK(?, 15) AS acquired', [$lock]);
        if ((int) ($result->acquired ?? 0) !== 1) throw new \RuntimeException('Timed out while checking for an existing curriculum record.');
        try {
            return $callback();
        } finally {
            DB::selectOne('SELECT RELEASE_LOCK(?) AS released', [$lock]);
        }
    }

    private function normalizeCurriculumName(mixed $value): string
    {
        $value = mb_strtolower(trim((string) $value));
        return trim(preg_replace('/[^\p{L}\p{N}]+/u', ' ', $value) ?? $value);
    }

    private function difficultyId(mixed $difficulty): int
    {
        $level = ucfirst(strtolower(trim((string) $difficulty)));
        if (! in_array($level, ['Easy', 'Medium', 'Hard'], true)) {
            throw new \RuntimeException('AI did not provide a valid Easy, Medium, or Hard difficulty.');
        }
        if (! isset($this->difficultyIds[$level])) {
            $id = Diff::query()->whereRaw('LOWER(diff_level) = ?', [strtolower($level)])->value('id');
            if (! $id) throw new \RuntimeException("The {$level} difficulty is not configured.");
            $this->difficultyIds[$level] = (int) $id;
        }
        return $this->difficultyIds[$level];
    }

    private function imageReferences(Question $question): array
    {
        $references = [];
        foreach (['question', 'option1', 'option2', 'option3', 'option4', 'option5', 'option6'] as $field) {
            preg_match_all("/<img[^>]+src=[\"']([^\"']+)[\"']/i", (string) $question->{$field}, $matches);
            foreach ((array) ($matches[1] ?? []) as $source) {
                $key = html_entity_decode(trim((string) $source));
                if ($key !== '') $references[$key] ??= $field;
            }
            $rawValue = html_entity_decode(trim(strip_tags((string) $question->{$field})));
            if ($rawValue !== '' && preg_match('/\.(?:png|jpe?g|gif|webp|svg)(?:\?.*)?$/i', $rawValue)) {
                $references[$rawValue] ??= $field;
            }
        }
        return array_slice($references, 0, 7, true);
    }

    private function visionImages(AiAnswerDraft $draft): array
    {
        $references = $this->imageReferences($draft->question);
        if ($references === []) return [];
        $images = [];
        foreach ($references as $source => $field) {
            $resolved = app(QuestionImageResolver::class)->forAi($source);
            if (! $resolved) {
                throw new \RuntimeException("The {$field} image could not be loaded for vision analysis: ".mb_substr($source, 0, 300));
            }
            $images[] = [
                'image_url' => $resolved['image_url'],
                'label' => "DRAFT_ID {$draft->id} {$field} image. Use only for this draft.",
            ];
        }
        return $images;
    }

    private function indices(mixed $indices): array
    {
        if (! is_array($indices) || $indices === []) throw new \RuntimeException('A correct option is required.');
        foreach ($indices as $index) {
            if (! is_int($index) || $index < 1 || $index > 6) throw new \RuntimeException('Correct option indices must be integers from 1 to 6.');
        }
        $values = collect((array) $indices)->map(fn ($index) => (int) $index)
            ->filter(fn ($index) => $index >= 1 && $index <= 6)->unique()->sort()->values()->all();
        if ($values === []) throw new \RuntimeException('AI did not provide a valid correct option.');
        return $values;
    }

    private function natConfig(mixed $value): array
    {
        $config = (array) $value;
        $mode = $config['mode'] ?? 'exact';
        if (! in_array($mode, ['exact', 'range', 'tolerance', 'minimum', 'maximum', 'ranges'], true)) throw new \RuntimeException('Invalid NAT answer mode.');
        $normalized = ['version' => 1, 'mode' => $mode];
        if (in_array($mode, ['minimum', 'maximum'], true)) {
            $bound = $mode === 'minimum' ? 'min' : 'max';
            if (! is_numeric($config[$bound] ?? null)) throw new \RuntimeException('A numeric NAT bound is required.');
            $normalized[$bound] = (float) $config[$bound];
        } elseif ($mode === 'ranges') {
            if (! is_array($config['ranges'] ?? null) || $config['ranges'] === []) throw new \RuntimeException('NAT answer ranges are required.');
            $normalized['ranges'] = array_map(function ($range) {
                $range = $this->natConfig(['mode' => 'range', 'min' => $range['min'] ?? null, 'max' => $range['max'] ?? null]);
                return ['min' => $range['min'], 'max' => $range['max']];
            }, $config['ranges']);
        } elseif ($mode === 'range') {
            if (! is_numeric($config['min'] ?? null) || ! is_numeric($config['max'] ?? null)) throw new \RuntimeException('AI did not provide a valid NAT range.');
            $normalized['min'] = (float) $config['min']; $normalized['max'] = (float) $config['max'];
            if ($normalized['min'] > $normalized['max']) [$normalized['min'], $normalized['max']] = [$normalized['max'], $normalized['min']];
        } else {
            if (! is_numeric($config['value'] ?? null)) throw new \RuntimeException('AI did not provide a valid NAT value.');
            $normalized['value'] = (float) $config['value'];
            if ($mode === 'tolerance') {
                if (! is_numeric($config['tolerance'] ?? null) || (float) $config['tolerance'] < 0) throw new \RuntimeException('A non-negative NAT tolerance is required.');
                $normalized['tolerance'] = (float) $config['tolerance'];
            }
        }
        foreach (['value', 'min', 'max', 'tolerance'] as $field) {
            if (isset($normalized[$field]) && ! is_finite($normalized[$field])) throw new \RuntimeException('NAT answers must be finite numbers.');
        }
        return $normalized;
    }

    private function trueFalse(mixed $value): string
    {
        $value = strtolower(trim((string) $value));
        if (! in_array($value, ['true', 'false'], true)) throw new \RuntimeException('AI did not provide a valid True/False answer.');
        return ucfirst($value);
    }

    private function applyFillBlank(array &$proposed, mixed $answers): void
    {
        $answers = collect((array) $answers)->map(fn ($value) => trim((string) $value))->filter(fn ($value) => $value !== '')->values()->all();
        if ($answers === []) throw new \RuntimeException('AI did not provide a fill-blank answer.');
        $proposed['fill_blank'] = $answers[0];
        $proposed['fill_blank_config'] = ['version' => 1, 'blanks' => [['answers' => $answers]]];
    }

    public function studentFacingExplanation(mixed $value): string
    {
        $value = (string) $value;
        $value = preg_replace_callback('/\\\\{2,}(?=[A-Za-z()\[\]])/', fn () => '\\', $value) ?? $value;
        $value = str_replace([
            "\f".'rac', "\x08".'eta', "\x08".'egin', "\x08".'ar', "\x08".'oldsymbol',
            "\t".'heta', "\t".'ext', "\t".'imes', "\t".'an', "\t".'au', "\t".'o', "\t".'ag',
            "\r".'ho', "\r".'ightarrow', "\r".'angle',
            "\n".'u', "\n".'abla', "\n".'eq', "\n".'ot', "\n".'atural',
        ], [
            '\\frac', '\\beta', '\\begin', '\\bar', '\\boldsymbol',
            '\\theta', '\\text', '\\times', '\\tan', '\\tau', '\\to', '\\tag',
            '\\rho', '\\rightarrow', '\\rangle',
            '\\nu', '\\nabla', '\\neq', '\\not', '\\natural',
        ], $value);
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value) ?? $value;
        $value = preg_replace('/[\x{1F000}-\x{1FAFF}\x{2600}-\x{27BF}]/u', '', $value) ?? $value;
        $value = preg_replace(
            '/(?:^|(?<=[.!?])\s+)(?:but\s+|however,\s+|therefore,\s+)?[^.!?]*'
            .'(?:stored (?:answer|explanation)|existing (?:answer|explanation)|provided answer|database (?:answer|explanation)|'
            .'answer (?:is |was )?inconsistent|(?:does not|doesn\'t) match)[^.!?]*(?:[.!?]|$)/iu',
            ' ',
            $value
        ) ?? $value;

        return trim(preg_replace('/[ \t]{2,}/', ' ', $value) ?? $value);
    }
    private function cleanRichText(mixed $value, int $minimum, int $maximum, string $label): string
    {
        $value = trim(strip_tags((string) $value, '<p><br><ul><ol><li><strong><em>'));
        $value = preg_replace_callback(
            '/<\/?(p|br|ul|ol|li|strong|em)\b[^>]*>/i',
            fn ($match) => str_starts_with($match[0], '</')
                ? '</'.strtolower($match[1]).'>'
                : '<'.strtolower($match[1]).'>',
            $value
        ) ?? $value;
        $length = mb_strlen(trim(strip_tags($value)));
        if ($length < $minimum) throw new \RuntimeException("AI returned a {$label} that is too short.");
        if ($length > $maximum) throw new \RuntimeException("AI returned a {$label} that is too long.");
        return $value;
    }

    private function plain(mixed $value): string
    {
        return trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags((string) $value))) ?? '');
    }
}

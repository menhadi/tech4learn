<?php

namespace App\Services;

use App\Models\Exam;
use App\Models\ExamLanguageTranslation;
use App\Models\Language;
use App\Models\Question;
use App\Models\QuestionLang;
use App\Support\AiProvider;
use App\Support\SaasAccess;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ExamTranslationService
{
    public const BATCH_SIZE = 5;

    private const QUESTION_FIELDS = [
        'question', 'option1', 'option2', 'option3', 'option4', 'option5', 'option6',
        'hint', 'explanation', 'fill_blank', 'si_answer1',
    ];

    public function translateNextBatch(Exam $exam, Language $language): array
    {
        if ($this->isEnglish($language)) {
            $this->markReady($exam, $language);

            return $this->progress($exam, $language);
        }

        SaasAccess::abortIfFeatureDisabled('ai_translation');
        abort_unless($exam->languages()->whereKey($language->id)->exists(), 422, 'This language is not enabled for the exam.');

        $lock = Cache::lock("exam-translation:{$exam->id}:{$language->id}", 180);
        if (! $lock->get()) {
            return array_merge($this->progress($exam, $language), ['status' => 'processing']);
        }

        try {
            $exam->languages()->updateExistingPivot($language->id, [
                'translation_status' => 'processing',
                'last_error' => null,
                'translating_at' => now(),
            ]);

            $questions = $exam->questions()
                ->with(['langs' => fn ($query) => $query->where('language_id', $language->id)])
                ->orderBy('questions.id')
                ->get()
                ->filter(fn (Question $question) => ! $this->questionComplete($question, $question->langs->first()))
                ->take(self::BATCH_SIZE)
                ->values();

            $examTranslation = ExamLanguageTranslation::where('exam_id', $exam->id)
                ->where('language_id', $language->id)
                ->first();
            $needsExamContent = ! $this->examContentComplete($exam, $examTranslation);

            if ($questions->isEmpty() && ! $needsExamContent) {
                $this->markReady($exam, $language);

                return $this->progress($exam, $language);
            }

            $provider = AiProvider::firstAvailable(getConfiguration(), false, 'translation');
            if (! $provider) {
                throw new \RuntimeException('No AI translation provider is configured.');
            }

            $payload = [
                'target_language' => ['id' => $language->id, 'name' => $language->name, 'code' => $language->code],
                'exam' => $needsExamContent ? [
                    'name' => $exam->name,
                    'instruction' => $exam->instruction,
                    'syllabus' => $exam->syllabus,
                ] : null,
                'questions' => $questions->map(fn (Question $question) => array_merge(
                    ['id' => $question->id],
                    collect(self::QUESTION_FIELDS)->mapWithKeys(fn ($field) => [$field => $question->{$field}])->all()
                ))->all(),
            ];

            $prompt = 'Translate the supplied exam content into '.$language->name.'. '
                .'Preserve HTML, LaTeX, numbers, option ordering, blanks, and meaning. '
                .'Do not translate or return passages. Return strict JSON with the same shape and question IDs.'
                ."\n\n".json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            $raw = AiProvider::generateText(
                $provider,
                $prompt,
                'You are a precise educational translator. Return one JSON object only. Do not omit requested fields.',
                0.1,
                12000,
                180,
                true
            );
            $translated = $this->decode($raw);

            DB::transaction(function () use ($exam, $language, $questions, $translated, $provider, $needsExamContent) {
                if ($needsExamContent) {
                    $examData = (array) ($translated['exam'] ?? []);
                    ExamLanguageTranslation::updateOrCreate(
                        ['exam_id' => $exam->id, 'language_id' => $language->id],
                        [
                            'name' => $this->translatedValue($examData, 'name', $exam->name),
                            'instruction' => $this->translatedValue($examData, 'instruction', $exam->instruction),
                            'syllabus' => $this->translatedValue($examData, 'syllabus', $exam->syllabus),
                            'translated_by' => $provider['stored_name'] ?? strtoupper((string) $provider['provider']),
                            'source_fingerprint' => $this->examFingerprint($exam),
                        ]
                    );
                }

                $byId = collect((array) ($translated['questions'] ?? []))
                    ->filter(fn ($item) => is_array($item) && isset($item['id']))
                    ->keyBy(fn ($item) => (int) $item['id']);

                foreach ($questions as $question) {
                    $item = (array) $byId->get((int) $question->id, []);
                    if ($item === []) {
                        continue;
                    }

                    $values = collect(self::QUESTION_FIELDS)
                        ->mapWithKeys(fn ($field) => [$field => $this->translatedValue($item, $field, $question->{$field})])
                        ->all();
                    $values['translated_by'] = $provider['stored_name'] ?? strtoupper((string) $provider['provider']);
                    $values['source_fingerprint'] = $this->questionFingerprint($question);

                    QuestionLang::updateOrCreate(
                        ['question_id' => $question->id, 'language_id' => $language->id],
                        $values
                    );
                }
            });

            $progress = $this->progress($exam, $language);
            if ($progress['remaining'] === 0 && $progress['exam_content_ready']) {
                $this->markReady($exam, $language);
                $progress['status'] = 'ready';
            } else {
                $exam->languages()->updateExistingPivot($language->id, [
                    'translation_status' => 'pending',
                    'translation_approved_at' => null,
                    'translation_approved_by' => null,
                    'translating_at' => null,
                ]);
                $progress['status'] = 'pending';
            }

            return $progress;
        } catch (\Throwable $exception) {
            $exam->languages()->updateExistingPivot($language->id, [
                'translation_status' => 'failed',
                'last_error' => mb_substr($exception->getMessage(), 0, 2000),
                'translating_at' => null,
            ]);
            throw $exception;
        } finally {
            $lock->release();
        }
    }

    public function progress(Exam $exam, Language $language): array
    {
        if ($this->isEnglish($language)) {
            return [
                'status' => 'ready',
                'translated' => $exam->questions()->count(),
                'remaining' => 0,
                'total' => $exam->questions()->count(),
                'exam_content_ready' => true,
            ];
        }

        $questions = $exam->questions()
            ->with(['langs' => fn ($query) => $query->where('language_id', $language->id)])
            ->get();
        $remaining = $questions
            ->filter(fn (Question $question) => ! $this->questionComplete($question, $question->langs->first()))
            ->count();
        $translation = ExamLanguageTranslation::where('exam_id', $exam->id)
            ->where('language_id', $language->id)
            ->first();
        $pivot = $exam->languages()->whereKey($language->id)->first()?->pivot;

        return [
            'status' => $pivot?->translation_status ?: 'pending',
            'translated' => $questions->count() - $remaining,
            'remaining' => $remaining,
            'total' => $questions->count(),
            'exam_content_ready' => $this->examContentComplete($exam, $translation),
        ];
    }

    public function questionFingerprint(Question $question): string
    {
        return hash('sha256', json_encode(
            collect(self::QUESTION_FIELDS)->mapWithKeys(fn ($field) => [$field => $question->{$field}])->all(),
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        ));
    }

    public function examFingerprint(Exam $exam): string
    {
        return hash('sha256', json_encode([
            'name' => $exam->name,
            'instruction' => $exam->instruction,
            'syllabus' => $exam->syllabus,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    private function markReady(Exam $exam, Language $language): void
    {
        $pivot = $exam->languages()->whereKey($language->id)->first()?->pivot;
        $approveAutomatically = $this->isEnglish($language) || (bool) $pivot?->auto_translate;
        $values = [
            'translation_status' => 'ready',
            'last_error' => null,
            'translating_at' => null,
            'translated_at' => now(),
        ];
        if ($approveAutomatically) {
            $values['translation_approved_at'] = now();
            $values['translation_approved_by'] = null;
        }
        $exam->languages()->updateExistingPivot($language->id, $values);
    }

    private function questionComplete(Question $question, ?QuestionLang $translation): bool
    {
        if (! $translation || blank($translation->question)) {
            return false;
        }
        if (! hash_equals($this->questionFingerprint($question), (string) $translation->source_fingerprint)) {
            return false;
        }
        foreach (array_diff(self::QUESTION_FIELDS, ['question']) as $field) {
            if (filled($question->{$field}) && blank($translation->{$field})) {
                return false;
            }
        }

        return true;
    }

    private function examContentComplete(Exam $exam, ?ExamLanguageTranslation $translation): bool
    {
        if (! $translation || blank($translation->name)) {
            return false;
        }
        if (! hash_equals($this->examFingerprint($exam), (string) $translation->source_fingerprint)) {
            return false;
        }
        if (filled($exam->instruction) && blank($translation->instruction)) {
            return false;
        }
        if (filled($exam->syllabus) && blank($translation->syllabus)) {
            return false;
        }

        return true;
    }

    private function translatedValue(array $payload, string $field, mixed $fallback): mixed
    {
        if (blank($fallback)) {
            return null;
        }
        $value = $payload[$field] ?? null;

        return filled($value) ? $value : null;
    }

    private function decode(?string $raw): array
    {
        $raw = trim((string) $raw);
        $raw = preg_replace('/^```(?:json)?\s*|\s*```$/i', '', $raw) ?? $raw;
        $decoded = json_decode($raw, true);
        if (! is_array($decoded)) {
            throw new \RuntimeException('AI translation returned invalid JSON.');
        }

        return $decoded;
    }

    private function isEnglish(Language $language): bool
    {
        return strtolower((string) $language->code) === 'en';
    }
}

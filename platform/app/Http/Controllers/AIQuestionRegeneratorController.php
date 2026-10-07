<?php

namespace App\Http\Controllers;

use App\Models\Question;
use App\Models\Topic;
use App\Models\Stopic;
use App\Models\Configuration;
use App\Support\AiProvider;
use App\Support\AiQuestionPrompt;
use App\Support\SaasAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AIQuestionRegeneratorController extends Controller
{
    private function tenantId(): ?int
    {
        return class_exists(\App\Support\Tenant::class) ? \App\Support\Tenant::id() : null;
    }

    /**
     * Regenerate selected questions using AI
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function regenerate(Request $request)
    {
        SaasAccess::abortIfFeatureDisabled('ai_regeneration');

        try {
            $request->validate([
                'question_ids' => 'required|array',
                'question_ids.*' => 'exists:questions,id',
            ]);

            $settings = getConfiguration();
            
            $ai = AiProvider::firstAvailable($settings, false, 'question_regeneration');
            $apiKey = $ai['key'] ?? null;
            $apiProvider = $ai['provider'] ?? null;
            $model = $ai['model'] ?? null;

            if (empty($apiKey)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No AI API key configured. Please add an API key in AI Settings.'
                ]);
            }

            $results = [];

            foreach ($request->question_ids as $questionId) {
                $original = Question::with(['subject', 'topic', 'stopic', 'groups', 'qtype', 'diff', 'language', 'passage', 'tags', 'exams.category', 'exams.subcategory', 'exams.packages.category', 'exams.packages.subcategory'])
                    ->when($this->tenantId(), function ($q, $tenantId) {
                        $q->where('organization_id', $tenantId);
                    })
                    ->find($questionId);
                
                if (!$original) {
                    $results[] = ['success' => false, 'original_id' => $questionId, 'message' => 'Question not found'];
                    continue;
                }

                $subjectName = $original->subject ? $original->subject->subject_name : 'General';
                $topicName = $original->topic ? $original->topic->name : 'General';
                $groupNames = $original->groups->pluck('group_name')->implode(', ');
                $questionType = $original->qtype ? $original->qtype->type : 'M';
                
                // Get AI generated question
                $improved = $this->getAIQuestion($original, $questionType, $apiKey, $apiProvider, $model, $settings);
                if (! $this->validGeneratedQuestion($improved, $questionType)) {
                    $results[] = [
                        'success' => false,
                        'original_id' => $original->id,
                        'message' => 'AI returned an incomplete or invalid response; no question was created.',
                    ];
                    continue;
                }
                
                // Create new question
                $newQuestion = new Question();
                
                // Copy basic fields
                $newQuestion->organization_id = $original->organization_id;
                $newQuestion->qtype_id = $original->qtype_id;
                $newQuestion->subject_id = $original->subject_id;
                $newQuestion->diff_id = $original->diff_id;
                $newQuestion->language_id = $original->language_id;
                $newQuestion->passage_id = $original->passage_id;
                $newQuestion->hint = $original->hint;
                $newQuestion->marks = $original->marks;
                $newQuestion->negative_marks = $original->negative_marks;
                $newQuestion->status = $original->status ?: 'Yes';
                $newQuestion->ai_generated = strtoupper($apiProvider);
                $newQuestion->original_question_id = $original->id; // Track source question
                
                // Only set topic_id if it exists
                if ($original->topic_id && Topic::where('id', $original->topic_id)->exists()) {
                    $newQuestion->topic_id = $original->topic_id;
                }
                
                // Only set stopic_id if it exists
                if ($original->stopic_id && Stopic::where('id', $original->stopic_id)->exists()) {
                    $newQuestion->stopic_id = $original->stopic_id;
                }
                
                // Set AI generated content
                if ($improved && isset($improved['question'])) {
                    $newQuestion->question = $improved['question'];
                    $newQuestion->explanation = $improved['explanation'] ?? '';
                    
                    if ($questionType === 'M' && isset($improved['option1'])) {
                        $newQuestion->option1 = $improved['option1'];
                        $newQuestion->option2 = $improved['option2'];
                        $newQuestion->option3 = $improved['option3'];
                        $newQuestion->option4 = $improved['option4'];
                        $correctNum = (int) ($improved['correct_option_number'] ?? 1);
                        $newQuestion->answer = (string) $correctNum;
                        $newQuestion->correct_option_indices = [$correctNum];
                    }
                    elseif ($questionType === 'T' && isset($improved['true_false_answer'])) {
                        $newQuestion->true_false = strtolower($improved['true_false_answer']);
                    }
                    elseif ($questionType === 'F' && isset($improved['fill_blank_answer'])) {
                        $newQuestion->fill_blank = $improved['fill_blank_answer'];
                    }
                    elseif ($questionType === 'S' && isset($improved['subjective_answer'])) {
                        $newQuestion->si_answer1 = $improved['subjective_answer'];
                    }
                    elseif ($questionType === 'NAT') {
                        $newQuestion->nat_config = $this->natConfigFromGenerated($improved);
                    }
                } else {
                    // Fallback: copy original with marker
                    $newQuestion->question = $original->question . "\n\n[AI Generated Variant - API Failed]";
                    $newQuestion->explanation = $original->explanation;
                    
                    if ($questionType === 'M') {
                        $newQuestion->option1 = $original->option1;
                        $newQuestion->option2 = $original->option2;
                        $newQuestion->option3 = $original->option3;
                        $newQuestion->option4 = $original->option4;
                        $newQuestion->answer = $original->answer;
                    }
                    elseif ($questionType === 'T') {
                        $newQuestion->true_false = $original->true_false;
                    }
                    elseif ($questionType === 'F') {
                        $newQuestion->fill_blank = $original->fill_blank;
                    }
                    elseif ($questionType === 'S') {
                        $newQuestion->si_answer1 = $original->si_answer1;
                    }
                }
                
                $newQuestion->save();
                
                // Sync groups
                if ($original->groups->count() > 0) {
                    $newQuestion->groups()->sync($original->groups->pluck('id')->toArray());
                }
                $newQuestion->tags()->sync($original->tags->pluck('id')->all());
                $newQuestion->exams()->sync($original->exams->pluck('id')->all());
                
                $results[] = [
                    'success' => true,
                    'original_id' => $original->id,
                    'new_id' => $newQuestion->id,
                    'ai_provider' => 'AI',
                    'message' => $improved ? 'AI generated new question' : 'Question copied (AI failed)'
                ];
            }

            return response()->json([
                'success' => true,
                'results' => $results,
                'ai_provider_used' => 'AI'
            ]);
            
        } catch (\Exception $e) {
            Log::error('AI Regenerator Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * Call AI API to generate a new question
     */
    private function validGeneratedQuestion(?array $question, string $type): bool
    {
        if (! filled($question['question'] ?? null)) return false;

        return match ($type) {
            'M' => collect(['option1', 'option2', 'option3', 'option4', 'correct_option_number'])->every(fn ($field) => filled($question[$field] ?? null))
                && in_array((string) $question['correct_option_number'], ['1', '2', '3', '4'], true),
            'T' => in_array(strtolower((string) ($question['true_false_answer'] ?? '')), ['true', 'false'], true),
            'F' => filled($question['fill_blank_answer'] ?? null),
            'S' => filled($question['subjective_answer'] ?? null),
            'NAT' => $this->natConfigFromGenerated($question) !== null,
            default => false,
        };
    }
    private function getAIQuestion(Question $original, $questionType, $apiKey, $apiProvider, $model, $settings)
    {
        try {
            if (! in_array($questionType, ['M', 'T', 'F', 'S', 'NAT'], true)) return null;
            $exam = $original->exams->first();
            $package = $exam?->packages?->first();
            $context = collect([
                'Group: '.$original->groups->pluck('group_name')->implode(', '),
                'Category: '.($exam?->category?->title ?: $package?->category?->title),
                'Subcategory: '.($exam?->subcategory?->title ?: $package?->subcategory?->title),
                'Package: '.$package?->name,
                'Exam: '.$original->exams->pluck('name')->implode(', '),
                'Subject: '.$original->subject?->subject_name,
                'Topic: '.$original->topic?->name,
                'Subtopic: '.$original->stopic?->name,
                'Difficulty: '.($original->diff?->diff_level ?: $original->diff?->type),
                'Language: '.$original->language?->name,
                'Marks: '.$original->marks.'; Negative marks: '.$original->negative_marks,
                'Tags: '.$original->tags->pluck('name')->implode(', '),
                'Passage: '.$original->passage?->name,
                'Original question: '.$original->question,
                'Original options: '.collect([$original->option1, $original->option2, $original->option3, $original->option4, $original->option5, $original->option6])->filter()->implode(' | '),
                'Original answer: '.($questionType === 'NAT' ? json_encode($original->nat_config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : ($original->answer ?: $original->true_false ?: $original->fill_blank ?: $original->si_answer1)),
                'Original explanation: '.$original->explanation,
            ])->filter(fn ($line) => ! str_ends_with($line, ': '))->implode("\n");
            $field = ['M' => 'ai_regeneration_mcq_prompt', 'T' => 'ai_regeneration_true_false_prompt', 'F' => 'ai_regeneration_fill_blank_prompt', 'S' => 'ai_regeneration_subjective_prompt', 'NAT' => 'ai_regeneration_nat_prompt'][$questionType];
            $prompt = AiQuestionPrompt::render($settings?->ai_regeneration_quality_prompt, $settings?->{$field}, $context, $questionType);

            $content = AiProvider::generateText([
                'provider' => $apiProvider,
                'key' => $apiKey,
                'model' => $model,
            ], $prompt, 'You are an exam question generator. Return only valid JSON. No markdown.', 0.7, 4096, 30);

            if (is_string($content) && trim($content) !== '') {
                $content = preg_replace('/```json\s*|\s*```/', '', $content);
                return json_decode(trim($content), true);
            }        } catch (\Exception $e) {
            Log::error("$apiProvider API failed: " . $e->getMessage());
        }
        return null;
    }

    private function natConfigFromGenerated(array $question): ?array
    {
        $mode = strtolower(trim((string) ($question['nat_mode'] ?? 'exact')));
        if (! in_array($mode, ['exact', 'range', 'tolerance'], true)) return null;

        if ($mode === 'range') {
            if (! is_numeric($question['nat_min'] ?? null) || ! is_numeric($question['nat_max'] ?? null)) return null;
            $min = (float) $question['nat_min'];
            $max = (float) $question['nat_max'];
            return ['version' => 1, 'mode' => 'range', 'min' => min($min, $max), 'max' => max($min, $max)];
        }

        if (! is_numeric($question['nat_value'] ?? null)) return null;
        $config = ['version' => 1, 'mode' => $mode, 'value' => (float) $question['nat_value']];
        if ($mode === 'tolerance') {
            if (! is_numeric($question['nat_tolerance'] ?? null)) return null;
            $config['tolerance'] = abs((float) $question['nat_tolerance']);
        }
        return $config;
    }
}

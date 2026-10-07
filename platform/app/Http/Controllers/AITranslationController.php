<?php

namespace App\Http\Controllers;

use App\Models\Question;
use App\Models\QuestionLang;
use App\Models\Configuration;
use App\Models\Language;
use App\Support\AiProvider;
use App\Support\SaasAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AITranslationController extends Controller
{
    private function tenantId(): ?int
    {
        return class_exists(\App\Support\Tenant::class) ? \App\Support\Tenant::id() : null;
    }

    public function translate(Request $request)
    {
        SaasAccess::abortIfFeatureDisabled('ai_translation');

        $request->validate([
            'question_id' => 'required|exists:questions,id',
            'target_language_id' => 'required|exists:languages,id',
        ]);

        $question = Question::query()
            ->when($this->tenantId(), function ($q, $tenantId) {
                $q->where('organization_id', $tenantId);
            })
            ->findOrFail($request->question_id);
        $targetLangId = $request->target_language_id;

        // Check if translation already exists
        $existing = QuestionLang::where('question_id', $question->id)
            ->where('language_id', $targetLangId)
            ->first();

        if ($existing) {
            return response()->json(['success' => true, 'data' => $existing]);
        }

        // Get API configuration
        $settings = getConfiguration();
        
        $ai = AiProvider::firstAvailable($settings, false, 'translation');
        $apiKey = $ai['key'] ?? null;
        $apiProvider = $ai['provider'] ?? null;
        $model = $ai['model'] ?? null;

        if (empty($apiKey)) {
            return response()->json(['success' => false, 'message' => 'No AI API key configured. Please add an API key.']);
        }

        $targetLang = Language::enabledForOrganization($question->organization_id)->findOrFail($targetLangId);
        $qtype = $question->qtype;

        // Build prompt for translation
        $prompt = "Translate the following exam question to " . $targetLang->name . ". Return ONLY valid JSON.\n\n";
        $prompt .= "Question: " . $question->question . "\n\n";

        if ($qtype && $qtype->type === 'M') {
            $prompt .= "Options:\n";
            $prompt .= "option1: " . ($question->option1 ?? '') . "\n";
            $prompt .= "option2: " . ($question->option2 ?? '') . "\n";
            $prompt .= "option3: " . ($question->option3 ?? '') . "\n";
            $prompt .= "option4: " . ($question->option4 ?? '') . "\n\n";
        }

        if ($question->fill_blank) {
            $prompt .= "Fill in the blank answer: " . $question->fill_blank . "\n\n";
        }

        if ($question->si_answer1) {
            $prompt .= "Subjective answer: " . $question->si_answer1 . "\n\n";
        }

        if ($question->explanation) {
            $prompt .= "Explanation: " . $question->explanation . "\n\n";
        }

        $prompt .= "Return JSON: {\"question\":\"text\",\"option1\":\"text\",\"option2\":\"text\",\"option3\":\"text\",\"option4\":\"text\",\"fill_blank\":\"text\",\"si_answer1\":\"text\",\"explanation\":\"text\"}";

        try {
            $content = AiProvider::generateText(
                $ai,
                $prompt,
                'You are a translator. Return only valid JSON. Translate all fields accurately.',
                0.3,
                4096,
                60
            );

            if (is_string($content) && trim($content) !== '') {
                $content = preg_replace('/```json|```/', '', $content);
                $translated = json_decode(trim($content), true);

                if ($translated) {
                    $translation = new QuestionLang();
                    $translation->question_id = $question->id;
                    $translation->language_id = $targetLangId;
                    $translation->question = $translated['question'] ?? $question->question;
                    $translation->option1 = $translated['option1'] ?? $question->option1;
                    $translation->option2 = $translated['option2'] ?? $question->option2;
                    $translation->option3 = $translated['option3'] ?? $question->option3;
                    $translation->option4 = $translated['option4'] ?? $question->option4;
                    $translation->fill_blank = $translated['fill_blank'] ?? $question->fill_blank;
                    $translation->si_answer1 = $translated['si_answer1'] ?? $question->si_answer1;
                    $translation->explanation = $translated['explanation'] ?? $question->explanation;
                    $translation->translated_by = $ai['stored_name'] ?? strtoupper((string) $apiProvider);
                    $translation->save();

                    return response()->json(['success' => true, 'data' => $translation]);
                }
            }
        } catch (\Exception $e) {
            Log::error('Translation error: ' . $e->getMessage());
        }

        return response()->json(['success' => false, 'message' => 'Translation failed']);
    }
}

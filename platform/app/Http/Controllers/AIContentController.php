<?php

namespace App\Http\Controllers;

use App\Models\Configuration;
use App\Support\AiProvider;
use App\Support\SaasAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AIContentController extends Controller
{
    /**
     * Generate AI content based on user prompt
     */
    public function generate(Request $request)
    {
        SaasAccess::abortIfFeatureDisabled('ai_content_generation');

        try {
            $request->validate([
                'prompt' => 'required|string|min:3',
                'context' => 'nullable|string'
            ]);

            $settings = getConfiguration();
            
            $ai = AiProvider::firstAvailable($settings, false, 'content_seo');
            $apiKey = $ai['key'] ?? null;
            $apiProvider = $ai['provider'] ?? null;
            $model = $ai['model'] ?? null;

            if (empty($apiKey)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No AI API key configured. Please add an API key in AI Settings.'
                ]);
            }

            // Build the prompt
            $fullPrompt = $request->prompt;
            if ($request->context) {
                $fullPrompt .= "\n\nContext: " . $request->context;
            }
            $fullPrompt .= "\n\nReturn ONLY the content. No explanations, no markdown, no meta-commentary. Just the pure educational content.";

            // Call the appropriate AI API
            $content = $this->callAI($apiProvider, $apiKey, $model, $fullPrompt);
            
            if (is_string($content) && trim($content) !== '') {
                return response()->json([
                    'success' => true,
                    'content' => $content,
                    'ai_provider' => 'AI'
                ]);
            }
            
            return response()->json([
                'success' => false,
                'message' => 'AI failed to generate content. Please try again.'
            ]);
            
        } catch (\Throwable $e) {
            Log::error('AI Content Error', [
                'message' => $e->getMessage(),
                'exception' => get_class($e),
                'organization_id' => class_exists(\App\Support\Tenant::class) ? \App\Support\Tenant::id() : null,
            ]);
            return response()->json([
                'success' => false,
                'message' => $this->publicAiError($e),
            ], 502);
        }
    }

    /**
     * Call appropriate AI API based on provider
     */
    private function callAI($provider, $apiKey, $model, $prompt)
    {
        return \App\Support\AiProvider::generateText(
            ['provider' => $provider, 'key' => $apiKey, 'model' => $model],
            $prompt,
            'You are an educational content generator. Return only the content. No explanations, no markdown, no meta-commentary.',
            0.7,
            2000,
            30
        );
    }

    private function publicAiError(\Throwable $error): string
    {
        $message = $error->getMessage();
        if (str_contains(strtolower($message), 'timed out') || str_contains(strtolower($message), 'timeout')) {
            return 'The AI provider timed out. Please try again or select another provider in AI Settings.';
        }
        if (preg_match('/\b(401|403)\b|api key|unauthori[sz]ed|authentication/i', $message)) {
            return 'The AI provider rejected its credentials. Please verify the API key in AI Settings.';
        }
        if (preg_match('/quota|rate limit|429|insufficient/i', $message)) {
            return 'The AI provider quota or rate limit was reached. Please try again later or select another provider.';
        }

        return 'AI generation failed. Please verify the selected provider and model in AI Settings, then try again.';
    }

    /**
     * Call DeepSeek API
     */
    private function callDeepSeek($apiKey, $model, $prompt)
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $apiKey,
            'Content-Type' => 'application/json',
        ])->timeout(30)->post('https://api.deepseek.com/v1/chat/completions', [
            'model' => $model,
            'messages' => [
                ['role' => 'system', 'content' => 'You are an educational content generator. Return only the content. No explanations, no markdown, no meta-commentary.'],
                ['role' => 'user', 'content' => $prompt]
            ],
            'temperature' => 0.7,
            'max_tokens' => 2000
        ]);
        
        if ($response->successful()) {
            return trim($response->json()['choices'][0]['message']['content']);
        }
        return null;
    }
    
    /**
     * Call ChatGPT API
     */
    private function callChatGPT($apiKey, $model, $prompt)
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $apiKey,
            'Content-Type' => 'application/json',
        ])->timeout(30)->post('https://api.openai.com/v1/chat/completions', [
            'model' => $model,
            'messages' => [
                ['role' => 'system', 'content' => 'You are an educational content generator. Return only the content. No explanations, no markdown, no meta-commentary.'],
                ['role' => 'user', 'content' => $prompt]
            ],
            'temperature' => 0.7,
            'max_tokens' => 2000
        ]);
        
        if ($response->successful()) {
            return trim($response->json()['choices'][0]['message']['content']);
        }
        return null;
    }
    
    /**
     * Call Google Gemini API
     */
    private function callGemini($apiKey, $model, $prompt)
    {
        $response = Http::timeout(30)->post('https://generativelanguage.googleapis.com/v1beta/models/' . rawurlencode($model) . ':generateContent?key=' . $apiKey, [
            'contents' => [
                [
                    'parts' => [
                        ['text' => "You are an educational content generator. Return only the content. No explanations, no markdown, no meta-commentary.\n\n" . $prompt]
                    ]
                ]
            ],
            'generationConfig' => [
                'temperature' => 0.7,
                'maxOutputTokens' => 2000
            ]
        ]);
        
        if ($response->successful()) {
            $content = $response->json()['candidates'][0]['content']['parts'][0]['text'] ?? '';
            return trim($content);
        }
        return null;
    }
}

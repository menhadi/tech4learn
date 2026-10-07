<?php

namespace App\Support;

use App\Models\Configuration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

class AiProvider
{
    public static function firstAvailable(?Configuration $settings, bool $requiresVision = false, ?string $task = null): ?array
    {
        return self::available($settings, $requiresVision, $task)[0] ?? null;
    }

    /**
     * Return configured providers in the administrator-selected priority order.
     * Providers without a compatible image/PDF adapter are excluded for vision.
     */
    public static function available(?Configuration $settings, bool $requiresVision = false, ?string $task = null): array
    {
        $available = [];

        foreach (self::candidateConfigurations($settings) as $config) {
            foreach (self::availableFromConfiguration($config, $requiresVision, $task) as $provider) {
                $identity = $provider['provider'].'|'.$provider['model'].'|'.$provider['key'];
                $available[$identity] ??= $provider;
            }
        }

        return array_values($available);
    }

    private static function availableFromConfiguration(?Configuration $settings, bool $requiresVision, ?string $task): array
    {
        if (! $settings) return [];

        $providers = [
            'deepseek' => [
                'provider' => 'deepseek', 'key' => $settings->deepseek_api_key ?? null,
                'model' => $requiresVision
                    ? ($settings->deepseek_vision_model ?: 'deepseek-v4-flash-vision-exp')
                    : ($settings->deepseek_model ?: 'deepseek-chat'),
                'stored_name' => 'DEEPSEEK',
            ],
            'openai' => [
                'provider' => 'chatgpt', 'key' => $settings->openai_api_key ?? null,
                'model' => $settings->openai_model ?: 'gpt-4o', 'stored_name' => 'CHATGPT',
            ],
            'google' => [
                'provider' => 'gemini', 'key' => $settings->google_gemini_api_key ?? null,
                'model' => $settings->google_gemini_model ?: 'gemini-1.5-flash', 'stored_name' => 'GEMINI',
            ],
            'anthropic' => [
                'provider' => 'claude', 'key' => $settings->anthropic_api_key ?? null,
                'model' => $settings->anthropic_model ?: 'claude-sonnet-4-5', 'stored_name' => 'CLAUDE',
            ],
        ];

        $priority = self::priority($settings, $task);
        $available = [];
        foreach ($priority as $key) {
            if (! isset($providers[$key]) || ! self::usable($providers[$key]['key'])) continue;
            $available[] = $providers[$key];
        }

        return $available;
    }

    public static function priority(?Configuration $settings, ?string $task = null): array
    {
        $allowed = ['deepseek', 'openai', 'google', 'anthropic'];
        $taskPriorities = (array) ($settings?->ai_task_priorities ?? []);
        $configuredPriority = ($task && isset($taskPriorities[$task]))
            ? $taskPriorities[$task]
            : ($settings?->ai_provider_priority ?? []);
        $configured = collect((array) $configuredPriority)
            ->map(fn ($provider) => strtolower(trim((string) $provider)))
            ->filter(fn ($provider) => in_array($provider, $allowed, true))
            ->unique()->values()->all();

        if ($configured !== []) return $configured;

        $legacy = strtolower(trim((string) ($settings?->ai_provider ?? '')));
        $legacy = match ($legacy) {
            'chatgpt' => 'openai',
            'gemini' => 'google',
            'claude' => 'anthropic',
            default => $legacy,
        };

        return in_array($legacy, $allowed, true)
            ? array_values(array_unique(array_merge([$legacy], $allowed)))
            : $allowed;
    }

    private static function candidateConfigurations(?Configuration $settings): array
    {
        $organization = SaasAccess::organization();
        if (! $organization || SaasAccess::isPlatformOrganization($organization)) return [$settings];

        $configs = [];
        if (SaasAccess::featureEnabled('ai_settings', $organization)) $configs[] = $settings;
        if (SaasAccess::featureEnabled('ai_platform_api', $organization)) {
            $platformConfig = self::platformConfiguration();
            if ($platformConfig && (! $settings || $platformConfig->id !== $settings->id)) $configs[] = $platformConfig;
        }
        return $configs;
    }

    private static function platformConfiguration(): ?Configuration
    {
        if (! Schema::hasColumn('configurations', 'organization_id') || ! Schema::hasTable('organizations')) return null;
        $realms = DB::table('organizations')->where('status', 'active')->where('settings->is_primary_platform', true)->pluck('id');
        if ($realms->count() !== 1) return null;
        return Configuration::where('organization_id', $realms->first())->first();
    }

    private static function usable(?string $key): bool
    {
        return ! empty($key) && $key !== 'sk-test-key-for-development';
    }

    public static function generateText(array $provider, string $prompt, ?string $system = null, float $temperature = 0.3, int $maxTokens = 4096, int $timeout = 90, bool $jsonMode = false): ?string
    {
        $kind = $provider['provider'] ?? null;
        $key = $provider['key'] ?? null;
        $model = $provider['model'] ?? null;
        if (! $kind || ! $key || ! $model) return null;

        if ($kind === 'gemini') {
            $text = trim(($system ? $system."\n\n" : '').$prompt);
            $generationConfig = ['temperature' => $temperature, 'maxOutputTokens' => $maxTokens];
            if ($jsonMode) $generationConfig['responseMimeType'] = 'application/json';
            $response = Http::timeout($timeout)->post('https://generativelanguage.googleapis.com/v1beta/models/'.rawurlencode($model).':generateContent?key='.$key, [
                'contents' => [['parts' => [['text' => $text]]]],
                'generationConfig' => $generationConfig,
            ]);
            self::ensureSuccessful($response, 'Gemini');
            return trim((string) ($response->json('candidates.0.content.parts.0.text') ?? ''));
        }

        if ($kind === 'claude') {
            $payload = [
                // Some current Claude models reject `temperature` instead of
                // ignoring it. Omit it so the administrator-selected model
                // controls its supported sampling behavior.
                'model' => $model, 'max_tokens' => $maxTokens,
                'messages' => [['role' => 'user', 'content' => $prompt]],
            ];
            if ($system) $payload['system'] = $system;
            $response = Http::withHeaders([
                'x-api-key' => $key, 'anthropic-version' => '2023-06-01', 'content-type' => 'application/json',
            ])->timeout($timeout)->post('https://api.anthropic.com/v1/messages', $payload);
            self::ensureSuccessful($response, 'Claude');
            return collect((array) $response->json('content', []))
                ->filter(fn ($block) => is_array($block) && isset($block['text']))
                ->pluck('text')->map(fn ($text) => trim((string) $text))->filter()->implode("\n");
        }

        $url = $kind === 'deepseek' ? 'https://api.deepseek.com/v1/chat/completions' : 'https://api.openai.com/v1/chat/completions';
        $messages = [];
        if ($system) $messages[] = ['role' => 'system', 'content' => $system];
        $messages[] = ['role' => 'user', 'content' => $prompt];
        $payload = ['model' => $model, 'messages' => $messages];
        $usesReasoningParameters = $kind === 'chatgpt' && preg_match('/^(gpt-5|o1|o3|o4)(?:-|$)/i', $model);
        if ($usesReasoningParameters) {
            $payload['max_completion_tokens'] = $maxTokens;
        } else {
            $payload['temperature'] = $temperature;
            $payload['max_tokens'] = $maxTokens;
        }
        if ($jsonMode) $payload['response_format'] = ['type' => 'json_object'];
        $response = Http::withToken($key)->timeout($timeout)->post($url, $payload);
        self::ensureSuccessful($response, $kind === 'deepseek' ? 'DeepSeek' : 'ChatGPT');
        $content = $response->json('choices.0.message.content');
        if (is_string($content)) return trim($content);
        if (is_array($content)) {
            return collect($content)->map(function ($part) {
                if (! is_array($part)) return '';
                $text = $part['text'] ?? '';
                return is_array($text) ? (string) ($text['value'] ?? '') : (string) $text;
            })->map(fn ($text) => trim($text))->filter()->implode("\n");
        }
        return '';
    }

    public static function generateVision(array $provider, string $prompt, array $images, ?string $system = null, int $maxTokens = 4096, int $timeout = 180): ?string
    {
        $kind = $provider['provider'] ?? null;
        $key = $provider['key'] ?? null;
        $model = $provider['model'] ?? null;
        if (! $kind || ! $key || ! $model) return null;
        if ($images === []) throw new \RuntimeException('No resolved question images were supplied to the vision provider.');

        if ($kind === 'gemini') {
            $parts = [['text' => trim(($system ? $system."\n\n" : '').$prompt)]];
            foreach ($images as $image) {
                $parts[] = ['text' => (string) ($image['label'] ?? 'Question image')];
                if (! preg_match('#^data:([^;]+);base64,(.+)$#s', (string) ($image['image_url'] ?? ''), $match)) {
                    throw new \RuntimeException('Gemini requires a resolved question image.');
                }
                $parts[] = ['inline_data' => ['mime_type' => $match[1], 'data' => $match[2]]];
            }
            $response = Http::timeout($timeout)->post(
                'https://generativelanguage.googleapis.com/v1beta/models/'.rawurlencode($model).':generateContent?key='.$key,
                [
                    'contents' => [['role' => 'user', 'parts' => $parts]],
                    'generationConfig' => ['temperature' => 0.1, 'maxOutputTokens' => $maxTokens, 'responseMimeType' => 'application/json'],
                ]
            );
            self::ensureSuccessful($response, 'Gemini');
            return trim((string) ($response->json('candidates.0.content.parts.0.text') ?? ''));
        }

        if ($kind === 'claude') {
            $content = [['type' => 'text', 'text' => $prompt]];
            foreach ($images as $image) {
                $content[] = ['type' => 'text', 'text' => (string) ($image['label'] ?? 'Question image')];
                $imageUrl = (string) ($image['image_url'] ?? '');
                if (preg_match('#^data:([^;]+);base64,(.+)$#s', $imageUrl, $match)) {
                    $content[] = ['type' => 'image', 'source' => [
                        'type' => 'base64', 'media_type' => $match[1], 'data' => $match[2],
                    ]];
                } else {
                    $content[] = ['type' => 'image', 'source' => ['type' => 'url', 'url' => $imageUrl]];
                }
            }
            $payload = [
                'model' => $model, 'max_tokens' => $maxTokens,
                'messages' => [['role' => 'user', 'content' => $content]],
            ];
            if ($system) $payload['system'] = $system;
            $response = Http::withHeaders([
                'x-api-key' => $key, 'anthropic-version' => '2023-06-01', 'content-type' => 'application/json',
            ])->timeout($timeout)->post('https://api.anthropic.com/v1/messages', $payload);
            self::ensureSuccessful($response, 'Claude');
            return collect((array) $response->json('content', []))
                ->filter(fn ($block) => is_array($block) && isset($block['text']))
                ->pluck('text')->map(fn ($text) => trim((string) $text))->filter()->implode("\n");
        }

        $content = [['type' => 'input_text', 'text' => trim(($system ? $system."\n\n" : '').$prompt)]];
        foreach ($images as $image) {
            $content[] = ['type' => 'input_text', 'text' => (string) ($image['label'] ?? 'Question image')];
            $content[] = ['type' => 'input_image', 'image_url' => (string) ($image['image_url'] ?? '')];
        }
        $response = Http::withToken($key)->timeout($timeout)->post(
            $kind === 'deepseek' ? 'https://api.deepseek.com/responses' : 'https://api.openai.com/v1/responses', [
            'model' => $model,
            'input' => [['role' => 'user', 'content' => $content]],
            'max_output_tokens' => $maxTokens,
        ]);
        self::ensureSuccessful($response, $kind === 'deepseek' ? 'DeepSeek' : 'ChatGPT');
        foreach ((array) $response->json('output', []) as $output) {
            foreach ((array) ($output['content'] ?? []) as $part) {
                if (($part['type'] ?? null) === 'output_text') return trim((string) ($part['text'] ?? ''));
            }
        }
        return '';
    }

    private static function ensureSuccessful($response, string $provider): void
    {
        if ($response->successful()) return;
        $message = trim((string) ($response->json('error.message') ?? $response->json('message') ?? ''));
        if ($message === '') $message = 'HTTP request failed with status '.$response->status().'.';
        throw new \RuntimeException($provider.' API error: '.mb_substr($message, 0, 1000));
    }
}

<?php

namespace App\Services;

use App\Models\Configuration;
use App\Models\Question;
use App\Support\AiProvider;

class ExamQualityAiReviewer
{
    public function review(Question $question, int $organizationId, ?string $selectedProvider = null): array
    {
        $providers = $this->providersFromConfiguration(Configuration::where('organization_id', $organizationId)->first());
        if ($selectedProvider && $selectedProvider !== 'auto') {
            $provider = collect($providers)->firstWhere('provider', $selectedProvider);
            $providers = $provider ? [$provider] : [];
        }
        if ($providers === []) throw new \RuntimeException('No AI API key is configured for this organization.');

        $prompt = $this->prompt($question);
        $errors = [];
        foreach ($providers as $provider) {
            try {
                $raw = AiProvider::generateText($provider, $prompt, 'Return strict JSON only.', 0.1, 4096, 90);
                if (! is_string($raw) || trim($raw) === '') {
                    throw new \RuntimeException('Provider returned an empty response.');
                }
                $decoded = $this->decodeJson($raw);

                return array_values(array_filter(array_map(function ($issue) use ($provider) {
                    if (! is_array($issue) || empty($issue['title'])) return null;
                    return [
                        'type' => preg_replace('/[^a-z0-9_]+/', '_', strtolower((string) ($issue['type'] ?? 'academic_review'))),
                        'severity' => in_array($issue['severity'] ?? null, ['info', 'warning', 'error', 'critical'], true) ? $issue['severity'] : 'warning',
                        'title' => mb_substr((string) $issue['title'], 0, 255),
                        'details' => (string) ($issue['details'] ?? ''),
                        'confidence' => $this->normalizedConfidence($issue['confidence'] ?? null, 50),
                        'evidence' => ['provider' => $provider['label'], 'suggestion' => $issue['suggestion'] ?? null],
                    ];
                }, (array) ($decoded['issues'] ?? []))));
            } catch (\Throwable $e) {
                $errors[] = $provider['label'].': '.$e->getMessage();
            }
        }

        throw new \RuntimeException('All configured academic-review providers failed. '.implode(' | ', $errors));
    }

    private function providersFromConfiguration(?Configuration $settings): array
    {
        if (! $settings) return [];

        return collect(AiProvider::available($settings, false, 'academic_review'))
            ->map(fn (array $provider) => [
                'provider' => $provider['provider'],
                'label' => strtolower($provider['stored_name']),
                'key' => $provider['key'],
                'model' => $provider['model'],
            ])->values()->all();
    }
    private function prompt(Question $question): string
    {
        $contentLanguage = $question->language?->name ?: 'English';
        $suggestionLanguage = app()->getLocale();
        $payload = [
            'content_language' => ['name' => $contentLanguage, 'code' => $question->language?->code],
            'suggestion_language' => $suggestionLanguage,
            'language_policy' => "Keep proposed replacement wording in {$contentLanguage}; write issue titles and details in locale {$suggestionLanguage}.",
            'type' => $question->qtype?->question_type,
            'question' => $this->plain($question->question),
            'options' => collect(range(1, 6))->map(fn ($i) => $this->plain($question->{'option'.$i}))->filter()->values()->all(),
            'stored_correct_option_indices' => $question->correctOptionIndices(),
            'true_false_answer' => $question->true_false,
            'fill_blank' => $question->fill_blank_config ?: $question->fill_blank,
            'nat' => $question->nat_config,
            'subjective_answer' => $this->plain($question->si_answer1),
            'explanation' => $this->plain($question->explanation),
        ];

        return "Act as a conservative exam quality reviewer. Check factual correctness, ambiguity, option plausibility, correct-answer consistency, explanation consistency, and whether mathematical or chemical notation is semantically correct and safely renderable. Simple chemistry must use MathJax mhchem syntax such as \\( \\ce{H2SO4} \\). Complex structures such as aromatic rings, skeletal formulae, stereochemistry, reaction mechanisms and orbitals must remain source images and must never be rewritten as linear text. Do not report style preferences. If uncertain, do not claim an error. Return only JSON: {\"issues\":[{\"type\":\"academic_error\",\"severity\":\"warning|error|critical\",\"title\":\"short title\",\"details\":\"specific reason\",\"confidence\":0-100,\"suggestion\":\"optional correction\"}]}. Empty issues is valid. Question data: ".json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function normalizedConfidence(mixed $value, float $default): float
    {
        if ($value === null || $value === '') return $default;
        $confidence = (float) $value;
        if ($confidence > 0 && $confidence <= 1) $confidence *= 100;
        return max(0, min(100, $confidence));
    }

    private function decodeJson(string $raw): array
    {
        $raw = trim(preg_replace('/^```(?:json)?|```$/m', '', trim($raw)) ?? $raw);
        $decoded = json_decode($raw, true);
        if (! is_array($decoded)) throw new \RuntimeException('AI returned invalid JSON.');
        return $decoded;
    }

    private function plain(mixed $value): string
    {
        return trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags((string) $value))) ?? '');
    }
}

<?php

namespace App\Services;

use App\Models\Configuration;
use Illuminate\Support\Facades\Http;

class MathpixOcrService
{
    public function recognize(string $imagePath, int $organizationId): array
    {
        $settings = Configuration::where('organization_id', $organizationId)->first();
        if (! $settings?->mathpix_enabled) throw new \RuntimeException('Mathpix crop-to-text is disabled in AI Settings.');
        if (blank($settings->mathpix_app_id) || blank($settings->mathpix_app_key)) throw new \RuntimeException('Configure the Mathpix App ID and App Key in AI Settings first.');
        if (! is_file($imagePath) || ! is_readable($imagePath)) throw new \RuntimeException('The selected PDF crop could not be prepared for Mathpix.');

        $stream = fopen($imagePath, 'rb');
        if (! is_resource($stream)) throw new \RuntimeException('The selected PDF crop could not be read.');
        try {
            $response = Http::timeout(60)
                ->withHeaders(['app_id' => (string) $settings->mathpix_app_id, 'app_key' => (string) $settings->mathpix_app_key])
                ->attach('file', $stream, 'exam-source-crop.png')
                ->post('https://api.mathpix.com/v3/text', ['options_json' => json_encode([
                    'formats' => ['text', 'data'], 'data_options' => ['include_latex' => true],
                    'math_inline_delimiters' => ['\\(', '\\)'], 'math_display_delimiters' => ['\\[', '\\]'], 'rm_spaces' => true,
                ], JSON_UNESCAPED_SLASHES)]);
        } finally { fclose($stream); }

        if (! $response->successful()) throw new \RuntimeException($this->failureMessage($response->status(), (array) $response->json()));
        $payload = (array) $response->json();
        $text = trim((string) ($payload['text'] ?? ''));
        if ($text === '') { $text = trim((string) ($payload['latex_styled'] ?? '')); if ($text !== '') $text = '\\['.$text.'\\]'; }
        if ($text === '') throw new \RuntimeException('Mathpix did not return recognizable text or an equation for this crop.');
        $confidence = max(0, min(100, ((float) ($payload['confidence'] ?? 0)) * 100));
        $threshold = (float) ($settings->mathpix_min_confidence ?? 70);
        return ['source' => $text, 'html' => $this->toDraftHtml($text), 'confidence' => round($confidence, 2),
            'minimum_confidence' => $threshold, 'low_confidence' => $confidence < $threshold,
            'request_id' => (string) ($payload['request_id'] ?? '')];
    }

    private function failureMessage(int $status, array $payload): string
    {
        $error = $payload['error'] ?? $payload['message'] ?? data_get($payload, 'errors.0.message', '');
        if (is_array($error)) $error = $error['message'] ?? $error['detail'] ?? json_encode($error, JSON_UNESCAPED_SLASHES);
        $providerMessage = is_scalar($error) ? trim((string) $error) : '';
        $normalized = strtolower($providerMessage);
        if (in_array($status, [402, 429], true) || str_contains($normalized, 'credit') || str_contains($normalized, 'balance') || str_contains($normalized, 'quota') || str_contains($normalized, 'limit')) {
            return 'Mathpix credits or request quota are exhausted. Add credits or increase the Mathpix limit, then try this crop again. Nothing was inserted or saved.';
        }
        if (in_array($status, [401, 403], true)) {
            return 'Mathpix rejected the App ID or App Key. Check the Mathpix credentials in AI Settings. Nothing was inserted or saved.';
        }
        if ($status >= 500) {
            return 'Mathpix is temporarily unavailable. Try again shortly. Nothing was inserted or saved.';
        }
        return $providerMessage !== '' ? 'Mathpix error: '.$providerMessage.' Nothing was inserted or saved.' : 'Mathpix could not recognize this crop. Nothing was inserted or saved.';
    }
    private function toDraftHtml(string $text): string
    {
        $escaped = htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        return collect(preg_split('/\R/u', $escaped) ?: [$escaped])->map(fn (string $line) => trim($line))
            ->filter(fn (string $line) => $line !== '')->map(fn (string $line) => '<p>'.$line.'</p>')->implode("\n");
    }
}
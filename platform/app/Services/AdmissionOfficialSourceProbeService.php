<?php

namespace App\Services;

use App\Models\AdmissionExamDefinition;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class AdmissionOfficialSourceProbeService
{
    public function __construct(private AdmissionOfficialSourcePolicy $sourcePolicy) {}

    public function probe(
        AdmissionExamDefinition $exam,
        string $sourceUrl,
        ?string $listingUrl = null
    ): array {
        $sourceUrl = $this->sourcePolicy->normalizeUrl($sourceUrl);
        $listingUrl = $listingUrl ? $this->sourcePolicy->normalizeUrl($listingUrl) : null;
        $host = $this->sourcePolicy->assertAllowed($exam, $sourceUrl, $listingUrl);
        $listingVerified = false;

        if ($listingUrl) {
            $listingResponse = $this->fetch($listingUrl);
            if (! $this->listingContains($listingResponse->body(), $sourceUrl, $listingUrl)) {
                throw new RuntimeException(
                    'The approved listing page does not link to the requested resource URL.'
                );
            }
            $listingVerified = true;
        }

        $response = $this->fetch($sourceUrl);
        $body = $response->body();
        $size = strlen($body);
        $maxBytes = (int) config('admission_prediction.probe.max_bytes', 25 * 1024 * 1024);
        if ($size > $maxBytes) {
            throw new RuntimeException("The resource exceeds the probe limit of {$maxBytes} bytes.");
        }

        $mimeType = strtolower(trim(explode(';', $response->header('Content-Type') ?: '')[0]));
        $format = $this->detectFormat($body);
        if ($mimeType === 'application/pdf' && $format !== 'pdf') {
            throw new RuntimeException('The resource claims to be a PDF but has no PDF signature.');
        }

        return [
            'source_url' => $sourceUrl,
            'source_host' => $host,
            'listing_url' => $listingUrl,
            'listing_verified' => $listingVerified,
            'http_status' => $response->status(),
            'mime_type' => $mimeType,
            'format' => $format,
            'size_bytes' => $size,
            'sha256' => hash('sha256', $body),
        ];
    }

    private function fetch(string $url): Response
    {
        $response = Http::connectTimeout(
            (int) config('admission_prediction.probe.connect_timeout_seconds', 15)
        )->timeout(
            (int) config('admission_prediction.probe.timeout_seconds', 90)
        )->withHeaders([
            'User-Agent' => 'ExamElite-OfficialSourceProbe/1.0',
            'Accept' => 'application/pdf,text/html,application/xhtml+xml,application/octet-stream',
        ])->withOptions(['allow_redirects' => false])->get($url);

        if (! $response->successful()) {
            throw new RuntimeException("Official source returned HTTP {$response->status()}.");
        }

        return $response;
    }

    private function detectFormat(string $body): string
    {
        if (str_starts_with($body, '%PDF-')) {
            return 'pdf';
        }
        if (str_starts_with($body, "PK\x03\x04")) {
            return 'zip';
        }

        $trimmed = ltrim($body);
        if (str_starts_with(strtolower($trimmed), '<!doctype html')
            || str_starts_with(strtolower($trimmed), '<html')) {
            return 'html';
        }

        return 'unknown';
    }

    private function listingContains(string $html, string $sourceUrl, string $listingUrl): bool
    {
        preg_match_all('/href\s*=\s*(["\'])(.*?)\1/is', $html, $matches);
        foreach ($matches[2] ?? [] as $href) {
            $href = html_entity_decode(trim($href), ENT_QUOTES | ENT_HTML5);
            if ($this->absoluteUrl($href, $listingUrl) === $sourceUrl) {
                return true;
            }
        }

        return false;
    }

    private function absoluteUrl(string $href, string $baseUrl): string
    {
        if (preg_match('/^https:\/\//i', $href)) {
            return $this->sourcePolicy->normalizeUrl($href);
        }

        $base = parse_url($baseUrl);
        if (! is_array($base) || empty($base['host'])) {
            return $href;
        }

        if (str_starts_with($href, '//')) {
            return $this->sourcePolicy->normalizeUrl('https:'.$href);
        }

        $origin = 'https://'.strtolower($base['host']);
        if (str_starts_with($href, '/')) {
            return $this->sourcePolicy->normalizeUrl($origin.$href);
        }

        $directory = rtrim(str_replace('\\', '/', dirname($base['path'] ?? '/')), '/');

        return $this->sourcePolicy->normalizeUrl($origin.$directory.'/'.$href);
    }
}

<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class QuestionImageResolver
{
    private const MAX_BYTES = 10485760;

    public function exists(string $source): ?bool
    {
        $source = html_entity_decode(trim($source));
        if ($source === '') return false;
        if (str_starts_with($source, 'data:image/')) return true;
        if (filter_var($source, FILTER_VALIDATE_URL)) return true;
        foreach ($this->localCandidates($source) as $path) if (is_file($path)) return true;
        if (! $this->r2Configured()) return false;
        try {
            foreach ($this->objectKeys($source) as $key) if (Storage::disk('r2')->exists($key)) return true;
            return false;
        } catch (\Throwable) {
            return null;
        }
    }

    public function forAi(string $source): ?array
    {
        $source = html_entity_decode(trim($source));
        if ($source === '') return null;
        if (str_starts_with($source, 'data:image/')) return ['source' => $source, 'image_url' => $source, 'resolved_via' => 'embedded'];

        foreach ($this->localCandidates($source) as $path) {
            if (! is_file($path)) continue;
            $data = $this->fileData($path);
            if ($data) return ['source' => $source, 'image_url' => $data, 'resolved_via' => 'local'];
        }

        if (filter_var($source, FILTER_VALIDATE_URL)) {
            try {
                $response = Http::timeout(45)->retry(2, 500)->withHeaders(['User-Agent' => 'ExamQualityAudit/1.0'])->get($source)->throw();
                $body = $response->body();
                $mime = strtok((string) $response->header('Content-Type'), ';') ?: $this->mimeFromName($source);
                if ($this->validImage($body, $mime)) return ['source' => $source, 'image_url' => 'data:'.$mime.';base64,'.base64_encode($body), 'resolved_via' => 'http'];
            } catch (\Throwable) {
                // Private/custom-domain R2 objects are attempted through the configured R2 disk.
            }
        }

        if ($this->r2Configured()) {
            try {
                foreach ($this->objectKeys($source) as $key) {
                    if (! Storage::disk('r2')->exists($key)) continue;
                    $body = Storage::disk('r2')->get($key);
                    $mime = Storage::disk('r2')->mimeType($key) ?: $this->mimeFromName($key);
                    if ($this->validImage($body, $mime)) return ['source' => $source, 'image_url' => 'data:'.$mime.';base64,'.base64_encode($body), 'resolved_via' => 'r2', 'object_key' => $key];
                }
            } catch (\Throwable) {
                return null;
            }
        }
        return null;
    }

    private function localCandidates(string $source): array
    {
        $path = ltrim((string) (parse_url($source, PHP_URL_PATH) ?: $source), '/');
        $storagePath = preg_replace('#^storage/#', '', $path);
        return array_values(array_unique([public_path($path), public_path('storage/'.$storagePath), Storage::disk('public')->path($storagePath)]));
    }

    private function objectKeys(string $source): array
    {
        $path = rawurldecode(ltrim((string) (parse_url($source, PHP_URL_PATH) ?: $source), '/'));
        $keys = [$path, preg_replace('#^(?:storage|uploads)/#', '', $path)];
        $bucket = trim((string) config('filesystems.disks.r2.bucket'));
        if ($bucket !== '' && str_starts_with($path, $bucket.'/')) $keys[] = substr($path, strlen($bucket) + 1);
        return array_values(array_unique(array_filter($keys)));
    }

    private function r2Configured(): bool
    {
        return trim((string) config('filesystems.disks.r2.bucket')) !== '' && trim((string) config('filesystems.disks.r2.endpoint')) !== '';
    }

    private function fileData(string $path): ?string
    {
        if (filesize($path) <= 0 || filesize($path) > self::MAX_BYTES) return null;
        $body = (string) file_get_contents($path);
        $mime = mime_content_type($path) ?: $this->mimeFromName($path);
        return $this->validImage($body, $mime) ? 'data:'.$mime.';base64,'.base64_encode($body) : null;
    }

    private function validImage(string $body, string $mime): bool
    {
        return $body !== '' && strlen($body) <= self::MAX_BYTES && str_starts_with(strtolower($mime), 'image/');
    }

    private function mimeFromName(string $name): string
    {
        return match (strtolower(pathinfo((string) parse_url($name, PHP_URL_PATH), PATHINFO_EXTENSION))) {
            'jpg', 'jpeg' => 'image/jpeg', 'gif' => 'image/gif', 'webp' => 'image/webp', 'svg' => 'image/svg+xml', default => 'image/png',
        };
    }
}

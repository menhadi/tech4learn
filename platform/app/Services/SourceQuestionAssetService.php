<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SourceQuestionAssetService
{
    public function download(array $urls, array $metadata, string $sourceUrl): array
    {
        $parts = [];
        foreach (['group','category','subcategory','package','exam','subject','topic','subtopic'] as $key) {
            $value = trim((string)($metadata[$key] ?? $metadata[$key.'_name'] ?? ''));
            if ($value !== '') $parts[] = Str::slug($value);
        }
        $base = 'questions/'.implode('/', $parts ?: ['unclassified']).'/'.sha1($sourceUrl);
        $assets = [];
        foreach (array_values(array_unique(array_filter($urls))) as $index => $url) {
            try {
                if (! preg_match('~^https?://~i', $url)) continue;
                $response = Http::timeout(20)->retry(1, 250)->get($url);
                if (! $response->successful() || strlen($response->body()) > 8 * 1024 * 1024) continue;
                $binary = $this->toPng($response->body());
                $extension = $binary === null ? strtolower(pathinfo(parse_url($url, PHP_URL_PATH) ?: '', PATHINFO_EXTENSION)) : 'png';
                if (! in_array($extension, ['jpg','jpeg','png','webp','gif','svg'], true)) $extension = 'bin';
                $body = $binary ?? $response->body();
                $path = $base.'/image-'.str_pad((string)($index + 1), 2, '0', STR_PAD_LEFT).'.'.$extension;
                Storage::disk('public')->put($path, $body);
                $assets[] = ['original_url' => $url, 'path' => $path, 'url' => Storage::disk('public')->url($path), 'format' => $extension];
            } catch (\Throwable) { }
        }
        return $assets;
    }

    private function toPng(string $body): ?string
    {
        if (class_exists('Imagick')) {
            try {
                $image = new \Imagick();
                $image->readImageBlob($body);
                if ($image->getNumberImages() > 1) $image->setIteratorIndex(0);
                $image->setImageFormat('png');
                $image->setImagePage(0, 0, 0, 0);
                $png = $image->getImageBlob();
                $image->clear(); $image->destroy();
                return $png ?: null;
            } catch (\Throwable) { }
        }
        if (function_exists('imagecreatefromstring') && function_exists('imagepng')) {
            $image = @imagecreatefromstring($body);
            if ($image !== false) {
                ob_start(); imagealphablending($image, false); imagesavealpha($image, true); imagepng($image); $png = ob_get_clean(); imagedestroy($image); return $png ?: null;
            }
        }
        return null;
    }
}

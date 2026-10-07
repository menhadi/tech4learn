<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;
use RuntimeException;

final class SourceExamImageContract
{
    public function normalize(array $row, string $outputRelative): array
    {
        return $this->walk($row, trim($outputRelative, '/'));
    }

    private function walk(mixed $value, string $outputRelative, bool $imageList = false): mixed
    {
        if (is_string($value)) {
            if ($imageList) {
                return $this->localUrl($value, $outputRelative);
            }

            return preg_replace_callback(
                '/(<img\b[^>]*\bsrc\s*=\s*)(["\'])(.*?)\2/iu',
                fn (array $match): string => $match[1].$match[2]
                    .$this->localUrl(html_entity_decode($match[3]), $outputRelative)
                    .$match[2],
                $value
            ) ?? $value;
        }

        if (! is_array($value)) {
            return $value;
        }

        $normalized = [];
        foreach ($value as $key => $item) {
            $insideImageList = $imageList || $key === 'extracted_images';
            if (
                $imageList
                && is_string($item)
                && in_array((string) $key, ['url', 'src', 'image_url'], true)
            ) {
                $normalized[$key] = $this->localUrl($item, $outputRelative);
                continue;
            }
            if ($imageList && is_string($item) && ! array_is_list($value)) {
                $normalized[$key] = $item;
                continue;
            }
            $normalized[$key] = $this->walk($item, $outputRelative, $insideImageList);
        }

        return $normalized;
    }

    private function localUrl(string $reference, string $outputRelative): string
    {
        $reference = trim(html_entity_decode($reference));
        $path = parse_url($reference, PHP_URL_PATH);
        $path = is_string($path) ? rawurldecode($path) : '';
        $storagePrefix = '/storage/';

        if (str_starts_with($path, $storagePrefix)) {
            $relative = ltrim(substr($path, strlen($storagePrefix)), '/');
        } else {
            $filename = basename(str_replace('\\', '/', $path));
            $relative = $outputRelative.'/'.$filename;
        }

        $expectedPrefix = $outputRelative.'/';
        if (
            $relative === ''
            || ! str_starts_with($relative, $expectedPrefix)
            || ! Storage::disk('public')->exists($relative)
        ) {
            throw new RuntimeException(
                'Extractor image reference was not downloaded into this exam folder: '
                .$reference.'. Python extractors must save image files under --out and '
                .'return URLs based on --public-prefix; CDN links are not stored.'
            );
        }

        return '/storage/'.$relative;
    }
}
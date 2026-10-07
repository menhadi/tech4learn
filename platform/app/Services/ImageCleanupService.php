<?php

namespace App\Services;

use App\Models\Configuration;
use App\Models\Exam;
use App\Models\ImageCleanupItem;
use App\Models\ImageCleanupRun;
use App\Models\Question;
use App\Models\QuestionVersion;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ImageCleanupService
{
    public const FIELDS = ['question', 'option1', 'option2', 'option3', 'option4', 'option5', 'option6', 'explanation'];

    public function discover(Exam $exam): Collection
    {
        return $exam->questions()->orderBy('exam_questions.id')->get()
            ->values()->flatMap(function (Question $question, int $paperIndex) {
                return collect(self::FIELDS)->flatMap(function (string $field) use ($question, $paperIndex) {
                    $html = (string) $question->{$field};
                    return collect($this->images($html))->map(fn (array $image) => [
                        'token' => $this->token($question->id, $field, $image['index']),
                        'question_id' => (int) $question->id,
                        'question_code' => (string) $question->question_code,
                        'paper_number' => $paperIndex + 1,
                        'field' => $field,
                        'field_label' => $this->fieldLabel($field),
                        'image_index' => $image['index'],
                        'src' => $image['src'],
                        'question_text' => Str::limit(trim(strip_tags((string) $question->question)), 140),
                    ]);
                });
            })->values();
    }

    public function resolveSelection(Exam $exam, array $tokens): Collection
    {
        $wanted = collect($tokens)->filter()->unique()->flip();
        return $this->discover($exam)->filter(fn (array $image) => $wanted->has($image['token']))->values();
    }

    public function processRun(ImageCleanupRun $run): void
    {
        $run->refresh();
        if (! in_array($run->status, ['queued', 'starting', 'running'], true) || $run->stop_requested_at) {
            return;
        }
        $run->update(['status' => 'running', 'started_at' => $run->started_at ?: now(), 'failure_message' => null]);
        foreach ($run->items()->whereIn('status', ['queued', 'processing'])->orderBy('id')->get() as $item) {
            $run->refresh();
            if ($run->status === 'stop_requested' || $run->stop_requested_at) {
                $run->items()->where('status', 'queued')->update(['status' => 'cancelled']);
                $run->update(['status' => 'cancelled', 'completed_at' => now()]);
                $this->refreshCounters($run);
                return;
            }
            $this->processItem($run, $item);
        }
        $this->refreshCounters($run);
        $run->refresh();
        if ($run->status === 'stop_requested' || $run->stop_requested_at) {
            $run->items()->where('status', 'queued')->update(['status' => 'cancelled']);
            $run->update(['status' => 'cancelled', 'completed_at' => now()]);
            $this->refreshCounters($run);
        } else {
            $run->update(['status' => 'completed', 'completed_at' => now()]);
        }
    }

    public function processItem(ImageCleanupRun $run, ImageCleanupItem $item): void
    {
        $item->update(['status' => 'processing', 'failure_message' => null]);
        $temporary = null;
        try {
            if ($run->action === 'remove_image') {
                $proposed = $this->removeImageAt((string) $item->original_field_html, (int) $item->image_index, (string) $item->original_src);
                $item->update([
                    'status' => 'ready', 'proposed_field_html' => $proposed, 'output_path' => null,
                    'provider' => 'local', 'model' => 'local-remove-image', 'request_id' => null,
                ]);
                return;
            }
            [$inputPath, $temporary] = $this->sourceFile($item->original_src);
            $result = $this->editImage($run, $item, $inputPath);
            $bytes = base64_decode((string) $result['base64'], true);
            if ($bytes === false || strlen($bytes) < 100 || @getimagesizefromstring($bytes) === false) {
                throw new \RuntimeException('The image provider did not return a valid image.');
            }
            $bytes = $this->monochromePng($bytes);
            $configuration = Configuration::where('organization_id', $run->organization_id)->firstOrFail();
            $bytes = $this->applyBranding($bytes, $configuration);
            $relative = collect(['question-images', 'ai-cleanup', 'org-'.$run->organization_id, 'run-'.$run->id, 'item-'.$item->id.'.png'])->implode('/');
            if (! Storage::disk('public')->put($relative, $bytes)) throw new \RuntimeException('The regenerated image could not be saved.');
            $url = Storage::disk('public')->url($relative);
            $proposed = $this->replaceImageAt((string) $item->original_field_html, (int) $item->image_index, (string) $item->original_src, $url);
            $item->update([
                'status' => 'ready', 'proposed_field_html' => $proposed, 'output_path' => $relative,
                'provider' => $run->provider, 'model' => $run->model, 'request_id' => $result['request_id'] ?? null,
            ]);
        } catch (\Throwable $exception) {
            report($exception);
            $item->update(['status' => 'failed', 'failure_message' => Str::limit($exception->getMessage(), 2000)]);
        } finally {
            if ($temporary && is_file($temporary)) @unlink($temporary);
            $this->refreshCounters($run);
        }
    }

    public function publish(Collection $items, int $userId): int
    {
        $published = 0;
        DB::transaction(function () use ($items, $userId, &$published) {
            foreach ($items->groupBy('question_id') as $questionItems) {
                $question = Question::where('organization_id', $questionItems->first()->organization_id)
                    ->whereKey($questionItems->first()->question_id)->lockForUpdate()->firstOrFail();
                $snapshot = app(QuestionRepairService::class)->snapshot($question);
                QuestionVersion::create(['organization_id' => $question->organization_id, 'question_id' => $question->id, 'payload' => $snapshot, 'created_by' => $userId ?: null]);
                $updates = [];
                foreach ($questionItems->groupBy('field') as $field => $fieldItems) {
                    $html = (string) $question->{$field};
                    $removeOnly = $fieldItems->first()?->model === 'local-remove-image';
                    $orderedItems = $removeOnly ? $fieldItems->sortByDesc('image_index') : $fieldItems->sortBy('image_index');
                    foreach ($orderedItems as $item) {
                        if ($item->model === 'local-remove-image') {
                            $html = $this->removeImageAt($html, (int) $item->image_index, (string) $item->original_src);
                        } else {
                            if (! $item->output_path || ! Storage::disk('public')->exists($item->output_path)) throw new \RuntimeException('A generated image file is missing; retry this item before publishing.');
                            $html = $this->replaceImageAt($html, (int) $item->image_index, (string) $item->original_src, Storage::disk('public')->url($item->output_path));
                        }
                        $item->update(['status' => 'published', 'published_by' => $userId ?: null, 'published_at' => now()]);
                        $published++;
                    }
                    $updates[$field] = $html;
                }
                $question->update($updates);
            }
        });
        return $published;
    }

    public function images(string $html): array
    {
        preg_match_all('/<img\b[^>]*\bsrc\s*=\s*(["\'])(.*?)\1[^>]*>/is', $html, $matches, PREG_SET_ORDER);
        return collect($matches)->values()->map(fn ($match, $index) => [
            'index' => $index, 'src' => html_entity_decode((string) $match[2], ENT_QUOTES | ENT_HTML5, 'UTF-8'),
        ])->all();
    }

    public function token(int $questionId, string $field, int $index): string
    {
        return rtrim(strtr(base64_encode($questionId.'|'.$field.'|'.$index), '+/', '-_'), '=');
    }

    private function replaceImageAt(string $html, int $targetIndex, string $expectedSrc, string $newSrc): string
    {
        $index = -1;
        $replaced = false;
        $result = preg_replace_callback('/<img\b[^>]*\bsrc\s*=\s*(["\'])(.*?)\1[^>]*>/is', function ($match) use (&$index, &$replaced, $targetIndex, $expectedSrc, $newSrc) {
            $index++;
            if ($index !== $targetIndex) return $match[0];
            $current = html_entity_decode((string) $match[2], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            if ($current !== $expectedSrc) throw new \RuntimeException('This image changed after the cleanup draft was created. Regenerate it from the current image.');
            $replaced = true;
            return preg_replace('/(\bsrc\s*=\s*)(["\']).*?\2/is', '$1$2'.htmlspecialchars($newSrc, ENT_QUOTES, 'UTF-8').'$2', $match[0], 1);
        }, $html);
        if (! $replaced || $result === null) throw new \RuntimeException('The selected image is no longer present in this field.');
        return $result;
    }

    private function removeImageAt(string $html, int $targetIndex, string $expectedSrc): string
    {
        $index = -1;
        $removed = false;
        $result = preg_replace_callback('/<img\b[^>]*\bsrc\s*=\s*(["\'])(.*?)\1[^>]*>/is', function ($match) use (&$index, &$removed, $targetIndex, $expectedSrc) {
            $index++;
            if ($index !== $targetIndex) return $match[0];
            $current = html_entity_decode((string) $match[2], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            if ($current !== $expectedSrc) throw new \RuntimeException('This image changed after the removal draft was created. Reload the current images.');
            $removed = true;
            return '';
        }, $html);
        if (! $removed || $result === null) throw new \RuntimeException('The selected image is no longer present in this field.');
        return $result;
    }
    private function editImage(ImageCleanupRun $run, ImageCleanupItem $item, string $path): array
    {
        $configuration = Configuration::where('organization_id', $run->organization_id)->first();
        if (! $configuration?->image_cleanup_enabled) throw new \RuntimeException('Image Cleanup Bot is disabled in Admin AI Settings.');
        $prompt = $this->prompt($run, $item);
        return match ($run->provider) {
            'local' => $this->localCleanup($path),
            'openai' => $this->openAi($configuration, $run, $path, $prompt),
            'google' => $this->google($configuration, $run, $path, $prompt),
            default => throw new \RuntimeException('The selected image cleanup provider is not supported.'),
        };
    }

    private function localCleanup(string $path): array
    {
        $bytes = file_get_contents($path);
        if ($bytes === false) throw new \RuntimeException('The existing image could not be read for local cleanup.');
        return ['base64' => base64_encode($this->localCleanPng($bytes)), 'request_id' => null];
    }
    private function openAi(Configuration $configuration, ImageCleanupRun $run, string $path, string $prompt): array
    {
        if (blank($configuration->openai_api_key)) throw new \RuntimeException('Configure the OpenAI API key in Admin AI Settings.');
        $stream = fopen($path, 'rb');
        try {
            $mime = mime_content_type($path) ?: 'image/png';
            $response = Http::timeout(180)->withToken((string) $configuration->openai_api_key)
                ->attach('image', $stream, 'source-image.png', ['Content-Type' => $mime])->post('https://api.openai.com/v1/images/edits', [
                    'model' => $run->model, 'prompt' => $prompt, 'quality' => $run->quality,
                    'size' => 'auto', 'output_format' => 'png',
                ]);
        } finally { if (is_resource($stream)) fclose($stream); }
        if (! $response->successful()) throw new \RuntimeException($this->providerError('OpenAI', $response->status(), (array) $response->json()));
        $base64 = (string) $response->json('data.0.b64_json');
        if ($base64 === '') throw new \RuntimeException('OpenAI returned no edited image.');
        return ['base64' => $base64, 'request_id' => $response->header('x-request-id')];
    }

    private function google(Configuration $configuration, ImageCleanupRun $run, string $path, string $prompt): array
    {
        if (blank($configuration->google_gemini_api_key)) throw new \RuntimeException('Configure the Gemini API key in Admin AI Settings.');
        $mime = mime_content_type($path) ?: 'image/png';
        $response = Http::timeout(180)->withHeaders(['x-goog-api-key' => (string) $configuration->google_gemini_api_key])
            ->post('https://generativelanguage.googleapis.com/v1beta/interactions', [
                'model' => $run->model,
                'input' => [
                    ['type' => 'text', 'text' => $prompt],
                    ['type' => 'image', 'mime_type' => $mime, 'data' => base64_encode(file_get_contents($path))],
                ],
                'response_format' => ['type' => 'image', 'mime_type' => 'image/png'],
            ]);
        if (! $response->successful()) throw new \RuntimeException($this->providerError('Gemini', $response->status(), (array) $response->json()));
        $payload = (array) $response->json();
        $base64 = (string) data_get($payload, 'output_image.data', '');
        if ($base64 === '') $base64 = $this->findImageData($payload) ?: '';
        if ($base64 === '') throw new \RuntimeException('Gemini returned no edited image.');
        return ['base64' => $base64, 'request_id' => (string) ($payload['id'] ?? $response->header('x-request-id'))];
    }

    private function findImageData(array $value): ?string
    {
        if (isset($value['data']) && is_string($value['data']) && str_starts_with((string) ($value['mime_type'] ?? $value['mimeType'] ?? ''), 'image/')) return $value['data'];
        foreach ($value as $child) if (is_array($child) && ($found = $this->findImageData($child))) return $found;
        return null;
    }

    private function sourceFile(string $src): array
    {
        $src = html_entity_decode(trim($src), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        if (preg_match('#^data:image/[^;]+;base64,(.+)$#is', $src, $match)) {
            $bytes = base64_decode($match[1], true);
            if ($bytes === false) throw new \RuntimeException('The embedded source image is invalid.');
            return [$this->temporaryImage($bytes), true];
        }
        $path = (string) (parse_url($src, PHP_URL_PATH) ?: $src);
        if (str_starts_with($path, '/storage/')) {
            $relative = ltrim(substr($path, strlen('/storage/')), '/');
            if (Storage::disk('public')->exists($relative)) return [Storage::disk('public')->path($relative), false];
        }
        if (! preg_match('#^https?://#i', $src)) {
            $candidate = realpath(public_path(ltrim($path, '/')));
            $public = realpath(public_path());
            if ($candidate && $public && str_starts_with($candidate, $public.DIRECTORY_SEPARATOR)) return [$candidate, false];
            throw new \RuntimeException('The existing image file could not be found.');
        }
        if (strtolower((string) parse_url($src, PHP_URL_SCHEME)) !== 'https') throw new \RuntimeException('Only HTTPS external images can be processed.');
        $host = (string) parse_url($src, PHP_URL_HOST);
        $ips = gethostbynamel($host) ?: [];
        if ($host === '' || $ips === [] || collect($ips)->contains(fn ($ip) => ! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE))) {
            throw new \RuntimeException('The external image host is not allowed.');
        }
        $response = Http::timeout(30)->withOptions(['allow_redirects' => false])->get($src);
        if (! $response->successful()) throw new \RuntimeException('The external image could not be downloaded.');
        $bytes = $response->body();
        if (strlen($bytes) > 20 * 1024 * 1024 || @getimagesizefromstring($bytes) === false) throw new \RuntimeException('The external file is not a supported image or exceeds 20 MB.');
        return [$this->temporaryImage($bytes), true];
    }

    private function localCleanPng(string $bytes): string
    {
        if (! function_exists('imagecreatefromstring') || ! function_exists('imagefilter')) {
            throw new \RuntimeException('Local cleanup requires the PHP GD extension on the worker.');
        }
        $source = @imagecreatefromstring($bytes);
        if (! $source) throw new \RuntimeException('The selected image is not readable by PHP GD.');
        $width = imagesx($source);
        $height = imagesy($source);
        $canvas = imagecreatetruecolor($width, $height);
        if (! $canvas) { imagedestroy($source); throw new \RuntimeException('Local cleanup could not allocate an image canvas.'); }
        $white = imagecolorallocate($canvas, 255, 255, 255);
        imagefill($canvas, 0, 0, $white);
        imagecopy($canvas, $source, 0, 0, 0, 0, $width, $height);
        imagefilter($canvas, IMG_FILTER_GRAYSCALE);

        $palette = [];
        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                $gray = imagecolorat($canvas, $x, $y) & 0xFF;
                $clean = $gray >= 215 ? 255 : ($gray <= 165 ? 0 : (int) round(($gray - 165) * 5.1));
                $palette[$clean] ??= imagecolorallocate($canvas, $clean, $clean, $clean);
                imagesetpixel($canvas, $x, $y, $palette[$clean]);
            }
        }
        ob_start();
        imagepng($canvas, null, 6);
        $converted = ob_get_clean();
        imagedestroy($canvas);
        imagedestroy($source);
        if (! is_string($converted) || strlen($converted) < 100) throw new \RuntimeException('Local cleanup could not encode the cleaned PNG.');
        return $converted;
    }

    private function applyBranding(string $bytes, Configuration $configuration): string
    {
        if (! $configuration->image_cleanup_branding_enabled) return $bytes;
        if (! function_exists('imagecreatefromstring')) {
            throw new \RuntimeException('Image branding requires the PHP GD extension on the worker.');
        }
        $canvas = @imagecreatefromstring($bytes);
        if (! $canvas) throw new \RuntimeException('The cleaned image could not be opened for branding.');
        $width = imagesx($canvas);
        $height = imagesy($canvas);
        $opacity = max(3, min(25, (int) ($configuration->image_cleanup_watermark_opacity ?: 8)));
        $branded = false;

        if (($configuration->image_cleanup_branding_mode ?: 'logo') === 'logo' && $configuration->logo
            && Storage::disk('public')->exists((string) $configuration->logo)) {
            $logoBytes = Storage::disk('public')->get((string) $configuration->logo);
            $logo = @imagecreatefromstring($logoBytes);
            if ($logo) {
                $targetWidth = max(80, (int) round($width * 0.34));
                $targetHeight = max(30, (int) round(imagesy($logo) * ($targetWidth / max(1, imagesx($logo)))));
                $maxHeight = max(30, (int) round($height * 0.28));
                if ($targetHeight > $maxHeight) {
                    $targetWidth = max(80, (int) round($targetWidth * ($maxHeight / $targetHeight)));
                    $targetHeight = $maxHeight;
                }
                $stamp = imagecreatetruecolor($targetWidth, $targetHeight);
                $white = imagecolorallocate($stamp, 255, 255, 255);
                imagefill($stamp, 0, 0, $white);
                imagecopyresampled($stamp, $logo, 0, 0, 0, 0, $targetWidth, $targetHeight, imagesx($logo), imagesy($logo));
                imagefilter($stamp, IMG_FILTER_GRAYSCALE);
                imagecopymerge($canvas, $stamp, (int) (($width - $targetWidth) / 2), (int) (($height - $targetHeight) / 2), 0, 0, $targetWidth, $targetHeight, $opacity);
                imagedestroy($stamp);
                imagedestroy($logo);
                $branded = true;
            }
        }

        if (! $branded) {
            $text = trim((string) $configuration->image_cleanup_watermark_text)
                ?: trim((string) ($configuration->organization_name ?: $configuration->name ?: 'ExamElite'));
            $text = Str::limit($text, 60, '');
            $font = 5;
            $textWidth = imagefontwidth($font) * strlen($text);
            $textColor = 255 - (int) round(255 * ($opacity / 100));
            $color = imagecolorallocate($canvas, $textColor, $textColor, $textColor);
            imagestring($canvas, $font, max(0, (int) (($width - $textWidth) / 2)), max(0, (int) (($height - imagefontheight($font)) / 2)), $text, $color);
        }

        ob_start();
        imagepng($canvas, null, 6);
        $output = ob_get_clean();
        imagedestroy($canvas);
        if (! is_string($output) || strlen($output) < 100) throw new \RuntimeException('The branded image could not be encoded.');
        return $output;
    }
    private function monochromePng(string $bytes): string
    {
        if (! function_exists('imagecreatefromstring') || ! function_exists('imagefilter')) return $bytes;
        $source = @imagecreatefromstring($bytes);
        if (! $source) return $bytes;
        $width = imagesx($source);
        $height = imagesy($source);
        $canvas = imagecreatetruecolor($width, $height);
        if (! $canvas) { imagedestroy($source); return $bytes; }
        $white = imagecolorallocate($canvas, 255, 255, 255);
        imagefill($canvas, 0, 0, $white);
        imagealphablending($canvas, true);
        imagecopy($canvas, $source, 0, 0, 0, 0, $width, $height);
        imagefilter($canvas, IMG_FILTER_GRAYSCALE);
        ob_start();
        imagepng($canvas, null, 6);
        $converted = ob_get_clean();
        imagedestroy($canvas);
        imagedestroy($source);
        return is_string($converted) && strlen($converted) > 100 ? $converted : $bytes;
    }
    private function temporaryImage(string $bytes): string
    {
        $path = tempnam(sys_get_temp_dir(), 'image-cleanup-');
        if (! $path || file_put_contents($path, $bytes) === false) throw new \RuntimeException('A temporary image could not be created.');
        return $path;
    }

    private function prompt(ImageCleanupRun $run, ImageCleanupItem $item): string
    {
        $action = match ($run->action) {
            'local_cleanup' => 'Apply conservative local grayscale, contrast and light-background cleanup without regenerating academic content.',
            'clean_enhance' => 'Clean scan noise, improve contrast and clarity, and recreate a clean version.',
            'redraw' => 'Faithfully redraw the educational image as a clean high-resolution diagram.',
            default => 'Remove publisher watermarks, logos and branding, reconstructing only the obscured background.',
        };
        $shared = trim((string) $run->instructions);
        $individual = trim((string) $item->instructions);
        return implode(' ', array_filter([
            $action,
            'Preserve every academic detail exactly: geometry, lines, labels, spelling, symbols, equations, arrows, dimensions, axes, values, relative positions and aspect ratio. Do not add, remove, correct or reinterpret educational content. Output strictly monochrome black and grayscale line work on a pure white background. Do not introduce blue or any other colored pixels. This is a draft for human verification.',
            $shared !== '' ? 'Shared administrator instruction for this paper: '.$shared : null,
            $individual !== '' ? 'Specific correction required for this image: '.$individual : null,
        ]));
    }

    private function providerError(string $provider, int $status, array $payload): string
    {
        $error = data_get($payload, 'error.message', $payload['message'] ?? $payload['error'] ?? '');
        if (is_array($error)) $error = json_encode($error, JSON_UNESCAPED_SLASHES);
        $message = trim((string) $error);
        if (in_array($status, [402, 429], true) || str_contains(strtolower($message), 'quota') || str_contains(strtolower($message), 'credit')) {
            return $provider.' image credits or quota are exhausted. No draft was created for this image.';
        }
        if (in_array($status, [401, 403], true)) return $provider.' rejected the configured API credentials. Check Admin AI Settings.';
        return $message !== '' ? $provider.' error: '.$message : $provider.' could not regenerate this image.';
    }

    private function refreshCounters(ImageCleanupRun $run): void
    {
        $counts = $run->items()->selectRaw("COUNT(*) total, SUM(CASE WHEN status IN ('ready','published','rejected','failed','cancelled') THEN 1 ELSE 0 END) processed, SUM(CASE WHEN status = 'ready' THEN 1 ELSE 0 END) ready_count, SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) failed_count")->first();
        $run->update(['total_images' => (int) $counts->total, 'processed_images' => (int) $counts->processed, 'ready_count' => (int) $counts->ready_count, 'failed_count' => (int) $counts->failed_count]);
    }

    private function fieldLabel(string $field): string
    {
        if ($field === 'question') return 'Question';
        if ($field === 'explanation') return 'Explanation';
        return 'Option '.chr(64 + (int) substr($field, 6));
    }
}

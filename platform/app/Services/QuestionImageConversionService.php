<?php

namespace App\Services;

use App\Models\Exam;
use App\Models\ImageConversionItem;
use App\Models\ImageConversionRun;
use App\Models\Question;
use App\Models\QuestionVersion;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class QuestionImageConversionService
{
    public const FIELDS = ['question', 'option1', 'option2', 'option3', 'option4', 'option5', 'option6', 'hint', 'explanation'];

    public function discover(Exam $exam): Collection
    {
        return $exam->questions()->orderBy('exam_questions.id')->get()->values()
            ->flatMap(function (Question $question, int $paperIndex) use ($exam) {
                return collect(self::FIELDS)->flatMap(function (string $field) use ($question, $paperIndex, $exam) {
                    return collect($this->images((string) $question->{$field}))
                        ->filter(fn (array $image) => $this->isConvertible($image['src']))
                        ->map(fn (array $image) => [
                            'token' => $this->token($exam->id, $question->id, $field, $image['index']),
                            'identity' => $question->id.'|'.$field.'|'.$image['index'],
                            'exam_id' => (int) $exam->id, 'exam_name' => (string) $exam->name,
                            'question_id' => (int) $question->id, 'question_code' => (string) $question->question_code,
                            'paper_number' => $paperIndex + 1, 'field' => $field,
                            'field_label' => $this->fieldLabel($field), 'image_index' => $image['index'],
                            'format' => strtolower(pathinfo((string) parse_url($image['src'], PHP_URL_PATH), PATHINFO_EXTENSION)),
                            'src' => $image['src'],
                            'question_text' => Str::limit(trim(strip_tags((string) $question->question)), 120),
                            'local' => $this->resolveLocal($image['src']) !== null,
                        ]);
                });
            })->values();
    }

    public function resolveTokens(Collection $exams, array $tokens): Collection
    {
        $wanted = collect($tokens)->filter()->unique()->flip();
        return $exams->flatMap(fn (Exam $exam) => $this->discover($exam))
            ->filter(fn (array $image) => $wanted->has($image['token']))->unique('identity')->values();
    }

    public function convert(ImageConversionItem $item): void
    {
        $item->update(['status' => 'processing', 'failure_message' => null]);
        try {
            $location = $this->resolveLocal($item->original_src);
            if (! $location) throw new \RuntimeException('The image is not a local server file or is outside the permitted public folders.');
            if (! is_file($location['absolute'])) throw new \RuntimeException('The original image file no longer exists on the server.');

            $extension = strtolower(pathinfo($location['absolute'], PATHINFO_EXTENSION));
            if (! in_array($extension, ['svg', 'webp'], true)) throw new \RuntimeException('Only SVG and WebP files can be converted.');
            $pngAbsolute = preg_replace('/\.(svg|webp)$/i', '.png', $location['absolute']);
            $backupAbsolute = $location['absolute'].'.backup';
            $backupAlreadyExisted = is_file($backupAbsolute);
            if (! is_file($backupAbsolute) && ! @copy($location['absolute'], $backupAbsolute)) {
                throw new \RuntimeException('The original image backup could not be created in the same folder.');
            }

            if (! is_file($pngAbsolute)) {
                $bytes = $this->toPng($location['absolute'], $extension);
                $temporary = $pngAbsolute.'.'.Str::uuid().'.tmp';
                try {
                    if (@file_put_contents($temporary, $bytes, LOCK_EX) === false) throw new \RuntimeException('The temporary PNG could not be written.');
                    if (! $this->validPngFile($temporary)) throw new \RuntimeException('PNG validation failed after conversion.');
                    if (! @rename($temporary, $pngAbsolute)) throw new \RuntimeException('The converted PNG could not be moved into place.');
                } finally {
                    if (isset($temporary) && is_file($temporary)) @unlink($temporary);
                }
            } elseif (! $backupAlreadyExisted) {
                throw new \RuntimeException('A PNG with the same base name already exists. It was left untouched; rename it or verify it before retrying.');
            } elseif (! $this->validPngFile($pngAbsolute)) {
                throw new \RuntimeException('A file with the target PNG name already exists but is not a valid PNG.');
            }

            $newSrc = preg_replace('/\.(svg|webp)(?=([?#]|$))/i', '.png', (string) $item->original_src, 1);
            if (! is_string($newSrc) || $newSrc === $item->original_src) throw new \RuntimeException('The PNG URL could not be generated.');

            DB::transaction(function () use ($item, $newSrc) {
                $question = Question::where('organization_id', $item->organization_id)->whereKey($item->question_id)->lockForUpdate()->firstOrFail();
                $html = (string) $question->{$item->field};
                $updated = $this->replaceImageAt($html, (int) $item->image_index, (string) $item->original_src, $newSrc);
                QuestionVersion::create([
                    'organization_id' => $question->organization_id, 'question_id' => $question->id,
                    'payload' => app(QuestionRepairService::class)->snapshot($question), 'created_by' => $item->run?->requested_by,
                ]);
                $question->update([$item->field => $updated]);
            });

            $item->update([
                'status' => 'success', 'source_path' => $location['display'],
                'backup_path' => $this->displayPath($backupAbsolute), 'png_path' => $this->displayPath($pngAbsolute),
                'new_src' => $newSrc, 'converted_at' => now(), 'failure_message' => null,
            ]);
        } catch (\Throwable $exception) {
            report($exception);
            $item->update(['status' => 'failed', 'failure_message' => Str::limit($exception->getMessage(), 2000)]);
        } finally {
            $this->refreshRun($item->run()->first());
        }
    }

    public function capabilities(): array
    {
        return [
            'webp' => class_exists('Imagick') || (function_exists('imagecreatefromwebp') && function_exists('imagepng')),
            'svg' => class_exists('Imagick'),
        ];
    }

    private function toPng(string $path, string $extension): string
    {
        if (class_exists('Imagick')) {
            try {
                $image = new \Imagick();
                if ($extension === 'svg') $image->setResolution(144, 144);
                $image->setBackgroundColor(new \ImagickPixel('transparent'));
                $image->readImage($path);
                if ($image->getNumberImages() > 1) $image->setIteratorIndex(0);
                $image->setImageBackgroundColor(new \ImagickPixel('transparent'));
                $image->setImageFormat('png32');
                $image->setImagePage(0, 0, 0, 0);
                $bytes = $image->getImageBlob();
                $image->clear(); $image->destroy();
                if ($bytes !== '') return $bytes;
            } catch (\Throwable $exception) {
                if ($extension === 'svg') throw new \RuntimeException('SVG conversion failed. Confirm Imagick has an SVG delegate: '.$exception->getMessage());
            }
        }
        if ($extension === 'webp' && function_exists('imagecreatefromwebp') && function_exists('imagepng')) {
            $image = @imagecreatefromwebp($path);
            if ($image !== false) {
                ob_start(); imagealphablending($image, false); imagesavealpha($image, true); imagepng($image);
                $bytes = (string) ob_get_clean(); imagedestroy($image);
                if ($bytes !== '') return $bytes;
            }
        }
        throw new \RuntimeException(strtoupper($extension).' conversion is not supported by this server. Install Imagick with SVG/WebP support.');
    }

    private function resolveLocal(string $src): ?array
    {
        $path = rawurldecode(ltrim((string) (parse_url(html_entity_decode($src), PHP_URL_PATH) ?: $src), '/'));
        if ($path === '' || str_contains(str_replace('\\', '/', $path), '../')) return null;
        $storageRelative = preg_replace('#^storage/#i', '', $path);
        $candidates = [
            ['absolute' => Storage::disk('public')->path($storageRelative), 'display' => 'storage/app/public/'.$storageRelative],
            ['absolute' => public_path($path), 'display' => 'public/'.$path],
        ];
        foreach ($candidates as $candidate) {
            $root = str_starts_with($candidate['display'], 'public/') ? public_path() : Storage::disk('public')->path('');
            $absolute = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $candidate['absolute']);
            $normalizedRoot = rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $root), DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;
            if (str_starts_with(strtolower($absolute), strtolower($normalizedRoot)) && is_file($absolute)) return $candidate;
        }
        return null;
    }

    private function images(string $html): array
    {
        preg_match_all('/<img\b[^>]*\bsrc\s*=\s*(["\'])(.*?)\1[^>]*>/is', $html, $matches, PREG_SET_ORDER);
        return collect($matches)->values()->map(fn ($match, $index) => [
            'index' => $index, 'src' => html_entity_decode((string) $match[2], ENT_QUOTES | ENT_HTML5, 'UTF-8'),
        ])->all();
    }

    private function replaceImageAt(string $html, int $targetIndex, string $expectedSrc, string $newSrc): string
    {
        $index = -1; $replaced = false;
        $result = preg_replace_callback('/<img\b[^>]*\bsrc\s*=\s*(["\'])(.*?)\1[^>]*>/is', function ($match) use (&$index, &$replaced, $targetIndex, $expectedSrc, $newSrc) {
            $index++;
            if ($index !== $targetIndex) return $match[0];
            $current = html_entity_decode((string) $match[2], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            if ($current !== $expectedSrc) throw new \RuntimeException('This image reference changed after it was selected. Reload the list and try again.');
            $replaced = true;
            return preg_replace('/(\bsrc\s*=\s*)(["\']).*?\2/is', '$1$2'.htmlspecialchars($newSrc, ENT_QUOTES, 'UTF-8').'$2', $match[0], 1);
        }, $html);
        if (! $replaced || $result === null) throw new \RuntimeException('The selected image is no longer present in this question field.');
        return $result;
    }

    private function isConvertible(string $src): bool
    {
        return in_array(strtolower(pathinfo((string) parse_url($src, PHP_URL_PATH), PATHINFO_EXTENSION)), ['svg', 'webp'], true);
    }

    private function token(int $examId, int $questionId, string $field, int $index): string
    {
        return rtrim(strtr(base64_encode($examId.'|'.$questionId.'|'.$field.'|'.$index), '+/', '-_'), '=');
    }

    private function fieldLabel(string $field): string
    {
        return str_starts_with($field, 'option') ? 'Option '.substr($field, 6) : Str::headline($field);
    }

    private function validPngFile(string $path): bool
    {
        if (! is_file($path) || filesize($path) < 8) return false;
        $handle = @fopen($path, 'rb');
        if (! $handle) return false;
        $signature = fread($handle, 8); fclose($handle);
        return $signature === "\x89PNG\r\n\x1a\n";
    }

    private function displayPath(string $absolute): string
    {
        $base = rtrim(str_replace('\\', '/', base_path()), '/').'/';
        return str_replace($base, '', str_replace('\\', '/', $absolute));
    }

    private function refreshRun(?ImageConversionRun $run): void
    {
        if (! $run) return;
        $processed = $run->items()->whereIn('status', ['success', 'failed'])->count();
        $run->update([
            'status' => $processed >= $run->total_images ? 'completed' : 'processing',
            'processed_images' => $processed, 'success_count' => $run->items()->where('status', 'success')->count(),
            'failed_count' => $run->items()->where('status', 'failed')->count(),
            'completed_at' => $processed >= $run->total_images ? now() : null,
        ]);
    }
}

<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Exam;
use App\Models\ExamQualitySource;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

class ExamQualitySourceStorage
{
    public function configuredDisk(): string
    {
        $disk = (string) config('filesystems.exam_source_disk', 'local');
        return array_key_exists($disk, (array) config('filesystems.disks')) ? $disk : 'local';
    }

    public function diskFor(ExamQualitySource $source): string
    {
        return $source->storage_disk ?: 'local';
    }

    public function store(UploadedFile $file, int $organizationId, int $examId, string $role): array
    {
        $disk = $this->configuredDisk();
        $base = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $name = (Str::slug($base) ?: 'source').'-'.Str::lower(Str::random(10)).'.pdf';
        $path = $file->storeAs($this->sourceDirectory($organizationId, $examId, $role), $name, $disk);
        return ['storage_disk' => $disk, 'file_path' => $path, 'label' => $file->getClientOriginalName()];
    }

    public function storeStaged(string $relative, string $label, int $organizationId, int $examId, string $role): array
    {
        $prefix = "tmp/source-document-discovery/{$organizationId}/";
        if (! str_starts_with($relative, $prefix) || str_contains($relative, '..') || ! Storage::disk('local')->exists($relative)) {
            throw new \RuntimeException('The staged local PDF is invalid or has expired.');
        }
        $source = Storage::disk('local')->readStream($relative);
        $disk = $this->configuredDisk();
        $name = (Str::slug(pathinfo($label, PATHINFO_FILENAME)) ?: 'source').'-'.Str::lower(Str::random(10)).'.pdf';
        $path = $this->sourceDirectory($organizationId, $examId, $role)."/{$name}";
        try {
            if (! is_resource($source) || ! Storage::disk($disk)->put($path, $source)) throw new \RuntimeException('Unable to save the selected local PDF.');
        } finally { if (is_resource($source)) fclose($source); }
        Storage::disk('local')->delete($relative);
        return ['storage_disk'=>$disk,'file_path'=>$path,'label'=>$label];
    }
    public function storeUrl(string $url, int $organizationId, int $examId, string $role, ?string $sourceLabel = null): array
    {
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        if (! in_array($scheme, ['http', 'https'], true)) {
            throw new \RuntimeException('The source URL must use HTTP or HTTPS.');
        }
        $target = $this->temporaryFile('exam-source-');
        try {
            $this->downloadUrlTo($url, $target);

            if (! is_file($target) || filesize($target) < 5
                || file_get_contents($target, false, null, 0, 5) !== '%PDF-') {
                throw new \RuntimeException('The source URL did not return a valid PDF file.');
            }
            $urlPath = urldecode((string) parse_url($url, PHP_URL_PATH));
            $originalName = trim((string) $sourceLabel) ?: (basename($urlPath) ?: ucfirst($role).'-source.pdf');
            $base = pathinfo($originalName, PATHINFO_FILENAME) ?: $originalName;
            $name = (Str::slug($base) ?: 'source').'-'.Str::lower(Str::random(10)).'.pdf';
            $disk = $this->configuredDisk();
            $path = $this->sourceDirectory($organizationId, $examId, $role)."/{$name}";
            $input = fopen($target, 'rb');
            try {
                if (! is_resource($input) || ! Storage::disk($disk)->put($path, $input)) {
                    throw new \RuntimeException('Unable to save the downloaded source PDF.');
                }
            } finally {
                if (is_resource($input)) fclose($input);
            }
            return [
                'storage_disk' => $disk,
                'file_path' => $path,
                'label' => $originalName,
                'source_url' => $url,
            ];
        } finally {
            @unlink($target);
        }
    }

    private function sourceDirectory(int $organizationId, int $examId, string $role): string
    {
        $exam = Exam::query()->with([
            'groups:id,group_name', 'category:id,title', 'subcategory:id,title',
            'packages:id,name,category_level_1,category_level_2',
        ])->find($examId);

        if (! $exam) {
            return "exam-quality-sources/org-{$organizationId}/no-group/no-category/no-subcategory/no-package/exam-{$examId}/".$this->safeRole($role);
        }

        $package = $exam->packages->sortBy('id')->first();
        $category = $exam->category ?: ($package?->category_level_1 ? Category::find($package->category_level_1) : null);
        $subcategory = $exam->subcategory ?: ($package?->category_level_2 ? Category::find($package->category_level_2) : null);
        $group = $exam->groups->sortBy('id')->first();

        return implode('/', [
            'exam-quality-sources', 'org-'.$organizationId,
            $this->entitySegment($group?->id, $group?->group_name, 'no-group'),
            $this->entitySegment($category?->id, $category?->title, 'no-category'),
            $this->entitySegment($subcategory?->id, $subcategory?->title, 'no-subcategory'),
            $this->entitySegment($package?->id, $package?->name, 'no-package'),
            $this->entitySegment($exam->id, $exam->name, 'exam-'.$exam->id),
            $this->safeRole($role),
        ]);
    }

    private function entitySegment(mixed $id, mixed $label, string $fallback): string
    {
        return $id ? $id.'-'.(Str::slug((string) $label) ?: 'item') : $fallback;
    }

    private function safeRole(string $role): string
    {
        return Str::slug($role) ?: 'source';
    }
    private function downloadUrlTo(string $url, string $target): void
    {
        try {
            $response = Http::timeout(180)->connectTimeout(20)
                ->withHeaders(['User-Agent' => 'ExamElite Source Import/1.0'])
                ->sink($target)
                ->get($url);

            if (! $response->successful()) {
                throw new \RuntimeException('The source URL returned HTTP '.$response->status().'.');
            }
        } catch (\Illuminate\Http\Client\ConnectionException $exception) {
            if (! str_contains(strtolower($exception->getMessage()), 'unsafe legacy renegotiation')) {
                throw $exception;
            }

            $this->downloadWithLegacyTls($url, $target);
        }
    }

    private function downloadWithLegacyTls(string $url, string $target): void
    {
        @unlink($target);
        $configPath = $this->temporaryFile('exam-tls-');
        file_put_contents($configPath, <<<'OPENSSL'
openssl_conf = openssl_init

[openssl_init]
ssl_conf = ssl_sect

[ssl_sect]
system_default = system_default_sect

[system_default_sect]
Options = UnsafeLegacyRenegotiation
OPENSSL);

        try {
            $process = new Process([
                'curl',
                '--fail',
                '--location',
                '--silent',
                '--show-error',
                '--max-time',
                '180',
                '--output',
                $target,
                $url,
            ], null, ['OPENSSL_CONF' => $configPath]);
            $process->setTimeout(190);
            $process->run();

            if (! $process->isSuccessful()) {
                throw new \RuntimeException(
                    'The remote PDF server uses unsupported legacy TLS and the compatibility download failed: '.
                    trim($process->getErrorOutput())
                );
            }
        } finally {
            @unlink($configPath);
        }
    }

    private function temporaryFile(string $prefix): string
    {
        $path = @tempnam(sys_get_temp_dir(), $prefix);
        if ($path === false) throw new \RuntimeException('The system temporary directory is not writable.');
        return $path;
    }

    public function delete(ExamQualitySource $source): void
    {
        if ($source->kind === 'file' && $source->file_path) Storage::disk($this->diskFor($source))->delete($source->file_path);
    }

    public function localPath(ExamQualitySource $source): array
    {
        if ($source->kind === 'url' && $source->source_url) {
            $target = $this->temporaryFile('exam-source-');
            $this->downloadUrlTo((string) $source->source_url, $target);
            if (! is_file($target) || filesize($target) < 5 || file_get_contents($target, false, null, 0, 5) !== '%PDF-') {
                @unlink($target);
                throw new \RuntimeException('The source URL did not return a valid PDF file.');
            }
            return [$target, fn () => @unlink($target)];
        }
        if (! $source->file_path) throw new \RuntimeException('The source PDF path is empty.');

        $disk = collect([
            $source->storage_disk,
            $this->configuredDisk(),
            'local',
            'r2',
        ])->filter()
            ->unique()
            ->first(function (string $candidate) use ($source) {
                if (! array_key_exists($candidate, (array) config('filesystems.disks'))) return false;
                try { return Storage::disk($candidate)->exists($source->file_path); }
                catch (\Throwable) { return false; }
            });

        if (! $disk) {
            throw new \RuntimeException('The source PDF is missing from local, configured and R2 private storage.');
        }
        if ($disk === 'local') return [Storage::disk('local')->path($source->file_path), null];

        $target = $this->temporaryFile('exam-source-');
        $input = Storage::disk($disk)->readStream($source->file_path);
        $output = fopen($target, 'wb');
        if (! is_resource($input) || ! is_resource($output)) throw new \RuntimeException('Unable to prepare the remote source PDF.');
        try { stream_copy_to_stream($input, $output); } finally { if (is_resource($input)) fclose($input); if (is_resource($output)) fclose($output); }
        return [$target, fn () => @unlink($target)];
    }
}

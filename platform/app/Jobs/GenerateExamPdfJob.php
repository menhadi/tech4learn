<?php

namespace App\Jobs;

use App\Models\ExamPdfBuild;
use App\Services\ExamDocumentLifecycleService;
use App\Services\ExamPdfCacheService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

class GenerateExamPdfJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 360;
    public int $tries = 3;

    public function __construct(public int $buildId) {}

    public function handle(ExamDocumentLifecycleService $lifecycle): void
    {
        $lock = Cache::lock('exam-pdf-build:'.$this->buildId, 400);
        if (! $lock->get()) {
            $this->release(10);
            return;
        }

        $build = ExamPdfBuild::with(['exam.organization', 'package', 'language'])->findOrFail($this->buildId);
        $temporary = null;
        $next = null;

        try {
            $build->update(['status' => 'processing', 'started_at' => now(), 'last_error' => null]);
            $exam = $build->exam;
            $package = $build->package;
            $language = $build->language;

            if ($language && strtolower((string) $language->code) !== 'en') {
                $pivot = $exam->languages()->whereKey($language->id)->first()?->pivot;
                if (! $pivot || $pivot->translation_status !== 'ready' || ! $pivot->translation_approved_at) {
                    throw new \RuntimeException('Translation is not approved.');
                }
            }

            $fingerprint = app(ExamPdfCacheService::class)
                ->fingerprint($exam, $language, $package, $build->document_type === 'solutions');
            $directory = $lifecycle->directory($exam, $package, $language, $build->document_type);
            File::ensureDirectoryExists($directory.'/versions');
            $version = $directory.'/versions/'.$fingerprint.'.pdf';
            $temporary = $directory.'/.'.$fingerprint.'.'.$build->id.'.tmp.pdf';

            if (! $this->validPdf($version)) {
                $node = env('NODE_BINARY', 'node');
                $process = new Process([$node, base_path('scripts/render-exam-pdf.mjs'), $lifecycle->printUrl($build), $temporary]);
                $process->setTimeout(330);
                $process->mustRun();
                if (! $this->validPdf($temporary)) {
                    throw new \RuntimeException('Renderer did not create a valid PDF.');
                }
                if (is_file($version)) {
                    File::delete($version);
                }
                if (! @rename($temporary, $version)) {
                    throw new \RuntimeException('Unable to preserve the generated PDF version.');
                }
                $temporary = null;
            }

            $current = $directory.'/current.pdf';
            $next = $directory.'/.current.'.$build->id.'.pdf';
            File::copy($version, $next);
            if (! $this->validPdf($next) || ! @rename($next, $current)) {
                throw new \RuntimeException('Unable to activate the generated PDF.');
            }
            $next = null;

            $build->update([
                'status' => 'ready',
                'source_fingerprint' => $fingerprint,
                'current_path' => $current,
                'version_path' => $version,
                'file_size' => filesize($current),
                'completed_at' => now(),
                'last_error' => null,
            ]);
        } catch (\Throwable $exception) {
            $build->update(['status' => 'failed', 'last_error' => mb_substr($exception->getMessage(), 0, 2000)]);
            throw $exception;
        } finally {
            if ($temporary && is_file($temporary)) File::delete($temporary);
            if ($next && is_file($next)) File::delete($next);
            $lock->release();
        }
    }

    private function validPdf(?string $path): bool
    {
        return $path && is_file($path) && filesize($path) >= 1000
            && file_get_contents($path, false, null, 0, 5) === '%PDF-';
    }
}
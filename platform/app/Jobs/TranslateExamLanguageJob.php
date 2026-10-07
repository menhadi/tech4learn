<?php

namespace App\Jobs;

use App\Models\Exam;
use App\Models\Language;
use App\Services\ExamDocumentLifecycleService;
use App\Services\ExamTranslationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;

class TranslateExamLanguageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 240;
    public int $tries = 3;

    public function __construct(public int $examId, public int $languageId) {}

    public function middleware(): array
    {
        return [
            (new WithoutOverlapping('exam-translation:'.$this->examId.':'.$this->languageId))
                ->releaseAfter(10)
                ->expireAfter(300),
        ];
    }

    public function handle(ExamTranslationService $translator, ExamDocumentLifecycleService $documents): void
    {
        $exam = Exam::findOrFail($this->examId);
        $language = Language::findOrFail($this->languageId);
        $progress = $translator->translateNextBatch($exam, $language);

        if (($progress['status'] ?? null) !== 'ready') {
            self::dispatch($exam->id, $language->id)->delay(now()->addSeconds(2));
            return;
        }

        $pivot = $exam->languages()->whereKey($language->id)->first()?->pivot;
        if ($pivot?->auto_pdf && $pivot?->translation_approved_at) {
            foreach ($exam->packages as $package) {
                if ($package->show_pdf_download ?? true) $documents->queue($exam, $package, $language, 'questions');
                if ($package->show_solution_pdf_download ?? true) $documents->queue($exam, $package, $language, 'solutions');
            }
        }
    }
}
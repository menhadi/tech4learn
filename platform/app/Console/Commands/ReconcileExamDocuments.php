<?php

namespace App\Console\Commands;

use App\Jobs\TranslateExamLanguageJob;
use App\Models\Exam;
use App\Services\ExamDocumentLifecycleService;
use App\Services\ExamTranslationService;
use Illuminate\Console\Command;

class ReconcileExamDocuments extends Command
{
    protected $signature = 'exam-documents:reconcile';
    protected $description = 'Queue stale automatic translations and PDFs';

    public function handle(ExamTranslationService $translations, ExamDocumentLifecycleService $documents): int
    {
        Exam::with(['languages', 'packages', 'pdfBuilds'])->chunkById(50, function ($exams) use ($translations, $documents) {
            foreach ($exams as $exam) {
                foreach ($exam->languages as $language) {
                    $pivot = $language->pivot;
                    $progress = $translations->progress($exam, $language);
                    $translationIsCurrent = $progress['remaining'] === 0 && $progress['exam_content_ready'];

                    if (! $translationIsCurrent) {
                        $exam->languages()->updateExistingPivot($language->id, [
                            'translation_status' => 'pending',
                            'translation_approved_at' => null,
                            'translation_approved_by' => null,
                        ]);
                        if ($pivot->auto_translate) {
                            TranslateExamLanguageJob::dispatch($exam->id, $language->id);
                        }
                        continue;
                    }

                    if ($pivot->auto_pdf && $pivot->translation_approved_at) {
                        foreach ($exam->packages as $package) {
                            if (($package->show_pdf_download ?? true) && ! $documents->ready($exam, $package, $language, 'questions')) {
                                $documents->queue($exam, $package, $language, 'questions');
                            }
                            if (($package->show_solution_pdf_download ?? true) && ! $documents->ready($exam, $package, $language, 'solutions')) {
                                $documents->queue($exam, $package, $language, 'solutions');
                            }
                        }
                    }
                }
            }
        });

        return self::SUCCESS;
    }
}
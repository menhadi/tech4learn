<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class ExamImportReportContractTest extends TestCase
{
    public function test_import_reports_row_and_pdf_change_counts_on_the_admin_page(): void
    {
        $service = file_get_contents(__DIR__.'/../../app/Services/ExamWorkbookService.php');
        $controller = file_get_contents(__DIR__.'/../../app/Http/Controllers/ExamImportExportController.php');
        $view = file_get_contents(__DIR__.'/../../resources/views/exams/import.blade.php');
        $summary = file_get_contents(__DIR__.'/../../resources/views/exams/partials/import-summary.blade.php');

        foreach (['question_pdfs', 'answer_pdfs', 'combined_pdfs', 'pdf_removals'] as $metric) {
            $this->assertStringContainsString("'{$metric}' => 0", $service);
            $this->assertStringContainsString("\$summary['{$metric}']", $controller);
            $this->assertStringContainsString("\$importSummary['{$metric}']", $summary);
        }

        $this->assertStringContainsString("->with('exam_import_summary', \$summary)", $controller);
        $this->assertStringContainsString("@include('exams.partials.import-summary')", $view);
        $this->assertStringContainsString('PDF changes planned:', $summary);
        $this->assertStringContainsString('Import completed', $summary);
    }
}

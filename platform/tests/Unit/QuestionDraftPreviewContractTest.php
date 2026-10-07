<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class QuestionDraftPreviewContractTest extends TestCase
{
    public function test_extraction_and_audit_drafts_have_rendered_preview_routes(): void
    {
        $routes = file_get_contents(__DIR__.'/../../routes/web.php');
        $sourceController = file_get_contents(__DIR__.'/../../app/Http/Controllers/SourceExamImportController.php');
        $auditController = file_get_contents(__DIR__.'/../../app/Http/Controllers/ExamQualityAuditController.php');

        $this->assertStringContainsString("name('source-exams.drafts.preview')", $routes);
        $this->assertStringContainsString("name('exam-quality.repairs.preview')", $routes);
        $this->assertStringContainsString('public function previewDraft(', $sourceController);
        $this->assertStringContainsString('public function previewRepair(', $auditController);
        $this->assertStringContainsString("view('question-drafts.preview'", $sourceController);
        $this->assertStringContainsString("view('question-drafts.preview'", $auditController);
    }

    public function test_preview_is_composed_print_ready_and_mathjax_enabled(): void
    {
        $preview = file_get_contents(__DIR__.'/../../resources/views/question-drafts/preview.blade.php');

        $this->assertStringContainsString('Rendered draft preview', $preview);
        $this->assertStringContainsString('Print / Save PDF', $preview);
        $this->assertStringContainsString('@page { size: A4;', $preview);
        $this->assertStringContainsString('tex-chtml.js', $preview);
        $this->assertStringContainsString("\$payload['question']", $preview);
        $this->assertStringContainsString("\$payload['option'.\$index]", $preview);
    }

    public function test_full_paper_previews_use_latest_saved_drafts_and_disable_caching(): void
    {
        $routes = file_get_contents(__DIR__.'/../../routes/web.php');
        $sourceController = file_get_contents(__DIR__.'/../../app/Http/Controllers/SourceExamImportController.php');
        $auditController = file_get_contents(__DIR__.'/../../app/Http/Controllers/ExamQualityAuditController.php');
        $paperPreview = file_get_contents(__DIR__.'/../../resources/views/question-drafts/paper-preview.blade.php');

        $this->assertStringContainsString("name('source-exams.preview')", $routes);
        $this->assertStringContainsString("name('exam-quality.preview')", $routes);
        $this->assertStringContainsString("name('exam-quality.question-preview')", $routes);
        $this->assertSame(1, substr_count($routes, "name('exam-quality.preview')"));
        $this->assertStringContainsString('public function previewPaper(', $sourceController);
        $this->assertStringContainsString('public function previewPaper(', $auditController);
        $this->assertStringContainsString("view('question-drafts.paper-preview'", $sourceController);
        $this->assertStringContainsString("view('question-drafts.paper-preview'", $auditController);
        $this->assertStringContainsString("private, no-store, max-age=0", $sourceController);
        $this->assertStringContainsString("private, no-store, max-age=0", $auditController);
        $this->assertStringContainsString('Full paper rendered preview', $paperPreview);
        $this->assertStringContainsString('Print / Save PDF', $paperPreview);
        $this->assertStringContainsString('@forelse($questions as $item)', $paperPreview);
        $this->assertStringContainsString('Downloaded PDF files are snapshots', $paperPreview);
    }

    public function test_scan_crop_uses_tight_padding_and_connected_shape_detection(): void
    {
        $crop = file_get_contents(__DIR__.'/../../scripts/extract-pdf-visual.py');
        $core = file_get_contents(__DIR__.'/../../scripts/source_pdf_extractor_core.py');

        $this->assertStringContainsString('shape_contours', $crop);
        $this->assertStringContainsString('gap=24', $crop);
        $this->assertStringContainsString('pad_ratio = 0.012 if used_scanned_refinement else 0.008', $crop);
        $this->assertStringContainsString('if band:', $crop);
        $this->assertStringContainsString('rect.width >= page_rect.width * 0.82', $core);
        $this->assertStringContainsString('horizontal_gap <= 120', $core);
    }
}
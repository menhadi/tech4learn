<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class ManualPdfCropContractTest extends TestCase
{
    public function test_extraction_and_audit_expose_draft_only_crop_endpoints(): void
    {
        $routes = file_get_contents(__DIR__.'/../../routes/web.php');
        $sourceController = file_get_contents(__DIR__.'/../../app/Http/Controllers/SourceExamImportController.php');
        $auditController = file_get_contents(__DIR__.'/../../app/Http/Controllers/ExamQualityAuditController.php');
        $sourceService = file_get_contents(__DIR__.'/../../app/Services/SourceExamImportService.php');

        $this->assertStringContainsString("name('source-exams.drafts.source-page')", $routes);
        $this->assertStringContainsString("name('source-exams.drafts.crop')", $routes);
        $this->assertStringContainsString("name('exam-quality.repairs.source-page')", $routes);
        $this->assertStringContainsString("name('exam-quality.repairs.crop')", $routes);
        $this->assertStringContainsString('public function sourceDraftPage(', $sourceController);
        $this->assertStringContainsString('public function cropDraftImage(', $sourceController);
        $this->assertStringContainsString('public function cropRepairImage(', $auditController);
        $this->assertStringContainsString('public function renderDraftSourcePage(', $sourceService);
        $this->assertStringContainsString('public function applyManualImageCrop(', $sourceService);
    }

    public function test_shared_cropper_auto_loads_detected_page_and_advances_options(): void
    {
        $cropper = file_get_contents(__DIR__.'/../../resources/views/question-drafts/manual-cropper.blade.php');
        $auditView = file_get_contents(__DIR__.'/../../resources/views/exam-quality/repair.blade.php');
        $sourceView = file_get_contents(__DIR__.'/../../resources/views/source-exams/edit-draft.blade.php');

        $this->assertStringContainsString('Loading the detected source page', $cropper);
        $this->assertStringContainsString('loadPage();', $cropper);
        $this->assertStringContainsString('Option images A-', $cropper);
        $this->assertStringContainsString('Crop & auto-save Option ${String.fromCharCode(64 + activeIndex)}', $cropper);
        $this->assertStringContainsString("nextTarget.value = activeIndex && activeIndex < optionCount ? 'option'+(activeIndex+1)", $cropper);
        $this->assertStringContainsString("'manual_crops'", file_get_contents(__DIR__.'/../../app/Services/QuestionRepairService.php'));
        $this->assertStringContainsString("question-drafts.manual-cropper", $auditView);
        $this->assertStringContainsString("question-drafts.manual-cropper", $sourceView);
        $this->assertStringContainsString('crop-page-jump', $cropper);
        $this->assertStringContainsString('background_mode', $cropper);
        $this->assertStringContainsString('manual-crop-saved', $cropper);
    }

    public function test_each_option_crop_replaces_only_its_own_field(): void
    {
        $sourceService = file_get_contents(__DIR__.'/../../app/Services/SourceExamImportService.php');
        $repairService = file_get_contents(__DIR__.'/../../app/Services/QuestionRepairService.php');

        $this->assertStringContainsString('$payload[$target]', $sourceService);
        $this->assertStringContainsString('$manualCrops[$target]', $sourceService);
        $this->assertStringContainsString('$proposed[$target]', $repairService);
        $this->assertStringContainsString('$manualCrops[$target]', $repairService);
    }
}
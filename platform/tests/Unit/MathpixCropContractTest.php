<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class MathpixCropContractTest extends TestCase
{
    public function test_mathpix_credentials_and_confidence_are_tenant_settings(): void
    {
        $migration = file_get_contents(__DIR__.'/../../database/migrations/2026_07_31_000001_add_mathpix_ocr_settings_to_configurations.php');
        $model = file_get_contents(__DIR__.'/../../app/Models/Configuration.php');
        $controller = file_get_contents(__DIR__.'/../../app/Http/Controllers/ConfigurationController.php');
        $view = file_get_contents(__DIR__.'/../../resources/views/configurations/ai.blade.php');

        foreach (['mathpix_enabled', 'mathpix_app_id', 'mathpix_app_key', 'mathpix_min_confidence'] as $field) {
            $this->assertStringContainsString($field, $migration);
            $this->assertStringContainsString($field, $model);
            $this->assertStringContainsString($field, $controller);
            $this->assertStringContainsString($field, $view);
        }
        $this->assertStringContainsString("Schema::hasColumn('configurations'", $migration);
    }

    public function test_mathpix_uses_server_side_v3_text_and_requires_preview_confirmation(): void
    {
        $service = file_get_contents(__DIR__.'/../../app/Services/MathpixOcrService.php');
        $cropper = file_get_contents(__DIR__.'/../../resources/views/question-drafts/manual-cropper.blade.php');
        $continuous = file_get_contents(__DIR__.'/../../resources/views/question-drafts/continuous-cropper-script.blade.php');

        $this->assertStringContainsString('https://api.mathpix.com/v3/text', $service);
        $this->assertStringContainsString("'app_id'", $service);
        $this->assertStringContainsString("'app_key'", $service);
        $this->assertStringContainsString('math_inline_delimiters', $service);
        $this->assertStringContainsString('Equation / text (Mathpix)', $cropper);
        $this->assertStringContainsString('mathpix-review-panel', $cropper);
        $this->assertStringContainsString('ExamEliteMathPreview', $cropper);
        $this->assertStringContainsString('how it will appear', $cropper);
        $this->assertStringContainsString('Insert &amp; save draft', $cropper);
        $this->assertStringContainsString("body.set('confirmed','1')", $cropper);
        $this->assertStringContainsString("body.set('confirmed','1')", $continuous);
        $this->assertStringContainsString('preview_required', file_get_contents(__DIR__.'/../../app/Http/Controllers/ExamController.php'));
        $this->assertStringContainsString('preview_required', file_get_contents(__DIR__.'/../../app/Http/Controllers/ExamQualityAuditController.php'));
        $this->assertStringContainsString('preview_required', file_get_contents(__DIR__.'/../../app/Http/Controllers/SourceExamImportController.php'));
    }

    public function test_mathpix_quota_and_authentication_failures_are_explained_without_saving(): void
    {
        $service = file_get_contents(__DIR__.'/../../app/Services/MathpixOcrService.php');

        $this->assertStringContainsString('credits or request quota are exhausted', $service);
        $this->assertStringContainsString('rejected the App ID or App Key', $service);
        $this->assertStringContainsString('Nothing was inserted or saved', $service);
    }
    public function test_confirmed_ocr_saves_only_to_draft_payloads(): void
    {
        $repair = file_get_contents(__DIR__.'/../../app/Services/QuestionRepairService.php');
        $source = file_get_contents(__DIR__.'/../../app/Services/SourceExamImportService.php');

        $this->assertStringContainsString('public function applyManualOcrText(', $repair);
        $this->assertStringContainsString("'proposed_payload' => \$proposed", $repair);
        $this->assertStringContainsString("'manual_mathpix_ocr'", $repair);
        $this->assertStringContainsString('public function applyManualOcrText(', $source);
        $this->assertStringContainsString("'payload' => \$payload", $source);
        $this->assertStringContainsString("'manual_mathpix_ocr'", $source);
    }
}
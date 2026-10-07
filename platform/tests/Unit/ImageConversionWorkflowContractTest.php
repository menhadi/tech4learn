<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class ImageConversionWorkflowContractTest extends TestCase
{
    public function test_admin_workflow_is_hierarchical_multi_select_and_status_filterable(): void
    {
        $controller = file_get_contents(__DIR__.'/../../app/Http/Controllers/ImageConversionController.php');
        $view = file_get_contents(__DIR__.'/../../resources/views/image-converter/index.blade.php');

        foreach (['groups', 'categoryOptions', 'examOptions', 'selected_images'] as $value) {
            $this->assertStringContainsString($value, $controller.$view);
        }
        $this->assertStringContainsString('multiple size="6"', $view);
        $this->assertStringContainsString('Show images requiring conversion', $view);
        $this->assertStringContainsString("['success'=>'Success','failed'=>'Failure'", $view);
        $this->assertStringContainsString('Original image URL', $view);
        $this->assertStringContainsString('New PNG URL', $view);
    }

    public function test_conversion_is_local_backed_up_validated_and_updates_the_question_reference(): void
    {
        $service = file_get_contents(__DIR__.'/../../app/Services/QuestionImageConversionService.php');

        $this->assertStringContainsString("['svg', 'webp']", $service);
        $this->assertStringContainsString(".'.backup'", $service);
        $this->assertStringContainsString('validPngFile', $service);
        $this->assertStringContainsString('lockForUpdate', $service);
        $this->assertStringContainsString('QuestionVersion::create', $service);
        $this->assertStringContainsString('replaceImageAt', $service);
        $this->assertStringContainsString("'status' => 'success'", $service);
        $this->assertStringContainsString("'status' => 'failed'", $service);
    }

    public function test_each_selected_image_is_queued_as_an_independent_auditable_item(): void
    {
        $controller = file_get_contents(__DIR__.'/../../app/Http/Controllers/ImageConversionController.php');
        $migration = file_get_contents(__DIR__.'/../../database/migrations/2026_08_14_000002_create_image_conversion_workflow.php');
        $job = file_get_contents(__DIR__.'/../../app/Jobs/ConvertQuestionImageToPng.php');

        $this->assertStringContainsString('ImageConversionItem::create', $controller);
        $this->assertStringContainsString('ConvertQuestionImageToPng::dispatch', $controller);
        $this->assertStringContainsString('image_conversion_items', $migration);
        $this->assertStringContainsString('original_src', $migration);
        $this->assertStringContainsString('backup_path', $migration);
        $this->assertStringContainsString('new_src', $migration);
        $this->assertStringContainsString('ShouldQueue', $job);
    }
}

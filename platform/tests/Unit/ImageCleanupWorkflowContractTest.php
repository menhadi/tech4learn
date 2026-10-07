<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class ImageCleanupWorkflowContractTest extends TestCase
{
    public function test_workflow_is_tenant_scoped_draft_only_and_stoppable(): void
    {
        $migration = file_get_contents(__DIR__.'/../../database/migrations/2026_08_01_000003_create_image_cleanup_workflow.php');
        $controller = file_get_contents(__DIR__.'/../../app/Http/Controllers/ImageCleanupController.php');
        $service = file_get_contents(__DIR__.'/../../app/Services/ImageCleanupService.php');
        $show = file_get_contents(__DIR__.'/../../resources/views/image-cleanup/show.blade.php');

        foreach (['image_cleanup_runs', 'image_cleanup_items', 'organization_id', 'stop_requested_at'] as $value) {
            $this->assertStringContainsString($value, $migration);
        }
        $this->assertStringContainsString("'status' => 'stop_requested'", $controller);
        $this->assertStringContainsString('The current paid image may finish', $controller);
        $this->assertStringContainsString('QuestionVersion::create', $service);
        $this->assertStringContainsString('Nothing changes live until Publish selected', $show);
    }

    public function test_admin_selects_a_real_image_edit_provider_without_fallback(): void
    {
        $settings = file_get_contents(__DIR__.'/../../resources/views/configurations/ai.blade.php');
        $service = file_get_contents(__DIR__.'/../../app/Services/ImageCleanupService.php');

        foreach (['image_cleanup_provider', 'image_cleanup_openai_model', 'image_cleanup_google_model', 'image_cleanup_quality'] as $field) {
            $this->assertStringContainsString($field, $settings);
        }
        $this->assertStringContainsString('https://api.openai.com/v1/images/edits', $service);
        $this->assertStringContainsString('https://generativelanguage.googleapis.com/v1beta/interactions', $service);
        $this->assertStringContainsString('No provider fallback', $settings);
    }

    public function test_each_selected_existing_image_becomes_an_independent_item(): void
    {
        $controller = file_get_contents(__DIR__.'/../../app/Http/Controllers/ImageCleanupController.php');
        $service = file_get_contents(__DIR__.'/../../app/Services/ImageCleanupService.php');
        $index = file_get_contents(__DIR__.'/../../resources/views/image-cleanup/index.blade.php');

        $this->assertStringContainsString("'selected_images' => ['nullable', 'required_if:selection_mode,selected_images', 'array'", $controller);
        $this->assertStringContainsString('ImageCleanupItem::create', $controller);
        $this->assertStringContainsString('foreach ($run->items()', $service);
        $this->assertStringContainsString('Select all visible', $index);
        $this->assertStringContainsString('name="selected_images[]"', $index);
    }

    public function test_full_and_multiple_papers_only_queue_discovered_images(): void
    {
        $controller = file_get_contents(__DIR__.'/../../app/Http/Controllers/ImageCleanupController.php');
        $index = file_get_contents(__DIR__.'/../../resources/views/image-cleanup/index.blade.php');

        $this->assertStringContainsString("'exam_ids' => ['nullable', 'required_if:selection_mode,all_images'", $controller);
        $this->assertStringContainsString('$service->discover($exam)', $controller);
        $this->assertStringContainsString("one submission has at most 5,000 image-processing items", $controller);
        $this->assertStringContainsString('Process every existing image in selected papers', $index);
        $this->assertStringContainsString('No PDF pages are sent', $index);
        $this->assertStringContainsString('name="exam_ids[]"', $index);
    }
    public function test_filtered_select_all_and_admin_bulk_publish_are_available(): void
    {
        $controller = file_get_contents(__DIR__.'/../../app/Http/Controllers/ImageCleanupController.php');
        $index = file_get_contents(__DIR__.'/../../resources/views/image-cleanup/index.blade.php');
        $routes = file_get_contents(__DIR__.'/../../routes/web.php');

        $this->assertStringContainsString("'all_filtered'", $controller);
        $this->assertStringContainsString('filteredExamQuery', $controller);
        $this->assertStringContainsString('selectionSummary', $controller);
        $this->assertStringContainsString('publishRuns', $controller);
        $this->assertStringContainsString("where('status', 'ready')", $controller);
        $this->assertStringContainsString('Select all ${matchingTotal} matching paper(s)', $index);
        $this->assertStringContainsString('Publish all ready', $index);
        $this->assertStringContainsString('even though review is pending', $index);
        $this->assertStringContainsString('image-cleanup.exams.selection-summary', $routes);
        $this->assertStringContainsString('image-cleanup.publish-runs', $routes);
    }
    public function test_corrective_prompts_can_reprocess_one_image_or_the_unpublished_paper(): void
    {
        $controller = file_get_contents(__DIR__.'/../../app/Http/Controllers/ImageCleanupController.php');
        $service = file_get_contents(__DIR__.'/../../app/Services/ImageCleanupService.php');
        $show = file_get_contents(__DIR__.'/../../resources/views/image-cleanup/show.blade.php');
        $migration = file_get_contents(__DIR__.'/../../database/migrations/2026_08_02_000001_add_instructions_to_image_cleanup_items.php');

        $this->assertStringContainsString('reprocessItem', $controller);
        $this->assertStringContainsString('reprocessPaper', $controller);
        $this->assertStringContainsString('Exactly one additional paid image request', $controller);
        $this->assertStringContainsString('Specific correction required for this image', $service);
        $this->assertStringContainsString('Shared administrator instruction for this paper', $service);
        $this->assertStringContainsString('Reprocess only this image (1 API call)', $show);
        $this->assertStringContainsString('Reprocess complete paper draft', $show);
        $this->assertStringContainsString("Schema::hasColumn('image_cleanup_items', 'instructions')", $migration);
    }
    public function test_individual_mode_is_exclusive_repeatable_and_monochrome(): void
    {
        $controller = file_get_contents(__DIR__.'/../../app/Http/Controllers/ImageCleanupController.php');
        $service = file_get_contents(__DIR__.'/../../app/Services/ImageCleanupService.php');
        $index = file_get_contents(__DIR__.'/../../resources/views/image-cleanup/index.blade.php');
        $show = file_get_contents(__DIR__.'/../../resources/views/image-cleanup/show.blade.php');

        $this->assertStringContainsString('selectedPapers.delete(id)', $index);
        $this->assertStringContainsString('Process ${count} selected image', $index);
        $this->assertStringContainsString('Only this image was queued', $controller);
        $this->assertStringContainsString('currentImageSource', $controller);
        $this->assertStringContainsString('published_by', $controller);
        $this->assertStringContainsString('monochromePng', $service);
        $this->assertStringContainsString('Do not introduce blue or any other colored pixels', $service);
        $this->assertStringContainsString('@if($canReprocess)<form', $show);
    }
    public function test_local_cleanup_and_optional_branding_do_not_require_an_ai_call(): void
    {
        $migration = file_get_contents(__DIR__.'/../../database/migrations/2026_08_03_000010_add_image_cleanup_branding_controls.php');
        $controller = file_get_contents(__DIR__.'/../../app/Http/Controllers/ImageCleanupController.php');
        $service = file_get_contents(__DIR__.'/../../app/Services/ImageCleanupService.php');
        $settings = file_get_contents(__DIR__.'/../../resources/views/configurations/ai.blade.php');
        $index = file_get_contents(__DIR__.'/../../resources/views/image-cleanup/index.blade.php');

        foreach (['image_cleanup_branding_enabled', 'image_cleanup_branding_mode', 'image_cleanup_watermark_text', 'image_cleanup_watermark_opacity'] as $field) {
            $this->assertStringContainsString($field, $migration);
            $this->assertStringContainsString($field, $settings);
        }
        $this->assertStringContainsString("'local_cleanup'", $controller);
        $this->assertStringContainsString("'remove_image'", $controller);
        $this->assertStringContainsString("\$provider = \$local ? 'local'", $controller);
        $this->assertStringContainsString('0 paid API requests', $controller);
        $this->assertStringContainsString("'local' => \$this->localCleanup", $service);
        $this->assertStringContainsString('localCleanPng', $service);
        $this->assertStringContainsString('removeImageAt', $service);
        $this->assertStringContainsString('local-remove-image', $service);
        $this->assertStringContainsString('applyBranding', $service);
        $this->assertStringContainsString('Organization logo', $settings);
        $this->assertStringContainsString('locally (0 API calls)', $index);
    }
    public function test_standalone_watermark_image_can_be_removed_without_touching_other_images(): void
    {
        $service = new \App\Services\ImageCleanupService();
        $method = new \ReflectionMethod($service, 'removeImageAt');
        $method->setAccessible(true);
        $html = '<p><img src="watermark.png"><img src="diagram.png"></p>';

        $result = $method->invoke($service, $html, 0, 'watermark.png');

        $this->assertStringNotContainsString('watermark.png', $result);
        $this->assertStringContainsString('diagram.png', $result);
    }
}

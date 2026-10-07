<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class QuestionRepairStorageContractTest extends TestCase
{
    public function test_repair_images_use_the_writable_public_storage_disk(): void
    {
        $source = file_get_contents(__DIR__.'/../../app/Services/QuestionRepairService.php');

        $this->assertStringContainsString("Storage::disk('public')", $source);
        $this->assertStringContainsString('$disk->path($relative)', $source);
        $this->assertStringContainsString('$disk->url($relative)', $source);
        $this->assertStringNotContainsString(
            "public_path(str_replace('/', DIRECTORY_SEPARATOR, \$relative))",
            $source
        );
        $this->assertStringNotContainsString('Unable to create the public repair image directory.', $source);
    }

    public function test_pdf_is_the_only_image_authority(): void
    {
        $reviewer = file_get_contents(__DIR__.'/../../app/Services/ExamQualityImageReviewer.php');
        $repair = file_get_contents(__DIR__.'/../../app/Services/QuestionRepairService.php');

        $this->assertStringContainsString('The attached QUESTION/COMBINED PDF is the sole source of authority.', $reviewer);
        $this->assertStringNotContainsString('storedImages(', $reviewer);
        $this->assertStringNotContainsString('compareStoredImage(', $repair);
        $this->assertStringContainsString('private function extractReviewerCrops(', $repair);
        $this->assertStringContainsString("'image_instructions' => \$instructions", $reviewer);
        $this->assertStringContainsString("'remove_stored_images' => true", $reviewer);
        $this->assertStringContainsString('private function removeStoredImagesFromProposal(', $repair);
        $cropScript = file_get_contents(__DIR__.'/../../scripts/extract-pdf-visual.py');
        $this->assertStringContainsString('authoritative_bbox_crop', $cropScript);
        $this->assertStringContainsString('scanned_line_art_refinement', $cropScript);
        $this->assertStringContainsString('scanned_visual_rects_without_opencv', $cropScript);
        $this->assertStringContainsString('exclude the question wording, answer choices, solutions, and adjacent questions', $reviewer);
    }

    public function test_preparing_saved_repairs_never_calls_a_provider_again(): void
    {
        $repair = file_get_contents(__DIR__.'/../../app/Services/QuestionRepairService.php');
        $start = strpos($repair, 'public function processBatch');
        $batchMethod = substr($repair, $start, strpos($repair, 'public function process(', $start) - $start);

        $this->assertStringContainsString('$this->proposalFromAudit($draft)', $batchMethod);
        $this->assertStringNotContainsString('proposeRepairBatch(', $batchMethod);
        $this->assertStringContainsString('No additional AI request was made.', $batchMethod);
    }
}

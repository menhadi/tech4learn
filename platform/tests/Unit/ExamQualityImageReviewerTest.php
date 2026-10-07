<?php

namespace Tests\Unit;

use App\Models\Question;
use App\Services\ExamQualityImageReviewer;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class ExamQualityImageReviewerTest extends TestCase
{
    public function test_it_accepts_multiple_authoritative_scanned_page_crops(): void
    {
        $question = new Question();
        $question->id = 42;
        $method = new ReflectionMethod(ExamQualityImageReviewer::class, 'sanitize');
        $method->setAccessible(true);

        $result = $method->invoke(new ExamQualityImageReviewer(), [
            'images' => [[
                'question_id' => 42,
                'action' => 'sync',
                'source_page' => 3,
                'bbox_normalized' => [0.12, 0.25, 0.78, 0.64],
                'target_field' => 'option1',
                'description' => 'First visual in the authoritative PDF.',
                'confidence' => 96,
            ], [
                'question_id' => 42,
                'action' => 'sync',
                'source_page' => 4,
                'bbox_normalized' => [0.15, 0.2, 0.72, 0.6],
                'target_field' => 'option1',
                'description' => 'Second visual in the authoritative PDF.',
                'confidence' => 94,
            ]],
        ], collect([$question]), ['provider' => 'gemini', 'model' => 'vision-model']);

        $this->assertSame('source_pdf_visual_sync', $result[42]['finding']['type']);
        $this->assertSame('sync', $result[42]['proposal']['image_instruction']['action']);
        $this->assertCount(2, $result[42]['proposal']['image_instructions']);
        $this->assertSame(2, $result[42]['proposal']['image_instructions'][1]['image_index']);
        $this->assertSame([0.12, 0.25, 0.78, 0.64], $result[42]['proposal']['image_instruction']['bbox_normalized']);
    }
    public function test_it_removes_stored_images_when_the_pdf_has_no_visual(): void
    {
        $withImage = new Question();
        $withImage->id = 42;
        $withImage->question = '<p>Text</p><img src="/wrong.png">';
        $withoutImage = new Question();
        $withoutImage->id = 43;
        $withoutImage->question = '<p>Text only</p>';
        $method = new ReflectionMethod(ExamQualityImageReviewer::class, 'sanitize');
        $method->setAccessible(true);

        $result = $method->invoke(new ExamQualityImageReviewer(), [
            'images' => [],
        ], collect([$withImage, $withoutImage]), ['provider' => 'gemini', 'model' => 'vision-model']);

        $this->assertTrue($result[42]['proposal']['remove_stored_images']);
        $this->assertSame(['question'], $result[42]['finding']['evidence']['stored_image_fields']);
        $this->assertArrayNotHasKey(43, $result);
    }

    public function test_it_rejects_an_unsafe_or_incomplete_crop_instruction(): void
    {
        $question = new Question();
        $question->id = 42;
        $method = new ReflectionMethod(ExamQualityImageReviewer::class, 'sanitize');
        $method->setAccessible(true);

        $result = $method->invoke(new ExamQualityImageReviewer(), [
            'images' => [[
                'question_id' => 42,
                'action' => 'sync',
                'source_page' => 0,
                'bbox_normalized' => [0.8, 0.2, 0.1, 0.7],
                'target_field' => 'question',
            ]],
        ], collect([$question]), ['provider' => 'chatgpt', 'model' => 'vision-model']);

        $this->assertArrayHasKey('_diagnostics', $result);
        $this->assertSame('The source page is missing or invalid.', $result['_diagnostics'][0]['reason']);
    }

    public function test_it_rejects_a_response_without_the_required_images_array(): void
    {
        $question = new Question();
        $question->id = 42;
        $method = new ReflectionMethod(ExamQualityImageReviewer::class, 'sanitize');
        $method->setAccessible(true);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('valid images array');

        $method->invoke(new ExamQualityImageReviewer(), [
            'unexpected' => [],
        ], collect([$question]), ['provider' => 'gemini', 'model' => 'vision-model']);
    }
}

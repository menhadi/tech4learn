<?php

namespace Tests\Unit;

use App\Support\SourceExamImageContract;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class SourceExamImageContractTest extends TestCase
{
    public function test_it_rewrites_downloaded_images_to_local_storage_urls(): void
    {
        Storage::fake('public');
        $folder = 'question-images/group/category/subcategory/package/exam/images';
        Storage::disk('public')->put($folder.'/question.png', 'question');
        Storage::disk('public')->put($folder.'/option1.png', 'option');

        $row = [
            'question' => '<img src="https://cdn.example/question.png"><a href="https://example.com">source</a>',
            'options' => ['<img src="option1.png">'],
            'extracted_images' => [
                'https://cdn.example/question.png',
                ['filename' => 'option1.png', 'url' => 'https://cdn.example/option1.png'],
            ],
        ];

        $normalized = app(SourceExamImageContract::class)->normalize($row, $folder);
        $json = json_encode($normalized, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);

        $this->assertStringNotContainsString('cdn.example', $json);
        $this->assertStringContainsString('/storage/'.$folder.'/question.png', $json);
        $this->assertStringContainsString('/storage/'.$folder.'/option1.png', $json);
        $this->assertStringContainsString('href="https://example.com"', $normalized['question']);
        $this->assertSame('option1.png', $normalized['extracted_images'][1]['filename']);
    }

    public function test_it_rejects_a_remote_image_that_was_not_downloaded(): void
    {
        Storage::fake('public');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('was not downloaded');

        app(SourceExamImageContract::class)->normalize(
            ['question' => '<img src="https://cdn.example/missing.png">'],
            'question-images/group/category/subcategory/package/exam/images'
        );
    }
}
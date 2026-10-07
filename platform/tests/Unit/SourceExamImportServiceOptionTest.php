<?php

namespace Tests\Unit;

use App\Services\SourceExamImportService;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class SourceExamImportServiceOptionTest extends TestCase
{
    public function test_image_only_options_are_preserved(): void
    {
        $options = $this->questionOptions([
            'options' => [
                '<img src="/storage/exam/q1-option1.png" alt="Option 1">',
                '<img src="/storage/exam/q1-option2.png" alt="Option 2">',
                '<img src="/storage/exam/q1-option3.png" alt="Option 3">',
                '<img src="/storage/exam/q1-option4.png" alt="Option 4">',
            ],
        ]);

        $this->assertCount(4, $options);
        $this->assertStringContainsString('q1-option1.png', $options[0]);
        $this->assertStringContainsString('q1-option4.png', $options[3]);
    }

    public function test_empty_markup_is_still_removed(): void
    {
        $options = $this->questionOptions([
            'options' => ['<p> </p>', '<br>', null, '<math><mi>x</mi></math>'],
        ]);

        $this->assertSame(['<math><mi>x</mi></math>'], $options);
    }

    private function questionOptions(array $row): array
    {
        $service = new SourceExamImportService;
        $method = new ReflectionMethod($service, 'questionOptions');
        $method->setAccessible(true);

        return $method->invoke($service, $row);
    }
}

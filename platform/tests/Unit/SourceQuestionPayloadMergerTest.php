<?php

namespace Tests\Unit;

use App\Services\SourceQuestionPayloadMerger;
use PHPUnit\Framework\TestCase;

class SourceQuestionPayloadMergerTest extends TestCase
{
    public function test_source_values_win_and_csv_fills_only_missing_values(): void
    {
        $payload = (new SourceQuestionPayloadMerger)->merge([
            'question' => 'Question from webpage',
            'options' => ['Web A', 'Web B'],
            'subject' => 'Physics',
            'topic' => 'Mechanics',
            'question_type' => 'NAT',
            'marks' => 0,
            'answer' => null,
        ], [
            'question' => 'Question from CSV',
            'option1' => 'CSV A',
            'option2' => 'CSV B',
            'option3' => 'CSV C',
            'subject' => 'Chemistry',
            'topic' => 'Organic Chemistry',
            'subtopic' => 'Kinematics',
            'question_type' => 'M',
            'marks' => '4',
            'answer' => '42',
            'groups' => 'Engineering',
            'exams' => 'JEE Main 2025',
            'correct_option_indices' => 'A,C',
        ]);

        $this->assertSame('Question from webpage', $payload['question']);
        $this->assertSame(['Web A', 'Web B', 'CSV C'], $payload['options']);
        $this->assertSame('Physics', $payload['subject']);
        $this->assertSame('Mechanics', $payload['topic']);
        $this->assertSame('Kinematics', $payload['subtopic']);
        $this->assertSame('NAT', $payload['question_type']);
        $this->assertSame(0, $payload['marks']);
        $this->assertSame('42', $payload['answer']);
        $this->assertSame('Engineering', $payload['group']);
        $this->assertSame('JEE Main 2025', $payload['exam']);
        $this->assertSame([1, 3], $payload['correct_option_indices']);
        $this->assertSame('English', $payload['language']);
    }
}

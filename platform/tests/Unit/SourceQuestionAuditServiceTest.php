<?php

namespace Tests\Unit;

use App\Models\Question;
use App\Services\SourceQuestionAuditService;
use PHPUnit\Framework\TestCase;

class SourceQuestionAuditServiceTest extends TestCase
{
    public function test_it_distinguishes_missing_mismatched_matching_and_source_blank_fields(): void
    {
        $question = new Question([
            'question' => '<p>What is 2 + 2?</p>', 'option1' => '4', 'option2' => '5',
            'answer' => null, 'explanation' => 'Old explanation', 'marks' => 4, 'negative_marks' => null,
        ]);
        $result = (new SourceQuestionAuditService)->compare($question, [
            'question' => 'What is 2 + 2?', 'options' => ['4', '6'], 'answer' => 'A',
            'explanation' => null, 'marks' => 4.0, 'negative_marks' => 1,
        ]);

        $this->assertSame('match', $result['fields']['question']['status']);
        $this->assertSame('match', $result['fields']['option1']['status']);
        $this->assertSame('mismatch', $result['fields']['option2']['status']);
        $this->assertSame('missing', $result['fields']['answer']['status']);
        $this->assertSame('source_blank', $result['fields']['explanation']['status']);
        $this->assertSame('match', $result['fields']['marks']['status']);
        $this->assertSame('missing', $result['fields']['negative_marks']['status']);
        $this->assertSame('changes', $result['status']);
    }

    public function test_it_ignores_different_image_hosts_during_audit_comparison(): void
    {
        $question = new Question([
            'question' => '<p>Identify the structure.</p><img src="https://examelite.com/storage/structure.png">',
            'option1' => '<img src="https://examelite.com/storage/answer-a.png">',
            'explanation' => '<p>See diagram.</p><img src="https://examelite.com/storage/solution.png">',
        ]);
        $result = (new SourceQuestionAuditService)->compare($question, [
            'question' => '<p>Identify the structure.</p><img src="https://cdn.examside.com/structure.png">',
            'options' => ['<img src="https://cdn.examside.com/answer-a.png">'],
            'explanation' => '<p>See diagram.</p><img src="https://cdn.examside.com/solution.png">',
        ]);

        $this->assertSame('match', $result['fields']['question']['status']);
        $this->assertSame('match', $result['fields']['option1']['status']);
        $this->assertSame('match', $result['fields']['explanation']['status']);
    }
}

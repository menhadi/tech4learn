<?php

namespace Tests\Unit;

use App\Models\Question;
use App\Services\SourceQuestionAuditService;
use PHPUnit\Framework\TestCase;

class SourceQuestionAcademicAuditTest extends TestCase
{
    public function test_it_reports_missing_repairable_academic_fields_from_resolved_assignment(): void
    {
        $question = new Question(['question' => 'Existing question']);
        $result = (new SourceQuestionAuditService)->compare($question, [
            'question' => 'Existing question',
            'assignment' => [
                'question_type' => ['id' => 10, 'name' => 'Multiple Choice', 'assigned' => true],
                'subject' => ['id' => 20, 'name' => 'Mathematics', 'assigned' => true],
                'topic' => ['id' => 30, 'name' => 'Algebra', 'assigned' => true],
                'subtopic' => ['id' => 40, 'name' => 'Complex Numbers', 'assigned' => true],
                'language' => ['id' => 50, 'name' => 'English', 'assigned' => true],
                'group' => ['ids' => [60], 'names' => ['Engineering'], 'assigned' => true, 'unmatched' => []],
                'exam' => ['ids' => [70], 'names' => ['AIEEE 2003'], 'assigned' => true, 'unmatched' => []],
            ],
        ]);

        foreach (['question_type', 'subject', 'topic', 'subtopic', 'language', 'groups', 'exams'] as $field) {
            $this->assertSame('missing', $result['fields'][$field]['status']);
            $this->assertTrue($result['fields'][$field]['repairable']);
        }
        $this->assertSame('changes', $result['status']);
    }
}

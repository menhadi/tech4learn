<?php

namespace Tests\Unit;

use App\Models\Exam;
use PHPUnit\Framework\TestCase;

class ExamTestClassificationTest extends TestCase
{
    public function test_supported_test_types_have_stable_frontend_labels(): void
    {
        $this->assertSame([
            'full_length' => 'Full-Length Tests',
            'subject_test' => 'Subject Tests',
            'topic_test' => 'Topic Tests',
            'subtopic_test' => 'Subtopic Tests',
            'sectional_test' => 'Sectional Tests',
            'previous_year' => 'Previous Year Papers',
            'other' => 'Other Tests',
        ], Exam::testTypeLabels());
    }

    public function test_other_is_the_safe_legacy_classification(): void
    {
        $this->assertSame('other', Exam::TEST_TYPE_OTHER);
        $this->assertArrayHasKey(Exam::TEST_TYPE_OTHER, Exam::testTypeLabels());
    }
}

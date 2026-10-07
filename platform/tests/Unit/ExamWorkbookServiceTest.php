<?php

namespace Tests\Unit;

use App\Services\ExamWorkbookService;
use PHPUnit\Framework\TestCase;

class ExamWorkbookServiceTest extends TestCase
{
    public function test_workbook_contains_package_display_classification_columns(): void
    {
        $headings = (new ExamWorkbookService())->headings();

        $this->assertContains('test_type', $headings);
        $this->assertContains('test_type_label', $headings);
        $this->assertContains('test_subject_id', $headings);
        $this->assertContains('test_subject_name', $headings);
        $this->assertContains('test_topic_id', $headings);
        $this->assertContains('test_topic_name', $headings);
        $this->assertContains('test_subtopic_id', $headings);
        $this->assertContains('test_subtopic_name', $headings);
    }
}
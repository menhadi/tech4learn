<?php

namespace Tests\Unit;

use App\Exports\QuestionsExport;
use PHPUnit\Framework\TestCase;

class QuestionsExportTest extends TestCase
{
    public function test_export_uses_only_the_canonical_correct_option_column(): void
    {
        $headings = (new QuestionsExport(null))->headings();

        $this->assertContains('correct_option_indices', $headings);
        $this->assertNotContains('mi_answer1', $headings);
        $this->assertNotContains('mi_answers', $headings);
        $this->assertContains('subject', $headings);
        $this->assertContains('topic', $headings);
        $this->assertContains('subtopic', $headings);
    }

    public function test_export_neutralizes_spreadsheet_formula_values(): void
    {
        $this->assertSame("'=HYPERLINK(\"https://example.test\")", QuestionsExport::safeSpreadsheetValue('=HYPERLINK("https://example.test")'));
        $this->assertSame("'  +SUM(1,2)", QuestionsExport::safeSpreadsheetValue('  +SUM(1,2)'));
        $this->assertSame('Ordinary question text', QuestionsExport::safeSpreadsheetValue('Ordinary question text'));
        $this->assertSame(2.5, QuestionsExport::safeSpreadsheetValue(2.5));
    }
}
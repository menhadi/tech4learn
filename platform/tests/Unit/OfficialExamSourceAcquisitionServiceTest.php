<?php

namespace Tests\Unit;

use App\Services\OfficialExamSourceAcquisitionService;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class OfficialExamSourceAcquisitionServiceTest extends TestCase
{
    public function test_css_selector_and_patterns_isolate_different_website_tables(): void
    {
        $html = <<<'HTML'
        <h2>GATE papers</h2>
        <table id="gate"><tr><td><a href="/files/gate-cs-2027-question.pdf">GATE CS 2027 Question Paper</a></td></tr></table>
        <h2>UPSC papers</h2>
        <table id="upsc"><tr><td><a href="/files/upsc-2027-question.pdf">UPSC 2027 Question Paper</a></td></tr></table>
        HTML;
        $links = $this->invoke('links', [$html, 'https://official.test/archive', [
            'selector' => '#gate', 'heading' => 'GATE', 'pattern' => 'question',
        ], ['pdf']]);

        self::assertCount(1, $links);
        self::assertSame('https://official.test/files/gate-cs-2027-question.pdf', $links[0]['url']);
    }

    public function test_question_and_answer_documents_are_paired_without_merging_other_exams(): void
    {
        $rows = $this->invoke('pair', [[
            ['url' => 'https://official.test/gate-cs-2027-question.pdf', 'local' => '', 'label' => 'GATE CS 2027 Question Paper', 'role' => 'question', 'position' => 0],
            ['url' => 'https://official.test/gate-me-2027-question.pdf', 'local' => '', 'label' => 'GATE ME 2027 Question Paper', 'role' => 'question', 'position' => 1],
            ['url' => 'https://official.test/gate-me-2027-answer.pdf', 'local' => '', 'label' => 'GATE ME 2027 Answer Key', 'role' => 'answer', 'position' => 2],
            ['url' => 'https://official.test/gate-cs-2027-answer.pdf', 'local' => '', 'label' => 'GATE CS 2027 Answer Key', 'role' => 'answer', 'position' => 3],
        ]]);

        self::assertCount(2, $rows);
        $byQuestion = [];
        foreach ($rows as $row) $byQuestion[$row['question_label']] = $row['answer_label'];
        self::assertSame('GATE CS 2027 Answer Key', $byQuestion['GATE CS 2027 Question Paper']);
        self::assertSame('GATE ME 2027 Answer Key', $byQuestion['GATE ME 2027 Question Paper']);
    }

    public function test_plain_text_and_regular_expression_patterns_are_supported(): void
    {
        self::assertTrue($this->invoke('matches', ['bilingual', 'UPSC Bilingual Paper 2027']));
        self::assertTrue($this->invoke('matches', ['/GATE\s+(CS|ME)/i', 'gate cs question paper']));
        self::assertFalse($this->invoke('matches', ['/GATE\s+(CS|ME)/i', 'UPSC question paper']));
    }

    private function invoke(string $method, array $arguments): mixed
    {
        $reflection = new ReflectionMethod(OfficialExamSourceAcquisitionService::class, $method);
        $reflection->setAccessible(true);
        return $reflection->invokeArgs(new OfficialExamSourceAcquisitionService(), $arguments);
    }
}

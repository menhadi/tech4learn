<?php

namespace Tests\Unit;

use App\Services\MathContentNormalizer;
use App\Services\SourceQuestionAdapters\ExamSideAdapter;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class ExamSideAdapterMetadataTest extends TestCase
{
    public function test_it_selects_the_url_question_id_and_extracts_authoritative_metadata(): void
    {
        $html = <<<'HTML'
<script>
questions:[{country:"in",question_id:"AAAAAAAAAAAAAAAA",exam:"jee-main",marks:1,negMarks:0,subject:"physics",chapterGroup:"mechanics",languages:["en"],chapter:"units",paperTitle:"Wrong Paper",difficulty:"easy",type:"mcq",question:{en:{content:"Wrong embedded question",options:[{identifier:"A",content:"Wrong"}],correct_options:["A"],answer:null,explanation:null}}},{country:"in",question_id:"BBBBBBBBBBBBBBBB",exam:"jee-main",marks:4,negMarks:1,subject:"mathematics",chapterGroup:"algebra",languages:["en"],chapter:"complex-numbers",paperTitle:"AIEEE 2003",difficulty:"medium",type:"mcq",question:{en:{content:"Correct embedded question",options:[{identifier:"A",content:"Option A"},{identifier:"B",content:"Option B"}],correct_options:["B"],answer:null,explanation:"Worked solution"}}}]
</script>
HTML;
        $adapter = new ExamSideAdapter(new MathContentNormalizer);
        $extractEmbedded = new ReflectionMethod($adapter, 'embeddedEnglishData');
        $payload = $extractEmbedded->invoke(
            $adapter,
            $html,
            'https://questions.examside.com/past-years/jee/question/example-bbbbbbbbbbbbbbbb.htm',
        );
        $resolveType = new ReflectionMethod($adapter, 'questionType');

        $this->assertSame('Correct embedded question', $payload['question']);
        $this->assertSame(['Option A', 'Option B'], $payload['options']);
        $this->assertSame('Mathematics', $payload['subject']);
        $this->assertSame('Algebra', $payload['topic']);
        $this->assertSame('Complex Numbers', $payload['subtopic']);
        $this->assertSame('Medium', $payload['difficulty_level']);
        $this->assertSame('English', $payload['language']);
        $this->assertSame('Aieee 2003', $payload['exam']);
        $this->assertSame('M', $resolveType->invoke($adapter, $payload['question_type']));
        $this->assertSame(4.0, $payload['marks']);
        $this->assertSame(1.0, $payload['negative_marks']);
        $this->assertSame([2], $payload['correct_option_indices']);
    }

    public function test_it_selects_the_first_visible_question_component(): void
    {
        $document = new \DOMDocument('1.0', 'UTF-8');
        $document->loadHTML(<<<'HTML'
<div class="question-component"><div class="question">Relevant first question</div></div>
<div class="question-component"><div class="question">Recommended other question</div></div>
HTML);
        $xpath = new \DOMXPath($document);
        $nodes = $xpath->query("//*[contains(concat(' ', normalize-space(@class), ' '), ' question-component ')]");
        $adapter = new ExamSideAdapter(new MathContentNormalizer);
        $select = new ReflectionMethod($adapter, 'selectComponent');

        $component = $select->invoke($adapter, $nodes, 'https://questions.examside.com/example-AAAAAAAAAAAAAAAA', $xpath);

        $this->assertStringContainsString('Relevant first question', $component->textContent);
        $this->assertStringNotContainsString('Recommended other question', $component->textContent);
    }
}

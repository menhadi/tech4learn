<?php

namespace Tests\Unit;

use App\Models\Qtype;
use App\Models\Question;
use App\Services\ExamQualityRuleEngine;
use Tests\TestCase;

class ExamQualityRuleEngineTest extends TestCase
{
    public function test_correct_option_marker_is_valid_by_its_database_position(): void
    {
        $question = $this->multipleChoice([
            'option1' => '<p>Option <strong>A</strong></p>',
            'option2' => '<p>Option with \( x^2 \)</p>',
            'correct_option_indices' => [2],
            'marks' => 1,
        ]);

        $types = collect((new ExamQualityRuleEngine())->inspect($question))->pluck('type');

        $this->assertNotContains('missing_correct_option', $types);
        $this->assertNotContains('correct_option_not_visible', $types);
    }

    public function test_image_only_option_can_be_marked_correct(): void
    {
        $question = $this->multipleChoice([
            'option1' => '<img src="data:image/png;base64,QQ==" alt="Option A">',
            'option2' => '<img src="data:image/png;base64,Qg==" alt="Option B">',
            'correct_option_indices' => [2],
            'marks' => 1,
        ]);

        $types = collect((new ExamQualityRuleEngine())->inspect($question))->pluck('type');

        $this->assertNotContains('insufficient_options', $types);
        $this->assertNotContains('missing_correct_option', $types);
        $this->assertNotContains('correct_option_not_visible', $types);
    }

    public function test_correct_marker_for_an_empty_option_is_reported(): void
    {
        $question = $this->multipleChoice([
            'option1' => 'Only visible option',
            'correct_option_indices' => [2],
            'marks' => 1,
        ]);

        $types = collect((new ExamQualityRuleEngine())->inspect($question))->pluck('type');

        $this->assertContains('correct_option_not_visible', $types);
    }

    private function multipleChoice(array $attributes): Question
    {
        $question = new Question($attributes);
        $question->setRelation('qtype', new Qtype(['type' => 'M', 'question_type' => 'Multiple Choices']));

        return $question;
    }
}
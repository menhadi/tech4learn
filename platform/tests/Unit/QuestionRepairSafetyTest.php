<?php

namespace Tests\Unit;

use App\Models\Question;
use App\Models\Qtype;
use App\Services\QuestionRepairService;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class QuestionRepairSafetyTest extends TestCase
{
    public function test_blank_ai_explanation_cannot_erase_manual_explanation(): void
    {
        $question = new Question();
        $question->explanation = '<p>Manually authored explanation with \( x^2 \).</p>';
        $proposed = ['explanation' => ''];

        $method = new ReflectionMethod(QuestionRepairService::class, 'preserveManualExplanation');
        $method->setAccessible(true);
        $service = new QuestionRepairService();
        $method->invokeArgs($service, [$question, &$proposed]);

        $this->assertArrayNotHasKey('explanation', $proposed);
    }

    public function test_non_empty_mathjax_normalization_remains_reviewable(): void
    {
        $question = new Question();
        $question->explanation = '<p>x2 + y2</p>';
        $proposed = ['explanation' => '<p>\( x^2 + y^2 \)</p>'];

        $method = new ReflectionMethod(QuestionRepairService::class, 'preserveManualExplanation');
        $method->setAccessible(true);
        $service = new QuestionRepairService();
        $method->invokeArgs($service, [$question, &$proposed]);

        $this->assertSame('<p>\( x^2 + y^2 \)</p>', $proposed['explanation']);
    }

    public function test_source_image_replaces_duplicate_html_table(): void
    {
        $method = new ReflectionMethod(QuestionRepairService::class, 'sourceVisualReplacesHtmlTable');
        $method->setAccessible(true);

        $result = $method->invoke(new QuestionRepairService(), ['source_html_table_targets' => []], ['visual_type' => 'source_crop'], 'question');
        $this->assertTrue($result);
    }

    public function test_legitimate_source_html_table_is_preserved(): void
    {
        $method = new ReflectionMethod(QuestionRepairService::class, 'sourceVisualReplacesHtmlTable');
        $method->setAccessible(true);

        $result = $method->invoke(new QuestionRepairService(), ['source_html_table_targets' => ['question']], ['visual_type' => 'source_crop'], 'question');
        $this->assertFalse($result);
    }

    public function test_legacy_manifest_preserves_html_table_safely(): void
    {
        $method = new ReflectionMethod(QuestionRepairService::class, 'sourceVisualReplacesHtmlTable');
        $method->setAccessible(true);

        $result = $method->invoke(new QuestionRepairService(), [], ['visual_type' => 'source_crop'], 'question');
        $this->assertFalse($result);
    }

    public function test_canonical_manifest_rebuilds_visual_placement_across_all_fields(): void
    {
        $method = new ReflectionMethod(QuestionRepairService::class, 'canonicalVisualFields');
        $method->setAccessible(true);

        $manifest = [
            'question_band_detected' => true,
            'canonical_fields' => [
                'question' => [],
                'option1' => [],
                'option2' => [],
                'option3' => [],
                'option4' => [],
            ],
        ];

        $result = $method->invoke(new QuestionRepairService(), $manifest, ['option1', 'option2', 'option3', 'option4']);
        $this->assertSame(['question', 'option1', 'option2', 'option3', 'option4'], $result);
    }

    public function test_untrusted_manifest_only_clears_fields_with_positive_visual_evidence(): void
    {
        $method = new ReflectionMethod(QuestionRepairService::class, 'canonicalVisualFields');
        $method->setAccessible(true);

        $result = $method->invoke(new QuestionRepairService(), [], ['option1', 'option2']);
        $this->assertSame(['option1', 'option2'], $result);
    }


    public function test_mcq_answer_proposal_is_normalized_to_canonical_positions(): void
    {
        $question = new Question();
        $question->option2 = '<p>Authoritative option B</p>';
        $question->setRelation('qtype', new Qtype(['type' => 'M', 'question_type' => 'Multiple Choice']));
        $proposed = ['correct_option_indices' => ['2', 2, 9]];

        $method = new ReflectionMethod(QuestionRepairService::class, 'normalizeMultipleChoiceAnswers');
        $method->setAccessible(true);
        $method->invokeArgs(new QuestionRepairService(), [$question, &$proposed]);

        $this->assertSame([2], $proposed['correct_option_indices']);
    }
}

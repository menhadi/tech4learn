<?php

namespace Tests\Unit;

use App\Models\ExamStat;
use App\Models\Qtype;
use App\Models\Question;
use App\Services\QuestionAnswerEvaluator;
use PHPUnit\Framework\TestCase;

class QuestionAnswerEvaluatorTest extends TestCase
{
    private QuestionAnswerEvaluator $evaluator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->evaluator = new QuestionAnswerEvaluator();
    }

    public function test_marks_to_all_is_correct_even_when_unanswered(): void
    {
        $question = new Question(['scoring_policy' => 'MTA']);
        $stat = new ExamStat(['answered' => false, 'answer' => null]);

        $this->assertTrue($this->evaluator->awardsMarksToAll($question));
        $this->assertTrue($this->evaluator->isCorrect($question, $stat));
    }

    public function test_legacy_fill_blank_remains_compatible_and_numeric_format_is_ignored(): void
    {
        $question = $this->question('F', ['fill_blank' => '1.0']);
        $stat = new ExamStat(['answered' => true, 'answer' => '1']);

        $this->assertTrue($this->evaluator->isCorrect($question, $stat));
    }

    public function test_multiple_fill_blanks_are_ordered_and_all_are_required(): void
    {
        $question = $this->question('F', [
            'fill_blank_config' => [
                'version' => 1,
                'blanks' => [
                    ['answers' => ['New Delhi', 'Delhi']],
                    ['answers' => ['1.0']],
                ],
            ],
        ]);

        $this->assertTrue($this->evaluator->isCorrect($question, new ExamStat([
            'answered' => true,
            'answer' => json_encode(['new delhi', '1']),
        ])));
        $this->assertFalse($this->evaluator->isCorrect($question, new ExamStat([
            'answered' => true,
            'answer' => json_encode(['1', 'New Delhi']),
        ])));
    }

    public function test_legacy_fill_blank_is_inferred_from_its_answer_data(): void
    {
        $question = $this->question('TEXT', ['fill_blank' => 'expected answer']);

        $this->assertSame('fill_blank', $this->evaluator->questionType($question));
        $this->assertSame(1, $this->evaluator->fillBlankCount($question));
    }

    public function test_legacy_no_option_question_is_inferred_as_subjective(): void
    {
        $withModelAnswer = $this->question('TEXT', ['si_answer1' => 'Model response']);
        $withoutModelAnswer = $this->question('TEXT', []);

        $this->assertSame('subjective', $this->evaluator->questionType($withModelAnswer));
        $this->assertSame('subjective', $this->evaluator->questionType($withoutModelAnswer));
    }

    public function test_explicit_multiple_choice_type_never_becomes_a_subjective_textarea(): void
    {
        $withoutAnswerKey = $this->question('M', [
            'option1' => 'First option',
            'option2' => 'Second option',
        ]);
        $withSeveralAnswers = $this->question('M', [
            'option1' => 'First option',
            'option2' => 'Second option',
            'correct_option_indices' => [1, 2],
        ]);

        $this->assertSame('multiple_choice_radio', $this->evaluator->questionType($withoutAnswerKey));
        $this->assertSame('multiple_choice_checkbox', $this->evaluator->questionType($withSeveralAnswers));
    }

    public function test_canonical_option_indices_grade_correctly_independent_of_display_order(): void
    {
        $question = $this->question('M', [
            'option1' => 'Alpha',
            'option2' => 'Beta',
            'option3' => 'Gamma',
            'correct_option_indices' => [2, 3],
        ]);

        $correct = new ExamStat(['answered' => true, 'selected_option_indices' => [3, 2]]);
        $wrong = new ExamStat(['answered' => true, 'selected_option_indices' => [1, 3]]);

        $this->assertSame('multiple_choice_checkbox', $this->evaluator->questionType($question));
        $this->assertTrue($this->evaluator->isCorrect($question, $correct));
        $this->assertFalse($this->evaluator->isCorrect($question, $wrong));
        $this->assertSame([2, 3], $this->evaluator->selectedOptionIndices($question, $correct));
    }

    public function test_database_attempt_without_a_canonical_selection_does_not_read_removed_columns(): void
    {
        $question = $this->question('M', [
            'option1' => 'Alpha',
            'option2' => 'Beta',
            'correct_option_indices' => [2],
        ]);
        $databaseAttempt = new ExamStat(['answered' => true, 'selected_option_indices' => null]);

        $this->assertSame([], $this->evaluator->selectedOptionIndices($question, $databaseAttempt));
        $this->assertFalse($this->evaluator->isCorrect($question, $databaseAttempt));
    }

    public function test_historic_report_text_attempts_are_mapped_to_canonical_option_indices(): void
    {
        $question = $this->question('M', [
            'option1' => '<p>Alpha</p>',
            'option2' => '<p>Beta</p>',
            'correct_option_indices' => [2],
        ]);
        $historicReport = (object) ['answered' => true, 'mi_answers' => ['Beta']];

        $this->assertSame([2], $this->evaluator->selectedOptionIndices($question, $historicReport));
        $this->assertTrue($this->evaluator->isCorrect($question, $historicReport));
    }

    public function test_historic_numeric_text_answer_maps_to_the_matching_option_value(): void
    {
        $question = $this->question('M', [
            'option1' => 'Alpha',
            'option2' => 'Beta',
            'option3' => '2',
            'correct_option_indices' => [3],
        ]);
        $historicReport = (object) ['answered' => true, 'mi_answers' => ['2']];

        $this->assertSame([3], $this->evaluator->selectedOptionIndices($question, $historicReport));
        $this->assertTrue($this->evaluator->isCorrect($question, $historicReport));
    }

    public function test_report_snapshot_stdclass_uses_canonical_and_legacy_option_answers(): void
    {
        $question = (object) [
            'option1' => '<p>Alpha</p>', 'option2' => '<p>Beta</p>',
            'option3' => null, 'option4' => null, 'option5' => null, 'option6' => null,
            'correct_option_indices' => '[2]',
            'mi_answer1' => null, 'mi_answer2' => '<p>Beta</p>', 'mi_answer3' => null,
            'mi_answer4' => null, 'mi_answer5' => null, 'mi_answer6' => null,
            'nat_config' => null, 'fill_blank_config' => null, 'fill_blank' => null,
            'true_false' => null, 'si_answer1' => null, 'answer' => null,
        ];
        $report = (object) ['selected_option_indices' => null, 'mi_answers' => ['Beta']];

        $this->assertSame('multiple_choice_radio', $this->evaluator->questionType($question));
        $this->assertSame([2], $this->evaluator->correctOptionIndices($question));
        $this->assertSame([2], $this->evaluator->selectedOptionIndices($question, $report));
        $this->assertSame(['<strong>B.</strong> <p>Beta</p>'], $this->evaluator->optionReviewValues($question, [2]));
    }

    public function test_explicit_fill_and_subjective_types_remain_authoritative(): void
    {
        $this->assertSame('fill_blank', $this->evaluator->questionType($this->question('F', [])));
        $this->assertSame('subjective', $this->evaluator->questionType($this->question('S', [])));
    }

    public function test_nat_supports_exact_range_and_tolerance_rules(): void
    {
        $range = $this->question('NAT', ['nat_config' => ['mode' => 'range', 'min' => 1.0, 'max' => 1.2]]);
        $this->assertTrue($this->evaluator->isCorrect($range, new ExamStat(['answered' => true, 'answer' => '1.2'])));
        $this->assertFalse($this->evaluator->isCorrect($range, new ExamStat(['answered' => true, 'answer' => '1.21'])));

        $exact = $this->question('NAT', ['nat_config' => ['mode' => 'exact', 'value' => 2.5]]);
        $this->assertTrue($this->evaluator->isCorrect($exact, new ExamStat(['answered' => true, 'answer' => '2.500'])));

        $tolerance = $this->question('NAT', ['nat_config' => ['mode' => 'tolerance', 'value' => 10, 'tolerance' => 0.5]]);
        $this->assertTrue($this->evaluator->isCorrect($tolerance, new ExamStat(['answered' => true, 'answer' => '9.5'])));
        $this->assertFalse($this->evaluator->isCorrect($tolerance, new ExamStat(['answered' => true, 'answer' => '9.49'])));
    }

    private function question(string $type, array $attributes): Question
    {
        $question = new Question($attributes);
        $question->setRelation('qtype', new Qtype(['type' => $type, 'question_type' => $type]));

        return $question;
    }
}
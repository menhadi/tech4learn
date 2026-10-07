<?php

namespace Tests\Unit;

use App\Models\Qtype;
use App\Models\Question;
use App\Services\QuestionAnswerEvaluator;
use App\Services\QuickQuizQuestionPoolService;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class QuickQuizQuestionPoolServiceTest extends TestCase
{
    public function test_only_multiple_choice_questions_with_answer_keys_are_usable(): void
    {
        $service = new QuickQuizQuestionPoolService(new QuestionAnswerEvaluator());

        $valid = $this->question('M', [
            'option1' => 'Alpha',
            'option2' => 'Beta',
            'correct_option_indices' => [2],
        ]);
        $invalid = $this->question('M', [
            'option1' => 'Alpha',
            'option2' => 'Beta',
        ]);

        $this->assertTrue($this->isUsable($service, $valid));
        $this->assertFalse($this->isUsable($service, $invalid));
    }

    public function test_structured_fill_blank_and_nat_questions_are_usable(): void
    {
        $service = new QuickQuizQuestionPoolService(new QuestionAnswerEvaluator());
        $fillBlank = $this->question('F', [
            'fill_blank_config' => ['blanks' => [['answers' => ['Delhi']]]],
        ]);
        $nat = $this->question('NAT', [
            'nat_config' => ['mode' => 'range', 'min' => 1, 'max' => 2],
        ]);
        $invalidNat = $this->question('NAT', [
            'nat_config' => ['mode' => 'exact', 'value' => null],
        ]);

        $this->assertTrue($this->isUsable($service, $fillBlank));
        $this->assertTrue($this->isUsable($service, $nat));
        $this->assertFalse($this->isUsable($service, $invalidNat));
    }

    private function isUsable(QuickQuizQuestionPoolService $service, Question $question): bool
    {
        $method = new ReflectionMethod($service, 'hasUsableAnswer');

        return $method->invoke($service, $question);
    }

    private function question(string $type, array $attributes): Question
    {
        $question = new Question($attributes);
        $question->setRelation('qtype', new Qtype(['type' => $type, 'question_type' => $type]));

        return $question;
    }
}

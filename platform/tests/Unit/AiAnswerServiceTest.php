<?php

namespace Tests\Unit;

use App\Models\Qtype;
use App\Models\Question;
use App\Services\AiAnswerService;
use App\Services\AiAnswerMathValidator;
use App\Models\{AiAnswerDraft, Subject, Topic, Stopic};
use Tests\TestCase;

class AiAnswerServiceTest extends TestCase
{
    public function test_it_generates_answer_and_explanation_when_answer_is_missing(): void
    {
        $question = $this->multipleChoiceQuestion([]);

        $this->assertSame('generate_answer_explanation', app(AiAnswerService::class)->mode($question));
    }

    public function test_it_generates_only_explanation_when_answer_exists(): void
    {
        $question = $this->multipleChoiceQuestion([2]);

        $this->assertSame('generate_explanation', app(AiAnswerService::class)->mode($question, ['status' => 'verified']));
    }

    public function test_it_rewrites_explanation_without_changing_answer_when_both_exist(): void
    {
        $question = $this->multipleChoiceQuestion([2]);
        $question->explanation = '<p>Option B follows from the stated relationship.</p>';

        $this->assertSame('generate_explanation', app(AiAnswerService::class)->mode($question, ['status' => 'verified']));
    }

    public function test_snapshot_includes_difficulty_for_every_ai_answer_draft(): void
    {
        $question = $this->multipleChoiceQuestion([2]);
        $question->diff_id = 3;
        $question->subject_id = 10;
        $question->topic_id = 20;
        $question->stopic_id = 30;

        $snapshot = app(AiAnswerService::class)->snapshot($question);
        $this->assertSame(3, $snapshot['diff_id']);
        $this->assertSame(10, $snapshot['subject_id']);
        $this->assertSame(20, $snapshot['topic_id']);
        $this->assertSame(30, $snapshot['stopic_id']);
    }

    public function test_it_normalizes_latex_controls_and_removes_internal_review_language(): void
    {
        $service = app(AiAnswerService::class);
        $result = $service->studentFacingExplanation(
            "Use \f"."rac{1}{2}. The stored answer is inconsistent. Therefore the result is 0.5. \u{1F680}"
        );

        $this->assertStringContainsString('\\frac{1}{2}', $result);
        $this->assertStringNotContainsString('stored answer', strtolower($result));
        $this->assertStringNotContainsString("\f", $result);
        $this->assertStringNotContainsString("\u{1F680}", $result);
    }
    public function test_saved_answer_is_preserved_even_if_model_returns_a_different_option(): void
    {
        [$service, $draft, $result] = $this->resultFixture([2]);
        $result['answer']['correct_option_indices'] = [1];
        $attributes = $this->invoke($service, 'resultAttributes', $draft, $result, ['provider' => 'chatgpt', 'model' => 'test']);
        $this->assertSame([2], $attributes['proposed_payload']['correct_option_indices']);
        $this->assertSame($result['explanation'], $attributes['proposed_payload']['explanation']);
        $this->assertSame('ready', $attributes['status']);
    }

    public function test_missing_mcq_answer_is_generated(): void
    {
        [$service, $draft, $result] = $this->resultFixture([]);
        $attributes = $this->invoke($service, 'resultAttributes', $draft, $result, ['provider' => 'chatgpt', 'model' => 'test']);
        $this->assertSame([1], $attributes['proposed_payload']['correct_option_indices']);
    }

    public function test_random_saved_answer_is_solved_instead_of_preserved(): void
    {
        [$service, $draft, $result] = $this->resultFixture([2]);
        $original = $draft->original_payload;
        $original['_answer_verification']['status'] = 'unverified';
        $draft->original_payload = $original;
        $draft->mode = $service->mode($draft->question, ['status' => 'unverified']);
        $this->assertSame('generate_answer_explanation', $draft->mode);
        $result['answer']['correct_option_indices'] = [1];
        $attributes = $this->invoke($service, 'resultAttributes', $draft, $result, ['provider' => 'chatgpt', 'model' => 'test']);
        $this->assertSame([1], $attributes['proposed_payload']['correct_option_indices']);
        $this->assertSame('discrepancy', $attributes['status']);
    }

    public function test_short_natural_explanation_is_accepted(): void
    {
        [$service, $draft, $result] = $this->resultFixture([2]);
        $result['explanation'] = '<p>Both terms cancel, leaving zero.</p>';
        $attributes = $this->invoke($service, 'resultAttributes', $draft, $result, ['provider' => 'chatgpt', 'model' => 'test']);
        $this->assertSame($result['explanation'], $attributes['proposed_payload']['explanation']);
    }

    public function test_changed_verification_state_requires_regeneration(): void
    {
        [$service, $draft] = $this->resultFixture([2]);
        $this->expectExceptionMessage('verification state changed');
        $this->invoke($service, 'assertVerification', $draft, ['policy_version' => 1, 'status' => 'unverified']);
    }

    public function test_unverified_answer_is_not_sent_to_the_model(): void
    {
        [$service, $draft] = $this->resultFixture([2]);
        $draft->id = 1;
        $original = $draft->original_payload;
        $original['_answer_verification']['status'] = 'unverified';
        $draft->original_payload = $original;
        $draft->mode = 'generate_answer_explanation';
        (new \ReflectionProperty($service, 'curriculumContexts'))->setValue($service, [1 => ['fixed_subject' => 'Mathematics']]);
        $prompt = $this->invoke($service, 'prompt', new \App\Models\AiAnswerRun(), collect([$draft]));
        $items = json_decode(explode("\nQuestions: ", $prompt, 2)[1], true, flags: JSON_THROW_ON_ERROR);
        $this->assertNull($items[0]['stored_answer']);
        $this->assertSame('unverified_solve_independently', $items[0]['answer_status']);
    }

    public function test_official_nat_minimum_and_disjoint_ranges_are_valid(): void
    {
        $service = new AiAnswerService();
        $this->assertSame(3.0, $this->invoke($service, 'natConfig', ['mode' => 'minimum', 'min' => 3])['min']);
        $this->assertCount(2, $this->invoke($service, 'natConfig', ['mode' => 'ranges', 'ranges' => [['min' => 1, 'max' => 2], ['min' => 4, 'max' => 5]]])['ranges']);
    }

    public function test_missing_msq_answer_accepts_multiple_options(): void
    {
        [$service, $draft, $result] = $this->resultFixture([]);
        $draft->question->setRelation('qtype', new Qtype(['type' => 'MSQ', 'question_type' => 'Multiple Select']));
        $result['answer']['correct_option_indices'] = [1, 2];
        $attributes = $this->invoke($service, 'resultAttributes', $draft, $result, ['provider' => 'chatgpt', 'model' => 'test']);
        $this->assertSame([1, 2], $attributes['proposed_payload']['correct_option_indices']);
    }

    public function test_missing_nat_answer_accepts_zero(): void
    {
        [$service, $draft, $result] = $this->resultFixture([]);
        $draft->question->setRelation('qtype', new Qtype(['type' => 'NAT', 'question_type' => 'Numerical Answer']));
        $result['answer'] = ['nat_config' => ['mode' => 'exact', 'value' => 0]];
        $attributes = $this->invoke($service, 'resultAttributes', $draft, $result, ['provider' => 'chatgpt', 'model' => 'test']);
        $this->assertSame(0.0, $attributes['proposed_payload']['nat_config']['value']);
    }

    public function test_unjustifiable_saved_answer_is_blocked(): void
    {
        [$service, $draft, $result] = $this->resultFixture([2]);
        $result['stored_answer_consistent'] = false;
        $this->expectExceptionMessage('saved answer cannot be justified');
        $this->invoke($service, 'resultAttributes', $draft, $result, ['provider' => 'chatgpt', 'model' => 'test']);
    }

    public function test_ai_cannot_change_the_subject(): void
    {
        [$service, $draft, $result] = $this->resultFixture([]);
        $result['classification']['subject'] = 'Chemistry';
        $this->expectExceptionMessage('change the assigned subject');
        $this->invoke($service, 'resultAttributes', $draft, $result, ['provider' => 'chatgpt', 'model' => 'test']);
    }

    public function test_blank_explanation_is_rejected(): void
    {
        [$service, $draft, $result] = $this->resultFixture([]);
        $result['explanation'] = '';
        $this->expectExceptionMessage('explanation that is too short');
        $this->invoke($service, 'resultAttributes', $draft, $result, ['provider' => 'chatgpt', 'model' => 'test']);
    }

    public function test_mcq_rejects_multiple_answers(): void
    {
        [$service, $draft, $result] = $this->resultFixture([]);
        $result['answer']['correct_option_indices'] = [1, 2];
        $this->expectExceptionMessage('exactly one correct option');
        $this->invoke($service, 'resultAttributes', $draft, $result, ['provider' => 'chatgpt', 'model' => 'test']);
    }

    public function test_math_validator_accepts_latex_and_chemistry(): void
    {
        app(AiAnswerMathValidator::class)->validate('<p>Use \\( \\frac{1}{2} \\) and \\[ \\ce{2H2 + O2 -> 2H2O} \\].</p>');
        $this->addToAssertionCount(1);
    }

    public function test_incomplete_result_fields_and_nonexistent_options_are_rejected(): void
    {
        foreach (['difficulty', 'topic', 'subtopic', 'option', 'answer'] as $invalidField) {
            [$service, $draft, $result] = $this->resultFixture([]);
            if ($invalidField === 'difficulty') $result['difficulty'] = '';
            elseif (in_array($invalidField, ['topic', 'subtopic'], true)) $result['classification'][$invalidField] = '';
            elseif ($invalidField === 'option') $result['answer']['correct_option_indices'] = [6];
            else $result['answer'] = [];
            try {
                $this->invoke($service, 'resultAttributes', $draft, $result, ['provider' => 'chatgpt', 'model' => 'test']);
                $this->fail('Accepted an incomplete result: '.$invalidField);
            } catch (\RuntimeException $exception) {
                $this->assertNotEmpty($exception->getMessage());
            }
        }
    }

    public function test_nat_requires_a_real_numeric_value(): void
    {
        $this->expectExceptionMessage('valid NAT value');
        $this->invoke(new AiAnswerService(), 'natConfig', ['mode' => 'exact', 'value' => '']);
    }

    public function test_math_validator_rejects_malformed_or_unwrapped_formulas(): void
    {
        foreach (['x = 2', '\\( x \\]', '\\( \\frac{1}{2 \\)', '$x$', '\\frac{1}{2}', 'H2O', '\\( \\begin{cases} x \\end{matrix} \\)'] as $text) {
            try {
                app(AiAnswerMathValidator::class)->validate($text);
                $this->fail('Accepted invalid notation: '.$text);
            } catch (\RuntimeException $exception) {
                $this->assertNotEmpty($exception->getMessage());
            }
        }
    }

    private function invoke(object $service, string $method, mixed ...$args): mixed
    {
        return (new \ReflectionMethod($service, $method))->invoke($service, ...$args);
    }

    private function resultFixture(array $indices): array
    {
        $service = new AiAnswerService();
        (new \ReflectionProperty($service, 'difficultyIds'))->setValue($service, ['Easy' => 1]);
        $question = $this->multipleChoiceQuestion($indices);
        $question->forceFill(['organization_id' => 1, 'subject_id' => 10, 'topic_id' => 20, 'stopic_id' => 30, 'diff_id' => 1]);
        $question->setRelation('subject', (new Subject())->forceFill(['id' => 10, 'organization_id' => 1, 'subject_name' => 'Mathematics']));
        $question->setRelation('topic', (new Topic())->forceFill(['id' => 20, 'subject_id' => 10, 'name' => 'Algebra']));
        $question->setRelation('stopic', (new Stopic())->forceFill(['id' => 30, 'subject_id' => 10, 'topic_id' => 20, 'name' => 'Linear equations']));
        $verification = ['policy_version' => 1, 'status' => $indices === [] ? 'unverified' : 'verified'];
        $draft = new AiAnswerDraft(['mode' => $service->mode($question, $verification), 'original_payload' => [...$service->snapshot($question), '_answer_verification' => $verification]]);
        $draft->setRelation('question', $question);
        return [$service, $draft, [
            'difficulty' => 'Easy', 'classification' => ['subject' => 'Mathematics', 'topic' => 'Algebra', 'subtopic' => 'Linear equations'],
            'answer' => ['correct_option_indices' => [1]], 'stored_answer_consistent' => true,
            'explanation' => '<p>Substitute the stated values into the defining relationship and simplify each term. The resulting value satisfies the original condition, which justifies the selected answer.</p>',
            'discrepancies' => [],
        ]];
    }

    private function multipleChoiceQuestion(array $correctIndices): Question
    {
        $question = new Question([
            'question' => '<p>Which option is correct?</p>',
            'option1' => '<p>A</p>', 'option2' => '<p>B</p>',
            'correct_option_indices' => $correctIndices,
        ]);
        $question->setRelation('qtype', new Qtype(['type' => 'M', 'question_type' => 'Multiple Choices']));
        return $question;
    }
}

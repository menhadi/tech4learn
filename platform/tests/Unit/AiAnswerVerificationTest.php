<?php

namespace Tests\Unit;

use App\Models\{Exam, Qtype, Question, SourceExamImport, SourceExamQuestionDraft};
use App\Services\AiAnswerVerification;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};
use Tests\TestCase;

class AiAnswerVerificationTest extends TestCase
{
    private function fixture(): array
    {
        $question = (new Question())->forceFill(['id' => 10, 'organization_id' => 1, 'correct_option_indices' => [2]]);
        $question->setRelation('qtype', new Qtype(['type' => 'M', 'question_type' => 'MCQ']));
        $import = (new SourceExamImport())->forceFill(['id' => 20, 'organization_id' => 1, 'exam_id' => 30, 'detected_questions' => 65,
            'settings' => ['answer_key_verification' => ['status' => 'completed', 'checked' => 65, 'outcome' => 'key_review', 'errors' => [], 'changes' => [['question' => '1']]]]]);
        $draft = (new SourceExamQuestionDraft())->forceFill(['id' => 40, 'source_exam_import_id' => 20, 'question_id' => 10, 'payload' => ['correct_answers' => [2]]]);
        return [$question, $import, $draft];
    }

    public function test_green_badge_and_matching_answer_are_verified(): void
    {
        [$q, $import, $draft] = $this->fixture();
        $this->assertSame('verified', (new AiAnswerVerification())->classify($q, $import, $draft)['status']);
    }

    public function test_no_key_found_overrides_old_counts_and_changes(): void
    {
        [$q, $import, $draft] = $this->fixture();
        $settings = $import->settings;
        $settings['answer_key_verification']['outcome'] = 'no_key_found';
        $settings['answer_key_verification']['status'] = 'blocked';
        $import->settings = $settings;
        $this->assertSame('unverified', (new AiAnswerVerification())->classify($q, $import, $draft)['status']);
    }

    public function test_updated_count_alone_is_not_verification(): void
    {
        [$q, $import, $draft] = $this->fixture();
        $import->settings = ['answer_key_verification' => ['changes' => [['question' => 1]]]];
        $this->assertSame('unverified', (new AiAnswerVerification())->classify($q, $import, $draft)['status']);
    }

    public function test_changed_live_answer_is_held_for_review(): void
    {
        [$q, $import, $draft] = $this->fixture();
        $q->correct_option_indices = [1];
        $this->assertSame('review_required', (new AiAnswerVerification())->classify($q, $import, $draft)['status']);
    }

    public function test_unmapped_question_does_not_inherit_paper_verification(): void
    {
        [$q, $import] = $this->fixture();
        $this->assertSame('unverified', (new AiAnswerVerification())->classify($q, $import, null)['status']);
    }

    public function test_partial_verification_and_warnings_do_not_protect_unproven_answers(): void
    {
        foreach ([['checked' => 30], ['errors' => ['Question 3 needs review']], ['status' => 'processing']] as $override) {
            [$q, $import, $draft] = $this->fixture();
            $settings = $import->settings;
            $settings['answer_key_verification'] = [...$settings['answer_key_verification'], ...$override];
            $import->settings = $settings;
            $this->assertSame('review_required', (new AiAnswerVerification())->classify($q, $import, $draft)['status']);
        }
    }

    public function test_nat_official_zero_and_range_are_preserved(): void
    {
        foreach ([['mode' => 'exact', 'value' => 0], ['mode' => 'range', 'min' => 3, 'max' => 4]] as $nat) {
            [$q, $import, $draft] = $this->fixture();
            $q->setRelation('qtype', new Qtype(['type' => 'NAT']));
            $q->nat_config = $nat;
            $draft->payload = ['nat_config' => $nat];
            $this->assertSame('verified', (new AiAnswerVerification())->classify($q, $import, $draft)['status']);
        }
    }

    public function test_lookup_uses_latest_import_and_question_ids_not_printed_numbers(): void
    {
        // Dedicated in-memory connection: this test never touches application data.
        config(['database.connections.answer_verification_test' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''], 'database.default' => 'answer_verification_test']);
        DB::purge('answer_verification_test');
        Schema::create('source_exam_imports', function (Blueprint $table) {
            $table->id(); $table->integer('organization_id'); $table->integer('exam_id'); $table->integer('detected_questions'); $table->text('settings');
        });
        Schema::create('source_exam_question_drafts', function (Blueprint $table) {
            $table->id(); $table->integer('source_exam_import_id'); $table->integer('question_id'); $table->text('payload');
        });
        [$q, $import, $draft] = $this->fixture();
        DB::table('source_exam_imports')->insert(['id' => 20, 'organization_id' => 1, 'exam_id' => 30, 'detected_questions' => 65, 'settings' => json_encode($import->settings)]);
        DB::table('source_exam_question_drafts')->insert(['id' => 40, 'source_exam_import_id' => 20, 'question_id' => 10, 'payload' => json_encode($draft->payload)]);
        $exam = (new Exam())->forceFill(['id' => 30, 'organization_id' => 1]);
        $service = new AiAnswerVerification();
        $this->assertSame('verified', $service->forExam($exam, [$q])[10]['status']);
        DB::table('source_exam_imports')->insert(['id' => 21, 'organization_id' => 1, 'exam_id' => 30, 'detected_questions' => 65, 'settings' => json_encode(['answer_key_verification' => ['outcome' => 'no_key_found']])]);
        $this->assertSame('unverified', $service->forExam($exam, [$q])[10]['status']);
    }
}

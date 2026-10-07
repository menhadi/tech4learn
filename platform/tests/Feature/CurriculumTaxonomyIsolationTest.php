<?php

namespace Tests\Feature;

use App\Exports\QuestionsExport;
use App\Models\Exam;
use App\Models\Group;
use App\Models\Organization;
use App\Models\Question;
use App\Models\Qtype;
use App\Models\Stopic;
use App\Models\Subject;
use App\Models\Topic;
use App\Services\CurriculumTaxonomyService;
use App\Services\ExamWorkbookService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CurriculumTaxonomyIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_same_subject_name_is_isolated_between_tenants(): void
    {
        $a = Organization::create(['name' => 'Tenant A', 'slug' => 'tenant-a', 'domain' => 'a.test', 'status' => 1]);
        $b = Organization::create(['name' => 'Tenant B', 'slug' => 'tenant-b', 'domain' => 'b.test', 'status' => 1]);

        $mathA = Subject::create(['organization_id' => $a->id, 'subject_name' => 'Mathematics']);
        $mathB = Subject::create(['organization_id' => $b->id, 'subject_name' => 'Mathematics']);

        $this->assertNotSame($mathA->id, $mathB->id);
        $this->assertDatabaseCount('subjects', 2);
    }

    public function test_same_subject_has_independent_topic_trees_per_group(): void
    {
        $tenant = Organization::create(['name' => 'Tenant', 'slug' => 'tenant', 'domain' => 'tenant.test', 'status' => 1]);
        $engineering = Group::create(['organization_id' => $tenant->id, 'group_name' => 'Engineering']);
        $jee = Group::create(['organization_id' => $tenant->id, 'group_name' => 'IIT-JEE']);
        $math = Subject::create(['organization_id' => $tenant->id, 'subject_name' => 'Mathematics']);
        $math->groups()->attach([$engineering->id, $jee->id]);

        $engineeringTopic = Topic::create([
            'subject_id' => $math->id, 'group_id' => $engineering->id, 'name' => 'Engineering Calculus',
        ]);
        $jeeTopic = Topic::create([
            'subject_id' => $math->id, 'group_id' => $jee->id, 'name' => 'JEE Calculus',
        ]);

        $this->assertNotSame($engineeringTopic->id, $jeeTopic->id);
        $this->assertSame(['Engineering Calculus'], Topic::where('group_id', $engineering->id)->pluck('name')->all());
        $this->assertSame(['JEE Calculus'], Topic::where('group_id', $jee->id)->pluck('name')->all());
    }

    public function test_mismatched_topic_and_subtopic_are_rejected(): void
    {
        $tenant = Organization::create(['name' => 'Tenant', 'slug' => 'tenant', 'domain' => 'tenant.test', 'status' => 1]);
        $engineering = Group::create(['organization_id' => $tenant->id, 'group_name' => 'Engineering']);
        $jee = Group::create(['organization_id' => $tenant->id, 'group_name' => 'IIT-JEE']);
        $math = Subject::create(['organization_id' => $tenant->id, 'subject_name' => 'Mathematics']);
        $math->groups()->attach([$engineering->id, $jee->id]);
        $jeeTopic = Topic::create(['subject_id' => $math->id, 'group_id' => $jee->id, 'name' => 'Algebra']);

        $this->expectException(ValidationException::class);
        app(CurriculumTaxonomyService::class)->validateSelection(
            $tenant->id, [$engineering->id], $math->id, $jeeTopic->id, null
        );
    }

    public function test_question_receives_one_explicit_mapping_per_group(): void
    {
        $tenant = Organization::create(['name' => 'Tenant', 'slug' => 'tenant', 'domain' => 'tenant.test', 'status' => 1]);
        $engineering = Group::create(['organization_id' => $tenant->id, 'group_name' => 'Engineering']);
        $jee = Group::create(['organization_id' => $tenant->id, 'group_name' => 'IIT-JEE']);
        $math = Subject::create(['organization_id' => $tenant->id, 'subject_name' => 'Mathematics']);
        $math->groups()->attach([$engineering->id, $jee->id]);
        $engineeringTopic = Topic::create(['subject_id' => $math->id, 'group_id' => $engineering->id, 'name' => 'Calculus']);
        $jeeTopic = Topic::create(['subject_id' => $math->id, 'group_id' => $jee->id, 'name' => 'Calculus']);
        $engineeringSubtopic = Stopic::create([
            'subject_id' => $math->id, 'group_id' => $engineering->id,
            'topic_id' => $engineeringTopic->id, 'name' => 'Limits',
        ]);
        Stopic::create([
            'subject_id' => $math->id, 'group_id' => $jee->id,
            'topic_id' => $jeeTopic->id, 'name' => 'Limits',
        ]);
        $qtype = Qtype::create(['question_type' => 'Multiple Choice', 'type' => 'M']);
        $question = Question::forceCreate([
            'organization_id' => $tenant->id,
            'qtype_id' => $qtype->id,
            'subject_id' => $math->id,
            'topic_id' => $engineeringTopic->id,
            'stopic_id' => $engineeringSubtopic->id,
            'question' => 'A limit question',
        ]);
        $question->groups()->attach([$engineering->id, $jee->id]);

        app(CurriculumTaxonomyService::class)->syncQuestion($question->fresh(['topic', 'stopic']), [$engineering->id, $jee->id]);

        $this->assertDatabaseHas('question_taxonomies', [
            'question_id' => $question->id, 'group_id' => $engineering->id,
            'topic_id' => $engineeringTopic->id,
        ]);
        $this->assertDatabaseHas('question_taxonomies', [
            'question_id' => $question->id, 'group_id' => $jee->id,
            'topic_id' => $jeeTopic->id,
        ]);
    }
    public function test_quick_quiz_does_not_authorize_questions_through_shared_subject_membership(): void
    {
        $tenant = Organization::create(['name' => 'Tenant', 'slug' => 'tenant', 'domain' => 'tenant.test', 'status' => 1]);
        $engineering = Group::create(['organization_id' => $tenant->id, 'group_name' => 'Engineering']);
        $jee = Group::create(['organization_id' => $tenant->id, 'group_name' => 'IIT-JEE']);
        $math = Subject::create(['organization_id' => $tenant->id, 'subject_name' => 'Mathematics']);
        $math->groups()->attach([$engineering->id, $jee->id]);
        $qtype = Qtype::create(['question_type' => 'Multiple Choice', 'type' => 'M']);
        $question = Question::forceCreate([
            'organization_id' => $tenant->id,
            'qtype_id' => $qtype->id,
            'subject_id' => $math->id,
            'question' => 'Engineering-only question',
            'option1' => 'A',
            'option2' => 'B',
            'correct_option_indices' => [1],
            'status' => 'Yes',
        ]);
        $question->groups()->attach($engineering->id);

        $pool = app(\App\Services\QuickQuizQuestionPoolService::class);

        $this->assertSame(1, $pool->query(['group_id' => $engineering->id], $tenant->id)->count());
        $this->assertSame(0, $pool->query(['group_id' => $jee->id], $tenant->id)->count());
    }

    public function test_question_export_uses_the_selected_groups_explicit_taxonomy(): void
    {
        $tenant = Organization::create(['name' => 'Tenant', 'slug' => 'tenant', 'domain' => 'tenant.test', 'status' => 1]);
        $engineering = Group::create(['organization_id' => $tenant->id, 'group_name' => 'Engineering']);
        $jee = Group::create(['organization_id' => $tenant->id, 'group_name' => 'IIT-JEE']);
        $math = Subject::create(['organization_id' => $tenant->id, 'subject_name' => 'Mathematics']);
        $math->groups()->attach([$engineering->id, $jee->id]);
        $engineeringTopic = Topic::create(['subject_id' => $math->id, 'group_id' => $engineering->id, 'name' => 'Engineering Calculus']);
        $jeeTopic = Topic::create(['subject_id' => $math->id, 'group_id' => $jee->id, 'name' => 'JEE Calculus']);
        $qtype = Qtype::create(['question_type' => 'Multiple Choice', 'type' => 'M']);
        $question = Question::forceCreate([
            'organization_id' => $tenant->id,
            'qtype_id' => $qtype->id,
            'subject_id' => $math->id,
            'topic_id' => $engineeringTopic->id,
            'question' => 'A calculus question',
        ]);
        $question->groups()->attach([$engineering->id, $jee->id]);
        $question->taxonomies()->create([
            'organization_id' => $tenant->id,
            'group_id' => $engineering->id,
            'subject_id' => $math->id,
            'topic_id' => $engineeringTopic->id,
        ]);
        $question->taxonomies()->create([
            'organization_id' => $tenant->id,
            'group_id' => $jee->id,
            'subject_id' => $math->id,
            'topic_id' => $jeeTopic->id,
        ]);
        $question->load([
            'groups', 'subject', 'questionSection', 'topic', 'stopic',
            'taxonomies.subject', 'taxonomies.topic', 'taxonomies.stopic',
            'diff', 'qtype', 'language', 'passage', 'tags', 'exams',
        ]);

        $export = new QuestionsExport(Question::query(), $jee->id);
        $row = array_combine($export->headings(), $export->map($question));

        $this->assertSame('Mathematics', $row['subject']);
        $this->assertSame('JEE Calculus', $row['topic']);
    }

    public function test_exam_workbook_rejects_a_topic_from_another_group_context(): void
    {
        $tenant = Organization::create(['name' => 'Tenant', 'slug' => 'tenant', 'domain' => 'tenant.test', 'status' => 1]);
        $engineering = Group::create(['organization_id' => $tenant->id, 'group_name' => 'Engineering']);
        $jee = Group::create(['organization_id' => $tenant->id, 'group_name' => 'IIT-JEE']);
        $math = Subject::create(['organization_id' => $tenant->id, 'subject_name' => 'Mathematics']);
        $math->groups()->attach([$engineering->id, $jee->id]);
        $jeeTopic = Topic::create(['subject_id' => $math->id, 'group_id' => $jee->id, 'name' => 'JEE Calculus']);

        $method = new \ReflectionMethod(ExamWorkbookService::class, 'validateRow');
        $errors = $method->invoke(new ExamWorkbookService(), [
            'operation' => 'CREATE',
            'name' => 'Engineering Test',
            'group_ids' => (string) $engineering->id,
            'start_date' => '2026-08-01 10:00:00',
            'end_date' => '2026-08-01 11:00:00',
            'test_type' => Exam::TEST_TYPE_TOPIC,
            'test_subject_id' => $math->id,
            'test_topic_id' => $jeeTopic->id,
        ], $tenant->id);

        $this->assertContains('The selected topic does not belong to this subject/group context.', $errors);
    }}

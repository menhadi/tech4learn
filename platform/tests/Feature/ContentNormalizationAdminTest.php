<?php

namespace Tests\Feature;

use App\Models\ContentNormalizationRun;
use App\Models\Diff;
use App\Models\Organization;
use App\Models\Package;
use App\Models\Qtype;
use App\Models\Question;
use App\Models\Subject;
use App\Models\User;
use App\Support\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ContentNormalizationAdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.url' => 'https://platform.test']);
        Cache::flush();
        Tenant::clear();
        if (DB::connection()->getDriverName() === 'sqlite') {
            DB::connection()->getPdo()->sqliteCreateFunction('FIELD', function ($value, ...$ordered): int {
                $index = array_search($value, $ordered, true);
                return $index === false ? 0 : $index + 1;
            });
        }
    }

    public function test_tenant_admin_can_apply_and_restore_a_package_inherited_paper(): void
    {
        [$admin, $tenantA, $tenantB] = $this->tenantAdminAndOrganizations();
        $mathml = '<p><math><mi>x</mi><mo>=</mo><mn>1</mn></math></p>';
        $questionA = $this->question($tenantA, $mathml);
        $questionB = $this->question($tenantB, $mathml);
        [$groupA, $categoryA, $packageA, $examA] = $this->packagePaper($tenantA, $questionA);

        $search = $this->actingAs($admin)->getJson('https://a.test/content-normalization/exams/search?group_id='.$groupA.'&category_id='.$categoryA.'&package_id='.$packageA)
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('results.0.id', $examA)
            ->assertJsonPath('results.0.text', 'GATE Paper');
        $this->assertSame(1, $search->json('results.0.question_count'));

        $created = $this->postJson('https://a.test/content-normalization/runs', [
            'selection_mode' => 'selected',
            'mode' => 'apply',
            'content_scope' => 'mathml_only',
            'exam_ids' => [$examA],
            'confirm_apply' => true,
        ])->assertCreated()->assertJsonPath('run.total_questions', 1);
        $runId = $created->json('run.id');

        $this->postJson("https://a.test/content-normalization/runs/{$runId}/step")->assertOk()->assertJsonPath('done', false);
        $this->postJson("https://a.test/content-normalization/runs/{$runId}/step")->assertOk()->assertJsonPath('done', true);
        $this->assertSame('<p>\\(x=1\\)</p>', $questionA->fresh()->question);
        $this->assertSame($mathml, $questionB->fresh()->question);

        $this->postJson("https://a.test/content-normalization/runs/{$runId}/restore")->assertOk()->assertJsonPath('run.status', 'restoring');
        $this->postJson("https://a.test/content-normalization/runs/{$runId}/restore-step")->assertOk()->assertJsonPath('done', false);
        $this->postJson("https://a.test/content-normalization/runs/{$runId}/restore-step")->assertOk()->assertJsonPath('done', true);
        $this->assertSame($mathml, $questionA->fresh()->question);
    }

    public function test_tenant_admin_can_restore_one_then_multiple_papers_from_the_same_run(): void
    {
        [$admin, $tenant] = $this->tenantAdminAndOrganizations();
        $mathA = '<p><math><mi>x</mi><mo>=</mo><mn>1</mn></math></p>';
        $mathB = '<p><math><mi>y</mi><mo>=</mo><mn>2</mn></math></p>';
        $questionA = $this->question($tenant, $mathA);
        $questionB = $this->question($tenant, $mathB);
        [, , , $examA] = $this->packagePaper($tenant, $questionA);
        $examB = $this->exam($tenant, 'JEE Paper');
        DB::table('exam_questions')->insert(['exam_id' => $examB, 'question_id' => $questionB->id, 'created_at' => now(), 'updated_at' => now()]);

        $created = $this->actingAs($admin)->postJson('https://a.test/content-normalization/runs', [
            'selection_mode' => 'selected',
            'mode' => 'apply',
            'content_scope' => 'mathml_only',
            'exam_ids' => [$examA, $examB],
            'confirm_apply' => true,
        ])->assertCreated();
        $runId = $created->json('run.id');

        $this->postJson("https://a.test/content-normalization/runs/{$runId}/step")->assertOk();
        $this->postJson("https://a.test/content-normalization/runs/{$runId}/step")->assertOk()->assertJsonPath('done', true);
        $this->assertSame('<p>\\(x=1\\)</p>', $questionA->fresh()->question);
        $this->assertSame('<p>\\(y=2\\)</p>', $questionB->fresh()->question);

        $this->postJson("https://a.test/content-normalization/runs/{$runId}/restore", ['exam_ids' => [$examA]])
            ->assertOk()->assertJsonPath('run.restore_exam_ids.0', $examA);
        $this->postJson("https://a.test/content-normalization/runs/{$runId}/restore-step")->assertOk()->assertJsonPath('done', false);
        $this->postJson("https://a.test/content-normalization/runs/{$runId}/restore-step")->assertOk()->assertJsonPath('done', true);
        $this->assertSame($mathA, $questionA->fresh()->question);
        $unrelatedExam = $this->exam($tenant, 'Unrelated Paper');
        $this->postJson("https://a.test/content-normalization/runs/{$runId}/restore", ['exam_ids' => [$unrelatedExam]])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'One or more selected papers do not belong to this cleanup run.');
        $this->assertSame('<p>\\(y=2\\)</p>', $questionB->fresh()->question);
        $run = ContentNormalizationRun::findOrFail($runId);
        $this->assertSame('completed', $run->status);
        $this->assertSame([$examA], $run->restored_exam_ids);

        $this->get('https://a.test/content-normalization')
            ->assertOk()
            ->assertSee('GATE Paper')
            ->assertSee('JEE Paper')
            ->assertSee('Select all remaining');

        $this->postJson("https://a.test/content-normalization/runs/{$runId}/restore", ['exam_ids' => [$examB]])->assertOk();
        $this->postJson("https://a.test/content-normalization/runs/{$runId}/restore-step")->assertOk()->assertJsonPath('done', false);
        $this->postJson("https://a.test/content-normalization/runs/{$runId}/restore-step")->assertOk()->assertJsonPath('done', true);
        $this->assertSame($mathB, $questionB->fresh()->question);
        $run = ContentNormalizationRun::findOrFail($runId);
        $this->assertSame('restored', $run->status);
        $this->assertSame([$examA, $examB], $run->restored_exam_ids);
    }

    public function test_all_matching_selection_uses_the_image_cleanup_hierarchy(): void
    {
        [$admin, $tenantA] = $this->tenantAdminAndOrganizations();
        $question = $this->question($tenantA, '<p><math><mi>y</mi></math></p>');
        [$group, $category, $package, $exam] = $this->packagePaper($tenantA, $question);

        $query = "group_id={$group}&category_id={$category}&package_id={$package}&q=GATE";
        $this->actingAs($admin)->getJson('https://a.test/content-normalization/exams/selection-summary?'.$query)
            ->assertOk()
            ->assertJson(['paper_count' => 1, 'question_count' => 1]);

        $created = $this->postJson('https://a.test/content-normalization/runs', [
            'selection_mode' => 'all_filtered',
            'mode' => 'preview',
            'content_scope' => 'mathml_only',
            'group_id' => $group,
            'category_id' => $category,
            'package_id' => $package,
            'q' => 'GATE',
        ])->assertCreated()->assertJsonPath('run.total_questions', 1);
        $this->assertSame([$exam], ContentNormalizationRun::findOrFail($created->json('run.id'))->filters['exam_ids']);
    }

    public function test_tenant_cannot_select_or_restore_another_organizations_data(): void
    {
        [$admin, $tenantA, $tenantB] = $this->tenantAdminAndOrganizations();
        $foreignQuestion = $this->question($tenantB, '<p><math><mi>z</mi></math></p>');
        [, , , $foreignExam] = $this->packagePaper($tenantB, $foreignQuestion);

        $this->actingAs($admin)->postJson('https://a.test/content-normalization/runs', [
            'selection_mode' => 'selected',
            'mode' => 'preview',
            'content_scope' => 'mathml_only',
            'exam_ids' => [$foreignExam],
        ])->assertUnprocessable();

        $foreignRun = ContentNormalizationRun::create([
            'organization_id' => $tenantB->id,
            'requested_by' => $admin->id,
            'mode' => 'preview',
            'status' => 'completed',
            'filters' => ['exam_ids' => [$foreignExam]],
            'total_questions' => 1,
            'stats' => [],
        ]);
        $this->postJson("https://a.test/content-normalization/runs/{$foreignRun->id}/restore")->assertNotFound();
        $this->assertDatabaseHas('organizations', ['id' => $tenantA->id]);
    }

    public function test_cleanup_is_not_a_saas_route_and_menu_follows_image_cleanup(): void
    {
        $this->assertFalse(Route::has('saas.content-normalization.index'));
        $this->assertFalse(Route::has('configurations.content-normalization.index'));
        $imageOrdering = DB::table('pages')->where('action_name', 'image-cleanup.index')->value('ordering');
        $cleanup = DB::table('pages')->where('action_name', 'content-normalization.index')->first();
        $this->assertNotNull($cleanup);
        $this->assertSame((int) $imageOrdering + 1, (int) $cleanup->ordering);
    }

    public function test_recommended_scope_cleans_html_only_wrappers_and_preserves_semantic_math_markup(): void
    {
        [$admin, $tenant] = $this->tenantAdminAndOrganizations();
        $question = $this->question($tenant, '<p>The area is y<sup class="source-style">2</sup>.</p><div class="grow question xl:text-lg">\\(8/3\\) sq. units</div>');
        [, , , $exam] = $this->packagePaper($tenant, $question);

        $created = $this->actingAs($admin)->postJson('https://a.test/content-normalization/runs', [
            'selection_mode' => 'selected',
            'mode' => 'apply',
            'content_scope' => 'all_content',
            'exam_ids' => [$exam],
            'confirm_apply' => true,
        ])->assertCreated();

        $run = ContentNormalizationRun::findOrFail($created->json('run.id'));
        $this->assertFalse($run->filters['only_mathml']);

        $this->postJson("https://a.test/content-normalization/runs/{$run->id}/step")->assertOk();

        $normalized = $question->fresh()->question;
        $this->assertStringContainsString('<sup>2</sup>', $normalized);
        $this->assertStringContainsString('\\(8/3\\)', $normalized);
        $this->assertStringNotContainsString('<div', $normalized);
        $this->assertStringNotContainsString('source-style', $normalized);
        $this->assertStringNotContainsString('grow question', $normalized);
    }

    private function tenantAdminAndOrganizations(): array
    {
        $features = DB::table('saas_plans')->where('slug', 'examelite-default')->value('features');
        $features = is_string($features) ? json_decode($features, true) : (array) $features;
        $features['exam_quality_audit'] = true;
        $features['exam_quality_ai'] = true;
        $planId = DB::table('saas_plans')->where('slug', 'examelite-default')->value('id');
        DB::table('saas_plans')->where('id', $planId)->update(['features' => json_encode($features)]);

        $admin = User::forceCreate(['name' => 'Tenant A Admin', 'username' => 'tenant-a-admin', 'email' => 'admin-a@example.test', 'password' => bcrypt('password'), 'status' => 'Active', 'is_platform_admin' => false]);
        $tenantA = Organization::create(['saas_plan_id' => $planId, 'name' => 'Tenant A', 'slug' => 'tenant-a', 'domain' => 'a.test', 'status' => 'active']);
        $tenantB = Organization::create(['saas_plan_id' => $planId, 'name' => 'Tenant B', 'slug' => 'tenant-b', 'domain' => 'b.test', 'status' => 'active']);
        DB::table('organization_users')->insert(['organization_id' => $tenantA->id, 'user_id' => $admin->id, 'role' => 'admin', 'status' => true, 'created_at' => now(), 'updated_at' => now()]);

        return [$admin, $tenantA, $tenantB];
    }

    private function packagePaper(Organization $tenant, Question $question): array
    {
        $suffix = (string) $tenant->id;
        $group = DB::table('groups')->insertGetId(['organization_id' => $tenant->id, 'group_name' => json_encode(['en' => 'Engineering']), 'created_at' => now(), 'updated_at' => now()]);
        $category = DB::table('category')->insertGetId(['organization_id' => $tenant->id, 'title' => json_encode(['en' => 'GATE']), 'slug' => 'gate-'.$suffix, 'status' => 1, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('category_groups')->insert(['category_id' => $category, 'group_id' => $group, 'created_at' => now(), 'updated_at' => now()]);
        $package = Package::create(['organization_id' => $tenant->id, 'name' => 'GATE Package', 'slug' => 'gate-package-'.$suffix, 'description' => 'Fixture', 'package_type' => 'free', 'expiry_days' => 30, 'status' => true, 'category_level_1' => $category]);
        $exam = $this->exam($tenant, 'GATE Paper');
        DB::table('package_groups')->insert(['package_id' => $package->id, 'group_id' => $group, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('exam_packages')->insert(['exam_id' => $exam, 'package_id' => $package->id, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('exam_questions')->insert(['exam_id' => $exam, 'question_id' => $question->id, 'created_at' => now(), 'updated_at' => now()]);

        return [$group, $category, $package->id, $exam];
    }

    private function question(Organization $tenant, string $content): Question
    {
        $qtype = Qtype::firstOrCreate(['question_type' => 'Multiple Choice'], ['type' => 'M']);
        $difficulty = Diff::firstOrCreate(['diff_level' => 'Easy'], ['type' => 'E']);
        $subject = Subject::firstOrCreate(['organization_id' => $tenant->id, 'subject_name' => 'Mathematics']);

        return Question::forceCreate(['organization_id' => $tenant->id, 'qtype_id' => $qtype->id, 'subject_id' => $subject->id, 'diff_id' => $difficulty->id, 'question' => $content]);
    }

    private function exam(Organization $tenant, string $name): int
    {
        return DB::table('exams')->insertGetId([
            'organization_id' => $tenant->id,
            'name' => $name,
            'passing_percentage' => 40,
            'duration' => 60,
            'attempt_count' => 0,
            'start_date' => now(),
            'end_date' => now()->addDay(),
            'mode' => 'Exam',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}

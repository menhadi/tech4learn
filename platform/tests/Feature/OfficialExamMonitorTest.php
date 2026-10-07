<?php

namespace Tests\Feature;

use App\Models\{Diff, Group, Language, OfficialExamDiscovery, OfficialExamSource, OfficialExamSourceRule, Organization, SaasPlan, SourceExamImport};
use App\Services\OfficialExamCreationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class OfficialExamMonitorTest extends TestCase
{
    use RefreshDatabase;

    public function test_creation_uses_existing_import_flow_and_never_claims_a_discovery_twice(): void
    {
        Storage::fake('local');
        config(['filesystems.exam_source_disk' => 'local']);
        $plan = SaasPlan::create(['name' => 'Monitor test', 'slug' => 'monitor-test', 'price' => 0, 'billing_cycle' => 'monthly', 'features' => [], 'limits' => [], 'status' => true]);
        $organization = Organization::create(['saas_plan_id' => $plan->id, 'name' => 'Exam org', 'slug' => 'exam-org', 'domain' => 'exam.test', 'status' => 'active']);
        $group = Group::create(['organization_id' => $organization->id, 'group_name' => 'GATE']);
        $language = Language::create(['organization_id' => $organization->id, 'name' => 'English', 'code' => 'en', 'is_enabled' => true]);
        Diff::create(['diff_level' => 'Normal', 'type' => 'normal']);
        $source = OfficialExamSource::create([
            'organization_id' => $organization->id, 'website_name' => 'GATE', 'name' => 'GATE papers',
            'source_url' => 'https://official.test/papers', 'driver' => 'static', 'automation_mode' => 'queue',
            'discovery_settings' => ['roles' => ['question' => ['enabled' => true]]],
            'exam_defaults' => ['group_id' => $group->id, 'language_id' => $language->id, 'duration' => 180, 'attempt_count' => 1, 'passing_percentage' => 0, 'marks' => 1, 'negative_marks' => 0],
        ]);
        $rule = OfficialExamSourceRule::create([
            'official_exam_source_id' => $source->id, 'name' => 'GATE PDF', 'priority' => 1,
            'language_mode' => 'single', 'language_id' => $language->id,
            'extractor_script' => 'PDF - GATE.py', 'ready_policy' => 'question_only',
            'exam_name_template' => '{detected_name}', 'enabled' => true,
        ]);
        $discovery = OfficialExamDiscovery::create([
            'organization_id' => $organization->id, 'official_exam_source_id' => $source->id,
            'official_exam_source_rule_id' => $rule->id, 'external_exam_key' => hash('sha256', 'gate-cs-2027'),
            'content_hash' => hash('sha256', 'paper'), 'status' => 'detected', 'exam_name' => 'GATE CS 2027',
            'source_config_version' => 1, 'rule_version' => 1, 'first_seen_at' => now(), 'last_seen_at' => now(),
        ]);
        $path = 'tmp/official-exam-monitor/org-'.$organization->id.'/source-'.$source->id.'/paper/question/gate.pdf';
        Storage::disk('local')->put($path, "%PDF-1.4\n%%EOF");
        $result = app(OfficialExamCreationService::class)->create($source, $rule, $discovery, [
            'question_local' => $path, 'question_label' => 'GATE CS 2027 Question Paper.pdf',
            'question_url' => 'https://official.test/gate-cs-2027.pdf',
        ]);

        self::assertSame('Inactive', $result['exam']->status);
        self::assertSame('queued', $result['import']->status);
        self::assertSame('PDF - GATE.py', data_get($result['import']->settings, 'extractor_script'));
        self::assertTrue($result['exam']->groups()->whereKey($group->id)->exists());
        self::assertDatabaseHas('official_exam_discoveries', ['id' => $discovery->id, 'status' => 'created', 'exam_id' => $result['exam']->id, 'source_exam_import_id' => $result['import']->id]);
        self::assertDatabaseCount('source_exam_imports', 1);

        $this->expectException(\RuntimeException::class);
        app(OfficialExamCreationService::class)->create($source, $rule, $discovery->fresh(), [
            'question_local' => $path, 'question_label' => 'GATE CS 2027 Question Paper.pdf',
        ]);
    }
}

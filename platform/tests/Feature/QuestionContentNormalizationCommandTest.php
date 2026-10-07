<?php

namespace Tests\Feature;

use App\Models\Diff;
use App\Models\Organization;
use App\Models\Qtype;
use App\Models\Question;
use App\Models\Subject;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class QuestionContentNormalizationCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_dry_run_apply_and_restore_are_tenant_scoped(): void
    {
        $tenantA = Organization::create(['name' => 'Tenant A', 'slug' => 'tenant-a', 'domain' => 'a.test', 'status' => 1]);
        $tenantB = Organization::create(['name' => 'Tenant B', 'slug' => 'tenant-b', 'domain' => 'b.test', 'status' => 1]);
        $qtype = Qtype::create(['question_type' => 'Multiple Choice', 'type' => 'M']);
        $difficulty = Diff::create(['diff_level' => 'Easy', 'type' => 'E']);
        $subjectA = Subject::create(['organization_id' => $tenantA->id, 'subject_name' => 'Physics']);
        $subjectB = Subject::create(['organization_id' => $tenantB->id, 'subject_name' => 'Physics']);
        $mathml = '<p>Radius <span class="mathjax-mathml"><math><msub><mi>R</mi><mi>e</mi></msub></math></span></p>';
        $questionA = Question::forceCreate([
            'organization_id' => $tenantA->id, 'qtype_id' => $qtype->id, 'subject_id' => $subjectA->id,
            'diff_id' => $difficulty->id, 'question' => $mathml,
        ]);
        $questionB = Question::forceCreate([
            'organization_id' => $tenantB->id, 'qtype_id' => $qtype->id, 'subject_id' => $subjectB->id,
            'diff_id' => $difficulty->id, 'question' => $mathml,
        ]);

        $this->assertSame(0, Artisan::call('questions:normalize-content', [
            '--organization' => $tenantA->id, '--question' => [$questionA->id],
        ]));
        $this->assertSame($mathml, $questionA->fresh()->question);
        $this->assertDatabaseCount('content_normalization_backups', 0);
        $this->removeReportFromOutput(Artisan::output());

        $this->assertSame(0, Artisan::call('questions:normalize-content', [
            '--organization' => $tenantA->id, '--question' => [$questionA->id], '--apply' => true,
        ]));
        $this->assertStringContainsString('\\(R_{e}\\)', $questionA->fresh()->question);
        $this->assertSame($mathml, $questionB->fresh()->question);
        $backup = DB::table('content_normalization_backups')->sole();
        $this->assertSame($tenantA->id, (int) $backup->organization_id);
        $this->removeReportFromOutput(Artisan::output());

        $this->assertSame(0, Artisan::call('questions:normalize-content', [
            '--organization' => $tenantA->id, '--restore' => $backup->run_id,
        ]));
        $this->assertSame($mathml, $questionA->fresh()->question);
        $this->assertSame('restored', DB::table('content_normalization_backups')->value('status'));
    }

    public function test_the_command_refuses_an_unscoped_run(): void
    {
        $this->assertSame(2, Artisan::call('questions:normalize-content'));
        $this->assertStringContainsString('No unscoped run is allowed', Artisan::output());
    }

    private function removeReportFromOutput(string $output): void
    {
        if (preg_match('/Report:\s+([^\r\n]+)/', $output, $match) && is_file(trim($match[1]))) {
            @unlink(trim($match[1]));
        }
    }
}

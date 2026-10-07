<?php

namespace Tests\Feature;

use App\Exports\QuestionsCsvExport;
use App\Http\Controllers\ImportExportController;
use App\Models\Diff;
use App\Models\Organization;
use App\Models\Qtype;
use App\Models\Question;
use App\Models\Subject;
use App\Support\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class QuestionExportAdvancedFilterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.url' => 'https://platform.test']);
        Cache::flush();
        Tenant::clear();
    }

    public function test_advanced_export_filters_remain_tenant_isolated(): void
    {
        $tenantA = $this->organization('Tenant A', 'tenant-a.test');
        $tenantB = $this->organization('Tenant B', 'tenant-b.test');
        $type = Qtype::create(['question_type' => 'Multiple Choice', 'type' => 'M']);
        $difficulty = Diff::create(['diff_level' => 'Medium', 'type' => 'M']);
        $subjectA = Subject::create(['organization_id' => $tenantA->id, 'subject_name' => 'Mathematics']);
        $subjectB = Subject::create(['organization_id' => $tenantB->id, 'subject_name' => 'Mathematics']);

        $matching = Question::forceCreate([
            'organization_id' => $tenantA->id,
            'qtype_id' => $type->id,
            'subject_id' => $subjectA->id,
            'diff_id' => $difficulty->id,
            'question' => 'Find the derivative of x squared',
            'marks' => 2,
            'status' => 'Yes',
            'ai_generated' => 'GEMINI',
            'explanation' => 'Use the power rule.',
        ]);
        Question::forceCreate([
            'organization_id' => $tenantA->id,
            'qtype_id' => $type->id,
            'subject_id' => $subjectA->id,
            'diff_id' => $difficulty->id,
            'question' => 'A manual algebra question',
            'marks' => 1,
            'status' => 'Yes',
            'ai_generated' => null,
        ]);
        Question::forceCreate([
            'organization_id' => $tenantB->id,
            'qtype_id' => $type->id,
            'subject_id' => $subjectB->id,
            'diff_id' => $difficulty->id,
            'question' => 'Find the derivative of x squared',
            'marks' => 2,
            'status' => 'Yes',
            'ai_generated' => 'GEMINI',
            'explanation' => 'Other tenant content.',
        ]);

        app()->instance('request', Request::create('https://tenant-a.test/question/export'));
        Tenant::clear();
        $method = new \ReflectionMethod(ImportExportController::class, 'questionExportQuery');
        $query = $method->invoke(new ImportExportController(), [
            'qtype_id' => $type->id,
            'diff_id' => $difficulty->id,
            'subject_id' => $subjectA->id,
            'ai_generated' => 'yes',
            'has_explanation' => 'yes',
            'search' => 'derivative',
            'marks_min' => 2,
            'marks_max' => 2,
        ]);

        $this->assertSame([$matching->id], $query->pluck('questions.id')->all());
    }

    public function test_manual_filter_accepts_null_or_empty_ai_provenance(): void
    {
        $tenant = $this->organization('Tenant A', 'tenant-a.test');
        $type = Qtype::create(['question_type' => 'Multiple Choice', 'type' => 'M']);
        $subject = Subject::create(['organization_id' => $tenant->id, 'subject_name' => 'Science']);
        $nullQuestion = Question::forceCreate(['organization_id' => $tenant->id, 'qtype_id' => $type->id, 'subject_id' => $subject->id, 'question' => 'Null provenance', 'ai_generated' => null]);
        $emptyQuestion = Question::forceCreate(['organization_id' => $tenant->id, 'qtype_id' => $type->id, 'subject_id' => $subject->id, 'question' => 'Empty provenance', 'ai_generated' => '']);
        Question::forceCreate(['organization_id' => $tenant->id, 'qtype_id' => $type->id, 'subject_id' => $subject->id, 'question' => 'Generated', 'ai_generated' => 'CHATGPT']);

        app()->instance('request', Request::create('https://tenant-a.test/question/export'));
        Tenant::clear();
        $method = new \ReflectionMethod(ImportExportController::class, 'questionExportQuery');
        $ids = $method->invoke(new ImportExportController(), ['ai_generated' => 'no'])
            ->orderBy('questions.id')->pluck('questions.id')->all();

        $this->assertSame([$nullQuestion->id, $emptyQuestion->id], $ids);
    }

    public function test_large_csv_export_streams_rows_and_excel_formula_values_safely(): void
    {
        $tenant = $this->organization('Tenant A', 'tenant-a.test');
        $type = Qtype::create(['question_type' => 'Multiple Choice', 'type' => 'M']);
        $subject = Subject::create(['organization_id' => $tenant->id, 'subject_name' => 'Security']);
        Question::forceCreate([
            'organization_id' => $tenant->id,
            'qtype_id' => $type->id,
            'subject_id' => $subject->id,
            'question_code' => 'EWQ-CSV-1',
            'question' => '=HYPERLINK("https://unsafe.test")',
        ]);

        $query = Question::with(['groups', 'subject', 'questionSection', 'topic', 'stopic', 'taxonomies.subject', 'taxonomies.topic', 'taxonomies.stopic', 'diff', 'qtype', 'language', 'passage', 'tags', 'exams.packages.category', 'exams.packages.subcategory', 'exams.category', 'exams.subcategory', 'exams.qualitySources'])
            ->where('organization_id', $tenant->id);
        $response = (new QuestionsCsvExport($query))->download('questions.csv');

        ob_start();
        ($response->getCallback())();
        $csv = ob_get_clean();

        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertStringContainsString('question_code', $csv);
        $this->assertStringContainsString('EWQ-CSV-1', $csv);
        $this->assertStringContainsString("'=HYPERLINK", $csv);
    }
    private function organization(string $name, string $domain): Organization
    {
        return Organization::create([
            'name' => $name,
            'slug' => str($name)->slug()->toString(),
            'domain' => $domain,
            'status' => 'active',
        ]);
    }
}
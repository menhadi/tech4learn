<?php

namespace Tests\Feature;

use App\Models\{Category, Exam, ExamQualitySource, Group, Package, Question, SourceExamImport};
use App\Services\ExamPdfPublicationService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema, Storage};
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ExamPdfPublicationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Dedicated, empty database: never run migrations or mutate the workspace DB.
        config(['database.default' => 'pdf_test', 'database.connections.pdf_test' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);
        DB::purge('pdf_test');
        foreach (['exams', 'groups', 'category', 'packages', 'questions', 'source_exam_imports', 'exam_quality_sources'] as $name) {
            Schema::create($name, function (Blueprint $table) use ($name) {
                $table->id(); $table->unsignedBigInteger('organization_id')->default(1); $table->timestamps();
                if ($name === 'groups') { $table->text('group_name')->nullable(); return; }
                $table->string('status')->default($name === 'category' ? '1' : 'Inactive');
                if ($name === 'category') { $table->string('title'); $table->string('slug'); $table->unsignedBigInteger('parent_id')->nullable(); return; }
                $table->string('name')->nullable();
                if ($name === 'questions') $table->string('question_code')->nullable();
                if (in_array($name, ['exams', 'packages'])) {
                    $table->string('slug')->nullable(); $table->unsignedBigInteger('category_level_1')->nullable(); $table->unsignedBigInteger('category_level_2')->nullable();
                }
                if (in_array($name, ['source_exam_imports', 'exam_quality_sources'])) $table->unsignedBigInteger('exam_id');
                if ($name === 'source_exam_imports') $table->text('settings')->nullable();
                if ($name === 'exam_quality_sources') {
                    $table->string('role'); $table->string('kind')->default('file'); $table->boolean('is_active')->default(true);
                    $table->string('storage_disk')->nullable(); $table->string('file_path')->nullable();
                }
            });
        }
        foreach (['exam_groups' => ['exam_id', 'group_id'], 'exam_packages' => ['exam_id', 'package_id'], 'category_groups' => ['category_id', 'group_id'], 'package_groups' => ['package_id', 'group_id'], 'exam_questions' => ['exam_id', 'question_id']] as $name => $columns) {
            Schema::create($name, function (Blueprint $table) use ($columns) {
                foreach ($columns as $column) $table->unsignedBigInteger($column);
                $table->integer('display_order')->nullable(); $table->unsignedBigInteger('exam_section_id')->nullable(); $table->timestamps();
            });
        }
        Storage::fake('local');
    }

    private function paper(int $tenant = 1): Exam
    {
        $exam = Exam::create(['organization_id' => $tenant, 'name' => 'Official paper', 'status' => 'Inactive']);
        $group = Group::create(['organization_id' => $tenant, 'group_name' => 'Entrance']);
        $exam->groups()->attach($group);
        Storage::disk('local')->put("papers/{$exam->id}.pdf", "%PDF-1.4\n%%EOF");
        ExamQualitySource::create(['organization_id' => $tenant, 'exam_id' => $exam->id, 'role' => 'questions', 'storage_disk' => 'local', 'file_path' => "papers/{$exam->id}.pdf", 'is_active' => true]);
        return $exam;
    }

    public function test_bulk_publish_keeps_imports_draft_and_updates_their_classification(): void
    {
        $one = $this->paper(); $two = $this->paper();
        $import = SourceExamImport::create(['exam_id' => $one->id, 'organization_id' => 1, 'status' => 'draft', 'settings' => ['extractor_script' => 'paper.py']]);
        $count = app(ExamPdfPublicationService::class)->publish(1, [$one->id, $two->id], ['category_mode' => 'create', 'new_category' => 'Engineering']);
        self::assertSame(2, $count);
        self::assertSame('Active', $one->fresh()->status);
        self::assertNotEmpty($one->fresh()->slug);
        self::assertFalse($one->fresh()->canAttemptOnline());
        self::assertSame($one->fresh()->category_level_1, $two->fresh()->category_level_1);
        self::assertSame('draft', $import->fresh()->status);
        self::assertSame('paper.py', $import->fresh()->settings['extractor_script']);
        self::assertSame($one->fresh()->category_level_1, $import->fresh()->settings['category_id']);
        self::assertSame(2, Category::first()->groups()->count());
    }

    public function test_active_questions_enable_online_attempts_without_republishing_the_paper(): void
    {
        $exam = $this->paper();
        app(ExamPdfPublicationService::class)->publish(1, [$exam->id]);
        $question = new Question; $question->forceFill(['status' => 'inactive'])->save();
        $exam->questions()->attach($question);
        self::assertFalse($exam->fresh()->canAttemptOnline());
        $question->forceFill(['status' => 'active'])->save();
        self::assertTrue($exam->fresh()->canAttemptOnline());
        self::assertNotNull(app(ExamPdfPublicationService::class)->source($exam));
    }

    public function test_missing_pdf_rolls_back_the_entire_selection(): void
    {
        $one = $this->paper(); $two = $this->paper();
        Storage::disk('local')->delete("papers/{$two->id}.pdf");
        try {
            app(ExamPdfPublicationService::class)->publish(1, [$one->id, $two->id], ['category_mode' => 'create', 'new_category' => 'New category']);
            self::fail('Missing source was accepted.');
        } catch (ValidationException $e) {
            self::assertSame('Inactive', $one->fresh()->status);
            self::assertSame(0, Category::count());
        }
    }

    public function test_cross_tenant_selection_is_rejected(): void
    {
        $exam = $this->paper(2);
        $this->expectException(ValidationException::class);
        app(ExamPdfPublicationService::class)->publish(1, [$exam->id]);
    }

    public function test_package_classification_cannot_be_overwritten_or_detached_by_bulk_publish(): void
    {
        $exam = $this->paper();
        $package = Package::create(['organization_id' => 1, 'name' => 'Course']);
        $exam->packages()->attach($package);
        try {
            app(ExamPdfPublicationService::class)->publish(1, [$exam->id], ['category_mode' => 'create', 'new_category' => 'Other']);
            self::fail('Package classification was overwritten.');
        } catch (ValidationException $e) {
            self::assertSame('Inactive', $exam->fresh()->status);
            self::assertSame(1, $exam->packages()->count());
            self::assertSame(0, Category::count());
        }
    }

    public function test_fallback_uses_a_saved_active_question_or_combined_pdf_only(): void
    {
        $exam = $this->paper();
        $source = $exam->qualitySources()->first();
        $source->update(['role' => 'answers']);
        self::assertNull(app(ExamPdfPublicationService::class)->source($exam));
        $source->update(['role' => 'combined']);
        self::assertSame($source->id, app(ExamPdfPublicationService::class)->source($exam)->id);
        $source->update(['is_active' => false]);
        self::assertNull(app(ExamPdfPublicationService::class)->source($exam));
        Storage::disk('local')->put('not-a-paper.docx', 'Document');
        $source->update(['is_active' => true, 'file_path' => 'not-a-paper.docx']);
        self::assertNull(app(ExamPdfPublicationService::class)->source($exam));
    }

    public function test_public_download_intent_and_signed_download_serve_the_official_pdf(): void
    {
        $exam = $this->paper();
        app(ExamPdfPublicationService::class)->publish(1, [$exam->id]);
        $controller = $this->downloadController();
        $intent = $controller->downloadIntent(\Illuminate\Http\Request::create('https://pdf.test/intent', 'POST'), $exam->id);
        $payload = $intent->getData(true);
        self::assertTrue($payload['original_source']);
        self::assertSame('original', $payload['language']);
        $request = \Illuminate\Http\Request::create($payload['download_url']);
        $request->setLaravelSession($this->app['session.store']);
        $download = $controller->download($request, $exam->id);
        self::assertSame('OFFICIAL-SOURCE', $download->headers->get('X-Exam-PDF-Cache'));
        ob_start();
        $download->sendContent();
        self::assertSame("%PDF-1.4\n%%EOF", ob_get_clean());
    }

    public function test_unpublished_paper_cannot_get_a_public_download_link(): void
    {
        $exam = $this->paper();
        $controller = $this->downloadController();
        try {
            $controller->downloadIntent(\Illuminate\Http\Request::create('https://pdf.test/intent', 'POST'), $exam->id);
            self::fail('Inactive paper received a download link.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            self::assertSame(404, $e->getStatusCode());
        }
    }

    public function test_missing_package_parameter_cannot_bypass_package_download_visibility(): void
    {
        $exam = $this->paper();
        $exam->update(['status' => 'Active']);
        $package = Package::create(['organization_id' => 1, 'name' => 'Course']);
        $exam->packages()->attach($package);
        $controller = $this->downloadController();
        try {
            $controller->downloadIntent(\Illuminate\Http\Request::create('https://pdf.test/intent', 'POST'), $exam->id);
            self::fail('Package visibility was bypassed.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            self::assertSame(404, $e->getStatusCode());
        }
    }

    public function test_standalone_browse_includes_published_source_papers_and_excludes_inactive_or_packaged_exams(): void
    {
        $one = $this->paper(); $two = $this->paper();
        self::assertSame(0, Exam::standalonePdfPapers()->count());
        app(ExamPdfPublicationService::class)->publish(1, [$one->id]);
        self::assertSame([$one->id], Exam::standalonePdfPapers()->pluck('id')->all());
        $package = Package::create(['organization_id' => 1, 'name' => 'Course']);
        $one->packages()->attach($package);
        self::assertSame(0, Exam::standalonePdfPapers()->count());
    }

    public function test_approved_generated_pdf_is_preferred_over_official_source(): void
    {
        $exam = $this->paper();
        $exam->update(['status' => 'Active']);
        $controller = $this->downloadController();
        $build = new \App\Models\ExamPdfBuild;
        $build->forceFill(['current_path' => Storage::disk('local')->path("papers/{$exam->id}.pdf")]);
        $lifecycle = \Mockery::mock(\App\Services\ExamDocumentLifecycleService::class);
        $lifecycle->shouldReceive('ready')->andReturn($build);
        $this->app->instance(\App\Services\ExamDocumentLifecycleService::class, $lifecycle);
        $languageService = \Mockery::mock(\App\Services\ExamLanguageService::class);
        $languageService->shouldReceive('display')->andReturn(['name' => $exam->name]);
        $this->app->instance(\App\Services\ExamLanguageService::class, $languageService);
        $intent = $controller->downloadIntent(\Illuminate\Http\Request::create('https://pdf.test/intent', 'POST'), $exam->id)->getData(true);
        self::assertFalse($intent['original_source']);
        $request = \Illuminate\Http\Request::create($intent['download_url']);
        $request->setLaravelSession($this->app['session.store']);
        $response = $controller->download($request, $exam->id);
        self::assertSame('APPROVED', $response->headers->get('X-Exam-PDF-Cache'));
    }

    private function downloadController(): \App\Http\Controllers\ExamPrintController
    {
        $organization = new \App\Models\Organization;
        $organization->forceFill(['id' => 1]);
        \Illuminate\Support\Facades\Cache::put('tenant.organization.host.'.hash('sha256', 'pdf.test'), $organization, 300);
        \Illuminate\Support\Facades\URL::forceRootUrl('https://pdf.test');
        $pdfCache = \Mockery::mock(\App\Services\ExamPdfCacheService::class);
        $pdfCache->shouldReceive('resolveLanguage')->andReturn(null);
        $this->app->instance(\App\Services\ExamPdfCacheService::class, $pdfCache);
        $lifecycle = \Mockery::mock(\App\Services\ExamDocumentLifecycleService::class);
        $lifecycle->shouldReceive('ready')->andReturn(null);
        $this->app->instance(\App\Services\ExamDocumentLifecycleService::class, $lifecycle);
        return new \App\Http\Controllers\ExamPrintController(\Mockery::mock(\App\Services\StudentPostAuthService::class));
    }
}

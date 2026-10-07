<?php

namespace Tests\Feature;

use App\Services\ExamWorkbookService;
use Illuminate\Support\Facades\Artisan;
use Mockery;
use Tests\TestCase;

class ImportExamWorkbookCommandTest extends TestCase
{
    private string $workbook;

    protected function setUp(): void
    {
        parent::setUp();
        $temporary = tempnam(sys_get_temp_dir(), 'exam-workbook-');
        unlink($temporary);
        $this->workbook = $temporary.'.xlsx';
        file_put_contents($this->workbook, 'test workbook placeholder');
    }

    protected function tearDown(): void
    {
        if (isset($this->workbook) && is_file($this->workbook)) {
            unlink($this->workbook);
        }
        parent::tearDown();
    }

    public function test_it_previews_and_applies_one_workbook_from_the_cli(): void
    {
        $organizationId = 17;
        $service = Mockery::mock(ExamWorkbookService::class);
        $service->shouldReceive('detectOrganizationId')->once()->with($this->workbook)->andReturn($organizationId);
        $service->shouldReceive('preview')->once()->with($this->workbook, $organizationId)->andReturn([
            'total' => 2, 'valid' => 2, 'updates' => 2, 'creates' => 0, 'errors' => [], 'warnings' => [],
        ]);
        $service->shouldReceive('apply')->once()->andReturnUsing(function ($path, $actualOrganizationId, $progress) use ($organizationId) {
            $this->assertSame($this->workbook, $path);
            $this->assertSame($organizationId, $actualOrganizationId);
            $progress(2, []);
            $progress(3, []);

            return ['created' => 0, 'updated' => 2, 'failed' => 0, 'errors' => []];
        });
        $this->app->instance(ExamWorkbookService::class, $service);

        $exit = Artisan::call('exams:import-workbook', [
            'workbook' => $this->workbook,
            '--force' => true,
        ]);

        $this->assertSame(0, $exit);
        $this->assertStringContainsString('Import completed successfully', Artisan::output());
    }

    public function test_dry_run_does_not_apply_the_workbook(): void
    {
        $organizationId = 17;
        $service = Mockery::mock(ExamWorkbookService::class);
        $service->shouldReceive('detectOrganizationId')->never();
        $service->shouldReceive('preview')->once()->with($this->workbook, $organizationId)->andReturn([
            'total' => 2, 'valid' => 2, 'updates' => 2, 'creates' => 0, 'errors' => [], 'warnings' => [],
        ]);
        $service->shouldNotReceive('apply');
        $this->app->instance(ExamWorkbookService::class, $service);

        $exit = Artisan::call('exams:import-workbook', [
            'workbook' => $this->workbook,
            '--organization' => $organizationId,
            '--dry-run' => true,
        ]);

        $this->assertSame(0, $exit);
        $this->assertStringContainsString('No data was changed', Artisan::output());
    }

    public function test_it_rejects_an_invalid_organization_option(): void
    {
        $exit = Artisan::call('exams:import-workbook', [
            'workbook' => $this->workbook,
            '--organization' => 'invalid',
        ]);

        $this->assertSame(2, $exit);
        $this->assertStringContainsString('must be a positive integer', Artisan::output());
    }
}

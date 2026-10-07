<?php

namespace App\Console\Commands;

use App\Services\ExamWorkbookService;
use Illuminate\Console\Command;

class ImportExamWorkbook extends Command
{
    protected $signature = 'exams:import-workbook
        {workbook : Absolute or current-directory-relative path to an XLSX, XLS, or CSV workbook}
        {--organization= : Organization ID; automatically detected for an UPDATE workbook when omitted}
        {--dry-run : Validate and preview without changing any data}
        {--force : Apply without asking for interactive confirmation}';

    protected $description = 'Validate and import one exam workbook outside the web-request timeout';

    public function handle(ExamWorkbookService $service): int
    {
        $workbook = realpath((string) $this->argument('workbook'));
        if (! $workbook || ! is_file($workbook) || ! is_readable($workbook)) {
            $this->error('The workbook does not exist or is not readable.');

            return self::INVALID;
        }

        if (! in_array(strtolower(pathinfo($workbook, PATHINFO_EXTENSION)), ['xlsx', 'xls', 'csv'], true)) {
            $this->error('The workbook must be an XLSX, XLS, or CSV file.');

            return self::INVALID;
        }

        $organizationOption = $this->option('organization');
        $organizationId = $organizationOption === null
            ? null
            : filter_var($organizationOption, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($organizationOption !== null && ! $organizationId) {
            $this->error('When provided, --organization must be a positive integer.');

            return self::INVALID;
        }

        @set_time_limit(0);
        if (! $organizationId) {
            $this->info('Detecting organization from workbook exam IDs...');
            try {
                $organizationId = $service->detectOrganizationId($workbook);
            } catch (\Throwable $exception) {
                $this->error('The workbook could not be read: '.$exception->getMessage());

                return self::FAILURE;
            }
            if (! $organizationId) {
                $this->error('The organization could not be detected. Use --organization=ID for CREATE-only or mixed-tenant workbooks.');

                return self::INVALID;
            }
        }

        $this->line("Workbook: {$workbook}");
        $this->line("Organization ID: {$organizationId}");
        $this->newLine();
        $this->info('Validating workbook...');

        try {
            $preview = $service->preview($workbook, $organizationId);
        } catch (\Throwable $exception) {
            $this->error('The workbook could not be read: '.$exception->getMessage());

            return self::FAILURE;
        }

        $this->table(
            ['Rows', 'Valid', 'Updates', 'Creates', 'Errors'],
            [[$preview['total'], $preview['valid'], $preview['updates'], $preview['creates'], count($preview['errors'])]]
        );

        if ($preview['errors']) {
            foreach (array_slice($preview['errors'], 0, 100) as $error) {
                $this->error("Row {$error['row']}: {$error['message']}");
            }
            if (count($preview['errors']) > 100) {
                $this->warn((count($preview['errors']) - 100).' additional errors were omitted.');
            }
            $this->error('Nothing was imported because validation failed.');

            return self::FAILURE;
        }

        if ($this->option('dry-run')) {
            $this->info('Dry run complete. No data was changed.');

            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm("Apply {$preview['valid']} changes?")) {
            $this->warn('Import cancelled. No data was changed.');

            return self::SUCCESS;
        }

        $this->info('Applying workbook...');
        $progress = $this->output->createProgressBar((int) $preview['valid']);
        $progress->start();

        try {
            $summary = $service->apply(
                $workbook,
                $organizationId,
                static function () use ($progress): void {
                    $progress->advance();
                }
            );
        } catch (\Throwable $exception) {
            $progress->finish();
            $this->newLine(2);
            $this->error('Import stopped unexpectedly: '.$exception->getMessage());
            $this->warn('Completed UPDATE/REPLACE rows can be safely retried with the same workbook.');

            return self::FAILURE;
        }

        $progress->finish();
        $this->newLine(2);
        $this->table(['Created', 'Updated', 'Failed'], [[$summary['created'], $summary['updated'], $summary['failed']]]);

        foreach (array_slice($summary['errors'], 0, 100) as $error) {
            $this->error($error);
        }

        if ($summary['failed']) {
            $this->warn('Import completed with row errors. Correct them and rerun the same workbook.');

            return self::FAILURE;
        }

        $this->info('Import completed successfully.');

        return self::SUCCESS;
    }
}

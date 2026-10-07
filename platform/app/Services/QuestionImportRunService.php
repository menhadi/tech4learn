<?php

namespace App\Services;

use App\Imports\QuestionsImport;
use App\Models\QuestionImportRun;
use App\Models\Organization;
use App\Models\User;
use App\Support\Tenant;
use App\Http\Middleware\CheckPageRights;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

class QuestionImportRunService
{
    private const ROWS_PER_BATCH = 100;

    public function process(QuestionImportRun $run): void
    {
        // A CLI worker must not inherit the platform host's tenant or privileges.
        if (!app()->runningInConsole()) { throw new \RuntimeException('Question imports require the CLI worker.'); }
        $originalRequest = request();
        $originalActor = Auth::guard('web')->user();
        $originalGuard = Auth::getDefaultDriver();
        try {
            $organization = Organization::whereKey($run->organization_id)->where('status', 'active')->firstOrFail();
            $actor = User::findOrFail($run->created_by);
            $host = $organization->domain ?: $organization->subdomain.'.'.parse_url(config('app.url'), PHP_URL_HOST);
            $scoped = Request::create('https://'.$host.'/question/import/large', 'POST');
            $scoped->setUserResolver(fn () => $actor);
            $scoped->setRouteResolver(fn () => app('router')->getRoutes()->getByName('questions.large-import.initialize'));
            app()->instance('request', $scoped);
            Auth::shouldUse('web');
            Auth::guard('web')->setUser($actor);
            Tenant::clear();
            abort_unless((int) Tenant::id() === (int) $run->organization_id, 403);
            (new CheckPageRights)->handle($scoped, fn () => null);
            $this->processScoped($run);
        } catch (\Throwable $error) {
            $this->fail($run, 'Import could not proceed. Verify the submitting account, organisation and import permissions.');
        } finally {
            app()->instance('request', $originalRequest);
            Auth::shouldUse($originalGuard);
            if ($originalActor) { Auth::guard('web')->setUser($originalActor); }
            else { Auth::guard('web')->forgetUser(); }
            Tenant::clear();
        }
    }

    private function processScoped(QuestionImportRun $run): void
    {
        $run->refresh();
        $source = Storage::disk('local')->path((string) $run->stored_path);
        if (! is_file($source)) {
            $this->fail($run, 'The uploaded CSV is no longer available on the server.');
            return;
        }

        try {
            $run->update([
                'status' => 'processing',
                'processing_started_at' => $run->processing_started_at ?: now(),
                'failure_message' => null,
            ]);
            if (! $run->total_rows) $run->update(['total_rows' => $this->countRows($source)]);
            $this->importRows($run->fresh(), $source);
            $run->refresh()->update([
                'status' => 'completed',
                'completed_at' => now(),
                'failure_message' => $run->failed_rows ? "Completed with {$run->failed_rows} row error(s)." : null,
            ]);
            Storage::disk('local')->delete((string) $run->stored_path);
        } catch (\Throwable $e) {
            Log::error('Question background import failed.', ['run_id' => $run->id, 'exception' => $e]);
            $this->fail($run, $e->getMessage());
        }
    }

    private function importRows(QuestionImportRun $run, string $source): void
    {
        $handle = fopen($source, 'rb');
        if (! $handle) throw new \RuntimeException('The uploaded CSV could not be opened.');
        $header = fgetcsv($handle);
        if (! is_array($header) || $header === []) throw new \RuntimeException('The CSV has no header row.');
        $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $header[0]);

        $skipped = 0;
        while ($skipped < $run->processed_rows && fgetcsv($handle) !== false) $skipped++;
        $sourceRow = $skipped + 2;

        try {
            while (true) {
                $batch = [];
                while (count($batch) < self::ROWS_PER_BATCH && ($row = fgetcsv($handle)) !== false) {
                    $batch[] = ['number' => $sourceRow++, 'values' => $row];
                }
                if ($batch === []) break;
                $this->processBatch($run, $header, $batch);
                $run->refresh();
            }
        } finally {
            fclose($handle);
        }
    }

    private function processBatch(QuestionImportRun $run, array $header, array $batch): void
    {
        Tenant::assertAccess(Tenant::current(), true);
        (new CheckPageRights)->handle(request(), fn () => null);
        $path = $this->writeCsv($run, $header, array_column($batch, 'values'), 'batch.csv');
        try {
            DB::transaction(function () use ($run, $path, $batch): void {
                $import = $this->newImport($run);
                Excel::import($import, $path);
                $this->recordSuccess($run, $import, count($batch));
            });
            return;
        } catch (\Throwable $batchError) {
            Log::warning('Question import batch failed; retrying row by row.', ['run_id' => $run->id, 'row' => $batch[0]['number'], 'exception' => $batchError]);
        } finally {
            @unlink($path);
        }

        foreach ($batch as $item) {
            $rowPath = $this->writeCsv($run, $header, [$item['values']], 'row.csv');
            try {
                DB::transaction(function () use ($run, $rowPath): void {
                    $import = $this->newImport($run);
                    Excel::import($import, $rowPath);
                    $this->recordSuccess($run, $import, 1);
                });
            } catch (\Throwable $e) {
                DB::transaction(function () use ($run, $header, $item, $e): void {
                    $this->appendError($run, $header, $item['values'], $item['number'], $e->getMessage());
                    QuestionImportRun::whereKey($run->id)->increment('processed_rows');
                    QuestionImportRun::whereKey($run->id)->increment('failed_rows');
                });
            } finally {
                @unlink($rowPath);
            }
        }
    }

    private function recordSuccess(QuestionImportRun $run, QuestionsImport $import, int $processed): void
    {
        QuestionImportRun::whereKey($run->id)->update([
            'processed_rows' => DB::raw('processed_rows + '.(int) $processed),
            'imported_rows' => DB::raw('imported_rows + '.(int) $import->importedCount),
            'updated_rows' => DB::raw('updated_rows + '.(int) $import->updatedCount),
            'duplicate_rows' => DB::raw('duplicate_rows + '.(int) $import->duplicateCount),
            'created_records' => DB::raw('created_records + '.(int) $import->createdRecordCount),
            'updated_at' => now(),
        ]);
    }

    private function newImport(QuestionImportRun $run): QuestionsImport
    {
        $options = $run->options ?: [];
        return new QuestionsImport(null, null, null, [], $run->organization_id, null, null, $options['import_mode'] ?? 'create');
    }

    private function writeCsv(QuestionImportRun $run, array $header, array $rows, string $suffix): string
    {
        $directory = storage_path('app/question-imports/'.$run->organization_id.'/'.$run->upload_id.'/work');
        if (! is_dir($directory)) mkdir($directory, 0750, true);
        $path = $directory.'/'.bin2hex(random_bytes(8)).'-'.$suffix;
        $handle = fopen($path, 'wb');
        fputcsv($handle, $header);
        foreach ($rows as $row) fputcsv($handle, $row);
        fclose($handle);
        return $path;
    }

    private function appendError(QuestionImportRun $run, array $header, array $row, int $sourceRow, string $message): void
    {
        $directory = 'question-imports/'.$run->organization_id.'/'.$run->upload_id;
        $relative = $directory.'/errors.csv';
        $path = Storage::disk('local')->path($relative);
        $new = ! is_file($path);
        $handle = fopen($path, 'ab');
        if ($new) fputcsv($handle, array_merge(['source_row', 'error'], $header));
        fputcsv($handle, array_merge([$sourceRow, mb_substr($message, 0, 1000)], $row));
        fclose($handle);
        if ($run->error_report_path !== $relative) QuestionImportRun::whereKey($run->id)->update(['error_report_path' => $relative]);
    }

    private function countRows(string $source): int
    {
        $handle = fopen($source, 'rb');
        if (! $handle) throw new \RuntimeException('The uploaded CSV could not be opened.');
        fgetcsv($handle);
        $count = 0;
        while (fgetcsv($handle) !== false) $count++;
        fclose($handle);
        return $count;
    }

    private function fail(QuestionImportRun $run, string $message): void
    {
        QuestionImportRun::whereKey($run->id)->update([
            'status' => 'failed',
            'failure_message' => mb_substr($message, 0, 4000),
            'completed_at' => now(),
        ]);
    }
}

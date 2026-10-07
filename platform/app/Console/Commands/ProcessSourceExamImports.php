<?php

namespace App\Console\Commands;

use App\Models\SourceExamImport;
use App\Services\SourceExamImportService;
use App\Services\SourceExamImportProcessLauncher;
use App\Support\ResourceSlotLimiter;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class ProcessSourceExamImports extends Command
{
    protected $signature = 'source-exams:process {--limit=1} {--import=} {--batch=} {--worker=}';
    protected $description = 'Extract queued source documents into reviewable exam question drafts';

    public function handle(SourceExamImportService $service): int
    {
        $this->recoverStaleImports();
        $imports = $this->claimImports(max(1, min(10, (int) $this->option('limit'))));
        foreach ($imports as $import) {
            try {
                app(ResourceSlotLimiter::class)->run('source-extraction', config('paper_processing.workers', 4), static function () use ($service, $import): void {
                    $service->process($import->fresh());
                }, 1800, 1800);
                $worker = $this->option('worker') ? " by worker {$this->option('worker')}" : '';
                $this->info("Processed import {$import->id}{$worker}");
            }
            catch (\Throwable $e) {
                if ($e instanceof \Illuminate\Contracts\Cache\LockTimeoutException) {
                    SourceExamImport::whereKey($import->id)->where('status', 'starting')->update(['status' => 'queued']);
                }
                $this->error("Import {$import->id}: {$e->getMessage()}");
            }
        }

        $batchToken = trim((string) $this->option('batch'));
        if ($batchToken !== '' && SourceExamImport::query()
            ->where('status', 'queued')
            ->where('settings->processing_batch_token', $batchToken)
            ->exists()) {
            app(SourceExamImportProcessLauncher::class)->startBatchWorker($batchToken);
        }

        return self::SUCCESS;
    }

    private function claimImports(int $limit): Collection
    {
        $query = SourceExamImport::query()->where('status', 'queued')->oldest();
        if ($this->option('import')) $query->whereKey($this->option('import'));
        if ($this->option('batch')) {
            $query->where('settings->processing_batch_token', (string) $this->option('batch'));
        }

        $claimed = collect();
        foreach ($query->limit(max(20, $limit * 8))->get() as $candidate) {
            $updated = SourceExamImport::whereKey($candidate->id)
                ->where('status', 'queued')
                ->update(['status' => 'starting']);
            if (! $updated) continue;

            $candidate->status = 'starting';
            $claimed->push($candidate);
            if ($claimed->count() >= $limit) break;
        }

        return $claimed;
    }

    private function recoverStaleImports(): void
    {
        SourceExamImport::query()
            ->whereIn('status', ['starting', 'processing'])
            ->where('updated_at', '<', now()->subHours(2))
            ->update([
                'status' => 'queued',
                'processing_started_at' => null,
                'processing_completed_at' => null,
                'failure_message' => 'Previous extraction worker stopped before completion; extraction was queued again automatically.',
            ]);
    }
}

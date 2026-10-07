<?php

namespace App\Console\Commands;

use App\Models\ExamQualityAudit;
use App\Services\ExamQualityAuditRunner;
use App\Support\ResourceSlotLimiter;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class ProcessExamQualityAudits extends Command
{
    protected $signature = 'exam-quality:process {--limit=1} {--audit=} {--batch=} {--worker=}';
    protected $description = 'Process queued exam quality audits';

    public function handle(ExamQualityAuditRunner $runner): int
    {
        $this->recoverStaleAudits();
        $audits = $this->claimAudits(max(1, min(10, (int) $this->option('limit'))));

        foreach ($audits as $audit) {
            try {
                app(ResourceSlotLimiter::class)->run('paper-processing', config('paper_processing.max_parallel_papers', 4), static function () use ($runner, $audit): void {
                    $runner->run($audit->fresh());
                }, 1800, 1800);
                $worker = $this->option('worker') ? " by worker {$this->option('worker')}" : '';
                $status = (string) ExamQualityAudit::whereKey($audit->id)->value('status');
                $this->info("Audit {$audit->id} {$status}{$worker}.");
            } catch (\Throwable $e) {
                if ($e instanceof \Illuminate\Contracts\Cache\LockTimeoutException) {
                    ExamQualityAudit::whereKey($audit->id)->where('status', 'starting')->update(['status' => 'queued']);
                }
                report($e);
                $this->error("Audit {$audit->id} failed: {$e->getMessage()}");
            }
        }

        $batchToken = trim((string) $this->option('batch'));
        if ($batchToken !== '' && ExamQualityAudit::query()
            ->where('status', 'queued')
            ->where('options->batch_token', $batchToken)
            ->exists()) {
            app(\App\Services\ExamQualityAuditProcessLauncher::class)->startBatchWorker($batchToken);
        }

        return self::SUCCESS;
    }

    private function claimAudits(int $limit): Collection
    {
        $query = ExamQualityAudit::query()->where('status', 'queued')->oldest();
        if ($this->option('audit')) $query->whereKey($this->option('audit'));

        if ($this->option('batch')) {
            $query->where('options->batch_token', (string) $this->option('batch'));
        }

        $claimed = collect();
        // Multiple scheduled workers may read the same candidates. Trying more
        // candidates lets losing workers atomically claim the next queued audit.
        foreach ($query->limit(max(20, $limit * 8))->get() as $candidate) {
            $updated = ExamQualityAudit::whereKey($candidate->id)
                ->where('status', 'queued')
                ->update(['status' => 'starting']);
            if (! $updated) continue;

            $candidate->status = 'starting';
            $claimed->push($candidate);
            if ($claimed->count() >= $limit) break;
        }

        return $claimed;
    }

    private function recoverStaleAudits(): void
    {
        $staleBefore = now()->subHours(2);
        ExamQualityAudit::query()
            ->whereIn('status', ['starting', 'running'])
            ->where('updated_at', '<', $staleBefore)
            ->update([
                'status' => 'queued',
                'started_at' => null,
                'completed_at' => null,
                'failure_message' => 'Previous audit worker stopped before completion; audit was queued again automatically.',
            ]);
    }
}

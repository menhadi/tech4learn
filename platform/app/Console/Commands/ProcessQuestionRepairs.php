<?php

namespace App\Console\Commands;

use App\Models\QuestionRepairDraft;
use App\Services\QuestionRepairService;
use App\Support\ResourceSlotLimiter;
use Illuminate\Console\Command;

class ProcessQuestionRepairs extends Command
{
    protected $signature = 'exam-quality:process-repairs {--limit=5} {--draft=} {--worker=}';
    protected $description = 'Prepare queued question repair drafts from evidence saved by the original audit';

    public function handle(QuestionRepairService $service): int
    {
        $limit = max(1, min(20, (int) $this->option('limit')));
        $query = QuestionRepairDraft::where('status', 'queued')->oldest();
        if ($this->option('draft')) $query->whereKey($this->option('draft'));
        $drafts = collect();
        foreach ($query->limit(max(20, $limit * 8))->get() as $candidate) {
            $claimed = QuestionRepairDraft::whereKey($candidate->id)
                ->where('status', 'queued')
                ->update(['status' => 'starting']);
            if (! $claimed) continue;

            $candidate->status = 'starting';
            $drafts->push($candidate);
            if ($drafts->count() >= $limit) break;
        }

        foreach ($drafts->groupBy(fn ($draft) => $draft->organization_id.':'.$draft->exam_id.':'.$draft->audit_id) as $group) {
            foreach ($group->chunk(5) as $batch) {
                try {
                    app(ResourceSlotLimiter::class)->run(
                        'paper-processing',
                        config('paper_processing.max_parallel_papers', 4),
                        static function () use ($service, $batch): void {
                            $service->processBatch($batch->map->fresh());
                        },
                        1800,
                        1800
                    );
                    $worker = $this->option('worker') ? " on worker {$this->option('worker')}" : '';
                    $this->info('Repair drafts '.$batch->pluck('id')->implode(', ')." processed in one paper batch{$worker}.");
                } catch (\Throwable $e) {
                    report($e);
                    if ($e instanceof \Illuminate\Contracts\Cache\LockTimeoutException) {
                        QuestionRepairDraft::whereIn('id', $batch->pluck('id'))->where('status', 'starting')
                            ->update(['status' => 'queued']);
                    } else {
                        QuestionRepairDraft::whereIn('id', $batch->pluck('id'))->whereIn('status', ['starting','processing'])
                            ->update(['status' => 'failed', 'failure_message' => mb_substr($e->getMessage(), 0, 6000)]);
                    }
                    $this->error('Repair batch failed: '.$e->getMessage());
                }
            }
        }
        return self::SUCCESS;
    }
}
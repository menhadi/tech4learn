<?php

namespace App\Console\Commands;

use App\Models\AiAnswerRun;
use App\Services\AiAnswerService;
use Illuminate\Console\Command;

class ProcessAiAnswers extends Command
{
    protected $signature = 'ai-answers:process {--limit=1} {--run=} {--batch=} {--worker=}';
    protected $description = 'Process queued AI answer and explanation jobs';

    public function handle(AiAnswerService $service): int
    {
        AiAnswerRun::whereIn('status', ['starting','running'])->where('updated_at', '<', now()->subHours(2))
            ->update(['status' => 'queued', 'started_at' => null, 'failure_message' => 'A stale worker was recovered automatically.']);
        $query = AiAnswerRun::where('status', 'queued')->oldest();
        if ($this->option('run')) $query->whereKey($this->option('run'));
        if ($this->option('batch')) $query->where('batch_token', $this->option('batch'));

        $claimed = collect();
        foreach ($query->limit(max(20, (int) $this->option('limit') * 8))->get() as $candidate) {
            if (! AiAnswerRun::whereKey($candidate->id)->where('status', 'queued')->update(['status' => 'starting'])) continue;
            $claimed->push($candidate);
            if ($claimed->count() >= max(1, min(10, (int) $this->option('limit')))) break;
        }
        foreach ($claimed as $run) {
            try { $service->processRun($run->fresh()); $this->info("AI answer run {$run->id} completed."); }
            catch (\Throwable $exception) { report($exception); $this->error($exception->getMessage()); }
        }
        return self::SUCCESS;
    }
}

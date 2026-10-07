<?php

namespace App\Console\Commands;

use App\Models\ImageCleanupRun;
use App\Services\ImageCleanupService;
use Illuminate\Console\Command;

class ProcessImageCleanup extends Command
{
    protected $signature = 'image-cleanup:process {--limit=1} {--run=} {--worker=}';
    protected $description = 'Process queued image cleanup and redraw drafts';

    public function handle(ImageCleanupService $service): int
    {
        ImageCleanupRun::whereIn('status', ['starting', 'running'])->where('updated_at', '<', now()->subHours(1))
            ->update(['status' => 'queued', 'started_at' => null, 'failure_message' => 'A stale image worker was recovered automatically.']);
        $query = ImageCleanupRun::where('status', 'queued')->oldest();
        if ($this->option('run')) $query->whereKey((int) $this->option('run'));
        $claimed = collect();
        foreach ($query->limit(max(5, (int) $this->option('limit') * 4))->get() as $candidate) {
            if (! ImageCleanupRun::whereKey($candidate->id)->where('status', 'queued')->update(['status' => 'starting'])) continue;
            $claimed->push($candidate);
            if ($claimed->count() >= max(1, min(4, (int) $this->option('limit')))) break;
        }
        foreach ($claimed as $run) {
            try { $service->processRun($run->fresh()); $this->info("Image cleanup run {$run->id} completed."); }
            catch (\Throwable $exception) {
                report($exception);
                $run->update(['status' => 'failed', 'failure_message' => $exception->getMessage(), 'completed_at' => now()]);
                $this->error($exception->getMessage());
            }
        }
        return self::SUCCESS;
    }
}

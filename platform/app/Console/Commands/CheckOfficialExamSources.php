<?php

namespace App\Console\Commands;

use App\Models\OfficialExamSource;
use App\Services\OfficialExamMonitorService;
use Illuminate\Console\Command;

class CheckOfficialExamSources extends Command
{
    protected $signature = 'official-exams:check {--source=} {--limit=5} {--force}';
    protected $description = 'Discover new official exam PDFs/ZIPs and create immutable source-exam drafts';

    public function handle(OfficialExamMonitorService $monitor): int
    {
        $query = OfficialExamSource::query()->where('enabled', true);
        if ($this->option('source')) $query->whereKey($this->option('source'));
        elseif (! $this->option('force')) $query->where(fn ($q) => $q->whereNull('next_check_at')->orWhere('next_check_at', '<=', now()));
        $sources = $query->orderByRaw('CASE WHEN next_check_at IS NULL THEN 0 ELSE 1 END')->orderBy('next_check_at')->limit(max(1, min(50, (int) $this->option('limit'))))->get();
        foreach ($sources as $source) {
            try {
                $run = $monitor->check($source);
                $this->info("{$source->name}: {$run->status}; found {$run->found_count}, created {$run->created_count}, skipped {$run->skipped_count}, review {$run->review_count}");
            } catch (\Throwable $exception) { $this->error("{$source->name}: {$exception->getMessage()}"); }
        }
        return self::SUCCESS;
    }
}

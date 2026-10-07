<?php

namespace App\Console\Commands;

use App\Models\QuestionImportRun;
use App\Services\QuestionImportRunService;
use Illuminate\Console\Command;

class ProcessQuestionImports extends Command
{
    protected $signature = 'questions:process-imports {--limit=1} {--import=}';
    protected $description = 'Process queued large CSV imports submitted from the Questions Import screen';

    public function handle(QuestionImportRunService $service): int
    {
        QuestionImportRun::query()->whereIn('status', ['starting', 'processing'])
            ->where('updated_at', '<', now()->subHours(2))->update(['status' => 'queued']);

        $query = QuestionImportRun::query()->where('status', 'queued')->oldest();
        if ($this->option('import')) $query->where('upload_id', $this->option('import'));
        $processed = 0;
        foreach ($query->limit(max(5, (int) $this->option('limit') * 5))->get() as $candidate) {
            if (! QuestionImportRun::whereKey($candidate->id)->where('status', 'queued')->update(['status' => 'starting'])) continue;
            $service->process($candidate->fresh());
            $processed++;
            if ($processed >= max(1, (int) $this->option('limit'))) break;
        }
        return self::SUCCESS;
    }
}

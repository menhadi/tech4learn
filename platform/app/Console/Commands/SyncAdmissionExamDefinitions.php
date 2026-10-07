<?php

namespace App\Console\Commands;

use App\Services\AdmissionExamRegistry;
use Illuminate\Console\Command;

class SyncAdmissionExamDefinitions extends Command
{
    protected $signature = 'admission-prediction:sync-exams';

    protected $description = 'Create or update reusable admission exam definitions from configuration';

    public function handle(AdmissionExamRegistry $registry): int
    {
        foreach ($registry->sync() as $definition) {
            $this->line("Synced {$definition->code}: {$definition->name}");
        }

        return self::SUCCESS;
    }
}

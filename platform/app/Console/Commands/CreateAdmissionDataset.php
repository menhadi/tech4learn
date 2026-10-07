<?php

namespace App\Console\Commands;

use App\Models\AdmissionExamDefinition;
use App\Services\AdmissionDatasetLifecycleService;
use Illuminate\Console\Command;
use Throwable;

class CreateAdmissionDataset extends Command
{
    protected $signature = 'admission-prediction:dataset-create {exam} {version} {resources*} {--created-by=} {--notes=}';

    protected $description = 'Create an immutable-versioned draft dataset from registered official resources';

    public function handle(AdmissionDatasetLifecycleService $lifecycle): int
    {
        try {
            $exam = AdmissionExamDefinition::query()->where('code', $this->argument('exam'))->firstOrFail();
            $dataset = $lifecycle->createDraft(
                $exam, (string) $this->argument('version'), (array) $this->argument('resources'),
                $this->option('created-by') !== null ? (int) $this->option('created-by') : null,
                $this->option('notes') !== null ? (string) $this->option('notes') : null
            );
            $this->info("Draft dataset {$dataset->id} created with {$dataset->officialResources->count()} resource(s).");

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}

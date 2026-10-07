<?php

namespace App\Console\Commands;

use App\Models\AdmissionDatasetVersion;
use App\Services\AdmissionDatasetLifecycleService;
use Illuminate\Console\Command;
use Throwable;

class PublishAdmissionDataset extends Command
{
    protected $signature = 'admission-prediction:dataset-publish {dataset} {--confirm= : Must exactly match the dataset version} {--approved-by=}';

    protected $description = 'Explicitly publish a validated admission dataset and retire the previous version';

    public function handle(AdmissionDatasetLifecycleService $lifecycle): int
    {
        try {
            $dataset = AdmissionDatasetVersion::query()->findOrFail($this->argument('dataset'));
            $published = $lifecycle->publish(
                $dataset, (string) $this->option('confirm'),
                $this->option('approved-by') !== null ? (int) $this->option('approved-by') : null
            );
            $this->info("Published dataset {$published->id} ({$published->version}).");

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}

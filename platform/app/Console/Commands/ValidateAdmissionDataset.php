<?php

namespace App\Console\Commands;

use App\Models\AdmissionDatasetVersion;
use App\Services\AdmissionDatasetLifecycleService;
use Illuminate\Console\Command;
use Throwable;

class ValidateAdmissionDataset extends Command
{
    protected $signature = 'admission-prediction:dataset-validate {dataset}';

    protected $description = 'Validate an admission dataset and report prediction readiness';

    public function handle(AdmissionDatasetLifecycleService $lifecycle): int
    {
        try {
            $summary = $lifecycle->validate(AdmissionDatasetVersion::query()->findOrFail($this->argument('dataset')));
            $this->line(json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return $summary['valid'] ? self::SUCCESS : self::FAILURE;
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}

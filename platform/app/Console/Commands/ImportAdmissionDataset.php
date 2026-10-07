<?php

namespace App\Console\Commands;

use App\Models\AdmissionDatasetVersion;
use App\Models\AdmissionOfficialResource;
use App\Services\AdmissionObservationImporter;
use App\Services\AdmissionPdfExtractionService;
use App\Services\JosaaOrCrAdapter;
use Illuminate\Console\Command;
use InvalidArgumentException;
use Throwable;

class ImportAdmissionDataset extends Command
{
    protected $signature = 'admission-prediction:dataset-import
        {dataset : Dataset ID} {resource : Official resource ID}
        {kind : jee_statistics, mcc_seat_matrix, mcc_allotment_cutoffs, or josaa_cutoffs}
        {--path= : Local official PDF path} {--year= : Exam year}
        {--round=1} {--institute-type=ALL} {--institute=ALL} {--program=ALL} {--seat-type=ALL}';

    protected $description = 'Replace one official resource import inside a mutable draft dataset';

    public function handle(AdmissionObservationImporter $importer, AdmissionPdfExtractionService $pdf, JosaaOrCrAdapter $josaa): int
    {
        try {
            $dataset = AdmissionDatasetVersion::query()->findOrFail($this->argument('dataset'));
            $resource = AdmissionOfficialResource::query()->findOrFail($this->argument('resource'));
            $kind = (string) $this->argument('kind');
            $year = (int) ($this->option('year') ?: $resource->exam_year);
            if ($year < 2000) {
                throw new InvalidArgumentException('A valid --year or resource exam_year is required.');
            }

            if ($kind === 'josaa_cutoffs') {
                $round = (string) $this->option('round');
                $records = $josaa->fetchRows([
                    'round' => $round, 'institute_type' => (string) $this->option('institute-type'),
                    'institute' => (string) $this->option('institute'), 'program' => (string) $this->option('program'),
                    'seat_type' => (string) $this->option('seat-type'),
                ]);
                $context = ['round' => $round];
            } else {
                $path = (string) $this->option('path');
                if ($path === '') {
                    throw new InvalidArgumentException('--path is required for PDF imports.');
                }
                [$examCode, $resourceKind] = match ($kind) {
                    'jee_statistics' => ['jee-main-paper-1', 'exam_statistics'],
                    'mcc_seat_matrix' => ['neet-ug', 'seat_matrix'],
                    'mcc_allotment_cutoffs' => ['neet-ug', 'allotment_result'],
                    default => throw new InvalidArgumentException("Unsupported import kind [{$kind}]."),
                };
                $records = $pdf->extract($examCode, $resourceKind, $path);
                $context = [];
            }

            $result = $importer->import($dataset, $resource, $kind, $records, $year, $context);
            $this->info("Imported {$result['imported']} row(s); skipped {$result['skipped']}. Dataset returned to draft status.");

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}

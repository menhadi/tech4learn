<?php

namespace App\Services;

use App\Models\AdmissionDatasetVersion;
use App\Models\AdmissionOfficialResource;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class AdmissionObservationImporter
{
    public function __construct(private AdmissionDatasetLifecycleService $lifecycle) {}

    public function import(AdmissionDatasetVersion $dataset, AdmissionOfficialResource $resource, string $kind, array $records, int $examYear, array $context = []): array
    {
        $this->lifecycle->assertMutable($dataset);
        $this->assertResource($dataset, $resource);

        return DB::transaction(function () use ($dataset, $resource, $kind, $records, $examYear, $context) {
            $result = match ($kind) {
                'jee_statistics' => $this->replaceJeeStatistics($dataset, $resource, $records, $examYear),
                'mcc_seat_matrix' => $this->replaceMccSeats($dataset, $resource, $records, $examYear),
                'mcc_allotment_cutoffs' => $this->replaceMccAllotmentCutoffs($dataset, $resource, $records, $examYear, $context),
                'josaa_cutoffs' => $this->replaceJosaaCutoffs($dataset, $resource, $records, $examYear, $context),
                default => throw new InvalidArgumentException("Unsupported observation import kind [{$kind}]."),
            };

            $metadata = $resource->extraction_metadata ?? [];
            $metadata['last_import'] = [
                'dataset_id' => $dataset->id, 'kind' => $kind, 'record_count' => $result['imported'],
                'imported_at' => now()->toIso8601String(),
            ];
            $resource->forceFill(['extracted_at' => now(), 'extraction_metadata' => $metadata])->save();
            $dataset->forceFill(['status' => AdmissionDatasetVersion::STATUS_DRAFT, 'validation_summary' => null])->save();

            return $result;
        });
    }

    private function replaceJeeStatistics($dataset, $resource, array $records, int $examYear): array
    {
        $dataset->rankObservations()->where('admission_official_resource_id', $resource->id)->delete();
        $rows = [];
        foreach ($records as $record) {
            $min = isset($record['percentile_min']) ? (float) $record['percentile_min'] : null;
            $max = isset($record['percentile_max']) ? (float) $record['percentile_max'] : null;
            if ($min === null || $max === null || $min > $max) {
                continue;
            }
            $rows[] = $this->timestamps([
                'admission_dataset_version_id' => $dataset->id,
                'admission_official_resource_id' => $resource->id,
                'exam_year' => (int) ($record['exam_year'] ?? $examYear),
                'session' => isset($record['session']) ? (string) $record['session'] : null,
                'shift' => $record['shift'] ?? null,
                'percentile' => ($min + $max) / 2,
                'candidate_count' => $record['candidate_count'] ?? null,
                'dimensions' => json_encode([
                    'record_type' => $record['record_type'] ?? 'percentile_distribution',
                    'exam_date' => $record['exam_date'] ?? null,
                    'percentile_min' => $min, 'percentile_max' => $max,
                ]),
                'provenance' => json_encode($record['provenance'] ?? []),
            ]);
        }
        $this->insertChunks('admission_rank_observations', $rows);

        return ['imported' => count($rows), 'skipped' => count($records) - count($rows), 'table' => 'rank'];
    }

    private function replaceMccSeats($dataset, $resource, array $records, int $examYear): array
    {
        $dataset->cutoffObservations()->where('admission_official_resource_id', $resource->id)->delete();
        $rows = [];
        foreach ($records as $record) {
            if (trim((string) ($record['institute_name'] ?? '')) === '' || trim((string) ($record['program_name'] ?? '')) === '') {
                continue;
            }
            $rows[] = $this->timestamps([
                'admission_dataset_version_id' => $dataset->id,
                'admission_official_resource_id' => $resource->id,
                'exam_year' => $examYear, 'counselling_body' => 'MCC',
                'institution_code' => $record['institution_code'] ?? null,
                'institution_name' => $record['institute_name'],
                'program_name' => $record['program_name'],
                'category' => $record['category'] ?? null, 'quota' => $record['quota'] ?? null,
                'gender_pool' => $record['seat_gender'] ?? null,
                'domicile_state' => $record['state_name'] ?? null,
                'seat_count' => (int) ($record['seat_count'] ?? 0),
                'dimensions' => json_encode(['institution_type' => $record['institution_type'] ?? null, 'record_type' => 'seat_matrix']),
                'provenance' => json_encode($record['provenance'] ?? []),
            ]);
        }
        $this->insertChunks('admission_cutoff_observations', $rows);

        return ['imported' => count($rows), 'skipped' => count($records) - count($rows), 'table' => 'cutoff'];
    }

    private function replaceMccAllotmentCutoffs($dataset, $resource, array $records, int $examYear, array $context): array
    {
        $dataset->cutoffObservations()->where('admission_official_resource_id', $resource->id)->delete();
        $rows = [];
        foreach ($records as $record) {
            $institution = trim((string) ($record['institute_name'] ?? ''));
            $program = trim((string) ($record['program_name'] ?? ''));
            $opening = $this->rank($record['opening_rank'] ?? null);
            $closing = $this->rank($record['closing_rank'] ?? null);
            if ($institution === '' || $program === '' || $opening === null || $closing === null) {
                continue;
            }
            $rows[] = $this->timestamps([
                'admission_dataset_version_id' => $dataset->id,
                'admission_official_resource_id' => $resource->id,
                'exam_year' => $examYear,
                'counselling_body' => 'MCC',
                'round' => (string) ($context['round'] ?? ''),
                'institution_name' => $institution,
                'program_name' => $program,
                'category' => $record['category'] ?? null,
                'quota' => $record['quota'] ?? null,
                'opening_rank' => $opening,
                'closing_rank' => $closing,
                'seat_count' => (int) ($record['allotment_count'] ?? 0),
                'dimensions' => json_encode([
                    'record_type' => 'opening_closing_rank',
                    'candidate_category_counts' => $record['candidate_category_counts'] ?? [],
                ]),
                'provenance' => json_encode($record['provenance'] ?? []),
            ]);
        }
        $this->insertChunks('admission_cutoff_observations', $rows);

        return ['imported' => count($rows), 'skipped' => count($records) - count($rows), 'table' => 'cutoff'];
    }

    private function replaceJosaaCutoffs($dataset, $resource, array $records, int $examYear, array $context): array
    {
        $dataset->cutoffObservations()->where('admission_official_resource_id', $resource->id)->delete();
        $rows = [];
        foreach ($records as $record) {
            $institution = trim((string) ($record['institute'] ?? $record['institute_name'] ?? ''));
            $program = trim((string) ($record['academic_program_name'] ?? $record['program_name'] ?? ''));
            $opening = $this->rank($record['opening_rank'] ?? null);
            $closing = $this->rank($record['closing_rank'] ?? null);
            if ($institution === '' || $program === '' || $closing === null) {
                continue;
            }
            $rows[] = $this->timestamps([
                'admission_dataset_version_id' => $dataset->id,
                'admission_official_resource_id' => $resource->id,
                'exam_year' => $examYear, 'counselling_body' => 'JoSAA',
                'round' => (string) ($context['round'] ?? ''),
                'institution_name' => $institution, 'program_name' => $program,
                'category' => $record['seat_type'] ?? null, 'quota' => $record['quota'] ?? null,
                'gender_pool' => $record['gender'] ?? $record['gender_pool'] ?? null,
                'opening_rank' => $opening, 'closing_rank' => $closing,
                'dimensions' => json_encode(['record_type' => 'opening_closing_rank']),
                'provenance' => json_encode($record['provenance'] ?? []),
            ]);
        }
        $this->insertChunks('admission_cutoff_observations', $rows);

        return ['imported' => count($rows), 'skipped' => count($records) - count($rows), 'table' => 'cutoff'];
    }

    private function assertResource($dataset, $resource): void
    {
        if ($resource->admission_exam_definition_id !== $dataset->admission_exam_definition_id) {
            throw new InvalidArgumentException('The resource and dataset must belong to the same exam.');
        }
        if ($resource->status !== AdmissionOfficialResource::STATUS_VERIFIED) {
            throw new InvalidArgumentException('Only verified official resources may be imported.');
        }
        if (! $dataset->officialResources()->whereKey($resource->id)->exists()) {
            throw new InvalidArgumentException('The resource is not attached to this dataset.');
        }
    }

    private function rank(mixed $value): ?int
    {
        $digits = preg_replace('/[^0-9]/', '', (string) $value);

        return $digits === '' ? null : (int) $digits;
    }

    private function timestamps(array $row): array
    {
        return $row + ['created_at' => now(), 'updated_at' => now()];
    }

    private function insertChunks(string $table, array $rows): void
    {
        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table($table)->insert($chunk);
        }
    }
}

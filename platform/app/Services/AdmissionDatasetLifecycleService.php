<?php

namespace App\Services;

use App\Models\AdmissionDatasetVersion;
use App\Models\AdmissionExamDefinition;
use App\Models\AdmissionOfficialResource;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class AdmissionDatasetLifecycleService
{
    public function createDraft(AdmissionExamDefinition $exam, string $version, array $resourceIds, ?int $createdBy = null, ?string $notes = null): AdmissionDatasetVersion
    {
        $version = trim($version);
        if ($version === '') {
            throw new InvalidArgumentException('A dataset version is required.');
        }

        return DB::transaction(function () use ($exam, $version, $resourceIds, $createdBy, $notes) {
            $ids = array_values(array_unique(array_map('intval', $resourceIds)));
            if ($ids === []) {
                throw ValidationException::withMessages(['resources' => 'At least one official resource is required.']);
            }
            $resources = AdmissionOfficialResource::query()
                ->where('admission_exam_definition_id', $exam->id)->whereIn('id', $ids)->get();
            if ($resources->count() !== count($ids)) {
                throw ValidationException::withMessages(['resources' => 'Every resource must exist and belong to the selected exam.']);
            }

            $dataset = AdmissionDatasetVersion::query()->create([
                'admission_exam_definition_id' => $exam->id, 'created_by' => $createdBy,
                'version' => $version, 'schema_version' => (int) config('admission_prediction.schema_version', 1),
                'status' => AdmissionDatasetVersion::STATUS_DRAFT, 'notes' => $notes,
            ]);
            $dataset->officialResources()->sync($resources->modelKeys());

            return $dataset->fresh(['officialResources']);
        });
    }

    public function validate(AdmissionDatasetVersion $dataset): array
    {
        $dataset->loadMissing('officialResources');
        $errors = [];
        $warnings = [];
        $unverified = $dataset->officialResources->where('status', '!=', AdmissionOfficialResource::STATUS_VERIFIED)->pluck('id')->all();
        if ($dataset->officialResources->isEmpty()) {
            $errors[] = 'No official resources are attached.';
        }
        if ($unverified !== []) {
            $errors[] = 'Unverified official resources: '.implode(', ', $unverified).'.';
        }

        $rankCount = $dataset->rankObservations()->count();
        $cutoffCount = $dataset->cutoffObservations()->count();
        $usableRankCount = $dataset->rankObservations()->whereNotNull('marks')
            ->where(fn ($q) => $q->whereNotNull('rank_min')->orWhereNotNull('rank_max'))->count();
        $percentileCount = $dataset->rankObservations()->whereNotNull('percentile')->count();
        $usableCutoffCount = $dataset->cutoffObservations()->whereNotNull('closing_rank')->count();
        if ($rankCount + $cutoffCount === 0) {
            $errors[] = 'The dataset contains no observations.';
        }
        if ($usableRankCount === 0) {
            $warnings[] = 'Marks-to-rank prediction is unavailable until official marks/rank observations are imported.';
        }
        if ($usableCutoffCount === 0) {
            $warnings[] = 'College prediction is unavailable until official closing-rank observations are imported.';
        }

        $summary = [
            'valid' => $errors === [], 'errors' => $errors, 'warnings' => $warnings,
            'counts' => [
                'resources' => $dataset->officialResources->count(),
                'rank_observations' => $rankCount, 'percentile_observations' => $percentileCount,
                'usable_marks_rank_observations' => $usableRankCount,
                'cutoff_observations' => $cutoffCount, 'usable_cutoff_observations' => $usableCutoffCount,
                'total_seats' => (int) $dataset->cutoffObservations()->sum('seat_count'),
            ],
            'validated_at' => now()->toIso8601String(),
        ];
        $dataset->forceFill([
            'validation_summary' => $summary,
            'status' => $summary['valid'] ? AdmissionDatasetVersion::STATUS_VALIDATED : AdmissionDatasetVersion::STATUS_DRAFT,
        ])->save();

        return $summary;
    }

    public function publish(AdmissionDatasetVersion $dataset, string $confirmation, ?int $approvedBy = null): AdmissionDatasetVersion
    {
        if (! hash_equals($dataset->version, $confirmation)) {
            throw ValidationException::withMessages(['confirmation' => 'The confirmation must exactly match the dataset version.']);
        }

        return DB::transaction(function () use ($dataset, $approvedBy) {
            $locked = AdmissionDatasetVersion::query()->lockForUpdate()->findOrFail($dataset->id);
            if ($locked->status === AdmissionDatasetVersion::STATUS_PUBLISHED) {
                return $locked;
            }
            $summary = $this->validate($locked);
            if (! $summary['valid']) {
                throw ValidationException::withMessages(['dataset' => $summary['errors']]);
            }
            AdmissionDatasetVersion::query()
                ->where('admission_exam_definition_id', $locked->admission_exam_definition_id)
                ->where('status', AdmissionDatasetVersion::STATUS_PUBLISHED)->whereKeyNot($locked->id)
                ->update(['status' => AdmissionDatasetVersion::STATUS_RETIRED]);
            $locked->forceFill([
                'status' => AdmissionDatasetVersion::STATUS_PUBLISHED, 'approved_by' => $approvedBy,
                'approved_at' => now(), 'published_at' => now(),
            ])->save();

            return $locked->fresh();
        });
    }

    public function assertMutable(AdmissionDatasetVersion $dataset): void
    {
        if (! in_array($dataset->status, [AdmissionDatasetVersion::STATUS_DRAFT, AdmissionDatasetVersion::STATUS_VALIDATED], true)) {
            throw new InvalidArgumentException('Published or retired datasets are immutable.');
        }
    }
}

<?php

namespace App\Services;

use App\Models\AdmissionDatasetVersion;
use App\Models\AdmissionExamDefinition;
use Illuminate\Database\Eloquent\Builder;
use InvalidArgumentException;

class AdmissionPredictionService
{
    public function predict(string $examCode, array $input): array
    {
        $exam = AdmissionExamDefinition::query()->where('code', $examCode)->where('enabled', true)->firstOrFail();
        $dataset = AdmissionDatasetVersion::query()
            ->where('admission_exam_definition_id', $exam->id)
            ->where('status', AdmissionDatasetVersion::STATUS_PUBLISHED)
            ->latest('published_at')->first();

        if (! $dataset) {
            return $this->unavailable('No published official dataset is available for this exam.');
        }

        $rank = $this->resolveRank($dataset, $input);
        $colleges = $rank['available']
            ? $this->collegeMatches($dataset, $rank['rank_min'], $rank['rank_max'], $input)
            : [];

        return [
            'available' => $rank['available'],
            'exam' => ['code' => $exam->code, 'name' => $exam->name],
            'dataset' => [
                'id' => $dataset->id, 'version' => $dataset->version,
                'published_at' => optional($dataset->published_at)->toIso8601String(),
            ],
            'rank_prediction' => $rank,
            'college_predictions' => $colleges,
            'evidence' => [
                'official_resource_ids' => $dataset->officialResources()->pluck('admission_official_resources.id')->all(),
                'rank_observations_used' => $rank['observation_count'] ?? 0,
                'cutoff_rows_considered' => count($colleges),
            ],
            'disclaimer' => 'Indicative range from published official historical data; not an allotment guarantee.',
        ];
    }

    public function interpolateRank(array $points, float $marks): array
    {
        $points = array_values(array_filter($points, fn ($p) => isset($p['marks']) && (isset($p['rank_min']) || isset($p['rank_max']))));
        usort($points, fn ($a, $b) => (float) $a['marks'] <=> (float) $b['marks']);
        if (count($points) < 2 || $marks < (float) $points[0]['marks'] || $marks > (float) end($points)['marks']) {
            return $this->unavailable('The entered marks are outside the supported official-data range.');
        }

        $lower = $points[0];
        $upper = end($points);
        foreach ($points as $point) {
            if ((float) $point['marks'] <= $marks) {
                $lower = $point;
            }
            if ((float) $point['marks'] >= $marks) {
                $upper = $point;
                break;
            }
        }

        $rankAt = fn ($p) => ((int) ($p['rank_min'] ?? $p['rank_max']) + (int) ($p['rank_max'] ?? $p['rank_min'])) / 2;
        $span = (float) $upper['marks'] - (float) $lower['marks'];
        $ratio = $span > 0 ? ($marks - (float) $lower['marks']) / $span : 0.5;
        $estimate = (int) round($rankAt($lower) + ($rankAt($upper) - $rankAt($lower)) * $ratio);
        $sourceSpread = max(
            abs((int) ($lower['rank_max'] ?? $estimate) - (int) ($lower['rank_min'] ?? $estimate)),
            abs((int) ($upper['rank_max'] ?? $estimate) - (int) ($upper['rank_min'] ?? $estimate))
        );
        $margin = max(50, (int) ceil(abs($rankAt($upper) - $rankAt($lower)) * 0.15), (int) ceil($sourceSpread / 2));

        return [
            'available' => true, 'method' => 'official_observation_interpolation',
            'rank_min' => max(1, $estimate - $margin), 'rank_max' => $estimate + $margin,
            'central_estimate' => max(1, $estimate), 'confidence' => count($points) >= 8 ? 'high' : 'medium',
            'observation_count' => count($points),
            'limitations' => ['Historical relationships can shift with participation, difficulty, normalization, and tie-breaking.'],
        ];
    }

    private function resolveRank(AdmissionDatasetVersion $dataset, array $input): array
    {
        if (isset($input['rank'])) {
            $rank = max(1, (int) $input['rank']);

            return [
                'available' => true, 'method' => 'student_supplied_rank', 'rank_min' => $rank,
                'rank_max' => $rank, 'central_estimate' => $rank, 'confidence' => 'exact_input',
                'observation_count' => 0, 'limitations' => [],
            ];
        }

        if (isset($input['marks'])) {
            $query = $dataset->rankObservations()->whereNotNull('marks');
            $this->applyRankDimensions($query, $input);
            $points = $query->get(['marks', 'rank_min', 'rank_max'])->map->toArray()->all();

            return $this->interpolateRank($points, (float) $input['marks']);
        }

        if (isset($input['percentile'])) {
            $percentile = (float) $input['percentile'];
            if ($percentile < 0 || $percentile > 100) {
                throw new InvalidArgumentException('Percentile must be between 0 and 100.');
            }
            $query = $dataset->rankObservations()->whereNotNull('percentile');
            $this->applyRankDimensions($query, $input);
            if (empty($input['session']) && (clone $query)->whereNotNull('session')->distinct()->count('session') > 1) {
                return $this->unavailable(
                    'This dataset contains multiple sessions; select a session to avoid combining candidate populations.'
                );
            }
            $total = (int) $query->sum('candidate_count');
            if ($total <= 0) {
                return $this->unavailable('Official candidate totals are unavailable for this percentile.');
            }
            $estimate = max(1, (int) ceil(((100 - $percentile) / 100) * $total));
            $margin = max(100, (int) ceil($estimate * 0.05));

            return [
                'available' => true, 'method' => 'percentile_candidate_count_estimate',
                'rank_min' => max(1, $estimate - $margin), 'rank_max' => $estimate + $margin,
                'central_estimate' => $estimate, 'confidence' => 'low',
                'observation_count' => $query->count(),
                'limitations' => ['Candidate totals may count appearances rather than unique candidates.', 'Tie-breaking is not represented.'],
            ];
        }

        return $this->unavailable('Provide marks, percentile, or an official rank.');
    }

    private function collegeMatches(AdmissionDatasetVersion $dataset, int $rankMin, int $rankMax, array $input): array
    {
        $query = $dataset->cutoffObservations()->whereNotNull('closing_rank');
        foreach ([
            'category' => 'category', 'quota' => 'quota', 'gender_pool' => 'gender_pool',
            'round' => 'round', 'domicile_state' => 'domicile_state',
        ] as $inputKey => $column) {
            if (! empty($input[$inputKey])) {
                $query->where($column, $input[$inputKey]);
            }
        }
        if (! empty($input['institution'])) {
            $query->where('institution_name', 'like', '%'.$input['institution'].'%');
        }
        if (! empty($input['program'])) {
            $query->where('program_name', 'like', '%'.$input['program'].'%');
        }

        return $query->orderBy('closing_rank')->limit(100)->get()->map(function ($row) use ($rankMin, $rankMax) {
            $closing = (int) $row->closing_rank;
            $band = $rankMax <= $closing ? 'likely' : ($rankMin <= $closing ? 'possible' : 'reach');

            return [
                'institution' => $row->institution_name, 'program' => $row->program_name,
                'counselling_body' => $row->counselling_body, 'round' => $row->round,
                'category' => $row->category, 'quota' => $row->quota, 'gender_pool' => $row->gender_pool,
                'opening_rank' => $row->opening_rank, 'closing_rank' => $closing,
                'chance_band' => $band,
            ];
        })->sortBy(fn ($row) => ['likely' => 0, 'possible' => 1, 'reach' => 2][$row['chance_band']])
            ->values()->take(50)->all();
    }

    private function applyRankDimensions(Builder $query, array $input): void
    {
        foreach (['exam_year', 'session', 'shift', 'category'] as $field) {
            if (empty($input['exam_year'])) {
                $latestYear = (clone $query)->max('exam_year');
                if ($latestYear !== null) {
                    $query->where('exam_year', $latestYear);
                }
            }
            if (! empty($input[$field])) {
                $query->where($field, $input[$field]);
            }
        }
    }

    private function unavailable(string $reason): array
    {
        return [
            'available' => false, 'reason' => $reason, 'rank_min' => null, 'rank_max' => null,
            'central_estimate' => null, 'confidence' => 'unavailable', 'observation_count' => 0,
            'limitations' => [$reason],
        ];
    }
}

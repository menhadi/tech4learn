<?php

namespace App\Services;

use App\Models\Exam;
use App\Models\Package;
use App\Models\PypPackageSetting;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class PypContentService
{
    public const DEFAULTS = [
        'enabled' => true,
        'subject_pages' => true,
        'topic_pages' => true,
        'subtopic_pages' => true,
        'analysis_enabled' => true,
        'min_questions' => 5,
        'min_years_for_historical' => 2,
        'cache_minutes' => 60,
        'sample_questions' => 6,
        'cache_version' => 1,
    ];

    public function settings(): array
    {
        $configured = (array) (getConfiguration()?->pyp_settings ?? []);

        return array_replace(self::DEFAULTS, $configured);
    }

    public function packageSetting(Package $package): ?PypPackageSetting
    {
        if (! Schema::hasTable('pyp_package_settings')) {
            return null;
        }

        return PypPackageSetting::query()
            ->where('package_id', $package->id)
            ->where(function ($query) use ($package) {
                $query->where('organization_id', $package->organization_id)
                    ->orWhereNull('organization_id');
            })
            ->orderByRaw('CASE WHEN organization_id IS NULL THEN 1 ELSE 0 END')
            ->first();
    }

    public function enabledFor(Package $package): bool
    {
        return (bool) $this->settings()['enabled']
            && (bool) ($this->packageSetting($package)?->enabled ?? true)
            && $this->pypExamQuery($package)->exists();
    }

    public function dataset(Package $package): array
    {
        $settings = $this->settings();
        $cacheKey = implode(':', [
            'pyp-content',
            $package->organization_id ?: 'global',
            $package->id,
            (int) $settings['cache_version'],
        ]);

        return Cache::remember($cacheKey, now()->addMinutes(max(1, (int) $settings['cache_minutes'])), function () use ($package) {
            return $this->buildDataset($package);
        });
    }

    public function forget(Package $package): void
    {
        $settings = $this->settings();
        Cache::forget(implode(':', [
            'pyp-content',
            $package->organization_id ?: 'global',
            $package->id,
            (int) $settings['cache_version'],
        ]));
    }

    public function pypExamQuery(Package $package)
    {
        return $package->exams()
            ->where('exams.status', 'Active')
            ->where(function ($query) {
                $query->where('exams.test_type', Exam::TEST_TYPE_PREVIOUS_YEAR)
                    ->orWhereRaw('LOWER(COALESCE(exams.category_level_1, ?)) = ?', ['', 'previous_year_papers'])
                    ->orWhereRaw('LOWER(COALESCE(exams.category_level_2, ?)) = ?', ['', 'year_wise']);
            });
    }

    public function token(string $name, int $id): string
    {
        return (Str::slug($name) ?: 'item').'-'.$id;
    }

    public function idFromToken(string $token): int
    {
        preg_match('/-(\d+)$/', $token, $matches);

        return (int) ($matches[1] ?? 0);
    }

    public function filteredEntries(array $dataset, ?int $subjectId = null, ?int $topicId = null, ?int $subtopicId = null): Collection
    {
        return collect($dataset['entries'])->filter(function (array $entry) use ($subjectId, $topicId, $subtopicId) {
            return (! $subjectId || $entry['subject_id'] === $subjectId)
                && (! $topicId || $entry['topic_id'] === $topicId)
                && (! $subtopicId || $entry['subtopic_id'] === $subtopicId);
        })->values();
    }

    private function buildDataset(Package $package): array
    {
        $exams = $this->pypExamQuery($package)
            ->get(['exams.id', 'exams.name', 'exams.slug', 'exams.updated_at'])
            ->map(function ($exam) {
                $name = $this->text($exam->name);
                preg_match_all('/(?:19|20)\d{2}/', $name, $matches);
                $year = collect($matches[0] ?? [])->last();

                return [
                    'id' => (int) $exam->id,
                    'name' => $name,
                    'slug' => $exam->slug,
                    'year' => $year ? (int) $year : null,
                    'updated_at' => optional($exam->updated_at)->toAtomString(),
                ];
            })->values();

        if ($exams->isEmpty()) {
            return $this->emptyDataset($package);
        }

        $examMap = $exams->keyBy('id');
        $questionRows = DB::table('exam_questions')
            ->join('questions', 'questions.id', '=', 'exam_questions.question_id')
            ->leftJoin('diffs', 'diffs.id', '=', 'questions.diff_id')
            ->leftJoin('qtypes', 'qtypes.id', '=', 'questions.qtype_id')
            ->whereIn('exam_questions.exam_id', $exams->pluck('id'))
            ->whereNotNull('questions.question')
            ->select([
                'exam_questions.exam_id',
                'questions.id as question_id',
                'questions.question',
                'questions.subject_id',
                'questions.topic_id',
                'questions.stopic_id',
                'questions.marks',
                'diffs.diff_level',
                'qtypes.question_type',
                'qtypes.type as question_type_code',
            ])
            ->get();

        $questionIds = $questionRows->pluck('question_id')->unique()->values();
        $groupIds = $package->groups()->pluck('groups.id');
        $taxonomy = collect();
        if ($questionIds->isNotEmpty() && $groupIds->isNotEmpty() && Schema::hasTable('question_taxonomies')) {
            $taxonomy = DB::table('question_taxonomies')
                ->whereIn('question_id', $questionIds)
                ->whereIn('group_id', $groupIds)
                ->orderBy('id')
                ->get()
                ->keyBy('question_id');
        }

        $subjectIds = collect();
        $topicIds = collect();
        $subtopicIds = collect();
        $prepared = $questionRows->map(function ($row) use ($taxonomy, $examMap, &$subjectIds, &$topicIds, &$subtopicIds) {
            $tax = $taxonomy->get($row->question_id);
            $subjectId = (int) ($tax->subject_id ?? $row->subject_id ?? 0);
            $topicId = (int) ($tax->topic_id ?? $row->topic_id ?? 0);
            $subtopicId = (int) ($tax->stopic_id ?? $row->stopic_id ?? 0);
            if ($subjectId) {
                $subjectIds->push($subjectId);
            }
            if ($topicId) {
                $topicIds->push($topicId);
            }
            if ($subtopicId) {
                $subtopicIds->push($subtopicId);
            }
            $exam = $examMap->get((int) $row->exam_id);

            return [
                'exam_id' => (int) $row->exam_id,
                'exam_name' => $exam['name'] ?? 'Previous year paper',
                'exam_slug' => $exam['slug'] ?? null,
                'year' => $exam['year'] ?? null,
                'question_id' => (int) $row->question_id,
                'question' => $this->text($row->question),
                'subject_id' => $subjectId ?: null,
                'topic_id' => $topicId ?: null,
                'subtopic_id' => $subtopicId ?: null,
                'marks' => (float) ($row->marks ?? 0),
                'difficulty' => $this->text($row->diff_level) ?: 'Not classified',
                'question_type' => $this->text($row->question_type) ?: ($row->question_type_code ?: 'Other'),
            ];
        });

        $subjectNames = $this->names('subjects', 'subject_name', $subjectIds);
        $topicNames = $this->names('topics', 'name', $topicIds);
        $subtopicNames = $this->names('stopics', 'name', $subtopicIds);
        $entries = $prepared->map(function (array $entry) use ($subjectNames, $topicNames, $subtopicNames) {
            $entry['subject_name'] = $subjectNames[$entry['subject_id']] ?? 'Unclassified';
            $entry['topic_name'] = $topicNames[$entry['topic_id']] ?? 'Unclassified';
            $entry['subtopic_name'] = $subtopicNames[$entry['subtopic_id']] ?? 'Unclassified';

            return $entry;
        })->values();

        $subjects = $this->dimensionSummary($entries, 'subject_id', 'subject_name');
        $topics = $this->dimensionSummary($entries, 'topic_id', 'topic_name', ['subject_id', 'subject_name']);
        $subtopics = $this->dimensionSummary($entries, 'subtopic_id', 'subtopic_name', ['subject_id', 'subject_name', 'topic_id', 'topic_name']);
        $years = $entries->filter(fn ($row) => $row['year'])
            ->groupBy('year')
            ->map(fn ($rows, $year) => [
                'year' => (int) $year,
                'questions' => $rows->count(),
                'unique_questions' => $rows->pluck('question_id')->unique()->count(),
                'papers' => $rows->pluck('exam_id')->unique()->count(),
                'marks' => round($rows->sum('marks'), 2),
            ])
            ->sortKeysDesc()
            ->values();

        return [
            'package' => ['id' => $package->id, 'name' => $this->text($package->name), 'slug' => $package->slug],
            'exams' => $exams->sortByDesc('year')->values()->all(),
            'entries' => $entries->all(),
            'subjects' => $subjects->all(),
            'topics' => $topics->all(),
            'subtopics' => $subtopics->all(),
            'years' => $years->all(),
            'difficulty' => $this->simpleSummary($entries, 'difficulty')->all(),
            'question_types' => $this->simpleSummary($entries, 'question_type')->all(),
            'totals' => [
                'papers' => $exams->count(),
                'years' => $exams->pluck('year')->filter()->unique()->count(),
                'question_occurrences' => $entries->count(),
                'unique_questions' => $entries->pluck('question_id')->unique()->count(),
                'subjects' => $subjects->count(),
                'topics' => $topics->count(),
                'subtopics' => $subtopics->count(),
            ],
            'updated_at' => $exams->pluck('updated_at')->filter()->max() ?: now()->toAtomString(),
        ];
    }

    private function dimensionSummary(Collection $entries, string $idKey, string $nameKey, array $parents = []): Collection
    {
        return $entries->filter(fn ($row) => $row[$idKey])
            ->groupBy($idKey)
            ->map(function ($rows, $id) use ($nameKey, $parents) {
                $first = $rows->first();
                $result = [
                    'id' => (int) $id,
                    'name' => $first[$nameKey],
                    'token' => $this->token($first[$nameKey], (int) $id),
                    'questions' => $rows->count(),
                    'unique_questions' => $rows->pluck('question_id')->unique()->count(),
                    'papers' => $rows->pluck('exam_id')->unique()->count(),
                    'years' => $rows->pluck('year')->filter()->unique()->sortDesc()->values()->all(),
                ];
                foreach ($parents as $parent) {
                    $result[$parent] = $first[$parent];
                }

                return $result;
            })
            ->sortByDesc('questions')
            ->values();
    }

    private function simpleSummary(Collection $entries, string $key): Collection
    {
        $total = max(1, $entries->count());

        return $entries->groupBy($key)->map(fn ($rows, $label) => [
            'label' => (string) $label,
            'questions' => $rows->count(),
            'percentage' => round(($rows->count() / $total) * 100, 1),
        ])->sortByDesc('questions')->values();
    }

    private function names(string $table, string $column, Collection $ids): array
    {
        if ($ids->filter()->isEmpty()) {
            return [];
        }

        return DB::table($table)->whereIn('id', $ids->filter()->unique())
            ->pluck($column, 'id')
            ->map(fn ($value) => $this->text($value))
            ->all();
    }

    private function emptyDataset(Package $package): array
    {
        return [
            'package' => ['id' => $package->id, 'name' => $this->text($package->name), 'slug' => $package->slug],
            'exams' => [], 'entries' => [], 'subjects' => [], 'topics' => [], 'subtopics' => [],
            'years' => [], 'difficulty' => [], 'question_types' => [],
            'totals' => ['papers' => 0, 'years' => 0, 'question_occurrences' => 0, 'unique_questions' => 0, 'subjects' => 0, 'topics' => 0, 'subtopics' => 0],
            'updated_at' => now()->toAtomString(),
        ];
    }

    private function text($value): string
    {
        if (is_array($value)) {
            return (string) ($value['en'] ?? reset($value) ?: '');
        }
        if (is_string($value) && str_starts_with(trim($value), '{')) {
            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                return (string) ($decoded['en'] ?? reset($decoded) ?: $value);
            }
        }

        return trim((string) ($value ?? ''));
    }
}

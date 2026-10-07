<?php

namespace App\Services;

use App\Imports\ExamWorkbookRowsImport;
use App\Models\Category;
use App\Models\Exam;
use App\Models\ExamQualitySource;
use App\Models\Group;
use App\Models\Package;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;

class ExamWorkbookService
{
    public const CLEAR = '__CLEAR__';

    private array $scalarColumns = [
        'name', 'slug', 'display_order', 'passing_percentage', 'instruction', 'syllabus',
        'duration', 'attempt_count', 'start_date', 'end_date', 'grouping_mode', 'timer_mode',
        'show_answer_sheet', 'negative_marking', 'random_question', 'result_after_finish',
        'mode', 'instant_result', 'option_shuffle', 'allow_answer_change', 'multi_language', 'math_editor',
        'browser_tolerance', 'proctor', 'calculator_allowed', 'tolerance_count', 'status',
        'meta_title', 'meta_description', 'meta_keywords', 'canonical_url', 'og_title',
        'og_description', 'og_image', 'robots_meta', 'seo_schema',
    ];

    private array $booleanColumns = [
        'show_answer_sheet', 'negative_marking', 'random_question', 'result_after_finish',
        'instant_result', 'option_shuffle', 'allow_answer_change', 'multi_language', 'math_editor',
        'browser_tolerance', 'proctor', 'calculator_allowed',
    ];

    private array $classificationColumns = [
        'test_type', 'test_type_label',
        'test_subject_id', 'test_subject_name',
        'test_topic_id', 'test_topic_name',
        'test_subtopic_id', 'test_subtopic_name',
    ];

    public function headings(): array
    {
        return array_merge(
            ['operation', 'exam_id'],
            $this->scalarColumns,
            $this->classificationColumns,
            ['group_ids', 'group_names', 'package_ids', 'package_names', 'package_display_orders', 'category_id', 'category_name', 'subcategory_id', 'subcategory_name'],
            $this->sourceHeadings('question'),
            $this->sourceHeadings('answer'),
            $this->sourceHeadings('combined')
        );
    }

    private function sourceHeadings(string $prefix): array
    {
        return ["{$prefix}_pdf_action", "{$prefix}_pdf_kind", "{$prefix}_pdf_disk", "{$prefix}_pdf_value", "{$prefix}_pdf_label", "{$prefix}_pdf_download_url"];
    }

    public function exportQuery(int $organizationId, array $filters = []): Builder
    {
        $query = Exam::query()
            ->where('organization_id', $organizationId)
            ->with([
                'groups:id,group_name',
                'packages:id,name',
                'category:id,title',
                'subcategory:id,title',
                'testSubject:id,subject_name',
                'testTopic:id,name',
                'testSubtopic:id,name',
                'qualitySources',
            ]);

        if ($groupId = (int) ($filters['group_id'] ?? 0)) {
            $query->whereHas('groups', fn (Builder $groupQuery) => $groupQuery->whereKey($groupId));
        }
        if ($categoryId = (int) ($filters['category_id'] ?? 0)) {
            $query->where(function (Builder $categoryQuery) use ($categoryId) {
                $categoryQuery->where('category_level_1', $categoryId)
                    ->orWhereHas('packages', fn (Builder $packageQuery) => $packageQuery->where('category_level_1', $categoryId));
            });
        }
        if ($subcategoryId = (int) ($filters['subcategory_id'] ?? 0)) {
            $query->where(function (Builder $subcategoryQuery) use ($subcategoryId) {
                $subcategoryQuery->where('category_level_2', $subcategoryId)
                    ->orWhereHas('packages', fn (Builder $packageQuery) => $packageQuery->where('category_level_2', $subcategoryId));
            });
        }
        if ($packageId = (int) ($filters['package_id'] ?? 0)) {
            $query->whereHas('packages', fn (Builder $packageQuery) => $packageQuery->whereKey($packageId));
        }
        if ($search = trim((string) ($filters['search'] ?? ''))) {
            $query->where('name', 'like', '%'.$search.'%');
        }

        return $query->orderBy('id');
    }

    public function exportRow(Exam $exam): array
    {
        $row = ['UPDATE', $exam->id];
        foreach ($this->scalarColumns as $column) {
            $value = $exam->{$column};
            if ($value instanceof \DateTimeInterface) {
                $value = $value->format('Y-m-d H:i:s');
            }
            if (in_array($column, $this->booleanColumns, true)) {
                $value = $value ? 1 : 0;
            }
            $row[] = $value;
        }
        $testType = $exam->test_type ?: Exam::TEST_TYPE_OTHER;
        $row[] = $testType;
        $row[] = Exam::testTypeLabels()[$testType] ?? Exam::testTypeLabels()[Exam::TEST_TYPE_OTHER];
        $row[] = $exam->test_subject_id;
        $row[] = $exam->testSubject?->subject_name;
        $row[] = $exam->test_topic_id;
        $row[] = $exam->testTopic?->name;
        $row[] = $exam->test_stopic_id;
        $row[] = $exam->testSubtopic?->name;
        $row[] = $exam->groups->pluck('id')->implode(',');
        $row[] = $exam->groups->pluck('group_name')->implode(' | ');
        $row[] = $exam->packages->pluck('id')->implode(',');
        $row[] = $exam->packages->pluck('name')->implode(' | ');
        $row[] = $exam->packages->map(fn ($package) => $package->id.':'.((int) ($package->pivot->display_order ?? 0)))->implode(',');
        $row[] = $exam->category_level_1;
        $row[] = $exam->category?->title;
        $row[] = $exam->category_level_2;
        $row[] = $exam->subcategory?->title;
        foreach (['questions' => 'question', 'answers' => 'answer', 'combined' => 'combined'] as $role => $prefix) {
            $source = $exam->qualitySources->where('role', $role)->where('is_active', true)->sortByDesc('id')->first();
            $row[] = 'KEEP';
            $row[] = $source?->kind;
            $row[] = $source?->storage_disk ?: ($source?->kind === 'file' ? 'local' : null);
            $row[] = $source?->kind === 'file' ? $source?->file_path : $source?->source_url;
            $row[] = $source?->label;
            $row[] = $source ? route('exams.sources.download', [$exam, $source]) : null;
        }

        return $row;
    }

    public function preview(string $absolutePath, int $organizationId): array
    {
        $result = [
            'total' => 0, 'valid' => 0, 'creates' => 0, 'updates' => 0,
            'question_pdfs' => 0,
            'answer_pdfs' => 0,
            'combined_pdfs' => 0,
            'pdf_removals' => 0,
            'errors' => [],
            'warnings' => [],
        ];

        Excel::import(new ExamWorkbookRowsImport(function (int $line, array $row) use ($organizationId, &$result) {
            $result['total']++;
            $errors = $this->validateRow($row, $organizationId);
            if ($errors) {
                $result['errors'][] = ['row' => $line, 'message' => implode(' ', $errors)];

                return;
            }
            $operation = strtoupper(trim((string) ($row['operation'] ?? 'UPDATE'))) ?: 'UPDATE';
            $result[$operation === 'CREATE' ? 'creates' : 'updates']++;
            $result['valid']++;

            foreach (['question', 'answer', 'combined'] as $prefix) {
                $action = strtoupper(trim((string) ($row["{$prefix}_pdf_action"] ?? 'KEEP'))) ?: 'KEEP';
                if ($action === 'REPLACE') {
                    $result["{$prefix}_pdfs"]++;
                } elseif ($action === 'REMOVE') {
                    $result['pdf_removals']++;
                }
            }
        }), $absolutePath);

        return $result;
    }

    public function detectOrganizationId(string $absolutePath): ?int
    {
        $examIds = [];

        Excel::import(new ExamWorkbookRowsImport(function (int $line, array $row) use (&$examIds) {
            $operation = strtoupper(trim((string) ($row['operation'] ?? 'UPDATE'))) ?: 'UPDATE';
            if ($operation === 'UPDATE' && is_numeric($row['exam_id'] ?? null)) {
                $examIds[] = (int) $row['exam_id'];
            }
        }), $absolutePath);

        if ($examIds === []) {
            return null;
        }

        $organizationIds = Exam::query()
            ->whereKey(array_values(array_unique($examIds)))
            ->distinct()
            ->pluck('organization_id');

        return $organizationIds->count() === 1
            ? (int) $organizationIds->first()
            : null;
    }

    public function apply(string $absolutePath, int $organizationId, ?callable $onProgress = null): array
    {
        $summary = [
            'created' => 0,
            'updated' => 0,
            'failed' => 0,
            'question_pdfs' => 0,
            'answer_pdfs' => 0,
            'combined_pdfs' => 0,
            'pdf_removals' => 0,
            'errors' => [],
        ];

        Excel::import(new ExamWorkbookRowsImport(function (int $line, array $row) use ($organizationId, &$summary, $onProgress) {
            $errors = $this->validateRow($row, $organizationId);
            if ($errors) {
                $summary['failed']++;
                $summary['errors'][] = "Row {$line}: ".implode(' ', $errors);
                if ($onProgress) {
                    $onProgress($line, $summary);
                }

                return;
            }
            try {
                DB::transaction(function () use ($row, $organizationId, &$summary) {
                    $operation = strtoupper(trim((string) ($row['operation'] ?? 'UPDATE'))) ?: 'UPDATE';
                    $exam = $operation === 'CREATE'
                        ? new Exam(['organization_id' => $organizationId])
                        : Exam::where('organization_id', $organizationId)->findOrFail((int) $row['exam_id']);

                    $data = [];
                    foreach ($this->scalarColumns as $column) {
                        if (! array_key_exists($column, $row) || $row[$column] === null || $row[$column] === '') {
                            continue;
                        }
                        $value = $row[$column] === self::CLEAR ? null : $row[$column];
                        if (in_array($column, $this->booleanColumns, true)) {
                            $value = $this->boolean($value);
                        }
                        $data[$column] = $value;
                    }
                    $data += $this->classificationData($row, $exam, $operation === 'CREATE');
                    if ($operation === 'CREATE') {
                        $data['name'] = trim((string) $row['name']);
                        $data['slug'] = trim((string) (($row['slug'] ?? '') ?: Str::slug($data['name'])));
                        $data += ['duration' => 0, 'attempt_count' => 0, 'passing_percentage' => 0, 'mode' => 'Exam', 'status' => 'Active', 'grouping_mode' => 'subject', 'timer_mode' => 'none', 'tolerance_count' => 0];
                    }
                    if (isset($data['slug'])) {
                        $base = Str::slug((string) $data['slug']) ?: Str::slug((string) ($data['name'] ?? $exam->name));
                        $slug = $base;
                        $suffix = 2;
                        while (Exam::where('organization_id', $organizationId)->where('slug', $slug)->when($exam->exists, fn ($query) => $query->where('id', '!=', $exam->id))->exists()) {
                            $slug = $base.'-'.$suffix++;
                        }
                        $data['slug'] = $slug;
                    }
                    if (isset($data['timer_mode'])) {
                        $data['is_subject_timer'] = $data['timer_mode'] !== 'none';
                    }
                    $exam->fill($data);
                    $exam->organization_id = $organizationId;
                    $exam->save();

                    $groupIds = $this->hasValue($row, 'group_ids')
                        ? $this->ids($row['group_ids'])
                        : $exam->groups()->pluck('groups.id')->all();
                    $packageIds = $this->hasValue($row, 'package_ids')
                        ? $this->ids($row['package_ids'])
                        : $exam->packages()->pluck('packages.id')->all();
                    $categoryId = $this->hasValue($row, 'category_id')
                        ? ($row['category_id'] === self::CLEAR ? null : (int) $row['category_id'])
                        : ($exam->category_level_1 ? (int) $exam->category_level_1 : null);
                    $subcategoryId = $this->hasValue($row, 'subcategory_id')
                        ? ($row['subcategory_id'] === self::CLEAR ? null : (int) $row['subcategory_id'])
                        : ($exam->category_level_2 ? (int) $exam->category_level_2 : null);
                    $scope = app(ExamScopeService::class)->resolve(
                        $organizationId, $packageIds, $groupIds, $categoryId, $subcategoryId
                    );
                    app(ExamScopeService::class)->sync($exam, $scope);

                    if ($this->hasValue($row, 'package_display_orders')) {
                        foreach ($this->pivotOrders($row['package_display_orders']) as $packageId => $order) {
                            if ($exam->packages()->whereKey($packageId)->exists()) {
                                $exam->packages()->updateExistingPivot($packageId, ['display_order' => $order]);
                            }
                        }
                    }
                    foreach (['question' => 'questions', 'answer' => 'answers', 'combined' => 'combined'] as $prefix => $role) {
                        $this->syncSource($exam, $row, $prefix, $role, $organizationId);
                        $action = strtoupper(trim((string) ($row["{$prefix}_pdf_action"] ?? 'KEEP'))) ?: 'KEEP';
                        if ($action === 'REPLACE') {
                            $summary["{$prefix}_pdfs"]++;
                        } elseif ($action === 'REMOVE') {
                            $summary['pdf_removals']++;
                        }
                    }
                    $summary[$operation === 'CREATE' ? 'created' : 'updated']++;
                });
            } catch (\Throwable $exception) {
                $summary['failed']++;
                $summary['errors'][] = "Row {$line}: ".$exception->getMessage();
            } finally {
                if ($onProgress) {
                    $onProgress($line, $summary);
                }
            }
        }), $absolutePath);

        return $summary;
    }

    private function validateRow(array $row, int $organizationId): array
    {
        $errors = [];
        $operation = strtoupper(trim((string) ($row['operation'] ?? 'UPDATE'))) ?: 'UPDATE';
        if (! in_array($operation, ['CREATE', 'UPDATE'], true)) {
            $errors[] = 'Operation must be CREATE or UPDATE.';
        }
        if ($operation === 'UPDATE' && (! is_numeric($row['exam_id'] ?? null) || ! Exam::where('organization_id', $organizationId)->whereKey((int) $row['exam_id'])->exists())) {
            $errors[] = 'Exam ID is missing or does not belong to this organization.';
        }
        if ($operation === 'CREATE' && trim((string) ($row['name'] ?? '')) === '') {
            $errors[] = 'Name is required when creating an exam.';
        }
        if ($operation === 'CREATE' && $this->ids($row['group_ids'] ?? '') === []) {
            $errors[] = 'At least one group ID is required when creating an exam.';
        }
        if ($this->hasValue($row, 'group_ids') && ($row['group_ids'] === self::CLEAR || $this->ids($row['group_ids']) === [])) {
            $errors[] = 'An exam must keep at least one group.';
        }
        if ($operation === 'CREATE' && ! $this->hasValue($row, 'start_date')) {
            $errors[] = 'Start date is required when creating an exam.';
        }
        if ($operation === 'CREATE' && ! $this->hasValue($row, 'end_date')) {
            $errors[] = 'End date is required when creating an exam.';
        }
        if ($this->hasValue($row, 'name') && $row['name'] === self::CLEAR) {
            $errors[] = 'Name cannot be cleared.';
        }

        $exam = null;
        if ($operation === 'UPDATE' && is_numeric($row['exam_id'] ?? null)) {
            $exam = Exam::where('organization_id', $organizationId)->find((int) $row['exam_id']);
        }
        if ($operation === 'CREATE' || $exam) {
            $errors = array_merge($errors, $this->validateClassification($row, $exam ?: new Exam, $operation === 'CREATE', $organizationId));
        }

        foreach (['display_order', 'passing_percentage', 'duration', 'attempt_count', 'tolerance_count'] as $column) {
            if ($this->hasValue($row, $column) && $row[$column] !== self::CLEAR && ! is_numeric($row[$column])) {
                $errors[] = "{$column} must be numeric.";
            }
        }
        foreach (['start_date', 'end_date'] as $column) {
            if ($this->hasValue($row, $column) && $row[$column] !== self::CLEAR && strtotime((string) $row[$column]) === false) {
                $errors[] = "{$column} is not a valid date.";
            }
        }
        foreach (['grouping_mode' => ['none', 'subject', 'section'], 'timer_mode' => ['none', 'subject', 'section'], 'mode' => ['Exam', 'Preparation'], 'status' => ['Active', 'Inactive']] as $column => $allowed) {
            if ($this->hasValue($row, $column) && $row[$column] !== self::CLEAR && ! in_array((string) $row[$column], $allowed, true)) {
                $errors[] = "{$column} has an invalid value.";
            }
        }
        foreach ($this->booleanColumns as $column) {
            if (! $this->hasValue($row, $column) || $row[$column] === self::CLEAR) {
                continue;
            }
            if (! in_array(strtolower(trim((string) $row[$column])), ['0', '1', 'yes', 'no', 'true', 'false', 'on', 'off', 'active', 'inactive'], true)) {
                $errors[] = "{$column} must be yes/no or 1/0.";
            }
        }

        foreach (['group_ids' => Group::class, 'package_ids' => Package::class] as $column => $model) {
            if (! $this->hasValue($row, $column) || $row[$column] === self::CLEAR) {
                continue;
            }
            $ids = $this->ids($row[$column]);
            $found = $model::where('organization_id', $organizationId)->whereIn('id', $ids)->count();
            if ($found !== count($ids)) {
                $errors[] = "{$column} contains an ID outside this organization.";
            }
        }
        foreach (['category_id', 'subcategory_id'] as $column) {
            if (! $this->hasValue($row, $column) || $row[$column] === self::CLEAR) {
                continue;
            }
            if (! Category::where('organization_id', $organizationId)->whereKey((int) $row[$column])->exists()) {
                $errors[] = "{$column} is invalid.";
            }
        }
        foreach (['question', 'answer', 'combined'] as $prefix) {
            $action = strtoupper(trim((string) ($row["{$prefix}_pdf_action"] ?? 'KEEP'))) ?: 'KEEP';
            if (! in_array($action, ['KEEP', 'REPLACE', 'REMOVE'], true)) {
                $errors[] = "{$prefix}_pdf_action must be KEEP, REPLACE or REMOVE.";
            }
            if ($action === 'REPLACE') {
                $kind = strtolower(trim((string) ($row["{$prefix}_pdf_kind"] ?? '')));
                $value = trim((string) ($row["{$prefix}_pdf_value"] ?? ''));
                if (! in_array($kind, ['file', 'url'], true) || $value === '') {
                    $errors[] = "{$prefix} PDF needs kind and value when replacing.";
                }
                if ($kind === 'url' && ! filter_var($value, FILTER_VALIDATE_URL)) {
                    $errors[] = "{$prefix} PDF URL is invalid.";
                }
                if ($kind === 'file') {
                    $disk = trim((string) ($row["{$prefix}_pdf_disk"] ?? 'local')) ?: 'local';
                    if (! array_key_exists($disk, (array) config('filesystems.disks'))) {
                        $errors[] = "{$prefix} PDF disk is not configured.";
                    }
                }
            }
        }

        return $errors;
    }

    private function validateClassification(array $row, Exam $exam, bool $creating, int $organizationId): array
    {
        $errors = [];
        $rawType = $this->classificationValue($row, ['test_type']);
        if ($rawType === self::CLEAR) {
            return ['test_type cannot be cleared.'];
        }

        $type = $rawType !== null
            ? $this->normalizeTestType((string) $rawType)
            : ($creating ? Exam::TEST_TYPE_OTHER : ($exam->test_type ?: Exam::TEST_TYPE_OTHER));
        if (! $type) {
            return ['test_type is invalid. Use one of: '.implode(', ', array_keys(Exam::testTypeLabels())).'.'];
        }

        $classification = $this->classificationData($row, $exam, $creating);
        $subjectId = $classification['test_subject_id'];
        $topicId = $classification['test_topic_id'];
        $subtopicId = $classification['test_stopic_id'];

        if (in_array($type, [Exam::TEST_TYPE_SUBJECT, Exam::TEST_TYPE_TOPIC, Exam::TEST_TYPE_SUBTOPIC], true) && ! $subjectId) {
            $errors[] = 'test_subject_id is required for subject, topic, and subtopic tests.';
        }
        if (in_array($type, [Exam::TEST_TYPE_TOPIC, Exam::TEST_TYPE_SUBTOPIC], true) && ! $topicId) {
            $errors[] = 'test_topic_id is required for topic and subtopic tests.';
        }
        if ($type === Exam::TEST_TYPE_SUBTOPIC && ! $subtopicId) {
            $errors[] = 'test_subtopic_id is required for subtopic tests.';
        }
        $groupIds = $this->hasValue($row, 'group_ids') && $row['group_ids'] !== self::CLEAR
            ? $this->ids($row['group_ids'])
            : ($creating ? [] : $exam->groups()->pluck('groups.id')->all());

        try {
            app(CurriculumTaxonomyService::class)->validateSelection(
                $organizationId,
                $groupIds,
                $subjectId,
                $topicId,
                $subtopicId,
            );
        } catch (ValidationException $exception) {
            foreach ($exception->errors() as $messages) {
                foreach ($messages as $message) {
                    $errors[] = $message;
                }
            }
        }

        return $errors;
    }

    private function classificationData(array $row, Exam $exam, bool $creating): array
    {
        $rawType = $this->classificationValue($row, ['test_type']);
        $type = $rawType !== null && $rawType !== self::CLEAR
            ? ($this->normalizeTestType((string) $rawType) ?: Exam::TEST_TYPE_OTHER)
            : ($creating ? Exam::TEST_TYPE_OTHER : ($exam->test_type ?: Exam::TEST_TYPE_OTHER));

        $subjectId = $this->classificationId($row, ['test_subject_id'], $creating ? null : $exam->test_subject_id);
        $topicId = $this->classificationId($row, ['test_topic_id'], $creating ? null : $exam->test_topic_id);
        $subtopicId = $this->classificationId($row, ['test_subtopic_id', 'test_stopic_id'], $creating ? null : $exam->test_stopic_id);

        if (! in_array($type, [Exam::TEST_TYPE_SUBJECT, Exam::TEST_TYPE_TOPIC, Exam::TEST_TYPE_SUBTOPIC], true)) {
            $subjectId = null;
        }
        if (! in_array($type, [Exam::TEST_TYPE_TOPIC, Exam::TEST_TYPE_SUBTOPIC], true)) {
            $topicId = null;
        }
        if ($type !== Exam::TEST_TYPE_SUBTOPIC) {
            $subtopicId = null;
        }

        return [
            'test_type' => $type,
            'test_subject_id' => $subjectId,
            'test_topic_id' => $topicId,
            'test_stopic_id' => $subtopicId,
        ];
    }

    private function classificationId(array $row, array $keys, ?int $current): ?int
    {
        $value = $this->classificationValue($row, $keys);
        if ($value === null) {
            return $current;
        }
        if ($value === self::CLEAR) {
            return null;
        }

        return is_numeric($value) && (int) $value > 0 ? (int) $value : null;
    }

    private function classificationValue(array $row, array $keys): mixed
    {
        foreach ($keys as $key) {
            if ($this->hasValue($row, $key)) {
                return $row[$key];
            }
        }

        return null;
    }

    private function normalizeTestType(string $value): ?string
    {
        $normalized = Str::of($value)->trim()->lower()->replaceMatches('/[^a-z0-9]+/', '_')->trim('_')->toString();
        foreach (Exam::testTypeLabels() as $key => $label) {
            $normalizedLabel = Str::of($label)->lower()->replaceMatches('/[^a-z0-9]+/', '_')->trim('_')->toString();
            if ($normalized === $key || $normalized === $normalizedLabel) {
                return $key;
            }
        }

        return null;
    }

    private function syncSource(Exam $exam, array $row, string $prefix, string $role, int $organizationId): void
    {
        $action = strtoupper(trim((string) ($row["{$prefix}_pdf_action"] ?? 'KEEP'))) ?: 'KEEP';
        if ($action === 'KEEP') {
            return;
        }
        $existing = ExamQualitySource::where('organization_id', $organizationId)->where('exam_id', $exam->id)->where('role', $role)->get();
        $storage = app(ExamQualitySourceStorage::class);
        foreach ($existing as $source) {
            $storage->delete($source);
            $source->delete();
        }
        if ($action === 'REMOVE') {
            return;
        }

        $kind = strtolower(trim((string) $row["{$prefix}_pdf_kind"]));
        $value = trim((string) $row["{$prefix}_pdf_value"]);
        ExamQualitySource::create([
            'organization_id' => $organizationId, 'exam_id' => $exam->id, 'role' => $role, 'kind' => $kind,
            'label' => trim((string) ($row["{$prefix}_pdf_label"] ?? '')) ?: basename($value),
            'storage_disk' => $kind === 'file' ? (trim((string) ($row["{$prefix}_pdf_disk"] ?? 'local')) ?: 'local') : 'local',
            'file_path' => $kind === 'file' ? ltrim($value, '/') : null,
            'source_url' => $kind === 'url' ? $value : null, 'is_active' => true,
        ]);
    }

    private function hasValue(array $row, string $key): bool
    {
        return array_key_exists($key, $row) && $row[$key] !== null && $row[$key] !== '';
    }

    private function ids(mixed $value): array
    {
        return collect(preg_split('/[|,;]/', (string) $value))->map(fn ($id) => (int) trim($id))->filter()->unique()->values()->all();
    }

    private function pivotOrders(mixed $value): array
    {
        return collect(preg_split('/[|,;]/', (string) $value))->mapWithKeys(function ($pair) {
            $parts = array_map('trim', explode(':', (string) $pair, 2));

            return count($parts) === 2 && (int) $parts[0] > 0 ? [(int) $parts[0] => max(0, (int) $parts[1])] : [];
        })->all();
    }

    private function boolean(mixed $value): bool
    {
        return in_array(strtolower(trim((string) $value)), ['1', 'yes', 'true', 'on', 'active'], true);
    }
}

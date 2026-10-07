<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\ContentNormalizationRun;
use App\Models\Exam;
use App\Models\Group;
use App\Models\Package;
use App\Support\SaasAccess;
use App\Support\Tenant;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\Rule;
use RuntimeException;
use Throwable;

class ContentNormalizationAdminController extends Controller
{
    private const BATCH_SIZE = 100;

    private const EMPTY_STATS = [
        'records' => 0,
        'clean' => 0,
        'converted' => 0,
        'sanitized' => 0,
        'needs_review' => 0,
        'changed_records' => 0,
        'applied_records' => 0,
        'skipped_concurrent' => 0,
        'math_expressions' => 0,
    ];

    public function index()
    {
        SaasAccess::abortIfFeatureDisabled('exam_quality_ai');
        $tenantId = (int) Tenant::id();
        $groups = Group::where('organization_id', $tenantId)->displayOrdered()->get(['id', 'group_name']);
        $categories = Category::where('organization_id', $tenantId)->with('groups:id,group_name')->displayOrdered()->get(['id', 'parent_id', 'title']);
        $packages = Package::where('organization_id', $tenantId)->with('groups:id,group_name')->displayOrdered()->get(['id', 'name', 'category_level_1', 'category_level_2']);
        $categoryById = $categories->keyBy('id');
        $categoryOptions = $categories->map(function ($category) use ($categoryById) {
            $groups = $category->groups->pluck('id');
            if ($groups->isEmpty() && $category->parent_id) {
                $groups = $categoryById->get($category->parent_id)?->groups?->pluck('id') ?? collect();
            }

            return [
                'id' => (int) $category->id,
                'parent_id' => $category->parent_id ? (int) $category->parent_id : null,
                'title' => $category->title,
                'group_ids' => $groups->map(fn ($id) => (int) $id)->values(),
            ];
        })->values();
        $packageOptions = $packages->map(function ($package) use ($categoryById) {
            $groupIds = $package->groups->pluck('id')
                ->merge($categoryById->get($package->category_level_1)?->groups?->pluck('id') ?? collect())
                ->merge($categoryById->get($package->category_level_2)?->groups?->pluck('id') ?? collect());

            return [
                'id' => (int) $package->id,
                'name' => $package->name,
                'category_id' => $package->category_level_1 ? (int) $package->category_level_1 : null,
                'subcategory_id' => $package->category_level_2 ? (int) $package->category_level_2 : null,
                'group_ids' => $groupIds->unique()->map(fn ($id) => (int) $id)->values(),
            ];
        })->values();
        $runs = ContentNormalizationRun::with(['requester:id,name'])
            ->where('organization_id', $tenantId)->latest()->limit(50)->get();
        $examIds = $runs->flatMap(fn (ContentNormalizationRun $run) => $run->filters['exam_ids'] ?? [])
            ->map(fn ($id) => (int) $id)->unique()->values();
        $examLookup = Exam::where('organization_id', $tenantId)->whereIn('id', $examIds)
            ->withCount('questions')->get(['id', 'name'])->keyBy('id');
        $runPaperOptions = $runs->mapWithKeys(function (ContentNormalizationRun $run) use ($examLookup) {
            $restored = $run->status === 'restored'
                ? array_map('intval', $run->filters['exam_ids'] ?? [])
                : array_map('intval', $run->restored_exam_ids ?? []);
            $papers = collect($run->filters['exam_ids'] ?? [])->map(function ($id) use ($examLookup, $restored) {
                $exam = $examLookup->get((int) $id);
                return [
                    'id' => (int) $id,
                    'name' => $exam?->name ?? 'Deleted paper #'.(int) $id,
                    'question_count' => (int) ($exam?->questions_count ?? 0),
                    'available' => (bool) $exam,
                    'restored' => in_array((int) $id, $restored, true),
                ];
            })->values()->all();
            return [$run->id => $papers];
        })->all();

        return view('content-normalization.index', compact('groups', 'categoryOptions', 'packageOptions', 'runs', 'runPaperOptions'));
    }

    public function examSearch(Request $request): JsonResponse
    {
        $tenantId = (int) Tenant::id();
        $filters = $this->validatePaperFilters($request, $tenantId);
        $query = $this->filteredExamQuery($tenantId, $filters);
        $total = (clone $query)->count();
        $results = $query->withCount('questions')->latest('id')->limit(30)->get(['id', 'name'])->map(fn ($exam) => [
            'id' => (int) $exam->id,
            'text' => $exam->name,
            'question_count' => (int) $exam->questions_count,
        ]);

        return response()->json(['results' => $results, 'total' => $total]);
    }

    public function selectionSummary(Request $request): JsonResponse
    {
        $tenantId = (int) Tenant::id();
        $filters = $this->validatePaperFilters($request, $tenantId);
        $examIds = $this->filteredExamQuery($tenantId, $filters)->pluck('id')->map(fn ($id) => (int) $id)->all();

        return response()->json([
            'paper_count' => count($examIds),
            'question_count' => $examIds === [] ? 0 : $this->questionQuery($tenantId, ['exam_ids' => $examIds, 'group_ids' => [], 'category_ids' => []])->count(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        SaasAccess::abortIfFeatureDisabled('exam_quality_ai');
        $organizationId = (int) Tenant::id();
        $validated = $request->validate([
            'selection_mode' => ['required', Rule::in(['selected', 'all_filtered'])],
            'mode' => ['required', Rule::in(['preview', 'apply'])],
            'content_scope' => ['required', Rule::in(['all_content', 'mathml_only'])],
            'exam_ids' => ['nullable', 'required_if:selection_mode,selected', 'array', 'min:1', 'max:500'],
            'exam_ids.*' => ['integer', 'distinct', Rule::exists('exams', 'id')->where('organization_id', $organizationId)],
            'q' => ['nullable', 'string', 'max:120'],
            'group_id' => ['nullable', Rule::exists('groups', 'id')->where('organization_id', $organizationId)],
            'category_id' => ['nullable', Rule::exists('category', 'id')->where('organization_id', $organizationId)],
            'package_id' => ['nullable', Rule::exists('packages', 'id')->where('organization_id', $organizationId)],
            'from_id' => ['nullable', 'integer', 'min:1'],
            'to_id' => ['nullable', 'integer', 'min:1', 'gte:from_id'],
            'confirm_apply' => ['exclude_unless:mode,apply', 'required', 'accepted'],
        ]);
        $paperFilters = [
            'q' => $validated['q'] ?? null,
            'group_id' => $validated['group_id'] ?? null,
            'category_id' => $validated['category_id'] ?? null,
            'package_id' => $validated['package_id'] ?? null,
        ];
        $examIds = $validated['selection_mode'] === 'all_filtered'
            ? $this->filteredExamQuery($organizationId, $paperFilters)->pluck('id')->map(fn ($id) => (int) $id)->all()
            : collect($validated['exam_ids'] ?? [])->map(fn ($id) => (int) $id)->unique()->values()->all();
        if ($examIds === []) {
            return response()->json(['message' => 'No tenant papers match the selected filters.'], 422);
        }
        $filters = [
            'group_ids' => [],
            'category_ids' => [],
            'exam_ids' => $examIds,
            'paper_filters' => $paperFilters,
            'selection_mode' => $validated['selection_mode'],
            'from_id' => isset($validated['from_id']) ? (int) $validated['from_id'] : null,
            'to_id' => isset($validated['to_id']) ? (int) $validated['to_id'] : null,
            'content_scope' => $validated['content_scope'],
            'only_mathml' => $validated['content_scope'] === 'mathml_only',
        ];
        $total = $this->questionQuery($organizationId, $filters)->count();
        if ($total === 0) {
            return response()->json(['message' => 'No tenant questions match the selected papers and ID range.'], 422);
        }
        $run = ContentNormalizationRun::create([
            'organization_id' => $organizationId,
            'requested_by' => auth()->id(),
            'mode' => $validated['mode'],
            'status' => 'pending',
            'filters' => $filters,
            'total_questions' => $total,
            'stats' => self::EMPTY_STATS,
            'child_run_ids' => [],
            'restored_child_run_ids' => [],
            'restore_exam_ids' => [],
            'restored_exam_ids' => [],
        ]);
        audit_log('content_normalization.'.$validated['mode'].'.started', $run, ['filters' => $filters, 'total_questions' => $total]);

        return response()->json(['message' => 'Normalization run created.', 'run' => $this->runPayload($run)], 201);
    }

    public function step(ContentNormalizationRun $run): JsonResponse
    {
        $this->authorizeRun($run);
        if (! in_array($run->status, ['pending', 'running'], true)) {
            return response()->json(['run' => $this->runPayload($run)]);
        }
        if (! $this->claim($run, ['pending', 'running'])) {
            return response()->json(['message' => 'This run is already processing another batch.'], 409);
        }

        try {
            $ids = $this->questionQuery($run->organization_id, $run->filters)
                ->where('questions.id', '>', $run->cursor_question_id)
                ->orderBy('questions.id')->limit(self::BATCH_SIZE)->pluck('questions.id')->map(fn ($id) => (int) $id)->all();
            if ($ids === []) {
                $run->update(['status' => 'completed', 'finished_at' => now()]);
                audit_log('content_normalization.'.$run->mode.'.completed', $run, ['stats' => $run->stats]);

                return response()->json(['done' => true, 'run' => $this->runPayload($run->fresh())]);
            }

            $parameters = [
                '--organization' => $run->organization_id,
                '--question' => $ids,
                '--chunk' => self::BATCH_SIZE,
            ];
            // Runs created before content_scope was introduced were MathML-only.
            // Keep that behavior when resuming an older run.
            if ((bool) ($run->filters['only_mathml'] ?? true)) {
                $parameters['--only-mathml'] = true;
            }
            if ($run->mode === 'apply') {
                $parameters['--apply'] = true;
            }
            $exitCode = Artisan::call('questions:normalize-content', $parameters);
            $output = Artisan::output();
            if (! in_array($exitCode, [0, 1], true)) {
                throw new RuntimeException(trim($output) ?: 'The normalization command rejected this batch.');
            }
            [$stepStats, $childRunId] = $this->readCommandResult($output);
            $stats = $this->mergeStats($run->stats ?: self::EMPTY_STATS, $stepStats);
            $childRuns = $run->child_run_ids ?: [];
            if ($run->mode === 'apply' && $childRunId && ($stepStats['applied_records'] ?? 0) > 0) {
                $childRuns[] = $childRunId;
            }
            $run->update([
                'status' => 'running',
                'started_at' => $run->started_at ?: now(),
                'cursor_question_id' => max($ids),
                'processed_questions' => min($run->total_questions, $run->processed_questions + count($ids)),
                'stats' => $stats,
                'child_run_ids' => array_values(array_unique($childRuns)),
                'error' => null,
            ]);

            return response()->json(['done' => false, 'run' => $this->runPayload($run->fresh())]);
        } catch (Throwable $exception) {
            report($exception);
            $run->update(['status' => 'failed', 'error' => $exception->getMessage(), 'finished_at' => now()]);

            return response()->json(['message' => 'Normalization stopped: '.$exception->getMessage(), 'run' => $this->runPayload($run->fresh())], 500);
        }
    }

    public function startRestore(Request $request, ContentNormalizationRun $run): JsonResponse
    {
        $this->authorizeRun($run);
        if ($run->mode !== 'apply' || ! in_array($run->status, ['completed', 'restore_partial'], true)) {
            return response()->json(['message' => 'Only a completed applied run can be restored.'], 422);
        }
        if (empty($run->child_run_ids)) {
            return response()->json(['message' => 'This run did not change any records, so there is nothing to restore.'], 422);
        }

        $allowedExamIds = collect($run->filters['exam_ids'] ?? [])
            ->map(fn ($id) => (int) $id)->unique()->values()->all();
        $validated = $request->validate([
            'exam_ids' => ['nullable', 'array', 'min:1'],
            'exam_ids.*' => ['integer', 'distinct', Rule::exists('exams', 'id')->where('organization_id', $run->organization_id)],
        ]);
        $requested = collect($validated['exam_ids'] ?? $allowedExamIds)
            ->map(fn ($id) => (int) $id)->unique()->values()->all();
        $restored = array_map('intval', $run->restored_exam_ids ?? []);
        if (array_diff($requested, $allowedExamIds) !== []) {
            return response()->json(['message' => 'One or more selected papers do not belong to this cleanup run.'], 422);
        }
        $selected = array_values(array_diff($requested, $restored));
        if ($selected === []) {
            return response()->json(['message' => 'Every selected paper has already been restored.'], 422);
        }
        $filters = $run->filters;
        $filters['exam_ids'] = $selected;
        if ($this->questionQuery($run->organization_id, $filters)->count() === 0) {
            return response()->json(['message' => 'No tenant questions remain for the selected papers and original ID range.'], 422);
        }
        $run->update([
            'status' => 'restoring',
            'restore_exam_ids' => $selected,
            'restored_child_run_ids' => [],
            'error' => null,
            'finished_at' => null,
        ]);
        audit_log('content_normalization.restore.started', $run, ['exam_ids' => $selected, 'child_runs' => count($run->child_run_ids)]);

        return response()->json(['run' => $this->runPayload($run->fresh())]);
    }

    public function restoreStep(ContentNormalizationRun $run): JsonResponse
    {
        $this->authorizeRun($run);
        if ($run->status !== 'restoring') {
            return response()->json(['run' => $this->runPayload($run)]);
        }
        if (! $this->claim($run, ['restoring'], 'restore_processing')) {
            return response()->json(['message' => 'This restore is already processing another batch.'], 409);
        }

        try {
            $selectedExamIds = array_map('intval', $run->restore_exam_ids ?? []);
            $filters = $run->filters;
            $filters['exam_ids'] = $selectedExamIds;
            $questionIds = $this->questionQuery($run->organization_id, $filters)
                ->pluck('questions.id')->map(fn ($id) => (int) $id)->all();
            if ($selectedExamIds === [] || $questionIds === []) {
                throw new RuntimeException('The selected paper restore scope is empty.');
            }
            $restored = $run->restored_child_run_ids ?: [];
            $remaining = array_values(array_diff(array_reverse($run->child_run_ids ?: []), $restored));
            if ($remaining === []) {
                $restoredExamIds = collect($run->restored_exam_ids ?? [])
                    ->merge($selectedExamIds)->map(fn ($id) => (int) $id)->unique()->values()->all();
                $allExamIds = array_map('intval', $run->filters['exam_ids'] ?? []);
                $allRestored = array_diff($allExamIds, $restoredExamIds) === [];
                $status = $run->restore_skipped > 0 ? 'restore_partial' : ($allRestored ? 'restored' : 'completed');
                $run->update([
                    'status' => $status,
                    'restored_exam_ids' => $restoredExamIds,
                    'restore_exam_ids' => [],
                    'restored_child_run_ids' => [],
                    'finished_at' => now(),
                ]);
                audit_log('content_normalization.restore.completed', $run, [
                    'status' => $status, 'exam_ids' => $selectedExamIds, 'skipped' => $run->restore_skipped,
                ]);

                return response()->json(['done' => true, 'run' => $this->runPayload($run->fresh())]);
            }

            $childRunId = $remaining[0];
            Artisan::call('questions:normalize-content', [
                '--organization' => $run->organization_id,
                '--restore' => $childRunId,
                '--question' => $questionIds,
            ]);
            $output = Artisan::output();
            $skipped = $this->metricFromOutput($output, 'skipped_concurrent');
            $restored[] = $childRunId;
            $run->update([
                'status' => 'restoring',
                'restored_child_run_ids' => array_values(array_unique($restored)),
                'restore_skipped' => $run->restore_skipped + $skipped,
                'error' => $skipped ? $skipped.' records were not restored because their content changed after normalization.' : null,
            ]);

            return response()->json(['done' => false, 'run' => $this->runPayload($run->fresh())]);
        } catch (Throwable $exception) {
            report($exception);
            $run->update(['status' => 'restore_partial', 'error' => $exception->getMessage(), 'finished_at' => now()]);

            return response()->json(['message' => 'Restore stopped: '.$exception->getMessage(), 'run' => $this->runPayload($run->fresh())], 500);
        }
    }

    private function claim(ContentNormalizationRun $run, array $allowed, string $processingStatus = 'processing'): bool
    {
        $updated = ContentNormalizationRun::whereKey($run->getKey())->whereIn('status', $allowed)
            ->update(['status' => $processingStatus, 'updated_at' => now()]);
        if ($updated) {
            $run->status = $processingStatus;
            $run->syncOriginalAttribute('status', $processingStatus);
        }

        return $updated === 1;
    }

    private function questionQuery(int $organizationId, array $filters): Builder
    {
        $query = DB::table('questions')->where('questions.organization_id', $organizationId);
        if (! empty($filters['from_id'])) {
            $query->where('questions.id', '>=', (int) $filters['from_id']);
        }
        if (! empty($filters['to_id'])) {
            $query->where('questions.id', '<=', (int) $filters['to_id']);
        }
        $groups = array_map('intval', $filters['group_ids'] ?? []);
        $categories = array_map('intval', $filters['category_ids'] ?? []);
        $exams = array_map('intval', $filters['exam_ids'] ?? []);
        if ($groups || $categories || $exams) {
            $query->whereExists(function (Builder $paper) use ($organizationId, $groups, $categories, $exams) {
                $paper->selectRaw('1')->from('exam_questions')
                    ->join('exams', 'exams.id', '=', 'exam_questions.exam_id')
                    ->whereColumn('exam_questions.question_id', 'questions.id')
                    ->where('exams.organization_id', $organizationId);
                if ($exams) {
                    $paper->whereIn('exams.id', $exams);
                }
                if ($categories) {
                    $paper->where(function (Builder $category) use ($organizationId, $categories) {
                        $category->whereIn('exams.category_level_1', $categories)
                            ->orWhereIn('exams.category_level_2', $categories)
                            ->orWhereExists(fn (Builder $package) => $package->selectRaw('1')
                                ->from('exam_packages')
                                ->join('packages', 'packages.id', '=', 'exam_packages.package_id')
                                ->whereColumn('exam_packages.exam_id', 'exams.id')
                                ->where('packages.organization_id', $organizationId)
                                ->where(fn (Builder $packageCategory) => $packageCategory
                                    ->whereIn('packages.category_level_1', $categories)
                                    ->orWhereIn('packages.category_level_2', $categories)));
                    });
                }
                if ($groups) {
                    $paper->where(function (Builder $groupScope) use ($organizationId, $groups) {
                        $groupScope->whereExists(fn (Builder $group) => $group->selectRaw('1')->from('exam_groups')
                            ->whereColumn('exam_groups.exam_id', 'exams.id')->whereIn('exam_groups.group_id', $groups))
                            ->orWhereExists(fn (Builder $packageGroup) => $packageGroup->selectRaw('1')
                                ->from('exam_packages')
                                ->join('packages', 'packages.id', '=', 'exam_packages.package_id')
                                ->join('package_groups', 'package_groups.package_id', '=', 'packages.id')
                                ->join('groups', 'groups.id', '=', 'package_groups.group_id')
                                ->whereColumn('exam_packages.exam_id', 'exams.id')
                                ->where('packages.organization_id', $organizationId)
                                ->where('groups.organization_id', $organizationId)
                                ->whereIn('package_groups.group_id', $groups));
                    });
                }
            });
        }

        return $query;
    }

    private function validatePaperFilters(Request $request, int $tenantId): array
    {
        return $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'group_id' => ['nullable', Rule::exists('groups', 'id')->where('organization_id', $tenantId)],
            'category_id' => ['nullable', Rule::exists('category', 'id')->where('organization_id', $tenantId)],
            'package_id' => ['nullable', Rule::exists('packages', 'id')->where('organization_id', $tenantId)],
        ]);
    }

    private function filteredExamQuery(int $tenantId, array $filters)
    {
        $query = Exam::where('organization_id', $tenantId)
            ->where(fn ($exam) => $exam->where('is_student_practice', false)->orWhereNull('is_student_practice'))
            ->whereHas('questions');
        if (! empty($filters['group_id'])) {
            $groupId = (int) $filters['group_id'];
            $query->where(fn ($exam) => $exam
                ->whereHas('groups', fn ($group) => $group->whereKey($groupId))
                ->orWhereHas('packages.groups', fn ($group) => $group->whereKey($groupId)));
        }
        if (! empty($filters['category_id'])) {
            $categoryId = (int) $filters['category_id'];
            $query->where(fn ($exam) => $exam
                ->where('category_level_1', $categoryId)
                ->orWhere('category_level_2', $categoryId)
                ->orWhereHas('packages', fn ($package) => $package
                    ->where('category_level_1', $categoryId)
                    ->orWhere('category_level_2', $categoryId)));
        }
        if (! empty($filters['package_id'])) {
            $query->whereHas('packages', fn ($package) => $package->whereKey((int) $filters['package_id']));
        }
        if ($term = trim((string) ($filters['q'] ?? ''))) {
            $query->where('name', 'like', '%'.addcslashes($term, '%_\\').'%');
        }

        return $query;
    }

    private function authorizeRun(ContentNormalizationRun $run): void
    {
        abort_unless((int) $run->organization_id === (int) Tenant::id(), 404);
    }

    private function readCommandResult(string $output): array
    {
        preg_match('/(?:Applied run ID:|run)\s+([0-9a-f-]{36})/i', $output, $runMatch);
        preg_match('/Report:\s+([^\r\n]+)/', $output, $reportMatch);
        $stats = self::EMPTY_STATS;
        $path = isset($reportMatch[1]) ? trim($reportMatch[1]) : null;
        if ($path && is_file($path)) {
            $handle = fopen($path, 'rb');
            while ($handle && ($line = fgets($handle)) !== false) {
                $row = json_decode($line, true);
                if (! is_array($row)) {
                    continue;
                }
                $stats['records']++;
                $status = $row['status'] ?? 'clean';
                if (array_key_exists($status, $stats)) {
                    $stats[$status]++;
                }
                if (! empty($row['changed_fields'])) {
                    $stats['changed_records']++;
                }
            }
            if ($handle) {
                fclose($handle);
            }
            File::delete($path);
        }
        if (! empty($runMatch[1])) {
            $stats['applied_records'] = DB::table('content_normalization_backups')->where('run_id', $runMatch[1])->count();
            $stats['math_expressions'] = $this->metricFromOutput($output, 'math_expressions');
            $stats['skipped_concurrent'] = $this->metricFromOutput($output, 'skipped_concurrent');
        }

        return [$stats, $runMatch[1] ?? null];
    }

    private function metricFromOutput(string $output, string $metric): int
    {
        return preg_match('/\|\s*'.preg_quote($metric, '/').'\s*\|\s*(\d+)\s*\|/', $output, $match) ? (int) $match[1] : 0;
    }

    private function mergeStats(array $current, array $step): array
    {
        foreach (self::EMPTY_STATS as $key => $zero) {
            $current[$key] = (int) ($current[$key] ?? 0) + (int) ($step[$key] ?? 0);
        }

        return $current;
    }

    private function runPayload(ContentNormalizationRun $run): array
    {
        return [
            'id' => $run->id,
            'status' => $run->status,
            'mode' => $run->mode,
            'total_questions' => $run->total_questions,
            'processed_questions' => $run->processed_questions,
            'progress' => $run->total_questions ? (int) floor($run->processed_questions * 100 / $run->total_questions) : 0,
            'stats' => $run->stats ?: self::EMPTY_STATS,
            'restore_exam_ids' => array_map('intval', $run->restore_exam_ids ?? []),
            'restored_exam_ids' => array_map('intval', $run->restored_exam_ids ?? []),
            'restore_skipped' => $run->restore_skipped,
            'error' => $run->error,
        ];
    }
}

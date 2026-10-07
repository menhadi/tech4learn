<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Exam;
use App\Models\Group;
use App\Models\Package;
use App\Models\Question;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class QuestionExamAssignmentController extends Controller
{
    public function index(Request $request, Question $question)
    {
        $this->ensureTenantOwns($question);
        $tenantId = (int) \App\Support\Tenant::id();
        $assignedIds = $this->assignedExamIds($question, $tenantId);
        $filters = $this->filters($request);
        $assignment = in_array($request->input('assignment'), ['assigned', 'unassigned'], true)
            ? $request->input('assignment')
            : 'all';

        $groups = Group::query()
            ->where('organization_id', $tenantId)
            ->where(function (Builder $query) use ($tenantId) {
                $query->whereHas('exams', fn (Builder $exams) => $exams->where('exams.organization_id', $tenantId))
                    ->orWhereHas('packages.exams', fn (Builder $exams) => $exams->where('exams.organization_id', $tenantId));
            })
            ->orderBy('group_name')
            ->get(['id', 'group_name']);

        $categoryIdsForGroup = $filters['group_id']
            ? $this->categoryIdsForGroup($tenantId, $filters['group_id'])
            : collect();
        $categories = Category::query()
            ->where('organization_id', $tenantId)
            ->whereNull('parent_id')
            ->where('status', 1)
            ->when($filters['group_id'], fn (Builder $query) => $query->whereIn('id', $categoryIdsForGroup))
            ->orderBy('title')
            ->get(['id', 'title']);
        $subcategories = subcategories_enabled() ? Category::query()
            ->where('organization_id', $tenantId)
            ->whereNotNull('parent_id')
            ->where('status', 1)
            ->when($filters['category_id'], fn (Builder $query) => $query->where('parent_id', $filters['category_id']))
            ->when($filters['group_id'], fn (Builder $query) => $query->whereIn('id', $categoryIdsForGroup))
            ->orderBy('title')
            ->get(['id', 'parent_id', 'title']) : collect();

        $packages = Package::query()
            ->where('organization_id', $tenantId);
        $this->applyPackageFilters($packages, $filters);
        $packages = $packages->orderBy('name')->get(['id', 'name']);

        $exams = Exam::query()
            ->where('organization_id', $tenantId)
            ->when(Schema::hasColumn('exams', 'is_student_practice'), fn (Builder $query) => $query->where('is_student_practice', false));
        $this->applyExamFilters($exams, $filters);
        $exams
            ->when($assignment === 'assigned', fn (Builder $query) => $query->whereIn('exams.id', $assignedIds))
            ->when($assignment === 'unassigned', fn (Builder $query) => $query->whereNotIn('exams.id', $assignedIds))
            ->when($request->filled('search'), fn (Builder $query) => $query->where('name', 'like', '%'.$request->input('search').'%'))
            ->withCount(['questions', 'results'])
            ->orderBy('name');
        $exams = $exams->paginate(30);

        $question->loadMissing([
            'subject:id,subject_name',
            'topic:id,name',
            'qtype:id,question_type',
            'diff:id,diff_level',
        ]);

        return response()->json([
            'question' => [
                'id' => (int) $question->id,
                'code' => $question->question_code ?: 'Question #'.$question->id,
                'preview' => Str::of(strip_tags((string) $question->question))->squish()->limit(240)->toString(),
                'subject' => $question->subject?->subject_name,
                'topic' => $question->topic?->name,
                'type' => $question->qtype?->question_type,
                'difficulty' => $question->diff?->diff_level,
                'edit_url' => user_can_route_action('questions.edit', 'edit') ? route('questions.edit', $question) : null,
            ],
            'assigned_exam_ids' => $assignedIds,
            'can_manage' => $this->canManage(),
            'filters' => [
                'groups' => $groups,
                'categories' => $categories,
                'subcategories' => $subcategories,
                'packages' => $packages,
            ],
            'exams' => [
                'data' => $exams->getCollection()->map(fn (Exam $exam) => [
                    'id' => (int) $exam->id,
                    'name' => $exam->name,
                    'status' => $exam->status,
                    'question_count' => (int) $exam->questions_count,
                    'results_count' => (int) $exam->results_count,
                    'assigned' => $assignedIds->contains((int) $exam->id),
                    'view_url' => route('exams.viewQuestions', $exam),
                ])->values(),
                'current_page' => $exams->currentPage(),
                'last_page' => $exams->lastPage(),
                'total' => $exams->total(),
            ],
        ]);
    }

    public function update(Request $request, Question $question)
    {
        $this->ensureTenantOwns($question);
        abort_unless($this->canManage(), 403);

        $validated = $request->validate([
            'exam_ids' => ['present', 'array', 'max:5000'],
            'exam_ids.*' => ['integer', 'distinct'],
        ]);
        $tenantId = (int) \App\Support\Tenant::id();
        $requestedIds = collect($validated['exam_ids'])
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values();
        $ownedExams = Exam::query()
            ->where('organization_id', $tenantId)
            ->when(Schema::hasColumn('exams', 'is_student_practice'), fn (Builder $query) => $query->where('is_student_practice', false))
            ->whereIn('id', $requestedIds)
            ->get();
        abort_unless($ownedExams->count() === $requestedIds->count(), 422, 'One or more selected exams are invalid.');

        $currentIds = $this->assignedExamIds($question, $tenantId);
        $addIds = $requestedIds->diff($currentIds)->values();
        $removeIds = $currentIds->diff($requestedIds)->values();
        $question->loadMissing('questionSection');

        DB::transaction(function () use ($question, $ownedExams, $addIds, $removeIds) {
            if ($removeIds->isNotEmpty()) {
                $question->exams()->detach($removeIds->all());
            }

            foreach ($ownedExams->whereIn('id', $addIds) as $exam) {
                $section = $question->questionSection
                    ? $exam->sections()->firstOrCreate(
                        ['question_section_id' => $question->questionSection->id],
                        [
                            'name' => $question->questionSection->name,
                            'display_order' => $question->questionSection->display_order,
                            'duration' => null,
                        ]
                    )
                    : null;
                $question->exams()->syncWithoutDetaching([
                    $exam->id => ['exam_section_id' => $section?->id],
                ]);
            }
        });

        $addIds->merge($removeIds)->each(fn ($examId) => Cache::forget('exam_'.$examId));
        audit_log('question.exam_assignments.updated', $question, [
            'added_exam_ids' => $addIds->all(),
            'removed_exam_ids' => $removeIds->all(),
        ]);

        return response()->json([
            'success' => true,
            'assigned_exam_ids' => $requestedIds,
            'assigned_count' => $requestedIds->count(),
            'added_count' => $addIds->count(),
            'removed_count' => $removeIds->count(),
            'message' => $addIds->count().' exam assignment(s) added and '.$removeIds->count().' removed.',
        ]);
    }

    private function assignedExamIds(Question $question, int $tenantId)
    {
        return Exam::query()
            ->where('organization_id', $tenantId)
            ->whereHas('questions', fn (Builder $query) => $query->where('questions.id', $question->id))
            ->pluck('exams.id')
            ->map(fn ($id) => (int) $id)
            ->values();
    }

    private function filters(Request $request): array
    {
        return [
            'group_id' => $request->integer('group_id') ?: null,
            'category_id' => $request->integer('category_id') ?: null,
            'subcategory_id' => subcategories_enabled() ? ($request->integer('subcategory_id') ?: null) : null,
            'package_id' => $request->integer('package_id') ?: null,
        ];
    }

    private function applyExamFilters(Builder $query, array $filters): void
    {
        $query
            ->when($filters['group_id'], function (Builder $query, int $groupId) {
                $query->where(function (Builder $scope) use ($groupId) {
                    $scope->whereHas('groups', fn (Builder $groups) => $groups->where('groups.id', $groupId))
                        ->orWhereHas('packages.groups', fn (Builder $groups) => $groups->where('groups.id', $groupId));
                });
            })
            ->when($filters['category_id'], function (Builder $query, int $categoryId) {
                $query->where(function (Builder $scope) use ($categoryId) {
                    $scope->where('category_level_1', $categoryId)
                        ->orWhereHas('packages', fn (Builder $packages) => $packages->where('category_level_1', $categoryId));
                });
            })
            ->when($filters['subcategory_id'], function (Builder $query, int $subcategoryId) {
                $query->where(function (Builder $scope) use ($subcategoryId) {
                    $scope->where('category_level_2', $subcategoryId)
                        ->orWhereHas('packages', fn (Builder $packages) => $packages->where('category_level_2', $subcategoryId));
                });
            })
            ->when($filters['package_id'], fn (Builder $query, int $packageId) => $query->whereHas('packages', fn (Builder $packages) => $packages->where('packages.id', $packageId)));
    }

    private function applyPackageFilters(Builder $query, array $filters): void
    {
        $query
            ->when($filters['group_id'], function (Builder $query, int $groupId) {
                $query->where(function (Builder $scope) use ($groupId) {
                    $scope->whereHas('groups', fn (Builder $groups) => $groups->where('groups.id', $groupId))
                        ->orWhereHas('exams.groups', fn (Builder $groups) => $groups->where('groups.id', $groupId));
                });
            })
            ->when($filters['category_id'], function (Builder $query, int $categoryId) {
                $query->where(function (Builder $scope) use ($categoryId) {
                    $scope->where('category_level_1', $categoryId)
                        ->orWhereHas('exams', fn (Builder $exams) => $exams->where('category_level_1', $categoryId));
                });
            })
            ->when($filters['subcategory_id'], function (Builder $query, int $subcategoryId) {
                $query->where(function (Builder $scope) use ($subcategoryId) {
                    $scope->where('category_level_2', $subcategoryId)
                        ->orWhereHas('exams', fn (Builder $exams) => $exams->where('category_level_2', $subcategoryId));
                });
            });
    }

    private function categoryIdsForGroup(int $tenantId, int $groupId)
    {
        $examCategoryIds = Exam::query()
            ->where('organization_id', $tenantId)
            ->where(function (Builder $query) use ($groupId) {
                $query->whereHas('groups', fn (Builder $groups) => $groups->where('groups.id', $groupId))
                    ->orWhereHas('packages.groups', fn (Builder $groups) => $groups->where('groups.id', $groupId));
            })
            ->get(['category_level_1', 'category_level_2'])
            ->flatMap(fn (Exam $exam) => [$exam->category_level_1, $exam->category_level_2]);
        $packageCategoryIds = Package::query()
            ->where('organization_id', $tenantId)
            ->where(function (Builder $query) use ($groupId) {
                $query->whereHas('groups', fn (Builder $groups) => $groups->where('groups.id', $groupId))
                    ->orWhereHas('exams.groups', fn (Builder $groups) => $groups->where('groups.id', $groupId));
            })
            ->get(['category_level_1', 'category_level_2'])
            ->flatMap(fn (Package $package) => [$package->category_level_1, $package->category_level_2]);

        return $examCategoryIds->merge($packageCategoryIds)->filter()->unique()->values();
    }

    private function ensureTenantOwns(Question $question): void
    {
        abort_unless((int) $question->organization_id === (int) \App\Support\Tenant::id(), 404);
    }

    private function canManage(): bool
    {
        return user_can_route_action('questions.edit', 'edit')
            || user_can_route_action('exams.edit', 'edit');
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\{Category, Exam, Group, Package};
use App\Support\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ExamBulkEditorController extends Controller
{
    public function index(Request $request)
    {
        $tenant = (int) Tenant::id();
        $groupId = $request->integer('group_id') ?: null;
        $categoryId = $request->integer('category_id') ?: null;
        $subcategoryId = $request->integer('subcategory_id') ?: null;
        $packageId = $request->integer('package_id') ?: null;
        $search = trim((string) $request->input('search'));

        $query = Exam::query()
            ->where('organization_id', $tenant)
            ->with(['groups:id,group_name', 'packages:id,name'])
            ->when($groupId, fn ($q) => $q->whereHas('groups', fn ($g) => $g->whereKey($groupId)))
            ->when($categoryId, function ($q) use ($categoryId) {
                $q->where(function ($scope) use ($categoryId) {
                    $scope->where('category_level_1', $categoryId)
                        ->orWhereHas('packages', fn ($p) => $p->where('category_level_1', $categoryId));
                });
            })
            ->when($subcategoryId, function ($q) use ($subcategoryId) {
                $q->where(function ($scope) use ($subcategoryId) {
                    $scope->where('category_level_2', $subcategoryId)
                        ->orWhereHas('packages', fn ($p) => $p->where('category_level_2', $subcategoryId));
                });
            })
            ->when($packageId, fn ($q) => $q->whereHas('packages', fn ($p) => $p->whereKey($packageId)))
            ->when($search !== '', fn ($q) => $q->where('name', 'like', '%'.$search.'%'))
            ->latest('id');

        $groups = Group::where('organization_id', $tenant)->orderBy('group_name')->get(['id', 'group_name']);
        $categories = Category::where('organization_id', $tenant)->whereNull('parent_id')
            ->when($groupId, fn ($q) => $q->whereHas('groups', fn ($g) => $g->whereKey($groupId)))
            ->orderBy('title')->get(['id', 'title']);
        $subcategories = Category::where('organization_id', $tenant)->whereNotNull('parent_id')
            ->when($groupId, fn ($q) => $q->whereHas('parent.groups', fn ($g) => $g->whereKey($groupId)))
            ->when($categoryId, fn ($q) => $q->where('parent_id', $categoryId))
            ->orderBy('title')->get(['id', 'parent_id', 'title']);
        $packages = Package::where('organization_id', $tenant)
            ->when($groupId, fn ($q) => $q->whereHas('groups', fn ($g) => $g->whereKey($groupId)))
            ->when($categoryId, fn ($q) => $q->where('category_level_1', $categoryId))
            ->when($subcategoryId, fn ($q) => $q->where('category_level_2', $subcategoryId))
            ->orderBy('name')->get(['id', 'name', 'category_level_1', 'category_level_2']);

        return view('exams.bulk-editor', [
            'exams' => $query->paginate(100)->withQueryString(),
            'groups' => $groups,
            'categories' => $categories,
            'subcategories' => $subcategories,
            'packages' => $packages,
            'filters' => compact('groupId', 'categoryId', 'subcategoryId', 'packageId', 'search'),
        ]);
    }

    public function update(Request $request)
    {
        $tenant = (int) Tenant::id();
        $data = $request->validate([
            'selected_exam_ids' => ['required', 'array', 'min:1', 'max:500'],
            'selected_exam_ids.*' => ['integer'],
            'rows' => ['nullable', 'array'],
            'rows.*.name' => ['nullable', 'string', 'max:255'],
            'rows.*.duration' => ['nullable', 'integer', 'min:0', 'max:1440'],
            'rows.*.attempt_count' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'rows.*.passing_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'rows.*.display_order' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'rows.*.status' => ['nullable', Rule::in(['Active', 'Inactive'])],
            'bulk.prefix' => ['nullable', 'string', 'max:100'],
            'bulk.suffix' => ['nullable', 'string', 'max:100'],
            'bulk.duration' => ['nullable', 'integer', 'min:0', 'max:1440'],
            'bulk.attempt_count' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'bulk.passing_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'bulk.display_order' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'bulk.status' => ['nullable', Rule::in(['Active', 'Inactive'])],
            'bulk.group_id' => ['nullable', 'integer'],
            'bulk.category_id' => ['nullable', 'integer'],
            'bulk.subcategory_id' => ['nullable', 'integer'],
            'bulk.package_id' => ['nullable', 'integer'],
        ]);

        $extraFields = $this->extraFields();
        $extraRules = [];
        foreach (array_keys($extraFields) as $field) {
            $extraRules["rows.*.{$field}"] = ['nullable'];
            $extraRules["bulk.{$field}"] = ['nullable'];
        }
        $extra = validator($request->all(), $extraRules)->validate();
        $data = array_replace_recursive($data, $extra);
        $ids = collect($data['selected_exam_ids'])->map(fn ($id) => (int) $id)->unique()->values();
        $exams = Exam::where('organization_id', $tenant)->whereIn('id', $ids)->get()->keyBy('id');
        if ($exams->count() !== $ids->count()) {
            abort(403, 'One or more selected exams do not belong to this organization.');
        }

        $bulk = $data['bulk'] ?? [];
        $this->validateOwnedRelations($tenant, $bulk);
        DB::transaction(function () use ($exams, $data, $bulk, $tenant) {
            foreach ($exams as $exam) {
                $row = $data['rows'][$exam->id] ?? [];
                $name = trim((string) ($row['name'] ?? $exam->name));
                if ($name === '') {
                    $name = $exam->name;
                }
                $name = trim((string) ($bulk['prefix'] ?? '')).$name.trim((string) ($bulk['suffix'] ?? ''));

                $changes = [
                    'name' => $name,
                    'duration' => $this->value($bulk, $row, 'duration', $exam->duration),
                    'attempt_count' => $this->value($bulk, $row, 'attempt_count', $exam->attempt_count),
                    'passing_percentage' => $this->value($bulk, $row, 'passing_percentage', $exam->passing_percentage),
                    'display_order' => $this->value($bulk, $row, 'display_order', $exam->display_order),
                    'status' => $this->value($bulk, $row, 'status', $exam->status),
                    'category_level_1' => ! empty($bulk['category_id']) ? (int) $bulk['category_id'] : $exam->category_level_1,
                    'category_level_2' => ! empty($bulk['subcategory_id']) ? (int) $bulk['subcategory_id'] : $exam->category_level_2,
                ];
                foreach (array_keys($this->extraFields()) as $field) {
                    $changes[$field] = $this->value($bulk, $row, $field, $exam->getAttribute($field));
                }
                $exam->fill($changes)->save();

                $packageIds = ! empty($bulk['package_id']) ? [(int) $bulk['package_id']] : $exam->packages()->pluck('packages.id')->all();
                $manualGroupIds = ! empty($bulk['group_id']) ? [(int) $bulk['group_id']] : $exam->groups()->pluck('groups.id')->all();
                $scope = app(\App\Services\ExamScopeService::class)->resolve(
                    $tenant, $packageIds, $manualGroupIds,
                    ! empty($bulk['category_id']) ? (int) $bulk['category_id'] : ($exam->category_level_1 ? (int) $exam->category_level_1 : null),
                    ! empty($bulk['subcategory_id']) ? (int) $bulk['subcategory_id'] : ($exam->category_level_2 ? (int) $exam->category_level_2 : null),
                );
                app(\App\Services\ExamScopeService::class)->sync($exam, $scope);
            }
        });

        return back()->with('success', $exams->count().' exam(s) updated.');
    }

    private function extraFields(): array
    {
        return [
            'instruction'=>'textarea', 'syllabus'=>'textarea', 'start_date'=>'datetime-local', 'end_date'=>'datetime-local',
            'negative_marking'=>'boolean', 'random_question'=>'boolean', 'result_after_finish'=>'boolean',
            'option_shuffle'=>'boolean', 'allow_answer_change'=>'boolean', 'browser_tolerance'=>'boolean',
            'proctor'=>'boolean', 'calculator_allowed'=>'boolean', 'tolerance_count'=>'number',
            'grouping_mode'=>'text', 'is_subject_timer'=>'boolean', 'timer_mode'=>'text',
            'show_answer_sheet'=>'boolean', 'mode'=>'text', 'instant_result'=>'boolean',
            'multi_language'=>'boolean', 'math_editor'=>'boolean',
        ];
    }
    private function value(array $bulk, array $row, string $key, mixed $fallback): mixed
    {
        if (array_key_exists($key, $bulk) && $bulk[$key] !== null && $bulk[$key] !== '') {
            return $bulk[$key];
        }
        return array_key_exists($key, $row) && $row[$key] !== null && $row[$key] !== '' ? $row[$key] : $fallback;
    }

    private function validateOwnedRelations(int $tenant, array $bulk): void
    {
        foreach ([
            'group_id' => Group::class,
            'category_id' => Category::class,
            'subcategory_id' => Category::class,
            'package_id' => Package::class,
        ] as $field => $model) {
            if (! empty($bulk[$field]) && ! $model::where('organization_id', $tenant)->whereKey($bulk[$field])->exists()) {
                abort(403, 'The selected classification does not belong to this organization.');
            }
        }
    }
}

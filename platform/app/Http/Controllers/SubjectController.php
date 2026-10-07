<?php

namespace App\Http\Controllers;

use App\Models\Subject;
use Illuminate\Http\Request;
use App\Models\Group;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use App\Services\CurriculumTaxonomyService;

class SubjectController extends Controller
{
    private function tenantId(): ?int
    {
        return class_exists(\App\Support\Tenant::class) ? \App\Support\Tenant::id() : null;
    }

    private function tenantGroups()
    {
        return Group::query()->when($this->tenantId(), function ($q, $tenantId) {
            $q->where('organization_id', $tenantId);
        });
    }

    private function ensureSubjectInTenant(Subject $subject): void
    {
        if ($this->tenantId() && (int) $subject->organization_id !== (int) $this->tenantId()) {
            abort(404);
        }
    }

    private function validateTenantGroups(array $groupIds): void
    {
        if (! $this->tenantId()) {
            return;
        }

        $ownedCount = $this->tenantGroups()->whereIn('id', $groupIds)->count();
        if ($ownedCount !== count(array_unique($groupIds))) {
            abort(422, 'Invalid group selected.');
        }
    }

    public function index(Request $request)
    {
        // YAHAN PAR CHANGE KIYA GAYA HAI: orderBy('ordering') ko latest() se badal diya hai
        $query = Subject::with('groups')
            ->when($this->tenantId(), fn ($q, $tenantId) => $q->where('organization_id', $tenantId))
            ->latest();
        
        $groupIds = getUserGroupIds();
        if (!empty($groupIds)) {
            $query->whereHas('groups', function ($q) use ($groupIds) {
                $q->whereIn('group_id', $groupIds);
            });
        }

        if ($request->filled('group')) {
            $selectedGroupId = $request->group;
            $query->whereHas('groups', function ($q) use ($selectedGroupId) {
                $q->where('group_id', $selectedGroupId);
            });
        }

        if ($request->filled('search')) {
            $query->where('subject_name', 'like', '%' . $request->input('search') . '%');
        }

        $perPage = (int) $request->input('per_page', 50);
        $perPage = in_array($perPage, [50, 100, 500], true) ? $perPage : 50;

        $subjects = $query->paginate($perPage)->withQueryString();
        $groups = $this->tenantGroups()->orderBy('group_name')->get();
        return view('subjects.index', compact('subjects', 'groups', 'perPage'));
    }

    public function create()
    {
        return view('subjects.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'subject_name' => ['required', Rule::unique('subjects', 'subject_name')->where(fn ($q) => $q->where('organization_id', $this->tenantId()))],
            'group_ids' => 'required|array',
            'group_ids.*' => 'exists:groups,id',
        ]);
        $this->validateTenantGroups($request->group_ids);

        $subject = new Subject();
        $subject->organization_id = $this->tenantId();
        $subject->subject_name = $request->subject_name;
        // Note: 'ordering' column ab default sorting ke liye use nahi ho raha hai
        $subject->ordering = Subject::max('ordering') + 1;
        $subject->save();

        $subject->groups()->sync($request->group_ids);

        return redirect()->route('subjects.index')->with('success', 'Subject created successfully.');
    }

    public function edit(Subject $subject)
    {
        $this->ensureSubjectInTenant($subject);
        return view('subjects.edit', compact('subject'));
    }

    public function update(Request $request, Subject $subject, CurriculumTaxonomyService $taxonomy)
    {
        $request->validate([
            'subject_name' => ['required', Rule::unique('subjects', 'subject_name')->where(fn ($q) => $q->where('organization_id', $this->tenantId()))->ignore($subject->id)],
            'group_ids' => 'required|array',
            'group_ids.*' => 'exists:groups,id',
        ]);
        $this->ensureSubjectInTenant($subject);
        $this->validateTenantGroups($request->group_ids);

        $subject->subject_name = $request->subject_name;
        $subject->save();

        $existingGroupIds = $subject->groups()->pluck('groups.id');
        $subject->groups()->sync($request->group_ids);
        $taxonomy->cloneTreeForNewGroups($subject, collect($request->group_ids)->diff($existingGroupIds));

        return redirect()->route('subjects.index')->with('success', 'Subject updated successfully.');
    }

    public function destroy(Subject $subject)
    {
        $this->ensureSubjectInTenant($subject);
        $subject->delete();

        return redirect()->route('subjects.index')->with(
            'success',
            'Subject deleted. Questions, exam papers, attempts and results were preserved; their subject/topic/subtopic classification was cleared.'
        );
    }

    public function bulkDestroy(Request $request)
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'distinct'],
        ]);

        $subjects = Subject::query()
            ->whereIn('id', $validated['ids'])
            ->when($this->tenantId(), fn ($query, $tenantId) => $query->where('organization_id', $tenantId));


        $count = $subjects->count();
        DB::transaction(fn () => $subjects->delete());

        return response()->json([
            'deleted' => $count,
            'message' => "{$count} subject(s) deleted. Questions, exam papers, attempts and results were preserved.",
        ]);
    }
}

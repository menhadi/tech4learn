<?php

namespace App\Http\Controllers;

use App\Models\Group;
use App\Models\QuestionSection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class SectionController extends Controller
{
    private function tenantId(): int
    {
        return (int) \App\Support\Tenant::id();
    }

    private function tenantSections()
    {
        return QuestionSection::query()->where('organization_id', $this->tenantId());
    }

    private function validateGroups(array $groupIds): array
    {
        $ids = collect($groupIds)->map(fn ($id) => (int) $id)->filter()->unique()->values()->all();
        abort_unless(Group::where('organization_id', $this->tenantId())->whereIn('id', $ids)->count() === count($ids), 422, 'Invalid group selected.');
        return $ids;
    }

    public function index(Request $request)
    {
        $query = $this->tenantSections()->with('groups')->withCount('questions')
            ->when($request->filled('search'), fn ($q) => $q->where('name', 'like', '%'.$request->search.'%'))
            ->orderByRaw('CASE WHEN display_order = 0 THEN 1 ELSE 0 END')->orderBy('display_order')->orderBy('name');
        $perPage = in_array((int) $request->input('per_page', 50), [50, 100, 500], true) ? (int) $request->input('per_page', 50) : 50;
        $sections = $query->paginate($perPage)->withQueryString();
        $groups = Group::where('organization_id', $this->tenantId())->orderBy('group_name')->get();
        return view('sections.index', compact('sections', 'groups', 'perPage'));
    }

    public function byGroups(Request $request)
    {
        $ids = $this->validateGroups((array) $request->input('group_ids', []));
        $sections = $this->tenantSections()->where('status', true)
            ->whereHas('groups', fn ($q) => $q->whereIn('groups.id', $ids))
            ->orderByRaw('CASE WHEN display_order = 0 THEN 1 ELSE 0 END')->orderBy('display_order')->orderBy('name')
            ->get(['id', 'name', 'display_order']);
        return response()->json($sections);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:191', Rule::unique('question_sections')->where('organization_id', $this->tenantId())],
            'display_order' => 'nullable|integer|min:0', 'status' => 'nullable|boolean',
            'group_ids' => 'required|array|min:1', 'group_ids.*' => 'integer',
        ]);
        $groups = $this->validateGroups($validated['group_ids']);
        $section = QuestionSection::create([
            'organization_id' => $this->tenantId(),
            'name' => $validated['name'], 'display_order' => $validated['display_order'] ?? 0,
            'status' => $request->boolean('status', true),
        ]);
        $section->groups()->sync($groups);
        return back()->with('success', 'Section created successfully.');
    }

    public function update(Request $request, QuestionSection $section)
    {
        abort_unless((int) $section->organization_id === $this->tenantId(), 404);
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:191', Rule::unique('question_sections')->where('organization_id', $this->tenantId())->ignore($section->id)],
            'display_order' => 'nullable|integer|min:0', 'status' => 'nullable|boolean',
            'group_ids' => 'required|array|min:1', 'group_ids.*' => 'integer',
        ]);
        $groups = $this->validateGroups($validated['group_ids']);
        $section->update(['name' => $validated['name'], 'display_order' => $validated['display_order'] ?? 0, 'status' => $request->boolean('status')]);
        $section->groups()->sync($groups);
        return back()->with('success', 'Section updated successfully.');
    }

    public function destroy(QuestionSection $section)
    {
        abort_unless((int) $section->organization_id === $this->tenantId(), 404);
        $section->delete();
        return back()->with('success', 'Section deleted. Existing questions and exam results were preserved.');
    }

    public function bulkDestroy(Request $request)
    {
        $validated = $request->validate(['ids' => 'required|array|min:1', 'ids.*' => 'integer|distinct']);
        $query = $this->tenantSections()->whereIn('id', $validated['ids']);
        $count = $query->count();
        DB::transaction(fn () => $query->delete());
        return response()->json(['deleted' => $count, 'message' => "$count section(s) deleted; questions and results were preserved."]);
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Stopic;
use App\Models\Subject;
use App\Models\Topic;
use App\Models\Group;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class StopicController extends Controller
{
    private function tenantId(): ?int
    {
        return class_exists(\App\Support\Tenant::class) ? \App\Support\Tenant::id() : null;
    }

    private function scopeSubjectTenant($query)
    {
        return $query->when($this->tenantId(), function ($q) {
            $q->where('organization_id', $this->tenantId());
        });
    }

    private function ensureStopicInTenant(Stopic $stopic): void
    {
        if ($this->tenantId() && (int) $stopic->group?->organization_id !== (int) $this->tenantId()) {
            abort(404);
        }
    }

    public function index(Request $request)
    {
        $query = Stopic::with('subject', 'topic', 'group')->withCount('questions')
            ->when($this->tenantId(), function ($q) {
                $q->whereHas('group', fn ($groupQuery) => $groupQuery->where('organization_id', $this->tenantId()));
            });

        $groupIds = getUserGroupIds();
        if (!empty($groupIds)) {
            $query->whereIn('group_id', $groupIds);
        }

        if ($request->filled('group')) {
            $query->where('group_id', $request->group);
        }

        if ($request->filled('subject')) {
            $query->where('subject_id', $request->subject);
        }

        if ($request->filled('topic')) {
            $query->where('topic_id', $request->topic);
        }

        if ($request->has('search') && $request->input('search') != '') {
            $search = $request->input('search');
            $query->where(function($q) use ($search) {
                $q->whereHas('subject', function ($subq) use ($search) {
                    $subq->where('subject_name', 'like', '%' . $search . '%');
                })->orWhereHas('topic', function ($topq) use ($search) {
                    $topq->where('name', 'like', '%' . $search . '%');
                })->orWhere('name', 'like', '%' . $search . '%');
            });
        }

        $perPage = (int) $request->input('per_page', 50);
        $perPage = in_array($perPage, [50, 100, 500], true) ? $perPage : 50;

        $stopics = $query->latest()->paginate($perPage)->withQueryString();
        
        $subjects = $this->scopeSubjectTenant(Subject::query())->orderBy('subject_name')->get();
        
        // ✅ CHANGE: Filter karne ke liye saare topics bhej rahe hain
        $topics = Topic::select('id', 'name', 'subject_id', 'group_id')
            ->when($this->tenantId(), function ($query, $tenantId) {
                $query->whereHas('group', fn ($groupQuery) => $groupQuery->where('organization_id', $tenantId));
            })
            ->orderBy('name')
            ->get();

        $groups = Group::when($this->tenantId(), function ($q, $tenantId) {
                $q->where('organization_id', $tenantId);
            })
            ->orderBy('group_name')
            ->get();

        return view('stopics.index', compact('stopics', 'subjects', 'topics', 'groups', 'perPage'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'group_id' => 'required|exists:groups,id',
            'subject_id' => 'required|exists:subjects,id',
            'topic_id' => 'required|exists:topics,id',
            'name' => ['required', 'string', 'max:255', Rule::unique('stopics', 'name')->where(fn ($query) => $query->where('group_id', $request->group_id)->where('topic_id', $request->topic_id))],
            'display_order' => 'nullable|integer|min:0',
        ]);
        $subject = $this->scopeSubjectTenant(Subject::query())->findOrFail($request->subject_id);
        abort_unless(Group::where('organization_id', $this->tenantId())->whereKey($request->group_id)->exists(), 422, 'Invalid group selected.');
        abort_unless($subject->groups()->whereKey($request->group_id)->exists(), 422, 'The subject is not available in the selected group.');
        Topic::where('subject_id', $request->subject_id)->where('group_id', $request->group_id)->findOrFail($request->topic_id);

        Stopic::create($request->only(['group_id', 'subject_id', 'topic_id', 'name', 'display_order']));

        return redirect()->route('stopics.index')->with('success', 'Sub topic created successfully.');
    }

    public function update(Request $request, Stopic $stopic)
    {
        $request->validate([
            'group_id' => 'required|exists:groups,id',
            'subject_id' => 'required|exists:subjects,id',
            'topic_id' => 'required|exists:topics,id',
            'name' => ['required', 'string', 'max:255', Rule::unique('stopics', 'name')->where(fn ($query) => $query->where('group_id', $request->group_id)->where('topic_id', $request->topic_id))->ignore($stopic->id)],
            'display_order' => 'nullable|integer|min:0',
        ]);
        $this->ensureStopicInTenant($stopic);
        $subject = $this->scopeSubjectTenant(Subject::query())->findOrFail($request->subject_id);
        abort_unless(Group::where('organization_id', $this->tenantId())->whereKey($request->group_id)->exists(), 422, 'Invalid group selected.');
        abort_unless($subject->groups()->whereKey($request->group_id)->exists(), 422, 'The subject is not available in the selected group.');
        Topic::where('subject_id', $request->subject_id)->where('group_id', $request->group_id)->findOrFail($request->topic_id);

        $stopic->update($request->only(['group_id', 'subject_id', 'topic_id', 'name', 'display_order']));

        return redirect()->route('stopics.index')->with('success', 'Sub topic updated successfully.');
    }

    public function destroy(Stopic $stopic)
    {
        $this->ensureStopicInTenant($stopic);
        $stopic->delete();

        return redirect()->route('stopics.index')->with(
            'success',
            'Sub topic deleted. Linked questions, exam papers and results were kept and unassigned from the subtopic.'
        );
    }

    public function bulkDestroy(Request $request)
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['required', 'integer', 'distinct'],
        ]);

        $ids = collect($data['ids'])->map(fn ($id) => (int) $id)->unique()->values();
        $stopicCount = Stopic::query()
            ->whereIn('id', $ids)
            ->when($this->tenantId(), function ($query) {
                $query->whereHas('group', fn ($groupQuery) => $groupQuery->where('organization_id', $this->tenantId()));
            })
            ->count();

        abort_unless($stopicCount === $ids->count(), 404);

        // questions.stopic_id uses ON DELETE SET NULL, so a single delete
        // statement preserves questions, papers and results efficiently.
        Stopic::whereIn('id', $ids)->delete();

        return redirect()->route('stopics.index')->with(
            'success',
            $ids->count().' sub topic(s) deleted. Linked questions, exam papers and results were kept and unassigned.'
        );
    }

    public function getSubjectsByGroup(Request $request)
    {
        $groupId = $request->input('group_id');
        $subjects = Subject::whereIn('id', function ($query) use ($groupId) {
            $query->select('subject_id')
                ->from('subject_groups')
                ->where('group_id', $groupId);
        })
        ->when($this->tenantId(), function ($query, $tenantId) {
            $query->whereHas('groups', function ($groupQuery) use ($tenantId) {
                $groupQuery->where('groups.organization_id', $tenantId);
            });
        })
        ->orderBy('subject_name')
        ->get();

        return response()->json($subjects);
    }

    public function getTopicsBySubject(Request $request)
    {
        $subjectId = $request->input('subject_id', $request->input('topic_id'));

        $topics = Topic::where('subject_id', $subjectId)
            ->when($request->filled('group_id'), fn ($query) => $query->where('group_id', $request->input('group_id')))
            ->when($request->filled('group_id'), function ($query) use ($request) {
                $query->whereHas('subject.groups', function ($groupQuery) use ($request) {
                    $groupQuery->where('groups.id', $request->input('group_id'));
                });
            })
            ->when($this->tenantId(), function ($query, $tenantId) {
                $query->whereHas('group', fn ($groupQuery) => $groupQuery->where('organization_id', $tenantId));
            })
            ->orderBy('name')
            ->get(['id', 'name']);

        return response()->json($topics);
    }
}

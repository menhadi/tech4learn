<?php

namespace App\Http\Controllers;

use App\Models\Topic;
use App\Models\Subject;
use Illuminate\Http\Request;
use App\Models\Group;
use Illuminate\Validation\Rule;

class TopicController extends Controller
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

    private function ensureTopicInTenant(Topic $topic): void
    {
        if ($this->tenantId() && (int) $topic->group?->organization_id !== (int) $this->tenantId()) {
            abort(404);
        }
    }

    public function index(Request $request)
    {
        $query = Topic::with('subject.groups', 'group')->withCount(['stopics', 'questions'])
            ->when($this->tenantId(), function ($q) {
                $q->whereHas('group', fn ($groupQuery) => $groupQuery->where('organization_id', $this->tenantId()));
            });

        $groupIds = getUserGroupIds();
        // if (!empty($groupIds)) {
        //     $query->whereHas('subject.groups', function ($q) use ($groupIds) {
        //         $q->whereIn('group_id', $groupIds);
        //     });
        // }

        if ($request->filled('group')) {
            $query->where('group_id', $request->group);
        }

        if ($request->filled('subject')) {
            if ($request->filled('subject')) {
                $query->where('subject_id', $request->subject);
            }
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->whereHas('subject', function ($subQuery) use ($search) {
                    $subQuery->where('subject_name', 'like', "%{$search}%");
                })
                ->orWhere('name', 'like', "%{$search}%");
            });
        }

        $perPage = (int) $request->input('per_page', 50);
        $perPage = in_array($perPage, [50, 100, 500], true) ? $perPage : 50;

        $topics = $query->latest()->paginate($perPage)->withQueryString();

        $groups = Group::when($this->tenantId(), function ($query, $tenantId) {
                $query->where('organization_id', $tenantId);
            })
            ->orderBy('group_name')
            ->get();
        $subjects = $this->scopeSubjectTenant(Subject::query())->orderBy('subject_name')->get();

        return view('topics.index', compact('topics', 'groups', 'subjects', 'perPage'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'group_id' => 'required|exists:groups,id',
            'subject_id' => 'required|exists:subjects,id',
            'name' => ['required', 'string', 'max:255', Rule::unique('topics', 'name')->where(fn ($query) => $query->where('group_id', $request->group_id)->where('subject_id', $request->subject_id))],
            'display_order' => 'nullable|integer|min:0',
        ]);
        $subject = $this->scopeSubjectTenant(Subject::query())->findOrFail($request->subject_id);
        abort_unless(Group::where('organization_id', $this->tenantId())->whereKey($request->group_id)->exists(), 422, 'Invalid group selected.');
        abort_unless($subject->groups()->whereKey($request->group_id)->exists(), 422, 'The subject is not available in the selected group.');

        Topic::create($request->only(['group_id', 'subject_id', 'name', 'display_order']));

        return redirect()->route('topics.index')->with('success', 'Topic created successfully.');
    }

    public function update(Request $request, Topic $topic)
    {
        $request->validate([
            'group_id' => 'required|exists:groups,id',
            'subject_id' => 'required|exists:subjects,id',
            'name' => ['required', 'string', 'max:255', Rule::unique('topics', 'name')->where(fn ($query) => $query->where('group_id', $request->group_id)->where('subject_id', $request->subject_id))->ignore($topic->id)],
            'display_order' => 'nullable|integer|min:0',
        ]);
        $this->ensureTopicInTenant($topic);
        $subject = $this->scopeSubjectTenant(Subject::query())->findOrFail($request->subject_id);
        abort_unless(Group::where('organization_id', $this->tenantId())->whereKey($request->group_id)->exists(), 422, 'Invalid group selected.');
        abort_unless($subject->groups()->whereKey($request->group_id)->exists(), 422, 'The subject is not available in the selected group.');

        $topic->update($request->only(['group_id', 'subject_id', 'name', 'display_order']));

        return redirect()->route('topics.index')->with('success', 'Topic updated successfully.');
    }

    public function destroy(Topic $topic)
    {
        $this->ensureTopicInTenant($topic);
        $topic->delete();

        return redirect()->route('topics.index')->with(
            'success',
            'Topic deleted. Linked questions, exam papers and results were kept; their topic/subtopic classification was removed.'
        );
    }

    public function bulkDestroy(Request $request)
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['required', 'integer', 'distinct'],
        ]);

        $ids = collect($data['ids'])->map(fn ($id) => (int) $id)->unique()->values();
        $topicCount = Topic::query()
            ->whereIn('id', $ids)
            ->when($this->tenantId(), function ($query) {
                $query->whereHas('group', fn ($groupQuery) => $groupQuery->where('organization_id', $this->tenantId()));
            })
            ->count();

        abort_unless($topicCount === $ids->count(), 404);

        // The questions.topic_id and questions.stopic_id foreign keys use
        // ON DELETE SET NULL. Deleting in one statement is substantially
        // faster for large batches while preserving papers and results.
        Topic::whereIn('id', $ids)->delete();

        return redirect()->route('topics.index')->with(
            'success',
            $ids->count().' topic(s) deleted. Linked questions, exam papers and results were kept; their topic/subtopic classification was removed.'
        );
    }

    public function getSubjectsByGroup(Request $request)
    {
        $groupId = $request->group_id;

        $subjects = Subject::whereHas('groups', function ($query) use ($groupId) {
            $query->where('groups.id', $groupId);
        })
        ->when($this->tenantId(), function ($query, $tenantId) {
            $query->whereHas('groups', function ($groupQuery) use ($tenantId) {
                $groupQuery->where('groups.organization_id', $tenantId);
            });
        })
        ->orderBy('subject_name')
        ->get();

        return response()->json([
            'status' => true,
            'subjects' => $subjects,
        ]);
    }
}

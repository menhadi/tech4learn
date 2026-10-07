<?php

namespace App\Http\Controllers;

use App\Models\QuestionTag;
use App\Support\SaasAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class QuestionTagController extends Controller
{
    public function index(Request $request)
    {
        $tenantId = \App\Support\Tenant::id();

        $query = QuestionTag::query()
            ->withCount('questions')
            ->where(function ($query) use ($tenantId) {
                $query->whereNull('organization_id')
                    ->orWhere('organization_id', $tenantId);
            });

        if ($request->filled('search')) {
            $query->where('name', 'like', '%' . $request->input('search') . '%');
        }

        $tags = $query->orderBy('name')->paginate(50)->withQueryString();
        $canManageDefaultTags = SaasAccess::isPlatformOwner();

        return view('question_tags.index', compact('tags', 'canManageDefaultTags'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:80',
            'status' => 'required|boolean',
        ]);

        $tenantId = SaasAccess::isPlatformOwner() ? null : \App\Support\Tenant::id();

        QuestionTag::create([
            'organization_id' => $tenantId,
            'name' => $data['name'],
            'slug' => $this->uniqueSlug($data['name'], $tenantId),
            'status' => (bool) $data['status'],
        ]);

        return redirect()->route('question-tags.index')->with('success', 'Question tag created successfully.');
    }

    public function update(Request $request, QuestionTag $questionTag)
    {
        $this->ensureEditable($questionTag);

        $data = $request->validate([
            'name' => 'required|string|max:80',
            'status' => 'required|boolean',
        ]);

        $questionTag->name = $data['name'];
        $questionTag->slug = $this->uniqueSlug($data['name'], $questionTag->organization_id, $questionTag->id);
        $questionTag->status = (bool) $data['status'];
        $questionTag->save();

        return redirect()->route('question-tags.index')->with('success', 'Question tag updated successfully.');
    }

    public function destroy(QuestionTag $questionTag)
    {
        $this->ensureEditable($questionTag);

        $questionTag->questions()->detach();
        $questionTag->delete();

        return redirect()->route('question-tags.index')->with('success', 'Question tag deleted successfully.');
    }

    private function ensureEditable(QuestionTag $questionTag): void
    {
        if ($questionTag->organization_id === null && ! SaasAccess::isPlatformOwner()) {
            abort(403, 'Default platform tags cannot be edited from here.');
        }

        if ($questionTag->organization_id === null) {
            return;
        }

        if ((int) $questionTag->organization_id !== (int) \App\Support\Tenant::id()) {
            abort(404);
        }
    }

    private function uniqueSlug(string $name, ?int $tenantId, ?int $ignoreId = null): string
    {
        $baseSlug = Str::slug($name) ?: 'tag';
        $slug = $baseSlug;
        $counter = 2;

        while (QuestionTag::query()
            ->where('slug', $slug)
            ->where('organization_id', $tenantId)
            ->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))
            ->exists()) {
            $slug = $baseSlug . '-' . $counter++;
        }

        return $slug;
    }
}

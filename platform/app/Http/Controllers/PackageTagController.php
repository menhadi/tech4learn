<?php

namespace App\Http\Controllers;

use App\Models\PackageTag;
use App\Support\SaasAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PackageTagController extends Controller
{
    public function index(Request $request)
    {
        $tenantId = \App\Support\Tenant::id();

        $query = PackageTag::query()
            ->withCount('packages')
            ->where(function ($query) use ($tenantId) {
                $query->whereNull('organization_id')
                    ->orWhere('organization_id', $tenantId);
            });

        if ($request->filled('search')) {
            $query->where('name', 'like', '%' . $request->input('search') . '%');
        }

        $tags = $query->orderBy('name')->paginate(50)->withQueryString();

        $canManageDefaultTags = SaasAccess::isPlatformOwner();

        return view('package_tags.index', compact('tags', 'canManageDefaultTags'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:80',
            'status' => 'required|boolean',
        ]);

        $tenantId = SaasAccess::isPlatformOwner() ? null : \App\Support\Tenant::id();
        $slug = $this->uniqueSlug($data['name'], $tenantId);

        PackageTag::create([
            'organization_id' => $tenantId,
            'name' => $data['name'],
            'slug' => $slug,
            'status' => (bool) $data['status'],
        ]);

        return redirect()->route('package-tags.index')->with('success', 'Package tag created successfully.');
    }

    public function update(Request $request, PackageTag $packageTag)
    {
        $this->ensureEditable($packageTag);

        $data = $request->validate([
            'name' => 'required|string|max:80',
            'status' => 'required|boolean',
        ]);

        $packageTag->name = $data['name'];
        $packageTag->slug = $this->uniqueSlug($data['name'], $packageTag->organization_id, $packageTag->id);
        $packageTag->status = (bool) $data['status'];
        $packageTag->save();

        return redirect()->route('package-tags.index')->with('success', 'Package tag updated successfully.');
    }

    public function destroy(PackageTag $packageTag)
    {
        $this->ensureEditable($packageTag);

        $packageTag->packages()->detach();
        $packageTag->delete();

        return redirect()->route('package-tags.index')->with('success', 'Package tag deleted successfully.');
    }

    private function ensureEditable(PackageTag $packageTag): void
    {
        if ($packageTag->organization_id === null && ! SaasAccess::isPlatformOwner()) {
            abort(403, 'Default platform tags cannot be edited from here.');
        }

        if ($packageTag->organization_id === null) {
            return;
        }

        if ((int) $packageTag->organization_id !== (int) \App\Support\Tenant::id()) {
            abort(404);
        }
    }

    private function uniqueSlug(string $name, ?int $tenantId, ?int $ignoreId = null): string
    {
        $baseSlug = Str::slug($name) ?: 'tag';
        $slug = $baseSlug;
        $counter = 2;

        while (PackageTag::query()
            ->where('slug', $slug)
            ->where('organization_id', $tenantId)
            ->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))
            ->exists()) {
            $slug = $baseSlug . '-' . $counter++;
        }

        return $slug;
    }
}

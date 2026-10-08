<?php
namespace App\Http\Controllers;

use App\Models\Group;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth; // ✅ Added Auth Facade

class GroupController extends Controller
{
    private function ensureTenantOwns($model): void
    {
        if ((int) ($model->organization_id ?? 0) !== (int) \App\Support\Tenant::id()) {
            abort(404);
        }
    }

    public function index(Request $request)
    {
        $query = Group::query()->where('organization_id', \App\Support\Tenant::id());

        // --- 🔒 SECURITY LOGIC START ---
        // Check karo kaun login hai
        $currentUser = Auth::user();

        // Agar user login hai aur wo "Super Admin" (0) nahi hai
        if ($currentUser && $currentUser->ugroup_id != 0) {
            
            // Helper function se user ke assigned groups nikalo
            // (Ye wahi helper hai jo aapne UserController me use kiya hai)
            $assignedGroupIds = getUserGroupIds(); 

            if (!empty($assignedGroupIds)) {
                // Sirf wahi groups dikhao jo user ko assigned hain
                $query->whereIn('id', $assignedGroupIds);
            } else {
                // Agar Staff hai par koi group assign nahi hai, toh kuch mat dikhao (Safety)
                $query->where('id', 0);
            }
        }
        // --- 🔒 SECURITY LOGIC END ---

        if ($request->has('search')) {
            $query->where('group_name', 'like', '%' . $request->input('search') . '%');
        }

        $perPage = (int) $request->input('per_page', 50);
        $perPage = in_array($perPage, [50, 100, 500], true) ? $perPage : 50;

        $groups = $query
            ->orderByRaw('display_order IS NULL')
            ->orderBy('display_order')
            ->orderBy('group_name')
            ->paginate($perPage)
            ->withQueryString();
        return view('groups.index', compact('groups', 'perPage'));
    }

    public function store(Request $request)
    {
        try {
            $request->validate([
                'group_name' => 'required|unique:groups,group_name',
                'display_order' => 'nullable|integer|min:0',
            ]);

            $data = $request->all();
            $data['organization_id'] = \App\Support\Tenant::id();
            $data['display_order'] = $data['display_order'] ?? 0;
            $this->setGroupSlug($data);
            
            Group::create($data);
            return redirect()->route('groups.index')->with('success', 'Group created successfully.');
        } catch (ValidationException $e) {
            return redirect()->route('groups.index')->withErrors($e->validator)->withInput();
        } catch (\Exception $e) {
            return redirect()->route('groups.index')->with('error', 'Failed to create group.');
        }
    }

    public function update(Request $request, Group $group)
    {
        $this->ensureTenantOwns($group);
        try {
            $request->validate([
                'group_name' => 'required|unique:groups,group_name,' . $group->id,
                'display_order' => 'nullable|integer|min:0',
            ]);

            $data = $request->all();
            $data['display_order'] = $data['display_order'] ?? 0;
            $this->setGroupSlug($data, $group->id);

            $group->update($data);
            return redirect()->route('groups.index')->with('success', 'Group updated successfully.');
        } catch (ValidationException $e) {
            return redirect()->route('groups.index')->withErrors($e->errors())->withInput();
        } catch (\Exception $e) {
            return redirect()->route('groups.index')->with('error', 'Failed to update group.');
        }
    }

    public function destroy($id)
    {
        try {
            $group = Group::findOrFail($id);

            $this->ensureTenantOwns($group);

            if (!$group) {
                return redirect()->route('groups.index')->with('error', 'Group not found.');
            }

            // Check karo ki ye group kahin use toh nahi ho raha
            $isUsed = $group->examGroups()->exists() ||
                $group->questionGroups()->exists() ||
                $group->studentGroups()->exists() ||
                $group->subjectGroups()->exists() ||
                $group->userGroups()->exists() ||
                $group->packageGroup()->exists() ||
                $group->categories()->exists();

            if ($isUsed) {
                return redirect()->route('groups.index')->with('error', 'Group cannot be deleted because it is used in other records.');
            }

            $group->delete();
            return redirect()->route('groups.index')->with('success', 'Group deleted successfully.');
        } catch (\Exception $e) {
            return redirect()->route('groups.index')->with('error', 'Failed to delete group.');
        }
    }

    private function setGroupSlug(array &$data, ?int $ignoreId = null): void
    {
        if (! Schema::hasColumn('groups', 'slug')) {
            unset($data['slug']);

            return;
        }

        $baseSlug = Str::slug($this->plainText($data['group_name'] ?? '')) ?: 'group';
        $slug = $baseSlug;
        $counter = 2;

        while (Group::where('slug', $slug)
            ->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))
            ->exists()) {
            $slug = $baseSlug . '-' . $counter++;
        }

        $data['slug'] = $slug;
    }

    private function plainText($value): string
    {
        if (is_array($value)) {
            return (string) ($value['en'] ?? reset($value));
        }

        if (is_string($value)) {
            $decoded = json_decode($value, true);

            if (is_array($decoded)) {
                return (string) ($decoded['en'] ?? reset($decoded));
            }
        }

        return (string) $value;
    }
}

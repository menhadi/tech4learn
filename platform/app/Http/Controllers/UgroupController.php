<?php
namespace App\Http\Controllers;

use App\Models\Ugroup;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use App\Models\Page;
use App\Models\PageRights;
use App\Support\SaasAccess;
use Illuminate\Support\Facades\Schema;

class UgroupController extends Controller
{
    private function currentTenantId(): ?int
    {
        return SaasAccess::organization()?->id;
    }

    private function tenantUgroupQuery()
    {
        return Ugroup::query()->when($this->currentTenantId() && Schema::hasColumn('ugroups', 'organization_id'), function ($query) {
            $query->where('organization_id', $this->currentTenantId());
        });
    }

    private function tenantUgroup(int $id): Ugroup
    {
        return $this->tenantUgroupQuery()->findOrFail($id);
    }

    public function index(Request $request)
    {
        $query = $this->tenantUgroupQuery();

        if ($request->has('search')) {
            $query->where('ugroup_name', 'like', '%' . $request->input('search') . '%');
        }

        $ugroups = $query->paginate(10);
        $pages = Page::all(); // Fetch all pages

        return view('ugroups.index', compact('ugroups', 'pages'));
    }

    public function store(Request $request)
    {
        try {
            $request->validate([
                'ugroup_name' => ['required', 'string', 'max:255'],
            ]);

            if ($this->tenantUgroupQuery()->where('ugroup_name', trim((string) $request->ugroup_name))->exists()) {
                throw ValidationException::withMessages(['ugroup_name' => 'A permission role with this name already exists.']);
            }

            Ugroup::create([
                'organization_id' => $this->currentTenantId(),
                'ugroup_name' => trim((string) $request->ugroup_name),
            ]);
            return redirect()->route('ugroups.index')->with('success', 'Permission role created successfully.');
        } catch (ValidationException $e) {
            return redirect()->route('ugroups.index')->withErrors($e->validator)->withInput();
        } catch (\Exception $e) {
            return redirect()->route('ugroups.index')->with('error', 'Failed to create permission role.');
        }
    }

    public function update(Request $request, Ugroup $ugroup)
    {
        try {
            $ugroup = $this->tenantUgroup($ugroup->id);

            $request->validate([
                'ugroup_name' => ['required', 'string', 'max:255'],
            ]);

            if ($this->tenantUgroupQuery()->where('id', '!=', $ugroup->id)->where('ugroup_name', trim((string) $request->ugroup_name))->exists()) {
                throw ValidationException::withMessages(['ugroup_name' => 'A permission role with this name already exists.']);
            }

            $ugroup->update(['ugroup_name' => trim((string) $request->ugroup_name)]);
            return redirect()->route('ugroups.index')->with('success', 'Permission role updated successfully.');
        } catch (ValidationException $e) {
            return redirect()->route('ugroups.index')->withErrors($e->validator)->withInput();
        } catch (\Exception $e) {
            return redirect()->route('ugroups.index')->with('error', 'Failed to update permission role.');
        }
    }

    public function destroy($id)
    {
        try {
            $ugroup = $this->tenantUgroup($id);
            if (User::where('ugroup_id', $ugroup->id)->exists()) {
                return redirect()->route('ugroups.index')->with('error', 'This permission role is assigned to one or more users. Reassign those users before deleting it.');
            }

            $ugroup->delete();
            return redirect()->route('ugroups.index')->with('success', 'Permission role deleted successfully.');
        } catch (\Exception $e) {
            return redirect()->route('ugroups.index')->with('error', 'Failed to delete permission role.');
        }
    }

    public function getPageRights($ugroup_id)
    {
        $ugroup = $this->tenantUgroup((int) $ugroup_id);
        $rights = PageRights::where('ugroup_id', $ugroup->id)
            ->get(['page_id', 'view_right', 'add_right', 'edit_right', 'delete_right']);

        return response()->json($rights->keyBy('page_id'));
    }

    public function storePageRights(Request $request)
    {
        try {
            $ugroup_id = $request->input('ugroup_id');
            $page_ids = $request->input('pages', []);
            
            // ✅ FIX: Rights ka data bhi uthaya (checkboxes se)
            // Frontend me input ka name kuch aisa hona chahiye: name="rights[page_id][view_right]"
            $rights_data = $request->input('rights', []); 

            // Validate that ugroup exists
            $ugroup = $this->tenantUgroup((int) $ugroup_id);

            // Delete existing rights
            PageRights::where('ugroup_id', $ugroup->id)->delete();

            // Save new rights
            foreach ($page_ids as $page_id) {
                
                // Check karo ki is page ke liye specific rights aaye hain ya nahi
                $current_rights = isset($rights_data[$page_id]) ? $rights_data[$page_id] : [];

                PageRights::create([
                    'ugroup_id' => $ugroup->id,
                    'page_id' => $page_id,
                    
                    // ✅ FIXED: Ab ye dynamic values lega (0 ya 1)
                    'view_right' => isset($current_rights['view_right']) ? 1 : 0,
                    'add_right' => isset($current_rights['add_right']) ? 1 : 0,
                    'edit_right' => isset($current_rights['edit_right']) ? 1 : 0,
                    'delete_right' => isset($current_rights['delete_right']) ? 1 : 0,
                    
                    // Agar aapke paas 6 rights hain (jaise print ya export), to unhe yahan neeche add kar dein:
                    // 'print_right' => isset($current_rights['print_right']) ? 1 : 0,
                    // 'export_right' => isset($current_rights['export_right']) ? 1 : 0,
                ]);
            }

            return redirect()->route('ugroups.index')->with('success', 'Rights assigned successfully.');
        } catch (\Exception $e) {
            // Error logging add kiya taaki pata chale agar kuch fat'ta hai
            \Log::error("Assign Rights Error: " . $e->getMessage());
            return redirect()->route('ugroups.index')->with('error', 'Failed to assign rights.');
        }
    }
}

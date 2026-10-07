<?php
namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use App\Models\Ugroup;
use App\Models\Group;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Support\SaasAccess;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UserController extends Controller
{

    private function currentTenantId(): ?int
    {
        return class_exists(\App\Support\Tenant::class)
            ? (\App\Support\Tenant::hostId(request()->getHost()) ?: \App\Support\Tenant::id())
            : null;
    }

    private function ensureUserBelongsToCurrentTenant(User $user): void
    {
        $tenantId = $this->currentTenantId();

        $belongs = DB::table('organization_users')
            ->where('organization_id', $tenantId)
            ->where('user_id', $user->id)
            ->where('status', 1)
            ->exists();

        if (! $belongs) {
            abort(404);
        }
    }

    private function applyTenantUserScope($query)
    {
        $tenantId = $this->currentTenantId();

        return $query->whereIn('id', DB::table('organization_users')
            ->where('organization_id', $tenantId)
            ->where('status', 1)
            ->select('user_id'));
    }

    private function tenantUgroupQuery()
    {
        $query = Ugroup::query();
        $tenantId = $this->currentTenantId();

        if ($tenantId) {
            $query->where('organization_id', $tenantId);
        }

        return $query;
    }

    private function ensureUgroupBelongsToCurrentTenant(int $ugroupId): void
    {
        if (! $this->tenantUgroupQuery()->where('id', $ugroupId)->exists()) {
            throw ValidationException::withMessages([
                'ugroup_id' => 'The selected role does not belong to this organization.',
            ]);
        }
    }

    private function linkUserToCurrentTenant(User $user): void
    {
        $tenantId = $this->currentTenantId();

        if (! $tenantId) {
            return;
        }

        DB::table('organization_users')->updateOrInsert(
            [
                'organization_id' => $tenantId,
                'user_id' => $user->id,
            ],
            [
                'role' => 'staff',
                'status' => 1,
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );
    }

    private function resolvedUsername(Request $request, ?User $user = null): string
    {
        if ($request->filled('username')) {
            return trim((string) $request->username);
        }

        $emailBase = Str::before((string) $request->email, '@');
        $base = Str::slug($emailBase) ?: Str::slug((string) $request->name) ?: 'admin-user';
        $username = $base;
        $suffix = 2;

        while (User::where('username', $username)
            ->when($user, fn ($query) => $query->whereKeyNot($user->id))
            ->exists()) {
            $username = $base . '-' . $suffix++;
        }

        return $username;
    }
    /**
     * Display a listing of the resource.
     * ✅ UPDATED: Added Strict Group Filtering Logic
     */
    public function index(Request $request)
    {
        $query = $this->applyTenantUserScope(User::query());
        // Search Logic (Existing)
        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', '%' . $search . '%')
                  ->orWhere('username', 'like', '%' . $search . '%')
                  ->orWhere('email', 'like', '%' . $search . '%')
                  ->orWhere('mobile', 'like', '%' . $search . '%');
            });
        }

        $perPage = (int) $request->input('per_page', 50);
        $perPage = in_array($perPage, [50, 100, 500], true) ? $perPage : 50;
        $users = $query->with('ugroup')->paginate($perPage)->withQueryString();
        $ugroups = $this->tenantUgroupQuery()->orderBy('ugroup_name')->get(); 
        
        $organizationRoles = DB::table('organization_users')
            ->where('organization_id', $this->currentTenantId())
            ->whereIn('user_id', $users->getCollection()->pluck('id'))
            ->pluck('role', 'user_id');

        $users->getCollection()->each(function (User $user) use ($organizationRoles) {
            $user->setAttribute('organization_role', $organizationRoles[$user->id] ?? 'staff');
        });

        $canManagePrivilegedAccounts = SaasAccess::isPlatformAdmin();
        return view('users.index', compact('users', 'ugroups', 'perPage', 'canManagePrivilegedAccounts'));
    }

    public function store(Request $request)
    {
        try {
            $request->validate([
                'name' => ['required', 'string', 'max:255'],
                'username' => ['nullable', 'string', 'max:255', 'unique:users,username'],
                'email' => ['required', 'email', 'max:255', 'unique:users,email'],
                'mobile' => ['nullable', 'string', 'max:30', 'unique:users,mobile'],
                'password' => ['required', 'string', 'min:8'],
                'ugroup_id' => ['required', 'exists:ugroups,id'],
                'status' => ['nullable', Rule::in(['Active', 'Suspended'])],
            ]);

            $this->ensureUgroupBelongsToCurrentTenant((int) $request->ugroup_id);

            $data = $request->except(['password']);
            $data['username'] = $this->resolvedUsername($request);
            $data['mobile'] = $request->filled('mobile') ? trim((string) $request->mobile) : null;
            $data['status'] = $request->input('status', 'Active');
            $data['password'] = bcrypt($request->password);

            $user = User::create($data);
            $this->linkUserToCurrentTenant($user);

            $user->groups()->sync([]);

            return redirect()->route('users.index')->with('success', 'User created successfully.');

        } catch (ValidationException $e) {
            return redirect()->route('users.index')->withErrors($e->validator)->withInput();
        } catch (\Exception $e) {
            return redirect()->route('users.index')->with('error', 'Failed: ' . $e->getMessage()); 
        }
    }

    public function update(Request $request, User $user)
    {
        try {
            $this->ensureUserBelongsToCurrentTenant($user);

            if ($user->ugroup_id == 0) {
                return redirect()->route('users.index')->with('error', 'The admin user cannot be updated.');
            }
            $request->validate([
                'name' => ['required', 'string', 'max:255'],
                'username' => ['nullable', 'string', 'max:255', Rule::unique('users', 'username')->ignore($user->id)],
                'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
                'mobile' => ['nullable', 'string', 'max:30', Rule::unique('users', 'mobile')->ignore($user->id)],
                'ugroup_id' => ['required', 'exists:ugroups,id'],
                'password' => ['nullable', 'string', 'min:8'],
                'status' => ['nullable', Rule::in(['Active', 'Suspended'])],
            ]);

            $this->ensureUgroupBelongsToCurrentTenant((int) $request->ugroup_id);

            $data = $request->except(['password']);
            $data['username'] = $this->resolvedUsername($request, $user);
            $data['mobile'] = $request->filled('mobile') ? trim((string) $request->mobile) : null;
            $data['status'] = $request->input('status', $user->status ?: 'Active');
            
            if ($request->filled('password')) {
                $data['password'] = bcrypt($request->password);
            }

            $user->update($data);

            // Legacy exam-content assignments are cleared on edit. Tenant access
            // is governed by the selected Permission Role.
            $user->groups()->sync([]);

            return redirect()->route('users.index')->with('success', 'User updated successfully.');
        } catch (ValidationException $e) {
            return redirect()->route('users.index')->withErrors($e->errors())->withInput();
        } catch (\Exception $e) {
            return redirect()->route('users.index')->with('error', 'Failed to update user: ' . $e->getMessage());
        }
    }

    public function destroy($id)
    {
        try {
            $user = User::findOrFail($id);
            $this->ensureUserBelongsToCurrentTenant($user);

            if ($user->ugroup_id == 0) {
                return redirect()->route('users.index')->with('error', 'The admin account cannot be deleted.');
            }

            $tenantGroupIds = Group::where('organization_id', $this->currentTenantId())->pluck('id')->all();
            $user->groups()->detach($tenantGroupIds);

            DB::table('organization_users')
                ->where('organization_id', $this->currentTenantId())
                ->where('user_id', $user->id)
                ->delete();

            if (! DB::table('organization_users')->where('user_id', $user->id)->exists()) {
                $user->delete();
            }
            
            return redirect()->route('users.index')->with('success', 'User deleted successfully.');
        } catch (\Exception $e) {
            return redirect()->route('users.index')->with('error', 'Failed to delete user.');
        }
    }
}

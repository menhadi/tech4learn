<?php

namespace App\Http\Controllers;

use App\Models\Configuration;
use App\Models\Organization;
use App\Models\SaasPlan;
use App\Models\SaasLead;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use App\Support\SaasAccess;

class SaasController extends Controller
{
    public function index()
    {
        $organizations = Organization::with('plan')->latest()->get();
        $attendanceOnboarding = DB::table('attendance_onboarding_requests')->get()->keyBy('organization_id');
        $plans = SaasPlan::where('status', true)->orderBy('price')->get();
        $users = User::orderBy('name')->get();
        $platformAdmins = Schema::hasColumn('users', 'is_platform_admin')
            ? User::where('is_platform_admin', true)->orderBy('name')->get()
            : collect();
        $saasLeads = Schema::hasTable('saas_leads')
            ? SaasLead::latest()->take(100)->get()
            : collect();

        $defaultOrganization = Organization::where('slug', 'examelite')->first();

        $ownedDataSummary = collect([
            'packages',
            'exams',
            'questions',
            'groups',
            'category',
            'students',
            'orders',
            'sales_reports',
            'configurations',
            'website_pages',
        ])->map(function ($table) use ($defaultOrganization) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'organization_id')) {
                return [
                    'table' => $table,
                    'total' => null,
                    'owned' => null,
                    'missing' => null,
                    'ready' => false,
                ];
            }

            $total = DB::table($table)->count();
            $owned = $defaultOrganization
                ? DB::table($table)->where('organization_id', $defaultOrganization->id)->count()
                : 0;

            return [
                'table' => $table,
                'total' => $total,
                'owned' => $owned,
                'missing' => DB::table($table)->whereNull('organization_id')->count(),
                'ready' => true,
            ];
        });

        return view('saas.index', compact(
            'organizations',
            'attendanceOnboarding',
            'plans',
            'defaultOrganization',
            'ownedDataSummary',
            'users',
            'saasLeads',
            'platformAdmins'
        ));
    }

    public function storeOrganization(Request $request)
    {
        $validated = $this->validateOrganization($request);

        $validated['slug'] = $this->uniqueSlug(Organization::class, $validated['name']);
        $validated['settings'] = [
            'is_primary_platform' => false,
            'created_from_admin' => true,
        ];

        $organization=DB::transaction(function () use ($validated) {
            $organization = Organization::create($validated);
            $this->ensureOrganizationConfiguration($organization);
            DB::table('attendance_onboarding_requests')->insert([
                'organization_id'=>$organization->id,
                'status'=>'pending',
                'attempts'=>0,
                'created_at'=>now(),
                'updated_at'=>now(),
            ]);
            \App\Models\AuditLog::create([
                'organization_id'=>$organization->id,
                'user_id'=>\Illuminate\Support\Facades\Auth::guard('web')->id(),
                'action'=>'organization.created',
                'auditable_type'=>Organization::class,
                'auditable_id'=>$organization->id,
                'metadata'=>['name'=>$organization->name],
                'ip_address'=>request()->ip(),
                'user_agent'=>request()->userAgent(),
            ]);
            return $organization;
        });

        $actor=\Illuminate\Support\Facades\Auth::guard('web')->user();
        if ($actor && $actor->is_platform_admin && $organization->status==='active') {
            $completed=app(\App\Services\AttendanceOnboarding::class)->deliver($request,$actor,$organization->id);
            return redirect()->route('saas.index')->with('success',$completed
                ? 'Organization created. Attendance organisation is ready; staff setup is still required.'
                : 'Organization created. Attendance setup is pending; retry after signing in to the linked platform account.');
        }
        return redirect()->route('saas.index')->with('success', 'Organization created successfully.');
    }

    public function retryAttendanceOnboarding(Request $request, Organization $organization)
    {
        $actor=\Illuminate\Support\Facades\Auth::guard('web')->user();
        abort_unless($actor,401);
        $completed=app(\App\Services\AttendanceOnboarding::class)->deliver($request,$actor,$organization->id);
        return redirect()->route('saas.index')->with('success',$completed
            ? 'Attendance organisation is ready; staff setup is still required.'
            : 'Attendance setup remains pending. Sign in to the linked platform account and retry.');
    }

    public function updateOrganization(Request $request, Organization $organization)
    {
        $validated = $this->validateOrganization($request, $organization->id);

        $validated['slug'] = $organization->slug ?: $this->uniqueSlug(Organization::class, $validated['name'], $organization->id);

        if ($organization->slug === 'examelite') {
            $validated['status'] = 'active';
        }

        $organization->update($validated);
        audit_log('organization.updated', $organization, ['name' => $organization->name]);

        return redirect()->route('saas.index')->with('success', 'Organization updated successfully.');
    }

    public function storePlan(Request $request)
    {
        $validated = $this->validatePlan($request);
        $validated['slug'] = $this->uniqueSlug(SaasPlan::class, $validated['name']);

        if (! empty($validated['is_default'])) {
            SaasPlan::query()->update(['is_default' => false]);
        }

        $plan = SaasPlan::create($validated);
        audit_log('saas_plan.created', $plan, ['name' => $plan->name]);

        return redirect()->route('saas.index')->with('success', 'SaaS plan created successfully.');
    }

    public function updatePlan(Request $request, SaasPlan $plan)
    {
        $validated = $this->validatePlan($request, $plan->id);
        $validated['slug'] = $plan->slug ?: $this->uniqueSlug(SaasPlan::class, $validated['name'], $plan->id);

        if (! empty($validated['is_default'])) {
            SaasPlan::where('id', '!=', $plan->id)->update(['is_default' => false]);
        }

        $plan->update($validated);
        audit_log('saas_plan.updated', $plan, ['name' => $plan->name]);

        return redirect()->route('saas.index')->with('success', 'SaaS plan updated successfully.');
    }

    private function validatePlan(Request $request, ?int $planId = null): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'price' => ['required', 'numeric', 'min:0'],
            'billing_cycle' => ['required', Rule::in(['monthly', 'yearly', 'lifetime'])],
            'is_default' => ['nullable', 'boolean'],
            'status' => ['required', 'boolean'],

            'limit_organizations' => ['nullable', 'integer', 'min:0'],
            'limit_admins' => ['nullable', 'integer', 'min:0'],
            'limit_students' => ['nullable', 'integer', 'min:0'],
            'limit_exams' => ['nullable', 'integer', 'min:0'],
            'limit_packages' => ['nullable', 'integer', 'min:0'],
            'limit_questions' => ['nullable', 'integer', 'min:0'],
            'limit_quality_audits_monthly' => ['nullable', 'integer', 'min:0'],
            'limit_quality_questions_per_audit' => ['nullable', 'integer', 'min:0'],
            'limit_quality_source_per_audit' => ['nullable', 'integer', 'min:0'],
            'limit_quality_ai_per_audit' => ['nullable', 'integer', 'min:0'],
            'limit_quality_visual_per_audit' => ['nullable', 'integer', 'min:0'],
            'limit_quality_repairs_monthly' => ['nullable', 'integer', 'min:0'],

            'feature_public_website' => ['nullable', 'boolean'],
            'feature_paid_packages' => ['nullable', 'boolean'],
            'feature_guest_exams' => ['nullable', 'boolean'],
            'feature_ai_generator' => ['nullable', 'boolean'],
            'feature_ai_translation' => ['nullable', 'boolean'],
            'feature_ai_regeneration' => ['nullable', 'boolean'],
            'feature_ai_content_generation' => ['nullable', 'boolean'],
            'feature_ai_subjective_analysis' => ['nullable', 'boolean'],
            'feature_ai_student_analysis' => ['nullable', 'boolean'],
            'feature_ai_seo' => ['nullable', 'boolean'],
            'feature_ai_platform_api' => ['nullable', 'boolean'],
            'feature_ai_settings' => ['nullable', 'boolean'],
            'feature_email_messaging' => ['nullable', 'boolean'],
            'feature_sms_messaging' => ['nullable', 'boolean'],
            'feature_reports' => ['nullable', 'boolean'],
            'feature_question_sharing' => ['nullable', 'boolean'],
            'feature_custom_theme' => ['nullable', 'boolean'],
            'feature_student_self_registration' => ['nullable', 'boolean'],
            'feature_flashcards' => ['nullable', 'boolean'],
            'feature_ai_flashcard_generation' => ['nullable', 'boolean'],
            'feature_exam_quality_audit' => ['nullable', 'boolean'],
            'feature_exam_quality_source' => ['nullable', 'boolean'],
            'feature_exam_quality_visual' => ['nullable', 'boolean'],
            'feature_exam_quality_ai' => ['nullable', 'boolean'],
        ]);

        $validated['is_default'] = (bool) ($validated['is_default'] ?? false);
        $validated['status'] = (bool) $validated['status'];

        $validated['limits'] = [
            'organizations' => $this->nullableLimit($request->input('limit_organizations')),
            'admins' => $this->nullableLimit($request->input('limit_admins')),
            'students' => $this->nullableLimit($request->input('limit_students')),
            'exams' => $this->nullableLimit($request->input('limit_exams')),
            'packages' => $this->nullableLimit($request->input('limit_packages')),
            'questions' => $this->nullableLimit($request->input('limit_questions')),
            'quality_audits_monthly' => $this->nullableLimit($request->input('limit_quality_audits_monthly')),
            'quality_questions_per_audit' => $this->nullableLimit($request->input('limit_quality_questions_per_audit')),
            'quality_source_per_audit' => $this->nullableLimit($request->input('limit_quality_source_per_audit')),
            'quality_ai_per_audit' => $this->nullableLimit($request->input('limit_quality_ai_per_audit')),
            'quality_visual_per_audit' => $this->nullableLimit($request->input('limit_quality_visual_per_audit')),
            'quality_repairs_monthly' => $this->nullableLimit($request->input('limit_quality_repairs_monthly')),
        ];

        $validated['features'] = [
            'public_website' => $request->boolean('feature_public_website'),
            'paid_packages' => $request->boolean('feature_paid_packages'),
            'guest_exams' => $request->boolean('feature_guest_exams'),
            'ai_generator' => $request->boolean('feature_ai_generator'),
            'ai_translation' => $request->boolean('feature_ai_translation'),
            'ai_regeneration' => $request->boolean('feature_ai_regeneration'),
            'ai_content_generation' => $request->boolean('feature_ai_content_generation'),
            'ai_subjective_analysis' => $request->boolean('feature_ai_subjective_analysis'),
            'ai_student_analysis' => $request->boolean('feature_ai_student_analysis'),
            'ai_seo' => $request->boolean('feature_ai_seo'),
            'ai_platform_api' => $request->boolean('feature_ai_platform_api'),
            'ai_settings' => $request->boolean('feature_ai_settings'),
            'email_messaging' => $request->boolean('feature_email_messaging'),
            'sms_messaging' => $request->boolean('feature_sms_messaging'),
            'reports' => $request->boolean('feature_reports'),
            'question_sharing' => $request->boolean('feature_question_sharing'),
            'custom_theme' => $request->boolean('feature_custom_theme'),
            'student_self_registration' => $request->boolean('feature_student_self_registration'),
            'flashcards' => $request->boolean('feature_flashcards'),
            'ai_flashcard_generation' => $request->boolean('feature_ai_flashcard_generation'),
            'exam_quality_audit' => $request->boolean('feature_exam_quality_audit'),
            'exam_quality_source' => $request->boolean('feature_exam_quality_source'),
            'exam_quality_visual' => $request->boolean('feature_exam_quality_visual'),
            'exam_quality_ai' => $request->boolean('feature_exam_quality_ai'),
        ];

        unset(
            $validated['limit_organizations'],
            $validated['limit_admins'],
            $validated['limit_students'],
            $validated['limit_exams'],
            $validated['limit_packages'],
            $validated['limit_questions'],
            $validated['limit_quality_audits_monthly'],
            $validated['limit_quality_questions_per_audit'],
            $validated['limit_quality_source_per_audit'],
            $validated['limit_quality_ai_per_audit'],
            $validated['limit_quality_visual_per_audit'],
            $validated['limit_quality_repairs_monthly'],
            $validated['feature_public_website'],
            $validated['feature_paid_packages'],
            $validated['feature_guest_exams'],
            $validated['feature_ai_generator'],
            $validated['feature_ai_translation'],
            $validated['feature_ai_regeneration'],
            $validated['feature_ai_content_generation'],
            $validated['feature_ai_subjective_analysis'],
            $validated['feature_ai_student_analysis'],
            $validated['feature_ai_seo'],
            $validated['feature_ai_platform_api'],
            $validated['feature_ai_settings'],
            $validated['feature_email_messaging'],
            $validated['feature_sms_messaging'],
            $validated['feature_reports'],
            $validated['feature_question_sharing'],
            $validated['feature_custom_theme'],
            $validated['feature_student_self_registration'],
            $validated['feature_flashcards'],
            $validated['feature_ai_flashcard_generation'],
            $validated['feature_exam_quality_audit'],
            $validated['feature_exam_quality_source'],
            $validated['feature_exam_quality_visual'],
            $validated['feature_exam_quality_ai']
        );

        return $validated;
    }

    private function nullableLimit($value): ?int
    {
        return $value === null || $value === '' ? null : (int) $value;
    }



    public function storeOrganizationAdmin(Request $request, Organization $organization)
    {
        SaasAccess::abortIfLimitReached('admins', $organization);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'mobile' => ['nullable', 'string', 'max:30', 'unique:users,mobile'],
            'password' => ['required', 'string', 'max:128'],
            'organization_role' => ['required', 'in:owner,admin,staff'],
            'status' => ['required', 'in:Active,Inactive'],
        ]);

        $baseUsername = Str::slug(strtok($validated['email'], '@')) ?: Str::slug($validated['name']);
        $username = $baseUsername;
        $counter = 1;

        while (User::where('username', $username)->exists()) {
            $username = $baseUsername . '-' . $counter;
            $counter++;
        }

        $user = DB::transaction(function () use ($validated, $organization, $username) {
        $user = User::create([
            'name' => $validated['name'],
            'username' => $username,
            'email' => $validated['email'],
            'mobile' => $validated['mobile'] ?? null,
            'password' => bcrypt($validated['password']),
            'ugroup_id' => 0,
            'status' => $validated['status'],
        ]);

        if ($validated['organization_role'] !== 'staff' && method_exists($user, 'assignRole')) {
            $user->assignRole('admin');
        }

        DB::table('organization_users')->updateOrInsert(
            [
                'organization_id' => $organization->id,
                'user_id' => $user->id,
            ],
            [
                'role' => $validated['organization_role'],
                'status' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        audit_log('organization_admin.created', $organization, [
            'user_id' => $user->id,
            'role' => $validated['organization_role'],
        ]);
        return $user;
        });

        $message='Organization user created successfully.';
        $actor=\Illuminate\Support\Facades\Auth::guard('web')->user();
        if ($actor && $actor->is_platform_admin && config('attendance.api_url')
            && $validated['organization_role']!=='staff' && $validated['status']==='Active') {
            try {
                app(\App\Support\AttendanceBridge::class)->provisionAdministrator($request,$actor,$organization,$user,$validated['password']);
                $message='Organization administrator created. Attendance account is ready.';
            } catch (\Throwable $error) {
                // Keep the committed native account; never persist passwords or remote errors.
                $message='Organization administrator created. Attendance account setup is pending.';
            }
        }
        return redirect()->route('saas.index')->with('success',$message);
    }

    public function retryAttendanceAdministrator(Request $request, Organization $organization, User $user)
    {
        $actor=\Illuminate\Support\Facades\Auth::guard('web')->user();
        abort_unless($actor && $actor->is_platform_admin,403);
        abort_unless($organization->status==='active' && !($organization->settings['is_primary_platform'] ?? false),403);
        abort_unless(User::whereKey($user->id)->where('status','Active')->where('deleted',false)->where('is_platform_admin',false)->exists(),404);
        abort_unless(DB::table('organization_users')->where('organization_id',$organization->id)->where('user_id',$user->id)
            ->whereIn('role',['owner','admin'])->where('status',1)->exists(),403);
        $validated=$request->validate(['password'=>['required','string','max:128']]);
        $stored=User::findOrFail($user->id);
        if (!\Illuminate\Support\Facades\Hash::check($validated['password'],$stored->password)) {
            throw \Illuminate\Validation\ValidationException::withMessages(['password'=>'Re-enter the current native account password.']);
        }
        try {
            app(\App\Support\AttendanceBridge::class)->provisionAdministrator($request,$actor,$organization,$user,$validated['password']);
            $message='Attendance administrator account is ready.';
        } catch (\Throwable $error) {
            $message='Attendance administrator setup is pending. Re-enter the password to retry.';
        }
        return redirect()->route('saas.index')->with('success',$message);
    }

    public function storePlatformAdmin(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'mobile' => ['nullable', 'string', 'max:30', 'unique:users,mobile'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $baseUsername = Str::slug(strtok($validated['email'], '@')) ?: Str::slug($validated['name']) ?: 'platform-admin';
        $username = $baseUsername;
        $counter = 2;

        while (User::where('username', $username)->exists()) {
            $username = $baseUsername . '-' . $counter++;
        }

        $user = User::create([
            'name' => $validated['name'],
            'username' => $username,
            'email' => $validated['email'],
            'mobile' => $validated['mobile'] ?? null,
            'password' => bcrypt($validated['password']),
            'ugroup_id' => 0,
            'status' => 'Active',
            'is_platform_admin' => true,
        ]);

        if (method_exists($user, 'assignRole')) {
            $user->assignRole('admin');
        }

        $platformOrganization = Organization::where('slug', 'examelite')->first();
        if ($platformOrganization && Schema::hasTable('organization_users')) {
            DB::table('organization_users')->updateOrInsert(
                ['organization_id' => $platformOrganization->id, 'user_id' => $user->id],
                ['role' => 'owner', 'status' => 1, 'created_at' => now(), 'updated_at' => now()]
            );
        }

        audit_log('platform_admin.created', $user, ['email' => $user->email]);

        return redirect()->route('saas.index')->with('success', 'Platform super admin created successfully.');
    }
    public function updateAdministratorEmail(Request $request, User $user)
    {
        $isOrganizationAdministrator = Schema::hasTable('organization_users')
            && DB::table('organization_users')
                ->where('user_id', $user->id)
                ->whereIn('role', ['owner', 'admin'])
                ->exists();

        abort_unless((bool) $user->is_platform_admin || $isOrganizationAdministrator, 404);

        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
        ]);

        $oldEmail = $user->email;
        $user->email = $validated['email'];
        $user->save();

        audit_log('administrator.email_updated', $user, [
            'old_email' => $oldEmail,
            'new_email' => $user->email,
        ]);

        return redirect()->route('saas.index')->with('success', 'Administrator login email updated successfully.');
    }
    public function assignUser(Request $request)
    {
        $validated = $request->validate([
            'organization_id' => ['required', 'exists:organizations,id'],
            'user_id' => ['required', 'exists:users,id'],
            'role' => ['required', 'in:owner,admin,staff'],
            'status' => ['required', 'boolean'],
        ]);

        $organization = Organization::findOrFail($validated['organization_id']);

        if ((bool) $validated['status'] && ! DB::table('organization_users')
            ->where('organization_id', $organization->id)
            ->where('user_id', $validated['user_id'])
            ->exists()) {
            SaasAccess::abortIfLimitReached('admins', $organization);
        }

        DB::table('organization_users')->updateOrInsert(
            [
                'organization_id' => $validated['organization_id'],
                'user_id' => $validated['user_id'],
            ],
            [
                'role' => $validated['role'],
                'status' => $validated['status'],
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        audit_log('organization_user.assigned', $organization, [
            'user_id' => $validated['user_id'],
            'role' => $validated['role'],
            'status' => $validated['status'],
        ]);

        return redirect()->route('saas.index')->with('success', 'Organization user assignment saved successfully.');
    }


    private function ensureOrganizationConfiguration(Organization $organization): void
    {
        if (Configuration::where('organization_id', $organization->id)->exists()) {
            return;
        }

        Configuration::create([
            "organization_id" => $organization->id,
            "name" => $organization->name,
            "organization_name" => $organization->name,
            "domain_name" => $organization->domain,
            "email" => $organization->email,
            "organization_phone" => $organization->phone,
        ]);
    }

    private function validateOrganization(Request $request, ?int $organizationId = null): array
    {
        return $request->validate([
            'saas_plan_id' => ['nullable', 'exists:saas_plans,id'],
            'name' => ['required', 'string', 'max:255'],
            'domain' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('organizations', 'domain')->ignore($organizationId),
            ],
            'subdomain' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('organizations', 'subdomain')->ignore($organizationId),
            ],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'status' => ['required', Rule::in(['active', 'inactive', 'suspended'])],
            'trial_ends_at' => ['nullable', 'date'],
            'subscription_ends_at' => ['nullable', 'date'],
        ]);
    }

    private function uniqueSlug(string $modelClass, string $value, ?int $ignoreId = null): string
    {
        $baseSlug = Str::slug($value) ?: 'item';
        $slug = $baseSlug;
        $counter = 2;

        while ($modelClass::query()
            ->where('slug', $slug)
            ->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))
            ->exists()
        ) {
            $slug = $baseSlug . '-' . $counter++;
        }

        return $slug;
    }
}

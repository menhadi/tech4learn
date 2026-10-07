<?php
namespace App\Http\Controllers;

use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use App\Models\Group;
use App\Models\ExamResult;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;

// ✅ Naye Excel aur Import/Export classes yahan add kiye gaye hain
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\StudentsExport;
use App\Imports\StudentsImport;
use App\Support\SaasAccess;
use App\Services\StudentAccountSupportEmailService;
use Illuminate\Support\Facades\Schema;

class StudentAdminController extends Controller
{
    public function __construct(private StudentAccountSupportEmailService $accountSupportEmailService)
    {
    }

    private function currentTenantId(): ?int
    {
        return class_exists(\App\Support\Tenant::class) ? \App\Support\Tenant::id() : null;
    }

    private function tenantStudentQuery()
    {
        $query = Student::query();
        $tenantId = $this->currentTenantId();

        if ($tenantId) {
            $query->where('organization_id', $tenantId);
        }

        if (Schema::hasColumn('students', 'is_demo')) {
            $query->where(function ($studentQuery) {
                $studentQuery->whereNull('is_demo')->orWhere('is_demo', false);
            });
        }

        return $query;
    }

    private function tenantGroupQuery()
    {
        $query = Group::query();
        $tenantId = $this->currentTenantId();

        if ($tenantId) {
            $query->where('organization_id', $tenantId);
        }

        return $query;
    }

    private function ensureTenantOwnsStudent(Student $student): void
    {
        $tenantId = $this->currentTenantId();

        if ($tenantId && (int) $student->organization_id !== (int) $tenantId) {
            abort(404);
        }
    }

    private function ensureTenantOwnsGroup(int $groupId): void
    {
        $tenantId = $this->currentTenantId();

        if ($tenantId && ! Group::where('organization_id', $tenantId)->where('id', $groupId)->exists()) {
            abort(404);
        }
    }

    public function index(Request $request)
    {
        $query = $this->tenantStudentQuery();
        
        // Eager Load Performance Metrics
        $query->withAvg('examResults', 'percent'); 
        $query->withMax('examResults as last_exam_date', 'created_at'); 
        
        // Group Filtering
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

        // Insight Filters
        if ($request->filled('rank')) {
            $filter = $request->rank;
            if ($filter == 'active') {
                $query->where('status', 'Active');
            } elseif ($filter == 'pending') {
                $query->where('status', 'Pending');
            } elseif ($filter == 'inactive') {
                $query->where('status', '!=', 'Active');
            } elseif ($filter == 'weak') {
                $query->having('exam_results_avg_percent', '<', 35);
            } elseif ($filter == 'top') {
                $query->having('exam_results_avg_percent', '>=', 75);
            }
        }

        // Search Logic
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', '%' . $search . '%')
                  ->orWhere('email', 'like', '%' . $search . '%')
                  ->orWhere('phone', 'like', '%' . $search . '%')
                  ->orWhere('reg_code', 'like', '%' . $search . '%')
                  ->orWhere('enroll', 'like', '%' . $search . '%');
            });
        }

        // Sorting
        $sort = $request->input('sort', 'created');
        if ($sort === 'name') {
            $query->orderBy('name');
        } elseif ($sort === 'status') {
            $query->orderBy('status')->orderBy('name');
        } elseif ($sort === 'performance') {
            $query->orderByDesc('exam_results_avg_percent')->orderBy('name');
        } elseif ($sort === 'created') {
            $query->orderByDesc('created_at');
        } else {
            $query->orderByDesc('last_login')->orderByDesc('created_at');
        }

        $perPage = (int) $request->input('per_page', 50);
        if (! in_array($perPage, [50, 100, 500], true)) {
            $perPage = 50;
        }

        $students = $query->paginate($perPage)->withQueryString();
        
        $groups = $this->tenantGroupQuery()->orderBy('group_name')->get();

        // Dashboard Stats
        $stats = [
            'total' => $this->tenantStudentQuery()->count(),
            'active' => $this->tenantStudentQuery()->where('status', 'Active')->count(),
            'pending' => $this->tenantStudentQuery()->where('status', 'Pending')->count(),
            'weak_approx' => DB::table('exam_results')
                            ->join('students', 'students.id', '=', 'exam_results.student_id')
                            ->when($this->currentTenantId(), function ($q, $tenantId) {
                                $q->where('students.organization_id', $tenantId);
                            })
                            ->when(Schema::hasColumn('students', 'is_demo'), function ($q) {
                                $q->where(function ($students) {
                                    $students->whereNull('students.is_demo')->orWhere('students.is_demo', false);
                                });
                            })
                            ->select('exam_results.student_id', DB::raw('AVG(percent) as avg_score'))
                            ->groupBy('exam_results.student_id')
                            ->having('avg_score', '<', 35)
                            ->get()->count(),
        ];


        $showDemoStudents = SaasAccess::isPlatformAdmin() && Schema::hasColumn('students', 'is_demo');
        $demoStudentsCount = 0;
        if ($showDemoStudents) {
            $demoStudentsCount = Student::query()
                ->where('is_demo', true)
                ->when($this->currentTenantId(), fn ($query, $tenantId) => $query->where('organization_id', $tenantId))
                ->count();
        }
        $stats['demo'] = $demoStudentsCount;
        $stats['all'] = $stats['total'] + $demoStudentsCount;

        $configuration = getConfiguration();
        $requireRegistrationNumber = (bool) ($configuration->require_student_registration_number ?? false);
        return view('students.admin.index', compact(
            'students',
            'groups',
            'stats',
            'requireRegistrationNumber',
            'showDemoStudents'
        ));
    }

    /**
     * Bulk Remove Students
     */
    public function bulkRemove(Request $request)
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:students,id'
        ]);

        try {
            $students = $this->tenantStudentQuery()->whereIn('id', $request->ids)->get();
            foreach($students as $student) {
                if ($student->photo) {
                    Storage::disk('public')->delete($student->photo);
                }
                $student->delete();
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Selected students deleted successfully.'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete students.'
            ], 500);
        }
    }

    /**
     * Bulk Assign/Update Groups
     */
    public function bulkAssignGroup(Request $request)
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:students,id',
            'group_id' => 'required|exists:groups,id',
            'action_type' => 'required|in:add,replace,remove'
        ]);

        try {
            $students = $this->tenantStudentQuery()->whereIn('id', $request->ids)->get();
            $groupId = (int) $request->group_id;
            $this->ensureTenantOwnsGroup($groupId);
            $count = 0;

            foreach ($students as $student) {
                if ($request->action_type == 'replace') {
                    // Replace all existing groups with this one
                    $student->groups()->sync([$groupId]);
                } elseif ($request->action_type == 'add') {
                    // Add this group, keep existing ones
                    $student->groups()->syncWithoutDetaching([$groupId]);
                } elseif ($request->action_type == 'remove') {
                    // Remove only this group
                    $student->groups()->detach($groupId);
                }
                $count++;
            }

            return response()->json([
                'status' => 'success',
                'message' => "Groups updated for $count students successfully."
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update groups: ' . $e->getMessage()
            ], 500);
        }
    }

    public function store(Request $request)
    {
        try {
            SaasAccess::abortIfLimitReached('students');
            $tenantId = $this->currentTenantId();
            $registrationRequired = (bool) (getConfiguration()->require_student_registration_number ?? false);

            $request->validate([
                'name' => 'required',
                'email' => [
                    'nullable',
                    'email',
                    \Illuminate\Validation\Rule::unique('students', 'email')->where(fn ($query) => $query->where('organization_id', $tenantId)),
                ],
                'address' => 'nullable',
                'phone' => [
                    'nullable',
                    \Illuminate\Validation\Rule::unique('students', 'phone')->where(fn ($query) => $query->where('organization_id', $tenantId)),
                ],
                'reg_code' => [
                    \Illuminate\Validation\Rule::requiredIf($registrationRequired),
                    'nullable',
                    'string',
                    'max:255',
                    \Illuminate\Validation\Rule::unique('students', 'reg_code')->where(fn ($query) => $query->where('organization_id', $tenantId)),
                ],
                'enroll' => [
                    'nullable',
                    'string',
                    'max:255',
                    \Illuminate\Validation\Rule::unique('students', 'enroll')->where(fn ($query) => $query->where('organization_id', $tenantId)),
                ],
                'password' => [$registrationRequired ? 'nullable' : 'required_without:reg_code', 'nullable', 'min:6'],
                'group_ids' => 'required|array',
                'group_ids.*' => 'exists:groups,id',
                'photo' => 'nullable|image|mimes:jpg,jpeg,png|max:1024',
            ]);

            foreach ($request->group_ids as $groupId) {
                $this->ensureTenantOwnsGroup((int) $groupId);
            }

            $data = $request->all();
            $data['organization_id'] = $this->currentTenantId();
            $data['status'] = $request->input('status', 'Active');
            $data['email'] = $request->filled('email') ? $request->email : null;
            $data['phone'] = $request->filled('phone') ? $request->phone : null;
            $data['enroll'] = $request->filled('enroll') ? $request->enroll : null;
            $data['address'] = $request->filled('address') ? $request->address : 'N/A';
            $data['password'] = bcrypt($request->filled('password') ? $request->password : $request->reg_code);

            if ($request->hasFile('photo')) {
                $data['photo'] = $request->file('photo')->store('students/profile_photos', 'public');
            }

            $student = Student::create($data);
            $student->groups()->sync($request->group_ids);

            return redirect()->route('students.index')->with('success', 'Student created successfully.');
        } catch (ValidationException $e) {
            return redirect()->route('students.index')->withErrors($e->errors())->withInput();
        } catch (\Exception $e) {
            return redirect()->route('students.index')->with('error', 'Failed to create student.');
        }
    }

    public function show(Student $student)
    {
        $this->ensureTenantOwnsStudent($student);
        return redirect()->route('students.index');
    }
    public function identity(Request $request, Student $student, \App\Support\AttendanceBridge $bridge)
    {
        $this->ensureTenantOwnsStudent($student);
        return response()->json($bridge->learnerIdentity($request,$student))->header('Cache-Control','no-store');
    }
    public function update(Request $request, Student $student)
    {
        $this->ensureTenantOwnsStudent($student);

        try {
            $tenantId = $this->currentTenantId();
            $registrationRequired = (bool) (getConfiguration()->require_student_registration_number ?? false);

            $request->validate([
                'name' => 'required',
                'email' => [
                    'nullable',
                    'email',
                    \Illuminate\Validation\Rule::unique('students', 'email')->ignore($student->id)->where(fn ($query) => $query->where('organization_id', $tenantId)),
                ],
                'address' => 'nullable',
                'phone' => [
                    'nullable',
                    \Illuminate\Validation\Rule::unique('students', 'phone')->ignore($student->id)->where(fn ($query) => $query->where('organization_id', $tenantId)),
                ],
                'reg_code' => [
                    \Illuminate\Validation\Rule::requiredIf($registrationRequired),
                    'nullable',
                    'string',
                    'max:255',
                    \Illuminate\Validation\Rule::unique('students', 'reg_code')->ignore($student->id)->where(fn ($query) => $query->where('organization_id', $tenantId)),
                ],
                'enroll' => [
                    'nullable',
                    'string',
                    'max:255',
                    \Illuminate\Validation\Rule::unique('students', 'enroll')->ignore($student->id)->where(fn ($query) => $query->where('organization_id', $tenantId)),
                ],
                'password' => 'nullable|min:6',
                'group_ids' => 'required|array',
                'group_ids.*' => 'exists:groups,id',
                'photo' => 'nullable|image|mimes:jpg,jpeg,png|max:1024',
            ]);

            foreach ($request->group_ids as $groupId) {
                $this->ensureTenantOwnsGroup((int) $groupId);
            }

            $data = $request->all();
            unset($data['organization_id']);
            $data['email'] = $request->filled('email') ? $request->email : null;
            $data['phone'] = $request->filled('phone') ? $request->phone : null;
            $data['enroll'] = $request->filled('enroll') ? $request->enroll : null;
            $data['address'] = $request->filled('address') ? $request->address : 'N/A';
            if ($request->filled('password')) {
                $data['password'] = bcrypt($request->password);
            } else {
                unset($data['password']);
            }

            $wasPending = $student->status === 'Pending';
            $willBeActive = ($data['status'] ?? $student->status) === 'Active';

            if (($data['status'] ?? null) === 'Pending' && Schema::hasColumn('students', 'admin_activation_email_sent_at')) {
                $data['admin_activation_email_sent_at'] = null;
                if (Schema::hasColumn('students', 'pending_admin_notified_at')) {
                    $data['pending_admin_notified_at'] = null;
                }
                if (Schema::hasColumn('students', 'founder_followup_sent_at')) {
                    $data['founder_followup_sent_at'] = null;
                }
            }

            if ($wasPending && $willBeActive) {
                $data['otp'] = null;
                $data['otp_expires_at'] = null;

                if (Schema::hasColumn('students', 'admin_activated_at')) {
                    $data['admin_activated_at'] = now();
                }
            }

            if ($request->hasFile('photo')) {
                if ($student->photo) {
                    Storage::disk('public')->delete($student->photo);
                }
                $data['photo'] = $request->file('photo')->store('students/profile_photos', 'public');
            }

            $student->update($data);
            $student->groups()->sync($request->group_ids);

            if ($wasPending && $willBeActive) {
                $student->refresh();
                $this->accountSupportEmailService->sendManualActivationOnce($student);
            }

            return redirect()->route('students.index')->with('success', 'Student updated successfully.');
        } catch (ValidationException $e) {
            return redirect()->route('students.index')->withErrors($e->errors())->withInput();
        } catch (\Exception $e) {
            return redirect()->route('students.index')->with('error', 'Failed to update student.');
        }
    }

    public function destroy($id)
    {
        try {
            $student = $this->tenantStudentQuery()->findOrFail($id);
            if ($student->photo) {
                Storage::disk('public')->delete($student->photo);
            }
            $student->delete();
            return redirect()->route('students.index')->with('success', 'Student deleted successfully.');
        } catch (\Exception $e) {
            return redirect()->route('students.index')->with('error', 'Failed to delete student.');
        }
    }

    // ========================================================
    // ✅ NEW IMPORT & EXPORT FUNCTIONS ADDED BELOW
    // ========================================================

    public function export(Request $request)
    {
        return Excel::download(new StudentsExport($request), 'students_' . date('d_m_Y') . '.xlsx');
    }

    public function import(Request $request)
    {
        $request->validate([
            'excel_file' => 'required|file|mimes:xlsx,csv',
            'group_id' => 'required|exists:groups,id',
        ]);

        try {
            $this->ensureTenantOwnsGroup((int) $request->group_id);
            SaasAccess::abortIfLimitReached('students');

            DB::transaction(function () use ($request) {
                Excel::import(new StudentsImport($request->group_id, $this->currentTenantId()), $request->file('excel_file'));
            });
            return redirect()->route('students.index')->with('success', 'Students imported successfully.');
        } catch (\Exception $e) {
            return redirect()->route('students.index')->with('error', 'Import failed: ' . $e->getMessage());
        }
    }

    public function downloadTemplate()
    {
        // Dynamic CSV generate kar rahe hain, physically file rakhne ki need nahi
        $headers = ['name', 'registration_number', 'roll_number', 'email', 'phone', 'address'];
        
        $callback = function() use ($headers) {
            $file = fopen('php://output', 'w');
            fputcsv($file, $headers);
            // Ek sample row demo ke liye
            fputcsv($file, ['John Doe', 'REG1001', 'ROLL1001', 'john@example.com', '9876543210', 'Delhi']);
            fclose($file);
        };

        return response()->stream($callback, 200, [
            "Content-type"        => "text/csv",
            "Content-Disposition" => "attachment; filename=student_import_template.csv",
            "Pragma"              => "no-cache",
            "Cache-Control"       => "must-revalidate, post-check=0, pre-check=0",
            "Expires"             => "0"
        ]);
    }
}

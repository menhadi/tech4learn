<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Laravel\Socialite\Facades\Socialite;
use App\Models\Student;
use App\Support\SaasAccess;
use App\Services\StudentActivityTracker;
use App\Services\StudentPostAuthService;
use Illuminate\Http\Request;
use App\Services\StudentWelcomeEmailService;
use Illuminate\Support\Facades\Auth;

class GoogleController extends Controller
{
    public function __construct(
        private StudentWelcomeEmailService $welcomeEmailService,
        private StudentPostAuthService $postAuthService
    )
    {
    }

    private function currentTenantId(): ?int
    {
        return class_exists(\App\Support\Tenant::class) ? \App\Support\Tenant::hostId() : null;
    }

    private function tenantStudentQuery()
    {
        $query = Student::query();
        $tenantId = $this->currentTenantId();

        if (SaasAccess::isPlatformOrganization()) {
            return $query
                ->whereHas('organization', function ($query) {
                    $query->where('status', 'active');
                })
                ->orderByRaw('organization_id = ? desc', [$tenantId]);
        }

        if ($tenantId) {
            $query->where('organization_id', $tenantId);
        }

        return $query;
    }

    public function redirectToGoogle()
    {
        if (! SaasAccess::isPlatformOrganization()) {
            return redirect()->route('student.signin')->with('error', 'Google login is available only on ExamElite.');
        }

        return Socialite::driver('google')->redirect();
    }

    public function handleGoogleCallback(Request $request)
    {
        try {
            if (! SaasAccess::isPlatformOrganization()) {
                return redirect()->route('student.signin')->with('error', 'Google login is available only on ExamElite.');
            }

            $googleUser = Socialite::driver('google')->user();

            $student = $this->tenantStudentQuery()->where('email', $googleUser->email)->first();

            $isNewStudent = ! $student;

            if ($student) {

                $student->update([
                    'google_id' => $googleUser->id,
                    'provider' => 'google',
                    'email_verified_at' => now(),
                ]);

            } else {

                $student = Student::create([
                    'name' => $googleUser->name,
                    'email' => $googleUser->email,
                    'organization_id' => $this->currentTenantId(),
                    'google_id' => $googleUser->id,
                    'provider' => 'google',
                    'email_verified_at' => now(),
                    'password' => null,
                    'photo' => $googleUser->avatar,
                    'status' => 1,
                ]);
            }

            Auth::guard('student')->login($student);

            StudentActivityTracker::track(StudentActivityTracker::STUDENT_LOGGED_IN, [
                'organization_id' => $student->organization_id,
                'student_id' => $student->id,
                'metadata' => [
                    'provider' => 'google',
                    'is_new_student' => $isNewStudent,
                ],
            ], $request);

            if ($isNewStudent) {
                $this->welcomeEmailService->sendOnce($student);
            }


            $continuation = $this->postAuthService->complete($student, $request);
            if ($continuation) {
                return redirect($continuation['url']);
            }
            return redirect(route('student.myexams'));

        } catch (\Exception $e) {

            return redirect(route('student.signin'))->with('error', 'Google login failed.');

        }
    }
}

<?php

namespace App\Http\Controllers\Students;

use App\Http\Controllers\Controller;
use App\Models\Configuration;
use App\Models\Student;
use App\Services\PhoneNumberService;
use App\Services\StudentOtpService;
use App\Support\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class StudentForgotPasswordController extends Controller
{
    public function __construct(
        private readonly StudentOtpService $otpService,
        private readonly PhoneNumberService $phoneNumbers
    ) {}

    private function tenantId(): ?int
    {
        return class_exists(Tenant::class) ? Tenant::id() : null;
    }

    private function studentQuery()
    {
        return Student::query()->when($this->tenantId(), fn ($query, $tenantId) => $query->where('organization_id', $tenantId));
    }

    private function configuration()
    {
        return function_exists('getConfiguration') ? getConfiguration() : Configuration::first();
    }

    public function showForgotPasswordForm()
    {
        return view('students.auth.student_email', ['configuration' => $this->configuration()]);
    }

    public function sendOtp(Request $request)
    {
        $request->validate(['identifier' => 'required|string|max:255']);
        $identifier = trim($request->identifier);
        $normalizedPhone = null;

        if (preg_match('/^[+\d\s().-]{8,20}$/', $identifier)) {
            try {
                $normalizedPhone = $this->phoneNumbers->normalize($identifier, $this->configuration()?->default_country_code ?: '+91');
            } catch (\InvalidArgumentException) {
                $normalizedPhone = null;
            }
        }

        $student = $this->studentQuery()->where(function ($query) use ($identifier, $normalizedPhone) {
            $query->where('email', $identifier)->orWhere('phone', $identifier);
            if ($normalizedPhone) {
                $query->orWhere('phone', $normalizedPhone);
            }
        })->first();

        if (! $student) {
            return back()->withErrors(['identifier' => 'No student account was found for that email or mobile number.'])->withInput();
        }

        try {
            $channel = $this->otpService->send($student, $request->input('channel'));
        } catch (\Throwable $exception) {
            report($exception);

            return back()->withErrors(['identifier' => 'We could not send the code. Try another method or contact the administrator.'])->withInput();
        }

        session(['reset_student_id' => $student->id, 'reset_channel' => $channel]);

        return redirect()->route('student.password.reset')->with('success', 'Password reset code sent by '.ucfirst($channel).'.');
    }

    public function verifyOtp(Request $request)
    {
        $request->validate([
            'otp' => 'required|digits:6',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $student = $this->studentQuery()->find(session('reset_student_id'));
        if (! $student || ! $this->otpService->verify($student, (string) $request->otp)) {
            return back()->withErrors(['otp' => 'The code is incorrect or expired. Request a new code and try again.']);
        }

        $student->forceFill(['password' => Hash::make($request->password)])->save();
        session()->forget(['reset_student_id', 'reset_channel']);

        return redirect()->route('student.signin')->with('status', 'Password reset successfully. You can now sign in.');
    }

    public function showResetForm()
    {
        $student = $this->studentQuery()->find(session('reset_student_id'));
        if (! $student) {
            return redirect()->route('student.password.request');
        }

        return view('students.auth.student_reset', [
            'student' => $student,
            'destination' => $this->otpService->destination($student),
            'channels' => $this->otpService->availableChannels($student),
            'configuration' => $this->configuration(),
        ]);
    }
}

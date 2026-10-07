<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Configuration;
use App\Models\EmailTemplate;
use App\Models\User;
use App\Services\EmailService;
use App\Support\SaasAccess;
use App\Support\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class ForgotPasswordController extends Controller
{
    public function __construct(protected EmailService $emailService)
    {
    }

    private function tenantId(): ?int
    {
        return class_exists(Tenant::class) ? Tenant::hostId(request()->getHost()) : null;
    }

    private function userQuery()
    {
        $query = User::query();
        $tenantId = $this->tenantId();

        if (! $tenantId || ! Schema::hasTable('organization_users')) {
            return $query;
        }

        $tenantUsers = DB::table('organization_users')
            ->where('organization_id', $tenantId)
            ->where('status', 1)
            ->select('user_id');

        if (SaasAccess::isPlatformOrganization() && Schema::hasColumn('users', 'is_platform_admin')) {
            return $query->where(function ($scope) use ($tenantUsers) {
                $scope->where('is_platform_admin', true)->orWhereIn('id', $tenantUsers);
            });
        }

        return $query->whereIn('id', $tenantUsers);
    }

    private function configuration()
    {
        return function_exists('getConfiguration') ? getConfiguration() : Configuration::first();
    }

    private function emailTemplate(string $type): ?EmailTemplate
    {
        $query = EmailTemplate::where('type', $type)->where('status', 'Active');

        if ($this->tenantId() && Schema::hasColumn('email_templates', 'organization_id')) {
            $query->where(function ($scope) {
                $scope->where('organization_id', $this->tenantId())->orWhereNull('organization_id');
            })->orderByRaw('organization_id is null');
        }

        return $query->first();
    }

    public function showForgotPasswordForm()
    {
        return view('auth.passwords.email', ['configuration' => $this->configuration()]);
    }

    public function sendOtp(Request $request)
    {
        $validated = $request->validate(['email' => ['required', 'email']]);
        $user = $this->userQuery()->where('email', $validated['email'])->first();

        if (! $user) {
            return back()->withErrors(['email' => 'No administrator account was found for this website.'])->withInput();
        }

        $configuration = $this->configuration();
        $siteName = $configuration?->name ?? config('app.name', 'ExamElite');
        $siteEmail = $configuration?->email ?? config('mail.from.address');
        $primaryColor = $configuration?->primary_color ?? '#0f766e';
        $otp = $user->generateOtp();
        $template = $this->emailTemplate('admin_password_reset');
        $subjectTemplate = $template?->subject ?: 'Reset your {#siteName#} administrator password';
        $subject = str_replace(['{#name#}', '{#siteName#}'], [$user->name, $siteName], $subjectTemplate);
        $body = $template?->description ?: '<div style="font-family:Arial,sans-serif;max-width:620px;margin:auto;padding:28px;border:1px solid #e2e8f0;border-radius:16px"><h2 style="color:{#primaryColor#}">Administrator password reset</h2><p>Hello {#name#},</p><p>Use this verification code to reset your {#siteName#} administrator password:</p><p style="font-size:30px;font-weight:700;letter-spacing:8px;color:{#primaryColor#}">{#code#}</p><p>This code expires in 10 minutes. If you did not request it, you can safely ignore this email.</p><p>Need help? Contact {#siteEmailContact#}.</p></div>';
        $content = str_replace(
            ['{#name#}', '{#code#}', '{#siteName#}', '{#siteEmailContact#}', '{#primaryColor#}'],
            [$user->name, $otp, $siteName, $siteEmail, $primaryColor],
            $body
        );

        $sent = $this->emailService->sendEmail($user->email, $subject, $content, null, null, $this->tenantId());

        if (! $sent) {
            $user->forceFill(['otp' => null, 'otp_expires_at' => null])->save();
            Log::warning('Admin password reset email could not be sent.', ['user_id' => $user->id, 'organization_id' => $this->tenantId()]);

            return back()->withErrors(['email' => 'The reset email could not be sent. Please ask an administrator to check SMTP Settings and Email Logs.'])->withInput();
        }

        session([
            'admin_password_reset_user_id' => $user->id,
            'admin_password_reset_email' => $user->email,
        ]);

        return redirect()->route('password.reset')->with('status', 'OTP sent successfully. Please check your email.');
    }

    public function verifyOtp(Request $request)
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'otp' => ['required', 'digits:6'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $sessionUserId = session('admin_password_reset_user_id');
        $sessionEmail = session('admin_password_reset_email');

        if (! $sessionUserId || ! $sessionEmail || ! hash_equals(strtolower($sessionEmail), strtolower($validated['email']))) {
            return redirect()->route('password.request')->withErrors(['email' => 'Your reset session expired. Please request a new OTP.']);
        }

        $user = $this->userQuery()->whereKey($sessionUserId)->where('email', $sessionEmail)->first();

        if (! $user || ! $user->verifyOtp($validated['otp'])) {
            return back()->withErrors(['otp' => 'The OTP is invalid or has expired.'])->withInput($request->except('password', 'password_confirmation'));
        }

        $user->forceFill([
            'password' => Hash::make($validated['password']),
            'otp' => null,
            'otp_expires_at' => null,
            'remember_token' => Str::random(60),
        ])->save();

        session()->forget(['admin_password_reset_user_id', 'admin_password_reset_email']);

        return redirect()->route('login')->with('status', 'Password reset successfully. Please log in.');
    }

    public function showResetForm()
    {
        $email = session('admin_password_reset_email');

        if (! $email || ! session('admin_password_reset_user_id')) {
            return redirect()->route('password.request')->withErrors(['email' => 'Session expired. Please request a new OTP.']);
        }

        return view('auth.passwords.reset', [
            'email' => $email,
            'configuration' => $this->configuration(),
        ]);
    }
}

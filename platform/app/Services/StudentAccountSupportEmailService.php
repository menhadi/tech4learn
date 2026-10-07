<?php

namespace App\Services;

use App\Models\Configuration;
use App\Models\EmailTemplate;
use App\Models\Student;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

class StudentAccountSupportEmailService
{
    public function __construct(private EmailService $emailService)
    {
    }

    public function notifyAdminPendingVerificationOnce(Student $student): bool
    {
        if (
            Schema::hasColumn('students', 'pending_admin_notified_at')
            && $student->pending_admin_notified_at
        ) {
            return false;
        }

        $student->loadMissing('groups', 'organization');
        $configuration = $this->configurationFor($student);

        if (! $this->enabled($configuration, 'student_pending_admin_email_enabled')) {
            return false;
        }

        $adminEmail = $this->adminEmailFor($configuration);

        if (! $adminEmail || ! filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        $tokens = $this->tokens($student, $configuration);
        $template = $this->templateFor($student, 'student_pending_admin_alert');
        $subject = $this->replaceTokens($template?->subject ?: 'Student needs verification help: {#studentName#}', $tokens);
        $html = $this->replaceTokens($template?->description ?: $this->pendingAdminFallback(), $tokens);

        $sent = $this->emailService->sendEmail($adminEmail, $subject, $html, null, null, $student->organization_id);

        if ($sent && Schema::hasColumn('students', 'pending_admin_notified_at')) {
            $student->forceFill(['pending_admin_notified_at' => now()])->save();
        }

        return $sent;
    }

    public function sendManualActivationOnce(Student $student): bool
    {
        if (! $student->email) {
            return false;
        }

        if (
            Schema::hasColumn('students', 'admin_activation_email_sent_at')
            && $student->admin_activation_email_sent_at
        ) {
            return false;
        }

        $student->loadMissing('groups', 'organization');
        $configuration = $this->configurationFor($student);

        if (! $this->enabled($configuration, 'student_manual_activation_email_enabled')) {
            return false;
        }

        $tokens = $this->tokens($student, $configuration);
        $template = $this->templateFor($student, 'student_admin_activated');
        $subject = $this->replaceTokens($template?->subject ?: 'Your {#siteName#} account has been activated', $tokens);
        $html = $this->replaceTokens($template?->description ?: $this->studentActivatedFallback(), $tokens);

        $sent = $this->emailService->sendEmail($student->email, $subject, $html, null, null, $student->organization_id);

        if ($sent && Schema::hasColumn('students', 'admin_activation_email_sent_at')) {
            $student->forceFill(['admin_activation_email_sent_at' => now()])->save();
        }

        return $sent;
    }

    private function configurationFor(Student $student): ?Configuration
    {
        if ($student->organization_id && Schema::hasColumn('configurations', 'organization_id')) {
            $configuration = Configuration::where('organization_id', $student->organization_id)->first();

            if ($configuration) {
                return $configuration;
            }
        }

        return function_exists('getConfiguration') ? getConfiguration() : Configuration::first();
    }

    private function enabled(?Configuration $configuration, string $column): bool
    {
        if (! $configuration || ! Schema::hasColumn('configurations', $column)) {
            return true;
        }

        return (bool) $configuration->{$column};
    }

    private function adminEmailFor(?Configuration $configuration): ?string
    {
        $email = $configuration?->email;

        if ($email && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $email;
        }

        $fallback = config('mail.from.address');

        return $fallback && filter_var($fallback, FILTER_VALIDATE_EMAIL) ? $fallback : null;
    }

    private function templateFor(Student $student, string $type): ?EmailTemplate
    {
        if (! Schema::hasTable('email_templates')) {
            return null;
        }

        if (Schema::hasColumn('email_templates', 'organization_id') && $student->organization_id) {
            $tenantTemplate = EmailTemplate::where('type', $type)
                ->where('status', 'Active')
                ->where('organization_id', $student->organization_id)
                ->latest()
                ->first();

            if ($tenantTemplate) {
                return $tenantTemplate;
            }

            return EmailTemplate::where('type', $type)
                ->where('status', 'Active')
                ->whereNull('organization_id')
                ->latest()
                ->first();
        }

        return EmailTemplate::where('type', $type)
            ->where('status', 'Active')
            ->when(Schema::hasColumn('email_templates', 'organization_id'), function ($query) {
                $query->whereNull('organization_id');
            })
            ->latest()
            ->first();
    }

    private function tokens(Student $student, ?Configuration $configuration): array
    {
        $siteName = $configuration?->name ?: config('app.name', 'ExamElite');
        $organizationName = $configuration?->organization_name ?: $siteName;
        $primaryColor = $configuration?->theme_primary_color ?: '#0f766e';
        $secondaryColor = $configuration?->theme_secondary_color ?: '#f59e0b';
        $groups = $student->groups instanceof Collection ? $student->groups : collect();
        $groupNames = $groups->pluck('group_name')->filter()->implode(', ');

        return [
            '{#studentName#}' => e($student->name ?: 'Student'),
            '{#studentEmail#}' => e($student->email ?: '-'),
            '{#studentPhone#}' => e($student->phone ?: '-'),
            '{#studentRegCode#}' => e($student->reg_code ?: '-'),
            '{#groupNames#}' => e($groupNames ?: 'Not selected'),
            '{#siteName#}' => e($siteName),
            '{#organizationName#}' => e($organizationName),
            '{#primaryColor#}' => e($primaryColor),
            '{#secondaryColor#}' => e($secondaryColor),
            '{#loginUrl#}' => e(Route::has('student.signin') ? route('student.signin') : url('/student/signin')),
            '{#resetPasswordUrl#}' => e(Route::has('student.password.request') ? route('student.password.request') : url('/student/password/forgot')),
            '{#studentsAdminUrl#}' => e(Route::has('students.index') ? route('students.index', ['rank' => 'pending']) : url('/students')),
        ];
    }

    private function replaceTokens(string $content, array $tokens): string
    {
        return str_replace(array_keys($tokens), array_values($tokens), $content);
    }

    private function pendingAdminFallback(): string
    {
        return '<p>{#studentName#} registered but has not completed OTP verification. Open Students to review and activate manually if needed.</p>';
    }

    private function studentActivatedFallback(): string
    {
        return '<p>Hello {#studentName#}, your account has been activated by the administrator. Please log in using the password you created during registration, or use reset password if needed.</p>';
    }
}

<?php

namespace App\Services;

use App\Models\Configuration;
use App\Models\EmailTemplate;
use App\Models\Student;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

class StudentWelcomeEmailService
{
    public function __construct(private EmailService $emailService)
    {
    }

    public function sendOnce(Student $student): bool
    {
        if (! $student->email) {
            return false;
        }

        if (Schema::hasColumn('students', 'welcome_email_sent_at') && $student->welcome_email_sent_at) {
            return false;
        }

        $student->loadMissing('groups');
        $configuration = $this->configurationFor($student);

        if (! $this->isEnabled($configuration)) {
            return false;
        }

        $siteName = $configuration?->name ?: config('app.name', 'ExamElite');
        $organizationName = $configuration?->organization_name ?: $siteName;
        $groups = $student->groups->take(4);
        $myExamsUrl = Route::has('student.myexams') ? route('student.myexams') : url('/student/my-exams');
        $coursesUrl = Route::has('student.courses.index') ? route('student.courses.index') : (Route::has('courses.index') ? route('courses.index') : url('/courses'));
        $primaryColor = $configuration?->theme_primary_color ?: '#0f766e';
        $secondaryColor = $configuration?->theme_secondary_color ?: '#f59e0b';

        $template = $this->welcomeTemplateFor($student);
        $tokens = $this->templateTokens(
            student: $student,
            siteName: $siteName,
            organizationName: $organizationName,
            groups: $groups,
            myExamsUrl: $myExamsUrl,
            coursesUrl: $coursesUrl,
            primaryColor: $primaryColor,
            secondaryColor: $secondaryColor,
        );

        if ($template) {
            $subject = $this->replaceTokens($template->subject ?: 'Welcome to {#siteName#}', $tokens);
            $html = $this->replaceTokens($template->description, $tokens);
        } else {
            $subject = 'Welcome to ' . $siteName;
            $html = view('emails.student_welcome', [
                'student' => $student,
                'siteName' => $siteName,
                'organizationName' => $organizationName,
                'groups' => $groups,
                'myExamsUrl' => $myExamsUrl,
                'coursesUrl' => $coursesUrl,
                'primaryColor' => $primaryColor,
                'secondaryColor' => $secondaryColor,
            ])->render();
        }

        $sent = $this->emailService->sendEmail(
            $student->email,
            $subject,
            $html
        );

        if ($sent && Schema::hasColumn('students', 'welcome_email_sent_at')) {
            $student->forceFill(['welcome_email_sent_at' => now()])->save();
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

    private function isEnabled(?Configuration $configuration): bool
    {
        if (! $configuration || ! Schema::hasColumn('configurations', 'student_welcome_email_enabled')) {
            return true;
        }

        return (bool) $configuration->student_welcome_email_enabled;
    }

    private function welcomeTemplateFor(Student $student): ?EmailTemplate
    {
        if (! Schema::hasTable('email_templates')) {
            return null;
        }

        $baseQuery = EmailTemplate::where('type', 'student_welcome')
            ->where('status', 'Active');

        if (Schema::hasColumn('email_templates', 'organization_id') && $student->organization_id) {
            $tenantTemplate = (clone $baseQuery)
                ->where('organization_id', $student->organization_id)
                ->latest()
                ->first();

            if ($tenantTemplate) {
                return $tenantTemplate;
            }
        }

        return $baseQuery
            ->when(Schema::hasColumn('email_templates', 'organization_id'), function ($query) {
                $query->orderByRaw('organization_id is null desc');
            })
            ->latest()
            ->first();
    }

    private function templateTokens(
        Student $student,
        string $siteName,
        string $organizationName,
        Collection $groups,
        string $myExamsUrl,
        string $coursesUrl,
        string $primaryColor,
        string $secondaryColor,
    ): array {
        $groupNames = $groups->pluck('group_name')->filter()->implode(', ');
        $groupBlock = $groupNames
            ? '<strong>Your exam group:</strong> ' . e($groupNames)
            : '';

        return [
            '{#studentName#}' => e($student->name ?: 'Student'),
            '{#studentEmail#}' => e($student->email),
            '{#siteName#}' => e($siteName),
            '{#organizationName#}' => e($organizationName),
            '{#myExamsUrl#}' => e($myExamsUrl),
            '{#coursesUrl#}' => e($coursesUrl),
            '{#groupNames#}' => e($groupNames ?: 'Not selected'),
            '{#groupBlock#}' => $groupBlock,
            '{#primaryColor#}' => e($primaryColor),
            '{#secondaryColor#}' => e($secondaryColor),
        ];
    }

    private function replaceTokens(string $content, array $tokens): string
    {
        return str_replace(array_keys($tokens), array_values($tokens), $content);
    }
}

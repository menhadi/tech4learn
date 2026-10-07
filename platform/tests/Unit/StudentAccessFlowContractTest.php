<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class StudentAccessFlowContractTest extends TestCase
{
    public function test_registration_uses_one_email_or_mobile_identifier(): void
    {
        $view = $this->source('resources/views/students/auth/student_signup.blade.php');
        $controller = $this->source('app/Http/Controllers/Students/StudentAuthController.php');

        $this->assertStringContainsString('name="contact"', $view);
        $this->assertStringNotContainsString('name="email"', $view);
        $this->assertStringNotContainsString('name="phone"', $view);
        $this->assertStringContainsString("filter_var(\$contact, FILTER_VALIDATE_EMAIL)", $controller);
        $this->assertStringContainsString("'phone' => \$this->phoneNumbers->normalize", $controller);
    }

    public function test_otp_resends_are_guarded_and_keep_the_previous_code_on_delivery_failure(): void
    {
        $service = $this->source('app/Services/StudentOtpService.php');
        $view = $this->source('resources/views/students/auth/student_verify_signup.blade.php');

        $this->assertStringContainsString('60 - (now()->timestamp - $lastSentAt)', $service);
        $this->assertStringContainsString('tooManyAttempts($this->hourlyLimiterKey($student), 5)', $service);
        $this->assertLessThan(
            strpos($service, '$student->forceFill'),
            strpos($service, 'match ($channel)')
        );
        $this->assertStringContainsString("json_encode(['1' => \$otp])", $service);
        $this->assertStringNotContainsString("strtok(\$message, ' ')", $service);
        $this->assertStringContainsString("__('ui.otp_security_copy')", $view);
        $translations = $this->source('lang/en/ui.php');
        $this->assertStringContainsString('Only the newest code will work.', $translations);
        $this->assertStringContainsString("__('ui.whatsapp_limit_copy')", $view);
        $this->assertStringContainsString('WhatsApp has already been tried twice.', $translations);
    }

    public function test_inactive_exams_are_excluded_from_public_student_and_guest_surfaces(): void
    {
        $exam = $this->source('app/Models/Exam.php');
        $website = $this->source('app/Http/Controllers/WebsiteController.php');
        $student = $this->source('app/Http/Controllers/Students/StudentExamsController.php');
        $guest = $this->source('app/Http/Controllers/Students/GuestExamsController.php');

        $this->assertStringContainsString('public function scopeActive', $exam);
        $this->assertStringContainsString("->where('exams.status', 'Active')", $website);
        $this->assertStringContainsString("->whereHas('exams', fn (\$examQuery) => \$examQuery->active())", $website);
        $this->assertStringContainsString("Exam::query()\n            ->active()", $student);
        $this->assertStringContainsString("Exam::query()\n            ->active()", $guest);
    }

    public function test_messaging_settings_are_grouped_after_send_email(): void
    {
        $provider = $this->source('app/Providers/ViewServiceProvider.php');
        $migration = $this->source('database/migrations/2026_07_31_000005_place_messaging_after_send_email.php');

        $this->assertStringContainsString("'configurations.messaging'", $provider);
        $this->assertStringContainsString("'send-email-form' => 32", $migration);
        $this->assertStringContainsString("'configurations.messaging' => 33", $migration);
    }

    public function test_ready_made_otp_templates_are_editable_and_used_by_delivery(): void
    {
        $model = $this->source('app/Models/SmsTemplate.php');
        $service = $this->source('app/Services/StudentOtpService.php');
        $controller = $this->source('app/Http/Controllers/SmsTemplateController.php');
        $view = $this->source('resources/views/sms_templates/action.blade.php');
        $migration = $this->source('database/migrations/2026_07_31_000004_seed_ready_made_messaging_templates.php');

        $this->assertStringContainsString("TYPE_STUDENT_OTP_SMS = 'student_otp_sms'", $model);
        $this->assertStringContainsString("TYPE_STUDENT_OTP_WHATSAPP = 'student_otp_whatsapp'", $model);
        $this->assertStringContainsString('SmsTemplate::TYPE_STUDENT_OTP_SMS', $service);
        $this->assertStringContainsString('SmsTemplate::TYPE_STUDENT_OTP_WHATSAPP', $service);
        $this->assertStringContainsString('ensureReadyMadeTemplates', $controller);
        $this->assertStringContainsString('name="type"', $view);
        $this->assertStringContainsString('copy-template-button', $view);
        $this->assertStringContainsString('sms_templates_org_name_unique', $migration);

        $messagingController = $this->source('app/Http/Controllers/MessagingSettingsController.php');
        $messagingView = $this->source('resources/views/configurations/messaging.blade.php');
        $this->assertStringContainsString('messageTemplates', $messagingController);
        $this->assertStringContainsString('settings.sms_template_id', $messagingController);
        $this->assertStringContainsString('settings.whatsapp_template_id', $messagingController);
        $this->assertStringContainsString('name="settings[sms_template_id]"', $messagingView);
        $this->assertStringContainsString('name="settings[whatsapp_template_id]"', $messagingView);
        $this->assertStringContainsString("'sms_template_id'", $service);
        $this->assertStringContainsString("'whatsapp_template_id'", $service);
    }

    public function test_instruction_acknowledgement_is_checked_by_default(): void
    {
        $view = $this->source('resources/views/students/exams/shared/instructions_content.blade.php');

        $this->assertStringContainsString('id="instruction_check" checked', $view);
        $this->assertStringContainsString('updateStartButtonState();', $view);
    }

    private function source(string $path): string
    {
        return str_replace("\r\n", "\n", (string) file_get_contents(dirname(__DIR__, 2).DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $path)));
    }
}
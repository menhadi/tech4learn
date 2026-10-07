<?php

namespace App\Services;

use App\Models\Configuration;
use App\Models\EmailTemplate;
use App\Models\SmsTemplate;
use App\Models\Student;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class StudentOtpService
{
    public function __construct(private readonly EmailService $emailService, private readonly PhoneNumberService $phones) {}

    public function send(Student $student, ?string $requestedChannel = null): string
    {
        $waitSeconds = $this->resendAvailableIn($student);
        if ($waitSeconds > 0) {
            throw new RuntimeException("Please wait {$waitSeconds} seconds before requesting another code.");
        }

        if (RateLimiter::tooManyAttempts($this->hourlyLimiterKey($student), 5)) {
            throw new RuntimeException('For security, a maximum of five verification codes can be sent per hour. Please try again later.');
        }

        $config = $this->configuration($student);
        $channel = $this->resolveChannel($student, $requestedChannel ?: $config?->student_otp_channel);
        $otp = (string) random_int(100000, 999999);

        match ($channel) {
            'email' => $this->sendEmail($student, $otp, $config),
            'sms' => $this->sendSms($student, $otp, $config),
            'whatsapp' => $this->sendWhatsApp($student, $otp, $config),
            default => throw new RuntimeException('Unsupported OTP delivery channel.'),
        };

        $student->forceFill([
            'otp' => Hash::make($otp),
            'otp_expires_at' => now()->addMinutes(10),
            'otp_channel' => $channel,
        ])->save();

        RateLimiter::hit($this->hourlyLimiterKey($student), 3600);
        Cache::put($this->lastSentKey($student), now()->timestamp, now()->addHour());
        if ($channel === 'whatsapp') {
            RateLimiter::hit($this->whatsAppLimiterKey($student), 3600);
        }

        return $channel;
    }

    public function resendAvailableIn(Student $student): int
    {
        $lastSentAt = (int) Cache::get($this->lastSentKey($student), 0);

        return $lastSentAt > 0 ? max(0, 60 - (now()->timestamp - $lastSentAt)) : 0;
    }

    public function whatsAppAttempts(Student $student): int
    {
        return RateLimiter::attempts($this->whatsAppLimiterKey($student));
    }

    public function verify(Student $student, string $otp): bool
    {
        if (! $student->otp || ! $student->otp_expires_at || now()->isAfter($student->otp_expires_at)) {
            return false;
        }

        $valid = str_starts_with((string) $student->otp, '$')
            ? Hash::check($otp, $student->otp)
            : hash_equals((string) $student->otp, $otp);

        if ($valid) {
            $updates = ['otp' => null, 'otp_expires_at' => null];
            if ($student->otp_channel === 'email') {
                $updates['email_verified_at'] = now();
            }
            if (in_array($student->otp_channel, ['sms', 'whatsapp'], true)) {
                $updates['phone_verified_at'] = now();
            }
            $student->forceFill($updates)->save();
        }

        return $valid;
    }

    public function availableChannels(Student $student): array
    {
        $config = $this->configuration($student);
        $channels = [];
        if ($student->email) {
            $channels['email'] = 'Email';
        }
        if ($student->phone && $config?->sms_provider) {
            $channels['sms'] = 'SMS';
        }
        if ($student->phone && $config?->whatsapp_provider) {
            $channels['whatsapp'] = 'WhatsApp';
        }

        return $channels;
    }

    public function destination(Student $student): string
    {
        return $student->otp_channel === 'email'
            ? $this->maskEmail($student->email)
            : $this->phones->mask($student->phone);
    }

    private function resolveChannel(Student $student, ?string $channel): string
    {
        $available = $this->availableChannels($student);
        if ($channel && isset($available[$channel])) {
            return $channel;
        }
        if ($student->phone && isset($available['sms'])) {
            return 'sms';
        }
        if ($student->phone && isset($available['whatsapp'])) {
            return 'whatsapp';
        }
        if ($student->email) {
            return 'email';
        }
        throw new RuntimeException('No OTP delivery method is available. Ask the administrator to configure SMS or WhatsApp.');
    }

    private function configuration(Student $student): ?Configuration
    {
        return $student->organization_id
            ? Configuration::where('organization_id', $student->organization_id)->first()
            : (function_exists('getConfiguration') ? getConfiguration() : Configuration::first());
    }

    private function sendEmail(Student $student, string $otp, ?Configuration $config): void
    {
        if (! $student->email) {
            throw new RuntimeException('This account does not have an email address.');
        }
        $template = EmailTemplate::where('type', 'otp')->where('status', 'Active')
            ->when(Schema::hasColumn('email_templates', 'organization_id'), fn ($q) => $q->where(fn ($x) => $x->where('organization_id', $student->organization_id)->orWhereNull('organization_id'))->orderByRaw('organization_id is null'))
            ->latest()->first();
        $tokens = ['{#studentName#}' => e($student->name ?: 'Student'), '{#otp#}' => e($otp), '{#siteName#}' => e($config?->name ?: config('app.name')), '{#primaryColor#}' => e($config?->theme_primary_color ?: '#0f766e'), '{#secondaryColor#}' => e($config?->theme_secondary_color ?: '#f59e0b')];
        $subject = str_replace(array_keys($tokens), array_values($tokens), $template?->subject ?: '{#otp#} is your {#siteName#} verification code');
        $html = str_replace(array_keys($tokens), array_values($tokens), $template?->description ?: '<p>Hello {#studentName#}, your verification code is <strong>{#otp#}</strong>.</p>');
        $this->emailService->sendEmail($student->email, $subject, $html, null, null, $student->organization_id);
    }

    private function sendSms(Student $student, string $otp, Configuration $config): void
    {
        $phone = $this->phones->normalize($student->phone, $config->default_country_code ?: '+91');
        $credentials = $config->messaging_credentials ?: [];
        $settings = $config->messaging_settings ?: [];
        $message = $this->message($student, $otp, $config, SmsTemplate::TYPE_STUDENT_OTP_SMS);

        match ($config->sms_provider) {
            'msg91' => $this->msg91Otp($phone, $otp, $credentials, $settings),
            'twilio' => $this->twilioMessage($phone, $message, $credentials, $settings, false, $otp),
            'twofactor' => $this->twoFactorOtp($phone, $otp, $credentials, $settings),
            default => throw new RuntimeException('Select an SMS provider in Messaging Settings.'),
        };
    }

    private function sendWhatsApp(Student $student, string $otp, Configuration $config): void
    {
        $phone = $this->phones->normalize($student->phone, $config->default_country_code ?: '+91');
        $credentials = $config->messaging_credentials ?: [];
        $settings = $config->messaging_settings ?: [];
        $message = $this->message($student, $otp, $config, SmsTemplate::TYPE_STUDENT_OTP_WHATSAPP);

        match ($config->whatsapp_provider) {
            'twilio' => $this->twilioMessage($phone, $message, $credentials, $settings, true, $otp),
            'msg91' => $this->msg91WhatsApp($phone, $otp, $credentials, $settings),
            default => throw new RuntimeException('Select a WhatsApp provider in Messaging Settings.'),
        };
    }

    private function msg91Otp(string $phone, string $otp, array $c, array $s): void
    {
        $response = Http::withHeaders(['authkey' => $c['msg91_auth_key'] ?? ''])
            ->withQueryParameters(['template_id' => $s['msg91_otp_template_id'] ?? '', 'mobile' => ltrim($phone, '+'), 'otp' => $otp])
            ->post('https://control.msg91.com/api/v5/otp');
        $response->throw();
    }

    private function twoFactorOtp(string $phone, string $otp, array $c, array $s): void
    {
        $key = $c['twofactor_api_key'] ?? '';
        Http::post('https://2factor.in/API/V1/'.rawurlencode($key).'/SMS/'.ltrim($phone, '+').'/'.$otp, ['template_name' => $s['twofactor_template_name'] ?? ''])->throw();
    }

    private function twilioMessage(string $phone, string $message, array $c, array $s, bool $whatsapp, string $otp): void
    {
        $sid = $c['twilio_account_sid'] ?? '';
        $token = $c['twilio_auth_token'] ?? '';
        $from = $whatsapp ? ($s['twilio_whatsapp_from'] ?? '') : ($s['twilio_sms_from'] ?? '');
        $prefix = $whatsapp ? 'whatsapp:' : '';
        $payload = ['To' => $prefix.$phone, 'From' => $prefix.$from];
        if ($whatsapp && filled($s['twilio_whatsapp_content_sid'] ?? null)) {
            $payload['ContentSid'] = $s['twilio_whatsapp_content_sid'];
            $payload['ContentVariables'] = json_encode(['1' => $otp]);
        } else {
            $payload['Body'] = $message;
        }

        Http::withBasicAuth($sid, $token)->asForm()
            ->post("https://api.twilio.com/2010-04-01/Accounts/{$sid}/Messages.json", $payload)
            ->throw();
    }

    private function msg91WhatsApp(string $phone, string $otp, array $c, array $s): void
    {
        Http::withHeaders(['authkey' => $c['msg91_auth_key'] ?? '', 'Content-Type' => 'application/json'])
            ->post('https://control.msg91.com/api/v5/whatsapp/whatsapp-outbound-message/bulk/', [
                'integrated_number' => $s['msg91_whatsapp_number'] ?? '',
                'content_type' => 'template',
                'payload' => ['to' => ltrim($phone, '+'), 'type' => 'template', 'template' => ['name' => $s['msg91_whatsapp_template'] ?? '', 'language' => ['code' => $s['msg91_whatsapp_language'] ?? 'en'], 'components' => [['type' => 'body', 'parameters' => [['type' => 'text', 'text' => $otp]]]]]],
            ])->throw();
    }

    private function message(Student $student, string $otp, ?Configuration $config, string $type): string
    {
        $template = null;
        if (Schema::hasTable('sms_templates')) {
            $settings = $config?->messaging_settings ?: [];
            $templateSetting = $type === SmsTemplate::TYPE_STUDENT_OTP_WHATSAPP
                ? 'whatsapp_template_id'
                : 'sms_template_id';
            $selectedTemplateId = $settings[$templateSetting] ?? null;

            $template = SmsTemplate::query()
                ->where('status', 'Active')
                ->where('description', 'like', '%{#otp#}%')
                ->when($selectedTemplateId, fn ($query) => $query->whereKey($selectedTemplateId))
                ->when(! $selectedTemplateId, fn ($query) => $query->where('type', $type))
                ->when(Schema::hasColumn('sms_templates', 'organization_id'), function ($query) use ($student) {
                    $student->organization_id
                        ? $query->where('organization_id', $student->organization_id)
                        : $query->whereNull('organization_id');
                })
                ->latest()
                ->first();
        }

        $fallback = $type === SmsTemplate::TYPE_STUDENT_OTP_WHATSAPP
            ? 'Your {#siteName#} verification code is {#otp#}. It expires in 10 minutes. Do not share it.'
            : '{#otp#} is your verification code for {#siteName#}. It expires in 10 minutes. Do not share it.';
        $body = html_entity_decode(strip_tags((string) ($template?->description ?: $fallback)));
        $body = trim((string) preg_replace('/\s+/', ' ', $body));

        return str_replace(
            ['{#otp#}', '{#siteName#}', '{#studentName#}'],
            [$otp, $config?->name ?: config('app.name'), $student->name ?: 'Student'],
            $body
        );
    }

    private function hourlyLimiterKey(Student $student): string
    {
        return 'student-otp:hourly:'.($student->organization_id ?: 'platform').':'.$student->getKey();
    }

    private function whatsAppLimiterKey(Student $student): string
    {
        return 'student-otp:whatsapp:'.($student->organization_id ?: 'platform').':'.$student->getKey();
    }

    private function lastSentKey(Student $student): string
    {
        return 'student-otp:last-sent:'.($student->organization_id ?: 'platform').':'.$student->getKey();
    }

    private function maskEmail(?string $email): string
    {
        if (! $email || ! str_contains($email, '@')) {
            return '';
        }
        [$name, $domain] = explode('@', $email, 2);

        return substr($name, 0, 1).str_repeat('*', max(2, strlen($name) - 1)).'@'.$domain;
    }
}

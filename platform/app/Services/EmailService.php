<?php

namespace App\Services;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;
use App\Models\EmailSetting;
use App\Models\Configuration;
use App\Models\EmailLog; // New Model Import kiya
use App\Support\SaasAccess;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class EmailService
{
    public function sendEmail(?string $email, string $subject, string $html, ?string $customFromAddress = null, ?string $customFromName = null, ?int $organizationId = null): bool
    {
        try {
            // 1. Settings Load Karo
            $organizationId ??= SaasAccess::organization()?->id;
            $email = trim((string) $email);
            if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                Log::warning('Email skipped because the recipient address is missing or invalid.', [
                    'organization_id' => $organizationId,
                ]);
                return false;
            }
            $s = EmailSetting::query()
                ->when($organizationId && Schema::hasColumn('email_settings', 'organization_id'), function ($query) use ($organizationId) {
                    $query->where('organization_id', $organizationId);
                })
                ->first();
            $cfg = Configuration::query()
                ->when($organizationId && Schema::hasColumn('configurations', 'organization_id'), function ($query) use ($organizationId) {
                    $query->where('organization_id', $organizationId);
                })
                ->first();

            $fromAddress = $customFromAddress ?: ($cfg->email ?? config('mail.from.address'));
            $fromName = trim((string) ($customFromName ?: ($cfg->name ?? config('app.name'))));
            if ($fromName === '') $fromName = 'ExamElite';
            $senderAddress = $fromAddress;

            // 2. Config Runtime pe Set karo
            if ($s && $s->type === 'smtp') {
                Config::set('mail.default', 'smtp');
                Config::set('mail.mailers.smtp.host', $s->host);
                Config::set('mail.mailers.smtp.port', $s->port);
                // Auto detect encryption based on port
                $encryption = ((int)$s->port === 465) ? 'ssl' : (((int)$s->port === 587) ? 'tls' : ($s->tls ? 'tls' : 'ssl'));
                Config::set('mail.mailers.smtp.encryption', $encryption);
                
                Config::set('mail.mailers.smtp.username', $s->username);
                Config::set('mail.mailers.smtp.password', $s->password);
                
                $senderAddress = $customFromAddress ?: ($s->username ?: $fromAddress);
                Config::set('mail.from.address', $senderAddress);
                Config::set('mail.from.name', $fromName);
            } else {
                // Local Fallback
                Config::set('mail.default', 'sendmail');
                Config::set('mail.from.address', $fromAddress);
                Config::set('mail.from.name', $fromName);
            }

            if (! is_string($senderAddress) || ! filter_var($senderAddress, FILTER_VALIDATE_EMAIL)) {
                Log::warning('Email skipped because the sender address is missing or invalid.', [
                    'organization_id' => $organizationId,
                    'recipient' => $email,
                ]);
                return false;
            }

            // 3. Email Send Karo
            Mail::html($html, function ($message) use ($email, $subject, $senderAddress, $fromName) {
                $message->to($email)
                        ->subject($subject)->from($senderAddress, $fromName);
            });

            // 4. Success Log DB me save karo
            EmailLog::create([
                'organization_id' => $organizationId,
                'to_email' => $email,
                'subject' => $subject,
                'status' => 'Success',
                'error_message' => null
            ]);

            return true;

        } catch (\Throwable $e) {
            // 5. Fail hua to Error Log DB me save karo
            // Note: Hum yahan 'throw' nahi kar rahe, taaki user ka process na ruke (Fail-Safe)
            try {
                EmailLog::create([
                    'organization_id' => $organizationId,
                    'to_email' => (string) $email,
                    'subject' => $subject,
                    'status' => 'Failed',
                    'error_message' => $e->getMessage()
                ]);
            } catch (\Throwable $logError) {
                Log::error('Unable to persist failed email log: '.$logError->getMessage());
            }
            // Developer debugging ke liye file log bhi rakho
            Log::error("Email sending failed to $email: " . $e->getMessage());

            return false;
        }
    }
}

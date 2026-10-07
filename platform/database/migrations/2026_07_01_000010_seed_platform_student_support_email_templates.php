<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('email_templates')) {
            return;
        }

        $organizationId = Schema::hasColumn('email_templates', 'organization_id') && Schema::hasTable('organizations')
            ? DB::table('organizations')->where('slug', 'examelite')->value('id')
            : null;

        $this->seedTemplate(
            'student_pending_admin_alert',
            'Student Pending Verification Alert',
            'Student needs verification help: {#studentName#}',
            $this->pendingAdminTemplate(),
            $organizationId
        );

        $this->seedTemplate(
            'student_admin_activated',
            'Student Admin Activation Email',
            'Your {#siteName#} account has been activated',
            $this->studentActivatedTemplate(),
            $organizationId
        );
    }

    public function down(): void
    {
        if (! Schema::hasTable('email_templates')) {
            return;
        }

        DB::table('email_templates')
            ->whereIn('type', ['student_pending_admin_alert', 'student_admin_activated'])
            ->when(Schema::hasColumn('email_templates', 'organization_id'), function ($query) {
                $query->whereNotNull('organization_id');
            })
            ->delete();
    }

    private function seedTemplate(string $type, string $name, string $subject, string $description, ?int $organizationId): void
    {
        $attributes = ['type' => $type];

        if (Schema::hasColumn('email_templates', 'organization_id')) {
            $attributes['organization_id'] = $organizationId;
        }

        $existing = DB::table('email_templates')->where($attributes)->first();

        if (! $existing) {
            $existing = DB::table('email_templates')
                ->where('name', $name)
                ->first();
        }

        if ($existing) {
            $updates = [
                'name' => $name,
                'subject' => $subject,
                'description' => $existing->description ?: $description,
                'status' => $existing->status ?: 'Active',
                'updated_at' => now(),
            ];

            if (Schema::hasColumn('email_templates', 'organization_id') && $organizationId) {
                $updates['organization_id'] = $organizationId;
            }

            DB::table('email_templates')->where('id', $existing->id)->update($updates);

            return;
        }

        DB::table('email_templates')->insert(array_merge($attributes, [
            'name' => $name,
            'subject' => $subject,
            'description' => $description,
            'status' => 'Active',
            'created_at' => now(),
            'updated_at' => now(),
        ]));
    }

    private function pendingAdminTemplate(): string
    {
        return <<<'HTML'
<div style="margin:0;background:#f3f8f7;color:#111827;font-family:Arial,Helvetica,sans-serif;">
    <div style="max-width:680px;margin:0 auto;padding:26px 14px;">
        <div style="background:#ffffff;border:1px solid #d7e2df;border-radius:14px;overflow:hidden;">
            <div style="padding:24px 28px;background:{#primaryColor#};color:#ffffff;">
                <div style="font-size:22px;font-weight:800;">Student needs verification help</div>
                <div style="font-size:14px;margin-top:8px;opacity:.92;">A student registered but has not completed OTP verification.</div>
            </div>
            <div style="padding:26px 28px;font-size:15px;line-height:1.7;color:#334155;">
                <p style="margin:0 0 14px;"><strong style="color:#111827;">Student:</strong> {#studentName#}</p>
                <p style="margin:0 0 14px;"><strong style="color:#111827;">Email:</strong> {#studentEmail#}</p>
                <p style="margin:0 0 14px;"><strong style="color:#111827;">Phone:</strong> {#studentPhone#}</p>
                <p style="margin:0 0 14px;"><strong style="color:#111827;">Group:</strong> {#groupNames#}</p>
                <p style="margin:0 0 20px;">If the student contacts you, you can verify details and activate the account manually from Students.</p>
                <a href="{#studentsAdminUrl#}" style="display:inline-block;background:{#primaryColor#};color:#ffffff;text-decoration:none;font-weight:800;border-radius:8px;padding:12px 18px;">Open Students</a>
            </div>
        </div>
    </div>
</div>
HTML;
    }

    private function studentActivatedTemplate(): string
    {
        return <<<'HTML'
<div style="margin:0;background:#f3f8f7;color:#111827;font-family:Arial,Helvetica,sans-serif;">
    <div style="max-width:680px;margin:0 auto;padding:26px 14px;">
        <div style="background:#ffffff;border:1px solid #d7e2df;border-radius:14px;overflow:hidden;">
            <div style="padding:28px 28px 24px;background:{#primaryColor#};color:#ffffff;">
                <div style="font-size:24px;font-weight:800;line-height:1.25;">Your account has been activated</div>
                <div style="font-size:14px;margin-top:8px;opacity:.92;">You can now log in to {#siteName#}</div>
            </div>
            <div style="padding:26px 28px;">
                <p style="font-size:16px;line-height:1.6;margin:0 0 16px;">Hello {#studentName#},</p>
                <p style="font-size:16px;line-height:1.6;margin:0 0 16px;">
                    We noticed you were unable to complete email verification. Your account has now been activated by the administrator.
                </p>
                <div style="background:#f8fafc;border:1px solid #d7e2df;border-radius:12px;padding:18px;margin:20px 0;color:#334155;font-size:15px;line-height:1.7;">
                    Please log in using the password you created during registration. If you do not remember it, use the reset password option before logging in.
                </div>
                <div style="margin:24px 0 10px;">
                    <a href="{#loginUrl#}" style="display:inline-block;background:{#primaryColor#};color:#ffffff;text-decoration:none;font-weight:800;border-radius:8px;padding:13px 20px;margin:0 8px 10px 0;">Log In</a>
                    <a href="{#resetPasswordUrl#}" style="display:inline-block;background:{#secondaryColor#};color:#ffffff;text-decoration:none;font-weight:800;border-radius:8px;padding:13px 20px;margin:0 0 10px 0;">Reset Password</a>
                </div>
                <p style="font-size:14px;line-height:1.6;color:#5f6b7a;margin:20px 0 0;">Regards,<br>{#organizationName#}</p>
            </div>
        </div>
    </div>
</div>
HTML;
    }
};

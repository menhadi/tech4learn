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

        $this->ensureTemplate(
            type: 'student_pending_admin_alert',
            name: 'Student Pending Verification Alert',
            subject: 'Student needs verification help: {#studentName#}',
            description: '<p>{#studentName#} registered but has not completed OTP verification. Please open Students, check the pending account, and activate it manually if details are correct.</p>',
            organizationId: $organizationId
        );

        $this->ensureTemplate(
            type: 'student_admin_activated',
            name: 'Student Admin Activation Email',
            subject: 'Your {#siteName#} account has been activated',
            description: '<p>Hello {#studentName#},</p><p>We noticed you were unable to complete email verification. Your account has now been activated by the administrator.</p><p>Please log in using the password you created during registration. If you do not remember it, use the reset password option.</p><p><a href="{#loginUrl#}" style="display:inline-block;background:{#primaryColor#};color:#ffffff;text-decoration:none;padding:12px 18px;border-radius:8px;font-weight:700;">Log In</a></p><p>Regards,<br>{#organizationName#}</p>',
            organizationId: $organizationId
        );
    }

    public function down(): void
    {
        // Keep templates because admins may edit them after migration.
    }

    private function ensureTemplate(string $type, string $name, string $subject, string $description, ?int $organizationId): void
    {
        $query = DB::table('email_templates')->where('type', $type);

        if (Schema::hasColumn('email_templates', 'organization_id') && $organizationId) {
            $existingForOrganization = (clone $query)->where('organization_id', $organizationId)->first();

            if ($existingForOrganization) {
                DB::table('email_templates')->where('id', $existingForOrganization->id)->update([
                    'name' => $name,
                    'subject' => $existingForOrganization->subject ?: $subject,
                    'description' => $existingForOrganization->description ?: $description,
                    'status' => $existingForOrganization->status ?: 'Active',
                    'updated_at' => now(),
                ]);

                return;
            }
        }

        $existing = $query->first() ?: DB::table('email_templates')->where('name', $name)->first();

        if ($existing) {
            $updates = [
                'type' => $type,
                'name' => $name,
                'subject' => $existing->subject ?: $subject,
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

        $data = [
            'type' => $type,
            'name' => $name,
            'subject' => $subject,
            'description' => $description,
            'status' => 'Active',
            'created_at' => now(),
            'updated_at' => now(),
        ];

        if (Schema::hasColumn('email_templates', 'organization_id')) {
            $data['organization_id'] = $organizationId;
        }

        DB::table('email_templates')->insert($data);
    }
};

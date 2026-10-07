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

        $organizationIds = Schema::hasTable('organizations')
            ? DB::table('organizations')->pluck('id')->all()
            : [null];

        if (empty($organizationIds)) {
            $organizationIds = [null];
        }

        foreach ($organizationIds as $organizationId) {
            $existing = DB::table('email_templates')
                ->where('type', 'admin_password_reset')
                ->when(Schema::hasColumn('email_templates', 'organization_id'), function ($query) use ($organizationId) {
                    $organizationId === null
                        ? $query->whereNull('organization_id')
                        : $query->where('organization_id', $organizationId);
                })
                ->first();

            if ($existing) {
                continue;
            }

            $baseName = 'Administrator Password Reset [' . ($organizationId === null ? 'Platform' : 'Org ' . $organizationId) . ']';
            $name = $baseName;
            $suffix = 2;

            while (DB::table('email_templates')->where('name', $name)->exists()) {
                $name = $baseName . ' ' . $suffix++;
            }

            $data = [
                'name' => $name,
                'type' => 'admin_password_reset',
                'status' => 'Active',
                'description' => '<div style="font-family:Arial,sans-serif;max-width:620px;margin:auto;padding:28px;border:1px solid #e2e8f0;border-radius:16px"><h2 style="color:{#primaryColor#}">Administrator password reset</h2><p>Hello {#name#},</p><p>Use this verification code to reset your {#siteName#} administrator password:</p><p style="font-size:30px;font-weight:700;letter-spacing:8px;color:{#primaryColor#}">{#code#}</p><p>This code expires in 10 minutes. If you did not request it, you can safely ignore this email.</p><p>Need help? Contact {#siteEmailContact#}.</p></div>',
                'created_at' => now(),
                'updated_at' => now(),
            ];

            if (Schema::hasColumn('email_templates', 'organization_id')) {
                $data['organization_id'] = $organizationId;
            }
            if (Schema::hasColumn('email_templates', 'subject')) {
                $data['subject'] = 'Reset your {#siteName#} administrator password';
            }

            DB::table('email_templates')->insert($data);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('email_templates')) {
            DB::table('email_templates')->where('type', 'admin_password_reset')->delete();
        }
    }
};

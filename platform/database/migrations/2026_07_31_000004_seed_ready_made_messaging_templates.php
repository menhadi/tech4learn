<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TYPES = [
        'student_otp_sms' => [
            'name' => 'Student OTP - SMS',
            'description' => '{#otp#} is your verification code for {#siteName#}. It expires in 10 minutes. Do not share it.',
        ],
        'student_otp_whatsapp' => [
            'name' => 'Student OTP - WhatsApp',
            'description' => 'Your {#siteName#} verification code is {#otp#}. It expires in 10 minutes. Do not share it.',
        ],
    ];

    public function up(): void
    {
        if (! Schema::hasTable('sms_templates')) {
            return;
        }

        $hasOrganization = Schema::hasColumn('sms_templates', 'organization_id');
        if ($hasOrganization) {
            Schema::table('sms_templates', function (Blueprint $table) {
                $table->dropUnique('sms_templates_name_unique');
                $table->unique(['organization_id', 'name'], 'sms_templates_org_name_unique');
            });
        }

        $organizationIds = $hasOrganization && Schema::hasTable('organizations')
            ? DB::table('organizations')->pluck('id')->all()
            : [null];

        foreach ($organizationIds ?: [null] as $organizationId) {
            foreach (self::TYPES as $type => $preset) {
                $key = ['type' => $type];
                if ($hasOrganization) {
                    $key['organization_id'] = $organizationId;
                }

                $existing = DB::table('sms_templates')->where($key)->exists();
                if ($existing) {
                    continue;
                }

                DB::table('sms_templates')->insert(array_merge($key, [
                    'name' => $preset['name'],
                    'description' => $preset['description'],
                    'status' => 'Active',
                    'dlt_template_id' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]));
            }
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('sms_templates')) {
            return;
        }

        DB::table('sms_templates')->whereIn('type', array_keys(self::TYPES))->delete();

        if (! Schema::hasColumn('sms_templates', 'organization_id')) {
            return;
        }

        Schema::table('sms_templates', function (Blueprint $table) {
            $table->dropUnique('sms_templates_org_name_unique');
        });

        $hasDuplicateNames = DB::table('sms_templates')
            ->select('name')
            ->groupBy('name')
            ->havingRaw('COUNT(*) > 1')
            ->exists();

        if (! $hasDuplicateNames) {
            Schema::table('sms_templates', function (Blueprint $table) {
                $table->unique('name', 'sms_templates_name_unique');
            });
        }
    }
};
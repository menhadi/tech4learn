<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $defaultOrganizationId = Schema::hasTable('organizations')
            ? DB::table('organizations')->where('slug', 'examelite')->value('id')
            : null;

        foreach (['email_settings', 'email_logs', 'email_templates'] as $table) {
            if (! Schema::hasTable($table) || Schema::hasColumn($table, 'organization_id')) {
                continue;
            }

            Schema::table($table, function (Blueprint $table) {
                $table->unsignedBigInteger('organization_id')->nullable()->after('id')->index();
            });

            if ($defaultOrganizationId) {
                DB::table($table)->whereNull('organization_id')->update([
                    'organization_id' => $defaultOrganizationId,
                ]);
            }
        }
    }

    public function down(): void
    {
        foreach (['email_settings', 'email_logs', 'email_templates'] as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'organization_id')) {
                continue;
            }

            Schema::table($table, function (Blueprint $table) {
                $table->dropColumn('organization_id');
            });
        }
    }
};

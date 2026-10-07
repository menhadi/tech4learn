<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('sms_templates') || Schema::hasColumn('sms_templates', 'organization_id')) {
            return;
        }

        Schema::table('sms_templates', function (Blueprint $table) {
            $table->unsignedBigInteger('organization_id')->nullable()->after('id')->index();
        });

        if (! Schema::hasTable('organizations')) {
            return;
        }

        $platformOrganizationId = DB::table('organizations')->where('slug', 'examelite')->value('id')
            ?: DB::table('organizations')->value('id');

        if ($platformOrganizationId) {
            DB::table('sms_templates')
                ->whereNull('organization_id')
                ->update(['organization_id' => $platformOrganizationId]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('sms_templates') || ! Schema::hasColumn('sms_templates', 'organization_id')) {
            return;
        }

        Schema::table('sms_templates', function (Blueprint $table) {
            $table->dropIndex(['organization_id']);
            $table->dropColumn('organization_id');
        });
    }
};

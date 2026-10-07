<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ugroups') || Schema::hasColumn('ugroups', 'organization_id')) {
            return;
        }

        Schema::table('ugroups', function (Blueprint $table) {
            $table->unsignedBigInteger('organization_id')->nullable()->after('id')->index();
        });

        $defaultOrganizationId = DB::table('organizations')->where('slug', 'examelite')->value('id')
            ?: DB::table('organizations')->where('status', 'active')->value('id');

        if ($defaultOrganizationId) {
            DB::table('ugroups')
                ->whereNull('organization_id')
                ->update(['organization_id' => $defaultOrganizationId]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('ugroups') || ! Schema::hasColumn('ugroups', 'organization_id')) {
            return;
        }

        Schema::table('ugroups', function (Blueprint $table) {
            $table->dropIndex('ugroups_organization_id_index');
            $table->dropColumn('organization_id');
        });
    }
};

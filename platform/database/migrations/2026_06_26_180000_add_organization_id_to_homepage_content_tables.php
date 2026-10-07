<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $tables = [
        'hero_sliders',
        'features',
        'counters',
        'testimonials',
        'titles',
    ];

    public function up(): void
    {
        $defaultOrganizationId = DB::table('organizations')->where('slug', 'examelite')->value('id')
            ?: DB::table('organizations')->where('status', 'active')->value('id');

        foreach ($this->tables as $tableName) {
            if (! Schema::hasTable($tableName) || Schema::hasColumn($tableName, 'organization_id')) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) {
                $table->unsignedBigInteger('organization_id')->nullable()->after('id')->index();
            });

            if ($defaultOrganizationId) {
                DB::table($tableName)
                    ->whereNull('organization_id')
                    ->update(['organization_id' => $defaultOrganizationId]);
            }
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $tableName) {
            if (! Schema::hasTable($tableName) || ! Schema::hasColumn($tableName, 'organization_id')) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                $table->dropIndex("{$tableName}_organization_id_index");
                $table->dropColumn('organization_id');
            });
        }
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $tables = [
        'packages',
        'exams',
        'questions',
        'groups',
        'category',
        'students',
        'orders',
        'sales_reports',
        'configurations',
        'website_pages',
    ];

    public function up(): void
    {
        foreach ($this->tables as $tableName) {
            if (! Schema::hasTable($tableName) || Schema::hasColumn($tableName, 'organization_id')) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) {
                $table->unsignedBigInteger('organization_id')->nullable()->after('id');
                $table->index('organization_id');
            });
        }

        $defaultOrganizationId = DB::table('organizations')->where('slug', 'examelite')->value('id');

        if ($defaultOrganizationId) {
            foreach ($this->tables as $tableName) {
                if (! Schema::hasTable($tableName) || ! Schema::hasColumn($tableName, 'organization_id')) {
                    continue;
                }

                DB::table($tableName)
                    ->whereNull('organization_id')
                    ->update(['organization_id' => $defaultOrganizationId]);
            }
        }
    }

    public function down(): void
    {
        foreach (array_reverse($this->tables) as $tableName) {
            if (! Schema::hasTable($tableName) || ! Schema::hasColumn($tableName, 'organization_id')) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) {
                $table->dropIndex([$table->getTable() . '_organization_id_index']);
            });
        }

        foreach (array_reverse($this->tables) as $tableName) {
            if (! Schema::hasTable($tableName) || ! Schema::hasColumn($tableName, 'organization_id')) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) {
                $table->dropColumn('organization_id');
            });
        }
    }
};

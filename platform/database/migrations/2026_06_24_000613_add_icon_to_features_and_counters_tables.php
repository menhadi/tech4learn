<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['features', 'counters'] as $tableName) {
            if (Schema::hasTable($tableName) && ! Schema::hasColumn($tableName, 'icon')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->string('icon')->nullable()->after('image_url');
                });
            }
        }
    }

    public function down(): void
    {
        foreach (['features', 'counters'] as $tableName) {
            if (Schema::hasTable($tableName) && Schema::hasColumn($tableName, 'icon')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->dropColumn('icon');
                });
            }
        }
    }
};

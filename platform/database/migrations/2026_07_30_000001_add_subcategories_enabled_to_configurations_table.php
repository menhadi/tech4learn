<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('configurations', 'subcategories_enabled')) {
            Schema::table('configurations', function (Blueprint $table) {
                $table->boolean('subcategories_enabled')->default(true)->after('allow_guest_exam_attempts');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('configurations', 'subcategories_enabled')) {
            Schema::table('configurations', function (Blueprint $table) {
                $table->dropColumn('subcategories_enabled');
            });
        }
    }
};
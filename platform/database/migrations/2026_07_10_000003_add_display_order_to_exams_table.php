<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('exams', 'display_order')) {
            Schema::table('exams', function (Blueprint $table) {
                $table->unsignedInteger('display_order')->nullable()->index();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('exams', 'display_order')) {
            Schema::table('exams', function (Blueprint $table) {
                $table->dropColumn('display_order');
            });
        }
    }
};
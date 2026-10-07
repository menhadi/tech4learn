<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exams', function (Blueprint $table) {
            $table->timestamp('start_date')->nullable()->change();
            $table->timestamp('end_date')->nullable()->change();
            $table->boolean('offline_enabled')->default(false)->after('end_date')->index();
        });
    }

    public function down(): void
    {
        DB::table('exams')->whereNull('start_date')->update(['start_date' => now()]);
        DB::table('exams')->whereNull('end_date')->update(['end_date' => now()->addYears(10)]);

        Schema::table('exams', function (Blueprint $table) {
            $table->dropIndex(['offline_enabled']);
            $table->dropColumn('offline_enabled');
            $table->timestamp('start_date')->nullable(false)->change();
            $table->timestamp('end_date')->nullable(false)->change();
        });
    }
};

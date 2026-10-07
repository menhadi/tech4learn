<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('exams', function (Blueprint $table) {
            // Check if column already exists to avoid duplication error
            if (!Schema::hasColumn('exams', 'calculator_allowed')) {
                $table->boolean('calculator_allowed')->default(0)->after('proctor');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('exams', function (Blueprint $table) {
            if (Schema::hasColumn('exams', 'calculator_allowed')) {
                $table->dropColumn('calculator_allowed');
            }
        });
    }
};
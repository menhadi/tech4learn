<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('exam_results') || Schema::hasColumn('exam_results', 'ai_performance_analysis')) {
            return;
        }

        Schema::table('exam_results', function (Blueprint $table) {
            $table->json('ai_performance_analysis')->nullable()->after('tolerance_count');
            $table->string('ai_performance_analysis_source', 30)->nullable()->after('ai_performance_analysis');
            $table->string('ai_performance_analysis_provider', 50)->nullable()->after('ai_performance_analysis_source');
            $table->timestamp('ai_performance_analysis_generated_at')->nullable()->after('ai_performance_analysis_provider');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('exam_results') || ! Schema::hasColumn('exam_results', 'ai_performance_analysis')) {
            return;
        }

        Schema::table('exam_results', function (Blueprint $table) {
            $table->dropColumn([
                'ai_performance_analysis',
                'ai_performance_analysis_source',
                'ai_performance_analysis_provider',
                'ai_performance_analysis_generated_at',
            ]);
        });
    }
};

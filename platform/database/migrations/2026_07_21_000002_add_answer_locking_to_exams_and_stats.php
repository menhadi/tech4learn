<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('exams', 'allow_answer_change')) {
            Schema::table('exams', function (Blueprint $table) {
                $table->boolean('allow_answer_change')->default(true)->after('option_shuffle');
            });
        }

        if (!Schema::hasColumn('exam_stats', 'answer_locked_at')) {
            Schema::table('exam_stats', function (Blueprint $table) {
                $table->timestamp('answer_locked_at')->nullable()->after('answered');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('exam_stats', 'answer_locked_at')) {
            Schema::table('exam_stats', fn (Blueprint $table) => $table->dropColumn('answer_locked_at'));
        }
        if (Schema::hasColumn('exams', 'allow_answer_change')) {
            Schema::table('exams', fn (Blueprint $table) => $table->dropColumn('allow_answer_change'));
        }
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('questions', 'question_code')) {
            Schema::table('questions', fn (Blueprint $table) => $table->string('question_code', 64)->nullable()->after('id'));
            if (DB::getDriverName() === 'mysql') {
                DB::statement("UPDATE questions SET question_code = CONCAT('EWQ-', LPAD(id, 10, '0')) WHERE question_code IS NULL");
            } else {
                DB::table('questions')->select('id')->whereNull('question_code')->orderBy('id')->chunkById(500, function ($rows) {
                    foreach ($rows as $row) {
                        DB::table('questions')->where('id', $row->id)->update(['question_code' => 'EWQ-'.str_pad((string) $row->id, 10, '0', STR_PAD_LEFT)]);
                    }
                });
            }
            Schema::table('questions', fn (Blueprint $table) => $table->unique('question_code'));
        }

        Schema::table('configurations', function (Blueprint $table) {
            if (! Schema::hasColumn('configurations', 'ai_regeneration_quality_prompt')) $table->longText('ai_regeneration_quality_prompt')->nullable();
            if (! Schema::hasColumn('configurations', 'ai_regeneration_mcq_prompt')) $table->longText('ai_regeneration_mcq_prompt')->nullable();
            if (! Schema::hasColumn('configurations', 'ai_regeneration_true_false_prompt')) $table->longText('ai_regeneration_true_false_prompt')->nullable();
            if (! Schema::hasColumn('configurations', 'ai_regeneration_fill_blank_prompt')) $table->longText('ai_regeneration_fill_blank_prompt')->nullable();
            if (! Schema::hasColumn('configurations', 'ai_regeneration_subjective_prompt')) $table->longText('ai_regeneration_subjective_prompt')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('configurations', fn (Blueprint $table) => $table->dropColumn([
            'ai_regeneration_quality_prompt', 'ai_regeneration_mcq_prompt', 'ai_regeneration_true_false_prompt',
            'ai_regeneration_fill_blank_prompt', 'ai_regeneration_subjective_prompt',
        ]));
        Schema::table('questions', function (Blueprint $table) {
            $table->dropUnique(['question_code']);
            $table->dropColumn('question_code');
        });
    }
};

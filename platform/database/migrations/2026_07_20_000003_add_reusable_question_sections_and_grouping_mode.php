<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('question_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('name');
            $table->unsignedInteger('display_order')->default(0);
            $table->boolean('status')->default(true);
            $table->timestamps();
            $table->unique(['organization_id', 'name']);
        });

        Schema::create('question_section_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('question_section_id')->constrained('question_sections')->cascadeOnDelete();
            $table->foreignId('group_id')->constrained('groups')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['question_section_id', 'group_id']);
        });

        Schema::table('questions', function (Blueprint $table) {
            $table->foreignId('question_section_id')->nullable()->after('subject_id')
                ->constrained('question_sections')->nullOnDelete();
        });

        Schema::table('exam_sections', function (Blueprint $table) {
            $table->foreignId('question_section_id')->nullable()->after('exam_id')
                ->constrained('question_sections')->nullOnDelete();
            $table->unique(['exam_id', 'question_section_id']);
        });

        Schema::table('exams', function (Blueprint $table) {
            $table->string('grouping_mode', 20)->default('subject')->after('timer_mode');
        });

        DB::table('exams')->where('timer_mode', 'section')->update(['grouping_mode' => 'section']);
        // Preserve any custom exam sections created by the earlier implementation.
        DB::table('exam_sections')->join('exams', 'exams.id', '=', 'exam_sections.exam_id')
            ->select('exam_sections.id', 'exam_sections.exam_id', 'exam_sections.name', 'exam_sections.display_order', 'exams.organization_id')
            ->orderBy('exam_sections.id')->get()->each(function ($legacy) {
                $definitionId = DB::table('question_sections')->where('organization_id', $legacy->organization_id)->where('name', $legacy->name)->value('id');
                if (! $definitionId) {
                    $definitionId = DB::table('question_sections')->insertGetId([
                        'organization_id' => $legacy->organization_id, 'name' => $legacy->name,
                        'display_order' => $legacy->display_order ?: 0, 'status' => true,
                        'created_at' => now(), 'updated_at' => now(),
                    ]);
                }
                $groupIds = DB::table('exam_groups')->where('exam_id', $legacy->exam_id)->pluck('group_id');
                foreach ($groupIds as $groupId) {
                    DB::table('question_section_groups')->insertOrIgnore([
                        'question_section_id' => $definitionId, 'group_id' => $groupId,
                        'created_at' => now(), 'updated_at' => now(),
                    ]);
                }
                DB::table('exam_sections')->where('id', $legacy->id)->update(['question_section_id' => $definitionId]);
                $questionIds = DB::table('exam_questions')->where('exam_id', $legacy->exam_id)->where('exam_section_id', $legacy->id)->pluck('question_id');
                DB::table('questions')->whereIn('id', $questionIds)->whereNull('question_section_id')->update(['question_section_id' => $definitionId]);
            });
    }

    public function down(): void
    {
        Schema::table('exams', fn (Blueprint $table) => $table->dropColumn('grouping_mode'));
        Schema::table('exam_sections', function (Blueprint $table) {
            $table->dropUnique(['exam_id', 'question_section_id']);
            $table->dropConstrainedForeignId('question_section_id');
        });
        Schema::table('questions', fn (Blueprint $table) => $table->dropConstrainedForeignId('question_section_id'));
        Schema::dropIfExists('question_section_groups');
        Schema::dropIfExists('question_sections');
    }
};

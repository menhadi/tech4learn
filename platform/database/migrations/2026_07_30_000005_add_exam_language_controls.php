<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exam_languages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_id')->constrained()->cascadeOnDelete();
            $table->foreignId('language_id')->constrained()->cascadeOnDelete();
            $table->string('translation_status', 20)->default('pending')->index();
            $table->text('last_error')->nullable();
            $table->timestamp('translating_at')->nullable();
            $table->timestamp('translated_at')->nullable();
            $table->timestamps();
            $table->unique(['exam_id', 'language_id']);
        });

        Schema::create('exam_language_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_id')->constrained()->cascadeOnDelete();
            $table->foreignId('language_id')->constrained()->cascadeOnDelete();
            $table->string('name')->nullable();
            $table->longText('instruction')->nullable();
            $table->longText('syllabus')->nullable();
            $table->string('translated_by')->nullable();
            $table->timestamps();
            $table->unique(['exam_id', 'language_id']);
        });

        Schema::table('exam_results', function (Blueprint $table) {
            $table->foreignId('language_id')->nullable()->after('exam_id')->constrained('languages')->nullOnDelete();
        });

        if (Schema::hasTable('question_langs')) {
            Schema::table('question_langs', function (Blueprint $table) {
                if (! Schema::hasColumn('question_langs', 'si_answer1')) {
                    $table->longText('si_answer1')->nullable()->after('fill_blank');
                }
                if (! Schema::hasColumn('question_langs', 'translated_by')) {
                    $table->string('translated_by')->nullable()->after('si_answer1');
                }
            });

            DB::table('question_langs')
                ->select('question_id', 'language_id', DB::raw('MAX(id) as keep_id'))
                ->groupBy('question_id', 'language_id')
                ->havingRaw('COUNT(*) > 1')
                ->orderBy('question_id')
                ->get()
                ->each(function ($duplicate) {
                    DB::table('question_langs')
                        ->where('question_id', $duplicate->question_id)
                        ->where('language_id', $duplicate->language_id)
                        ->where('id', '!=', $duplicate->keep_id)
                        ->delete();
                });

            Schema::table('question_langs', function (Blueprint $table) {
                $table->unique(['question_id', 'language_id']);
            });
        }

        DB::table('exams')
            ->select('id', 'organization_id')
            ->orderBy('id')
            ->chunkById(200, function ($exams) {
                foreach ($exams as $exam) {
                    $englishId = DB::table('languages')
                        ->where('organization_id', $exam->organization_id)
                        ->where('is_enabled', true)
                        ->whereRaw('LOWER(code) = ?', ['en'])
                        ->value('id');

                    $englishId ??= DB::table('languages')
                        ->where('organization_id', $exam->organization_id)
                        ->where('is_enabled', true)
                        ->whereRaw('LOWER(name) = ?', ['english'])
                        ->value('id');

                    $englishId ??= DB::table('languages')
                        ->where('organization_id', $exam->organization_id)
                        ->where('is_enabled', true)
                        ->orderBy('id')
                        ->value('id');

                    if ($englishId) {
                        DB::table('exam_languages')->updateOrInsert(
                            ['exam_id' => $exam->id, 'language_id' => $englishId],
                            [
                                'translation_status' => 'ready',
                                'translated_at' => now(),
                                'created_at' => now(),
                                'updated_at' => now(),
                            ]
                        );
                    }
                }
            }, 'id');
    }

    public function down(): void
    {
        if (Schema::hasTable('question_langs')) {
            Schema::table('question_langs', function (Blueprint $table) {
                $table->dropUnique(['question_id', 'language_id']);
                if (Schema::hasColumn('question_langs', 'translated_by')) $table->dropColumn('translated_by');
                if (Schema::hasColumn('question_langs', 'si_answer1')) $table->dropColumn('si_answer1');
            });
        }

        Schema::table('exam_results', function (Blueprint $table) {
            $table->dropConstrainedForeignId('language_id');
        });

        Schema::dropIfExists('exam_language_translations');
        Schema::dropIfExists('exam_languages');
    }
};

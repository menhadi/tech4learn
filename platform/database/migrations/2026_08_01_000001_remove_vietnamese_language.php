<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('languages')) {
            return;
        }

        DB::transaction(function () {
            $vietnameseLanguages = DB::table('languages')
                ->where(function ($query) {
                    if (Schema::hasColumn('languages', 'code')) {
                        $query->whereRaw('LOWER(code) = ?', ['vi']);
                    }

                    $query->orWhereRaw('LOWER(name) = ?', ['vietnamese'])
                        ->orWhereRaw('LOWER(name) LIKE ?', ['tiếng việt%']);
                })
                ->orderBy('id')
                ->get();

            foreach ($vietnameseLanguages as $vietnamese) {
                $organizationId = Schema::hasColumn('languages', 'organization_id')
                    ? $vietnamese->organization_id
                    : null;
                $fallbackId = DB::table('languages')
                    ->when(
                        Schema::hasColumn('languages', 'organization_id'),
                        fn ($query) => $organizationId === null
                            ? $query->whereNull('organization_id')
                            : $query->where('organization_id', $organizationId)
                    )
                    ->where('id', '!=', $vietnamese->id)
                    ->when(
                        Schema::hasColumn('languages', 'code'),
                        fn ($query) => $query->orderByRaw("CASE WHEN LOWER(code) = 'en' THEN 0 ELSE 1 END")
                    )
                    ->orderBy('id')
                    ->value('id');

                foreach (['questions', 'exam_results'] as $table) {
                    if (Schema::hasTable($table) && Schema::hasColumn($table, 'language_id')) {
                        DB::table($table)->where('language_id', $vietnamese->id)->update([
                            'language_id' => $fallbackId,
                        ]);
                    }
                }

                if (Schema::hasColumn('languages', 'source_language_id')) {
                    DB::table('languages')->where('source_language_id', $vietnamese->id)->update([
                        'source_language_id' => null,
                    ]);
                }

                foreach (['exam_language_translations', 'exam_languages', 'question_langs', 'passage_langs'] as $table) {
                    if (Schema::hasTable($table) && Schema::hasColumn($table, 'language_id')) {
                        DB::table($table)->where('language_id', $vietnamese->id)->delete();
                    }
                }

                DB::table('languages')->where('id', $vietnamese->id)->delete();
            }

            foreach (['users', 'students'] as $table) {
                if (Schema::hasTable($table) && Schema::hasColumn($table, 'language')) {
                    DB::table($table)
                        ->whereIn(DB::raw('LOWER(language)'), ['vi', 'vietnamese'])
                        ->update(['language' => 'en']);
                }
            }

            if (Schema::hasTable('exam_feedbacks') && Schema::hasColumn('exam_feedbacks', 'language_of_questions')) {
                DB::table('exam_feedbacks')
                    ->whereIn(DB::raw('LOWER(language_of_questions)'), ['vi', 'vietnamese'])
                    ->update(['language_of_questions' => 'English']);
            }
        });
    }

    public function down(): void
    {
        // Intentionally irreversible: removed translations and preferences must not be recreated as empty data.
    }
};

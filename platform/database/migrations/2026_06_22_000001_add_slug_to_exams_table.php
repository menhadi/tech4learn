<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('exams', 'slug')) {
            Schema::table('exams', function (Blueprint $table) {
                $table->string('slug')->nullable()->unique()->after('name');
            });
        }

        DB::table('exams')->orderBy('id')->select('id', 'name', 'slug')->chunk(100, function ($exams) {
            foreach ($exams as $exam) {
                if (!empty($exam->slug)) {
                    continue;
                }

                $base = Str::slug($exam->name ?: 'exam-' . $exam->id) ?: 'exam-' . $exam->id;
                $slug = $base;
                $i = 2;

                while (DB::table('exams')->where('slug', $slug)->where('id', '!=', $exam->id)->exists()) {
                    $slug = $base . '-' . $i++;
                }

                DB::table('exams')->where('id', $exam->id)->update(['slug' => $slug]);
            }
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('exams', 'slug')) {
            Schema::table('exams', function (Blueprint $table) {
                $table->dropColumn('slug');
            });
        }
    }
};
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->dropForeignKeyIfPresent('questions', 'subject_id');
        $this->dropForeignKeyIfPresent('exam_stats', 'subject_id');

        DB::table('questions')
            ->whereNotNull('subject_id')
            ->whereNotExists(function ($query) {
                $query->selectRaw('1')
                    ->from('subjects')
                    ->whereColumn('subjects.id', 'questions.subject_id');
            })
            ->update(['subject_id' => null, 'topic_id' => null, 'stopic_id' => null]);

        DB::table('exam_stats')
            ->whereNotNull('subject_id')
            ->whereNotExists(function ($query) {
                $query->selectRaw('1')
                    ->from('subjects')
                    ->whereColumn('subjects.id', 'exam_stats.subject_id');
            })
            ->update(['subject_id' => null]);

        Schema::table('questions', function (Blueprint $table) {
            $table->unsignedBigInteger('subject_id')->nullable()->change();
        });

        Schema::table('exam_stats', function (Blueprint $table) {
            $table->unsignedBigInteger('subject_id')->nullable()->change();
        });

        Schema::table('questions', function (Blueprint $table) {
            $table->foreign('subject_id')->references('id')->on('subjects')->nullOnDelete();
        });

        Schema::table('exam_stats', function (Blueprint $table) {
            $table->foreign('subject_id')->references('id')->on('subjects')->nullOnDelete();
        });
    }

    public function down(): void
    {
        $this->dropForeignKeyIfPresent('questions', 'subject_id');
        $this->dropForeignKeyIfPresent('exam_stats', 'subject_id');

        Schema::table('questions', function (Blueprint $table) {
            $table->foreign('subject_id')->references('id')->on('subjects')->restrictOnDelete();
        });

        Schema::table('exam_stats', function (Blueprint $table) {
            $table->foreign('subject_id')->references('id')->on('subjects')->restrictOnDelete();
        });
    }

    private function dropForeignKeyIfPresent(string $table, string $column): void
    {
        if (DB::getDriverName() !== 'mysql') {
            Schema::table($table, function (Blueprint $blueprint) use ($column) {
                $blueprint->dropForeign([$column]);
            });

            return;
        }

        $constraint = DB::table('information_schema.KEY_COLUMN_USAGE')
            ->where('TABLE_SCHEMA', DB::getDatabaseName())
            ->where('TABLE_NAME', $table)
            ->where('COLUMN_NAME', $column)
            ->whereNotNull('REFERENCED_TABLE_NAME')
            ->value('CONSTRAINT_NAME');

        if ($constraint) {
            $safeTable = str_replace('`', '``', $table);
            $safeConstraint = str_replace('`', '``', $constraint);
            DB::statement("ALTER TABLE `{$safeTable}` DROP FOREIGN KEY `{$safeConstraint}`");
        }
    }
};
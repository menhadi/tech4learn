<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->dropStopicForeignKeyIfPresent();

        // Legacy imports/deletions may have left question rows pointing to
        // subtopics that no longer exist. Preserve those questions and only
        // remove the invalid classification before restoring the constraint.
        DB::table('questions')
            ->whereNotNull('stopic_id')
            ->whereNotExists(function ($query) {
                $query->selectRaw('1')
                    ->from('stopics')
                    ->whereColumn('stopics.id', 'questions.stopic_id');
            })
            ->update(['stopic_id' => null]);

        Schema::table('questions', function (Blueprint $table) {
            $table->foreign('stopic_id')
                ->references('id')
                ->on('stopics')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        $this->dropStopicForeignKeyIfPresent();

        Schema::table('questions', function (Blueprint $table) {
            $table->foreign('stopic_id')
                ->references('id')
                ->on('stopics')
                ->restrictOnDelete();
        });
    }

    private function dropStopicForeignKeyIfPresent(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            Schema::table('questions', function (Blueprint $table) {
                $table->dropForeign(['stopic_id']);
            });

            return;
        }

        $constraint = DB::table('information_schema.KEY_COLUMN_USAGE')
            ->where('TABLE_SCHEMA', DB::getDatabaseName())
            ->where('TABLE_NAME', 'questions')
            ->where('COLUMN_NAME', 'stopic_id')
            ->whereNotNull('REFERENCED_TABLE_NAME')
            ->value('CONSTRAINT_NAME');

        if ($constraint) {
            $safeConstraint = str_replace('`', '``', $constraint);
            DB::statement("ALTER TABLE `questions` DROP FOREIGN KEY `{$safeConstraint}`");
        }
    }
};

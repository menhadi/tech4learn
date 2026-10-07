<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->dropTopicForeignKeyIfPresent();

        // Preserve imported questions with stale topic references before
        // restoring the safer optional relationship.
        DB::table('questions')
            ->whereNotNull('topic_id')
            ->whereNotExists(function ($query) {
                $query->selectRaw('1')
                    ->from('topics')
                    ->whereColumn('topics.id', 'questions.topic_id');
            })
            ->update(['topic_id' => null, 'stopic_id' => null]);

        Schema::table('questions', function (Blueprint $table) {
            $table->foreign('topic_id')
                ->references('id')
                ->on('topics')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        $this->dropTopicForeignKeyIfPresent();

        Schema::table('questions', function (Blueprint $table) {
            $table->foreign('topic_id')
                ->references('id')
                ->on('topics')
                ->restrictOnDelete();
        });
    }

    private function dropTopicForeignKeyIfPresent(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            Schema::table('questions', function (Blueprint $table) {
                $table->dropForeign(['topic_id']);
            });

            return;
        }

        $constraint = DB::table('information_schema.KEY_COLUMN_USAGE')
            ->where('TABLE_SCHEMA', DB::getDatabaseName())
            ->where('TABLE_NAME', 'questions')
            ->where('COLUMN_NAME', 'topic_id')
            ->whereNotNull('REFERENCED_TABLE_NAME')
            ->value('CONSTRAINT_NAME');

        if ($constraint) {
            DB::statement("ALTER TABLE questions DROP FOREIGN KEY {$constraint}");
        }
    }
};
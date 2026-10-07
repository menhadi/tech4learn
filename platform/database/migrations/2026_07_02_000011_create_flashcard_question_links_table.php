<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('flashcard_question_links')) {
            Schema::create('flashcard_question_links', function (Blueprint $table) {
                $table->id();
                $table->foreignId('flashcard_id')->constrained('flashcards')->cascadeOnDelete();
                $table->unsignedBigInteger('question_id');
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();

                $table->unique(['flashcard_id', 'question_id'], 'flashcard_question_unique');
                $table->index(['question_id', 'flashcard_id'], 'flashcard_question_lookup');
            });
        }

        if (Schema::hasColumn('flashcards', 'source_question_id')) {
            DB::table('flashcards')
                ->whereNotNull('source_question_id')
                ->orderBy('id')
                ->select(['id', 'source_question_id'])
                ->chunkById(500, function ($cards) {
                    $now = now();
                    $rows = $cards->map(function ($card) use ($now) {
                        return [
                            'flashcard_id' => $card->id,
                            'question_id' => $card->source_question_id,
                            'sort_order' => 0,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                    })->all();

                    DB::table('flashcard_question_links')->insertOrIgnore($rows);
                });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('flashcard_question_links');
    }
};

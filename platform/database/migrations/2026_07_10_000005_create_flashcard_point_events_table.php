<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('student_flashcard_point_events')) {
            Schema::create('student_flashcard_point_events', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('student_id')->index();
                $table->unsignedBigInteger('package_id')->nullable()->index();
                $table->unsignedBigInteger('flashcard_set_id')->nullable()->index();
                $table->unsignedBigInteger('flashcard_id')->nullable()->index();
                $table->unsignedInteger('points')->default(0);
                $table->unsignedInteger('cards_studied')->default(0);
                $table->unsignedInteger('correct_answers')->default(0);
                $table->timestamp('occurred_at')->index();
                $table->timestamps();
                $table->index(['package_id', 'occurred_at']);
            });
        }

        if (Schema::hasTable('student_flashcard_points') && DB::table('student_flashcard_point_events')->doesntExist()) {
            DB::table('student_flashcard_points')->orderBy('id')->chunkById(500, function ($rows) {
                $now = now();
                $events = collect($rows)->map(fn ($row) => [
                    'student_id' => $row->student_id,
                    'package_id' => $row->package_id,
                    'flashcard_set_id' => $row->flashcard_set_id,
                    'flashcard_id' => null,
                    'points' => $row->total_points,
                    'cards_studied' => $row->cards_studied,
                    'correct_answers' => $row->correct_answers,
                    'occurred_at' => $row->last_activity_at ?: ($row->updated_at ?: $now),
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->all();
                if ($events) DB::table('student_flashcard_point_events')->insert($events);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('student_flashcard_point_events');
    }
};

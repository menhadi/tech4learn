<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->scale('flashcard_reviews', 'points', 5);
        $this->scale('student_flashcard_progress', 'points_earned', 5);
        $this->scale('student_flashcard_points', 'total_points', 5);
    }

    public function down(): void
    {
        $this->scale('flashcard_reviews', 'points', 0.2, true);
        $this->scale('student_flashcard_progress', 'points_earned', 0.2, true);
        $this->scale('student_flashcard_points', 'total_points', 0.2, true);
    }

    private function scale(string $table, string $column, float $factor, bool $floor = false): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
            return;
        }

        $expression = $floor ? "FLOOR({$column} * {$factor})" : "{$column} * {$factor}";
        DB::table($table)->update([$column => DB::raw($expression)]);
    }
};

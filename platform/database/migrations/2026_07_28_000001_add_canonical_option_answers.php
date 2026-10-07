<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->json('correct_option_indices')->nullable()->after('mi_answer6');
        });
        Schema::table('exam_stats', function (Blueprint $table) {
            $table->json('selected_option_indices')->nullable()->after('mi_answers');
        });

        DB::table('questions')->select(['id', 'mi_answer1', 'mi_answer2', 'mi_answer3', 'mi_answer4', 'mi_answer5', 'mi_answer6'])
            ->orderBy('id')->chunkById(500, function ($questions) {
                $updates = $questions->map(function ($question) {
                    $indices = collect(range(1, 6))
                        ->filter(fn (int $index) => trim(strip_tags((string) $question->{'mi_answer'.$index})) !== '')
                        ->values()->all();
                    return $indices === [] ? null : ['id' => $question->id, 'correct_option_indices' => json_encode($indices)];
                })->filter()->values()->all();
                $this->bulkUpdateJson('questions', 'correct_option_indices', $updates);
            });

        DB::table('exam_stats')->join('questions', 'questions.id', '=', 'exam_stats.question_id')
            ->whereNotNull('exam_stats.mi_answers')
            ->select(['exam_stats.id', 'exam_stats.mi_answers', 'questions.option1', 'questions.option2', 'questions.option3', 'questions.option4', 'questions.option5', 'questions.option6'])
            ->orderBy('exam_stats.id')->chunkById(500, function ($stats) {
                $updates = $stats->map(function ($stat) {
                    $selected = json_decode((string) $stat->mi_answers, true);
                    if (! is_array($selected)) $selected = [(string) $stat->mi_answers];
                    $indices = collect($selected)->map(function ($answer) use ($stat) {
                        if (is_numeric($answer) && (int) $answer >= 1 && (int) $answer <= 6) return (int) $answer;
                        $needle = $this->normalize((string) $answer);
                        foreach (range(1, 6) as $index) {
                            if ($needle !== '' && $needle === $this->normalize((string) $stat->{'option'.$index})) return $index;
                        }
                        return null;
                    })->filter()->unique()->sort()->values()->all();
                    return $indices === [] ? null : ['id' => $stat->id, 'selected_option_indices' => json_encode($indices)];
                })->filter()->values()->all();
                $this->bulkUpdateJson('exam_stats', 'selected_option_indices', $updates);
            }, 'exam_stats.id', 'id');
    }

    public function down(): void
    {
        Schema::table('exam_stats', fn (Blueprint $table) => $table->dropColumn('selected_option_indices'));
        Schema::table('questions', fn (Blueprint $table) => $table->dropColumn('correct_option_indices'));
    }

    private function bulkUpdateJson(string $table, string $column, array $updates): void
    {
        if ($updates === []) return;
        $case = implode(' ', array_fill(0, count($updates), 'WHEN ? THEN ?'));
        $ids = array_column($updates, 'id');
        $bindings = [];
        foreach ($updates as $update) {
            $bindings[] = $update['id'];
            $bindings[] = $update[$column];
        }
        $bindings = array_merge($bindings, $ids);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        DB::update("UPDATE `{$table}` SET `{$column}` = CASE `id` {$case} END WHERE `id` IN ({$placeholders})", $bindings);
    }

    private function normalize(string $value): string
    {
        $value = html_entity_decode(strip_tags($value));
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;
        return mb_strtolower(trim($value));
    }
};

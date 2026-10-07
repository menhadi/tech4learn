<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->ensureLegacyAttemptArchive();
        $this->backfillQuestionIndices();
        $this->backfillAttemptIndices();

        if (Schema::hasColumn('questions', 'mi_answer1')) {
            Schema::table('questions', function (Blueprint $table) {
                $table->dropColumn([
                    'mi_answer1', 'mi_answer2', 'mi_answer3',
                    'mi_answer4', 'mi_answer5', 'mi_answer6',
                ]);
            });
        }

        if (Schema::hasColumn('exam_stats', 'mi_answers')) {
            Schema::table('exam_stats', fn (Blueprint $table) => $table->dropColumn('mi_answers'));
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('questions', 'mi_answer1')) {
            Schema::table('questions', function (Blueprint $table) {
                foreach (range(1, 6) as $index) {
                    $table->text('mi_answer'.$index)->nullable();
                }
            });
        }

        if (! Schema::hasColumn('exam_stats', 'mi_answers')) {
            Schema::table('exam_stats', fn (Blueprint $table) => $table->text('mi_answers')->nullable());
        }

        DB::table('questions')->select([
            'id', 'correct_option_indices',
            'option1', 'option2', 'option3', 'option4', 'option5', 'option6',
        ])->orderBy('id')->chunkById(500, function ($questions) {
            foreach ($questions as $question) {
                $indices = $this->indices($question->correct_option_indices);
                $values = [];
                foreach (range(1, 6) as $index) {
                    $values['mi_answer'.$index] = in_array($index, $indices, true)
                        ? $question->{'option'.$index}
                        : null;
                }
                DB::table('questions')->where('id', $question->id)->update($values);
            }
        });

        DB::table('exam_stats')->select(['id', 'selected_option_indices'])
            ->orderBy('id')->chunkById(500, function ($stats) {
                foreach ($stats as $stat) {
                    $indices = $this->indices($stat->selected_option_indices);
                    DB::table('exam_stats')->where('id', $stat->id)
                        ->update(['mi_answers' => $indices === [] ? null : json_encode($indices)]);
                }
            });

        if (Schema::hasTable('legacy_option_answer_archives')) {
            DB::table('legacy_option_answer_archives')->orderBy('id')->chunkById(500, function ($archives) {
                foreach ($archives as $archive) {
                    DB::table('exam_stats')->where('id', $archive->exam_stat_id)
                        ->update(['mi_answers' => $archive->legacy_mi_answers]);
                }
            });
            Schema::drop('legacy_option_answer_archives');
        }
    }

    private function backfillQuestionIndices(): void
    {
        if (! Schema::hasColumn('questions', 'mi_answer1')) return;

        DB::table('questions')->select([
            'id', 'correct_option_indices',
            'mi_answer1', 'mi_answer2', 'mi_answer3',
            'mi_answer4', 'mi_answer5', 'mi_answer6',
        ])->orderBy('id')->chunkById(500, function ($questions) {
            $updates = [];
            foreach ($questions as $question) {
                $legacy = collect(range(1, 6))
                    ->filter(fn ($index) => trim(strip_tags((string) $question->{'mi_answer'.$index})) !== '')
                    ->values()->all();
                if ($legacy === []) continue;

                $canonical = $this->indices($question->correct_option_indices);
                if ($canonical === []) {
                    $updates[] = ['id' => $question->id, 'correct_option_indices' => json_encode($legacy)];
                } elseif ($canonical !== $legacy) {
                    throw new RuntimeException(
                        'Canonical and legacy correct answers conflict for question ID '.$question->id.'. Migration stopped before dropping columns.'
                    );
                }
            }
            $this->bulkUpdateJson('questions', 'correct_option_indices', $updates);
        });
    }

    private function backfillAttemptIndices(): void
    {
        if (! Schema::hasColumn('exam_stats', 'mi_answers')) return;

        DB::table('exam_stats')->join('questions', 'questions.id', '=', 'exam_stats.question_id')
            ->whereNotNull('exam_stats.mi_answers')
            ->select([
                'exam_stats.id', 'exam_stats.mi_answers', 'exam_stats.selected_option_indices',
                'questions.option1', 'questions.option2', 'questions.option3',
                'questions.option4', 'questions.option5', 'questions.option6',
            ])->orderBy('exam_stats.id')->chunkById(500, function ($stats) {
                $updates = [];
                foreach ($stats as $stat) {
                    $canonical = $this->indices($stat->selected_option_indices);
                    $legacy = $this->legacyAnswers($stat->mi_answers);
                    if ($legacy === []) continue;

                    $mapped = collect($legacy)->map(function ($answer) use ($stat) {
                        if ((is_int($answer) || is_float($answer)) && (int) $answer >= 1 && (int) $answer <= 6) {
                            return (int) $answer;
                        }
                        $needle = $this->normalize($answer);
                        foreach (range(1, 6) as $index) {
                            if ($needle !== '' && $needle === $this->normalize($stat->{'option'.$index})) return $index;
                        }
                        if (is_numeric($answer) && (int) $answer >= 1 && (int) $answer <= 6) return (int) $answer;
                        return null;
                    });

                    if ($mapped->containsStrict(null)) {
                        $this->archiveLegacyAttempt($stat, 'unmapped_option_value');
                        continue;
                    }
                    $indices = $mapped->unique()->sort()->values()->all();
                    if ($canonical === []) {
                        $updates[] = ['id' => $stat->id, 'selected_option_indices' => json_encode($indices)];
                    } elseif ($canonical !== $indices) {
                        $this->archiveLegacyAttempt($stat, 'canonical_legacy_conflict');
                    }
                }
                $this->bulkUpdateJson('exam_stats', 'selected_option_indices', $updates);
            }, 'exam_stats.id', 'id');
    }

    private function ensureLegacyAttemptArchive(): void
    {
        if (Schema::hasTable('legacy_option_answer_archives')) return;

        Schema::create('legacy_option_answer_archives', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('exam_stat_id')->unique();
            $table->longText('legacy_mi_answers')->nullable();
            $table->string('reason', 100);
            $table->timestamps();
        });
    }

    private function archiveLegacyAttempt(object $stat, string $reason): void
    {
        DB::table('legacy_option_answer_archives')->updateOrInsert(
            ['exam_stat_id' => $stat->id],
            [
                'legacy_mi_answers' => $stat->mi_answers,
                'reason' => $reason,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }

    private function legacyAnswers(mixed $value): array
    {
        for ($depth = 0; $depth < 4; $depth++) {
            if ($value === null) return [];
            if (is_array($value)) {
                return array_values(array_filter($value, function ($item) {
                    if ($item === null) return false;
                    $normalized = strtolower(trim((string) $item, " \t\n\r\0\x0B\"'"));
                    return $normalized !== '' && $normalized !== 'null';
                }));
            }
            if (! is_string($value)) return [$value];

            $raw = trim($value);
            $normalized = strtolower(trim($raw, " \t\n\r\0\x0B\"'"));
            if ($normalized === '' || $normalized === 'null' || $normalized === '[]') return [];

            $decoded = json_decode($raw, true);
            if (json_last_error() !== JSON_ERROR_NONE) return [$raw];
            $value = $decoded;
        }

        return [];
    }

    private function indices(mixed $value): array
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            $value = is_array($decoded) ? $decoded : [];
        }
        return collect((array) $value)->map(fn ($index) => (int) $index)
            ->filter(fn ($index) => $index >= 1 && $index <= 6)
            ->unique()->sort()->values()->all();
    }

    private function normalize(mixed $value): string
    {
        if (is_array($value) || is_object($value)) return '';
        $value = html_entity_decode(strip_tags((string) $value));
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;
        return mb_strtolower(trim($value));
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
};

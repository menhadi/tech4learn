<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('source_exam_question_drafts') || ! Schema::hasTable('questions')) {
            return;
        }

        DB::table('source_exam_question_drafts')
            ->whereNotNull('question_id')
            ->where('status', 'published')
            ->orderBy('id')
            ->chunkById(250, function ($drafts): void {
                foreach ($drafts as $draft) {
                    $payload = is_array($draft->payload)
                        ? $draft->payload
                        : json_decode((string) $draft->payload, true);

                    if (! is_array($payload)) {
                        continue;
                    }

                    $visibleOptionCount = collect(range(1, 6))
                        ->filter(fn ($index) => trim((string) ($payload['option'.$index] ?? '')) !== '')
                        ->count();
                    if ($visibleOptionCount < 2) {
                        continue;
                    }

                    $correctIndexes = collect($payload['correct_answers'] ?? [])
                        ->map(fn ($value) => (int) $value)
                        ->filter(fn ($value) => $value >= 1 && $value <= 6)
                        ->unique()
                        ->values();

                    $answer = trim((string) ($payload['correct_answer'] ?? ''));
                    if ($correctIndexes->isEmpty() && preg_match('/^[A-F]$/i', $answer)) {
                        $correctIndexes = collect([ord(strtoupper($answer)) - 64]);
                    }
                    if ($correctIndexes->isEmpty()) {
                        continue;
                    }

                    $updates = collect(range(1, 6))
                        ->mapWithKeys(fn ($index) => ['mi_answer'.$index => null])
                        ->all();
                    foreach ($correctIndexes as $optionIndex) {
                        $option = $payload['option'.$optionIndex] ?? null;
                        if ($option !== null && trim((string) $option) !== '') {
                            $updates['mi_answer'.$optionIndex] = $option;
                        }
                    }
                    $updates['updated_at'] = now();

                    DB::table('questions')->where('id', $draft->question_id)->update($updates);
                }
            });
    }

    public function down(): void
    {
        // Data correction is intentionally not reversed.
    }
};
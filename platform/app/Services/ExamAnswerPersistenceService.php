<?php

namespace App\Services;

use App\Models\ExamStat;
use Illuminate\Support\Facades\DB;

class ExamAnswerPersistenceService
{
    public function save(ExamStat $authorizedStat, array $payload): array
    {
        return DB::transaction(function () use ($authorizedStat, $payload) {
            $stat = ExamStat::query()->with('exam')->lockForUpdate()->findOrFail($authorizedStat->id);
            $questionType = (string) ($payload['question_type'] ?? '');
            $answerData = $this->answerData($questionType, $payload['option_selected'] ?? null);
            $allowsChanges = (bool) ($stat->exam?->allow_answer_change ?? true);

            if (! $allowsChanges && $stat->answer_locked_at && $this->answerChanged($stat, $answerData, $questionType)) {
                return [
                    'success' => false,
                    'answer_locked' => true,
                    'message' => 'This answer was locked after you left the question and cannot be changed.',
                ];
            }

            $answered = filter_var($payload['answered'] ?? false, FILTER_VALIDATE_BOOLEAN);
            $updates = array_merge($answerData, [
                'attempt_time' => now(),
                'opened' => true,
                'answered' => $answered,
                'review' => filter_var($payload['review'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'time_taken' => max(0, (int) ($payload['time_taken'] ?? 0)),
            ]);

            if (array_key_exists('bookmark', $payload)) {
                $updates['bookmark'] = filter_var($payload['bookmark'], FILTER_VALIDATE_BOOLEAN);
            }

            if (! $allowsChanges && ! $stat->answer_locked_at && $answered
                && filter_var($payload['lock_answer'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                $updates['answer_locked_at'] = now();
            }

            $stat->update($updates);

            return [
                'success' => true,
                'answer_locked' => ! $allowsChanges && $stat->fresh()->answer_locked_at !== null,
            ];
        });
    }

    private function answerData(string $questionType, mixed $selected): array
    {
        if ($questionType === 'true_false') {
            return ['true_false' => $selected];
        }
        if ($questionType === 'fill_blank') {
            $values = is_array($selected) ? array_values($selected) : [$selected];
            return ['answer' => json_encode($values, JSON_UNESCAPED_UNICODE)];
        }
        if (in_array($questionType, ['multiple_choice_checkbox', 'multiple_choice_radio'], true)) {
            $values = is_array($selected) ? $selected : ($selected === null ? [] : [$selected]);
            $indices = collect($values)->map(fn ($value) => (int) $value)
                ->filter(fn ($value) => $value >= 1 && $value <= 6)->unique()->sort()->values()->all();
            return ['selected_option_indices' => $indices];
        }

        return ['answer' => $selected];
    }

    private function answerChanged(ExamStat $stat, array $incoming, string $questionType): bool
    {
        $field = array_key_first($incoming);
        $existing = $stat->getRawOriginal($field);
        $new = $incoming[$field];

        if ($field === 'selected_option_indices' || $questionType === 'fill_blank') {
            $existing = $this->normalizedArray($existing, $field === 'selected_option_indices');
            $new = $this->normalizedArray($new, $field === 'selected_option_indices');
            return $existing !== $new;
        }

        return trim((string) $existing) !== trim((string) $new);
    }

    private function normalizedArray(mixed $value, bool $sort): array
    {
        while (is_string($value)) {
            $decoded = json_decode($value, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $value = [$value];
                break;
            }
            $value = $decoded;
        }
        if (! is_array($value)) {
            $value = $value === null ? [] : [$value];
        }

        $value = array_map(fn ($item) => trim((string) $item), array_values($value));
        if ($sort) {
            sort($value, SORT_STRING);
        }
        return $value;
    }
}

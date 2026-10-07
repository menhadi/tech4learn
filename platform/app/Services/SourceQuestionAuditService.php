<?php

namespace App\Services;

use App\Models\Question;

class SourceQuestionAuditService
{
    private const DIRECT_FIELDS = [
        'question', 'option1', 'option2', 'option3', 'option4', 'option5', 'option6',
        'answer', 'explanation', 'marks', 'negative_marks', 'scoring_policy', 'hint',
        'true_false', 'fill_blank', 'si_answer1', 'correct_option_indices',
    ];

    private const SINGLE_ASSIGNMENTS = [
        'question_type' => ['attribute' => 'qtype_id', 'relation' => 'qtype', 'label' => 'question_type'],
        'subject' => ['attribute' => 'subject_id', 'relation' => 'subject', 'label' => 'subject_name'],
        'section' => ['attribute' => 'question_section_id', 'relation' => 'questionSection', 'label' => 'name'],
        'topic' => ['attribute' => 'topic_id', 'relation' => 'topic', 'label' => 'name'],
        'subtopic' => ['attribute' => 'stopic_id', 'relation' => 'stopic', 'label' => 'name'],
        'difficulty_level' => ['attribute' => 'diff_id', 'relation' => 'diff', 'label' => 'diff_level'],
        'language' => ['attribute' => 'language_id', 'relation' => 'language', 'label' => 'name'],
    ];

    public const FIELDS = [
        ...self::DIRECT_FIELDS,
        'question_type', 'subject', 'section', 'topic', 'subtopic',
        'difficulty_level', 'language', 'groups', 'exams',
    ];

    public function compare(Question $question, array $payload): array
    {
        $extracted = $this->questionValues($payload);
        $fields = [];

        foreach (self::DIRECT_FIELDS as $field) {
            $fields[$field] = $this->difference(
                $question->{$field},
                $extracted[$field] ?? null,
                true,
            );
        }

        $assignment = (array) ($payload['assignment'] ?? []);
        foreach (self::SINGLE_ASSIGNMENTS as $field => $definition) {
            $incoming = data_get($assignment, $field.'.name', $payload[$field] ?? null);
            $assigned = (bool) data_get($assignment, $field.'.assigned', false);
            $fields[$field] = $this->difference(
                $this->currentRelationLabel($question, $definition['relation'], $definition['label']),
                $incoming,
                $assigned,
                true,
            );
        }

        foreach (['groups' => 'group', 'exams' => 'exam'] as $field => $assignmentKey) {
            $incoming = data_get($assignment, $assignmentKey.'.names', []);
            $assigned = (bool) data_get($assignment, $assignmentKey.'.assigned', false);
            $fields[$field] = $this->difference(
                $this->currentManyLabels($question, $field),
                $incoming,
                $assigned,
                true,
            );
        }

        return [
            'status' => collect($fields)->contains(fn ($field) => in_array($field['status'], ['missing', 'mismatch'], true)) ? 'changes' : 'clean',
            'fields' => $fields,
            'checked_at' => now()->toIso8601String(),
        ];
    }

    public function apply(Question $question, array $payload, array $fields, bool $allowOverwrite = false): array
    {
        $selected = array_values(array_intersect(self::FIELDS, $fields));
        $values = $this->questionValues($payload);
        $updates = [];
        $changed = [];

        foreach (array_intersect(self::DIRECT_FIELDS, $selected) as $field) {
            $incoming = $values[$field] ?? null;
            if ($this->blank($incoming)) continue;
            if (! $this->blank($question->{$field}) && ! $allowOverwrite) continue;
            $updates[$field] = $incoming;
            $changed[] = $field;
        }

        $assignment = (array) ($payload['assignment'] ?? []);
        foreach (self::SINGLE_ASSIGNMENTS as $field => $definition) {
            if (! in_array($field, $selected, true)) continue;
            $incomingId = (int) data_get($assignment, $field.'.id', 0);
            if ($incomingId <= 0) continue;
            if ((int) $question->{$definition['attribute']} > 0 && ! $allowOverwrite) continue;
            $updates[$definition['attribute']] = $incomingId;
            $changed[] = $field;
        }

        if ($updates !== []) $question->update($updates);

        foreach (['groups' => 'group', 'exams' => 'exam'] as $field => $assignmentKey) {
            if (! in_array($field, $selected, true)) continue;
            $ids = collect((array) data_get($assignment, $assignmentKey.'.ids', []))->map(fn ($id) => (int) $id)->filter()->values()->all();
            if ($ids === [] || ! data_get($assignment, $assignmentKey.'.assigned', false)) continue;
            $relation = $question->{$field}();
            if ($relation->exists() && ! $allowOverwrite) continue;
            $relation->sync($ids);
            $changed[] = $field;
        }

        return array_values(array_unique($changed));
    }

    private function questionValues(array $payload): array
    {
        $values = [
            'question' => $payload['question'] ?? null,
            'answer' => $payload['answer'] ?? null,
            'explanation' => $payload['explanation'] ?? null,
            'marks' => $payload['marks'] ?? null,
            'negative_marks' => $payload['negative_marks'] ?? null,
            'scoring_policy' => $payload['scoring_policy'] ?? null,
            'hint' => $payload['hint'] ?? null,
            'true_false' => $payload['true_false'] ?? null,
            'fill_blank' => $payload['fill_blank'] ?? null,
            'si_answer1' => $payload['si_answer1'] ?? null,
            'correct_option_indices' => $payload['correct_option_indices'] ?? null,
        ];
        $options = array_values((array) ($payload['options'] ?? []));
        for ($index = 1; $index <= 6; $index++) $values['option'.$index] = $options[$index - 1] ?? null;

        return $values;
    }

    private function difference(mixed $current, mixed $incoming, bool $canRepair, bool $assignment = false): array
    {
        $currentBlank = $this->blank($current);
        $incomingBlank = $this->blank($incoming);
        $same = $assignment
            ? $this->assignmentComparable($current) === $this->assignmentComparable($incoming)
            : $this->comparable($current) === $this->comparable($incoming);
        $status = $same ? 'match' : ($currentBlank && ! $incomingBlank ? 'missing' : ($incomingBlank ? 'source_blank' : 'mismatch'));

        return [
            'status' => $status,
            'current' => $this->display($current),
            'extracted' => $this->display($incoming),
            'repairable' => $canRepair && in_array($status, ['missing', 'mismatch'], true),
        ];
    }

    private function currentRelationLabel(Question $question, string $relation, string $field): ?string
    {
        if (! $question->relationLoaded($relation) && $question->exists) $question->loadMissing($relation);
        $record = $question->relationLoaded($relation) ? $question->getRelation($relation) : null;
        return $record ? (string) data_get($record, $field) : null;
    }

    private function currentManyLabels(Question $question, string $relation): array
    {
        if (! $question->relationLoaded($relation) && $question->exists) $question->loadMissing($relation);
        if (! $question->relationLoaded($relation)) return [];
        $field = $relation === 'groups' ? 'group_name' : 'name';

        return $question->getRelation($relation)->pluck($field)->map(fn ($value) => (string) $value)->sort()->values()->all();
    }

    private function blank(mixed $value): bool
    {
        if ($value === null) return true;
        if (is_array($value)) return $value === [];
        return is_string($value) && trim(strip_tags($value)) === '';
    }

    private function comparable(mixed $value): string
    {
        if ($this->blank($value)) return '';
        if (is_array($value)) return json_encode(array_values($value), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';
        if (is_numeric($value)) return rtrim(rtrim(number_format((float) $value, 8, '.', ''), '0'), '.');
        $value = html_entity_decode(strip_tags((string) $value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return preg_replace('/\s+/u', ' ', trim($value)) ?? trim($value);
    }

    private function assignmentComparable(mixed $value): string
    {
        $values = is_array($value) ? $value : [$value];
        return collect($values)->map(function ($item) {
            $item = html_entity_decode(strip_tags((string) $item), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            return preg_replace('/[^\pL\pN]+/u', '', mb_strtolower(trim($item))) ?? '';
        })->filter()->sort()->implode('|');
    }

    private function display(mixed $value): mixed
    {
        return is_array($value) ? implode(' | ', array_map('strval', $value)) : $value;
    }
}

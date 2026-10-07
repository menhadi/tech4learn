<?php

namespace App\Services;

class SourceQuestionPayloadMerger
{
    private const FIELDS = [
        'group' => ['source' => ['group', 'groups'], 'csv' => ['groups', 'group', 'group_name']],
        'category' => ['source' => ['category'], 'csv' => ['category', 'category_name']],
        'subcategory' => ['source' => ['subcategory'], 'csv' => ['subcategory', 'sub_category', 'subcategory_name']],
        'package' => ['source' => ['package'], 'csv' => ['package', 'package_name']],
        'exam' => ['source' => ['exam', 'exams', 'paper_name'], 'csv' => ['exams', 'exam', 'exam_name', 'paper']],
        'subject' => ['source' => ['subject'], 'csv' => ['subject', 'subject_name']],
        'section' => ['source' => ['section'], 'csv' => ['section', 'section_name']],
        'topic' => ['source' => ['topic'], 'csv' => ['topic', 'topic_name']],
        'subtopic' => ['source' => ['subtopic'], 'csv' => ['subtopic', 'sub_topic', 'stopic', 'subtopic_name']],
        'question_type' => ['source' => ['question_type'], 'csv' => ['question_type', 'type']],
        'difficulty_level' => ['source' => ['difficulty_level', 'difficulty'], 'csv' => ['difficulty_level', 'difficulty']],
        'language' => ['source' => ['language'], 'csv' => ['language', 'language_name', 'language_code']],
        'passage' => ['source' => ['passage'], 'csv' => ['passage', 'passage_name']],
        'question' => ['source' => ['question'], 'csv' => ['question', 'question_text']],
        'answer' => ['source' => ['answer'], 'csv' => ['answer', 'correct_answer']],
        'explanation' => ['source' => ['explanation'], 'csv' => ['explanation']],
        'marks' => ['source' => ['marks'], 'csv' => ['marks']],
        'negative_marks' => ['source' => ['negative_marks'], 'csv' => ['negative_marks']],
        'scoring_policy' => ['source' => ['scoring_policy'], 'csv' => ['scoring_policy']],
        'hint' => ['source' => ['hint'], 'csv' => ['hint']],
        'true_false' => ['source' => ['true_false'], 'csv' => ['true_false', 'truefalse']],
        'fill_blank' => ['source' => ['fill_blank'], 'csv' => ['fill_blank', 'fill_in_the_blank']],
        'si_answer1' => ['source' => ['si_answer1'], 'csv' => ['si_answer1', 'si_answer_1', 'subjective_answer']],
        'status' => ['source' => ['status'], 'csv' => ['status']],
        'tags' => ['source' => ['tags'], 'csv' => ['tags', 'question_tags', 'tag']],
        'question_source_reference' => ['source' => ['question_source_reference', 'source_reference'], 'csv' => ['question_source_reference', 'source_reference']],
    ];

    public function merge(array $payload, array $metadata): array
    {
        $mergedMetadata = $metadata;

        foreach (self::FIELDS as $field => $aliases) {
            $source = $this->firstValue($payload, $aliases['source']);
            $csv = $this->firstValue($metadata, $aliases['csv']);
            $value = $source ?? $csv;
            if ($value === null) continue;

            $payload[$field] = $value;
            $mergedMetadata[$field] = $value;
        }

        $payload['options'] = $this->mergeOptions((array) ($payload['options'] ?? []), $metadata);

        $sourceIndices = $this->optionIndices($payload['correct_option_indices'] ?? null);
        $csvIndices = $this->optionIndices($this->firstValue($metadata, ['correct_option_indices', 'correct_options', 'correct_answers']));
        if ($sourceIndices !== [] || $csvIndices !== []) $payload['correct_option_indices'] = $sourceIndices !== [] ? $sourceIndices : $csvIndices;

        if ($this->blank($payload['language'] ?? null)) {
            $payload['language'] = 'English';
            $mergedMetadata['language'] = 'English';
        }

        $payload['metadata'] = $mergedMetadata;

        return $payload;
    }

    private function mergeOptions(array $sourceOptions, array $metadata): array
    {
        $merged = [];
        for ($index = 1; $index <= 6; $index++) {
            $source = $sourceOptions[$index - 1] ?? null;
            $csv = $this->firstValue($metadata, ['option'.$index, 'option_'.$index]);
            $value = ! $this->blank($source) ? $source : $csv;
            if (! $this->blank($value)) $merged[$index - 1] = $value;
        }

        if ($merged === []) return [];
        $last = max(array_keys($merged));

        return array_map(fn ($index) => $merged[$index] ?? null, range(0, $last));
    }

    private function optionIndices(mixed $value): array
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            $value = is_array($decoded) ? $decoded : preg_split('/[,;|\s]+/', trim($value), -1, PREG_SPLIT_NO_EMPTY);
        }

        return collect((array) $value)->map(function ($index) {
            $index = strtoupper(trim((string) $index));
            return preg_match('/^[A-F]$/', $index) ? ord($index) - 64 : (int) $index;
        })->filter(fn ($index) => $index >= 1 && $index <= 6)->unique()->sort()->values()->all();
    }

    private function firstValue(array $values, array $keys): mixed
    {
        foreach ($keys as $key) {
            if (! array_key_exists($key, $values) || $this->blank($values[$key])) continue;
            return $values[$key];
        }

        return null;
    }

    private function blank(mixed $value): bool
    {
        if ($value === null) return true;
        if (is_string($value)) return trim($value) === '';
        if (is_array($value)) return $value === [];

        return false;
    }
}

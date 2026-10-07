<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithHeadings;

class QuestionsExport implements FromQuery, WithMapping, WithHeadings
{
    public function __construct(protected $questionsQuery, protected ?int $groupId = null)
    {
    }

    public function query()
    {
        return $this->questionsQuery;
    }

    public function map($question): array
    {
        $exam = $question->exams->first();
        $package = $exam?->packages?->first();
        $category = $exam?->category ?: $package?->category;
        $subcategory = $exam?->subcategory ?: $package?->subcategory;
        $taxonomy = $this->groupId ? $question->taxonomies->firstWhere('group_id', $this->groupId) : null;

        $row = [
            $question->question_code,
            $question->groups->pluck('group_name')->implode(' | '),
            $category?->title,
            $subcategory?->title,
            $package?->name,
            $question->exams->pluck('name')->implode(' | '),
            ($taxonomy?->subject ?: $question->subject)?->subject_name,
            $question->questionSection?->name,
            ($taxonomy?->topic ?: $question->topic)?->name,
            ($taxonomy?->stopic ?: $question->stopic)?->name,
            $question->qtype?->type,
            $question->diff?->diff_level ?: $question->diff?->type,
            $question->language?->name,
            $question->passage?->name,
            $question->question,
            $question->option1,
            $question->option2,
            $question->option3,
            $question->option4,
            $question->option5,
            $question->option6,
            $question->answer,
            $question->true_false,
            $question->fill_blank,
            collect($question->fill_blank_config['blanks'] ?? [])->map(fn ($blank) => implode(' | ', $blank['answers'] ?? []))->implode(' ;; '),
            data_get($question->nat_config, 'mode'),
            data_get($question->nat_config, 'value'),
            data_get($question->nat_config, 'min'),
            data_get($question->nat_config, 'max'),
            data_get($question->nat_config, 'tolerance'),
            implode(',', $question->correctOptionIndices()),

            $question->si_answer1,
            $question->marks,
            $question->negative_marks,
            $question->scoring_policy ?: 'NORMAL',
            $question->hint,
            $question->explanation,
            $question->status,
            $question->tags->pluck('name')->implode(' | '),
            $question->source_url,
            $question->source_reference,
            $exam?->qualitySources?->firstWhere('role', 'questions')?->source_url,
            $exam?->qualitySources?->firstWhere('role', 'answers')?->source_url,
            $exam?->qualitySources?->firstWhere('role', 'combined')?->source_url,
        ];

        return array_map([self::class, 'safeSpreadsheetValue'], $row);
    }

    public static function safeSpreadsheetValue(mixed $value): mixed
    {
        if (! is_string($value)) return $value;

        return preg_match('/^[\t\r\n ]*[=+\-@]/u', $value) ? "'".$value : $value;
    }
    public function headings(): array
    {
        return [
            'question_code',
            'groups', 'category', 'subcategory', 'package', 'exams', 'subject', 'section', 'topic', 'subtopic',
            'question_type', 'difficulty_level', 'language', 'passage', 'question',
            'option1', 'option2', 'option3', 'option4', 'option5', 'option6',
            'answer', 'true_false', 'fill_blank', 'fill_blank_answers',
            'nat_mode', 'nat_value', 'nat_min', 'nat_max', 'nat_tolerance',
            'correct_option_indices', 'si_answer1',
            'marks', 'negative_marks', 'scoring_policy', 'hint', 'explanation', 'status', 'tags',
            'question_source_url', 'question_source_reference',
            'paper_question_source_url', 'paper_answer_source_url', 'paper_combined_source_url',
        ];
    }
}

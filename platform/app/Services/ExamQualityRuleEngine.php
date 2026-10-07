<?php

namespace App\Services;

use App\Models\Question;

class ExamQualityRuleEngine
{
    public function inspect(Question $question): array
    {
        $findings = [];
        $type = app(QuestionAnswerEvaluator::class)->questionType($question);
        $body = $this->plain($question->question);

        if ($body === '') {
            $findings[] = $this->finding('missing_question', 'error', 'Question text is missing', 'The question body is empty.');
        }

        if (! $question->qtype) {
            $findings[] = $this->finding('missing_question_type', 'error', 'Question type is missing', 'No question type is linked to this question.');
        }

        $this->inspectEncoding($question, $findings);
        $this->inspectImages($question, $findings);
        $this->inspectMath($question, $findings);

        if (str_starts_with($type, 'multiple_choice')) {
            $this->inspectMultipleChoice($question, $findings);
        } elseif ($type === 'true_false' && ! in_array(strtolower(trim((string) $question->true_false)), ['true', 'false', '1', '0'], true)) {
            $findings[] = $this->finding('invalid_true_false_answer', 'error', 'True/False answer is invalid', 'Set the correct answer to True or False.');
        } elseif ($type === 'fill_blank') {
            $rules = app(QuestionAnswerEvaluator::class)->fillBlankRules($question);
            if ($rules === [] || collect($rules)->contains(fn ($rule) => empty($rule['answers']))) {
                $findings[] = $this->finding('missing_fill_blank_answer', 'error', 'Fill-in answer is missing', 'Every blank must have at least one accepted answer.');
            }
        } elseif ($type === 'nat') {
            $this->inspectNat($question, $findings);
        } elseif ($type === 'subjective' && $this->plain($question->si_answer1) === '' && $this->plain($question->explanation) === '') {
            $findings[] = $this->finding('missing_subjective_reference', 'warning', 'Subjective reference answer is missing', 'Add a reference answer or explanation for review.');
        }

        if ((float) ($question->marks ?? 0) <= 0) {
            $findings[] = $this->finding('invalid_marks', 'warning', 'Marks are missing or zero', 'Verify the positive marks for this question.');
        }

        if ((float) ($question->negative_marks ?? 0) < 0) {
            $findings[] = $this->finding('negative_marks_below_zero', 'warning', 'Negative marks use an unexpected value', 'Store the deduction as a positive magnitude, for example 1.00.');
        }

        return $findings;
    }

    private function inspectMultipleChoice(Question $question, array &$findings): void
    {
        $options = collect(range(1, 6))
            ->mapWithKeys(fn ($i) => [$i => $this->plain($question->{'option'.$i})])
            ->filter(fn ($value, $i) => trim((string) $question->{'option'.$i}) !== '');
        $correctIndexes = collect($question->correctOptionIndices());

        if ($options->count() < 2) {
            $findings[] = $this->finding('insufficient_options', 'error', 'Multiple-choice options are incomplete', 'At least two visible options are required.', ['option_count' => $options->count()]);
        }
        if ($correctIndexes->isEmpty()) {
            $findings[] = $this->finding('missing_correct_option', 'error', 'Correct option is missing', 'Select at least one correct option.');
        }

        $normalized = $options->filter(fn ($value) => $value !== '')
            ->map(fn ($value) => mb_strtolower(preg_replace('/\s+/u', ' ', $value)));
        if ($normalized->count() > 1 && $normalized->unique()->count() !== $normalized->count()) {
            $findings[] = $this->finding('duplicate_options', 'warning', 'Duplicate options detected', 'Two or more answer options display the same text.');
        }

        foreach ($correctIndexes as $optionIndex) {
            if (! $options->has($optionIndex)) {
                $findings[] = $this->finding(
                    'correct_option_not_visible',
                    'error',
                    'Correct answer is not a visible option',
                    'A correct-answer marker exists for an option that has no displayed value.',
                    ['option_index' => $optionIndex]
                );
            }
        }
    }

    private function inspectNat(Question $question, array &$findings): void
    {
        $rule = app(QuestionAnswerEvaluator::class)->natRule($question);
        if ($rule['mode'] === 'range') {
            if (! is_numeric($rule['min']) || ! is_numeric($rule['max'])) {
                $findings[] = $this->finding('invalid_nat_range', 'error', 'NAT range is incomplete', 'Both minimum and maximum values are required.');
            } elseif ((float) $rule['min'] > (float) $rule['max']) {
                $findings[] = $this->finding('reversed_nat_range', 'warning', 'NAT range is reversed', 'Minimum is greater than maximum.');
            }
        } elseif (! is_numeric($rule['value'])) {
            $findings[] = $this->finding('missing_nat_answer', 'error', 'NAT answer is missing', 'Enter the expected numerical value.');
        }

        if ($rule['mode'] === 'tolerance' && (! is_numeric($rule['tolerance']) || (float) $rule['tolerance'] < 0)) {
            $findings[] = $this->finding('invalid_nat_tolerance', 'error', 'NAT tolerance is invalid', 'Tolerance must be zero or a positive number.');
        }
    }

    private function inspectEncoding(Question $question, array &$findings): void
    {
        foreach ($this->contentFields($question) as $field => $content) {
            if (str_contains((string) $content, "\u{FFFD}") || str_contains((string) $content, 'â€') || str_contains((string) $content, 'Ã')) {
                $findings[] = $this->finding('broken_encoding', 'error', 'Broken characters detected', "Possible character encoding damage in {$field}.", ['field' => $field]);
            }
        }
    }

    private function inspectImages(Question $question, array &$findings): void
    {
        foreach ($this->contentFields($question) as $field => $content) {
            preg_match_all('/<img[^>]+src=["\']([^"\']+)["\']/i', (string) $content, $matches);
            foreach ($matches[1] ?? [] as $src) {
                if (str_starts_with($src, 'data:')) continue;
                if (preg_match('/^(?:javascript|file):/i', $src)) {
                    $findings[] = $this->finding('invalid_image_url', 'error', 'Image URL is invalid', "An image in {$field} uses an unsafe source.", ['field' => $field, 'src' => $src]);
                    continue;
                }
                $exists = app(QuestionImageResolver::class)->exists($src);
                if ($exists === false) {
                    $findings[] = $this->finding('missing_question_image', 'error', 'Question image is missing', "The image referenced in {$field} was not found in public storage or Cloudflare R2.", ['field' => $field, 'src' => $src]);
                }
            }
        }
    }

    private function inspectMath(Question $question, array &$findings): void
    {
        foreach ($this->contentFields($question) as $field => $content) {
            $text = (string) $content;
            $pairs = [['\\(', '\\)'], ['\\[', '\\]']];
            foreach ($pairs as [$open, $close]) {
                if (substr_count($text, $open) !== substr_count($text, $close)) {
                    $findings[] = $this->finding('unbalanced_math_delimiter', 'error', 'Math delimiter is incomplete', "Unbalanced {$open} {$close} delimiters in {$field}.", ['field' => $field]);
                }
            }
            if (substr_count($text, '$$') % 2 !== 0) {
                $findings[] = $this->finding('unbalanced_math_delimiter', 'error', 'Math delimiter is incomplete', "Unbalanced $$ delimiters in {$field}.", ['field' => $field]);
            }
        }
    }

    private function contentFields(Question $question): array
    {
        $fields = ['question', 'option1', 'option2', 'option3', 'option4', 'option5', 'option6', 'hint', 'explanation'];
        return collect($fields)->mapWithKeys(fn ($field) => [$field => $question->{$field}])->all();
    }

    private function hasRenderableContent(mixed $value): bool
    {
        $html = trim((string) $value);
        if ($html === '') return false;

        return $this->plain($html) !== ''
            || preg_match('/<(?:img|math|svg)\b/i', $html) === 1;
    }

    private function plain(mixed $value): string
    {
        $text = html_entity_decode(strip_tags((string) $value));
        return trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
    }

    private function finding(string $type, string $severity, string $title, string $details, array $evidence = []): array
    {
        return compact('type', 'severity', 'title', 'details', 'evidence');
    }
}

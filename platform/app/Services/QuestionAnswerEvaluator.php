<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Model;

class QuestionAnswerEvaluator
{
    public function questionType(object $question): string
    {
        $code = strtoupper(trim((string) (data_get($question, 'qtype.type') ?? data_get($question, 'pdf_question_type_code') ?? '')));
        $label = strtolower(trim((string) (data_get($question, 'qtype.question_type') ?? data_get($question, 'pdf_question_type') ?? '')));
        $hasNatConfig = $this->configArray(data_get($question, 'nat_config')) !== [];
        $hasFillBlankConfig = $this->configArray(data_get($question, 'fill_blank_config')) !== [];
        $hasLegacyFillBlankAnswer = trim(strip_tags((string) data_get($question, 'fill_blank'))) !== '';
        $hasTrueFalseAnswer = trim(strip_tags((string) data_get($question, 'true_false'))) !== '';
        $hasSubjectiveAnswer = trim(strip_tags((string) data_get($question, 'si_answer1'))) !== '';

        return match (true) {
            // An explicitly assigned database type is authoritative. Answer-key
            // fields are incomplete on some legacy questions and must never turn
            // an M question into a subjective textarea.
            $code === 'NAT', str_contains($label, 'numerical answer'), str_contains($label, 'numeric answer') => 'nat',
            $code === 'F', $code === 'B', str_contains($label, 'fill') && str_contains($label, 'blank') => 'fill_blank',
            $code === 'T', str_contains($label, 'true') && str_contains($label, 'false'), $hasTrueFalseAnswer => 'true_false',
            $code === 'S', str_contains($label, 'subjective'), str_contains($label, 'descriptive'), str_contains($label, 'essay') => 'subjective',
            $code === 'MSQ', str_contains($label, 'msq'), str_contains($label, 'multiple select'), str_contains($label, 'multiple correct') => 'multiple_choice_checkbox',
            $code === 'M', str_contains($label, 'multiple choice'), str_contains($label, 'mcq') => $this->multipleChoiceType($question),
            $hasNatConfig => 'nat',
            $hasFillBlankConfig, $hasLegacyFillBlankAnswer => 'fill_blank',
            $hasSubjectiveAnswer => 'subjective',
            $this->hasMultipleChoiceAnswers($question), $this->hasMultipleChoiceOptions($question) => $this->multipleChoiceType($question),
            default => 'subjective',
        };
    }
    public function correctAnswerSnapshot(object $question): ?string
    {
        return match ($this->questionType($question)) {
            'true_false' => $question->true_false,
            'fill_blank' => $this->fillBlankAnswerLabel($question),
            'nat' => $this->natAnswerLabel($question),
            'subjective' => $question->si_answer1,
            'multiple_choice_checkbox', 'multiple_choice_radio' => json_encode($this->correctOptionIndices($question), JSON_UNESCAPED_UNICODE),
            default => $question->answer,
        };
    }

    public function isCorrect(object $question, object $stat): bool
    {
        if ($this->awardsMarksToAll($question)) {
            return true;
        }

        if (! $stat->answered) {
            return false;
        }

        return match ($this->questionType($question)) {
            'fill_blank' => $this->evaluateFillBlank($question, $this->answerArray($stat->answer)),
            'nat' => $this->evaluateNat($question, $stat->answer),
            'true_false' => $this->valuesEqual($stat->true_false, $question->true_false),
            'multiple_choice_checkbox', 'multiple_choice_radio' => $this->evaluateMultipleChoice($question, $this->selectedOptionIndices($question, $stat)),
            'subjective' => false,
            default => $this->valuesEqual($stat->answer, $stat->correct_answer ?: $question->answer),
        };
    }

    public function awardsMarksToAll(object $question): bool
    {
        return strtoupper(trim((string) $question->scoring_policy)) === 'MTA';
    }

    public function fillBlankCount(object $question): int
    {
        return max(1, count($this->fillBlankRules($question)));
    }

    public function fillBlankRules(object $question): array
    {
        $config = $this->configArray(data_get($question, 'fill_blank_config'));
        $blanks = array_values(array_filter($config['blanks'] ?? [], fn ($blank) => is_array($blank) && ! empty($blank['answers'])));

        if ($blanks) {
            return array_map(function (array $blank) {
                return [
                    'answers' => array_values(array_filter(array_map(
                        fn ($answer) => trim((string) $answer),
                        (array) ($blank['answers'] ?? [])
                    ), fn ($answer) => $answer !== '')),
                ];
            }, $blanks);
        }

        $legacy = trim(strip_tags((string) data_get($question, 'fill_blank')));

        return $legacy === '' ? [] : [['answers' => [$legacy]]];
    }

    public function natRule(object $question): array
    {
        $config = $this->configArray(data_get($question, 'nat_config'));
        $mode = in_array($config['mode'] ?? null, ['exact', 'range', 'tolerance'], true)
            ? $config['mode']
            : 'exact';

        return [
            'mode' => $mode,
            'value' => $config['value'] ?? null,
            'min' => $config['min'] ?? null,
            'max' => $config['max'] ?? null,
            'tolerance' => $config['tolerance'] ?? null,
        ];
    }

    private function evaluateFillBlank(object $question, array $studentAnswers): bool
    {
        $rules = $this->fillBlankRules($question);
        if (! $rules || count($rules) !== count($studentAnswers)) {
            return false;
        }

        foreach ($rules as $index => $rule) {
            $studentAnswer = $studentAnswers[$index] ?? null;
            $accepted = (array) ($rule['answers'] ?? []);
            if ($this->normalize($studentAnswer) === '' || ! collect($accepted)->contains(fn ($answer) => $this->valuesEqual($studentAnswer, $answer))) {
                return false;
            }
        }

        return true;
    }

    private function evaluateNat(object $question, mixed $studentAnswer): bool
    {
        $student = $this->number($studentAnswer);
        if ($student === null) {
            return false;
        }

        $rule = $this->natRule($question);
        if ($rule['mode'] === 'range') {
            $min = $this->number($rule['min']);
            $max = $this->number($rule['max']);
            return $min !== null && $max !== null && $student >= min($min, $max) && $student <= max($min, $max);
        }

        $value = $this->number($rule['value']);
        if ($value === null) {
            return false;
        }

        if ($rule['mode'] === 'tolerance') {
            $tolerance = abs($this->number($rule['tolerance']) ?? 0.0);
            return abs($student - $value) <= $tolerance + 1.0E-12;
        }

        return abs($student - $value) <= 1.0E-12;
    }

    public function correctOptionIndices(object $question): array
    {
        if (method_exists($question, 'correctOptionIndices')) {
            return $question->correctOptionIndices();
        }

        $raw = data_get($question, 'correct_option_indices');
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            $raw = is_array($decoded) ? $decoded : null;
        }
        if ($raw !== null) {
            return collect((array) $raw)->map(fn ($index) => (int) $index)
                ->filter(fn ($index) => $index >= 1 && $index <= 6)
                ->unique()->sort()->values()->all();
        }

        return collect(range(1, 6))
            ->filter(fn ($index) => trim(strip_tags((string) data_get($question, 'mi_answer'.$index))) !== '')
            ->values()->all();
    }

    public function optionValue(object $question, int $index): ?string
    {
        if ($index < 1 || $index > 6) return null;
        if (method_exists($question, 'optionValue')) return $question->optionValue($index);
        $value = data_get($question, 'option'.$index);
        return $value === null || $value === '' ? null : (string) $value;
    }

    public function correctOptionValues(object $question): array
    {
        return collect($this->correctOptionIndices($question))
            ->map(fn ($index) => $this->optionValue($question, $index))
            ->filter(fn ($value) => $value !== null && $value !== '')
            ->values()->all();
    }

    public function selectedOptionIndices(object $question, object $stat): array
    {
        $canonicalRaw = data_get($stat, 'selected_option_indices');
        if (is_string($canonicalRaw)) {
            $decoded = json_decode($canonicalRaw, true);
            $canonicalRaw = is_array($decoded) ? $decoded : null;
        }
        $canonical = collect((array) $canonicalRaw)
            ->map(fn ($index) => (int) $index)
            ->filter(fn ($index) => $index >= 1 && $index <= 6)
            ->unique()->sort()->values()->all();
        if ($canonicalRaw !== null) return $canonical;

        // Database rows use only the canonical column. Legacy value fallback is
        // reserved for immutable stdClass report snapshots created before migration.
        if ($stat instanceof Model) return $canonical;

        $legacyRaw = data_get($stat, 'mi_answers');
        $legacy = is_array($legacyRaw) ? $legacyRaw : json_decode((string) $legacyRaw, true);
        $legacy = is_array($legacy) ? $legacy : [$legacyRaw];

        return collect($legacy)->map(function ($answer) use ($question) {
            if ((is_int($answer) || is_float($answer)) && (int) $answer >= 1 && (int) $answer <= 6) return (int) $answer;
            foreach (range(1, 6) as $index) {
                if ($this->valuesEqual($answer, $this->optionValue($question, $index))) return $index;
            }
            if (is_numeric($answer) && (int) $answer >= 1 && (int) $answer <= 6) return (int) $answer;
            return null;
        })->filter()->unique()->sort()->values()->all();
    }

    private function evaluateMultipleChoice(object $question, array $studentAnswers): bool
    {
        $given = array_values(array_map('intval', $studentAnswers));
        $correct = $this->correctOptionIndices($question);
        sort($given);
        sort($correct);

        return $correct !== [] && $given === $correct;
    }

    public function optionReviewValues(object $question, array $indices): array
    {
        return collect($indices)->map(function ($index) use ($question) {
            $index = (int) $index;
            $value = $this->optionValue($question, $index);
            return $value === null ? null : '<strong>'.chr(64 + $index).'.</strong> '.$value;
        })->filter()->values()->all();
    }
    private function fillBlankAnswerLabel(object $question): ?string
    {
        $rules = $this->fillBlankRules($question);
        if (! $rules) {
            return null;
        }

        return collect($rules)
            ->map(fn ($rule) => implode(' / ', (array) ($rule['answers'] ?? [])))
            ->implode(' | ');
    }

    private function natAnswerLabel(object $question): ?string
    {
        $rule = $this->natRule($question);

        return match ($rule['mode']) {
            'range' => $rule['min'].' to '.$rule['max'],
            'tolerance' => $rule['value'].' ± '.$rule['tolerance'],
            default => isset($rule['value']) ? (string) $rule['value'] : null,
        };
    }

    private function answerArray(mixed $answer): array
    {
        if (is_array($answer)) {
            return array_values($answer);
        }

        $decoded = json_decode((string) $answer, true);
        return is_array($decoded) ? array_values($decoded) : [$answer];
    }

    private function multipleChoiceAnswers(object $question)
    {
        return collect($this->correctOptionValues($question));
    }

    private function hasMultipleChoiceAnswers(object $question): bool
    {
        return $this->multipleChoiceAnswers($question)->isNotEmpty();
    }

    private function hasMultipleChoiceOptions(object $question): bool
    {
        return collect(range(1, 6))
            ->contains(fn ($index) => trim(strip_tags((string) data_get($question, 'option'.$index))) !== '');
    }

    private function multipleChoiceType(object $question): string
    {
        return count($this->correctOptionIndices($question)) > 1
            ? 'multiple_choice_checkbox'
            : 'multiple_choice_radio';
    }

    private function configArray(mixed $value): array
    {
        if (is_array($value)) return $value;
        if (is_object($value)) {
            $decoded = json_decode(json_encode($value, JSON_UNESCAPED_UNICODE), true);
            return is_array($decoded) ? $decoded : [];
        }
        if (is_string($value) && trim($value) !== '') {
            $decoded = json_decode($value, true);
            return is_array($decoded) ? $decoded : [];
        }
        return [];
    }

    private function valuesEqual(mixed $left, mixed $right): bool
    {
        $leftNumber = $this->number($left);
        $rightNumber = $this->number($right);
        if ($leftNumber !== null && $rightNumber !== null) {
            return abs($leftNumber - $rightNumber) <= 1.0E-12;
        }

        return $this->normalize($left) === $this->normalize($right);
    }

    private function number(mixed $value): ?float
    {
        if (is_array($value) || is_object($value)) {
            return null;
        }

        $value = str_replace([',', ' '], '', html_entity_decode(strip_tags((string) $value)));
        return $value !== '' && is_numeric($value) ? (float) $value : null;
    }

    private function normalize(mixed $value): string
    {
        if (is_array($value) || is_object($value)) {
            return '';
        }

        $value = html_entity_decode(strip_tags((string) $value));
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;
        return mb_strtolower(trim($value));
    }
}

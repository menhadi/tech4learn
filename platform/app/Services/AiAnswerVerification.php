<?php

namespace App\Services;

use App\Models\{Exam, Question, SourceExamImport, SourceExamQuestionDraft};

/** Uses the same latest-import verification state as the exam-list badge. */
class AiAnswerVerification
{
    public function forExam(Exam $exam, iterable $questions): array
    {
        $import = SourceExamImport::where('organization_id', $exam->organization_id)
            ->where('exam_id', $exam->id)->latest('id')->first();
        $drafts = $import ? $import->drafts()->whereNotNull('question_id')->get()->groupBy('question_id') : collect();
        $result = [];
        foreach ($questions as $question) {
            $matches = $drafts->get($question->id, collect());
            $result[$question->id] = $this->classify($question, $import, $matches->count() === 1 ? $matches->first() : null);
        }
        return $result;
    }

    public function classify(Question $question, ?SourceExamImport $import, ?SourceExamQuestionDraft $draft): array
    {
        $state = (array) data_get($import?->settings, 'answer_key_verification', []);
        $result = [
            'policy_version' => 1, 'status' => 'unverified', 'source_import_id' => $import?->id,
            'verification_updated_at' => $state['updated_at'] ?? null,
            'source_draft_id' => $draft?->id, 'reason' => 'No verified official answer key.',
        ];
        if (! $import || ($state['outcome'] ?? '') === 'no_key_found') return $result;
        if ((int) $import->organization_id !== (int) $question->organization_id) {
            return [...$result, 'status' => 'review_required', 'reason' => 'The answer-key source belongs to another organization.'];
        }
        if (in_array($state['status'] ?? '', ['queued', 'processing'], true)) {
            return [...$result, 'status' => 'review_required', 'reason' => 'Answer-key verification is still running.'];
        }
        $errors = collect((array) ($state['errors'] ?? []))->filter();
        if ($errors->isNotEmpty() || trim((string) ($state['failure_message'] ?? '')) !== ''
            || in_array($state['status'] ?? '', ['blocked', 'failed'], true)) {
            return [...$result, 'status' => 'review_required', 'reason' => 'Resolve the answer-key warning in the paper editor first.'];
        }
        $checked = (int) ($state['checked'] ?? $state['questions_found'] ?? 0);
        if (! in_array($state['status'] ?? '', ['completed', 'completed_with_warnings'], true) || $checked < 1) return $result;
        if (! $draft) return [...$result, 'reason' => 'This question has no unique mapping to the verified paper draft.'];
        if ((int) $draft->source_exam_import_id !== (int) $import->id || (int) $draft->question_id !== (int) $question->id) {
            return [...$result, 'status' => 'review_required', 'reason' => 'The verified question mapping is invalid.'];
        }
        // The count is paper-wide, never evidence for an arbitrary subset of rows.
        if ($checked < (int) $import->detected_questions) {
            return [...$result, 'status' => 'review_required', 'reason' => 'The paper is only partially verified; complete its key verification first.'];
        }
        $payload = (array) $draft->payload;
        $type = app(QuestionAnswerEvaluator::class)->questionType($question);
        $expected = match ($type) {
            'multiple_choice_radio', 'multiple_choice_checkbox' => ['correct_option_indices' => $this->options($payload)],
            'nat' => ['nat_config' => $payload['nat_config'] ?? null],
            'true_false' => ['true_false' => $payload['true_false'] ?? null],
            'fill_blank' => ['fill_blank' => $payload['fill_blank'] ?? null, 'fill_blank_config' => $payload['fill_blank_config'] ?? null],
            'subjective' => ['si_answer1' => $payload['si_answer1'] ?? null],
            default => [],
        };
        $same = $expected !== [] && collect($expected)->contains(fn ($value) => $value !== null && $value !== '' && $value !== []);
        foreach ($expected as $field => $value) {
            $live = $field === 'correct_option_indices' ? $question->correctOptionIndices() : $question->{$field};
            if ($value != $live) $same = false;
        }
        if (! $same) return [...$result, 'status' => 'review_required', 'reason' => 'The live answer differs from the verified paper draft. Publish or review the official answer first.'];
        return [...$result, 'status' => 'verified', 'reason' => 'Official key verified in the exam editor and matched to this live answer.'];
    }

    private function options(array $payload): array
    {
        $values = $payload['correct_answers'] ?? $payload['correct_option_indices'] ?? [];
        if ($values === [] && preg_match('/^[A-F]$/i', trim((string) ($payload['correct_answer'] ?? '')))) {
            $values = [ord(strtoupper(trim($payload['correct_answer']))) - 64];
        }
        return collect($values)->map(fn ($value) => (int) $value)->filter(fn ($value) => $value >= 1 && $value <= 6)->unique()->sort()->values()->all();
    }
}

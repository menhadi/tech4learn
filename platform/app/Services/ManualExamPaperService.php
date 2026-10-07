<?php

namespace App\Services;

use App\Models\Exam;
use App\Models\ExamQualityAudit;
use App\Models\QuestionRepairDraft;
use App\Models\SourceExamQuestionDraft;
use App\Support\Tenant;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ManualExamPaperService
{
    public function __construct(private QuestionRepairService $repairs)
    {
    }

    public function prepare(Exam $exam, int $userId): Collection
    {
        $tenantId = (int) Tenant::id();
        abort_unless((int) $exam->organization_id === $tenantId, 404);

        $questions = $exam->questions()
            ->with('qtype:id,question_type,type')
            ->orderBy('exam_questions.id')
            ->get();

        $audit = ExamQualityAudit::where('organization_id', $tenantId)
            ->where('exam_id', $exam->id)
            ->where('options->manual_editor', true)
            ->latest('id')
            ->first();

        if (! $audit) {
            $audit = ExamQualityAudit::create([
                'organization_id' => $tenantId,
                'exam_id' => $exam->id,
                'requested_by' => $userId ?: null,
                'public_token' => (string) Str::uuid(),
                'status' => 'completed',
                'include_ai' => false,
                'include_visual' => false,
                'include_source' => true,
                'question_limit' => null,
                'total_questions' => $questions->count(),
                'checked_questions' => $questions->count(),
                'passed_questions' => 0,
                'warning_count' => 0,
                'error_count' => 0,
                'options' => ['manual_editor' => true, 'no_ai_calls' => true],
                'started_at' => now(),
                'completed_at' => now(),
            ]);
        } elseif ((int) $audit->total_questions !== $questions->count()) {
            $audit->update([
                'total_questions' => $questions->count(),
                'checked_questions' => $questions->count(),
            ]);
        }

        $questionIds = $questions->pluck('id')->map(fn ($id) => (int) $id);
        $sourceEvidence = SourceExamQuestionDraft::whereIn('question_id', $questionIds)
            ->whereHas('sourceImport', fn ($query) => $query
                ->where('organization_id', $tenantId)
                ->where('exam_id', $exam->id))
            ->latest('id')
            ->get(['id', 'question_id', 'source_evidence'])
            ->unique('question_id')
            ->keyBy('question_id');

        $auditEvidence = QuestionRepairDraft::where('organization_id', $tenantId)
            ->where('exam_id', $exam->id)
            ->where('audit_id', '!=', $audit->id)
            ->whereIn('question_id', $questionIds)
            ->whereNotNull('evidence')
            ->latest('id')
            ->get(['id', 'question_id', 'evidence'])
            ->unique('question_id')
            ->keyBy('question_id');

        $drafts = collect();
        foreach ($questions as $question) {
            $snapshot = $this->repairs->snapshot($question);
            $draft = QuestionRepairDraft::firstOrNew([
                'audit_id' => $audit->id,
                'question_id' => $question->id,
            ]);

            if (! $draft->exists) {
                $evidence = (array) ($sourceEvidence->get($question->id)?->source_evidence
                    ?: $auditEvidence->get($question->id)?->evidence
                    ?: []);
                $evidence['manual_editor'] = true;
                $draft->fill([
                    'organization_id' => $tenantId,
                    'exam_id' => $exam->id,
                    'status' => 'ready',
                    'original_payload' => $snapshot,
                    'proposed_payload' => $snapshot,
                    'changed_fields' => [],
                    'evidence' => $evidence,
                    'question_updated_at' => $question->updated_at,
                    'created_by' => $userId ?: null,
                ])->save();
            } elseif ($draft->status === 'published' || (
                empty($draft->changed_fields)
                && (! $draft->question_updated_at || ! $draft->question_updated_at->equalTo($question->updated_at))
            )) {
                $draft->update([
                    'status' => 'ready',
                    'original_payload' => $snapshot,
                    'proposed_payload' => $snapshot,
                    'changed_fields' => [],
                    'question_updated_at' => $question->updated_at,
                    'published_at' => null,
                ]);
            }

            $draft->setRelation('question', $question);
            $drafts->push($draft);
        }

        return $drafts;
    }

    public function assertOwned(Exam $exam, QuestionRepairDraft $draft): void
    {
        abort_unless((int) $exam->organization_id === (int) Tenant::id(), 404);
        abort_unless(
            (int) $draft->organization_id === (int) Tenant::id()
            && (int) $draft->exam_id === (int) $exam->id
            && data_get($draft->audit?->options, 'manual_editor') === true,
            404
        );
    }

    public function update(QuestionRepairDraft $draft, array $proposed): QuestionRepairDraft
    {
        $draft->loadMissing('question');
        $original = (array) ($draft->original_payload ?: $this->repairs->snapshot($draft->question));
        $payload = array_replace(
            (array) ($draft->proposed_payload ?: $original),
            collect($proposed)->only(QuestionRepairService::FIELDS)->all()
        );
        $changes = collect($payload)
            ->filter(fn ($value, $field) => json_encode($value) !== json_encode($original[$field] ?? null))
            ->keys()
            ->values()
            ->all();

        $draft->update([
            'proposed_payload' => $payload,
            'changed_fields' => $changes,
            'status' => 'ready',
            'failure_message' => null,
        ]);

        return $draft->fresh(['question.qtype', 'audit']);
    }

    public function publishChanged(Exam $exam, int $userId): int
    {
        return DB::transaction(function () use ($exam, $userId) {
            $drafts = $this->prepare($exam, $userId)
                ->filter(fn (QuestionRepairDraft $draft) => ! empty($draft->changed_fields));

            foreach ($drafts as $draft) {
                $this->repairs->publish($draft, $userId, true);
            }

            return $drafts->count();
        });
    }

    public function paperItems(Exam $exam, int $userId, bool $editable = false): Collection
    {
        return $this->prepare($exam, $userId)->values()->map(function (QuestionRepairDraft $draft, int $index) use ($exam, $editable) {
            $draft->loadMissing('question.qtype');
            return [
                'payload' => array_replace((array) $draft->original_payload, (array) $draft->proposed_payload),
                'label' => 'Paper Q. '.($index + 1).' - Question ID '.$draft->question_id,
                'type' => (string) ($draft->question?->qtype?->question_type ?: $draft->question?->qtype?->type ?: 'Question'),
                'status' => empty($draft->changed_fields) ? 'Live content' : 'Manual draft',
                'edit_url' => $editable ? route('exams.paper.questions.edit', [$exam, $draft]) : null,
            ];
        });
    }
}

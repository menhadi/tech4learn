<?php

namespace App\Services;

use App\Models\ExamQualityAudit;
use App\Models\ExamQualityFinding;
use App\Models\ExamQualitySource;
use App\Models\Question;
use App\Models\QuestionLang;
use App\Models\QuestionRepairDraft;
use App\Models\QuestionRepairRelease;
use App\Models\QuestionRepairReleaseItem;
use App\Models\QuestionVersion;
use App\Models\SourceExamQuestionDraft;
use App\Models\Organization;
use App\Support\SaasAccess;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

class QuestionRepairService
{
    public const FIELDS = [
        'question', 'option1', 'option2', 'option3', 'option4', 'option5', 'option6',
        'answer', 'true_false', 'fill_blank', 'fill_blank_config', 'nat_config',
        'correct_option_indices',
        'si_answer1', 'hint', 'explanation',
    ];

    public function snapshot(Question $question): array
    {
        return $question->only(self::FIELDS);
    }

    /**
     * Build drafts only from evidence saved by the original one-pass audit.
     *
     * Preparing or retrying a draft must never make a second provider request.
     * Older audits without a structured proposal remain available for manual
     * review instead of silently spending more API credits.
     */
    public function processBatch($drafts): void
    {
        $drafts = collect($drafts)->filter()->values();
        if ($drafts->isEmpty()) return;
        $organization = Organization::with('plan')->findOrFail($drafts->first()->organization_id);
        if (! SaasAccess::featureEnabled('exam_quality_ai', $organization)) {
            QuestionRepairDraft::whereIn('id', $drafts->pluck('id'))->update(['status' => 'failed', 'failure_message' => 'AI audit repair is not included in this organization plan.']);
            return;
        }
        $drafts->each(fn ($draft) => $draft->loadMissing(['question.qtype', 'audit', 'exam.groups', 'exam.category', 'exam.packages.category', 'exam.packages.groups']));
        foreach ($drafts as $draft) {
            $this->process($draft, $this->proposalFromAudit($draft) ?? [
                'provider' => 'saved-audit-evidence',
                'proposed_fields' => [],
                'summary' => 'No structured correction was saved by this audit. Review the original finding manually. No additional AI request was made.',
            ]);
        }
    }

    public function process(QuestionRepairDraft $draft, ?array $providedProposal = null): void
    {
        $organization = Organization::with('plan')->findOrFail($draft->organization_id);
        if (! SaasAccess::featureEnabled('exam_quality_ai', $organization)) {
            $draft->update(['status' => 'failed', 'failure_message' => 'AI audit repair is not included in this organization plan.']);
            return;
        }
        $draft->loadMissing(['question.qtype', 'audit', 'exam.groups', 'exam.category', 'exam.packages.category', 'exam.packages.groups']);
        $draft->update(['status' => 'processing', 'failure_message' => null]);

        try {
            $proposal = $providedProposal ?? app(ExamQualitySourceReviewer::class)->proposeRepair(
                $draft->question,
                $draft->exam_id,
                $draft->organization_id,
                (array) data_get($draft->audit?->options, 'source_profile', []),
                (string) data_get($draft->audit?->options, 'source_ai_provider', data_get($draft->audit?->options, 'ai_provider', 'auto'))
            );
            $proposed = collect((array) ($proposal['proposed_fields'] ?? []))
                ->only(self::FIELDS)
                ->map(function ($value, $field) {
                    if (in_array($field, ['fill_blank_config', 'nat_config', 'correct_option_indices'], true) && is_string($value)) {
                        $decoded = json_decode($value, true);
                        return is_array($decoded) ? $decoded : $value;
                    }
                    if ($field === 'correct_option_indices' && is_array($value)) return collect($value)->map(fn ($index) => (int) $index)->filter(fn ($index) => $index >= 1 && $index <= 6)->unique()->sort()->values()->all();
                    if (is_array($value)) return implode(', ', array_map(fn ($item) => is_scalar($item) ? (string) $item : json_encode($item, JSON_UNESCAPED_UNICODE), $value));
                    return is_scalar($value) || $value === null ? $value : (string) $value;
                })->all();

            // Reuse structured corrections captured during the audit. They take
            // precedence over any legacy direct-provider repair response, which
            // could omit a correction the original audit already supplied.
            $proposed = $providedProposal === null
                ? array_replace($proposed, $this->findingProposals($draft))
                : array_replace($this->findingProposals($draft), $proposed);
            $this->normalizeMultipleChoiceAnswers($draft->question, $proposed);

            // A blank provider value must never erase a manually authored explanation.
            // Non-empty explanation proposals remain reviewable for correctness or MathJax normalization.
            $this->preserveManualExplanation($draft->question, $proposed);

            // Preserve image markup until the authoritative PDF synchronization
            // either rebuilds every visual placement or removes stale images.
            $this->preserveOriginalImages($draft->question, $proposed);
            $beforeImageExtraction = $proposed;
            $imageInstructions = array_values(array_filter((array) ($proposal['image_instructions'] ?? []), 'is_array'));
            if ($imageInstructions === [] && ! empty($proposal['image_instruction'])) {
                $imageInstructions[] = (array) $proposal['image_instruction'];
            }
            $removeStoredImages = (bool) ($proposal['remove_stored_images'] ?? false);
            try {
                $imageEvidence = $removeStoredImages
                    ? $this->removeStoredImagesFromProposal($draft, $proposed)
                    : $this->extractProposedImages($draft, $imageInstructions, $proposed);
            } catch (\Throwable $exception) {
                $proposed = $beforeImageExtraction;
                $imageEvidence = ['status' => 'storage_unavailable', 'message' => $exception->getMessage()];
            }
            $original = $this->snapshot($draft->question);
            $changes = collect($proposed)->filter(function ($value, $field) use ($original) {
                return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                    !== json_encode($original[$field] ?? null, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            })->keys()->values()->all();

            $manualReviewReason = $changes === [] ? $this->manualReviewReason($draft) : null;

            $evidence = [
                'provider' => $proposal['provider'] ?? null,
                'model' => $proposal['model'] ?? null,
                'source_review' => ['proposed_fields' => $proposal['source_proposed_fields'] ?? []],
                'academic_review' => $proposal['academic_review'] ?? null,
                'academic_proposed_fields' => $proposal['academic_proposed_fields'] ?? [],
                'conflicts' => $proposal['conflicts'] ?? [],
                'source_reference' => $proposal['source_reference'] ?? null,
                'source_excerpt' => $proposal['source_excerpt'] ?? null,
                'summary' => $proposal['summary'] ?? null,
                'image_instruction' => $imageInstructions[0] ?? null,
                'image_instructions' => $imageInstructions,
                'remove_stored_images' => $removeStoredImages,
                'extracted_image' => $imageEvidence,
                'manual_review_reason' => $manualReviewReason,
                'finding_ids' => ExamQualityFinding::where('audit_id', $draft->audit_id)
                    ->where('question_id', $draft->question_id)->where('status', 'open')->pluck('id')->all(),
            ];
            $evidence = app(StructuredContentReviewService::class)->attachToRepair($draft, $original, $proposed, $evidence);
            $hasStructuredReview = collect((array) ($evidence['structured_content'] ?? []))
                ->contains(fn ($item) => ($item['status'] ?? null) !== 'reviewed');
            $hasConflicts = ! empty($proposal['conflicts']);
            $hasUnresolvedImageReview = $this->imageNeedsReview($imageEvidence, $imageInstructions);

            $draft->update([
                'status' => ($changes === [] || $hasStructuredReview || $hasConflicts || $hasUnresolvedImageReview) ? 'needs_review' : 'ready',
                'original_payload' => $original,
                'proposed_payload' => $proposed,
                'changed_fields' => $changes,
                'evidence' => $evidence,
                'confidence' => $proposal['confidence'] ?? null,
                'question_updated_at' => $draft->question->updated_at,
            ]);
        } catch (\Throwable $e) {
            $draft->update([
                'status' => 'failed',
                'failure_message' => mb_substr($e->getMessage(), 0, 6000),
            ]);
            throw $e;
        }
    }

    private function imageNeedsReview(?array $evidence, array $instructions): bool
    {
        $status = (string) ($evidence['status'] ?? '');
        if (in_array($status, ['storage_unavailable', 'source_unavailable', 'manual_review'], true)) return true;

        $instructionItems = array_is_list($instructions) ? $instructions : [$instructions];
        $explicit = collect($instructionItems)->contains(fn ($instruction) => is_array($instruction)
            && in_array($instruction['action'] ?? 'none', ['sync', 'extract', 'crop', 'replace'], true));
        $resolved = ['canonical_source_visuals_applied', 'source_visuals_synchronized', 'source_visual_synchronized', 'matched_existing', 'replaced_mismatched', 'extracted_missing'];
        return $explicit && ! in_array($status, $resolved, true);
    }

    private function findingProposals(QuestionRepairDraft $draft): array
    {
        return (array) data_get($this->proposalFromAudit($draft), 'proposed_fields', []);
    }

    /** Reuse source-backed corrections already paid for during the audit. */
    private function proposalFromAudit(QuestionRepairDraft $draft): ?array
    {
        $findings = ExamQualityFinding::where('audit_id', $draft->audit_id)
            ->where('question_id', $draft->question_id)
            ->where('status', 'open')
            ->get();
        $fields = $findings->reduce(function (array $carry, ExamQualityFinding $finding) {
            $fields = collect((array) data_get($finding->evidence, 'proposed_fields', []))
                ->only(self::FIELDS)
                ->all();

            return array_replace($carry, $fields);
        }, []);

        $evidence = $findings->first(fn (ExamQualityFinding $finding) =>
            data_get($finding->evidence, 'source_reference')
            || data_get($finding->evidence, 'source_excerpt')
            || data_get($finding->evidence, 'image_instruction')
            || data_get($finding->evidence, 'image_instructions')
            || data_get($finding->evidence, 'remove_stored_images')
        );
        if ($fields === []
            && ! data_get($evidence?->evidence, 'image_instruction')
            && ! data_get($evidence?->evidence, 'image_instructions')
            && ! data_get($evidence?->evidence, 'remove_stored_images')) return null;

        return [
            'provider' => data_get($evidence?->evidence, 'provider', 'audit-source-reuse'),
            'proposed_fields' => $fields,
            'source_reference' => data_get($evidence?->evidence, 'source_reference'),
            'source_excerpt' => data_get($evidence?->evidence, 'source_excerpt'),
            'summary' => $evidence?->details,
            'image_instruction' => data_get($evidence?->evidence, 'image_instruction'),
            'image_instructions' => (array) data_get($evidence?->evidence, 'image_instructions', []),
            'remove_stored_images' => (bool) data_get($evidence?->evidence, 'remove_stored_images', false),
        ];
    }

    private function normalizeMultipleChoiceAnswers(Question $question, array &$proposed): void
    {
        $type = app(QuestionAnswerEvaluator::class)->questionType($question);
        if (! str_starts_with($type, 'multiple_choice') || ! array_key_exists('correct_option_indices', $proposed)) return;

        $proposed['correct_option_indices'] = collect((array) $proposed['correct_option_indices'])
            ->map(fn ($index) => (int) $index)
            ->filter(fn ($index) => $index >= 1 && $index <= 6)
            ->unique()->sort()->values()->all();
    }
    private function manualReviewReason(QuestionRepairDraft $draft): string
    {
        $types = ExamQualityFinding::where('audit_id', $draft->audit_id)
            ->where('question_id', $draft->question_id)
            ->where('status', 'open')
            ->pluck('issue_type');

        if ($types->contains('missing_correct_option')) {
            return 'The source comparison did not provide a trustworthy correct option. Select it manually, or rerun with the authoritative answer source and AI source comparison enabled.';
        }

        if ($types->contains(fn ($type) => str_contains((string) $type, 'math')
            || str_contains((string) $type, 'formula')
            || str_contains((string) $type, 'chem'))) {
            return 'A notation issue was detected, but no source-backed MathJax or chemistry correction was produced. Review it manually or rerun source comparison with a vision-capable provider.';
        }

        return 'The source comparison found an issue but did not produce a safe field-level correction. Review the question manually.';
    }
    /** Remove stored images when the complete authoritative PDF inventory has none. */
    private function removeStoredImagesFromProposal(QuestionRepairDraft $draft, array &$proposed): array
    {
        $removed = [];
        foreach (['question','option1','option2','option3','option4','option5','option6','explanation'] as $field) {
            $current = (string) ($proposed[$field] ?? $draft->question->{$field} ?? '');
            if ($this->imageFragments($current) !== []) $removed[] = $field;
            $proposed[$field] = $this->withoutImages($current);
        }

        return [
            'pipeline_version' => 4,
            'status' => 'source_visuals_synchronized',
            'visuals' => [],
            'removed_stored_image_fields' => $removed,
            'message' => 'Stored images were removed because they are absent from the authoritative PDF.',
        ];
    }

    /** Synchronize every source diagram and assign it to question/option by PDF coordinates. */
    private function extractProposedImages(QuestionRepairDraft $draft, array $instructions, array &$proposed): ?array
    {
        $instructions = array_values(array_filter($instructions, 'is_array'));
        $instruction = $instructions[0] ?? [];
        $fields = ['question','option1','option2','option3','option4','option5','option6','explanation'];
        $explicit = $instructions !== [];
        // The PDF is authoritative for visuals. Stored text is not a reliable gate:
        // a missing diagram may leave no "figure" cue or image tag to detect.
        $manifest = app(SourceQuestionManifestService::class)->forQuestion(
            $draft->question, (int) $draft->exam_id, (int) $draft->organization_id,
            (array) data_get($draft->audit?->options, 'source_profile', []), true
        );
        if (($manifest['status'] ?? null) === 'ready') {
            $manifestEvidence = $this->applySourceManifestImages($draft, $manifest, $proposed);
            if (($manifestEvidence['status'] ?? null) !== 'canonical_source_has_no_detected_visual' || ! $explicit) {
                return $manifestEvidence;
            }
            // Flattened/vector diagrams may not be enumerable in the PDF manifest.
            // When the paid reviewer supplied an exact page and bounding box, crop it
            // automatically instead of forcing the administrator to repeat the work.
            $fallback = $this->extractReviewerCrops($draft, $instructions, $proposed);
            if ($fallback && ! in_array($fallback['status'] ?? null, ['visual_not_detected', 'manual_review'], true)) {
                return array_merge($fallback, [
                    'pipeline_version' => 4,
                    'fallback' => 'reviewer_coordinate_crop',
                    'manifest_status' => $manifestEvidence['status'],
                ]);
            }
            return array_merge($manifestEvidence, [
                'fallback_status' => $fallback['status'] ?? null,
                'fallback_message' => $fallback['message'] ?? null,
            ]);
        }
        if (($manifest['status'] ?? null) === 'storage_unavailable') return $manifest;
        // Always inspect the located source question locally. A source may contain
        // an option image even when stored text has no visual cue or placeholder.

        try { [$pdfPath, $temporaryPdf] = $this->resolveSourcePdf($draft, 'questions'); }
        catch (\Throwable $e) {
            // Never hide a local extraction failure behind the existing image.
            // That made placeholders look like a successful source comparison.
            return [
                'pipeline_version' => 3,
                'status' => 'source_unavailable',
                'message' => $e->getMessage(),
            ];
        }

        $paperNumber = $this->paperQuestionNumber($draft);
        if ($paperNumber < 1) return ['status' => 'manual_review', 'message' => 'The question position in this paper could not be determined.'];
        $temporaryDirectory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'repair-visuals-'.$draft->id.'-'.Str::uuid();
        if (! is_dir($temporaryDirectory) && ! @mkdir($temporaryDirectory, 0775, true) && ! is_dir($temporaryDirectory)) {
            return ['status' => 'storage_unavailable', 'message' => 'The repair-image temporary directory is not writable.'];
        }
        $process = new Process(['python3', base_path('scripts/extract-pdf-question-visuals.py'), $pdfPath,
            $temporaryDirectory, (string) $paperNumber, Str::slug((string) $draft->exam->name)], base_path(), null, null, 240);

        try {
            $process->run();
            $result = json_decode(trim($process->getOutput()), true);
            if (! $process->isSuccessful() || ! ($result['ok'] ?? false)) {
                if ($explicit) {
                    $fallback = $this->extractReviewerCrops($draft, $instructions, $proposed);
                    if ($fallback && ! in_array($fallback['status'] ?? null, ['visual_not_detected', 'manual_review'], true)) {
                        return array_merge($fallback, [
                            'pipeline_version' => 3,
                            'fallback' => 'ai_guided_crop',
                            'deterministic_error' => (string) ($result['error'] ?? trim($process->getErrorOutput())),
                        ]);
                    }
                }
                return ['pipeline_version' => 2, 'status' => 'visual_not_detected', 'message' => (string) ($result['error'] ?? trim($process->getErrorOutput()) ?: 'No source visual was detected.'), 'paper_question_number' => $paperNumber];
            }
            $visuals = collect((array) ($result['visuals'] ?? []))
                ->filter(fn ($visual) => in_array($visual['target_field'] ?? '', $fields, true) && is_file((string) ($visual['path'] ?? '')))->values();
            if ($visuals->isEmpty()) {
                // Some PDFs flatten diagrams/tables into page content that PyMuPDF
                // cannot enumerate. Use the provider's exact source-page crop instead
                // of retaining a placeholder or accepting an HTML reconstruction.
                if ($explicit) {
                    $fallback = $this->extractReviewerCrops($draft, $instructions, $proposed);
                    if ($fallback && ! in_array($fallback['status'] ?? null, ['visual_not_detected', 'manual_review'], true)) {
                        return array_merge($fallback, [
                            'pipeline_version' => 3,
                            'fallback' => 'ai_guided_crop',
                            'deterministic_status' => 'source_has_no_enumerable_visual',
                            'paper_question_number' => $paperNumber,
                        ]);
                    }
                }
                $removed = [];
                foreach ($fields as $field) {
                    $current = (string) ($proposed[$field] ?? $draft->question->{$field} ?? '');
                    if ($this->imageFragments($current) !== []) $removed[] = $field;
                    $proposed[$field] = $this->withoutImages($current);
                }
                return ['pipeline_version' => 2, 'status' => 'source_has_no_visual', 'removed_stored_image_fields' => $removed, 'paper_question_number' => $paperNumber, 'source_page' => $result['page'] ?? null];
            }

            // The located source question is authoritative. Rebuild all visual placement,
            // which also removes placeholders stored in the wrong field.
            foreach ($fields as $field) $proposed[$field] = $this->withoutImages((string) ($proposed[$field] ?? $draft->question->{$field} ?? ''));
            $published = [];
            $imagesByTarget = [];
            foreach ($visuals as $visual) {
                $target = (string) $visual['target_field'];
                $index = max(1, (int) ($visual['index'] ?? 1));
                [$relative, $destination, $publicUrl] = $this->structuredImageDestination($draft, $paperNumber, $target, $index);
                if (! @rename($visual['path'], $destination)) {
                    if (! @copy($visual['path'], $destination)) throw new \RuntimeException('An extracted source image could not be published for preview.');
                    @unlink($visual['path']);
                }
                // Remove a table only when the source proves that this field is an image,
                // not when the PDF itself contains a legitimate table.
                if ($this->sourceVisualReplacesHtmlTable($result, $visual, $target)) {
                    $proposed[$target] = $this->withoutTables((string) $proposed[$target]);
                }
                $alt = $target === 'question' ? 'Question diagram' : ucfirst($target).' diagram';
                $imagesByTarget[$target][] = '<p><img src="'.$publicUrl.'" alt="'.$alt.'"></p>';
                $published[] = array_merge($visual, ['path' => $relative, 'public_url' => $publicUrl]);
            }
            foreach ($imagesByTarget as $target => $images) {
                $proposed[$target] = $this->withImages((string) $proposed[$target], $images);
            }
            return ['pipeline_version' => 2, 'status' => 'source_visuals_synchronized', 'paper_question_number' => $paperNumber,
                'printed_question_number' => $result['printed_question_number'] ?? null, 'source_page' => $result['page'] ?? null,
                'visuals' => $published, 'option_markers' => $result['option_markers'] ?? [],
                'removed_repeated_occurrences' => $result['removed_repeated_occurrences'] ?? 0];
        } finally {
            if (is_callable($temporaryPdf)) $temporaryPdf(); elseif ($temporaryPdf) @unlink($temporaryPdf);
            if (is_dir($temporaryDirectory)) { foreach (glob($temporaryDirectory.DIRECTORY_SEPARATOR.'*') ?: [] as $file) @unlink($file); @rmdir($temporaryDirectory); }
        }
    }


    /** Apply visuals already extracted and evidenced by the audit manifest. */
    private function applySourceManifestImages(QuestionRepairDraft $draft, array $manifest, array &$proposed): array
    {
        $fields = ['question','option1','option2','option3','option4','option5','option6','explanation'];
        $visuals = collect((array) ($manifest['visuals'] ?? []))->filter(function (array $visual) use ($fields) {
            $privateReady = ! empty($visual['private_path']) && Storage::disk('local')->exists($visual['private_path']);
            $temporaryReady = ! empty($visual['temporary_path']) && is_file($visual['temporary_path']);
            return in_array($visual['target_field'] ?? '', $fields, true) && ($privateReady || $temporaryReady);
        })->values();

        if ($visuals->isEmpty()) {
            return [
                'pipeline_version' => 4,
                'status' => 'canonical_source_has_no_detected_visual',
                'source_manifest_schema' => $manifest['schema_version'] ?? 1,
                'paper_question_number' => $manifest['paper_question_number'] ?? null,
                'source_pages' => $manifest['source_pages'] ?? [],
                'visuals' => [],
            ];
        }

        // A document-level canonical manifest is authoritative for visual
        // placement across the whole source question. Clear every canonical
        // question/option field first so an image assigned to an option cannot
        // survive as a stale duplicate in the question (or another option).
        foreach ($this->canonicalVisualFields($manifest, $visuals->pluck('target_field')->all()) as $field) {
            $proposed[$field] = $this->withoutImages((string) ($proposed[$field] ?? $draft->question->{$field} ?? ''));
        }
        $published = [];
        $imagesByTarget = [];
        try {
            foreach ($visuals as $visual) {
                $target = (string) $visual['target_field'];
                $index = max(1, (int) ($visual['index'] ?? 1));
                [$relative, $destination, $publicUrl] = $this->structuredImageDestination(
                    $draft, (int) $manifest['paper_question_number'], $target, $index
                );
                $source = ! empty($visual['temporary_path'])
                    ? (string) $visual['temporary_path']
                    : Storage::disk('local')->path($visual['private_path']);
                if (! @copy($source, $destination)) throw new \RuntimeException('A canonical source visual could not be copied into the repair preview.');
                if ($this->sourceVisualReplacesHtmlTable($manifest, $visual, $target)) {
                    $proposed[$target] = $this->withoutTables((string) $proposed[$target]);
                }
                $alt = $target === 'question' ? 'Question diagram' : ucfirst($target).' diagram';
                $imagesByTarget[$target][] = '<p><img src="'.$publicUrl.'" alt="'.$alt.'"></p>';
                $published[] = array_merge(collect($visual)->except('temporary_path')->all(), ['path' => $relative, 'public_url' => $publicUrl]);
            }
            foreach ($imagesByTarget as $target => $images) {
                $proposed[$target] = $this->withImages((string) $proposed[$target], $images);
            }
        } finally {
            foreach ($visuals as $visual) {
                $temporary = (string) ($visual['temporary_path'] ?? '');
                if ($temporary !== '') { @unlink($temporary); @rmdir(dirname($temporary)); }
            }
        }

        return [
            'pipeline_version' => 4,
            'status' => 'canonical_source_visuals_applied',
            'source_manifest_schema' => $manifest['schema_version'] ?? 1,
            'paper_question_number' => $manifest['paper_question_number'] ?? null,
            'printed_question_number' => $manifest['printed_question_number'] ?? null,
            'source_pages' => $manifest['source_pages'] ?? [],
            'visuals' => $published,
            'option_markers' => $manifest['option_markers'] ?? [],
        ];
    }
    private function structuredImageDestination(QuestionRepairDraft $draft, int $paperNumber, string $target, int $index): array
    {
        $exam = $draft->exam;
        $group = $exam->groups->sortBy(fn ($item) => [$item->display_order ?: PHP_INT_MAX, $item->id])->first()
            ?: $exam->packages->flatMap(fn ($package) => $package->groups)->sortBy(fn ($item) => [$item->display_order ?: PHP_INT_MAX, $item->id])->first();
        $category = $exam->category ?: $exam->packages->pluck('category')->filter()->first();
        $groupName = $group?->group_name;
        if (is_array($groupName)) $groupName = collect($groupName)->filter()->first();
        $segments = [Str::slug((string) ($groupName ?: 'ungrouped')) ?: 'ungrouped',
            Str::slug((string) ($category?->title ?: 'uncategorized')) ?: 'uncategorized',
            Str::slug((string) ($exam->name ?: 'paper-'.$exam->id)) ?: 'paper-'.$exam->id];
        $filename = $target === 'question' ? 'q_'.$paperNumber.'_img'.$index.'.png' : 'q_'.$paperNumber.'_'.$target.'_image'.$index.'.png';
        $relative = 'question-repairs/'.implode('/', $segments).'/'.$filename;
        $disk = Storage::disk('public');
        $directory = str_replace('\\', '/', dirname($relative));
        if (! $disk->makeDirectory($directory) && ! $disk->directoryExists($directory)) {
            throw new \RuntimeException('The repair-image storage directory is not writable.');
        }
        $destination = $disk->path($relative);
        $publicUrl = $disk->url($relative).'?v='.time();
        return [$relative, $destination, $publicUrl];
    }

    /** Crop every visual reported from the authoritative PDF, preserving multiple visuals per field. */
    private function extractReviewerCrops(QuestionRepairDraft $draft, array $instructions, array &$proposed): ?array
    {
        if ($instructions === []) return null;
        foreach (['question','option1','option2','option3','option4','option5','option6','explanation'] as $field) {
            $proposed[$field] = $this->withoutImages((string) ($proposed[$field] ?? $draft->question->{$field} ?? ''));
        }
        $published = [];
        $failures = [];
        $targetCounts = [];
        foreach ($instructions as $instruction) {
            $target = (string) ($instruction['target_field'] ?? 'question');
            $targetCounts[$target] = ($targetCounts[$target] ?? 0) + 1;
            $imageIndex = max(1, (int) ($instruction['image_index'] ?? $targetCounts[$target]));
            $append = $targetCounts[$target] > 1;
            $result = $this->extractProposedImage($draft, $instruction, $proposed, $append, $imageIndex);
            if (($result['status'] ?? null) === 'source_visual_synchronized') $published[] = $result;
            else $failures[] = $result ?: ['status' => 'manual_review', 'message' => 'The PDF crop returned no result.'];
        }
        if ($published === []) return $failures[0] ?? ['status' => 'manual_review', 'message' => 'No authoritative PDF visual could be cropped.'];

        return [
            'pipeline_version' => 5,
            'status' => $failures === [] ? 'source_visuals_synchronized' : 'partial_visual_sync',
            'visuals' => $published,
            'failed_visuals' => $failures,
            'source_pages' => collect($published)->pluck('source_page')->unique()->values()->all(),
            'message' => $failures === [] ? null : count($failures).' PDF visual crop(s) need manual review.',
        ];
    }
    private function extractProposedImage(QuestionRepairDraft $draft, array $instruction, array &$proposed, bool $append = false, int $imageIndex = 1): ?array
    {
        $imageFields = ['question','option1','option2','option3','option4','option5','option6','explanation'];
        $explicit = in_array($instruction['action'] ?? 'none', ['sync','extract','crop','replace'], true);
        $target = (string) ($instruction['target_field'] ?? 'question');
        if (! in_array($target, $imageFields, true)) $target = 'question';

        $storedTarget = collect($imageFields)->first(fn ($field) => $this->imageFragments((string) ($draft->question->{$field} ?? '')) !== []);
        if ($storedTarget && ! $explicit) $target = $storedTarget;
        $questionHtml = implode(' ', array_map(fn ($field) => (string) ($draft->question->{$field} ?? ''), $imageFields));
        $questionText = strip_tags($questionHtml);
        $hasVisualCue = (bool) preg_match('/\b(?:figure|diagram|graph|chart|plot|table|shown\s+(?:below|above|in\s+the\s+figure)|following\s+image)\b/i', $questionText);
        $hasTableMarkup = stripos($questionHtml, '<table') !== false;
        if (! $explicit && ! $storedTarget && ! $hasVisualCue && ! $hasTableMarkup) return null;

        $bbox = $instruction['bbox_normalized'] ?? [0.05, 0.05, 0.95, 0.95];
        if (! is_array($bbox) || count($bbox) !== 4) $bbox = [0.05, 0.05, 0.95, 0.95];
        $bbox = array_map('floatval', $bbox);
        $page = max(0, (int) ($instruction['source_page'] ?? 0));

        $sourceQuery = ExamQualitySource::where('organization_id', $draft->organization_id)
            ->where('exam_id', $draft->exam_id)->where('is_active', true)
            ->whereIn('role', ['questions','combined'])->orderByRaw("FIELD(role, 'questions', 'combined')");
        $source = (clone $sourceQuery)->where('kind', 'file')->first() ?: (clone $sourceQuery)->where('kind', 'url')->first();
        if (! $source) return $explicit ? ['status' => 'manual_review', 'message' => 'No question PDF/source is attached to this exam.'] : null;

        $temporaryPdf = null;
        if ($source->kind === 'file' && $source->file_path) {
            try { [$pdfPath, $temporaryPdf] = app(ExamQualitySourceStorage::class)->localPath($source); }
            catch (\Throwable $e) { return ['status' => 'manual_review', 'message' => 'The source PDF could not be prepared: '.$e->getMessage()]; }
        } else {
            $sourceUrl = (string) ($source->source_url ?? '');
            $remoteUrl = str_ends_with(strtolower((string) parse_url($sourceUrl, PHP_URL_PATH)), '.pdf') ? $sourceUrl : (string) $draft->question->source_url;
            if (! filter_var($remoteUrl, FILTER_VALIDATE_URL) || ! str_ends_with(strtolower((string) parse_url($remoteUrl, PHP_URL_PATH)), '.pdf')) return $explicit ? ['status' => 'manual_review', 'message' => 'A direct PDF source URL is required.'] : null;
            try {
                $body = Http::timeout(120)->retry(2, 750)->withHeaders(['User-Agent' => 'ExamQualityRepair/1.0'])->get($remoteUrl)->throw()->body();
                if ($body === '') throw new \RuntimeException('The remote PDF is empty.');
                $temporaryPdf = $this->temporaryFile('repair-source-', '.pdf');
                file_put_contents($temporaryPdf, $body); $pdfPath = $temporaryPdf;
            } catch (\Throwable $e) { return ['status' => 'manual_review', 'message' => 'The source PDF could not be downloaded: '.$e->getMessage()]; }
        }

        $paperQuestionNumber = $this->paperQuestionNumber($draft);
        [$relative, $destination, $publicUrl] = $this->structuredImageDestination(
            $draft, $paperQuestionNumber, $target, $imageIndex
        );
        $output = $this->temporaryFile('repair-extract-', '.'.pathinfo($destination, PATHINFO_EXTENSION));
        $args = ['python3', base_path('scripts/extract-pdf-visual.py'), $pdfPath, $output, (string) $page];
        foreach ($bbox as $coordinate) $args[] = (string) $coordinate;
        $args[] = (string) $paperQuestionNumber;
        $process = new Process($args, base_path(), null, null, 180);
        try {
            $process->run();
            $result = json_decode(trim($process->getOutput()), true);
            if (! $process->isSuccessful() || ! is_file($output)) {
                @unlink($output);
                // Only an explicit, reviewer-supplied crop is safe as a fallback.
                if (! $explicit || $page < 1) return ['pipeline_version' => 2, 'status' => 'visual_not_detected', 'message' => (string) ($result['error'] ?? 'No source visual was detected for this question.'), 'paper_question_number' => $paperQuestionNumber ?: null];
                $fallbackArgs = ['python3', base_path('scripts/extract-pdf-region.py'), $pdfPath, $output, (string) $page];
                foreach ($bbox as $coordinate) $fallbackArgs[] = (string) $coordinate;
                $fallback = new Process($fallbackArgs, base_path(), null, null, 180); $fallback->run();
                $fallbackResult = json_decode(trim($fallback->getOutput()), true);
                if (! $fallback->isSuccessful() || ! is_file($output)) return ['status' => 'manual_review', 'message' => (string) ($result['error'] ?? $fallbackResult['error'] ?? 'PDF visual extraction failed.')];
                $result = array_merge((array) $fallbackResult, ['mode' => 'reviewer_bbox_fallback', 'bbox_normalized' => $bbox]);
            }
        } finally {
            if (is_callable($temporaryPdf)) $temporaryPdf(); elseif ($temporaryPdf) @unlink($temporaryPdf);
        }

        if (($result['visual_type'] ?? null) === 'table') {
            $proposed[$target] = $this->withoutTables((string) ($proposed[$target] ?? ''));
        }

        if (! @rename($output, $destination)) {
            if (! @copy($output, $destination)) throw new \RuntimeException('The extracted repair image could not be published for preview.');
            @unlink($output);
        }
        $current = (string) ($proposed[$target] ?? $draft->question->{$target} ?? '');
        $images = $append ? $this->imageFragments($current) : [];
        $images[] = '<p><img src="'.$publicUrl.'" alt="Question diagram"></p>';
        $proposed[$target] = $this->withImages($this->withoutImages($current), $images);
        return [
            'status' => 'source_visual_synchronized', 'path' => $relative, 'public_url' => $publicUrl,
            'target_field' => $target, 'image_index' => $imageIndex,
            'source_page' => $result['page'] ?? $page,
            'bbox_normalized' => $result['bbox_normalized'] ?? $bbox, 'extraction_mode' => $result['mode'] ?? 'visual_object_detection',
            'paper_question_number' => $paperQuestionNumber ?: null, 'question_band_detected' => $result['question_band_detected'] ?? false,
            'embedded_candidates' => $result['embedded_candidates'] ?? null, 'vector_candidates' => $result['vector_candidates'] ?? null,
            'visual_type' => $result['visual_type'] ?? null, 'removed_repeated_occurrences' => $result['removed_repeated_occurrences'] ?? 0,
        ];
    }

    private function paperQuestionNumber(QuestionRepairDraft $draft): int
    {
        $pivotId = DB::table('exam_questions')
            ->where('exam_id', $draft->exam_id)
            ->where('question_id', $draft->question_id)
            ->orderBy('id')
            ->value('id');
        if (! $pivotId) return 0;

        return DB::table('exam_questions')
            ->where('exam_id', $draft->exam_id)
            ->where('id', '<=', $pivotId)
            ->count();
    }

    public function detectedSourcePage(QuestionRepairDraft $draft): int
    {
        $evidence = (array) $draft->evidence;
        $candidates = collect([
            data_get($evidence, 'manual_crops.question.source_page'),
            data_get($evidence, 'extracted_image.source_page'),
            data_get($evidence, 'image_instruction.source_page'),
            data_get($evidence, 'image_instructions.0.source_page'),
            data_get($evidence, 'extracted_image.source_pages.0'),
            data_get($evidence, 'pages.0'),
        ])->map(fn ($page) => (int) $page)->filter(fn ($page) => $page > 0);
        if ($candidates->isNotEmpty()) return (int) $candidates->first();

        $sourceDraft = SourceExamQuestionDraft::where('organization_id', $draft->organization_id)
            ->where('question_id', $draft->question_id)
            ->whereHas('sourceImport', fn ($query) => $query->where('exam_id', $draft->exam_id))
            ->latest('id')
            ->first(['id', 'source_evidence']);
        $sourceEvidence = (array) ($sourceDraft?->source_evidence ?? []);

        return max(1, (int) collect([
            data_get($sourceEvidence, 'manual_crops.question.source_page'),
            data_get($sourceEvidence, 'extracted_image.source_page'),
            data_get($sourceEvidence, 'pages.0'),
        ])->map(fn ($page) => (int) $page)->first(fn ($page) => $page > 0, 1));
    }
    public function renderSourcePage(QuestionRepairDraft $draft, int $page, string $sourceRole = 'questions'): string
    {
        [$pdfPath, $temporaryPdf] = $this->resolveSourcePdf($draft, $sourceRole);
        $output = $this->temporaryFile('repair-source-page-', '.png');
        try { $this->runPdfCrop($pdfPath, $output, $page, [0, 0, 1, 1]); }
        finally { if (is_callable($temporaryPdf)) $temporaryPdf(); elseif ($temporaryPdf) @unlink($temporaryPdf); }
        return $output;
    }

    public function extractMathpixCrop(QuestionRepairDraft $draft, int $page, array $bbox, string $sourceRole = 'questions'): string
    {
        [$pdfPath, $temporaryPdf] = $this->resolveSourcePdf($draft, $sourceRole);
        $output = $this->temporaryFile('repair-mathpix-', '.png');
        try { $this->runPdfCrop($pdfPath, $output, $page, $bbox, 'white'); }
        catch (\Throwable $exception) { @unlink($output); throw $exception; }
        finally { if (is_callable($temporaryPdf)) $temporaryPdf(); elseif ($temporaryPdf) @unlink($temporaryPdf); }
        return $output;
    }

    public function applyManualOcrText(QuestionRepairDraft $draft, string $target, string $html, array $recognition = []): array
    {
        $fields = ['question','option1','option2','option3','option4','option5','option6','explanation'];
        if (! in_array($target, $fields, true)) throw new \InvalidArgumentException('Invalid text target field.');
        if (trim(strip_tags($html)) === '' && ! str_contains($html, '\\(') && ! str_contains($html, '\\[')) throw new \InvalidArgumentException('Recognized text cannot be empty.');
        if (mb_strlen($html) > 100000) throw new \InvalidArgumentException('Recognized text is too long for one question field.');
        $proposed = (array) ($draft->proposed_payload ?: $draft->original_payload ?: $this->snapshot($draft->question));
        $proposed[$target] = $html;
        $original = (array) ($draft->original_payload ?: $this->snapshot($draft->question));
        $changes = collect($proposed)->filter(fn ($value, $field) => json_encode($value) !== json_encode($original[$field] ?? null))->keys()->values()->all();
        $evidence = (array) $draft->evidence;
        $evidence['manual_mathpix_ocr'][$target] = array_merge($recognition, ['target_field' => $target, 'saved_at' => now()->toIso8601String()]);
        $draft->update(['proposed_payload' => $proposed, 'changed_fields' => $changes, 'evidence' => $evidence, 'status' => $draft->status === 'needs_review' ? 'needs_review' : 'ready']);
        return (array) $evidence['manual_mathpix_ocr'][$target];
    }
    public function applyManualImageCrop(QuestionRepairDraft $draft, int $page, array $bbox, string $target, string $sourceRole = 'questions', string $backgroundMode = 'white'): array
    {
        $imageFields = ['question','option1','option2','option3','option4','option5','option6','explanation'];
        if (! in_array($target, $imageFields, true)) throw new \InvalidArgumentException('Invalid image target field.');
        if (! in_array($backgroundMode, ['white', 'transparent'], true)) throw new \InvalidArgumentException('Invalid crop background mode.');
        [$pdfPath, $temporaryPdf] = $this->resolveSourcePdf($draft, $sourceRole);
        [$relative, $destination, $publicUrl] = $this->structuredImageDestination($draft, $this->paperQuestionNumber($draft), $target, 1);
        $output = $this->temporaryFile('repair-manual-', '.'.pathinfo($destination, PATHINFO_EXTENSION));
        try { $this->runPdfCrop($pdfPath, $output, $page, $bbox, $backgroundMode); }
        finally { if (is_callable($temporaryPdf)) $temporaryPdf(); elseif ($temporaryPdf) @unlink($temporaryPdf); }
        if (! @rename($output, $destination)) {
            if (! @copy($output, $destination)) throw new \RuntimeException('The manually cropped image could not be published for preview.');
            @unlink($output);
        }
        $proposed = (array) ($draft->proposed_payload ?: $draft->original_payload ?: $this->snapshot($draft->question));
        // Manual crop is an explicit replacement; remove every old image from this field first.
        $existing = $this->withoutImages((string) ($proposed[$target] ?? $draft->question->{$target} ?? ''));
        if (preg_match('/^(?:image|diagram|figure)\s+(?:will\s+appear\s+soon|not\s+available|missing)$/i', trim(strip_tags($existing)))) $existing = '';
        $optionIndex = str_starts_with($target, 'option') ? (int) substr($target, 6) : 0;
        $alt = $optionIndex > 0 ? 'Option '.chr(64 + $optionIndex).' diagram' : 'Question diagram';
        $proposed[$target] = $this->withImages($existing, ['<p><img src="'.$publicUrl.'" alt="'.$alt.'"></p>']);
        $original = (array) ($draft->original_payload ?: $this->snapshot($draft->question));
        $changes = collect($proposed)->filter(fn ($value, $field) => json_encode($value) !== json_encode($original[$field] ?? null))->keys()->values()->all();
        $evidence = (array) $draft->evidence;
        $cropEvidence = ['status' => 'manually_cropped', 'path' => $relative, 'target_field' => $target, 'source_page' => $page, 'bbox_normalized' => array_map('floatval', $bbox), 'source_role' => $sourceRole, 'background_mode' => $backgroundMode];
        $manualCrops = (array) ($evidence['manual_crops'] ?? []);
        $manualCrops[$target] = $cropEvidence;
        $evidence['manual_crops'] = $manualCrops;
        $evidence['extracted_image'] = $cropEvidence;
        $hasPendingStructuredReview = collect((array) data_get($evidence, 'structured_content', []))
            ->contains(fn ($item) => ($item['status'] ?? null) !== 'reviewed');
        $draft->update([
            'proposed_payload' => $proposed,
            'changed_fields' => $changes,
            'evidence' => $evidence,
            'status' => $hasPendingStructuredReview ? 'needs_review' : 'ready',
        ]);

        return $cropEvidence;
    }
    private function preserveManualExplanation(Question $question, array &$proposed): void
    {
        if (! array_key_exists('explanation', $proposed)) return;
        $original = (string) ($question->explanation ?? '');
        $candidate = (string) ($proposed['explanation'] ?? '');
        if (trim(strip_tags($original)) !== '' && trim(strip_tags($candidate)) === '') {
            unset($proposed['explanation']);
        }
    }

    private function preserveOriginalImages(Question $question, array &$proposed): void
    {
        foreach (['question','option1','option2','option3','option4','option5','option6','explanation'] as $field) {
            if (! array_key_exists($field, $proposed)) continue;

            $original = (string) ($question->{$field} ?? '');
            $images = $this->imageFragments($original);
            if ($images === []) continue;

            // An image-only source field must remain image-only. Provider prose is
            // not source evidence and must never be appended beside an option image.
            $originalText = trim(strip_tags($this->withoutImages($original)));
            $proposedText = trim(strip_tags($this->withoutImages((string) ($proposed[$field] ?? ''))));
            if ($originalText === '' && $proposedText !== '') {
                $proposed[$field] = $original;
                continue;
            }

            $proposed[$field] = $this->withImages((string) ($proposed[$field] ?? ''), $images);
        }
    }
    private function imageFragments(string $html): array
    {
        preg_match_all('#(?:<p[^>]*>\s*)?<img\b[^>]*>(?:\s*</p>)?#is', $html, $matches);
        return array_values(array_unique(array_filter(array_map('trim', $matches[0] ?? []))));
    }

    private function withoutImages(string $html): string
    {
        return trim((string) preg_replace('#(?:<p[^>]*>\s*)?<img\b[^>]*>(?:\s*</p>)?#is', '', $html));
    }

    private function withoutTables(string $html): string
    {
        return trim((string) preg_replace('#<table\b[^>]*>.*?</table>#is', '', $html));
    }

    private function sourceVisualReplacesHtmlTable(array $sourceEvidence, array $visual, string $target): bool
    {
        if (($visual['visual_type'] ?? null) === 'table') return true;
        if (! array_key_exists('source_html_table_targets', $sourceEvidence)) return false;

        return ! in_array($target, (array) $sourceEvidence['source_html_table_targets'], true);
    }

    private function canonicalVisualFields(array $manifest, array $fallbackTargets): array
    {
        $allowed = ['question', 'option1', 'option2', 'option3', 'option4', 'option5', 'option6'];
        $canonical = array_keys((array) ($manifest['canonical_fields'] ?? []));

        // Only a successfully located document-level question can assert that
        // the absence of an image in a field is meaningful.
        if (($manifest['question_band_detected'] ?? false) && $canonical !== []) {
            return array_values(array_intersect($allowed, $canonical));
        }

        return array_values(array_intersect($allowed, array_unique($fallbackTargets)));
    }
    private function withImages(string $html, array $images): string
    {
        $text = $this->withoutImages($html);
        return trim($text.($images === [] ? '' : "\n".implode("\n", $images)));
    }
    private function temporaryFile(string $prefix, string $suffix = ''): string
    {
        $path = @tempnam(sys_get_temp_dir(), $prefix);
        if ($path === false) throw new \RuntimeException('The system temporary directory is not writable.');
        if ($suffix === '') return $path;
        $suffixed = $path.$suffix;
        if (! @rename($path, $suffixed)) { @unlink($path); throw new \RuntimeException('A temporary repair file could not be prepared.'); }
        return $suffixed;
    }

    private function resolveSourcePdf(QuestionRepairDraft $draft, string $sourceRole = 'questions'): array
    {
        $query = ExamQualitySource::where('organization_id', $draft->organization_id)->where('exam_id', $draft->exam_id)->where('is_active', true)->whereIn('role', [$sourceRole, 'combined', 'questions', 'answers'])
            ->orderByRaw("FIELD(role, ?, 'combined', 'questions', 'answers')", [$sourceRole]);
        $source = (clone $query)->where('kind', 'file')->first() ?: (clone $query)->where('kind', 'url')->first();
        if ($source?->kind === 'file' && $source->file_path) return app(ExamQualitySourceStorage::class)->localPath($source);
        $sourceUrl = (string) ($source?->source_url ?? '');
        $remoteUrl = str_ends_with(strtolower((string) parse_url($sourceUrl, PHP_URL_PATH)), '.pdf') ? $sourceUrl : (string) $draft->question->source_url;
        if (! filter_var($remoteUrl, FILTER_VALIDATE_URL) || ! str_ends_with(strtolower((string) parse_url($remoteUrl, PHP_URL_PATH)), '.pdf')) throw new \RuntimeException('A private uploaded PDF or direct PDF source URL is required for cropping.');
        $response = Http::timeout(120)->retry(2, 750)->withHeaders(['User-Agent' => 'ExamQualityRepair/1.0'])->get($remoteUrl)->throw();
        $temporaryPdf = $this->temporaryFile('repair-source-', '.pdf');
        file_put_contents($temporaryPdf, $response->body());
        return [$temporaryPdf, $temporaryPdf];
    }

    private function runPdfCrop(string $pdfPath, string $output, int $page, array $bbox, string $backgroundMode = 'white'): void
    {
        $args = ['python3', base_path('scripts/extract-pdf-region.py'), $pdfPath, $output, (string) $page];
        foreach ($bbox as $coordinate) $args[] = (string) ((float) $coordinate);
        $args[] = $backgroundMode;
        $process = new Process($args, base_path(), null, null, 180);
        $process->run();
        $result = json_decode(trim($process->getOutput()), true);
        if (! $process->isSuccessful() || ! is_file($output)) throw new \RuntimeException((string) ($result['error'] ?? trim($process->getErrorOutput()) ?: 'PDF crop extraction failed.'));
    }

    public function restoreVersion(QuestionVersion $version, int $userId): Question
    {
        return DB::transaction(function () use ($version, $userId) {
            $version = QuestionVersion::whereKey($version->id)->lockForUpdate()->firstOrFail();
            $question = Question::whereKey($version->question_id)->lockForUpdate()->firstOrFail();
            if ((int) $question->organization_id !== (int) $version->organization_id) abort(404);
            $payload = collect((array) $version->payload)->only(self::FIELDS)->all();
            if ($payload === []) throw new \RuntimeException('This saved version has no restorable question content.');
            QuestionVersion::create(['organization_id' => $version->organization_id, 'question_id' => $question->id, 'repair_draft_id' => $version->repair_draft_id, 'payload' => $this->snapshot($question), 'created_by' => $userId]);
            $question->update($payload);
            $langChanges = collect($payload)->only(['question','option1','option2','option3','option4','option5','option6','hint','explanation','fill_blank'])->all();
            if ($langChanges !== []) QuestionLang::where('question_id', $question->id)->where('language_id', $question->language_id)->update($langChanges);
            return $question->fresh();
        });
    }

    public function publishBatch(ExamQualityAudit $audit, array $draftIds, int $userId, ?string $name = null, bool $allowNeedsReview = false): QuestionRepairRelease
    {
        return DB::transaction(function () use ($audit, $draftIds, $userId, $name, $allowNeedsReview) {
            $drafts = QuestionRepairDraft::where('organization_id', $audit->organization_id)
                ->where('audit_id', $audit->id)->whereIn('id', $draftIds)
                ->lockForUpdate()->get();
            if ($drafts->count() !== count(array_unique(array_map('intval', $draftIds)))) {
                throw new \RuntimeException('One or more selected repair drafts do not belong to this paper.');
            }
            if ($drafts->isEmpty()) throw new \RuntimeException('Select at least one repair draft.');
            foreach ($drafts as $draft) {
                $allowedStatuses = $allowNeedsReview ? ['ready', 'needs_review'] : ['ready'];
                if (! in_array($draft->status, $allowedStatuses, true)) throw new \RuntimeException('Question '.$draft->question_id.' is not ready to publish.');
                if (empty($draft->changed_fields)) throw new \RuntimeException('Question '.$draft->question_id.' has no reviewed repair changes.');
                $question = Question::whereKey($draft->question_id)->lockForUpdate()->firstOrFail();
                if ($draft->question_updated_at && ! $question->updated_at->equalTo($draft->question_updated_at)) throw new \RuntimeException('Question '.$draft->question_id.' changed after its draft was generated. Regenerate it before publishing.');
            }

            $release = QuestionRepairRelease::create([
                'organization_id' => $audit->organization_id, 'audit_id' => $audit->id, 'exam_id' => $audit->exam_id,
                'name' => $name ?: $audit->exam->name.' repair release '.now()->format('Y-m-d H:i'),
                'status' => 'published', 'created_by' => $userId, 'published_at' => now(),
            ]);
            foreach ($drafts as $draft) {
                $this->publish($draft, $userId, $allowNeedsReview);
                $version = QuestionVersion::where('repair_draft_id', $draft->id)->latest('id')->firstOrFail();
                QuestionRepairReleaseItem::create([
                    'release_id' => $release->id, 'question_id' => $draft->question_id,
                    'repair_draft_id' => $draft->id, 'published_version_id' => $version->id, 'status' => 'published',
                ]);
            }
            return $release->fresh(['items.question']);
        });
    }

    public function restoreRelease(QuestionRepairRelease $release, array $itemIds, int $userId): int
    {
        return DB::transaction(function () use ($release, $itemIds, $userId) {
            $release = QuestionRepairRelease::whereKey($release->id)->lockForUpdate()->firstOrFail();
            $query = QuestionRepairReleaseItem::where('release_id', $release->id)->where('status', 'published');
            if ($itemIds !== []) $query->whereIn('id', $itemIds);
            $items = $query->with('publishedVersion')->lockForUpdate()->get();
            if ($itemIds !== [] && $items->count() !== count(array_unique(array_map('intval', $itemIds)))) throw new \RuntimeException('One or more selected release questions are invalid or already restored.');
            if ($items->isEmpty()) throw new \RuntimeException('No unrestored release questions were selected.');

            foreach ($items as $item) {
                $before = QuestionVersion::where('question_id', $item->question_id)->max('id');
                $this->restoreVersion($item->publishedVersion, $userId);
                $backup = QuestionVersion::where('question_id', $item->question_id)->where('id', '>', (int) $before)->latest('id')->first();
                $item->update(['status' => 'restored', 'restored_version_id' => $backup?->id, 'restored_at' => now()]);
            }
            $remaining = QuestionRepairReleaseItem::where('release_id', $release->id)->where('status', 'published')->count();
            $release->update(['status' => $remaining ? 'partially_restored' : 'restored', 'restored_at' => $remaining ? null : now()]);
            return $items->count();
        });
    }

    public function publish(QuestionRepairDraft $draft, int $userId, bool $allowNeedsReview = false): Question
    {
        return DB::transaction(function () use ($draft, $userId, $allowNeedsReview) {
            $draft = QuestionRepairDraft::whereKey($draft->id)->lockForUpdate()->firstOrFail();
            $question = Question::whereKey($draft->question_id)->lockForUpdate()->firstOrFail();
            if ((int) $question->organization_id !== (int) $draft->organization_id) abort(404);
            $allowedStatuses = $allowNeedsReview ? ['ready', 'needs_review'] : ['ready'];
            if (! in_array($draft->status, $allowedStatuses, true)) {
                throw new \RuntimeException('Only a reviewed repair draft with approved field changes can be published.');
            }
            if ($draft->question_updated_at && ! $question->updated_at->equalTo($draft->question_updated_at)) {
                throw new \RuntimeException('This question changed after the draft was generated. Regenerate the draft before publishing.');
            }

            $changes = collect((array) $draft->proposed_payload)->only(self::FIELDS)->all();
            if (empty($draft->changed_fields)) throw new \RuntimeException('This draft has no approved field changes to publish.');

            QuestionVersion::create([
                'organization_id' => $draft->organization_id,
                'question_id' => $question->id,
                'repair_draft_id' => $draft->id,
                'payload' => $this->snapshot($question),
                'created_by' => $userId,
            ]);

            $question->update($changes);
            $langChanges = collect($changes)->only([
                'question','option1','option2','option3','option4','option5','option6','hint','explanation','fill_blank',
            ])->all();
            if ($langChanges !== []) {
                QuestionLang::where('question_id', $question->id)
                    ->where('language_id', $question->language_id)
                    ->update($langChanges);
            }

            ExamQualityFinding::where('audit_id', $draft->audit_id)
                ->where('question_id', $question->id)
                ->where('status', 'open')
                ->update(['status' => 'resolved', 'reviewed_by' => $userId, 'reviewed_at' => now()]);

            $draft->update([
                'status' => 'published', 'reviewed_by' => $userId,
                'reviewed_at' => now(), 'published_at' => now(),
            ]);

            return $question->fresh();
        });
    }
}

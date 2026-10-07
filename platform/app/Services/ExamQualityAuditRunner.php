<?php

namespace App\Services;

use App\Models\Configuration;
use App\Models\ExamQualityAudit;
use App\Models\ExamQualityFinding;
use App\Models\Organization;
use App\Support\AiProvider;
use App\Support\SaasAccess;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use App\Support\ResourceSlotLimiter;

class ExamQualityAuditRunner
{
    public function run(ExamQualityAudit $audit): void
    {
        if ($this->cancelIfRequested($audit)) return;
        $organization = Organization::with('plan')->findOrFail($audit->organization_id);
        $repairLimit = SaasAccess::limit('quality_repairs_monthly', $organization);
        $repairSlotsRemaining = $repairLimit === null ? PHP_INT_MAX : max(0, $repairLimit - SaasAccess::usage('quality_repairs_monthly', $organization));
        $repairCapacityReported = false;
        if (! SaasAccess::featureEnabled('exam_quality_audit', $organization)) throw new \RuntimeException('Exam Quality Audit is no longer included in this organization plan.');
        if ($audit->include_source && ! SaasAccess::featureEnabled('exam_quality_source', $organization)) throw new \RuntimeException('Source comparison is not included in this organization plan.');
        if (data_get($audit->options, 'include_image_audit') && ! SaasAccess::featureEnabled('exam_quality_source', $organization)) throw new \RuntimeException('Image source audit is not included in this organization plan.');
        if ($audit->include_visual && ! SaasAccess::featureEnabled('exam_quality_visual', $organization)) throw new \RuntimeException('Browser visual audit is not included in this organization plan.');
        if ($audit->include_ai && ! SaasAccess::featureEnabled('exam_quality_ai', $organization)) throw new \RuntimeException('AI academic audit is not included in this organization plan.');
        $audit->loadMissing('exam');
        $started = ExamQualityAudit::whereKey($audit->id)
            ->whereNotIn('status', ['stop_requested','cancelled'])
            ->update([
                'status' => 'running', 'started_at' => now(), 'completed_at' => null, 'failure_message' => null,
                'total_questions' => 0, 'checked_questions' => 0, 'passed_questions' => 0,
                'warning_count' => 0, 'error_count' => 0,
            ]);
        if (! $started) {
            $this->cancelIfRequested($audit);
            return;
        }
        $audit->refresh();
        $audit->findings()->delete();

        try {
            $query = $audit->exam->questions()->with(['qtype', 'subject', 'topic', 'stopic', 'passage'])
                ->orderBy('exam_questions.id');
            $sampleOrdinals = $this->sampleOrdinals((string) data_get($audit->options, 'sample_questions', ''));
            if (data_get($audit->options, 'sample_mode') && $sampleOrdinals !== []) {
                $paperQuestionIds = \Illuminate\Support\Facades\DB::table('exam_questions')
                    ->where('exam_id', $audit->exam_id)->orderBy('id')->pluck('question_id')->values();
                $selectedIds = collect($sampleOrdinals)->map(fn ($ordinal) => $paperQuestionIds->get($ordinal - 1))
                    ->filter()->values();
                $query->whereIn('questions.id', $selectedIds);
            }
            if ($audit->question_limit) $query->limit($audit->question_limit);
            $questions = $query->get();
            if ($questions->isEmpty()) {
                throw new \RuntimeException('This exam has no linked questions. Review and explicitly publish its source-exam draft before running an audit.');
            }
            $audit->update(['total_questions' => $questions->count()]);

            $ruleEngine = app(ExamQualityRuleEngine::class);
            $aiReviewer = app(ExamQualityAiReviewer::class);
            $aiLimit = min((int) data_get($audit->options, 'ai_limit', 20), 100);
            $aiReviewed = 0;
            $aiFailureReported = false;
            $sourceReviewer = app(ExamQualitySourceReviewer::class);
            $imageReviewer = app(ExamQualityImageReviewer::class);
            $imageLimit = min((int) data_get($audit->options, 'image_limit', $questions->count()), 1000);
            $imageEnabled = (bool) data_get($audit->options, 'include_image_audit', false);
            $sourceLimit = min((int) data_get($audit->options, 'source_limit', $questions->count()), 1000);
            $sourceReviewed = 0;
            $sourceFailureReported = false;
            $allowedProviders = ['auto', 'claude', 'chatgpt', 'gemini', 'deepseek'];
            $legacyProvider = (string) data_get($audit->options, 'ai_provider', 'auto');
            $sourceProvider = (string) data_get($audit->options, 'source_ai_provider', $legacyProvider);
            $academicProvider = (string) data_get($audit->options, 'academic_ai_provider', $legacyProvider);
            $imageProvider = (string) data_get($audit->options, 'image_ai_provider', 'auto');
            if (! in_array($sourceProvider, $allowedProviders, true)) $sourceProvider = 'auto';
            if (! in_array($academicProvider, $allowedProviders, true)) $academicProvider = 'auto';
            if (! in_array($imageProvider, $allowedProviders, true)) $imageProvider = 'auto';
            $settings = Configuration::where('organization_id', $audit->organization_id)->first();
            if ($audit->include_source && $sourceProvider === 'auto') {
                $provider = AiProvider::firstAvailable($settings, false, 'source_text_audit');
                if (! $provider) throw new \RuntimeException('No compatible Source Text Audit provider is configured in Admin AI Settings.');
                $sourceProvider = (string) $provider['provider'];
            }
            if ($audit->include_ai && $academicProvider === 'auto') {
                $provider = AiProvider::firstAvailable($settings, false, 'academic_review');
                if (! $provider) throw new \RuntimeException('No Academic Review provider is configured in Admin AI Settings.');
                $academicProvider = (string) $provider['provider'];
            }
            if ($imageEnabled && $imageProvider === 'auto') {
                $provider = AiProvider::firstAvailable($settings, true, 'image_audit');
                if (! $provider) throw new \RuntimeException('No compatible Image Audit provider is configured in Admin AI Settings.');
                $imageProvider = (string) $provider['provider'];
            }
            if (($audit->include_source && data_get($audit->options, 'source_ai_provider') !== $sourceProvider)
                || ($audit->include_ai && data_get($audit->options, 'academic_ai_provider') !== $academicProvider)
                || ($imageEnabled && data_get($audit->options, 'image_ai_provider') !== $imageProvider)) {
                $options = (array) $audit->options;
                if ($audit->include_source) $options['source_ai_provider'] = $sourceProvider;
                if ($audit->include_ai) $options['academic_ai_provider'] = $academicProvider;
                if ($imageEnabled) $options['image_ai_provider'] = $imageProvider;
                $options['provider_locked_at'] = now()->toIso8601String();
                $audit->update(['options' => $options]);
            }

            $sourceProfile = (array) data_get($audit->options, 'source_profile', []);
            $sourceChunks = $questions->take($sourceLimit)->chunk(20)->values();
            $sourceBatchFindings = [];
            $sourceBatchFailure = null;
            $loadedSourceChunk = null;
            $cacheSourceDocuments = $questions->take($sourceLimit)->count() > 20;
            $combinedReview = $audit->include_source && $audit->include_ai && $sourceProvider === $academicProvider
                && (bool) data_get($audit->options, 'combined_ai_request', true);
            $combinedAcademicQuestionIds = $combinedReview
                ? $questions->take(min($sourceLimit, $aiLimit))->pluck('id')->map(fn ($id) => (int) $id)->all()
                : [];
            $sourceBatchOutcomes = [];
            $imageOutcomes = [];
            if ($imageEnabled) {
                if ($this->cancelIfRequested($audit)) return;
                try {
                    // One vision request inspects the paper and returns every crop.
                    // Cropping and draft creation remain local and require no second AI call.
                    $imageOutcomes = $imageReviewer->reviewBatch(
                        $questions->take($imageLimit), $audit->exam_id, $audit->organization_id, $imageProvider
                    );
                    $imageDiagnostics = (array) ($imageOutcomes['_diagnostics'] ?? []);
                    unset($imageOutcomes['_diagnostics']);
                    if ($imageDiagnostics !== []) {
                        $this->saveFinding($audit, (int) $questions->first()->id, 'system', [
                            'type' => 'image_audit_response_rejected', 'severity' => 'warning',
                            'title' => 'Some image instructions were rejected',
                            'details' => collect($imageDiagnostics)->map(fn (array $item) =>
                                'Item '.($item['item_number'] ?? '?').': '.($item['reason'] ?? 'Invalid image instruction.')
                            )->implode(' '),
                            'evidence' => ['provider' => $imageProvider, 'diagnostics' => $imageDiagnostics],
                        ]);
                    }
                } catch (\Throwable $exception) {
                    $this->saveFinding($audit, (int) $questions->first()->id, 'system', [
                        'type' => 'image_audit_unavailable', 'severity' => 'warning',
                        'title' => 'Image audit could not run',
                        'details' => $exception->getMessage(),
                        'evidence' => ['provider' => $imageProvider],
                    ]);
                }
            }


            foreach ($questions as $question) {
                if ($this->cancelIfRequested($audit)) return;
                $questionFindings = $ruleEngine->inspect($question);
                if ($audit->include_source) {
                    $sourceIrrelevantRules = [
                        'invalid_true_false_answer','insufficient_options','missing_correct_option','duplicate_options','correct_option_not_visible',
                        'missing_fill_blank_answer','invalid_nat_range','reversed_nat_range','missing_nat_answer','invalid_nat_tolerance',
                        'missing_subjective_reference','invalid_marks','negative_marks_below_zero',
                    ];
                    $questionFindings = array_values(array_filter($questionFindings, fn ($finding) => ! in_array($finding['type'] ?? '', $sourceIrrelevantRules, true)));
                }
                foreach ($questionFindings as $finding) $this->saveFinding($audit, $question->id, 'rules', $finding);
                if ($audit->include_source && $sourceReviewed < $sourceLimit) {
                    try {
                        // Load only the batch containing this question. Results from
                        // each batch are persisted before the next paid API call starts.
                        $chunkIndex = intdiv($sourceReviewed, 20);
                        if ($loadedSourceChunk !== $chunkIndex) {
                            $sourceBatchFindings = [];
                            $sourceBatchFailure = null;
                            $sourceChunk = $sourceChunks->get($chunkIndex, collect());
                            try {
                                $academicIds = $combinedReview
                                    ? $sourceChunk->pluck('id')->map(fn ($id) => (int) $id)->intersect($combinedAcademicQuestionIds)->values()->all()
                                    : [];
                                $sourceBatchOutcomes = $sourceReviewer->reviewAndRepairBatch(
                                    $sourceChunk->values(), $audit->exam_id, $audit->organization_id,
                                    $sourceProfile, $sourceProvider, $academicIds, $cacheSourceDocuments
                                );
                                $sourceBatchFindings = collect($sourceBatchOutcomes)
                                    ->map(fn ($outcome) => (array) ($outcome['source_findings'] ?? []))->all();
                            } catch (\Throwable $exception) {
                                $sourceBatchFailure = $exception->getMessage();
                            }
                            $loadedSourceChunk = $chunkIndex;
                        }

                        // Build immutable source evidence before asking AI to compare content.
                        $profile = $sourceProfile;
                        $manifest = app(SourceQuestionManifestService::class)->forQuestion(
                            $question, $audit->exam_id, $audit->organization_id, $profile
                        );
                        foreach (app(SourceQuestionManifestService::class)->compareStored($question, $manifest) as $finding) {
                            $this->saveFinding($audit, $question->id, 'source_manifest', $finding);
                        }
                        if ($sourceBatchFailure !== null) throw new \RuntimeException($sourceBatchFailure);
                        foreach ((array) ($sourceBatchFindings[$question->id] ?? []) as $finding) {
                            $this->saveFinding($audit, $question->id, 'source_compare', $finding);
                        }
                        $sourceReviewed++;
                    } catch (\Throwable $e) {
                        if (! $sourceFailureReported) {
                            $this->saveFinding($audit, $question->id, 'system', [
                                'type' => 'source_compare_unavailable', 'severity' => 'warning',
                                'title' => 'Source comparison could not run', 'details' => $e->getMessage(),
                                'evidence' => [],
                            ]);
                            $sourceFailureReported = true;
                        }
                        $sourceReviewed = $sourceLimit;
                    }
                } elseif ($audit->include_source) {
                    $this->saveFinding($audit, $question->id, 'system', [
                        'type' => 'source_comparison_not_run', 'severity' => 'warning',
                        'title' => 'Source comparison was not run',
                        'details' => 'This question was outside the configured source-comparison limit and is not counted as passed.',
                        'evidence' => ['source_limit' => $sourceLimit],
                    ]);
                }

                if ($this->cancelIfRequested($audit)) return;
                if ($audit->include_ai && in_array((int) $question->id, $combinedAcademicQuestionIds, true)) {
                    if ($sourceBatchFailure === null) {
                        foreach ((array) data_get($sourceBatchOutcomes, $question->id.'.academic_findings', []) as $finding) {
                            $this->saveFinding($audit, $question->id, 'ai_content', $finding);
                        }
                    }
                    $aiReviewed++;
                } elseif ($audit->include_ai && $aiReviewed < $aiLimit) {
                    try {
                        foreach ($aiReviewer->review($question, $audit->organization_id, $academicProvider) as $finding) {
                            $this->saveFinding($audit, $question->id, 'ai_content', $finding);
                        }
                        $aiReviewed++;
                    } catch (\Throwable $e) {
                        if (! $aiFailureReported) {
                            $this->saveFinding($audit, $question->id, 'system', [
                                'type' => 'ai_unavailable', 'severity' => 'warning',
                                'title' => 'AI review could not run', 'details' => $e->getMessage(),
                                'evidence' => [],
                            ]);
                            $aiFailureReported = true;
                        }
                        $aiReviewed = $aiLimit;
                    }
                } elseif ($audit->include_ai) {
                    $this->saveFinding($audit, $question->id, 'system', [
                        'type' => 'academic_review_not_run', 'severity' => 'warning',
                        'title' => 'Academic review was not run',
                        'details' => 'This question was outside the configured academic-review limit and is not counted as passed.',
                        'evidence' => ['ai_limit' => $aiLimit],
                    ]);
                }

                $outcome = (array) ($sourceBatchOutcomes[$question->id] ?? []);
                $proposal = (array) ($outcome['proposal'] ?? []);
                $imageOutcome = (array) ($imageOutcomes[$question->id] ?? []);
                if (! empty($imageOutcome['finding'])) {
                    $this->saveFinding($audit, $question->id, 'image_compare', (array) $imageOutcome['finding']);
                }
                if (! empty($imageOutcome['proposal'])) {
                    $imageProposal = (array) $imageOutcome['proposal'];
                    $proposal = $proposal === [] ? $imageProposal : array_replace($proposal, [
                        'image_instruction' => $imageProposal['image_instruction'] ?? null,
                        'image_instructions' => $imageProposal['image_instructions'] ?? [],
                        'remove_stored_images' => (bool) ($imageProposal['remove_stored_images'] ?? false),
                        'image_provider' => $imageProposal['provider'] ?? null,
                        'image_model' => $imageProposal['model'] ?? null,
                    ]);
                }
                $this->prepareAutomaticDraft(
                    $audit, $question, $proposal, $organization,
                    $repairLimit, $repairSlotsRemaining, $repairCapacityReported
                );
                $audit->increment('checked_questions');
            }

            if ($this->cancelIfRequested($audit)) return;
            if ($audit->include_visual) {
                $this->runBrowserChecks($audit, $questions);
                // Browser-only findings are created after the main question loop.
                // Prepare their drafts now without making another AI request.
                foreach ($questions as $question) {
                    $this->prepareAutomaticDraft(
                        $audit, $question, [], $organization,
                        $repairLimit, $repairSlotsRemaining, $repairCapacityReported
                    );
                }
            }
            $this->complete($audit);
        } catch (\Throwable $e) {
            $audit->update(['status' => 'failed', 'failure_message' => mb_substr($e->getMessage(), 0, 6000), 'completed_at' => now()]);
            throw $e;
        }
    }

    private function prepareAutomaticDraft(
        ExamQualityAudit $audit,
        $question,
        array $proposal,
        Organization $organization,
        ?int $repairLimit,
        int &$repairSlotsRemaining,
        bool &$repairCapacityReported
    ): void {
        if (! SaasAccess::featureEnabled('exam_quality_ai', $organization)) return;
        $hasOpenFindings = ExamQualityFinding::where('audit_id', $audit->id)
            ->where('question_id', $question->id)->where('status', 'open')->exists();
        $hasProposalWork = $proposal !== [] && (! empty($proposal['proposed_fields'])
            || ! empty($proposal['conflicts']) || ! empty($proposal['image_instruction'])
            || ! empty($proposal['image_instructions']) || ! empty($proposal['remove_stored_images']));
        if (! $hasOpenFindings && ! $hasProposalWork) return;

        $draft = \App\Models\QuestionRepairDraft::firstOrNew([
            'audit_id' => $audit->id, 'question_id' => $question->id,
        ]);
        if ($draft->exists) return;
        if ($repairSlotsRemaining < 1) {
            if (! $repairCapacityReported) {
                $this->saveFinding($audit, $question->id, 'system', [
                    'type' => 'repair_draft_limit_reached', 'severity' => 'warning',
                    'title' => 'Repair draft limit reached',
                    'details' => 'The review completed, but the monthly repair-draft limit prevented additional automatic drafts.',
                    'evidence' => ['limit' => $repairLimit],
                ]);
                $repairCapacityReported = true;
            }
            return;
        }

        $draft->fill([
            'organization_id' => $audit->organization_id, 'exam_id' => $audit->exam_id,
            'status' => 'starting', 'original_payload' => app(QuestionRepairService::class)->snapshot($question),
            'created_by' => $audit->requested_by, 'failure_message' => null,
        ])->save();
        if ($repairSlotsRemaining !== PHP_INT_MAX) $repairSlotsRemaining--;
        try {
            // Passing an empty array deliberately avoids a second provider call.
            // Deterministic source-image extraction and manual-safe draft setup still run.
            app(QuestionRepairService::class)->process($draft->fresh(), $proposal);
            $draft->refresh();
            $imageStatus = (string) data_get($draft->evidence, 'extracted_image.status', '');
            $resolvedImageStatuses = ['canonical_source_visuals_applied','source_visuals_synchronized','source_visual_synchronized','matched_existing','replaced_mismatched','extracted_missing','manually_cropped'];
            if ($imageStatus !== '' && ! in_array($imageStatus, $resolvedImageStatuses, true)) {
                $this->saveFinding($audit, $question->id, 'system', [
                    'type' => 'image_extraction_failed', 'severity' => 'error',
                    'title' => 'Automatic image extraction needs attention',
                    'details' => (string) data_get($draft->evidence, 'extracted_image.message',
                        'The image reviewer found work, but the local crop could not be saved.'),
                    'evidence' => ['draft_id' => $draft->id, 'status' => $imageStatus,
                        'image_instruction' => data_get($draft->evidence, 'image_instruction'),
                        'image_instructions' => data_get($draft->evidence, 'image_instructions', [])],
                ]);
            }
        } catch (\Throwable $exception) {
            $this->saveFinding($audit, $question->id, 'system', [
                'type' => 'repair_draft_unavailable', 'severity' => 'warning',
                'title' => 'Repair draft could not be prepared',
                'details' => $exception->getMessage().' The review findings were preserved and the live question was not changed.',
                'evidence' => ['draft_id' => $draft->id],
            ]);
        }
    }

    private function sampleOrdinals(string $selection): array
    {
        $ordinals = [];
        foreach (array_filter(array_map('trim', explode(',', $selection))) as $part) {
            if (preg_match('/^(\d+)\s*-\s*(\d+)$/', $part, $match)) {
                $start = max(1, (int) $match[1]);
                $end = max(1, (int) $match[2]);
                if ($start > $end) [$start, $end] = [$end, $start];
                foreach (range($start, min($end, $start + 499)) as $value) $ordinals[$value] = true;
            } elseif (ctype_digit($part) && (int) $part > 0) {
                $ordinals[(int) $part] = true;
            }
            if (count($ordinals) >= 500) break;
        }
        $values = array_keys($ordinals);
        sort($values);
        return array_slice($values, 0, 500);
    }

    private function runBrowserChecks(ExamQualityAudit $audit, $questions): void
    {
        $directory = 'exam-quality/'.$audit->id;
        $manifestPath = null;
        $outputPath = null;
        try {
            if (! Storage::disk('public')->makeDirectory($directory) && ! Storage::disk('public')->directoryExists($directory)) {
                throw new \RuntimeException('The visual-audit screenshot directory is not writable.');
            }
            $visualLimit = min((int) data_get($audit->options, 'visual_limit', 30), 100);
            $items = $questions->take($visualLimit)->map(function ($question) use ($audit, $directory) {
                return [
                    'question_id' => $question->id,
                    'url' => $this->tenantUrl($audit, URL::temporarySignedRoute('exam-quality.question-preview', now()->addHours(2), [
                        'audit' => $audit->public_token, 'question' => $question->id,
                    ], false)),
                    'screenshot' => public_path('storage/'.$directory.'/question-'.$question->id.'.png'),
                ];
            })->values()->all();

            if ($items === []) return;
            $manifestPath = storage_path('app/exam-quality-'.$audit->id.'-'.Str::random(8).'.json');
            $outputPath = storage_path('app/exam-quality-'.$audit->id.'-output-'.Str::random(8).'.json');
            if (@file_put_contents($manifestPath, json_encode(['items' => $items, 'output' => $outputPath], JSON_UNESCAPED_SLASHES)) === false) {
                throw new \RuntimeException('The visual-audit temporary directory is not writable.');
            }
            $process = new Process(['node', base_path('scripts/exam-quality-browser.mjs'), $manifestPath], base_path(), null, null, 600);
            app(ResourceSlotLimiter::class)->run(
                'paper-heavy',
                config('paper_processing.max_parallel_heavy', 2),
                static function () use ($process): void {
                    $process->run();
                },
                900,
                900
            );
            if (! $process->isSuccessful() || ! is_file($outputPath)) {
                throw new \RuntimeException(trim($process->getErrorOutput() ?: $process->getOutput() ?: 'Playwright browser check failed.'));
            }
            $results = json_decode((string) file_get_contents($outputPath), true);
            foreach ((array) ($results['items'] ?? []) as $result) {
                foreach ((array) ($result['findings'] ?? []) as $finding) {
                    $finding['evidence'] = array_merge((array) ($finding['evidence'] ?? []), ['viewport' => $result['viewport'] ?? null]);
                    $finding['screenshot_path'] = $directory.'/question-'.((int) $result['question_id']).'.png';
                    $this->saveFinding($audit, (int) $result['question_id'], 'browser', $finding);
                }
            }
        } catch (\Throwable $e) {
            $this->saveFinding($audit, null, 'system', [
                'type' => 'browser_unavailable', 'severity' => 'warning',
                'title' => 'Browser visual check could not run',
                'details' => 'Install Playwright Chromium on this server, then rerun the audit. '.$e->getMessage(),
                'evidence' => [],
            ]);
        } finally {
            if ($manifestPath) @unlink($manifestPath);
            if ($outputPath) @unlink($outputPath);
        }
    }

    private function tenantUrl(ExamQualityAudit $audit, string $relativeUrl): string
    {
        $requestedBaseUrl = trim((string) data_get($audit->options, 'base_url', ''));
        if ($requestedBaseUrl !== '') return rtrim($requestedBaseUrl, '/').'/'.ltrim($relativeUrl, '/');
        $audit->loadMissing('organization');
        $domain = trim((string) ($audit->organization?->domain ?? ''));
        if ($domain === '') return url($relativeUrl);
        if (! str_starts_with($domain, 'http://') && ! str_starts_with($domain, 'https://')) $domain = 'https://'.$domain;
        return rtrim($domain, '/').'/'.ltrim($relativeUrl, '/');
    }
    private function saveFinding(ExamQualityAudit $audit, ?int $questionId, string $source, array $finding): void
    {
        ExamQualityFinding::create([
            'organization_id' => $audit->organization_id,
            'audit_id' => $audit->id,
            'exam_id' => $audit->exam_id,
            'question_id' => $questionId,
            'source' => $source,
            'issue_type' => $finding['type'] ?? 'unknown',
            'severity' => $finding['severity'] ?? 'warning',
            'status' => 'open',
            'title' => $finding['title'] ?? 'Quality issue',
            'details' => $finding['details'] ?? null,
            'confidence' => $finding['confidence'] ?? null,
            'evidence' => $finding['evidence'] ?? null,
            'screenshot_path' => $finding['screenshot_path'] ?? null,
        ]);
    }

    private function cancelIfRequested(ExamQualityAudit $audit): bool
    {
        $status = (string) ExamQualityAudit::whereKey($audit->id)->value('status');
        if (! in_array($status, ['stop_requested','cancelled'], true)) return false;
        $audit->refresh();
        $warningCount = $audit->findings()->whereIn('severity', ['info','warning'])->count();
        $errorCount = $audit->findings()->whereIn('severity', ['error','critical'])->count();
        $questionsWithFindings = $audit->findings()->whereNotNull('question_id')->distinct()->count('question_id');
        $audit->update([
            'status' => 'cancelled',
            'passed_questions' => max(0, $audit->checked_questions - $questionsWithFindings),
            'warning_count' => $warningCount, 'error_count' => $errorCount,
            'completed_at' => $audit->completed_at ?: now(), 'failure_message' => null,
        ]);
        return true;
    }

    private function complete(ExamQualityAudit $audit): void
    {
        $audit->refresh();
        $warningCount = $audit->findings()->whereIn('severity', ['info', 'warning'])->count();
        $errorCount = $audit->findings()->whereIn('severity', ['error', 'critical'])->count();
        $questionsWithFindings = $audit->findings()->whereNotNull('question_id')->distinct()->count('question_id');
        $audit->update([
            'status' => 'completed',
            'passed_questions' => max(0, $audit->total_questions - $questionsWithFindings),
            'warning_count' => $warningCount,
            'error_count' => $errorCount,
            'completed_at' => now(),
        ]);
    }
}

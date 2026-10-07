<?php

namespace App\Services;

use App\Models\{OfficialExamDiscovery, OfficialExamSource, OfficialExamSourceRule, OfficialExamSourceRun};
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class OfficialExamMonitorService
{
    public function __construct(
        private OfficialExamSourceAcquisitionService $acquisition,
        private OfficialExamCreationService $creator,
    ) {}

    public function check(OfficialExamSource $source): OfficialExamSourceRun
    {
        $lock = Cache::lock('official-exam-source:'.$source->id, 900);
        if (! $lock->get()) throw new \RuntimeException('This official source is already being checked.');
        $run = OfficialExamSourceRun::create(['official_exam_source_id' => $source->id, 'status' => 'running', 'started_at' => now()]);
        $counts = ['found_count' => 0, 'created_count' => 0, 'skipped_count' => 0, 'review_count' => 0];
        $diagnostics = [];
        try {
            $source->load('rules.language');
            $rows = $this->acquisition->scan($source);
            $counts['found_count'] = count($rows);
            foreach ($rows as $position => $row) {
                try { $this->handleRow($source, $run, $row, $counts); }
                catch (\Throwable $exception) {
                    $counts['review_count']++;
                    $diagnostics[] = ['row' => $position + 1, 'exam' => $row['exam_name'] ?? null, 'error' => $exception->getMessage()];
                } finally { $this->acquisition->cleanup($row); }
            }
            $run->update(array_merge($counts, ['status' => $diagnostics ? 'completed_with_warnings' : 'completed', 'diagnostics' => $diagnostics ?: null, 'completed_at' => now()]));
            $source->update(['last_checked_at' => now(), 'last_success_at' => now(), 'next_check_at' => now()->addMinutes($source->check_interval_minutes), 'last_error' => $diagnostics ? implode(' | ', array_column($diagnostics, 'error')) : null]);
        } catch (\Throwable $exception) {
            $run->update(array_merge($counts, ['status' => 'failed', 'failure_message' => $exception->getMessage(), 'diagnostics' => $diagnostics ?: null, 'completed_at' => now()]));
            $source->update(['last_checked_at' => now(), 'next_check_at' => now()->addMinutes($source->check_interval_minutes), 'last_error' => $exception->getMessage()]);
        } finally { $lock->release(); }
        return $run->fresh();
    }

    private function handleRow(OfficialExamSource $source, OfficialExamSourceRun $run, array $row, array &$counts): void
    {
        $rule = $this->matchRule($source, $row);
        $examName = $this->examName($source, $rule, $row);
        $key = $this->externalKey($source, $rule, $row, $examName);
        $now = now();
        $discovery = OfficialExamDiscovery::firstOrCreate(
            ['official_exam_source_id' => $source->id, 'external_exam_key' => $key],
            [
                'organization_id' => $source->organization_id, 'official_exam_source_rule_id' => $rule?->id,
                'official_exam_source_run_id' => $run->id, 'content_hash' => $row['content_hash'] ?? null,
                'observed_content_hash' => $row['content_hash'] ?? null, 'status' => $rule ? 'detected' : 'needs_configuration',
                'exam_name' => $examName, 'question_url' => ($row['question_url'] ?? '') ?: null,
                'answer_url' => ($row['answer_url'] ?? '') ?: null, 'combined_url' => ($row['combined_url'] ?? '') ?: null,
                'archive_url' => $row['archive_url'] ?? null, 'document_hashes' => $row['document_hashes'] ?? null,
                'metadata' => $this->metadata($row), 'source_config_version' => $source->config_version,
                'rule_version' => $rule?->version, 'first_seen_at' => $now, 'last_seen_at' => $now,
            ]
        );

        if (! $discovery->wasRecentlyCreated) {
            $discovery->update(['official_exam_source_run_id' => $run->id, 'last_seen_at' => $now, 'observed_content_hash' => $row['content_hash'] ?? null]);
            if ($discovery->exam_id || $discovery->source_exam_import_id) {
                if ($discovery->content_hash !== ($row['content_hash'] ?? null)) {
                    $discovery->update(['status' => 'revised', 'revised_at' => $now, 'failure_message' => 'The official source changed after this exam was created. The existing exam was not modified.']);
                    $counts['review_count']++;
                } else $counts['skipped_count']++;
                return;
            }
            $discovery->update([
                'content_hash' => $row['content_hash'] ?? null, 'document_hashes' => $row['document_hashes'] ?? null,
                'question_url' => ($row['question_url'] ?? '') ?: null, 'answer_url' => ($row['answer_url'] ?? '') ?: null,
                'combined_url' => ($row['combined_url'] ?? '') ?: null, 'archive_url' => $row['archive_url'] ?? null,
                'metadata' => $this->metadata($row), 'source_config_version' => $source->config_version,
                'failure_message' => null,
            ]);
        }

        if (! $rule) {
            $discovery->update(['status' => 'needs_configuration', 'failure_message' => 'No enabled paper rule matched this PDF set.']);
            $counts['review_count']++;
            return;
        }
        $discovery->update(['official_exam_source_rule_id' => $rule->id, 'rule_version' => $rule->version, 'exam_name' => $examName]);
        $hasQuestion = ! empty($row['question_local']) || ! empty($row['combined_local']);
        $hasAnswer = ! empty($row['answer_local']) || ! empty($row['combined_local']);
        if (! $hasQuestion || ($rule->ready_policy === 'question_and_answer' && ! $hasAnswer)) {
            $discovery->update(['status' => 'awaiting_companion', 'failure_message' => $hasQuestion ? 'Waiting for the configured answer/solution PDF.' : 'Waiting for a question PDF.']);
            $counts['review_count']++;
            return;
        }
        try {
            $this->creator->create($source, $rule, $discovery, $row);
            $counts['created_count']++;
        } catch (\Throwable $exception) {
            $discovery->update(['status' => 'failed', 'failure_message' => $exception->getMessage()]);
            throw $exception;
        }
    }

    private function matchRule(OfficialExamSource $source, array $row): ?OfficialExamSourceRule
    {
        $candidate = implode(' ', array_filter([$row['exam_name'] ?? '', $row['question_label'] ?? '', $row['answer_label'] ?? '', $row['combined_label'] ?? '', $row['question_url'] ?? '', $row['answer_url'] ?? '', $row['combined_url'] ?? '']));
        foreach ($source->rules as $rule) {
            if (! $rule->enabled) continue;
            $pattern = trim((string) $rule->match_pattern);
            if ($pattern === '') return $rule;
            if ($pattern[0] === '/' && strrpos($pattern, '/') > 0) {
                $matched = @preg_match($pattern, $candidate);
                if ($matched === false) throw new \RuntimeException("Paper rule {$rule->name} has an invalid regular expression.");
                if ($matched === 1) return $rule;
            } elseif (str_contains(Str::lower(Str::ascii($candidate)), Str::lower(Str::ascii($pattern)))) return $rule;
        }
        return null;
    }

    private function examName(OfficialExamSource $source, ?OfficialExamSourceRule $rule, array $row): string
    {
        $template = $rule?->exam_name_template ?: '{detected_name}';
        $values = [
            '{detected_name}' => trim((string) ($row['exam_name'] ?? '')) ?: $source->name,
            '{year}' => (string) ($row['year'] ?? ''), '{website}' => $source->website_name,
            '{rule}' => $rule?->name ?? '', '{language}' => $rule?->language?->name ?? $rule?->language_mode ?? '',
        ];
        return Str::limit(trim(preg_replace('/\s+/', ' ', strtr($template, $values))), 255, '');
    }

    private function externalKey(OfficialExamSource $source, ?OfficialExamSourceRule $rule, array $row, string $examName): string
    {
        $template = trim((string) data_get($rule?->settings, 'external_key_template', ''));
        $identity = $template !== '' ? strtr($template, ['{detected_name}' => $row['exam_name'] ?? '', '{exam_name}' => $examName, '{year}' => $row['year'] ?? '', '{language_mode}' => $rule?->language_mode ?? 'unknown']) : implode('|', [$examName, $row['year'] ?? '', $rule?->language_mode ?? 'unknown']);
        return hash('sha256', Str::lower(Str::ascii(trim(preg_replace('/\s+/', ' ', $identity)))));
    }

    private function metadata(array $row): array
    {
        return array_filter(['year' => $row['year'] ?? null, 'question_label' => $row['question_label'] ?? null, 'answer_label' => $row['answer_label'] ?? null, 'combined_label' => $row['combined_label'] ?? null]);
    }
}

<?php

namespace App\Console\Commands;

use App\Services\MathContentNormalizer;
use Illuminate\Console\Command;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class NormalizeQuestionContent extends Command
{
    protected $signature = 'questions:normalize-content
        {--organization= : Required tenant organization ID}
        {--apply : Write validated changes and create restorable backups}
        {--restore= : Restore one previously applied run ID}
        {--chunk=250 : Records processed per database batch (10-1000)}
        {--from-id=0 : Start after this question ID}
        {--limit=0 : Maximum primary questions to inspect; zero means all}
        {--question=* : Limit to specific question IDs and their related translations/passages}';

    protected $description = 'Dry-run or safely normalize tenant question HTML and MathML into canonical MathJax source';

    private const TABLE_FIELDS = [
        'questions' => [
            'question', 'option1', 'option2', 'option3', 'option4', 'option5', 'option6',
            'hint', 'explanation', 'fill_blank', 'si_answer1',
        ],
        'question_langs' => [
            'question', 'option1', 'option2', 'option3', 'option4', 'option5', 'option6',
            'hint', 'explanation', 'fill_blank', 'si_answer1',
        ],
        'passage_langs' => ['passage'],
    ];

    private array $stats = [
        'records' => 0,
        'clean' => 0,
        'converted' => 0,
        'sanitized' => 0,
        'needs_review' => 0,
        'changed_records' => 0,
        'applied_records' => 0,
        'restored_records' => 0,
        'skipped_concurrent' => 0,
        'math_expressions' => 0,
    ];

    public function handle(MathContentNormalizer $normalizer): int
    {
        $organizationId = filter_var($this->option('organization'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if (! $organizationId || ! DB::table('organizations')->where('id', $organizationId)->exists()) {
            $this->error('Provide an existing positive --organization ID. No unscoped run is allowed.');
            return self::INVALID;
        }

        $restoreRun = trim((string) $this->option('restore'));
        if ($restoreRun !== '') {
            if ($this->option('apply')) {
                $this->error('--apply and --restore cannot be used together.');
                return self::INVALID;
            }
            return $this->restore($organizationId, $restoreRun);
        }

        $chunk = max(10, min(1000, (int) $this->option('chunk')));
        $limit = max(0, (int) $this->option('limit'));
        $fromId = max(0, (int) $this->option('from-id'));
        $questionIds = collect((array) $this->option('question'))
            ->map(fn ($id) => filter_var($id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]))
            ->filter()->map(fn ($id) => (int) $id)->unique()->values()->all();
        $apply = (bool) $this->option('apply');
        $runId = (string) Str::uuid();
        $reportPath = storage_path('app/content-normalization/'.$runId.'.jsonl');
        File::ensureDirectoryExists(dirname($reportPath));
        $report = fopen($reportPath, 'wb');
        if (! is_resource($report)) {
            throw new RuntimeException('Could not create normalization report: '.$reportPath);
        }

        $this->components->info(($apply ? 'APPLY' : 'DRY RUN').' for organization '.$organizationId.'; run '.$runId);

        try {
            $processedQuestionIds = $this->processQuestions(
                $normalizer, $organizationId, $runId, $apply, $chunk, $fromId, $limit, $questionIds, $report
            );
            $this->processQuestionTranslations(
                $normalizer, $organizationId, $runId, $apply, $chunk, $processedQuestionIds, $report
            );
            $this->processPassageTranslations(
                $normalizer, $organizationId, $runId, $apply, $chunk, $processedQuestionIds, $report
            );
        } finally {
            fclose($report);
        }

        $this->table(['Metric', 'Count'], collect($this->stats)->map(fn ($count, $name) => [$name, $count]));
        $this->line('Report: '.$reportPath);
        if ($apply) {
            $this->info('Applied run ID: '.$runId);
            $this->line('Restore with: php artisan questions:normalize-content --organization='.$organizationId.' --restore='.$runId);
        } else {
            $this->comment('Dry run only: no question content or backup rows were changed.');
        }

        return $this->stats['needs_review'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function processQuestions(
        MathContentNormalizer $normalizer,
        int $organizationId,
        string $runId,
        bool $apply,
        int $chunk,
        int $fromId,
        int $limit,
        array $questionIds,
        $report
    ): array {
        $query = DB::table('questions')
            ->where('organization_id', $organizationId)
            ->where('id', '>', $fromId);
        if ($questionIds !== []) {
            $query->whereIn('id', $questionIds);
        }

        $processed = [];
        $remaining = $limit;
        $this->scanById($query, 'questions', self::TABLE_FIELDS['questions'], $chunk, $remaining, function ($row) use (
            $normalizer, $organizationId, $runId, $apply, $report, &$processed
        ) {
            $processed[] = (int) $row->id;
            $this->processRecord($normalizer, $organizationId, $runId, 'questions', $row, $apply, $report);
        });

        return $processed;
    }

    private function processQuestionTranslations(
        MathContentNormalizer $normalizer,
        int $organizationId,
        string $runId,
        bool $apply,
        int $chunk,
        array $questionIds,
        $report
    ): void {
        if ($questionIds === []) {
            return;
        }
        $query = DB::table('question_langs')
            ->join('questions', 'questions.id', '=', 'question_langs.question_id')
            ->where('questions.organization_id', $organizationId)
            ->whereIn('question_langs.question_id', $questionIds)
            ->select(array_merge(['question_langs.id'], array_map(fn ($field) => 'question_langs.'.$field, self::TABLE_FIELDS['question_langs'])));

        $this->scanById($query, 'question_langs', self::TABLE_FIELDS['question_langs'], $chunk, 0, function ($row) use (
            $normalizer, $organizationId, $runId, $apply, $report
        ) {
            $this->processRecord($normalizer, $organizationId, $runId, 'question_langs', $row, $apply, $report);
        }, 'question_langs.id');
    }

    private function processPassageTranslations(
        MathContentNormalizer $normalizer,
        int $organizationId,
        string $runId,
        bool $apply,
        int $chunk,
        array $questionIds,
        $report
    ): void {
        if ($questionIds === []) {
            return;
        }
        $passageIds = DB::table('questions')
            ->where('organization_id', $organizationId)
            ->whereIn('id', $questionIds)
            ->whereNotNull('passage_id')
            ->pluck('passage_id')->unique()->values()->all();
        if ($passageIds === []) {
            return;
        }

        $query = DB::table('passage_langs')
            ->join('passages', 'passages.id', '=', 'passage_langs.passage_id')
            ->where('passages.organization_id', $organizationId)
            ->whereIn('passage_langs.passage_id', $passageIds)
            ->select(['passage_langs.id', 'passage_langs.passage']);

        $this->scanById($query, 'passage_langs', self::TABLE_FIELDS['passage_langs'], $chunk, 0, function ($row) use (
            $normalizer, $organizationId, $runId, $apply, $report
        ) {
            $this->processRecord($normalizer, $organizationId, $runId, 'passage_langs', $row, $apply, $report);
        }, 'passage_langs.id');
    }

    private function scanById(
        Builder $query,
        string $table,
        array $fields,
        int $chunk,
        int $limit,
        callable $callback,
        string $idColumn = 'id'
    ): void {
        $lastId = 0;
        $processed = 0;
        do {
            $take = $limit > 0 ? min($chunk, $limit - $processed) : $chunk;
            if ($take <= 0) {
                break;
            }
            $rows = (clone $query)
                ->where($idColumn, '>', $lastId)
                ->orderBy($idColumn)
                ->limit($take)
                ->get(array_merge([$idColumn.' as id'], array_map(fn ($field) => $table.'.'.$field, $fields)));
            foreach ($rows as $row) {
                $callback($row);
                $lastId = (int) $row->id;
                $processed++;
            }
        } while ($rows->count() === $take);
    }

    private function processRecord(
        MathContentNormalizer $normalizer,
        int $organizationId,
        string $runId,
        string $table,
        object $row,
        bool $apply,
        $report
    ): void {
        $original = [];
        $normalized = [];
        $fieldResults = [];
        $recordNeedsReview = false;
        $changedFields = [];

        foreach (self::TABLE_FIELDS[$table] as $field) {
            $value = $row->{$field} ?? null;
            $original[$field] = $value;
            $result = $value === null
                ? ['content' => null, 'candidate' => null, 'status' => 'clean', 'changed' => false, 'math_count' => 0, 'issues' => []]
                : $normalizer->normalize($value);
            $fieldResults[$field] = $result;
            $normalized[$field] = $result['content'];
            $this->stats['math_expressions'] += $result['math_count'];
            if ($result['status'] === 'needs_review') {
                $recordNeedsReview = true;
            }
            if ($result['changed']) {
                $changedFields[] = $field;
            }
        }

        $this->stats['records']++;
        if ($recordNeedsReview) {
            $recordStatus = 'needs_review';
        } elseif ($changedFields === []) {
            $recordStatus = 'clean';
        } elseif (collect($fieldResults)->contains(fn ($result) => $result['status'] === 'converted')) {
            $recordStatus = 'converted';
        } else {
            $recordStatus = 'sanitized';
        }
        $this->stats[$recordStatus]++;
        if ($changedFields !== []) {
            $this->stats['changed_records']++;
        }

        $issues = collect($fieldResults)
            ->filter(fn ($result) => $result['issues'] !== [])
            ->map(fn ($result) => $result['issues'])
            ->all();
        fwrite($report, json_encode([
            'organization_id' => $organizationId,
            'record_table' => $table,
            'record_id' => (int) $row->id,
            'status' => $recordStatus,
            'changed_fields' => $changedFields,
            'original_hash' => $this->contentHash($original),
            'proposed_content' => array_intersect_key($normalized, array_flip($changedFields)),
            'review_candidates' => collect($fieldResults)
                ->filter(fn ($result) => $result['candidate'] !== null)
                ->map(fn ($result) => $result['candidate'])
                ->all(),
            'issues' => $issues,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).PHP_EOL);

        if (! $apply || $recordNeedsReview || $changedFields === []) {
            return;
        }

        DB::transaction(function () use ($organizationId, $runId, $table, $row, $original, $normalized, $recordStatus, $issues) {
            $current = $this->scopedRecordQuery($table, (int) $row->id, $organizationId)
                ->lockForUpdate()->first(self::TABLE_FIELDS[$table]);
            if (! $current || ! hash_equals($this->contentHash($original), $this->contentHash((array) $current))) {
                $this->stats['skipped_concurrent']++;
                return;
            }

            DB::table('content_normalization_backups')->insert([
                'run_id' => $runId,
                'organization_id' => $organizationId,
                'record_table' => $table,
                'record_id' => (int) $row->id,
                'original_content' => json_encode($original, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'normalized_content' => json_encode($normalized, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'original_hash' => $this->contentHash($original),
                'status' => $recordStatus,
                'issues' => $issues === [] ? null : json_encode($issues, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $this->scopedRecordQuery($table, (int) $row->id, $organizationId)
                ->update(array_merge($normalized, ['updated_at' => now()]));
            $this->stats['applied_records']++;
        }, 3);
    }

    private function restore(int $organizationId, string $runId): int
    {
        if (! Str::isUuid($runId)) {
            $this->error('The restore run ID must be a valid UUID.');
            return self::INVALID;
        }

        $backups = DB::table('content_normalization_backups')
            ->where('organization_id', $organizationId)
            ->where('run_id', $runId)
            ->where('status', '!=', 'restored')
            ->orderByDesc('id')
            ->get();
        if ($backups->isEmpty()) {
            $this->error('No unrestored backup records were found for this tenant and run ID.');
            return self::FAILURE;
        }

        foreach ($backups as $backup) {
            if (! isset(self::TABLE_FIELDS[$backup->record_table])) {
                $this->warn('Skipped unsupported backup table '.$backup->record_table.' record '.$backup->record_id);
                continue;
            }
            DB::transaction(function () use ($backup, $organizationId) {
                $fields = self::TABLE_FIELDS[$backup->record_table];
                $current = $this->scopedRecordQuery($backup->record_table, (int) $backup->record_id, $organizationId)
                    ->lockForUpdate()->first($fields);
                $normalized = json_decode((string) $backup->normalized_content, true, 512, JSON_THROW_ON_ERROR);
                if (! $current || ! hash_equals($this->contentHash($normalized), $this->contentHash((array) $current))) {
                    $this->stats['skipped_concurrent']++;
                    return;
                }
                $original = json_decode((string) $backup->original_content, true, 512, JSON_THROW_ON_ERROR);
                $this->scopedRecordQuery($backup->record_table, (int) $backup->record_id, $organizationId)
                    ->update(array_merge(array_intersect_key($original, array_flip($fields)), ['updated_at' => now()]));
                DB::table('content_normalization_backups')->where('id', $backup->id)
                    ->update(['status' => 'restored', 'updated_at' => now()]);
                $this->stats['restored_records']++;
            }, 3);
        }

        $this->table(['Metric', 'Count'], [
            ['restored_records', $this->stats['restored_records']],
            ['skipped_concurrent', $this->stats['skipped_concurrent']],
        ]);
        return $this->stats['skipped_concurrent'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function contentHash(array $content): string
    {
        ksort($content);
        return hash('sha256', json_encode($content, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }

    private function scopedRecordQuery(string $table, int $recordId, int $organizationId): Builder
    {
        $query = DB::table($table)->where($table.'.id', $recordId);

        return match ($table) {
            'questions' => $query->where('questions.organization_id', $organizationId),
            'question_langs' => $query->whereExists(fn ($parent) => $parent->selectRaw('1')
                ->from('questions')
                ->whereColumn('questions.id', 'question_langs.question_id')
                ->where('questions.organization_id', $organizationId)),
            'passage_langs' => $query->whereExists(fn ($parent) => $parent->selectRaw('1')
                ->from('passages')
                ->whereColumn('passages.id', 'passage_langs.passage_id')
                ->where('passages.organization_id', $organizationId)),
            default => throw new RuntimeException('Unsupported normalization table: '.$table),
        };
    }}

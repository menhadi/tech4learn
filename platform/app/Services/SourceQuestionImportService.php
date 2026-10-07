<?php

namespace App\Services;

use App\Jobs\DiscoverSourceQuestionUrls;
use App\Jobs\ProcessSourceQuestionImportItem;
use App\Models\Question;
use App\Models\SourceQuestionImportItem;
use App\Models\SourceQuestionImportRun;
use App\Support\Tenant;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class SourceQuestionImportService
{
    public function __construct(private SourceUrlNormalizer $urls) {}

    public function createRun(UploadedFile $file, array $options = []): SourceQuestionImportRun
    {
        $path = $file->storeAs('source-question-imports/'.Tenant::id().'/'.uniqid('pending-', true), $file->getClientOriginalName(), 'local');
        return $this->createFromRows($this->readRows(Storage::disk('local')->path($path)), 'import', $options);
    }

    public function createRunFromUrls(array $urls, array $options = []): SourceQuestionImportRun
    {
        return $this->createFromRows($this->urlRows($urls, $options), 'import', $options);
    }

    public function createAuditRun(UploadedFile $file, array $options = []): SourceQuestionImportRun
    {
        $path = $file->storeAs('source-question-audits/'.Tenant::id().'/'.uniqid('pending-', true), $file->getClientOriginalName(), 'local');
        return $this->createFromRows($this->readRows(Storage::disk('local')->path($path)), 'audit', $options);
    }

    public function createAuditRunFromUrls(array $urls, array $options = []): SourceQuestionImportRun
    {
        return $this->createFromRows($this->urlRows($urls, $options), 'audit', $options);
    }

    public function createCrawlRun(array $options): SourceQuestionImportRun
    {
        $baseUrl = $this->urls->normalize((string) ($options['base_url'] ?? ''));
        $options['crawl'] = [
            'base_url' => $baseUrl,
            'question_pattern' => trim((string) ($options['question_pattern'] ?? '')),
            'max_pages' => (int) ($options['max_pages'] ?? 100),
            'max_questions' => (int) ($options['max_questions'] ?? 1000),
            'pending_urls' => [$baseUrl], 'visited_urls' => [],
            'pages_scanned' => 0, 'pages_failed' => 0, 'question_urls_found' => 0,
            'new_questions' => 0, 'skipped_existing' => 0, 'discovery_complete' => false,
        ];
        $run = SourceQuestionImportRun::create([
            'organization_id' => (int) Tenant::id(), 'created_by' => auth()->id(),
            'status' => 'processing', 'mode' => 'import',
            'adapter' => trim((string) ($options['adapter'] ?? 'auto')) ?: 'auto',
            'options' => $options,
        ]);
        DiscoverSourceQuestionUrls::dispatch($run->id);

        return $run;
    }

    public function appendCrawlUrls(SourceQuestionImportRun $run, array $urls): array
    {
        $options = (array) $run->options;
        $crawl = (array) ($options['crawl'] ?? []);
        $remaining = max(0, (int) ($crawl['max_questions'] ?? 1000) - (int) ($crawl['new_questions'] ?? 0));
        $seen = $run->items()->pluck('source_url_hash')->filter()->flip()->all();
        $rowNumber = max(1, (int) $run->items()->max('row_number')) + 1;
        $created = 0;
        $skippedExisting = 0;
        $dispatch = [];

        foreach ($urls as $url) {
            if ($created >= $remaining) break;
            $url = $this->urls->normalize(trim((string) $url));
            if (! preg_match('~^https?://~i', $url)) continue;
            $hash = $this->urls->hash($url);
            if (isset($seen[$hash])) continue;
            $seen[$hash] = true;
            if ($this->existingQuestion((int) $run->organization_id, $url, $hash) || $this->priorImportedItem((int) $run->organization_id, $url, $hash)) {
                $skippedExisting++;
                continue;
            }
            $metadata = [];
            foreach (['group', 'category', 'subcategory', 'package', 'exam', 'section', 'language'] as $field) {
                $value = trim((string) ($options['default_'.$field] ?? ''));
                if ($value !== '') $metadata[$field] = $value;
            }
            $item = SourceQuestionImportItem::create([
                'run_id' => $run->id, 'row_number' => $rowNumber++, 'source_url' => $url,
                'source_url_hash' => $hash, 'adapter' => $run->adapter ?: 'auto',
                'metadata' => $metadata, 'status' => 'queued',
            ]);
            $dispatch[] = $item->id;
            $created++;
        }
        $run->update(['total' => $run->items()->count(), 'status' => 'processing']);
        foreach ($dispatch as $itemId) ProcessSourceQuestionImportItem::dispatch($itemId);

        return ['created' => $created, 'skipped_existing' => $skippedExisting];
    }

    private function createFromRows(array $rows, string $mode, array $options): SourceQuestionImportRun
    {
        $tenantId = (int) Tenant::id();
        $adapter = trim((string) ($options['adapter'] ?? 'auto')) ?: 'auto';
        $run = SourceQuestionImportRun::create([
            'organization_id' => $tenantId, 'created_by' => auth()->id(), 'status' => 'queued',
            'mode' => $mode, 'adapter' => $adapter, 'options' => $options,
        ]);

        $seen = [];
        $created = 0;
        foreach ($rows as $index => $row) {
            $url = trim((string) ($row['question_source_url'] ?? $row['source_url'] ?? $row['url'] ?? ''));
            if ($url === '' || ! preg_match('~^https?://~i', $url)) continue;
            $hash = $this->urls->hash($url);
            if (isset($seen[$hash])) continue;
            $seen[$hash] = true;
            $metadata = array_filter($row, fn ($value) => trim((string) $value) !== '');
            if (! empty($options['default_group']) && empty($metadata['group'])) $metadata['group'] = $options['default_group'];
            $selectedAdapter = trim((string) ($row['adapter'] ?? $row['source_adapter'] ?? $adapter)) ?: 'auto';
            $question = $this->existingQuestion($tenantId, $url, $hash);
            $prior = $this->priorImportedItem($tenantId, $url, $hash);

            if ($mode === 'audit') {
                $question ??= $prior?->question;
                if ($prior && is_array($prior->metadata)) $metadata = array_replace($prior->metadata, $metadata);
                $selectedAdapter = $prior?->adapter ?: $selectedAdapter;
                $status = $question ? 'queued' : 'audit_missing';
                $error = $question ? null : 'Audit requires an existing imported question with this source URL.';
            } else {
                $status = ($question || $prior) ? 'duplicate' : 'queued';
                $error = $status === 'duplicate' ? 'Source URL was already imported; extraction was skipped to prevent a duplicate.' : null;
                $question ??= $prior?->question;
            }

            $item = SourceQuestionImportItem::create([
                'run_id' => $run->id, 'row_number' => $index + 2, 'source_url' => $url,
                'source_url_hash' => $hash, 'adapter' => $selectedAdapter, 'metadata' => $metadata,
                'status' => $status, 'question_id' => $question?->id, 'error_message' => $error,
            ]);
            $created++;
            if ($status === 'queued') ProcessSourceQuestionImportItem::dispatch($item->id);
        }

        if ($created === 0) {
            $run->delete();
            throw new \RuntimeException('No valid question_source_url values were found in the supplied data.');
        }
        $pending = $run->items()->where('status', 'queued')->exists();
        $run->update([
            'total' => $created, 'duplicates' => $run->items()->where('status', 'duplicate')->count(),
            'failed' => $run->items()->where('status', 'audit_missing')->count(),
            'processed' => $run->items()->whereNotIn('status', ['queued', 'processing'])->count(),
            'status' => $pending ? 'processing' : 'completed',
        ]);
        return $run;
    }

    private function urlRows(array $urls, array $options): array
    {
        return collect($urls)->map(fn ($url) => trim((string) $url))->filter()
            ->map(fn ($url) => ['question_source_url' => $url, 'group' => $options['default_group'] ?? ''])->values()->all();
    }

    private function existingQuestion(int $tenantId, string $url, string $hash): ?Question
    {
        $question = Question::where('organization_id', $tenantId)->where(fn ($query) => $query->where('source_url', $url)->orWhere('source_reference', $url))->first();
        if ($question) return $question;
        $prior = SourceQuestionImportItem::query()->where('source_url_hash', $hash)->whereNotNull('question_id')
            ->whereHas('run', fn ($query) => $query->where('organization_id', $tenantId))->latest('id')->first();
        return $prior?->question;
    }

    private function priorImportedItem(int $tenantId, string $url, string $hash): ?SourceQuestionImportItem
    {
        return SourceQuestionImportItem::query()
            ->where(fn ($query) => $query->where('source_url_hash', $hash)->orWhere('source_url', $url))
            ->whereHas('run', fn ($query) => $query->where('organization_id', $tenantId)->where('mode', 'import'))
            ->whereNotIn('status', ['failed', 'audit_missing'])->latest('id')->first();
    }

    private function readRows(string $path): array
    {
        $handle = fopen($path, 'rb');
        if (! $handle) throw new \RuntimeException('Could not open source import file.');
        $headers = fgetcsv($handle) ?: [];
        $headers = array_map(fn ($value) => strtolower(trim(preg_replace('/^\\xEF\\xBB\\xBF/', '', (string) $value))), $headers);
        $rows = [];
        while (($values = fgetcsv($handle)) !== false) {
            if (count(array_filter($values, fn ($value) => trim((string) $value) !== '')) === 0) continue;
            $row = [];
            foreach ($headers as $index => $header) if ($header !== '') $row[$header] = $values[$index] ?? '';
            $rows[] = $row;
        }
        fclose($handle);
        return $rows;
    }
}

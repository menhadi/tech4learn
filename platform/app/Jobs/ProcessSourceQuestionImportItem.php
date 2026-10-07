<?php

namespace App\Jobs;

use App\Models\{Question, SourceQuestionImportItem};
use App\Services\SourceQuestionAdapterRegistry;
use App\Services\SourceQuestionAssetService;
use App\Services\SourceQuestionAssignmentResolver;
use App\Services\SourceQuestionAuditService;
use App\Services\SourceQuestionPayloadMerger;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessSourceQuestionImportItem implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public function __construct(public int $itemId) {}

    public function handle(
        SourceQuestionAdapterRegistry $registry,
        SourceQuestionAssetService $assets,
        SourceQuestionAuditService $audits,
        SourceQuestionPayloadMerger $merger,
        SourceQuestionAssignmentResolver $assignments,
    ): void
    {
        $item = SourceQuestionImportItem::with(['run', 'question'])->find($this->itemId);
        if (! $item || ($item->run->status === 'failed' && str_contains((string) $item->error_message, 'Stopped by administrator'))) return;
        $mode = $item->run->mode ?: 'import';
        $item->update(['status' => 'processing']);
        try {
            if ($mode === 'audit' && ! $item->question) throw new \RuntimeException('Audit cannot run because no existing question is linked to this source URL.');
            if ($mode === 'import' && ($existing = Question::where('organization_id', $item->run->organization_id)->where('source_url', $item->source_url)->first())) {
                $item->update(['status' => 'duplicate', 'question_id' => $existing->id, 'error_message' => 'Source URL was already imported; extraction was skipped.']);
                $this->recount($item->run);
                return;
            }

            $adapter = $registry->for($item->source_url, $item->adapter ?: $item->run->adapter, (int) $item->run->organization_id);
            $payload = $merger->merge(
                $adapter->extract($item->source_url, $item->metadata ?? []),
                $item->metadata ?? [],
            );
            $payload['source_adapter'] = $adapter->key();
            $payload['adapter_version'] ??= 1;

            if ($mode === 'import') {
                $payload['assets'] = $assets->download($payload['image_urls'] ?? [], $item->metadata ?? [], $item->source_url);
                foreach ($payload['assets'] as $asset) {
                    if (empty($asset['original_url']) || empty($asset['url'])) continue;
                    foreach (['question', 'explanation'] as $field) if (array_key_exists($field, $payload)) $payload[$field] = str_replace($asset['original_url'], $asset['url'], (string) $payload[$field]);
                    $payload['options'] = array_map(fn ($option) => str_replace($asset['original_url'], $asset['url'], (string) $option), (array) ($payload['options'] ?? []));
                }
            }
            $payload['content_hash'] = hash('sha256', trim(strip_tags((string) ($payload['question'] ?? ''))));
            $payload['assignment'] = $assignments->resolve($payload, (int) $item->run->organization_id);

            if ($mode === 'audit') {
                $result = $audits->compare($item->question, $payload);
                $item->update([
                    'adapter' => $adapter->key(), 'status' => $result['status'] === 'clean' ? 'audit_clean' : 'audit_changes',
                    'payload' => $payload, 'audit_result' => $result, 'content_hash' => $payload['content_hash'],
                    'fetched_at' => now(), 'audited_at' => now(), 'error_message' => null,
                ]);
            } else {
                $item->update([
                    'adapter' => $adapter->key(), 'status' => ! empty($payload['needs_review']) ? 'needs_review' : 'ready',
                    'payload' => $payload, 'content_hash' => $payload['content_hash'], 'fetched_at' => now(), 'error_message' => null,
                ]);
            }
            $this->recount($item->run);
        } catch (\Throwable $e) {
            $item->update(['status' => 'failed', 'error_message' => $e->getMessage(), 'fetched_at' => now()]);
            $this->recount($item->run);
        }
    }

    private function recount($run): void
    {
        $items = $run->items();
        $run->refresh();
        $crawl = (array) data_get($run->options, 'crawl', []);
        $discovering = $crawl !== [] && empty($crawl['discovery_complete']);
        $pending = $discovering || (clone $items)->whereIn('status', ['queued', 'processing'])->exists();
        $run->update([
            'total' => (clone $items)->count(), 'processed' => (clone $items)->whereNotIn('status', ['queued', 'processing'])->count(),
            'ready' => (clone $items)->whereIn('status', ['ready', 'audit_clean', 'audit_changes', 'audited_repaired'])->count(),
            'published' => (clone $items)->where('status', 'published')->count(),
            'duplicates' => (clone $items)->where('status', 'duplicate')->count() + (int) ($crawl['skipped_existing'] ?? 0),
            'failed' => (clone $items)->whereIn('status', ['failed', 'audit_missing'])->count(), 'status' => $pending ? 'processing' : 'completed',
        ]);
    }
}

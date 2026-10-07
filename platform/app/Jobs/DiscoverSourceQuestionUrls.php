<?php

namespace App\Jobs;

use App\Models\SourceQuestionImportRun;
use App\Services\SourceQuestionImportService;
use App\Services\SourceQuestionUrlCrawler;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;

class DiscoverSourceQuestionUrls implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 45;

    public function __construct(public int $runId) {}

    public function handle(SourceQuestionUrlCrawler $crawler, SourceQuestionImportService $imports): void
    {
        $run = SourceQuestionImportRun::find($this->runId);
        if (! $run || $run->status === 'failed') return;
        $options = (array) $run->options;
        $state = (array) ($options['crawl'] ?? []);
        if ($state === [] || ! empty($state['discovery_complete'])) return;

        $pending = array_values(array_unique(array_filter((array) ($state['pending_urls'] ?? []))));
        $visited = array_fill_keys((array) ($state['visited_urls'] ?? []), true);
        while ($pending !== [] && isset($visited[$pending[0]])) array_shift($pending);

        $maxPages = max(1, (int) ($state['max_pages'] ?? 100));
        $maxQuestions = max(1, (int) ($state['max_questions'] ?? 1000));
        if ($pending === [] || count($visited) >= $maxPages || (int) ($state['new_questions'] ?? 0) >= $maxQuestions) {
            $this->finish($run, $options, $state, $pending, array_keys($visited));
            return;
        }

        $pageUrl = array_shift($pending);
        $visited[$pageUrl] = true;
        $state['pages_scanned'] = count($visited);
        try {
            $response = Http::withHeaders(['User-Agent' => 'ExamElite Source Discovery/1.0'])
                ->timeout(25)->retry(1, 400)->get($pageUrl);
            $response->throw();
            if (strlen($response->body()) > 5 * 1024 * 1024) throw new \RuntimeException('Discovery page exceeded the 5 MB safety limit.');

            $result = $crawler->parse(
                $response->body(), $pageUrl, (string) $state['base_url'],
                (string) ($run->adapter ?: 'auto'), (string) ($state['question_pattern'] ?? ''),
            );
            $questionUrls = array_values(array_unique((array) ($result['question_urls'] ?? [])));
            $state['question_urls_found'] = (int) ($state['question_urls_found'] ?? 0) + count($questionUrls);
            $added = $imports->appendCrawlUrls($run, $questionUrls);
            $state['new_questions'] = (int) ($state['new_questions'] ?? 0) + $added['created'];
            $state['skipped_existing'] = (int) ($state['skipped_existing'] ?? 0) + $added['skipped_existing'];

            foreach ((array) ($result['crawl_urls'] ?? []) as $nextUrl) {
                if (! isset($visited[$nextUrl])) $pending[] = $nextUrl;
            }
            $pending = array_slice(array_values(array_unique($pending)), 0, $maxPages * 5);
        } catch (\Throwable $error) {
            $state['pages_failed'] = (int) ($state['pages_failed'] ?? 0) + 1;
            $state['last_error'] = $pageUrl.': '.$error->getMessage();
        }

        $state['pending_urls'] = $pending;
        $state['visited_urls'] = array_keys($visited);
        $complete = $pending === [] || count($visited) >= $maxPages || (int) $state['new_questions'] >= $maxQuestions;
        $state['discovery_complete'] = $complete;
        $options['crawl'] = $state;
        $run->update([
            'options' => $options,
            'duplicates' => $run->items()->where('status', 'duplicate')->count() + (int) ($state['skipped_existing'] ?? 0),
            'status' => $complete && ! $run->items()->whereIn('status', ['queued', 'processing'])->exists() ? 'completed' : 'processing',
        ]);

        if (! $complete) self::dispatch($run->id);
    }

    private function finish(SourceQuestionImportRun $run, array $options, array $state, array $pending, array $visited): void
    {
        $state['pending_urls'] = $pending;
        $state['visited_urls'] = $visited;
        $state['pages_scanned'] = count($visited);
        $state['discovery_complete'] = true;
        $options['crawl'] = $state;
        $run->update([
            'options' => $options,
            'duplicates' => $run->items()->where('status', 'duplicate')->count() + (int) ($state['skipped_existing'] ?? 0),
            'status' => $run->items()->whereIn('status', ['queued', 'processing'])->exists() ? 'processing' : 'completed',
        ]);
    }

    public function failed(\Throwable $error): void
    {
        $run = SourceQuestionImportRun::find($this->runId);
        if (! $run) return;
        $options = (array) $run->options;
        $state = (array) ($options['crawl'] ?? []);
        $state['discovery_complete'] = true;
        $state['last_error'] = $error->getMessage();
        $options['crawl'] = $state;
        $run->update(['options' => $options, 'status' => 'failed', 'failure_message' => 'Base URL discovery failed: '.$error->getMessage()]);
    }
}

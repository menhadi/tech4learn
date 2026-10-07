<?php

namespace App\Console\Commands;

use App\Models\SeoIntegration;
use App\Services\SearchConsoleService;
use Illuminate\Console\Command;

class SyncSearchConsole extends Command
{
    protected $signature = 'seo:sync-search-console {--integration=}';
    protected $description = 'Refresh connected Google Search Console properties';

    public function handle(SearchConsoleService $searchConsole): int
    {
        $query = SeoIntegration::query()
            ->where('provider', 'google_search_console')
            ->where('status', 'connected')
            ->whereNotNull('property_url');

        if ($this->option('integration')) {
            $query->whereKey($this->option('integration'));
        }

        $failed = 0;
        $query->eachById(function (SeoIntegration $integration) use ($searchConsole, &$failed) {
            try {
                $searchConsole->sync($integration);
                $this->line("Synced integration {$integration->id}");
            } catch (Throwable $exception) {
                $failed++;
                $integration->update(['last_error' => $exception->getMessage()]);
                report($exception);
                $this->error("Integration {$integration->id}: {$exception->getMessage()}");
            }
        });

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}

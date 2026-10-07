<?php

namespace App\Services;

use App\Models\SeoIntegration;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class SearchConsoleService
{
    private const API_ROOT = 'https://www.googleapis.com/webmasters/v3';

    public function properties(SeoIntegration $integration): array
    {
        $response = $this->client($integration)->get(self::API_ROOT . '/sites');

        if (! $response->successful()) {
            throw new RuntimeException($this->errorMessage($response->json(), 'Unable to load Search Console properties.'));
        }

        return collect($response->json('siteEntry', []))
            ->filter(fn (array $site) => in_array($site['permissionLevel'] ?? '', [
                'siteOwner', 'siteFullUser', 'siteRestrictedUser',
            ], true))
            ->map(fn (array $site) => [
                'url' => $site['siteUrl'] ?? '',
                'permission' => $site['permissionLevel'] ?? '',
            ])
            ->filter(fn (array $site) => filled($site['url']))
            ->values()
            ->all();
    }

    public function sync(SeoIntegration $integration): array
    {
        if (! $integration->property_url) {
            throw new RuntimeException('Choose a Search Console property before syncing.');
        }

        $end = now()->subDays(3)->toDateString();
        $start = now()->subDays(30)->toDateString();
        $previousEnd = now()->subDays(31)->toDateString();
        $previousStart = now()->subDays(58)->toDateString();

        $summary = $this->query($integration, $start, $end);
        $previous = $this->query($integration, $previousStart, $previousEnd);
        $queries = $this->query($integration, $start, $end, ['query'], 100);
        $pages = $this->query($integration, $start, $end, ['page'], 100);
        $daily = $this->query($integration, $start, $end, ['date'], 60);

        $currentRow = $summary['rows'][0] ?? $this->emptyRow();
        $previousRow = $previous['rows'][0] ?? $this->emptyRow();

        $data = array_merge($integration->data ?? [], [
            'summary' => [
                'clicks' => (int) round($currentRow['clicks'] ?? 0),
                'impressions' => (int) round($currentRow['impressions'] ?? 0),
                'ctr' => (float) ($currentRow['ctr'] ?? 0),
                'position' => (float) ($currentRow['position'] ?? 0),
                'changes' => [
                    'clicks' => $this->percentChange($currentRow['clicks'] ?? 0, $previousRow['clicks'] ?? 0),
                    'impressions' => $this->percentChange($currentRow['impressions'] ?? 0, $previousRow['impressions'] ?? 0),
                    'ctr' => $this->percentChange($currentRow['ctr'] ?? 0, $previousRow['ctr'] ?? 0),
                    'position' => round(($previousRow['position'] ?? 0) - ($currentRow['position'] ?? 0), 1),
                ],
                'start_date' => $start,
                'end_date' => $end,
            ],
            'queries' => $this->normalizeRows($queries['rows'] ?? [], 'query'),
            'pages' => $this->normalizeRows($pages['rows'] ?? [], 'page'),
            'daily' => $this->normalizeRows($daily['rows'] ?? [], 'date'),
        ]);

        $integration->forceFill([
            'data' => $data,
            'status' => 'connected',
            'last_synced_at' => now(),
            'last_error' => null,
        ])->save();

        return $data;
    }

    private function query(
        SeoIntegration $integration,
        string $startDate,
        string $endDate,
        array $dimensions = [],
        int $rowLimit = 1
    ): array {
        $site = rawurlencode($integration->property_url);
        $payload = [
            'startDate' => $startDate,
            'endDate' => $endDate,
            'type' => 'web',
            'rowLimit' => $rowLimit,
            'dataState' => 'final',
        ];

        if ($dimensions) {
            $payload['dimensions'] = $dimensions;
        }

        $response = $this->client($integration)
            ->post(self::API_ROOT . "/sites/{$site}/searchAnalytics/query", $payload);

        if (! $response->successful()) {
            throw new RuntimeException($this->errorMessage($response->json(), 'Search Console sync failed.'));
        }

        return $response->json();
    }

    private function client(SeoIntegration $integration): PendingRequest
    {
        $this->refreshAccessTokenIfNeeded($integration);

        if (! $integration->access_token) {
            throw new RuntimeException('Google authorization is missing. Reconnect Search Console.');
        }

        return Http::acceptJson()
            ->asJson()
            ->withToken($integration->access_token)
            ->timeout(30)
            ->retry(2, 400);
    }

    private function refreshAccessTokenIfNeeded(SeoIntegration $integration): void
    {
        if ($integration->access_token && $integration->token_expires_at?->isFuture()) {
            return;
        }

        if (! $integration->refresh_token) {
            return;
        }

        $response = Http::asForm()->timeout(20)->post('https://oauth2.googleapis.com/token', [
            'client_id' => config('services.google_search_console.client_id'),
            'client_secret' => config('services.google_search_console.client_secret'),
            'refresh_token' => $integration->refresh_token,
            'grant_type' => 'refresh_token',
        ]);

        if (! $response->successful() || ! $response->json('access_token')) {
            throw new RuntimeException($this->errorMessage($response->json(), 'Google authorization could not be refreshed.'));
        }

        $integration->forceFill([
            'access_token' => $response->json('access_token'),
            'token_expires_at' => now()->addSeconds(max(60, (int) $response->json('expires_in', 3600) - 60)),
        ])->save();
    }

    private function normalizeRows(array $rows, string $dimension): array
    {
        return collect($rows)->map(fn (array $row) => [
            $dimension => $row['keys'][0] ?? '',
            'clicks' => (int) round($row['clicks'] ?? 0),
            'impressions' => (int) round($row['impressions'] ?? 0),
            'ctr' => (float) ($row['ctr'] ?? 0),
            'position' => round((float) ($row['position'] ?? 0), 1),
        ])->all();
    }

    private function percentChange(float $current, float $previous): float
    {
        if ($previous == 0.0) return $current > 0 ? 100.0 : 0.0;

        return round((($current - $previous) / abs($previous)) * 100, 1);
    }

    private function emptyRow(): array
    {
        return ['clicks' => 0, 'impressions' => 0, 'ctr' => 0, 'position' => 0];
    }

    private function errorMessage(?array $body, string $fallback): string
    {
        return Str::limit((string) data_get($body, 'error.message', $fallback), 500, '');
    }
}

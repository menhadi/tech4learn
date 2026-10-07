<?php

namespace Tests\Unit;

use App\Models\SeoIntegration;
use App\Services\SearchConsoleService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SearchConsoleServiceTest extends TestCase
{
    public function test_sync_normalizes_summary_queries_pages_and_daily_rows(): void
    {
        Http::fakeSequence()
            ->push(['rows' => [[
                'clicks' => 120,
                'impressions' => 4000,
                'ctr' => .03,
                'position' => 8.4,
            ]]])
            ->push(['rows' => [[
                'clicks' => 100,
                'impressions' => 3200,
                'ctr' => .03125,
                'position' => 9.2,
            ]]])
            ->push(['rows' => [[
                'keys' => ['mock test online'],
                'clicks' => 12,
                'impressions' => 420,
                'ctr' => .02857,
                'position' => 7.8,
            ]]])
            ->push(['rows' => [[
                'keys' => ['https://example.com/mock-tests'],
                'clicks' => 20,
                'impressions' => 600,
                'ctr' => .0333,
                'position' => 6.1,
            ]]])
            ->push(['rows' => [[
                'keys' => ['2026-08-20'],
                'clicks' => 5,
                'impressions' => 100,
                'ctr' => .05,
                'position' => 7.0,
            ]]]);

        $integration = new class extends SeoIntegration {
            public function save(array $options = []): bool
            {
                $this->exists = true;
                return true;
            }
        };
        $integration->forceFill([
            'property_url' => 'sc-domain:example.com',
            'access_token' => 'test-token',
            'refresh_token' => 'test-refresh-token',
            'token_expires_at' => now()->addHour(),
            'status' => 'connected',
            'data' => [],
        ]);

        $data = app(SearchConsoleService::class)->sync($integration);

        $this->assertSame(120, $data['summary']['clicks']);
        $this->assertSame(4000, $data['summary']['impressions']);
        $this->assertSame(20.0, $data['summary']['changes']['clicks']);
        $this->assertSame('mock test online', $data['queries'][0]['query']);
        $this->assertSame('https://example.com/mock-tests', $data['pages'][0]['page']);
        $this->assertSame('2026-08-20', $data['daily'][0]['date']);
        $this->assertNotNull($integration->last_synced_at);

        Http::assertSentCount(5);
        Http::assertSent(fn ($request) => str_contains(
            $request->url(),
            'sites/sc-domain%3Aexample.com/searchAnalytics/query'
        ));
    }
}

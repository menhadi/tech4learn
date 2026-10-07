<?php

namespace Tests\Unit;

use App\Services\GoogleAnalyticsSettings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class GoogleAnalyticsSettingsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        (require database_path('migrations/2026_10_05_102432_create_google_analytics_settings_table.php'))->up();
    }

    public function test_tracking_is_disabled_until_saved_and_is_isolated_by_host(): void
    {
        $settings = app(GoogleAnalyticsSettings::class);
        $this->assertFalse($settings->current('examelite.com')['enabled']);
        $settings->save('examelite.com', true, 'G-XL23PFK983');
        $this->assertSame('G-XL23PFK983', $settings->current('WWW.ExamElite.com')['measurement_id']);
        $this->assertFalse($settings->current('school.examelite.com')['enabled']);
        $settings->save('examelite.com', false, 'G-XL23PFK983');
        $this->assertFalse($settings->current('examelite.com')['enabled']);
    }

    public function test_public_tag_renders_once_and_removes_query_from_page_location(): void
    {
        $this->app->instance('request', \Illuminate\Http\Request::create('https://examelite.com/papers?email=private@example.org'));
        app(GoogleAnalyticsSettings::class)->save('examelite.com', true, 'G-XL23PFK983');
        $html = view('website.partials.google-analytics')->render();
        $this->assertSame(1, substr_count($html, 'googletagmanager.com/gtag/js'));
        $this->assertStringNotContainsString('private@example.org', $html);
        app(GoogleAnalyticsSettings::class)->save('examelite.com', true, '<script>alert(1)</script>');
        $this->assertStringNotContainsString('googletagmanager.com', view('website.partials.google-analytics')->render());
    }

    public function test_missing_migration_keeps_public_pages_available_and_routes_require_admin(): void
    {
        Schema::drop('google_analytics_settings');
        $this->assertFalse(app(GoogleAnalyticsSettings::class)->current('examelite.com')['enabled']);
        foreach (['configurations.analytics', 'configurations.analytics.update'] as $name) {
            $this->assertContains('platform.admin', app('router')->getRoutes()->getByName($name)->gatherMiddleware());
        }
    }

    public function test_admin_save_validates_id_and_can_disable_tracking(): void
    {
        $this->withoutMiddleware();
        $this->put('https://examelite.com/configurations/analytics', ['enabled' => 1, 'measurement_id' => '<script>'])->assertSessionHasErrors('measurement_id');
        $this->put('https://examelite.com/configurations/analytics', ['enabled' => 1, 'measurement_id' => ''])->assertSessionHasErrors('measurement_id');
        $this->app['session']->forget('errors');
        $this->put('https://examelite.com/configurations/analytics', ['enabled' => 1, 'measurement_id' => 'G-XL23PFK983'])->assertSessionHasNoErrors()->assertRedirect(route('configurations.analytics'));
        $this->assertTrue(app(GoogleAnalyticsSettings::class)->current('examelite.com')['enabled']);
        $this->put('https://examelite.com/configurations/analytics', ['enabled' => 0, 'measurement_id' => ''])->assertSessionHasNoErrors();
        $this->assertFalse(app(GoogleAnalyticsSettings::class)->current('examelite.com')['enabled']);
    }
}

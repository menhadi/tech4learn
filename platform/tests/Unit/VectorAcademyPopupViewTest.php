<?php

namespace Tests\Unit;

use App\Models\Configuration;
use PHPUnit\Framework\TestCase;

class VectorAcademyPopupViewTest extends TestCase
{
    public function test_configuration_casts_popup_settings_and_migration_adds_json_storage(): void
    {
        $configuration = new Configuration([
            'partner_popup_settings' => [
                'enabled' => false,
                'delay_seconds' => 30,
            ],
        ]);

        $this->assertIsArray($configuration->partner_popup_settings);
        $this->assertFalse($configuration->partner_popup_settings['enabled']);
        $this->assertSame(30, $configuration->partner_popup_settings['delay_seconds']);

        $migration = $this->source('database/migrations/2026_08_22_000002_add_partner_popup_settings_to_configurations.php');
        $this->assertStringContainsString("json('partner_popup_settings')->nullable()", $migration);
    }

    public function test_admin_controls_and_validation_cover_campaign_content_timing_and_design(): void
    {
        $settings = $this->source('resources/views/configurations/website.blade.php');
        $controller = $this->source('app/Http/Controllers/ConfigurationController.php');

        foreach ([
            'partner_popup_enabled',
            'partner_popup_scope',
            'partner_popup_delay_seconds',
            'partner_popup_repeat_days',
            'partner_popup_title',
            'partner_popup_message',
            'partner_popup_button_label',
            'partner_popup_url',
            'partner_popup_position',
            'partner_popup_icon',
            'partner_popup_accent_color',
            'partner_popup_background_color',
            'partner_popup_open_new_tab',
        ] as $field) {
            $this->assertStringContainsString('name="' . $field . '"', $settings, $field);
            $this->assertStringContainsString("'" . $field . "'", $controller, $field);
        }

        $this->assertStringContainsString("'partner_popup_settings' => \$partnerPopupSettings", $controller);
        $this->assertStringContainsString("url:http,https", $controller);
    }

    public function test_public_popup_uses_admin_settings_and_campaign_specific_dismissal(): void
    {
        $layout = $this->source('resources/views/website/layouts/app.blade.php');
        $popup = $this->source('resources/views/website/partials/vector-academy-popup.blade.php');

        $this->assertStringContainsString("@include('website.partials.vector-academy-popup')", $layout);
        $this->assertStringContainsString('$configuration->partner_popup_settings', $popup);
        $this->assertStringContainsString('$partnerPopupShouldRender', $popup);
        $this->assertStringContainsString('@json($partnerPopupDelay * 1000)', $popup);
        $this->assertStringContainsString('repeatDays === 0 ? window.sessionStorage : window.localStorage', $popup);
        $this->assertStringContainsString('$partnerPopupFingerprint', $popup);
        $this->assertStringContainsString('vector-academy-popup--center', $popup);
    }

    private function source(string $path): string
    {
        return (string) file_get_contents(
            dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path)
        );
    }
}

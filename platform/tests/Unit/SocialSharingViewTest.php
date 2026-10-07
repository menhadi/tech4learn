<?php

namespace Tests\Unit;

use App\Models\Configuration;
use PHPUnit\Framework\TestCase;

class SocialSharingViewTest extends TestCase
{
    public function test_configuration_casts_social_settings_and_has_json_storage(): void
    {
        $configuration = new Configuration([
            'social_sharing_settings' => ['enabled' => true, 'platforms' => ['facebook', 'copy']],
        ]);

        $this->assertIsArray($configuration->social_sharing_settings);
        $this->assertSame(['facebook', 'copy'], $configuration->social_sharing_settings['platforms']);
        $this->assertStringContainsString(
            "json('social_sharing_settings')->nullable()",
            $this->source('database/migrations/2026_09_02_000001_add_social_sharing_settings_to_configurations.php')
        );
    }

    public function test_admin_controls_validate_platforms_and_profile_urls(): void
    {
        $view = $this->source('resources/views/configurations/website.blade.php');
        $controller = $this->source('app/Http/Controllers/ConfigurationController.php');

        $this->assertStringContainsString('name="social_share_enabled"', $view);
        $this->assertStringContainsString('name="social_share_platforms[]"', $view);
        $this->assertStringContainsString('name="social_profiles[{{ $key }}]"', $view);
        $this->assertStringContainsString('Admin share composer', $view);
        $this->assertStringContainsString("'social_share_platforms.*' => 'in:native,facebook,x,linkedin,whatsapp,telegram,reddit,pinterest,email,copy'", $controller);
        $this->assertStringContainsString("'social_profiles.*' => 'nullable|url:http,https|max:1000'", $controller);
    }

    public function test_public_layout_has_configurable_share_widget_and_footer_profiles(): void
    {
        $layout = $this->source('resources/views/website/layouts/app.blade.php');
        $share = $this->source('resources/views/website/partials/social-share.blade.php');
        $footer = $this->source('resources/views/website/footer.blade.php');

        $this->assertStringContainsString("@include('website.partials.social-share')", $layout);
        $this->assertStringContainsString('$configuration->social_sharing_settings', $share);
        $this->assertStringContainsString('navigator.share', $share);
        $this->assertStringContainsString('navigator.clipboard.writeText', $share);
        $this->assertStringContainsString('Official social media profiles', $footer);
        $this->assertStringContainsString('rel="noopener noreferrer"', $footer);
    }

    private function source(string $path): string
    {
        return (string) file_get_contents(dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path));
    }
}

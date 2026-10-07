<?php

namespace Tests\Unit;

use App\Http\Controllers\AIContentController;
use App\Models\Configuration;
use App\Support\AiProvider;
use PHPUnit\Framework\TestCase;

class AiProviderFallbackTest extends TestCase
{
    public function test_legacy_provider_is_used_when_priority_has_not_been_saved(): void
    {
        $settings = new Configuration();
        $settings->forceFill(['ai_provider' => 'google']);

        $this->assertSame(['google', 'deepseek', 'openai', 'anthropic'], AiProvider::priority($settings, 'content_seo'));
    }

    public function test_default_provider_order_prevents_empty_priority_from_disabling_ai(): void
    {
        $settings = new Configuration();

        $this->assertSame(['deepseek', 'openai', 'google', 'anthropic'], AiProvider::priority($settings, 'content_seo'));
    }

    public function test_ai_failures_are_converted_to_actionable_public_messages(): void
    {
        $method = new \ReflectionMethod(AIContentController::class, 'publicAiError');
        $controller = new AIContentController();

        $this->assertStringContainsString('timed out', $method->invoke($controller, new \RuntimeException('cURL timeout')));
        $this->assertStringContainsString('credentials', $method->invoke($controller, new \RuntimeException('HTTP 401 unauthorized')));
        $this->assertStringContainsString('quota', $method->invoke($controller, new \RuntimeException('429 rate limit')));
    }
}
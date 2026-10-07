<?php

namespace App\Services;

use Illuminate\Http\Request;

class BotTrafficDetector
{
    private const USER_AGENT_PATTERNS = [
        'Googlebot' => ['googlebot', 'google-inspectiontool', 'googleother'],
        'Bingbot' => ['bingbot', 'bingpreview'],
        'Amazonbot' => ['amazonbot'],
        'Cloudflare' => ['cloudflare-healthchecks', 'cloudflare-alwaysonline'],
        'AI crawler' => ['gptbot', 'chatgpt-user', 'claudebot', 'anthropic-ai', 'perplexitybot', 'cohere-ai'],
        'Social preview' => ['facebookexternalhit', 'facebot', 'twitterbot', 'linkedinbot', 'slackbot', 'discordbot'],
        'SEO crawler' => ['ahrefsbot', 'semrushbot', 'dotbot', 'mj12bot', 'petalbot', 'bytespider', 'dataforseobot'],
        'Generic crawler' => ['crawler', 'spider', 'headlesschrome', 'phantomjs', 'selenium', 'playwright'],
        'Automated client' => ['python-requests', 'python-urllib', 'go-http-client', 'apache-httpclient', 'curl/', 'wget/', 'libwww-perl'],
    ];

    public static function detect(?Request $request = null): array
    {
        $request ??= request();
        $userAgent = strtolower((string) $request->userAgent());
        $purpose = strtolower((string) ($request->headers->get('sec-purpose') ?: $request->headers->get('purpose')));

        if (str_contains($purpose, 'prefetch') || str_contains($purpose, 'preview')) {
            return ['is_bot' => true, 'name' => 'Browser prefetch', 'reason' => 'prefetch'];
        }

        foreach (self::USER_AGENT_PATTERNS as $name => $patterns) {
            foreach ($patterns as $pattern) {
                if ($userAgent !== '' && str_contains($userAgent, $pattern)) {
                    return ['is_bot' => true, 'name' => $name, 'reason' => 'user_agent:'.$pattern];
                }
            }
        }

        return ['is_bot' => false, 'name' => null, 'reason' => null];
    }

    public static function databasePatterns(): array
    {
        return collect(self::USER_AGENT_PATTERNS)->flatten()->values()->all();
    }
}

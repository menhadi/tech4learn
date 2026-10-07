<?php

namespace Tests\Unit;

use App\Services\UrlRedirectService;
use PHPUnit\Framework\TestCase;

class UrlRedirectServiceTest extends TestCase
{
    public function test_it_normalizes_source_paths_without_query_strings_or_trailing_slashes(): void
    {
        $this->assertSame('/old/exam', UrlRedirectService::normalizeSourcePath('old//exam/?utm_source=google'));
        $this->assertSame('/', UrlRedirectService::normalizeSourcePath('/'));
    }

    public function test_it_accepts_only_safe_internal_targets(): void
    {
        $this->assertSame('/courses?page=2', UrlRedirectService::normalizeTargetPath('/courses/?page=2#top'));
        $this->assertNull(UrlRedirectService::normalizeTargetPath('https://example.com/courses'));
        $this->assertNull(UrlRedirectService::normalizeTargetPath('//example.com/courses'));
    }
}

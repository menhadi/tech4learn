<?php

namespace Tests\Unit;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\TestCase;

class BladeDirectiveCompatibilityTest extends TestCase
{
    public function test_views_do_not_use_inline_php_directives_unsupported_by_laravel_12(): void
    {
        $violations = [];
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(resource_path('views'))
        );

        foreach ($files as $file) {
            if (! $file->isFile() || ! str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }

            $contents = file_get_contents($file->getPathname());
            if (preg_match('/@php\s*\(/', $contents)) {
                $violations[] = str_replace(base_path().DIRECTORY_SEPARATOR, '', $file->getPathname());
            }
        }

        $this->assertSame(
            [],
            $violations,
            'Use @php ... @endphp blocks; inline @php(...) leaves later Blade directives uncompiled on Laravel 12.'
        );
    }
}

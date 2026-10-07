<?php
// Pure initial-production profile checks; no environment mutation or scheduler activation.
function nativeRuntimeProfileChecks(array $env): array
{
    return [
        'explicit PHP 8.4 detached-worker binary' => ($env['PHP_CLI_BINARY'] ?? '') === '/usr/bin/php8.4',
        'initial paper processing bounded to one worker' => ($env['PAPER_PROCESSING_WORKERS'] ?? '') === '1'
            && ($env['PAPER_PROCESSING_MAX_PARALLEL'] ?? '') === '1'
            && ($env['PAPER_PROCESSING_MAX_HEAVY'] ?? '') === '1',
        'lifecycle email jobs explicitly disabled pending delivery acceptance' => ($env['NATIVE_SCHEDULE_LIFECYCLE_EMAILS'] ?? '') === 'false',
        'Search Console jobs explicitly disabled pending provider acceptance' => ($env['NATIVE_SCHEDULE_SEARCH_CONSOLE'] ?? '') === 'false',
        'private shared native storage selected' => ($env['LARAVEL_STORAGE_PATH'] ?? '') === '/home/tech4learn/native-shared/storage',
    ];
}

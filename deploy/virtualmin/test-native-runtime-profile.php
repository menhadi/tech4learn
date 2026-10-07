<?php
require __DIR__.'/native-runtime-profile.php';
$accepted = [
    'PHP_CLI_BINARY' => '/usr/bin/php8.4',
    'PAPER_PROCESSING_WORKERS' => '1',
    'PAPER_PROCESSING_MAX_PARALLEL' => '1',
    'PAPER_PROCESSING_MAX_HEAVY' => '1',
    'NATIVE_SCHEDULE_LIFECYCLE_EMAILS' => 'false',
    'NATIVE_SCHEDULE_SEARCH_CONSOLE' => 'false',
    'LARAVEL_STORAGE_PATH' => '/home/tech4learn/native-shared/storage',
];
if (in_array(false, nativeRuntimeProfileChecks($accepted), true)) throw new RuntimeException('Accepted profile rejected');
foreach ($accepted as $key => $value) {
    $missing = $accepted; unset($missing[$key]);
    if (!in_array(false, nativeRuntimeProfileChecks($missing), true)) throw new RuntimeException('Missing setting accepted');
    $changed = $accepted; $changed[$key] = 'unexpected';
    if (!in_array(false, nativeRuntimeProfileChecks($changed), true)) throw new RuntimeException('Changed setting accepted');
}
foreach (['NATIVE_SCHEDULE_LIFECYCLE_EMAILS', 'NATIVE_SCHEDULE_SEARCH_CONSOLE'] as $key) {
    $enabled = $accepted; $enabled[$key] = 'true';
    if (!in_array(false, nativeRuntimeProfileChecks($enabled), true)) throw new RuntimeException('Unaccepted provider enabled');
}
echo "PASS: initial runtime profile and missing/changed/provider settings boundaries\n";

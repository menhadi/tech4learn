<?php

return [
    // CLI executable used when a web request hands one audit directly to a
    // detached worker. Override this on hosts with a versioned PHP binary.
    'php_cli_binary' => env('PHP_CLI_BINARY', '/usr/bin/php'),

    // Maximum number of independent papers processed across source extraction,
    // quality audit and repair workflows. Work inside each paper remains ordered.
    'workers' => max(1, min(8, (int) env('PAPER_PROCESSING_WORKERS', 4))),
    'max_parallel_papers' => max(1, min(8, (int) env('PAPER_PROCESSING_MAX_PARALLEL', 4))),

    // OCR and Playwright/Chromium are memory-heavy. They share this smaller pool.
    'max_parallel_heavy' => max(1, min(4, (int) env('PAPER_PROCESSING_MAX_HEAVY', 2))),
];
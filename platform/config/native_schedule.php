<?php

return [
    // Keep copied engine defaults; Tech4Learn deployment opts in after provider acceptance.
    'lifecycle_emails' => env('NATIVE_SCHEDULE_LIFECYCLE_EMAILS', true),
    'search_console' => env('NATIVE_SCHEDULE_SEARCH_CONSOLE', true),
];

<?php

return [
    'enabled' => env('GOOGLE_SHEETS_ENABLED', false),

    // Absolute path to a Google service-account JSON file. Never commit the file.
    'credentials' => env('GOOGLE_SERVICE_ACCOUNT_CREDENTIALS'),

    // Optional fallback. The setup screen can instead use the signed-in admin email.
    'share_email' => env('GOOGLE_SHEETS_SHARE_EMAIL'),

    'tab_name' => env('GOOGLE_SHEETS_TAB_NAME', 'Data'),
    'max_rows' => (int) env('GOOGLE_SHEETS_MAX_ROWS', 20000),
];

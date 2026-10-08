<?php

return [
    // A fixed server-controlled endpoint. Never accept a destination from a request.
    'api_url' => env('ATTENDANCE_API_URL', ''),
    // Operator enables only after migrations and pinned public-key registration.
    'student_delivery_enabled' => env('STUDENT_DELIVERY_ENABLED', false),
];

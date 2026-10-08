<?php

$marker=storage_path('app/private/student-delivery.enabled');
$reviewedMarker=is_file($marker) && !is_link($marker) && filesize($marker)===11
    && (PHP_OS_FAMILY==='Windows' || (fileperms($marker)&0077)===0)
    && file_get_contents($marker)==="enabled-v1\n";
$admissionsMarker=storage_path('app/private/student-admissions.enabled');
$reviewedAdmissions=is_file($admissionsMarker) && !is_link($admissionsMarker) && filesize($admissionsMarker)===11
    && (PHP_OS_FAMILY==='Windows' || (fileperms($admissionsMarker)&0077)===0)
    && file_get_contents($admissionsMarker)==="enabled-v1\n";
return [
    // A fixed server-controlled endpoint. Never accept a destination from a request.
    'api_url' => env('ATTENDANCE_API_URL', ''),
    // Operator enables only after migrations and pinned public-key registration.
    'student_delivery_enabled' => env('STUDENT_DELIVERY_ENABLED', $reviewedMarker),
    // Separate opt-in after the complete reviewed admission protocol and migrations are checked.
    'student_admissions_enabled' => env('STUDENT_ADMISSIONS_ENABLED', $reviewedAdmissions),
];

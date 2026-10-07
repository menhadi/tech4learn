<?php
// Pure predicate: ownership is enforced by the explicit organisation/student SQL join.
function nativeAttendanceStudentReady(array $rows): bool
{
    if (count($rows) !== 1) return false;
    $row = $rows[0];
    $settings = json_decode($row['settings'] ?? '', true);
    return is_array($settings)
        && ($settings['is_primary_platform'] ?? null) === false
        && ($row['organisation_status'] ?? '') === 'active'
        && ($row['student_status'] ?? '') === 'Active';
}

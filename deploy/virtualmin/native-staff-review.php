<?php
// Pure predicate shared by read-only operator review and boundary tests.
function nativeAttendanceStaffReady(array $rows): bool
{
    if (count($rows) !== 1) return false;
    $row = $rows[0];
    $settings = json_decode($row['settings'] ?? '', true);
    return is_array($settings)
        && ($settings['is_primary_platform'] ?? null) === false
        && ($row['organisation_status'] ?? '') === 'active'
        && ($row['user_status'] ?? '') === 'Active'
        && (string)($row['deleted'] ?? '') === '0'
        && (string)($row['is_platform_admin'] ?? '') === '0'
        && (string)($row['membership_status'] ?? '') === '1'
        && in_array($row['membership_role'] ?? '', ['owner', 'admin'], true);
}

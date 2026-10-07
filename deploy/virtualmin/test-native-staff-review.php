<?php
require __DIR__.'/native-staff-review.php';
$valid=['settings'=>'{"is_primary_platform":false}','organisation_status'=>'active','user_status'=>'Active','deleted'=>0,'is_platform_admin'=>0,'membership_status'=>1,'membership_role'=>'owner'];
if (!nativeAttendanceStaffReady([$valid])) throw new RuntimeException('Valid owner rejected');
$admin=$valid; $admin['membership_role']='admin';
if (!nativeAttendanceStaffReady([$admin])) throw new RuntimeException('Valid administrator rejected');
foreach (['settings'=>'{"is_primary_platform":true}','organisation_status'=>'inactive','user_status'=>'Inactive','deleted'=>1,'is_platform_admin'=>1,'membership_status'=>0,'membership_role'=>'staff'] as $key=>$value) {
    $invalid=$valid; $invalid[$key]=$value;
    if (nativeAttendanceStaffReady([$invalid])) throw new RuntimeException('Invalid authority accepted');
    unset($invalid[$key]);
    if (nativeAttendanceStaffReady([$invalid])) throw new RuntimeException('Missing authority accepted');
}
foreach ([[],[$valid,$valid],[array_replace($valid,['settings'=>'invalid'])],[array_replace($valid,['settings'=>'{}'])]] as $rows)
    if (nativeAttendanceStaffReady($rows)) throw new RuntimeException('Ambiguous authority accepted');
echo "PASS: native staff membership, realm and revocation boundaries\n";

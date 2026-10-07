<?php
require __DIR__.'/foundation-tenancy.php';
$loader->addClassMap(['App\\Support\\AttendanceBridge'=>realpath(__DIR__.'/../platform/app/Support/AttendanceBridge.php')]);
$api = getenv('FOUNDATION_TEST_API_URL');
if (!is_string($api) || !preg_match('#^http://127\.0\.0\.1:[0-9]+/api/v1$#D',$api)) {
    throw new RuntimeException('Only the ephemeral loopback test API is allowed');
}
actor(2);
$request=Illuminate\Http\Request::create('https://two.example.invalid/attendance/records?date=2026-10-07&organisationId=22222222-2222-4222-8222-222222222222&nativeUserId=99','GET',[],['t4l_session'=>str_repeat('d',64)]);
$request->setUserResolver(fn()=>Illuminate\Support\Facades\Auth::user());
$app->instance('request',$request);
config(['attendance.api_url'=>$api]);
$data=(new App\Support\AttendanceBridge)->records($request);
if (($data['total']??null)!==1 || count($data['rows']??[])!==1 || $data['rows'][0]['group_name']!=='Synthetic authorised section') {
    throw new RuntimeException('The connected bridge did not preserve attendance scope');
}
echo "PASS: real PHP-to-Nest attendance read preserves explicit identity and excludes the foreign organisation record.\n";

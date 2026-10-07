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
$bridge=new App\Support\AttendanceBridge;
$identity=$bridge->learnerIdentity($request,App\Models\Student::findOrFail(10));
if ($identity['learnerId']!==getenv('FOUNDATION_TEST_LEARNER') || $identity['nativeStudentId']!=='10') {
    throw new RuntimeException('Connected learner identity selected another canonical record');
}
echo "PASS: real native student resolves its explicit canonical learner identity.\n";
$login=Illuminate\Http\Request::create('https://two.example.invalid/login','POST',['login'=>'synthetic@example.invalid','password'=>'long synthetic foundation password']);
$user=$bridge->authenticate($login);
if ($user->id!==2) { throw new RuntimeException('Connected sign-in mapped another native account'); }
$token=Illuminate\Support\Facades\Cookie::queued('t4l_session')->getValue();
$org='11111111-1111-4111-8111-111111111111';
function gatewayRequest(string $path, string $method='GET', ?array $body=null): array {
    global $app,$bridge,$token;
    $req=Illuminate\Http\Request::create('https://two.example.invalid/attendance/api/'.$path,$method,[],['t4l_session'=>$token],[],
        $body===null?[]:['CONTENT_TYPE'=>'application/json'], $body===null?null:json_encode($body));
    $req->setUserResolver(fn()=>Illuminate\Support\Facades\Auth::user());$app->instance('request',$req);
    $response=$bridge->gateway($req,$path);
    if (!in_array($response->getStatusCode(),[200,201],true)) { throw new RuntimeException('Connected attendance action rejected: '.$response->getContent()); }
    return json_decode($response->getContent(),true);
}
$base='organisations/'.$org.'/attendance';
$capture=gatewayRequest($base.'/captures','POST',['group_id'=>getenv('FOUNDATION_TEST_GROUP')]);
if (empty($capture['id'])) { throw new RuntimeException('Capture intent missing'); }
// Same artificial one-pixel JPEG container as the API's transport tests; no learner photo.
$photo=base64_encode(pack('C*',255,216,255,192,0,17,8,0,1,0,1,3,1,17,0,2,17,0,3,17,0,255,218,0,12,3,1,0,2,0,3,0,0,63,0,0,255,217));
$now=now()->utc()->format('Y-m-d\TH:i:s.v\Z');
gatewayRequest($base.'/captures/'.$capture['id'].'/submit','POST',['photo'=>$photo,'captured_at'=>$now,'location'=>['latitude'=>0,'longitude'=>0,'accuracy'=>10,'timestamp'=>$now],'custom_values'=>(object)[]]);
$detail=gatewayRequest($base.'/'.$capture['id']);
gatewayRequest($base.'/'.$capture['id'].'/review','POST',['version'=>$detail['version'],'decision'=>'confirmed','reason'=>'Synthetic verification','marks'=>[getenv('FOUNDATION_TEST_LEARNER')=>'present'],'acknowledge_warnings'=>true]);
$confirmed=gatewayRequest($base.'/'.$capture['id']);
if ($confirmed['status']!=='confirmed'||count($confirmed['reviews']??[])!==1) { throw new RuntimeException('Capture/review history did not persist'); }
$request->cookies->set('t4l_session',$token);$bridge->signOut($request);
try { $bridge->context($request);throw new RuntimeException('Logout did not revoke API session'); }
catch (Symfony\Component\HttpKernel\Exception\HttpException $error) { if ($error->getStatusCode()!==401)throw $error; }
echo "PASS: connected mapped login, scoped capture/submission/review/history and API logout.\n";

<?php
// Reuse the isolated in-memory native fixture; no database, browser or remote API writes.
require __DIR__.'/foundation-tenancy.php';
$loader->addClassMap([
    'App\\Support\\AttendanceBridge' => realpath(__DIR__.'/../platform/app/Support/AttendanceBridge.php'),
    'App\\Http\\Middleware\\EncryptCookies' => realpath(__DIR__.'/../platform/app/Http/Middleware/EncryptCookies.php'),
    'App\\Http\\Controllers\\AttendanceBridgeController' => realpath(__DIR__.'/../platform/app/Http/Controllers/AttendanceBridgeController.php'),
]);
foreach (['attendance.context','attendance.records'] as $name) {
    $route=Illuminate\Support\Facades\Route::getRoutes()->getByName($name);
    if (!$route || !in_array('auth',$route->middleware(),true) || !in_array('GET',$route->methods(),true)
        || !class_exists($route->getControllerClass())) { throw new RuntimeException('Native bridge route is not guarded/registered'); }
}
actor(2);
$request = Illuminate\Http\Request::create('https://two.example.invalid/attendance/records?date=2026-10-07', 'GET', [], ['t4l_session'=>str_repeat('d',64)]);
$request->setUserResolver(fn()=>Illuminate\Support\Facades\Auth::user());
$app->instance('request',$request);
config(['attendance.api_url'=>'http://127.0.0.1:3000/api/v1']);
$bridge = new App\Support\AttendanceBridge;
$context = ['nativeOrganisationId'=>'2','nativeUserId'=>'2','userId'=>'33333333-3333-4333-8333-333333333333','organisation'=>['id'=>'11111111-1111-4111-8111-111111111111','name'=>'Synthetic Two'],
    'permissions'=>['attendance.view'],'scope'=>['type'=>'organisation','ids'=>[]]];
function responseJson(array $body, int $status=200): GuzzleHttp\Psr7\Response {
    return new GuzzleHttp\Psr7\Response($status,['Content-Type'=>'application/json'],json_encode($body));
}
function clientMock(array $responses, array &$history): GuzzleHttp\Client {
    $stack = GuzzleHttp\HandlerStack::create(new GuzzleHttp\Handler\MockHandler($responses));
    $stack->push(GuzzleHttp\Middleware::history($history));
    return new GuzzleHttp\Client(['handler'=>$stack]);
}
function statusDenied(int $status, callable $action): void {
    try { $action(); } catch (Symfony\Component\HttpKernel\Exception\HttpException $error) {
        if ($error->getStatusCode()===$status) { return; }
        throw $error;
    }
    throw new RuntimeException('Expected bridge access denial');
}
$history=[];
$http=clientMock([responseJson($context)],$history);
if ($bridge->context($request,$http)!==$context || count($history)!==1) { throw new RuntimeException('Explicit context failed'); }
if ($history[0]['request']->getHeaderLine('Cookie')!=='t4l_session='.str_repeat('d',64)) { throw new RuntimeException('Wrong API credential'); }
if ($history[0]['request']->getUri()->getPath()!=='/api/v1/foundation/organisations/2/staff/2/attendance-context') { throw new RuntimeException('Wrong identity source'); }
foreach ([['users','status','Suspended','Active'],['organization_users','status',0,1]] as [$table,$column,$revoked,$active]) {
    $h=[];
    $mock=clientMock([function () use ($db,$context,$table,$column,$revoked) {
        $db->table($table)->where('id',$table==='users'?2:1)->update([$column=>$revoked]);
        return responseJson($context);
    }],$h);
    try { statusDenied(403,fn()=>$bridge->context($request,$mock)); }
    finally {
        $db->table($table)->where('id',$table==='users'?2:1)->update([$column=>$active]);
        actor(2);$app->instance('request',$request);
    }
}
foreach (['http://outside.example.invalid/api/v1','https://user@example.invalid/api/v1','https://example.invalid/api/v1?redirect=1',''] as $url) {
    config(['attendance.api_url'=>$url]);
    statusDenied(503,fn()=>$bridge->context($request,$http));
}
config(['attendance.api_url'=>'http://127.0.0.1:3000/api/v1']);
$request->cookies->remove('t4l_session');
statusDenied(401,fn()=>$bridge->context($request,$http));
$request->cookies->set('t4l_session',str_repeat('d',64));
$cookiePass=$app->make(App\Http\Middleware\EncryptCookies::class)->handle($request,fn($r)=>new Symfony\Component\HttpFoundation\Response($r->cookie('t4l_session')));
if ($cookiePass->getContent()!==str_repeat('d',64)) { throw new RuntimeException('Attendance cookie was decrypted as Laravel data'); }
foreach ([401,403,404,302] as $code) {
    $h=[]; $mock=clientMock([responseJson(['private'=>'must not leak'],$code)],$h);
    statusDenied($code===302?502:$code,fn()=>$bridge->context($request,$mock));
}
$wrong=$context; $wrong['nativeUserId']='99';
$h=[]; $mock=clientMock([responseJson($wrong)],$h);
statusDenied(502,fn()=>$bridge->context($request,$mock));
$h=[]; $mock=clientMock([new GuzzleHttp\Psr7\Response(200,[],str_repeat('x',1048577))],$h);
statusDenied(502,fn()=>$bridge->context($request,$mock));
$h=[]; $mock=clientMock([responseJson($context),responseJson(['items'=>[]]),responseJson($context)],$h);
if ($bridge->records($request,$mock)!==['items'=>[]] || count($h)!==3) { throw new RuntimeException('Scoped read failed'); }
if ($h[1]['request']->getUri()->getQuery()!=='date=2026-10-07') { throw new RuntimeException('Unexpected forwarded query'); }
$h=[]; $mock=clientMock([responseJson($context),responseJson(['items'=>[['synthetic'=>'withheld']]]),responseJson([],404)],$h);
statusDenied(404,fn()=>$bridge->records($request,$mock));
$changed=$context; $changed['scope']=['type'=>'centres','ids'=>[]];
$h=[]; $mock=clientMock([responseJson($context),responseJson(['items'=>[['synthetic'=>'withheld']]]),responseJson($changed)],$h);
statusDenied(403,fn()=>$bridge->records($request,$mock));
$h=[]; $mock=clientMock([responseJson($context),function () use ($db) {
    $db->table('users')->where('id',2)->update(['status'=>'Suspended']);
    return responseJson(['items'=>[['synthetic'=>'withheld']]]);
}],$h);
statusDenied(403,fn()=>$bridge->records($request,$mock));
$db->table('users')->where('id',2)->update(['status'=>'Active']);
actor(2);$app->instance('request',$request);
$student=App\Models\Student::findOrFail(10);
$identity=['nativeOrganisationId'=>'2','nativeUserId'=>'2','nativeStudentId'=>'10','organisationId'=>'11111111-1111-4111-8111-111111111111','learnerId'=>'44444444-4444-4444-8444-444444444444','version'=>1];
$h=[];$mock=clientMock([responseJson($identity),responseJson($identity)],$h);
if ($bridge->learnerIdentity($request,$student,$mock)!==$identity || count($h)!==2) { throw new RuntimeException('Native learner identity was not checked twice'); }
$foreign=new App\Models\Student(['organization_id'=>1,'status'=>'Active']);$foreign->id=99;
$h=[];statusDenied(404,fn()=>$bridge->learnerIdentity($request,$foreign,clientMock([],$h)));
$h=[];$changed=array_merge($identity,['version'=>2]);
statusDenied(403,fn()=>$bridge->learnerIdentity($request,$student,clientMock([responseJson($identity),responseJson($changed)],$h)));
$h=[];$mock=clientMock([responseJson($identity),function () use ($db,$identity) {
    $db->table('students')->where('id',10)->update(['status'=>'Inactive']);return responseJson($identity);
}],$h);
statusDenied(404,fn()=>$bridge->learnerIdentity($request,$student,$mock));
$db->table('students')->where('id',10)->update(['status'=>'Active']);
$request->query->set('date','2026-02-30');
statusDenied(422,fn()=>$bridge->records($request,$http));
$request->query->set('date','2026-10-07');
$path='organisations/11111111-1111-4111-8111-111111111111/attendance';
$h=[];$mock=clientMock([responseJson($context),responseJson(['rows'=>[]]),responseJson($context)],$h);
$proxied=$bridge->gateway($request,$path,$mock);
if ($proxied->headers->get('Cache-Control')!=='no-store, private' && $proxied->headers->get('Cache-Control')!=='no-store') { throw new RuntimeException('Gateway cache policy failed'); }
if (json_decode($proxied->getContent(),true)!==['rows'=>[]]) { throw new RuntimeException('Gateway JSON changed'); }
foreach (['centres','academic-years','classes','groups'] as $resource) {
    $h=[];$mock=clientMock([responseJson($context),responseJson([]),responseJson($context)],$h);
    $bridge->gateway($request,'organisations/11111111-1111-4111-8111-111111111111/'.$resource,$mock);
}
$setupWrite=Illuminate\Http\Request::create('https://two.example.invalid/attendance/api/setup','POST',[],['t4l_session'=>str_repeat('d',64)],[],['CONTENT_TYPE'=>'application/json'],'{"name":"Synthetic year"}');
$setupWrite->setUserResolver(fn()=>Illuminate\Support\Facades\Auth::user());
$h=[];$mock=clientMock([responseJson($context),responseJson(['id'=>'synthetic']),responseJson($context)],$h);
$bridge->gateway($setupWrite,'organisations/11111111-1111-4111-8111-111111111111/academic-years',$mock);
statusDenied(404,fn()=>$bridge->gateway($setupWrite,'organisations/11111111-1111-4111-8111-111111111111/centres/unknown',$http));
statusDenied(404,fn()=>$bridge->gateway($request,'organisations/11111111-1111-4111-8111-111111111111/classes/arbitrary',$http));
statusDenied(405,fn()=>$bridge->gateway($request,'organisations/11111111-1111-4111-8111-111111111111/centres/44444444-4444-4444-8444-444444444444/approve',$http));
statusDenied(404,fn()=>$bridge->gateway($setupWrite,'organisations/11111111-1111-4111-8111-111111111111/centres/44444444-4444-4444-8444-444444444444/delete',$http));
foreach (['learners','learner-fields','learners/44444444-4444-4444-8444-444444444444','learners/44444444-4444-4444-8444-444444444444/photos'] as $resource) {
    $h=[];$mock=clientMock([responseJson($context),responseJson([]),responseJson($context)],$h);
    $bridge->gateway($request,'organisations/11111111-1111-4111-8111-111111111111/'.$resource,$mock);
}
$h=[];$mock=clientMock([responseJson($context),new GuzzleHttp\Psr7\Response(200,['Content-Type'=>'image/jpeg'],'synthetic-learner-image'),responseJson($context)],$h);
$portrait=$bridge->gateway($request,'organisations/11111111-1111-4111-8111-111111111111/learners/44444444-4444-4444-8444-444444444444/photos/55555555-5555-4555-8555-555555555555',$mock);
if ($portrait->getContent()!=='synthetic-learner-image'||!str_contains($portrait->headers->get('Cache-Control'),'no-store')) throw new RuntimeException('Learner photo transport lost privacy');
statusDenied(405,fn()=>$bridge->gateway($request,'organisations/11111111-1111-4111-8111-111111111111/learner-imports/preview',$http));
statusDenied(405,fn()=>$bridge->gateway($setupWrite,'organisations/11111111-1111-4111-8111-111111111111/learners/44444444-4444-4444-8444-444444444444/photos/55555555-5555-4555-8555-555555555555',$http));
statusDenied(404,fn()=>$bridge->gateway($request,'organisations/11111111-1111-4111-8111-111111111111/learners/44444444-4444-4444-8444-444444444444/delete',$http));
$h=[];$mock=clientMock([responseJson($context)],$h);
statusDenied(404,fn()=>$bridge->gateway($request,'organisations/22222222-2222-4222-8222-222222222222/learners',$mock));
statusDenied(404,fn()=>$bridge->gateway($request,'platform/foundation/organisations',$http));
$h=[];$mock=clientMock([responseJson($context)],$h);
statusDenied(404,fn()=>$bridge->gateway($request,str_replace('11111111-1111-4111-8111-111111111111','22222222-2222-4222-8222-222222222222',$path),$mock));
$h=[];$mock=clientMock([responseJson($context),new GuzzleHttp\Psr7\Response(200,['Content-Type'=>'image/jpeg'],'synthetic-image-bytes'),responseJson($context)],$h);
$media=$bridge->gateway($request,$path.'/44444444-4444-4444-8444-444444444444/photo',$mock);
if ($media->getContent()!=='synthetic-image-bytes'||$media->headers->get('Content-Type')!=='image/jpeg') { throw new RuntimeException('Scoped media transport failed'); }
$write=Illuminate\Http\Request::create('https://two.example.invalid/attendance/api/'.$path.'/policy','PATCH',[],['t4l_session'=>str_repeat('d',64)],[],['CONTENT_TYPE'=>'application/json'],json_encode(['version'=>0,'timezone'=>'Asia/Kolkata']));
$write->setUserResolver(fn()=>Illuminate\Support\Facades\Auth::user());
$h=[];$mock=clientMock([responseJson($context),responseJson(['version'=>1]),responseJson($context)],$h);
$bridge->gateway($write,$path.'/policy',$mock);
if ($h[1]['request']->getMethod()!=='PATCH'||$h[1]['request']->getHeaderLine('X-Tech4Learn-Request')!=='1') { throw new RuntimeException('Write transport did not preserve CSRF protocol'); }
$login=Illuminate\Http\Request::create('https://two.example.invalid/login','POST',['login'=>'synthetic@example.invalid','password'=>'synthetic password']);
$app->instance('request',$login);actor(2);
$sessionToken=str_repeat('e',64);
$h=[];$mock=clientMock([new GuzzleHttp\Psr7\Response(200,['set-cookie'=>'t4l_session='.$sessionToken.'; Path=/; HttpOnly'],json_encode(['nativeOrganisationId'=>'2','nativeUserId'=>'2']))],$h);
if ($bridge->authenticate($login,$mock)->id!==2) { throw new RuntimeException('Mapped login picked another native account'); }
$cookie=Illuminate\Support\Facades\Cookie::queued('t4l_session');
if (!$cookie||!$cookie->isHttpOnly()||$cookie->getDomain()!==null||$cookie->getValue()!==$sessionToken) { throw new RuntimeException('API session was not queued safely'); }
$db->table('users')->where('id',2)->update(['status'=>'Suspended']);
Illuminate\Support\Facades\Auth::forgetGuards();
Illuminate\Support\Facades\Auth::shouldUse('web');
App\Support\Tenant::clear();
$h=[];$mock=clientMock([new GuzzleHttp\Psr7\Response(200,['Set-Cookie'=>'t4l_session='.$sessionToken.'; Path=/; HttpOnly'],json_encode(['nativeOrganisationId'=>'2','nativeUserId'=>'2'])),responseJson(['ok'=>true])],$h);
statusDenied(403,fn()=>$bridge->authenticate($login,$mock));
if (count($h)!==2||$h[1]['request']->getUri()->getPath()!=='/api/v1/auth/logout') { throw new RuntimeException('Denied native login did not revoke its API session'); }
$db->table('users')->where('id',2)->update(['status'=>'Active']);
echo "PASS: native attendance mapping context, safe transport, cookie ownership, bounded reads and post-read revocation.\n";

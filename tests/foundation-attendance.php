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
$context = ['nativeOrganisationId'=>'2','nativeUserId'=>'2','organisation'=>['id'=>'11111111-1111-4111-8111-111111111111','name'=>'Synthetic Two'],
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
$request->query->set('date','2026-02-30');
statusDenied(422,fn()=>$bridge->records($request,$http));
echo "PASS: native attendance mapping context, safe transport, cookie ownership, bounded reads and post-read revocation.\n";

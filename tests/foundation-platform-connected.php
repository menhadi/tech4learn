<?php
// Local in-memory native fixture and ephemeral local API only.
require __DIR__.'/foundation-tenancy.php';
$loader->addClassMap([
    'App\Support\AttendanceBridge'=>realpath(__DIR__.'/../platform/app/Support/AttendanceBridge.php'),
    'App\Http\Middleware\VerifyPlatformIdentity'=>realpath(__DIR__.'/../platform/app/Http/Middleware/VerifyPlatformIdentity.php'),
    'App\Http\Controllers\Auth\LoginController'=>realpath(__DIR__.'/../platform/app/Http/Controllers/Auth/LoginController.php'),
]);
$api=getenv('FOUNDATION_TEST_API_URL');
if (!preg_match('#^http://127\.0\.0\.1:[0-9]+/api/v1$#D',$api??''))throw new RuntimeException('Ephemeral local API required');
$db->statement('ALTER TABLE organizations ADD COLUMN settings TEXT');
$db->table('organizations')->where('id',1)->update(['settings'=>'{"is_primary_platform":true}']);
config(['attendance.api_url'=>$api]);
Illuminate\Support\Facades\Auth::forgetGuards();
Illuminate\Support\Facades\Auth::shouldUse('web');
Illuminate\Support\Facades\Cache::flush();App\Support\Tenant::clear();
$login=Illuminate\Http\Request::create('https://one.example.invalid/login','POST',[
    'login'=>'synthetic-platform@example.invalid','password'=>'long synthetic foundation password',
]);
$login->setLaravelSession(app('session')->driver());
$login->setUserResolver(fn()=>Illuminate\Support\Facades\Auth::guard('web')->user());
$app->instance('request',$login);
$controller=app(App\Http\Controllers\Auth\LoginController::class);
if (!(new ReflectionMethod($controller,'attemptLogin'))->invoke($controller,$login))throw new RuntimeException('Connected native platform login failed');
$expected=['nativeOrganisationId'=>'1','nativeUserId'=>'1','userId'=>getenv('FOUNDATION_TEST_PLATFORM_USER'),'version'=>1,'realm'=>'platform'];
if ($login->session()->get('foundation_platform_identity')!==$expected)throw new RuntimeException('Connected platform metadata selected another identity');
$token=Illuminate\Support\Facades\Cookie::queued('t4l_session')->getValue();
$request=Illuminate\Http\Request::create('https://one.example.invalid/exams','GET',[],['t4l_session'=>$token]);
$request->setLaravelSession($login->session());
$request->setUserResolver(fn()=>Illuminate\Support\Facades\Auth::guard('web')->user());
$app->instance('request',$request);
$middleware=new App\Http\Middleware\VerifyPlatformIdentity;
$response=$middleware->handle($request,fn($request)=>(new App\Http\Middleware\CheckPageRights)
    ->handle($request,fn()=>response('Synthetic protected platform page')));
if ($response->getContent()!=='Synthetic protected platform page')throw new RuntimeException('Connected platform page was not authorized');
if ($request->attributes->has('foundation_verified_platform_actor'))throw new RuntimeException('Connected platform permission marker survived the request');
$http=new GuzzleHttp\Client(['http_errors'=>false,'allow_redirects'=>false,'timeout'=>5]);
try {
    $middleware->handle($request,function()use($http,$api,$token){
        $result=$http->patch($api.'/platform/foundation/platforms/1/staff/1',[
            'headers'=>['Origin'=>'https://one.example.invalid','Host'=>'one.example.invalid','Cookie'=>'t4l_session='.$token,'X-Tech4Learn-Request'=>'1'],
            'json'=>['active'=>false,'version'=>1],
        ]);
        if ($result->getStatusCode()!==200)throw new RuntimeException('Synthetic platform revocation failed');
        return response('Private page must not be released');
    });
    throw new RuntimeException('Mid-request platform revocation released private content');
}catch(Symfony\Component\HttpKernel\Exception\HttpException $error){
    if($error->getStatusCode()!==404)throw $error;
}
$bridge=new App\Support\AttendanceBridge;$bridge->signOut($request);
try {
    $bridge->platformIdentity($request,App\Models\User::findOrFail(1));
    throw new RuntimeException('Platform logout did not revoke the API session');
}catch(Symfony\Component\HttpKernel\Exception\HttpException $error){
    if($error->getStatusCode()!==401)throw $error;
}
echo "PASS: connected platform password sign-in, native page rights, private metadata, protected response, mid-request link revocation and logout.\n";

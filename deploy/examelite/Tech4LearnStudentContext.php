<?php
namespace App\Services;
use App\Models\{Organization,Student};
use App\Support\Tenant;
use Illuminate\Http\Request;
use Illuminate\Routing\Redirector;
use Illuminate\Session\{Store,ArraySessionHandler};
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Facade;

final class Tech4LearnStudentContext
{
 public function run(int $tenant,Student $student,array $fields,callable $work):mixed {
  $organisation=Organization::where('status','active')->findOrFail($tenant);
  abort_unless((int)$student->organization_id===$tenant&&$student->status==='Active',403);
  $app=app();$oldRequest=$app['request'];$oldRedirect=$app['redirect'];$hasSession=$app->bound('session');$oldSession=$hasSession?$app['session']:null;
  $guard=Auth::guard('student');$oldStudent=$guard->user();
  $session=new Store('t4l-student',new ArraySessionHandler(5));$session->start();
  $request=Request::create('https://'.$organisation->domain.'/student/exam','POST',$fields);$request->query->add($fields);
  $request->setLaravelSession($session);$request->setUserResolver(fn()=>$student);
  $redirect=new Redirector($app['url']);$redirect->setSession($session);
  $app->instance('request',$request);$app->instance('redirect',$redirect);$app->instance('session',$session);Facade::clearResolvedInstance('session');$guard->setUser($student);Tenant::clear();
  try{abort_unless((int)Tenant::resolve($organisation->domain)->id===$tenant,403);return $work($request,$session);}
  finally{$oldStudent?$guard->setUser($oldStudent):$guard->forgetUser();$app->instance('request',$oldRequest);$app->instance('redirect',$oldRedirect);if($hasSession)$app->instance('session',$oldSession);else $app->forgetInstance('session');Facade::clearResolvedInstance('session');Tenant::clear();$session->invalidate();}
 }
}

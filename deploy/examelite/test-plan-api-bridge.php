<?php
// CLI-only integration transport over synthetic SQLite; no production bootstrap.
if(PHP_SAPI!=='cli')exit(1);
require __DIR__.'/test-native-plan-assignment.php';
require __DIR__.'/Tech4LearnPlanEditor.php';
use Illuminate\Support\Facades\DB;
$app->instance('view',new Illuminate\View\Factory(new Illuminate\View\Engines\EngineResolver(),new Illuminate\View\FileViewFinder(new Illuminate\Filesystem\Filesystem(),[__DIR__]),new Illuminate\Events\Dispatcher($app)));
$app->instance(Illuminate\Contracts\Routing\ResponseFactory::class,new Illuminate\Routing\ResponseFactory($app['view'],$app['redirect']));
$bridgeConfig=tempnam(sys_get_temp_dir(),'t4l-plan-api-');$bridgeToken=bin2hex(random_bytes(32));
file_put_contents($bridgeConfig,json_encode(['_platform'=>['enabled'=>true,'organization_id'=>10,'token_hash'=>hash('sha256',$bridgeToken)]]));
register_shutdown_function(function()use($bridgeConfig){if(is_file($bridgeConfig))unlink($bridgeConfig);});
$app->instance(App\Http\Controllers\Tech4LearnWorkspaceController::class,new class($bridgeConfig) extends App\Http\Controllers\Tech4LearnWorkspaceController {
 public function __construct(private string $file){}protected function configPath():string{return $this->file;}
});
$router=new Illuminate\Routing\Router(new Illuminate\Events\Dispatcher($app),$app);
$app->instance('router',$router);Illuminate\Support\Facades\Route::clearResolvedInstance('router');
$router->prefix('api')->group(function(){require __DIR__.'/tech4learn-routes.php';});
$emit=function(array $data){echo 'PLAN_JSON '.json_encode($data,JSON_THROW_ON_ERROR)."\n";flush();};
$baseline=App\Models\Organization::findOrFail(20)->getRawOriginal();unset($baseline['saas_plan_id'],$baseline['updated_at']);
$shared=App\Models\SaasPlan::orderBy('id')->get()->map(fn($model)=>$model->getRawOriginal())->all();
$emit(['ready'=>true,'org'=>$workspace,'actor'=>$centralActor]);
while(($line=fgets(STDIN))!==false){
 try{
  $command=json_decode($line,true,32,JSON_THROW_ON_ERROR);$path=$command['path']??'';
  if($path==='fixture/facts'){
   $current=App\Models\Organization::findOrFail(20)->getRawOriginal();unset($current['saas_plan_id'],$current['updated_at']);
   $emit(['status'=>200,'data'=>['unchanged_details'=>$current===$baseline,'unchanged_plans'=>App\Models\SaasPlan::whereIn('id',array_column($shared,'id'))->orderBy('id')->get()->map(fn($model)=>$model->getRawOriginal())->all()===$shared,'plan_count'=>App\Models\SaasPlan::count(),'plan_id'=>(int)App\Models\Organization::findOrFail(20)->saas_plan_id,'audits'=>count($GLOBALS['planAudit']??[])]]);continue;
  }
  if(!is_string($path)||(!preg_match('#^central/plans(?:\?after=[0-9]+|/[1-9][0-9]*)?$#D',$path)&&!preg_match('#^workspace/'.preg_quote($workspace,'#').'/(plans(?:\?after=[0-9]+)?|plan)$#D',$path)))throw new RuntimeException('Unsupported fixture path');
  $body=$command['body']??null;
  $request=Illuminate\Http\Request::create('https://central.example.test/api/tech4learn/v1/'.$path,$body===null?'GET':'POST',[],[],[],['HTTP_AUTHORIZATION'=>'Bearer '.$bridgeToken,'CONTENT_TYPE'=>'application/json'],$body===null?null:json_encode($body,JSON_THROW_ON_ERROR));
  $route=$router->getRoutes()->match($request);$route->setContainer($app);$route->flushController();$app->instance('request',$request);
  $reply=$route->run();$emit(['status'=>$reply->getStatusCode(),'data'=>$reply->getData(true)]);
 }catch(Throwable $error){$emit(['status'=>method_exists($error,'getStatusCode')?$error->getStatusCode():500,'failure'=>get_class($error).': '.$error->getMessage()]);}
}

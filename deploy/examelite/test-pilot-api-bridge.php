<?php
// CLI-only synthetic fixture. Never boot the deployed application or its database.
if (PHP_SAPI !== 'cli') exit(1);
require __DIR__.'/test-result-marking.php';
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

DB::table('qtypes')->insert(['id'=>5,'question_type'=>'Subjective','type'=>'S']);
$workspace=$next();$actor=$next();
DB::table('tech4learn_workspaces')->insert(['id'=>$workspace,'source_organization_id'=>10,'organization_id'=>20,'restrictions'=>'[]']);
DB::table('tech4learn_workspace_users')->insert(['workspace_id'=>$workspace,'local_id'=>$actor,'kind'=>'staff','external_id'=>1]);
Carbon::setTestNow(Carbon::parse('2026-09-13 12:00:00','UTC'));
$bridgeConfig=tempnam(sys_get_temp_dir(),'t4l-pilot-api-');
$bridgeToken=bin2hex(random_bytes(32));
file_put_contents($bridgeConfig,json_encode(['_platform'=>['enabled'=>true,'organization_id'=>10,'token_hash'=>hash('sha256',$bridgeToken)]]));
register_shutdown_function(function()use($bridgeConfig){if(is_file($bridgeConfig))unlink($bridgeConfig);});
$app->instance(App\Http\Controllers\Tech4LearnAuthoringController::class,new class($bridgeConfig) extends App\Http\Controllers\Tech4LearnAuthoringController {
    public function __construct(private string $file){} protected function configPath():string{return $this->file;}
});
$app->instance(App\Http\Controllers\Tech4LearnStudentController::class,new class($bridgeConfig) extends App\Http\Controllers\Tech4LearnStudentController {
    public function __construct(private string $file){} protected function configPath():string{return $this->file;}
});
$app->instance(App\Http\Controllers\Tech4LearnResultController::class,new class($bridgeConfig) extends App\Http\Controllers\Tech4LearnResultController {
    public function __construct(private string $file){} protected function configPath():string{return $this->file;}
});
$emit=function(array $data){echo 'PILOT_JSON '.json_encode($data,JSON_THROW_ON_ERROR)."\n";flush();};
$emit(['ready'=>true,'org'=>$workspace,'actor'=>$actor,'group'=>$group->id,'language'=>$language->id]);
while(($line=fgets(STDIN))!==false){
    try {
        $command=json_decode($line,true,32,JSON_THROW_ON_ERROR);
        $path=$command['path']??'';
        if(!is_string($path)||!preg_match('#^(student|results|authoring)/'.preg_quote($workspace,'#').'/#',$path))throw new RuntimeException('Unsupported fixture path');
        $body=$command['body']??null;
        $request=Illuminate\Http\Request::create('https://central.example.test/api/tech4learn/v1/'.$path,$body===null?'GET':'POST',[],[],[],['HTTP_AUTHORIZATION'=>'Bearer '.$bridgeToken,'CONTENT_TYPE'=>'application/json'],$body===null?null:json_encode($body,JSON_THROW_ON_ERROR));
        // Real registered controller/credential checks; HTTP middleware is outside this CLI transport.
        $route=$router->getRoutes()->match($request);
        $route->setContainer($app);
        $route->flushController();
        $app->instance('request',$request);
        $reply=$route->run();
        $emit(['status'=>$reply->getStatusCode(),'data'=>$reply->getData(true)]);
    }catch(Throwable $error){
        $emit(['status'=>method_exists($error,'getStatusCode')?$error->getStatusCode():500,'failure'=>get_class($error).': '.$error->getMessage().' '.$error->getTraceAsString()]);
    }
}
Carbon::setTestNow();

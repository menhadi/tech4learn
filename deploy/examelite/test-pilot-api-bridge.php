<?php
// CLI-only synthetic fixture. Never boot the deployed application or its database.
if (PHP_SAPI !== 'cli') exit(1);
require __DIR__.'/test-result-marking.php';
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

DB::table('qtypes')->insert(['id'=>5,'question_type'=>'Subjective','type'=>'S']);
// Exercise real local-disk image persistence and the protected byte reader.
require_once dirname($argv[3]).'/PassageController.php';
foreach(['index'=>'passages','create'=>'passages/create','edit'=>'passages/{passage}/edit'] as $name=>$path)
 $routes->add((new Illuminate\Routing\Route(['GET'],$path,fn()=>null))->name('passages.'.$name));
$mediaRoot=sys_get_temp_dir().'/t4l-pilot-media-'.bin2hex(random_bytes(12));
mkdir($mediaRoot,0700);
$app['config']->set('filesystems.disks.public',['driver'=>'local','root'=>$mediaRoot,'throw'=>true]);
$app->instance('filesystem',new Illuminate\Filesystem\FilesystemManager($app));
Illuminate\Support\Facades\Storage::clearResolvedInstance('filesystem');
register_shutdown_function(function()use($mediaRoot){
 if(is_dir($mediaRoot)&&(bool)preg_match('#[/\\\\]t4l-pilot-media-[a-f0-9]{24}$#D',$mediaRoot))
  (new Illuminate\Filesystem\Filesystem())->deleteDirectory($mediaRoot);
});
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

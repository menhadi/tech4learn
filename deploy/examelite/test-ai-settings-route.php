<?php
namespace {
require __DIR__.'/test-ai-settings-view.php';
}
namespace App\Models { class Organization extends \Illuminate\Database\Eloquent\Model {protected $guarded=[];} }
namespace App\Support { class Tenant { public static function hostId($host){return $host==='central.example.test'?10:20;} } }
namespace App\Http\Controllers { class Controller extends \Illuminate\Routing\Controller {} }
namespace {
require __DIR__.'/Tech4LearnPlatformController.php';
require __DIR__.'/Tech4LearnWorkspaceController.php';
$capsule=new Illuminate\Database\Capsule\Manager();
$capsule->addConnection(['driver'=>'sqlite','database'=>':memory:']);$capsule->setAsGlobal();$capsule->bootEloquent();
$schema=$capsule->schema();
$schema->create('organizations',function($table){$table->integer('id')->primary();$table->string('status');});
$schema->create('configurations',function($table){$table->increments('id');$table->integer('organization_id');$table->text('openai_api_key')->nullable();$table->text('openai_model')->nullable();$table->text('ai_provider_priority')->nullable();});
$capsule->table('organizations')->insert([['id'=>10,'status'=>'active'],['id'=>20,'status'=>'active']]);
$centralSecret=bin2hex(random_bytes(32));$foreignSecret=bin2hex(random_bytes(32));
$capsule->table('configurations')->insert([
 ['organization_id'=>20,'openai_api_key'=>$foreignSecret,'openai_model'=>'foreign-only'],
 ['organization_id'=>10,'openai_api_key'=>$centralSecret,'openai_model'=>'central-only'],
]);
$token=bin2hex(random_bytes(32));$path=tempnam(sys_get_temp_dir(),'t4l-ai-route-');
file_put_contents($path,json_encode(['_platform'=>['organization_id'=>10,'enabled'=>true,'token_hash'=>hash('sha256',$token)]]));
$controller=new class($path) extends App\Http\Controllers\Tech4LearnWorkspaceController {
 public function __construct(private string $path){}protected function configPath():string{return $this->path;}
 protected function reply(int $tenant,array $data){return ['organization_id'=>$tenant]+$data;}
};
$request=fn($suffix='', $key=null, $host='central.example.test', $content='')=>Illuminate\Http\Request::create('https://'.$host.'/api/tech4learn/v1/central/ai-settings'.$suffix,'GET',[],[],[],['HTTP_AUTHORIZATION'=>'Bearer '.($key??$token)],$content);
try{
 Illuminate\Support\Facades\Facade::setFacadeApplication($app);
 $router=new Illuminate\Routing\Router(new Illuminate\Events\Dispatcher($app),$app);$app->instance('router',$router);
 $router->prefix('api')->group(function(){require __DIR__.'/tech4learn-routes.php';});
 $route=$router->getRoutes()->match($request());
 checkAiView(str_ends_with($route->getActionName(),'Tech4LearnWorkspaceController@centralAiSettings'),'Central read route is registered');
 $view=$controller->centralAiSettings($request());$json=json_encode($view,JSON_THROW_ON_ERROR);
 checkAiView($view['organization_id']===10&&$view['providers'][1]['configured_model']==='central-only','Credential-derived owner selects only its configuration');
 checkAiView(!str_contains($json,$centralSecret)&&!str_contains($json,$foreignSecret)&&!str_contains($json,'foreign-only'),'Route never returns either owner credential or foreign settings');
 denyAiView(fn()=>$controller->centralAiSettings($request('?organization_id=20')),422);
 denyAiView(fn()=>$controller->centralAiSettings($request('',str_repeat('a',64))),401);
 denyAiView(fn()=>$controller->centralAiSettings($request('',null,'foreign.example.test')),403);
 denyAiView(fn()=>$controller->centralAiSettings($request('',null,'central.example.test','{"owner":20}')),422);
 $capsule->table('configurations')->where('organization_id',10)->delete();
 $missing=$controller->centralAiSettings($request());
 checkAiView(!$missing['providers'][1]['credential_saved']&&$missing['providers'][1]['configured_model']===null,'Missing owner settings never fall back to the foreign row');
 checkAiView($capsule->table('configurations')->count()===1,'Read does not create configuration');
 $capsule->table('organizations')->where('id',10)->update(['status'=>'suspended']);
 try{$controller->centralAiSettings($request());throw new RuntimeException('Expected inactive owner denial');}catch(Illuminate\Database\Eloquent\ModelNotFoundException){}
}finally{unlink($path);}
echo "PASS: private central AI-settings route, credential/host checks, exact owner and no fallback or writes.\n";
}

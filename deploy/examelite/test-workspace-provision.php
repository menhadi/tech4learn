<?php
namespace {require $argv[1];}
namespace App\Http\Controllers {
 class Tech4LearnPlatformController {
  protected function configuration($r){return ['_platform'=>['organization_id'=>10]];}
  protected function uuid($id){if(!preg_match('/^[a-f0-9-]{36}$/D',$id))throw new \RuntimeException('UUID',422);}
  protected function reply($tenant,$data){return $data;}
 }
 function abort_unless($ok,$code,$message=''){if(!$ok)throw new \RuntimeException($message,$code);}
}
namespace {
require __DIR__.'/Tech4LearnWorkspacePolicy.php';require __DIR__.'/Tech4LearnWorkspaceController.php';
use Illuminate\Support\Facades\DB;
$db=new Illuminate\Database\Capsule\Manager();$db->addConnection(['driver'=>'sqlite','database'=>':memory:']);$db->setAsGlobal();$db->bootEloquent();
$container=$db->getContainer();$container->instance('db',$db->getDatabaseManager());
$container->instance('config',new Illuminate\Config\Repository(['hashing'=>['driver'=>'bcrypt','bcrypt'=>['rounds'=>4]],'permission'=>['teams'=>false],'database'=>['default'=>'default','connections'=>['default'=>['driver'=>'sqlite','database'=>':memory:']]]]));
$container->instance('hash',new Illuminate\Hashing\HashManager($container));
Illuminate\Container\Container::setInstance($container);Illuminate\Support\Facades\Facade::setFacadeApplication($container);
$tables=[
 'tech4learn_workspaces'=>['id TEXT PRIMARY KEY','organization_id INTEGER','source_organization_id INTEGER','revision INTEGER','restrictions TEXT'],
 'tech4learn_workspace_users'=>['workspace_id TEXT','local_id TEXT','kind TEXT','external_id INTEGER'],
 'tech4learn_workspace_tickets'=>['hash TEXT','workspace_id TEXT','kind TEXT','external_id INTEGER','name TEXT','entry TEXT','expires_at TEXT'],
 'organizations'=>['id INTEGER PRIMARY KEY AUTOINCREMENT','saas_plan_id INTEGER','name TEXT','slug TEXT','domain TEXT','subdomain TEXT','status TEXT','settings TEXT'],
 'saas_plans'=>['id INTEGER PRIMARY KEY AUTOINCREMENT','name TEXT','slug TEXT','price TEXT','billing_cycle TEXT','limits TEXT','features TEXT','is_default INTEGER','status INTEGER'],
 'configurations'=>['id INTEGER PRIMARY KEY AUTOINCREMENT','organization_id INTEGER','name TEXT','organization_name TEXT','domain_name TEXT','timezone TEXT','email TEXT'],
 'users'=>['id INTEGER PRIMARY KEY AUTOINCREMENT','name TEXT','username TEXT','email TEXT','password TEXT','ugroup_id INTEGER','status INTEGER','is_platform_admin INTEGER'],
 'organization_users'=>['organization_id INTEGER','user_id INTEGER','role TEXT','status INTEGER'],
 'students'=>['id INTEGER PRIMARY KEY AUTOINCREMENT','organization_id INTEGER','name TEXT','email TEXT','phone TEXT','password TEXT','status TEXT'],
 'groups'=>['id INTEGER PRIMARY KEY AUTOINCREMENT','organization_id INTEGER','group_name TEXT'],
 'student_groups'=>['student_id INTEGER','group_id INTEGER'],
];
$tables['configurations']=array_merge($tables['configurations'],array_map(fn($c)=>$c.' TEXT',['theme_primary_color','theme_secondary_color','theme_header_bg','theme_header_text','theme_body_bg','theme_heading_color','theme_button_text','math_editor','translate','exam_feedback','date_format']));
foreach($tables as $name=>$columns)$db->getConnection()->statement('CREATE TABLE '.$name.' ('.implode(',',$columns).', created_at TEXT,updated_at TEXT)');
DB::table('organizations')->insert(['id'=>10,'name'=>'Master']);
DB::table('configurations')->insert(['organization_id'=>10,'name'=>'Master','email'=>'private@example.invalid','timezone'=>'UTC']);
$controller=new App\Http\Controllers\Tech4LearnWorkspaceController();
$org='11111111-1111-4111-8111-111111111111';$actor='22222222-2222-4222-8222-222222222222';$learner='33333333-3333-4333-8333-333333333333';
$payload=['actor_id'=>$actor,'actor_name'=>'Test admin','organisation_name'=>'Test organisation','feature'=>'exams','restrictions'=>[],'revision'=>0,'provision_only'=>true];
$request=fn($values)=>Illuminate\Http\Request::create('/','POST',$values);
function check($ok,$message){if(!$ok)throw new RuntimeException($message);}
try{$controller->launch($request(array_replace($payload,['provision_only'=>false])),$org);throw new RuntimeException('Expected retired launch denial');}catch(RuntimeException $e){if($e->getCode()!==410)throw $e;}
check(DB::table('tech4learn_workspaces')->count()===0,'Retired launch has no provisioning side effects');
$result=$controller->launch($request($payload),$org);
$workspace=DB::table('tech4learn_workspaces')->where('id',$org)->first();
check($workspace->organization_id!==10 && $result===['ready'=>true] && DB::table('tech4learn_workspace_tickets')->count()===0,'Isolated tenant without browser ticket');
$user=DB::table('users')->first();
check(!(bool)$user->is_platform_admin && DB::table('organization_users')->value('organization_id')===$workspace->organization_id,'Native owner has no global admin privilege');
check(DB::table('configurations')->where('organization_id',$workspace->organization_id)->value('email')===null,'Private source configuration excluded');
$controller->launch($request($payload),$org);
check(DB::table('users')->count()===1 && DB::table('organizations')->count()===2,'Retry reuses identity and organisation');
$tickets=DB::table('tech4learn_workspace_tickets')->count();
$provisioned=$controller->launch($request(array_replace($payload,['provision_only'=>true])),$org);
check($provisioned===['ready'=>true] && DB::table('tech4learn_workspace_tickets')->count()===$tickets,'Content provisioning never issues a browser launch ticket');
DB::table('groups')->insert([['organization_id'=>$workspace->organization_id,'group_name'=>'Own'],['organization_id'=>10,'group_name'=>'Master']]);
$studentPayload=array_replace($payload,['feature'=>'taking','learner'=>['id'=>$learner,'name'=>'Sample learner']]);
$controller->launch($request($studentPayload),$org);
$student=DB::table('students')->first();
check($student->organization_id===$workspace->organization_id && $student->email===null && DB::table('student_groups')->count()===0,'Minimal student identity without all-group access');
$controller->restrict($request(['restrictions'=>['taking'],'revision'=>1]),$org);
try {$controller->launch($request(array_replace($studentPayload,['restrictions'=>['taking'],'revision'=>1])),$org);throw new RuntimeException('Expected restriction');}
catch(RuntimeException $e){if($e->getCode()!==403)throw $e;}
echo "Workspace provision: separate organisation, no global role, minimal identity, safe retry and restrictions passed.\n";
DB::table('organizations')->where('id',10)->update(['status'=>'active']);
$read=fn()=>$controller->capabilities(Illuminate\Http\Request::create('/','GET'),$org);
$catalogue=$read();
check(array_keys($catalogue)===['revision','native_features','workspace_features'],'Catalogue contains only revision and bounded feature flags');
check(count($catalogue['native_features'])>0&&array_filter($catalogue['native_features'],fn($item)=>$item['enabled']!==true)===[],'Native default workspace plan enables its known capabilities');
check(array_column($catalogue['workspace_features'],'enabled','key')['taking']===false&&$catalogue['revision']===1,'Workspace restrictions are separate from native plan entitlements');
$plan=App\Models\SaasPlan::findOrFail(DB::table('organizations')->where('id',$workspace->organization_id)->value('saas_plan_id'));
$features=$plan->features;$first=array_key_first($features);$features[$first]=false;$features['private_unknown_feature']=true;$plan->features=$features;$plan->save();
$flags=array_column($read()['native_features'],'enabled','key');
check($flags[$first]===false&&!array_key_exists('private_unknown_feature',$flags),'Catalogue uses native entitlement evaluation and omits unknown plan attributes');
DB::statement('ALTER TABLE organizations ADD COLUMN subscription_ends_at TEXT');
DB::table('organizations')->where('id',$workspace->organization_id)->update(['subscription_ends_at'=>'2000-01-01 00:00:00']);
check(array_filter($read()['native_features'],fn($item)=>$item['enabled'])===[],'Native expired subscription denies plan features');
DB::table('organizations')->where('id',$workspace->organization_id)->update(['subscription_ends_at'=>null]);
$deny=function(callable $operation){try{$operation();throw new LogicException('Expected catalogue denial');}catch(RuntimeException $error){if(!in_array($error->getCode(),[403,404,422],true))throw $error;}};
$deny(fn()=>$controller->capabilities(Illuminate\Http\Request::create('/?organization_id=10','GET'),$org));
DB::table('tech4learn_workspaces')->where('id',$org)->update(['source_organization_id'=>999]);$deny($read);
DB::table('tech4learn_workspaces')->where('id',$org)->update(['source_organization_id'=>10,'organization_id'=>10]);$deny($read);
DB::table('tech4learn_workspaces')->where('id',$org)->update(['organization_id'=>$workspace->organization_id]);
DB::table('organizations')->where('id',$workspace->organization_id)->update(['settings'=>'{}']);$deny($read);
DB::table('organizations')->where('id',$workspace->organization_id)->update(['settings'=>json_encode(['tech4learn_workspace'=>$org])]);
check(DB::table('tech4learn_workspace_tickets')->count()===$tickets,'Capability reads never create launch credentials');
$router=new Illuminate\Routing\Router(new Illuminate\Events\Dispatcher($container),$container);
$container->instance('router',$router);Illuminate\Support\Facades\Route::clearResolvedInstance('router');
$router->prefix('api')->group(function(){require __DIR__.'/tech4learn-routes.php';});
$route=$router->getRoutes()->match(Illuminate\Http\Request::create('https://example.test/api/tech4learn/v1/workspace/'.$org.'/capabilities','GET'));
check(str_ends_with($route->getActionName(),'Tech4LearnWorkspaceController@capabilities'),'Registered capability route resolves to scoped native reader');
echo "Native capability catalogue: native plan flags, separate restrictions, bounded output, owner isolation and no provisioning passed.\n";
$planRead=fn(string $query='')=>$controller->plans(Illuminate\Http\Request::create('/'.$query,'GET'),$org);
$firstPlanPage=$planRead();
check(count($firstPlanPage['items'])===1&&$firstPlanPage['items'][0]['selected']===true&&$firstPlanPage['next']===null,'Current native plan is marked without modifying assignment');
check(array_keys($firstPlanPage['items'][0])===['id','name','selected','revision'],'Plan options exclude prices, configuration and feature payloads');
check($firstPlanPage['assignment_revision']===hash('sha256',json_encode(App\Models\Organization::findOrFail($workspace->organization_id)->getRawOriginal(),JSON_THROW_ON_ERROR)),'Plan reader supplies the exact current organisation assignment revision');
$oldPlanRevision=$firstPlanPage['items'][0]['revision'];
$plan->name='Renamed synthetic plan';$plan->save();
check($planRead()['items'][0]['revision']!==$oldPlanRevision,'Plan edit invalidates option revision');
for($i=0;$i<55;$i++)App\Models\SaasPlan::create(['name'=>'Synthetic choice '.$i,'slug'=>'synthetic-choice-'.$i,'price'=>0,'billing_cycle'=>'monthly','features'=>[],'limits'=>[],'is_default'=>false,'status'=>true]);
App\Models\SaasPlan::create(['name'=>'Hidden inactive choice','slug'=>'synthetic-disabled','price'=>0,'billing_cycle'=>'monthly','features'=>[],'limits'=>[],'is_default'=>false,'status'=>false]);
$one=$planRead();$two=$planRead('?after='.$one['next']);
check(count($one['items'])===50&&count($two['items'])===6&&$two['next']===null,'Active native plan options have bounded cursor pages');
check(count(array_unique(array_column(array_merge($one['items'],$two['items']),'id')))===56,'Plan pages do not repeat or include inactive records');
foreach(['?after=-1','?after=01','?after=1e2','?after[]=1','?owner=10'] as $query)$deny(fn()=>$planRead($query));
DB::table('tech4learn_workspaces')->where('id',$org)->update(['source_organization_id'=>999]);$deny($planRead);
DB::table('tech4learn_workspaces')->where('id',$org)->update(['source_organization_id'=>10]);
$route=$router->getRoutes()->match(Illuminate\Http\Request::create('https://example.test/api/tech4learn/v1/workspace/'.$org.'/plans','GET'));
check(str_ends_with($route->getActionName(),'Tech4LearnWorkspaceController@plans'),'Plan catalogue route resolves to native scoped reader');
$route=$router->getRoutes()->match(Illuminate\Http\Request::create('https://example.test/api/tech4learn/v1/workspace/'.$org.'/plan','POST'));
check(str_ends_with($route->getActionName(),'Tech4LearnWorkspaceController@assignPlan'),'Plan write route resolves to private credential controller');
check((int)DB::table('organizations')->where('id',$workspace->organization_id)->value('saas_plan_id')===(int)$plan->id,'Reading plans never changes an organisation subscription');
echo "Native plan options: current assignment, bounded pages, active-only records, revision changes and owner checks passed.\n";
}

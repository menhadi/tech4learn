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
$payload=['actor_id'=>$actor,'actor_name'=>'Test admin','organisation_name'=>'Test organisation','feature'=>'exams','restrictions'=>[],'revision'=>0];
$request=fn($values)=>Illuminate\Http\Request::create('/','POST',$values);
function check($ok,$message){if(!$ok)throw new RuntimeException($message);}
$result=$controller->launch($request($payload),$org);
$workspace=DB::table('tech4learn_workspaces')->where('id',$org)->first();
check($workspace->organization_id!==10 && preg_match('/^[a-f0-9]{64}$/D',$result['ticket']),'Isolated tenant and one-time link');
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
check($student->organization_id===$workspace->organization_id && $student->email===null && DB::table('student_groups')->count()===1,'Minimal student identity and only own groups');
$controller->restrict($request(['restrictions'=>['taking'],'revision'=>1]),$org);
try {$controller->launch($request(array_replace($studentPayload,['restrictions'=>['taking'],'revision'=>1])),$org);throw new RuntimeException('Expected restriction');}
catch(RuntimeException $e){if($e->getCode()!==403)throw $e;}
echo "Workspace provision: separate organisation, no global role, minimal identity, safe retry and restrictions passed.\n";
}

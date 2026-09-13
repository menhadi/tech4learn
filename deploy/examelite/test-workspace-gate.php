<?php
namespace {require $argv[1];}
namespace App\Support {class Tenant {static function id(){return 1;}}}
namespace App\Http\Middleware {
 function abort_unless($condition,$code,$message=''){if(!$condition)throw new \RuntimeException($message,$code);}
 function config($values){$GLOBALS['workspace_config']=$values;}
}
namespace {
require __DIR__.'/Tech4LearnWorkspacePolicy.php';require __DIR__.'/Tech4LearnWorkspaceContext.php';require __DIR__.'/Tech4LearnWorkspaceGate.php';
use Illuminate\Support\Facades\DB;
$db=new Illuminate\Database\Capsule\Manager();$db->addConnection(['driver'=>'sqlite','database'=>':memory:']);$db->setAsGlobal();
$db->getContainer()->instance('db',$db->getDatabaseManager());
$auth=new class {public int $id=11;public function guard($name){return new class($this){function __construct(private $manager){}function id(){return $this->manager->id;}function user(){return(object)['id'=>$this->id(),'organization_id'=>1];}};}};
$db->getContainer()->instance('auth',$auth);Illuminate\Support\Facades\Facade::setFacadeApplication($db->getContainer());
$org='11111111-1111-4111-8111-111111111111';
$db->getConnection()->getPdo()->exec("CREATE TABLE tech4learn_workspaces(id TEXT PRIMARY KEY,organization_id INTEGER,restrictions TEXT);
 CREATE TABLE tech4learn_workspace_users(workspace_id TEXT,kind TEXT,external_id INTEGER);
 CREATE TABLE organization_users(organization_id INTEGER,user_id INTEGER,status INTEGER);
 INSERT INTO tech4learn_workspaces VALUES('$org',1,'[]');
 INSERT INTO tech4learn_workspace_users VALUES('$org','staff',11),('$org','student',12);
 INSERT INTO organization_users VALUES(1,11,1);");
$context=new App\Http\Middleware\Tech4LearnWorkspaceContext();$gate=new App\Http\Middleware\Tech4LearnWorkspaceGate();
$request=function($path,$session=null)use($org,$context){$r=Illuminate\Http\Request::create('https://t4l-'.str_replace('-','',$org).'.examelite.com/'.$path);$s=new Illuminate\Session\Store('test',new Illuminate\Session\ArraySessionHandler(120));if($session)$s->put('tech4learn_workspace',$session);$r->setLaravelSession($s);$context->handle($r,fn()=>null);return $r;};
$staff=['id'=>$org,'kind'=>'staff','user'=>11,'expires'=>time()+60];
function check($value,$message){if(!$value)throw new RuntimeException($message);}
function denied($code,$fn){try{$fn();}catch(RuntimeException $e){if($e->getCode()===$code)return;throw $e;}throw new RuntimeException('Expected denial');}
$r=$request('questions',$staff);
check($GLOBALS['workspace_config']['session.domain']===null&&$GLOBALS['workspace_config']['session.cookie']==='__Host-t4l_workspace'&&$GLOBALS['workspace_config']['session.secure']===true,'Host-only secure session');
check($gate->handle($r,fn()=>true)===true,'Staff owned workspace');
denied(401,fn()=>$gate->handle($request('questions'),fn()=>true));
denied(401,fn()=>$gate->handle($request('questions',array_replace($staff,['id'=>'other'])),fn()=>true));
denied(401,fn()=>$gate->handle($request('questions',array_replace($staff,['expires'=>time()-1])),fn()=>true));
denied(403,fn()=>$gate->handle($request('saas/organizations',$staff),fn()=>true));
DB::table('tech4learn_workspaces')->where('id',$org)->update(['restrictions'=>'["questions","results"]']);
denied(403,fn()=>$gate->handle($request('questions',$staff),fn()=>true));
denied(403,fn()=>$gate->handle($request('exams/1/analytics',$staff),fn()=>true));
check($gate->handle($request('exams',$staff),fn()=>true)===true,'Other allowed feature');
$student=['id'=>$org,'kind'=>'student','user'=>12,'expires'=>time()+60];$auth->id=12;
check($gate->handle($request('student/dashboard',$student),fn()=>true)===true,'Student exam portal');
denied(403,fn()=>$gate->handle($request('questions/create',$student),fn()=>true));
unset($GLOBALS['workspace_config']);$context->handle(Illuminate\Http\Request::create('https://examelite.com/'),fn()=>true);
check(!isset($GLOBALS['workspace_config']),'Existing ExamElite session configuration unchanged');
denied(404,fn()=>$context->handle(Illuminate\Http\Request::create('https://t4l-invalid.examelite.com/'),fn()=>true));
echo "Workspace gate: host cookies, tenant/identity binding, expiry, restrictions and student/staff separation passed.\n";
}

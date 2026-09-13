<?php
require $argv[1];
require __DIR__.'/Tech4LearnWorkspacePolicy.php';
require __DIR__.'/Tech4LearnLaunchTickets.php';
use Illuminate\Support\Facades\DB;
$db=new Illuminate\Database\Capsule\Manager();$db->addConnection(['driver'=>'sqlite','database'=>':memory:']);$db->setAsGlobal();
$db->getContainer()->instance('db',$db->getDatabaseManager());Illuminate\Support\Facades\Facade::setFacadeApplication($db->getContainer());
$db->getConnection()->getPdo()->exec("CREATE TABLE tech4learn_workspaces(id TEXT PRIMARY KEY,organization_id INTEGER,restrictions TEXT);
 CREATE TABLE tech4learn_workspace_users(workspace_id TEXT,kind TEXT,external_id INTEGER);
 CREATE TABLE tech4learn_workspace_tickets(hash TEXT PRIMARY KEY,workspace_id TEXT,kind TEXT,external_id INTEGER,entry TEXT,expires_at TEXT,used_at TEXT);
 INSERT INTO tech4learn_workspaces VALUES('own',1,'[]'),('other',2,'[]');
 INSERT INTO tech4learn_workspace_users VALUES('own','staff',11),('own','student',12);");
$w=(object)['id'=>'own','organization_id'=>1];$service=new App\Services\Tech4LearnLaunchTickets();
function denied($code,$fn){try{$fn();}catch(DomainException $e){if($e->getCode()===$code)return;throw $e;}throw new RuntimeException('Expected denial');}
$mint=function($changes=[]){$token=bin2hex(random_bytes(32));DB::table('tech4learn_workspace_tickets')->insert(array_replace(['hash'=>hash('sha256',$token),'workspace_id'=>'own','kind'=>'staff','external_id'=>11,'entry'=>'exams','expires_at'=>date('Y-m-d H:i:s',time()+120),'used_at'=>null],$changes));return $token;};
$token=$mint();denied(410,fn()=>$service->consume((object)['id'=>'other','organization_id'=>2],$token));
$ticket=$service->consume($w,$token);if($ticket->external_id!==11)throw new RuntimeException('Wrong identity');
denied(410,fn()=>$service->consume($w,$token));
denied(410,fn()=>$service->consume($w,$mint(['expires_at'=>date('Y-m-d H:i:s',time()-1)])));
denied(422,fn()=>$service->consume($w,'malformed'));
denied(403,fn()=>$service->consume($w,$mint(['entry'=>'https://evil.example'])));
denied(403,fn()=>$service->consume($w,$mint(['external_id'=>99])));
denied(403,fn()=>$service->consume($w,$mint(['kind'=>'student','external_id'=>12])));
$pending=$mint();DB::table('tech4learn_workspaces')->where('id','own')->update(['restrictions'=>'["exams"]']);
denied(403,fn()=>$service->consume($w,$pending));
if(DB::table('tech4learn_workspace_tickets')->where('hash',hash('sha256',$pending))->value('used_at')!==null)throw new RuntimeException('Failed ticket consumed');
echo "Launch tickets: expiry, replay, tenant/identity binding, fixed destinations and restriction changes passed.\n";

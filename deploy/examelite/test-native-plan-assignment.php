<?php
namespace App\Http\Controllers { function audit_log(...$args){if($GLOBALS['failPlanAudit']??false)throw new \RuntimeException('Synthetic native audit failure');$GLOBALS['planAudit'][]=$args[0];} }
namespace {
require __DIR__.'/test-central-question-authoring.php';
require $argv[4]??'/home/examelite/public_html/app/Http/Controllers/SaasController.php';
require __DIR__.'/Tech4LearnPlanAssignment.php';
use Illuminate\Support\Facades\DB;
use App\Models\{Organization,SaasPlan};
use App\Services\Tech4LearnPlanAssignment;
foreach(['saas_plan_id','name','slug','subdomain','email','phone','trial_ends_at','subscription_ends_at','settings','created_at','updated_at'] as $field)
 if(!DB::getSchemaBuilder()->hasColumn('organizations',$field))DB::statement('ALTER TABLE organizations ADD COLUMN '.$field.' TEXT');
DB::statement('CREATE TABLE saas_plans(id INTEGER PRIMARY KEY AUTOINCREMENT,name TEXT,slug TEXT,price TEXT,billing_cycle TEXT,limits TEXT,features TEXT,is_default INTEGER,status INTEGER,created_at TEXT,updated_at TEXT)');
DB::table('organizations')->where('id',20)->update(['name'=>'Synthetic owner','slug'=>'synthetic-owner','settings'=>json_encode(['tech4learn_workspace'=>$workspace]),'subscription_ends_at'=>'2030-01-01 00:00:00']);
$plan=SaasPlan::create(['name'=>'Synthetic assignment','slug'=>'synthetic-assignment','price'=>0,'billing_cycle'=>'monthly','features'=>['reports'=>true],'limits'=>[],'status'=>true,'is_default'=>false]);
$plan->refresh();
$helper=new Tech4LearnPlanAssignment();$owner=Organization::findOrFail(20);$before=$owner->getRawOriginal();$planBefore=$plan->getRawOriginal();
$session=new Illuminate\Session\Store('native-plan-test',new Illuminate\Session\ArraySessionHandler(120));
$request=Illuminate\Http\Request::create('/','POST',['name'=>'Injected name','organization_id'=>30,'status'=>'suspended']);$request->setLaravelSession($session);
$routes->add((new Illuminate\Routing\Route(['GET'],'saas',fn()=>null))->name('saas.index'));
$oldRedirect=app('redirect');$redirect=new Illuminate\Routing\Redirector(app('url'));$redirect->setSession($session);app()->instance('redirect',$redirect);
$apply=fn($ownerRevision,$planRevision)=>$helper->apply(10,$workspace,$plan->id,$ownerRevision,$planRevision,$request);
try{
 $reject(fn()=>$apply(str_repeat('0',64),Tech4LearnPlanAssignment::revision($plan)));
 $reject(fn()=>$apply(Tech4LearnPlanAssignment::revision($owner),str_repeat('0',64)));
 $result=$apply(Tech4LearnPlanAssignment::revision($owner),Tech4LearnPlanAssignment::revision($plan));
 check($result['plan_id']===$plan->id&&(int)$owner->fresh()->saas_plan_id===$plan->id,'Native organisation controller saves plan assignment');
 $after=$owner->fresh()->getRawOriginal();unset($before['saas_plan_id'],$before['updated_at'],$after['saas_plan_id'],$after['updated_at']);
 check($before===$after&&$plan->fresh()->getRawOriginal()===$planBefore,'Assignment preserves organisation details, expiry and shared plan');
 check(in_array('organization.updated',$GLOBALS['planAudit']??[],true),'Native organisation audit remains invoked');
 $replacement=SaasPlan::create(['name'=>'Rollback choice','slug'=>'rollback-choice','price'=>0,'billing_cycle'=>'monthly','features'=>[],'limits'=>[],'status'=>true,'is_default'=>false]);$replacement->refresh();
 $GLOBALS['failPlanAudit']=true;
 try{$helper->apply(10,$workspace,$replacement->id,Tech4LearnPlanAssignment::revision($owner->fresh()),Tech4LearnPlanAssignment::revision($replacement),$request);throw new LogicException('Expected native failure');}
 catch(RuntimeException $error){check($error->getMessage()==='Synthetic native audit failure','Native failure reaches caller');}
 finally{$GLOBALS['failPlanAudit']=false;}
 check((int)$owner->fresh()->saas_plan_id===$plan->id,'Native failure rolls back plan assignment');
 $plan->status=false;$plan->save();$reject(fn()=>$apply(Tech4LearnPlanAssignment::revision($owner->fresh()),Tech4LearnPlanAssignment::revision($plan)));
 $plan->status=true;$plan->save();
 DB::table('tech4learn_workspaces')->where('id',$workspace)->update(['source_organization_id'=>30]);
 $reject(fn()=>$apply(Tech4LearnPlanAssignment::revision($owner->fresh()),Tech4LearnPlanAssignment::revision($plan)));
}finally{app()->instance('redirect',$oldRedirect);}
DB::table('tech4learn_workspaces')->where('id',$workspace)->update(['source_organization_id'=>10]);
DB::table('tech4learn_central_users')->insert(['organization_id'=>10,'local_id'=>$centralActor,'external_id'=>$nativeId]);
$assignmentId=$nextId();$ownerRevision=Tech4LearnPlanAssignment::revision($owner->fresh());$planRevision=Tech4LearnPlanAssignment::revision($replacement->fresh());
$assign=fn($id,$actor=null,$choice=null)=>$helper->assign(10,$workspace,$actor??$centralActor,$choice??$replacement->id,$ownerRevision,$planRevision,$id);
$requestBefore=app('request');$redirectBefore=app('redirect');$guard=Illuminate\Support\Facades\Auth::guard('web');$userBefore=$guard->user();
$receipt=$assign($assignmentId);$audits=count($GLOBALS['planAudit']);
check($receipt['plan_id']===$replacement->id&&$assign($assignmentId)===$receipt&&count($GLOBALS['planAudit'])===$audits,'Assignment receipt replays without a second native write');
check(app('request')===$requestBefore&&app('redirect')===$redirectBefore&&$guard->user()===$userBefore,'Assignment restores caller context');
$reject(fn()=>$assign($assignmentId,$centralActor,$plan->id));
$reject(fn()=>$assign($nextId()));
$reject(fn()=>$assign($nextId(),'dddddddd-1111-1111-1111-111111111111'));
$centralNativeId=DB::table('tech4learn_central_users')->where('local_id',$centralActor)->value('external_id');
DB::table('organization_users')->where('organization_id',10)->where('user_id',$centralNativeId)->update(['status'=>0]);
$reject(fn()=>$assign($assignmentId));
DB::table('organization_users')->where('organization_id',10)->where('user_id',$centralNativeId)->update(['status'=>1,'role'=>'member']);
$reject(fn()=>$assign($assignmentId));
DB::table('organization_users')->where('organization_id',10)->where('user_id',$centralNativeId)->update(['role'=>'owner']);
DB::table('tech4learn_workspaces')->where('id',$workspace)->update(['source_organization_id'=>30]);$reject(fn()=>$assign($assignmentId));
DB::table('tech4learn_workspaces')->where('id',$workspace)->update(['source_organization_id'=>10]);
$ledgerBefore=DB::table('tech4learn_central_requests')->count();$GLOBALS['failPlanAudit']=true;
try{$helper->assign(10,$workspace,$centralActor,$plan->id,Tech4LearnPlanAssignment::revision($owner->fresh()),Tech4LearnPlanAssignment::revision($plan->fresh()),$nextId());throw new LogicException('Expected audit failure');}
catch(RuntimeException $error){check($error->getMessage()==='Synthetic native audit failure','Assignment audit failure propagates');}
finally{$GLOBALS['failPlanAudit']=false;}
check(DB::table('tech4learn_central_requests')->count()===$ledgerBefore&&(int)$owner->fresh()->saas_plan_id===$replacement->id,'Failed assignment rolls back both native change and receipt');
check(app('request')===$requestBefore&&app('redirect')===$redirectBefore&&$guard->user()===$userBefore,'Failed assignment restores caller context');
require __DIR__.'/Tech4LearnWorkspaceController.php';
$planController=new class extends App\Http\Controllers\Tech4LearnWorkspaceController {
 protected function configuration(Illuminate\Http\Request $r):array {return ['_platform'=>['organization_id'=>10]];}
 protected function reply(int $tenant,array $data){return $data;}
};
$newActor='eeeeeeee-1111-1111-1111-111111111111';
$planBody=['actor_id'=>$newActor,'request_id'=>$nextId(),'plan_id'=>$plan->id,'assignment_revision'=>Tech4LearnPlanAssignment::revision($owner->fresh()),'plan_revision'=>Tech4LearnPlanAssignment::revision($plan->fresh())];
$callPlan=fn($body)=>$planController->assignPlan(Illuminate\Http\Request::create('/','POST',$body),$workspace);
foreach(['organization_id'=>30,'price'=>99,'status'=>'suspended'] as $key=>$value)$reject(fn()=>$callPlan($planBody+[$key=>$value]));
foreach(['plan_id'=>'1','actor_id'=>[],'assignment_revision'=>'new','plan_revision'=>null,'request_id'=>'bad'] as $key=>$value)$reject(fn()=>$callPlan(array_replace($planBody,[$key=>$value])));
$reject(fn()=>$planController->assignPlan(Illuminate\Http\Request::create('/?owner=30','POST',$planBody),$workspace));
$created=$callPlan($planBody);check($created['saved']&&$created['plan_id']===$plan->id&&$callPlan($planBody)===$created,'Private plan controller saves and replays a bounded request');
check($callPlan(array_replace($planBody,['request_id'=>$nextId()]))===['saved'=>false,'conflict'=>true],'Stale assignment is a bounded conflict response for the gateway');
$newNativeId=DB::table('tech4learn_central_users')->where('local_id',$newActor)->value('external_id');
check($newNativeId!==null&&!App\Models\User::findOrFail($newNativeId)->is_platform_admin&&DB::table('organization_users')->where('user_id',$newNativeId)->count()===1,'First plan assignment provisions only a central scoped author');
DB::table('tech4learn_central_users')->where('local_id',$newActor)->delete();$reject(fn()=>$callPlan($planBody));
echo "Private plan controller: bounded input, first-use isolated actor and revoked mapping replay passed.\n";
echo "Central plan assignment: receipts, revocation, scope, rollback and caller context passed.\n";
echo "Native plan assignment helper: native validation/persistence, audit, scope, revisions and unchanged shared plan passed.\n";
}

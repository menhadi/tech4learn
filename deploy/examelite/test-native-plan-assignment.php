<?php
namespace App\Http\Controllers { function audit_log(...$args){if($GLOBALS['failPlanAudit']??false)throw new \RuntimeException('Synthetic native audit failure');$GLOBALS['planAudit'][]=$args[0];} }
namespace {
require __DIR__.'/test-central-question-authoring.php';
if(isset($argv[4]))require $argv[4];
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
echo "Native plan assignment helper: native validation/persistence, audit, scope, revisions and unchanged shared plan passed.\n";
}

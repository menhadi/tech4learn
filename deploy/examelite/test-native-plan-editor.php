<?php
require __DIR__.'/test-native-plan-assignment.php';
require __DIR__.'/Tech4LearnPlanEditor.php';
use Illuminate\Support\Facades\DB;
use App\Models\SaasPlan;
use App\Services\{Tech4LearnPlanEditor,Tech4LearnPlanAssignment};

$editor=new Tech4LearnPlanEditor();
$session=new Illuminate\Session\Store('plan-editor-test',new Illuminate\Session\ArraySessionHandler(5));$session->start();
$request=Illuminate\Http\Request::create('/','POST');$request->setLaravelSession($session);
$redirect=new Illuminate\Routing\Redirector(app('url'));$redirect->setSession($session);
$oldRedirect=app('redirect');app()->instance('redirect',$redirect);
$beforePlans=SaasPlan::orderBy('id')->get()->map(fn($p)=>$p->getRawOriginal())->all();
$beforeOwners=DB::table('organizations')->orderBy('id')->get()->toJson();
try {
    $result=$editor->create(10,['name'=>'Synthetic full capability plan'],$request);
    $created=SaasPlan::findOrFail($result['plan_id']);
    check($result['revision']===Tech4LearnPlanAssignment::revision($created),'Created plan carries a current opaque revision');
    check(count($created->features)===count(Tech4LearnPlanEditor::FEATURES)&&!in_array(false,$created->features,true),'Native creation enables all known capabilities by default');
    check(!$created->is_default&&$created->status&&$created->price==='0.00','Native defaults do not change the global default or charge anyone');
    check(in_array('saas_plan.created',$GLOBALS['planAudit']??[],true),'Native creation audit invoked');
    $second=$editor->create(10,['name'=>'Synthetic full capability plan','feature_ai_translation'=>false,'limit_students'=>12,'price'=>'15.50','billing_cycle'=>'yearly','status'=>false],$request);
    $other=SaasPlan::findOrFail($second['plan_id']);
    check($other->slug!==$created->slug&&!$other->features['ai_translation']&&$other->features['reports']===true,'Native duplicate-name slug and feature restriction preserved');
    check($other->limits['students']===12&&$other->price==='15.50'&&$other->billing_cycle==='yearly'&&!$other->status,'Native limits and commercial metadata validated and saved');
    $count=SaasPlan::count();
    foreach ([['is_default'=>true],['organization_id'=>20],['features'=>['reports'=>false]],['feature_unknown'=>true],['price'=>-1],['billing_cycle'=>'weekly'],['limit_students'=>-1],['feature_reports'=>'invalid']] as $bad) {
        try {$editor->create(10,array_merge(['name'=>'Rejected'],$bad),$request);throw new LogicException('Expected invalid plan denial');}
        catch (Illuminate\Validation\ValidationException|Symfony\Component\HttpKernel\Exception\HttpException $error) {}
        check(SaasPlan::count()===$count,'Invalid plan input never persists');
    }
    $GLOBALS['failPlanAudit']=true;
    try {$editor->create(10,['name'=>'Audit rollback'],$request);throw new LogicException('Expected audit failure');}
    catch (RuntimeException $error) {check($error->getMessage()==='Synthetic native audit failure','Native audit failure propagated');}
    finally {$GLOBALS['failPlanAudit']=false;}
    check(SaasPlan::count()===$count,'Native audit failure rolls back plan creation');
    check(SaasPlan::whereIn('id',array_column($beforePlans,'id'))->orderBy('id')->get()->map(fn($p)=>$p->getRawOriginal())->all()===$beforePlans,'Creating plans preserves every pre-existing plan');
    check(DB::table('organizations')->orderBy('id')->get()->toJson()===$beforeOwners,'Plan creation changes no organisation assignment or subscription');
} finally {app()->instance('redirect',$oldRedirect);$session->invalidate();}
$requestBefore=app('request');$redirectBefore=app('redirect');$guard=Illuminate\Support\Facades\Auth::guard('web');$userBefore=$guard->user();
$body=['actor_id'=>'cccccccc-9999-4999-8999-999999999999','request_id'=>$nextId(),'fields'=>['name'=>'Coordinated plan','feature_reports'=>false]];
$call=fn($input)=>$planController->createPlan(Illuminate\Http\Request::create('/','POST',$input));
$saved=$call($body);$count=SaasPlan::count();$auditCount=count($GLOBALS['planAudit']);
check($saved['saved']&&$call($body)===$saved&&SaasPlan::count()===$count&&count($GLOBALS['planAudit'])===$auditCount,'Plan creation receipt prevents duplicate native creation/audit');
check($call(array_replace($body,['fields'=>array_reverse($body['fields'],true)]))===$saved,'Field ordering does not change the logical request');
check($call(array_replace($body,['fields'=>['name'=>'Changed retry']]))===['saved'=>false,'conflict'=>true],'Changed payload cannot reuse a creation receipt');
check(app('request')===$requestBefore&&app('redirect')===$redirectBefore&&$guard->user()===$userBefore,'Creation restores caller context');
$native=DB::table('tech4learn_central_users')->where('organization_id',10)->where('local_id',$body['actor_id'])->value('external_id');
check($native!==null&&!App\Models\User::findOrFail($native)->is_platform_admin&&DB::table('organization_users')->where('user_id',$native)->count()===1,'Plan creation provisions only an isolated central author');
foreach(['organization_id'=>20,'price'=>10] as $key=>$value)$reject(fn()=>$call($body+[$key=>$value]));
foreach(['actor_id'=>[],'request_id'=>'bad','fields'=>[]] as $key=>$value)$reject(fn()=>$call(array_replace($body,[$key=>$value])));
$reject(fn()=>$planController->createPlan(Illuminate\Http\Request::create('/?owner=20','POST',$body)));
$reject(fn()=>$call(array_replace($body,['fields'=>['name'=>str_repeat('x',17000)]])));
foreach([['status'=>0],['status'=>1,'role'=>'member']] as $revoke){
    DB::table('organization_users')->where('organization_id',10)->where('user_id',$native)->update($revoke);
    $reject(fn()=>$call($body));
    $reject(fn()=>$call(array_replace($body,['request_id'=>$nextId()])));
}
DB::table('organization_users')->where('organization_id',10)->where('user_id',$native)->update(['status'=>1,'role'=>'owner']);
DB::table('users')->where('id',$native)->update(['status'=>0]);$reject(fn()=>$call($body));
DB::table('users')->where('id',$native)->update(['status'=>1]);
$beforeLedger=DB::table('tech4learn_central_requests')->count();$GLOBALS['failPlanAudit']=true;
try{$call(array_replace($body,['request_id'=>$nextId()]));throw new LogicException('Expected coordinated audit failure');}
catch(RuntimeException $error){check($error->getMessage()==='Synthetic native audit failure','Coordinated native failure propagated');}
finally{$GLOBALS['failPlanAudit']=false;}
check(DB::table('tech4learn_central_requests')->count()===$beforeLedger&&SaasPlan::count()===$count,'Failed coordinated creation rolls back plan and receipt');
check(app('request')===$requestBefore&&app('redirect')===$redirectBefore&&$guard->user()===$userBefore,'Failed creation restores caller context');
DB::table('tech4learn_central_users')->where('organization_id',10)->where('local_id',$body['actor_id'])->delete();$reject(fn()=>$call($body));
echo "Native plan creation: validation, defaults, limits, metadata, receipt replay, revocation, bounded controller input and rollback passed. T4L gateway/interface remain unconnected.\n";

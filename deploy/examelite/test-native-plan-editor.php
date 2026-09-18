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
echo "Native plan creation: native validation, capability defaults, limits, metadata, preserved assignments/defaults and audit rollback passed. No route or user interface exposed.\n";

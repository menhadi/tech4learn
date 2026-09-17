<?php
// Optional fourth argument is the locally transformed native LanguageController.
// Without it, the installed native controller must contain the scoped hooks.
if(isset($argv[4]))$GLOBALS['t4lTestLanguageController']=$argv[4];
require __DIR__.'/test-central-question-authoring.php';
foreach(['isPlatformAdmin','platformOrganizationId'] as $hook)check((new ReflectionMethod(App\Http\Controllers\LanguageController::class,$hook))->isProtected(),'Native scoped language hooks installed');
require __DIR__.'/Tech4LearnCentralLanguageController.php';
use Illuminate\Support\Facades\DB;
use App\Models\Language;
$languageSave=fn(int $id,array $fields,string $revision,string $request)=>$service->saveCentralTaxonomy(10,$centralActor,'languages',$id,$fields,$revision,$request);
$orgBefore=Language::where('organization_id',20)->orderBy('id')->get()->toArray();
$request=$nextId();$fields=['name'=>'New master language','code'=>'new-master','value1'=>'True label','value2'=>'False label'];
$created=$languageSave(0,$fields,'new',$request);
check($created['fields']['name']===$fields['name']&&$created['fields']['code']===$fields['code']&&Language::find($created['id'])->organization_id==10,'Native controller creates language in configured central bank');
check($languageSave(0,$fields,'new',$request)===$created,'Central language creation is replay safe');
check(!$GLOBALS['t4lTestPlatformAdmin'],'Global native administrator context stays disabled');
$authorId=DB::table('tech4learn_central_users')->where('organization_id',10)->where('local_id',$centralActor)->value('external_id');
check(!App\Models\User::find($authorId)->is_platform_admin,'Language author has no global administrator rights');
$orgEnabled=$service->save($workspace,20,$actor,0,['master_language_id'=>$created['id']],'new',$nextId(),'languages');
$edited=$languageSave($created['id'],['name'=>'Renamed master language','value1'=>'Yes label'],$created['revision'],$nextId());
check($edited['fields']['code']==='new-master'&&$edited['fields']['value1']==='Yes label','Native language update retains unedited fields');
check(Language::find($orgEnabled['id'])->name==='New master language'&&Language::find($orgEnabled['id'])->value1==='True label','Master edit preserves enabled organisation version');
$ledger=DB::table('tech4learn_central_requests')->count();
$reject(fn()=>$languageSave($created['id'],['value2'=>'stale'],$created['revision'],$nextId()));
$reject(fn()=>$languageSave($orgEnabled['id'],['name'=>'foreign'],$orgEnabled['revision'],$nextId()));
foreach([
    ['name'=>'Duplicate code','code'=>'new-master'],
    ['name'=>'Renamed master language','code'=>'other-code'],
    ['name'=>'Invalid','code'=>str_repeat('x',21)],
    ['name'=>['array'],'code'=>'invalid'],
    ['name'=>'<b>Markup</b>','code'=>'markup'],
    ['name'=>'Scope override','code'=>'scope','organization_id'=>20],
    ['master_language_id'=>$created['id']],
] as $bad)$reject(fn()=>$languageSave(0,$bad,'new',$nextId()));
check(DB::table('tech4learn_central_requests')->count()===$ledger,'Rejected central language writes leave no ledger entry');
DB::table('users')->where('id',$authorId)->update(['status'=>0]);
$reject(fn()=>$languageSave(0,$fields,'new',$request));
DB::table('users')->where('id',$authorId)->update(['status'=>1]);
check($languageSave(0,$fields,'new',$request)===$created,'Restored author may retry original creation');
check(Language::where('organization_id',20)->where('id','<>',$orgEnabled['id'])->orderBy('id')->get()->toArray()===$orgBefore,'Other organisation languages unchanged');
echo "Central native languages: native create/update, unique rules, scoped owner, no global rights, copies, stale revisions and retries passed.\n";

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
$draft=$centralController->centralTaxonomy(Illuminate\Http\Request::create('/','GET'),'languages','new');
check($draft['record']['id']===0&&array_keys($draft['record']['fields'])===['name','code','value1','value2'],'Central language draft projects only editable fields');
$requestBody=['actor_id'=>$centralActor,'request_id'=>$nextId(),'revision'=>$edited['revision'],'fields'=>['value2'=>'No label']];
$savedReply=$centralController->centralTaxonomyWrite(Illuminate\Http\Request::create('/','POST',$requestBody),'languages',(string)$edited['id']);
check($savedReply['saved']===true&&$savedReply['record']['fields']['value2']==='No label'&&!array_key_exists('master_language_id',$savedReply['record']['fields'])&&!array_key_exists('is_enabled',$savedReply['record']['fields']),'Central language route returns bounded saved snapshot');
check($centralController->centralTaxonomyWrite(Illuminate\Http\Request::create('/','POST',$requestBody),'languages',(string)$edited['id'])===$savedReply,'Central language route retry stays stable');
$reject(fn()=>$centralController->centralTaxonomy(Illuminate\Http\Request::create('/?owner=20','GET'),'languages',(string)$edited['id']));
$reject(fn()=>$centralController->centralTaxonomy(Illuminate\Http\Request::create('/','GET'),'languages',(string)$orgEnabled['id']));
echo "Central language routes: draft, projection, native save/retry and foreign-record denial passed.\n";

$deletable=$languageSave(0,['name'=>'Unused synthetic language','code'=>'delete-test'],'new',$nextId());
$remove=fn($record,$key)=>$service->deleteCentralLanguage(10,$centralActor,$record['id'],$record['revision'],$key);
$deleteRequest=$nextId();$ledger=DB::table('tech4learn_central_requests')->count();
foreach([
 'languages'=>'source_language_id','questions'=>'language_id','question_langs'=>'language_id',
 'passage_langs'=>'language_id','exam_languages'=>'language_id','exam_language_translations'=>'language_id',
 'exam_results'=>'language_id','exam_pdf_builds'=>'language_id','official_exam_source_rules'=>'language_id',
] as $table=>$column){
 if(!DB::getSchemaBuilder()->hasTable($table))DB::statement('CREATE TABLE '.$table.' (id INTEGER PRIMARY KEY, '.$column.' INTEGER)');
 if(!DB::getSchemaBuilder()->hasColumn($table,$column))DB::statement('ALTER TABLE '.$table.' ADD COLUMN '.$column.' INTEGER');
 DB::beginTransaction();
 try{
  DB::table($table)->insert([$column=>$deletable['id']]);
  $reject(fn()=>$remove($deletable,$deleteRequest));
  check(Language::find($deletable['id'])!==null&&DB::table('tech4learn_central_requests')->count()===$ledger,'Referenced language remains intact with no receipt: '.$table);
 }finally{DB::rollBack();}
}
$reject(fn()=>$remove($orgEnabled,$nextId()));
$reject(fn()=>$remove(array_replace($deletable,['revision'=>str_repeat('0',64)]),$nextId()));
$englishRecord=$service->record('languages',Language::where('organization_id',10)->where('code','en')->firstOrFail());
$reject(fn()=>$remove($englishRecord,$nextId()));
$deleted=$remove($deletable,$deleteRequest);
check($deleted===['id'=>$deletable['id'],'deleted'=>true]&&Language::find($deletable['id'])===null,'Unused central language deleted by native controller');
check($remove($deletable,$deleteRequest)===$deleted,'Deleted central language receipt remains replayable');
DB::table('users')->where('id',$authorId)->update(['status'=>0]);
$reject(fn()=>$remove($deletable,$deleteRequest));
DB::table('users')->where('id',$authorId)->update(['status'=>1]);
echo "Central language deletion: native persistence, all known references, English protection, ownership, revisions and authorised retries passed.\n";

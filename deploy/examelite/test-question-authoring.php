<?php
namespace App\Support {
 // These request-context doubles avoid loading server credentials or host configuration.
 class Tenant {
  private static ?int $tenant=null;
  public static function clear():void{self::$tenant=null;}
  public static function resolve($host=null){$org=\App\Models\Organization::where('domain',$host??request()->getHost())->firstOrFail();self::$tenant=(int)$org->id;return $org;}
  public static function id(){return self::$tenant??self::resolve()->id;}
 }
 class SaasAccess {public static function abortIfLimitReached($feature):void{}}
}
namespace {
if(isset($argv[3])){ $taxonomy=dirname($argv[3]).'/CurriculumTaxonomyService.php'; if(is_file($taxonomy))require $taxonomy; }
require __DIR__.'/test-content-copies.php';
if(isset($argv[3])){require $argv[3];foreach(['SubjectController','TopicController','StopicController','GroupController','SectionController'] as $controller){$path=dirname($argv[3]).'/'.$controller.'.php';if(is_file($path))require $path;}}
require __DIR__.'/Tech4LearnQuestionAuthoring.php';
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
$app=new Illuminate\Foundation\Application(__DIR__);
$app->instance('db',$db->getDatabaseManager());$app->bind('db.schema',fn()=>$db->getConnection()->getSchemaBuilder());
$app->instance('config',new Illuminate\Config\Repository(['app'=>['locale'=>'en','fallback_locale'=>'en']]));
Illuminate\Support\Facades\Facade::setFacadeApplication($app);
$guard=new class {public $current=null;function user(){return $this->current;}function setUser($u){$this->current=$u;return $this;}function forgetUser(){$this->current=null;return $this;}};
$app->instance('auth',new class($guard){function __construct(private $guard){}function guard($name){return $this->guard;}});
$translator=new Illuminate\Translation\Translator(new Illuminate\Translation\ArrayLoader(),'en');
$validator=new Illuminate\Validation\Factory($translator,$app);$validator->setPresenceVerifier(new Illuminate\Validation\DatabasePresenceVerifier($db->getDatabaseManager()));
$app->instance('validator',$validator);
Request::macro('validate',function($rules)use($validator){return $validator->make($this->all(),$rules)->validate();});
$oldRequest=Request::create('https://central.example.test/');$app->instance('request',$oldRequest);
$routes=new Illuminate\Routing\RouteCollection();foreach(['index'=>'questions','edit'=>'questions/{question}/edit','create'=>'questions/create'] as $name=>$path)$routes->add((new Illuminate\Routing\Route(['GET'],$path,fn()=>null))->name('questions.'.$name));
$url=new Illuminate\Routing\UrlGenerator($routes,$oldRequest);$app->instance('url',$url);
$oldRedirect=new Illuminate\Routing\Redirector($url);$app->instance('redirect',$oldRedirect);
DB::statement('DROP TABLE tech4learn_workspaces');
DB::statement('CREATE TABLE tech4learn_workspaces(id TEXT PRIMARY KEY,source_organization_id INTEGER,organization_id INTEGER,restrictions TEXT)');
DB::statement('CREATE TABLE tech4learn_workspace_users(workspace_id TEXT,local_id TEXT,kind TEXT,external_id INTEGER)');
DB::statement('CREATE TABLE tech4learn_authoring_requests(workspace_id TEXT,request_id TEXT,fingerprint TEXT,result TEXT,created_at TEXT,PRIMARY KEY(workspace_id,request_id))');
DB::statement('CREATE TABLE organizations(id INTEGER PRIMARY KEY,domain TEXT,status TEXT)');
DB::statement('CREATE TABLE users(id INTEGER PRIMARY KEY,name TEXT)');
DB::statement('CREATE TABLE organization_users(organization_id INTEGER,user_id INTEGER,status INTEGER)');
DB::statement('CREATE TABLE qtypes(id INTEGER PRIMARY KEY,question_type TEXT,type TEXT)');
DB::statement('CREATE TABLE diffs(id INTEGER PRIMARY KEY,diff_level TEXT)');
foreach(['qtype_id','diff_id','source_url','source_reference','option1','option2','option3','option4','option5','option6','marks','negative_marks','scoring_policy','hint','explanation','answer','true_false','fill_blank','status','correct_option_indices','si_answer1'] as $field)DB::statement('ALTER TABLE questions ADD COLUMN '.$field.' TEXT');
DB::table('organizations')->insert(['id'=>20,'domain'=>'owned.example.test','status'=>'active']);
DB::table('users')->insert(['id'=>1,'name'=>'Synthetic author']);DB::table('organization_users')->insert(['organization_id'=>20,'user_id'=>1,'status'=>1]);
DB::table('qtypes')->insert([['id'=>1,'question_type'=>'Multiple choice','type'=>'M'],['id'=>2,'question_type'=>'Numerical','type'=>'NAT'],['id'=>3,'question_type'=>'Blanks','type'=>'F'],['id'=>4,'question_type'=>'True/False','type'=>'T']]);
$workspace='11111111-1111-1111-1111-111111111111';$actor='22222222-2222-2222-2222-222222222222';
DB::table('tech4learn_workspaces')->insert(['id'=>$workspace,'source_organization_id'=>10,'organization_id'=>20,'restrictions'=>'[]']);
DB::table('tech4learn_workspace_users')->insert(['workspace_id'=>$workspace,'local_id'=>$actor,'kind'=>'staff','external_id'=>1]);
$group=App\Models\Group::create(['organization_id'=>20,'group_name'=>'Own group']);
$language=App\Models\Language::where('organization_id',20)->firstOrFail();
$q=App\Models\Question::create(['organization_id'=>20,'qtype_id'=>2,'question'=>'Original numerical question','language_id'=>$language->id,'nat_config'=>['version'=>1,'mode'=>'exact','value'=>7],'marks'=>4,'status'=>'Yes']);$q->groups()->attach($group->id);
$service=new App\Services\Tech4LearnQuestionAuthoring();$before=$service->snapshot($q->fresh());
$saved=$service->save($workspace,20,$actor,$q->id,['question'=>'Edited numerical question'],$before['revision'],'first');
check($saved['fields']['question']==='Edited numerical question' && $saved['fields']['nat_value']===7,'Native update preserves numerical answer configuration');
check($service->save($workspace,20,$actor,$q->id,['question'=>'Edited numerical question'],$before['revision'],'first')===$saved,'Successful update replay is stable');
check(app('request')===$oldRequest && app('redirect')===$oldRedirect && $guard->user()===null,'Private request and identity restored');
try{$service->save($workspace,20,$actor,$q->id,['question'=>'Stale edit'],$before['revision'],'stale');throw new RuntimeException('Expected stale edit denial');}catch(Symfony\Component\HttpKernel\Exception\HttpException $e){check($e->getStatusCode()===409,'Stale editor conflict');}
try{$service->save($workspace,20,$actor,$q->id,['nat_value'=>null],$saved['revision'],'invalid');throw new RuntimeException('Expected validation');}catch(Illuminate\Validation\ValidationException $e){check(isset($e->errors()['nat_value']),'Native numerical answer validation returned');}
check($service->snapshot($q->fresh())===$saved,'Invalid native save rolls back');
try{$service->save($workspace,20,$actor,$q->id,['group_ids'=>[1]],$saved['revision'],'foreign');throw new RuntimeException('Expected tenant validation');}catch(Illuminate\Validation\ValidationException $e){}
check($service->snapshot($q->fresh())===$saved,'Foreign taxonomy has no partial changes');
foreach([['organization_id'=>10],['question'=>'<img src=x onerror=alert(1)>'],['question'=>'<p onclick="alert(1)">Unsafe</p>']] as $unsafe){
 try{$service->save($workspace,20,$actor,$q->id,$unsafe,$saved['revision'],'unsafe');throw new RuntimeException('Expected forbidden authoring data');}catch(Illuminate\Validation\ValidationException $e){}
}
check($service->snapshot($q->fresh())===$saved,'Unsupported markup and ownership changes have no effect');

foreach([
 ['qtype_id'=>1,'option1'=>'A','option2'=>'B','correct_option_indices'=>[1,2]],
 ['qtype_id'=>3,'fill_blank'=>'one','fill_blank_config'=>['version'=>1,'blanks'=>[['answers'=>['one','first']],['answers'=>['two']]]]],
 ['qtype_id'=>4,'true_false'=>'True'],
 ['qtype_id'=>2,'nat_config'=>['version'=>1,'mode'=>'range','min'=>-2,'max'=>5]],
 ['qtype_id'=>2,'nat_config'=>['version'=>1,'mode'=>'tolerance','value'=>7,'tolerance'=>0.2]],
] as $index=>$answer){
 $item=App\Models\Question::create(array_merge(['organization_id'=>20,'question'=>'Synthetic answer preservation','language_id'=>$language->id,'marks'=>4,'status'=>'Yes'],$answer));$item->groups()->attach($group->id);
 $snapshot=$service->snapshot($item->fresh());
 $result=$service->save($workspace,20,$actor,$item->id,['marks'=>5],$snapshot['revision'],'answers-'.$index);
 foreach(['correct_answers','fill_blank_answers','true_false','nat_mode','nat_value','nat_min','nat_max','nat_tolerance'] as $field)check(($result['fields'][$field]??null)==($snapshot['fields'][$field]??null),'Answer preserved: '.$index.' '.$field);
}

$createFields=['qtype_id'=>2,'question'=>'Created through the native controller','language_id'=>$language->id,'group_ids'=>[$group->id],'nat_mode'=>'exact','nat_value'=>9,'marks'=>4,'status'=>'Yes'];
foreach(['option1','option2','option3','option4','option5','option6','hint','explanation','fill_blank'] as $field)DB::statement('ALTER TABLE question_langs ADD COLUMN '.$field.' TEXT');
$beforeCount=App\Models\Question::count();$originalEvents=App\Models\Question::getEventDispatcher();
$created=$service->save($workspace,20,$actor,0,$createFields,'new','create-1');
check(App\Models\Question::count()===$beforeCount+1 && $created['fields']['nat_value']==9,'Native question creation');
check(App\Models\QuestionLang::where('question_id',$created['id'])->count()===1,'Native language record created');
check($service->save($workspace,20,$actor,0,$createFields,'new','create-1')===$created && App\Models\Question::count()===$beforeCount+1,'Create retry does not duplicate');
check(App\Models\Question::getEventDispatcher()===$originalEvents,'Native event dispatcher restored');
try{$service->save($workspace,20,$actor,0,array_replace($createFields,['nat_value'=>null]),'new','create-invalid');throw new RuntimeException('Expected create validation');}catch(Illuminate\Validation\ValidationException $e){}
check(App\Models\Question::count()===$beforeCount+1,'Invalid create has no partial record');

foreach(['groups','topics','stopics','question_sections'] as $table)DB::statement('ALTER TABLE '.$table.' ADD COLUMN display_order INTEGER');
DB::statement('ALTER TABLE subjects ADD COLUMN category_ids TEXT');DB::statement('ALTER TABLE question_sections ADD COLUMN status INTEGER');
foreach(['groups','subjects','topics','stopics','sections'] as $route)$routes->add((new Illuminate\Routing\Route(['GET'],$route,fn()=>null))->name($route.'.index'));
$subject=$service->save($workspace,20,$actor,0,['subject_name'=>'Synthetic syllabus','group_ids'=>[$group->id]],'new','subject-1','subjects');
$topic=$service->save($workspace,20,$actor,0,['name'=>'Synthetic topic','subject_id'=>$subject['id'],'group_id'=>$group->id,'display_order'=>0],'new','topic-1','topics');
$subtopic=$service->save($workspace,20,$actor,0,['name'=>'Synthetic subtopic','subject_id'=>$subject['id'],'group_id'=>$group->id,'topic_id'=>$topic['id'],'display_order'=>0],'new','subtopic-1','subtopics');
$section=$service->save($workspace,20,$actor,0,['name'=>'Synthetic section','group_ids'=>[$group->id],'status'=>true],'new','section-1','sections');
$newGroup=$service->save($workspace,20,$actor,0,['group_name'=>'Synthetic new exam group','display_order'=>0],'new','group-1','groups');
check($subject['id']&&$topic['id']&&$subtopic['id']&&$section['id']&&$newGroup['id'],'Native taxonomy creation');
$renamed=$service->save($workspace,20,$actor,$subject['id'],['subject_name'=>'Renamed syllabus'],$subject['revision'],'subject-update','subjects');
check($renamed['fields']['subject_name']==='Renamed syllabus' && $renamed['fields']['group_ids']===[$group->id],'Native subject edit preserves scope');
$beforeTaxonomy=App\Models\Topic::count();
try{$service->save($workspace,20,$actor,0,['name'=>'Foreign','subject_id'=>1,'group_id'=>$group->id],'new','foreign-topic','topics');throw new RuntimeException('Expected foreign subject denial');}catch(Illuminate\Database\Eloquent\ModelNotFoundException|Symfony\Component\HttpKernel\Exception\HttpException|Illuminate\Validation\ValidationException $e){}
check(App\Models\Topic::count()===$beforeTaxonomy,'Foreign taxonomy create has no partial records');
require __DIR__.'/Tech4LearnPlatformController.php';require __DIR__.'/Tech4LearnAuthoringController.php';
$controller=new class extends App\Http\Controllers\Tech4LearnAuthoringController {
 protected function configuration(Request $r):array{return ['_platform'=>['organization_id'=>10]];}
 protected function reply(int $tenant,array $data){return $data;}
};
foreach(['groups','subjects','topics','subtopics','sections','languages','types','difficulties'] as $kind){
 $choices=$controller->choices(Request::create('/','GET',['after'=>'0']),$workspace,$kind);
 foreach($choices['items'] as $item){
  if(in_array($kind,['types','difficulties'],true))continue;
  if($kind==='languages')check(App\Models\Language::find($item['id'])->organization_id==20,'Language choices tenant scope');
  else check($service->owned($kind,20)->whereKey($item['id'])->exists(),'Taxonomy choices tenant scope');
 }
}
$beforeRequests=DB::table('tech4learn_authoring_requests')->count();
DB::table('tech4learn_workspaces')->where('id',$workspace)->update(['restrictions'=>'["subjects"]']);
try{$service->save($workspace,20,$actor,$subject['id'],['subject_name'=>'Blocked'],$renamed['revision'],'blocked','subjects');throw new RuntimeException('Expected taxonomy restriction');}catch(Symfony\Component\HttpKernel\Exception\HttpException $e){check($e->getStatusCode()===403,'Taxonomy feature restriction');}
check(DB::table('tech4learn_authoring_requests')->count()===$beforeRequests,'Denied taxonomy save has no ledger entry');

echo "Native question adapter: native validation, unchanged answers, scope, stale edits, replay and context restoration passed.\n";
}


<?php
namespace App\Support {
 // These request-context doubles avoid loading server credentials or host configuration.
 class Tenant {
  private static ?int $tenant=null;
  public static function clear():void{self::$tenant=null;}
  public static function resolve($host=null){$org=\App\Models\Organization::where('domain',$host??request()->getHost())->firstOrFail();self::$tenant=(int)$org->id;return $org;}
  public static function id(){return self::$tenant??self::resolve()->id;}
  public static function hostId($host){return self::resolve($host)->id;}
 }
 class SaasAccess {public static function abortIfLimitReached($feature):void{} public static function organization(){return \App\Models\Organization::find(Tenant::id());} public static function isPlatformAdmin():bool{return $GLOBALS['t4lTestPlatformAdmin']??false;} public static function isPlatformOwner():bool{return false;} public static function featureEnabled($feature,$organisation=null):bool{return $feature==='ai_translation'?($GLOBALS['t4lTestAiTranslation']??true):true;}}
}
namespace {
// The native controller consults this organisation configuration helper.
if(!function_exists('subcategories_enabled')){function subcategories_enabled():bool{return $GLOBALS['t4lTestSubcategoriesEnabled']??true;}}
if(isset($argv[3])){ $taxonomy=dirname($argv[3]).'/CurriculumTaxonomyService.php'; if(is_file($taxonomy))require $taxonomy; }
require __DIR__.'/test-content-copies.php';
if(isset($argv[3])){require $argv[3];foreach(['SubjectController','TopicController','StopicController','GroupController','SectionController','CategoryController','LanguageController'] as $controller){$path=dirname($argv[3]).'/'.$controller.'.php';if(is_file($path))require $path;}}
require __DIR__.'/Tech4LearnQuestionAuthoring.php';
require_once __DIR__.'/Tech4LearnQuestionMedia.php';
require_once __DIR__.'/Tech4LearnQuestionImageUpload.php';
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
$app=new Illuminate\Foundation\Application(__DIR__);
$app->instance('db',$db->getDatabaseManager());$app->bind('db.schema',fn()=>$db->getConnection()->getSchemaBuilder());
$app->instance('config',new Illuminate\Config\Repository(['app'=>['locale'=>'en','fallback_locale'=>'en']]));
Illuminate\Support\Facades\Facade::setFacadeApplication($app);
$guard=new class {public $current=null;function user(){return $this->current;}function id(){return $this->current?->id;}function setUser($u){$this->current=$u;return $this;}function forgetUser(){$this->current=null;return $this;}};
$app->instance('auth',new class($guard){function __construct(private $guard){}function guard($name){return $this->guard;}function id(){return $this->guard->id();}});
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
$retryLedgerCount=DB::table('tech4learn_authoring_requests')->count();
DB::table('organization_users')->where('organization_id',20)->where('user_id',1)->update(['status'=>0]);
try{$service->save($workspace,20,$actor,$q->id,['question'=>'Edited numerical question'],$before['revision'],'first');throw new RuntimeException('Expected revoked replay denial');}catch(Symfony\Component\HttpKernel\Exception\HttpException $e){check($e->getStatusCode()===403,'Revoked staff cannot replay a saved response');}
DB::table('organization_users')->where('organization_id',20)->where('user_id',1)->update(['status'=>1]);
DB::table('organizations')->where('id',20)->update(['status'=>'inactive']);
try{$service->save($workspace,20,$actor,$q->id,['question'=>'Edited numerical question'],$before['revision'],'first');throw new RuntimeException('Expected inactive replay denial');}catch(Illuminate\Database\Eloquent\ModelNotFoundException $e){}
DB::table('organizations')->where('id',20)->update(['status'=>'active']);
check(DB::table('tech4learn_authoring_requests')->count()===$retryLedgerCount&&$service->snapshot($q->fresh())===$saved,'Denied replay does not change content or ledger');
check($service->save($workspace,20,$actor,$q->id,['question'=>'Edited numerical question'],$before['revision'],'first')===$saved,'Restored authorised replay retains original outcome');
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
$formula='<p>Solve \\(x^2=9\\) and \\[x=\\sqrt{9}\\].</p>';
$formulaSaved=$service->save($workspace,20,$actor,$created['id'],['question'=>$formula],$created['revision'],'formula-edit');
check($formulaSaved['fields']['question']===$formula && $formulaSaved['fields']['nat_value']==9,'Native formula text preserved without changing the answer');
check($service->save($workspace,20,$actor,$created['id'],['question'=>$formula],$created['revision'],'formula-edit')===$formulaSaved,'Formula edit retry preserved');
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
foreach(['title','description','status','display_order','show_in_header','header_display_order','meta_title','meta_description','meta_keywords','canonical_url','og_title','og_description','og_image','robots_meta','seo_schema'] as $field)DB::statement('ALTER TABLE category ADD COLUMN '.$field.' TEXT');
DB::statement('CREATE TABLE category_groups(category_id INTEGER,group_id INTEGER,display_order INTEGER,created_at TEXT,updated_at TEXT)');
$routes->add((new Illuminate\Routing\Route(['GET'],'category',fn()=>null))->name('category.index'));
$categoryFields=['title'=>'Synthetic category','status'=>true,'description'=>'Description','group_ids'=>[$group->id],'group_orders'=>[$group->id=>7],'show_in_header'=>true,'header_display_order'=>3,'meta_title'=>'Preserved metadata'];
$category=$service->save($workspace,20,$actor,0,$categoryFields,'new','category-create','categories');
check($category['fields']['title']==='Synthetic category'&&$category['fields']['group_ids']===[$group->id],'Native category created with owned groups');
check($service->save($workspace,20,$actor,0,$categoryFields,'new','category-create','categories')===$category,'Category create retry does not duplicate');
$categoryEdit=$service->save($workspace,20,$actor,$category['id'],['title'=>'Renamed category'],$category['revision'],'category-edit','categories');
check($categoryEdit['fields']['meta_title']==='Preserved metadata'&&$categoryEdit['fields']['show_in_header']===true&&(int)$categoryEdit['fields']['group_orders'][$group->id]===7,'Category edit preserves metadata and group order');
$categoryCount=App\Models\Category::count();
try{$service->save($workspace,20,$actor,0,['title'=>'Invalid group','status'=>true,'group_ids'=>[1]],'new','category-foreign','categories');throw new RuntimeException('Expected foreign category group denial');}catch(Illuminate\Validation\ValidationException $e){}
check(App\Models\Category::count()===$categoryCount,'Invalid category leaves no partial record');
try{$service->save($workspace,20,$actor,$category['id'],['title'=>'Stale'],$category['revision'],'category-stale','categories');throw new RuntimeException('Expected category conflict');}catch(Symfony\Component\HttpKernel\Exception\HttpException $e){check($e->getStatusCode()===409,'Category stale revision denied');}
$foreignCategory=App\Models\Category::create(['organization_id'=>30,'title'=>'Foreign category']);
$childCategory=App\Models\Category::create(['organization_id'=>20,'title'=>'Child category','parent_id'=>$category['id']]);
foreach([$foreignCategory,$childCategory] as $unavailable)check(!$service->owned('categories',20)->whereKey($unavailable->id)->exists(),'Category adapter excludes foreign and child records');
$routes->add((new Illuminate\Routing\Route(['GET'],'subcategories',fn()=>null))->name('subcategories.index'));
$subFields=['title'=>'Synthetic subcategory','parent_id'=>$category['id'],'status'=>true];
$subcategory=$service->save($workspace,20,$actor,0,$subFields,'new','subcategory-create','subcategories');
check((int)$subcategory['fields']['parent_id']===$category['id'],'Native subcategory references owned parent');
check($service->save($workspace,20,$actor,0,$subFields,'new','subcategory-create','subcategories')===$subcategory,'Subcategory create replay does not duplicate');
$subEdit=$service->save($workspace,20,$actor,$subcategory['id'],['title'=>'Edited subcategory'],$subcategory['revision'],'subcategory-edit','subcategories');
check($subEdit['fields']['title']==='Edited subcategory'&&(int)$subEdit['fields']['parent_id']===$category['id'],'Native subcategory edit preserves parent');
foreach([$foreignCategory->id,$subcategory['id']] as $invalidParent){
 try{$service->save($workspace,20,$actor,$subcategory['id'],['parent_id'=>$invalidParent],$subEdit['revision'],'subcategory-invalid-'.$invalidParent,'subcategories');throw new RuntimeException('Expected invalid parent denial');}catch(Illuminate\Validation\ValidationException $e){}
 check($service->record('subcategories',App\Models\Category::find($subcategory['id']))===$subEdit,'Invalid parent leaves subcategory unchanged');
}
$GLOBALS['t4lTestSubcategoriesEnabled']=false;$beforeSubRequests=DB::table('tech4learn_authoring_requests')->count();
try{$service->save($workspace,20,$actor,0,$subFields,'new','subcategory-disabled','subcategories');throw new RuntimeException('Expected disabled subcategory denial');}catch(Symfony\Component\HttpKernel\Exception\HttpException $e){check($e->getStatusCode()===404,'Native subcategory feature setting enforced');}
check(DB::table('tech4learn_authoring_requests')->count()===$beforeSubRequests,'Disabled subcategory leaves no request entry');$GLOBALS['t4lTestSubcategoriesEnabled']=true;
$corruptChild=App\Models\Category::create(['organization_id'=>20,'title'=>'Foreign parent child','parent_id'=>$foreignCategory->id]);
check(!$service->owned('subcategories',20)->whereKey($corruptChild->id)->exists(),'Subcategory with foreign parent cannot be edited');
DB::statement('ALTER TABLE organizations ADD COLUMN slug TEXT');
DB::table('organizations')->insert(['id'=>10,'domain'=>'central.example.test','slug'=>'examelite','status'=>'active']);
DB::statement('ALTER TABLE languages ADD COLUMN is_enabled INTEGER DEFAULT 1');
foreach(['value1','value2'] as $field)DB::statement('ALTER TABLE languages ADD COLUMN '.$field.' TEXT');
$routes->add((new Illuminate\Routing\Route(['GET'],'languages',fn()=>null))->name('languages.index'));
$master=App\Models\Language::create(['organization_id'=>10,'name'=>'Synthetic language','code'=>'synthetic','value1'=>'True','value2'=>'False','is_enabled'=>true]);
$enabled=$service->save($workspace,20,$actor,0,['master_language_id'=>$master->id],'new','language-enable','languages');
check($enabled['fields']['code']==='synthetic'&&$enabled['fields']['is_enabled']===true,'Native central language enabled in organisation');
$languageCount=App\Models\Language::count();
check($service->save($workspace,20,$actor,0,['master_language_id'=>$master->id],'new','language-enable','languages')===$enabled&&App\Models\Language::count()===$languageCount,'Language enable replay does not duplicate');
$languageLabels=$service->save($workspace,20,$actor,$enabled['id'],['value1'=>'Correct','value2'=>'Incorrect'],$enabled['revision'],'language-labels','languages');
check($languageLabels['fields']['value1']==='Correct'&&$master->fresh()->value1==='True','Native language labels remain organisation-owned');
try{$service->save($workspace,20,$actor,$enabled['id'],['code'=>'changed'],$languageLabels['revision'],'language-code','languages');throw new RuntimeException('Expected central code protection');}catch(Illuminate\Validation\ValidationException $e){}
$foreignLanguage=App\Models\Language::create(['organization_id'=>30,'name'=>'Foreign language','code'=>'foreign']);
try{$service->save($workspace,20,$actor,0,['master_language_id'=>$foreignLanguage->id],'new','language-foreign','languages');throw new RuntimeException('Expected foreign language protection');}catch(Illuminate\Database\Eloquent\ModelNotFoundException $e){}
$GLOBALS['t4lTestPlatformAdmin']=true;
try{$service->save($workspace,20,$actor,$enabled['id'],['value1'=>'Wrong context'],$languageLabels['revision'],'language-platform-context','languages');throw new RuntimeException('Expected platform context rejection');}catch(Symfony\Component\HttpKernel\Exception\HttpException $e){check($e->getStatusCode()===403,'Native platform context cannot edit through organisation adapter');}
$GLOBALS['t4lTestPlatformAdmin']=false;
$disabled=$service->save($workspace,20,$actor,$enabled['id'],[],$languageLabels['revision'],'language-disable','languages','disable-language');
check($disabled['fields']['is_enabled']===false&&App\Models\Language::find($enabled['id'])&&$master->fresh()->is_enabled,'Native language disable retains local and central records');
check($service->save($workspace,20,$actor,$enabled['id'],[],$languageLabels['revision'],'language-disable','languages','disable-language')===$disabled,'Disable retry preserves its original outcome');
check($service->save($workspace,20,$actor,$enabled['id'],[],$disabled['revision'],'language-already-disabled','languages','disable-language')===$disabled,'Already disabled state is idempotent');
try{$service->save($workspace,20,$actor,$master->id,[],$disabled['revision'],'language-disable-master','languages','disable-language');throw new RuntimeException('Expected central language disable denial');}catch(Illuminate\Database\Eloquent\ModelNotFoundException $e){}
try{$service->save($workspace,20,$actor,$enabled['id'],['value1'=>'Unrelated'],$disabled['revision'],'language-disable-fields','languages','disable-language');throw new RuntimeException('Expected empty disable payload');}catch(Illuminate\Validation\ValidationException $e){}
$reenabled=$service->save($workspace,20,$actor,0,['master_language_id'=>$master->id],'new','language-reenable','languages');
check($reenabled['id']===$enabled['id']&&App\Models\Language::count()===$languageCount+1&&$reenabled['fields']['is_enabled']===true,'Native reenable preserves language identity');
require __DIR__.'/Tech4LearnPlatformController.php';require __DIR__.'/Tech4LearnAuthoringController.php';
$controller=new class extends App\Http\Controllers\Tech4LearnAuthoringController {
 protected function configuration(Request $r):array{return ['_platform'=>['organization_id'=>10]];}
 protected function reply(int $tenant,array $data){return $data;}
};
require_once __DIR__.'/Tech4LearnQuestionMedia.php';
$imageSource='data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII=';
$categoryChoices=$controller->choices(Request::create('/','GET'),$workspace,'categories');
$platformLanguages=$controller->choices(Request::create('/','GET'),$workspace,'platform-languages');
check(!in_array($master->id,array_column($platformLanguages['items'],'id'),true)&&!in_array($foreignLanguage->id,array_column($platformLanguages['items'],'id'),true),'Language enable choices exclude enabled and foreign languages');
check(in_array($category['id'],array_column($categoryChoices['items'],'id'),true)&&!in_array($foreignCategory->id,array_column($categoryChoices['items'],'id'),true)&&!in_array($childCategory->id,array_column($categoryChoices['items'],'id'),true),'Category choices exclude foreign and child records');
$subChoices=$controller->choices(Request::create('/','GET'),$workspace,'subcategories');
check(in_array($subcategory['id'],array_column($subChoices['items'],'id'),true)&&!in_array($category['id'],array_column($subChoices['items'],'id'),true)&&!in_array($corruptChild->id,array_column($subChoices['items'],'id'),true),'Subcategory choices exclude parents and foreign parent references');
$filteredChoices=$controller->choices(Request::create('/','GET',['parent_id'=>(string)$category['id']]),$workspace,'subcategories');
foreach($filteredChoices['items'] as $item)check((int)App\Models\Category::find($item['id'])->parent_id===$category['id'],'Subcategory parent filter enforced');
try{$controller->choices(Request::create('/','GET',['parent_id'=>(string)$foreignCategory->id]),$workspace,'subcategories');throw new RuntimeException('Expected foreign parent filter denial');}catch(Illuminate\Database\Eloquent\ModelNotFoundException $e){}
$imageKey=hash('sha256',$imageSource);
$imageQuestion=App\Models\Question::findOrFail($created['id']);
$imageQuestion->explanation='<p>Reference</p><img src="'.$imageSource.'">';$imageQuestion->save();
$preview=$service->snapshot($imageQuestion);
check(str_contains($preview['preview_fields']['explanation'],'t4l-media:'.$imageKey)&&!str_contains($preview['preview_fields']['explanation'],$imageSource),'Authoring preview uses hashed references');
check($preview['fields']['explanation']===$imageQuestion->explanation,'Authoring preview preserves original editable snapshot');
$mediaRequest=Request::create('/','GET');
$imageReply=$controller->questionMedia($mediaRequest,$workspace,(string)$imageQuestion->id,$imageKey);
check($imageReply['question_id']===$imageQuestion->id&&$imageReply['asset']===$imageKey&&$imageReply['mime']==='image/png','Owned authoring image read');
check(!isset($imageReply['attempt_id'])&&!str_contains(json_encode($imageReply),$imageSource),'Authoring image returns only raster bytes and identifiers');
try{app(App\Services\Tech4LearnQuestionMedia::class)->readAuthoring($imageQuestion,10,$imageKey);throw new RuntimeException('Expected foreign owner rejection');}catch(Symfony\Component\HttpKernel\Exception\HttpException $e){check($e->getStatusCode()===403,'Foreign authoring owner denied');}
try{$controller->questionMedia($mediaRequest,$workspace,(string)$imageQuestion->id,str_repeat('a',64));throw new RuntimeException('Expected unreferenced image denial');}catch(Symfony\Component\HttpKernel\Exception\HttpException $e){check($e->getStatusCode()===404,'Unreferenced authoring image denied');}
DB::table('tech4learn_workspaces')->where('id',$workspace)->update(['restrictions'=>'["questions"]']);
try{$controller->questionMedia($mediaRequest,$workspace,(string)$imageQuestion->id,$imageKey);throw new RuntimeException('Expected restricted image denial');}catch(Symfony\Component\HttpKernel\Exception\HttpException $e){check($e->getStatusCode()===403,'Authoring image restriction enforced');}
DB::table('tech4learn_workspaces')->where('id',$workspace)->update(['restrictions'=>'[]']);
DB::table('organizations')->where('id',20)->update(['status'=>'inactive']);
try{$controller->questionMedia($mediaRequest,$workspace,(string)$imageQuestion->id,$imageKey);throw new RuntimeException('Expected inactive owner denial');}catch(Illuminate\Database\Eloquent\ModelNotFoundException $e){}
DB::table('organizations')->where('id',20)->update(['status'=>'active']);
DB::table('questions')->where('id',$imageQuestion->id)->update(['organization_id'=>10]);
try{$controller->questionMedia($mediaRequest,$workspace,(string)$imageQuestion->id,$imageKey);throw new RuntimeException('Expected foreign question denial');}catch(Illuminate\Database\Eloquent\ModelNotFoundException $e){}
DB::table('questions')->where('id',$imageQuestion->id)->update(['organization_id'=>20]);
$invalidSource='data:image/png;base64,'.base64_encode('not an image');
$imageQuestion->explanation='<img src="'.$invalidSource.'">';$imageQuestion->save();
try{$controller->questionMedia($mediaRequest,$workspace,(string)$imageQuestion->id,hash('sha256',$invalidSource));throw new RuntimeException('Expected invalid raster denial');}catch(Symfony\Component\HttpKernel\Exception\HttpException $e){check($e->getStatusCode()===422,'Invalid raster rejected');}
$imageQuestion->explanation='Image removed';$imageQuestion->save();
try{$controller->questionMedia($mediaRequest,$workspace,(string)$imageQuestion->id,$imageKey);throw new RuntimeException('Expected removed image denial');}catch(Symfony\Component\HttpKernel\Exception\HttpException $e){check($e->getStatusCode()===404,'Removed authoring image loses access');}
$imageDisk=new class {public array $files=[];public int $writes=0;function disk($name){check($name==='public','Native public image disk');return $this;}function put($path,$bytes){$this->writes++;$this->files[$path]=$bytes;return true;}function delete($path){unset($this->files[$path]);return true;}};
$app->instance('filesystem',$imageDisk);Illuminate\Support\Facades\Storage::clearResolvedInstance('filesystem');
$imageBefore=$service->snapshot($imageQuestion->fresh());
$uploadFields=['field'=>'explanation','image'=>substr($imageSource,strpos($imageSource,',')+1)];
$uploaded=$service->save($workspace,20,$actor,$imageQuestion->id,$uploadFields,$imageBefore['revision'],'image-upload','questions','set-image');
check(count($imageDisk->files)===1&&str_contains($uploaded['fields']['explanation'],'/storage/images/upload/t4l/20/'),'Image attached through native question save');
check($uploaded['fields']['nat_value']===$imageBefore['fields']['nat_value'],'Image save preserves native answer');
check($service->save($workspace,20,$actor,$imageQuestion->id,$uploadFields,$imageBefore['revision'],'image-upload','questions','set-image')===$uploaded&&$imageDisk->writes===1,'Image retry writes once');
$storedAsset=array_key_first(app(App\Services\Tech4LearnQuestionMedia::class)->sources($uploaded['fields']['explanation']));
$replaced=$service->save($workspace,20,$actor,$imageQuestion->id,$uploadFields+['asset'=>$storedAsset],$uploaded['revision'],'image-replace','questions','set-image');
check(count($imageDisk->files)===2&&substr_count($replaced['fields']['explanation'],'<img')===1,'Replacement preserves old shared file and replaces one reference');
$writesBefore=$imageDisk->writes;
foreach([['field'=>'organization_id'],['image'=>base64_encode('invalid')],['asset'=>str_repeat('b',64)]] as $invalid){
 try{$service->save($workspace,20,$actor,$imageQuestion->id,array_replace($uploadFields,$invalid),$replaced['revision'],'image-invalid-'.count($invalid).hash('sha256',json_encode($invalid)),'questions','set-image');throw new RuntimeException('Expected invalid image rejection');}catch(Symfony\Component\HttpKernel\Exception\HttpException $e){check($e->getStatusCode()===422,'Invalid image rejected');}
}
check($imageDisk->writes===$writesBefore,'Invalid image requests never store files');
$app->instance(App\Http\Controllers\QuestionController::class,new class extends App\Http\Controllers\QuestionController {public function update(Request $request,App\Models\Question $question){throw new RuntimeException('synthetic image save failure');}});
try{$service->save($workspace,20,$actor,$imageQuestion->id,$uploadFields,$replaced['revision'],'image-failed','questions','set-image');throw new RuntimeException('Expected native failure');}catch(RuntimeException $e){check($e->getMessage()==='synthetic image save failure','Native image failure propagated');}
$app->forgetInstance(App\Http\Controllers\QuestionController::class);
check(count($imageDisk->files)===2&&$service->snapshot($imageQuestion->fresh())===$replaced,'Native failure rolls back question and removes uploaded file');
$removeFields=['field'=>'explanation','asset'=>array_key_first(app(App\Services\Tech4LearnQuestionMedia::class)->sources($replaced['fields']['explanation'])),'remove'=>true];
$beforeRemoveWrites=$imageDisk->writes;
$removed=$service->save($workspace,20,$actor,$imageQuestion->id,$removeFields,$replaced['revision'],'image-remove','questions','set-image');
check(!str_contains($removed['fields']['explanation'],'<img')&&count($imageDisk->files)===2&&$imageDisk->writes===$beforeRemoveWrites,'Removal only changes the question reference');
check($service->save($workspace,20,$actor,$imageQuestion->id,$removeFields,$replaced['revision'],'image-remove','questions','set-image')===$removed,'Removal retry preserves outcome');
try{$service->save($workspace,20,$actor,$imageQuestion->id,$removeFields,$removed['revision'],'image-remove-stale','questions','set-image');throw new RuntimeException('Expected missing image denial');}catch(Symfony\Component\HttpKernel\Exception\HttpException $e){check($e->getStatusCode()===422,'Missing image cannot be removed');}
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


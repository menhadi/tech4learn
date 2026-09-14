<?php
namespace App\Jobs {
 // No real jobs run in the isolated controller test.
 class ReconcileExamDocumentsForExamJob {public static int $scheduled=0;public static function dispatch($id){self::$scheduled++;return new self;}public function afterCommit(){return $this;}}
}
namespace App\Services {
 class ExamDocumentInvalidationService {public static int $calls=0;public function invalidateExam($exam){self::$calls++;}}
 class PypContentService {public function forgetForExam($exam,$previous=[]):void{}}
}
namespace {
function user_can_route_action($route,$action='view'){return true;}
require __DIR__.'/test-question-authoring.php';
if(isset($argv[4]))require $argv[4];
if(isset($argv[3]))foreach(['ExamScopeService','ExamLanguageService','ExamQualitySourceStorage','CategoryHierarchy'] as $name){$file=dirname($argv[3]).'/'.$name.'.php';if(is_file($file))require $file;}
use Illuminate\Support\Facades\DB;
if(!function_exists('subcategories_enabled')){function subcategories_enabled(){return true;}}
DB::statement('CREATE TABLE packages(id INTEGER PRIMARY KEY,organization_id INTEGER,name TEXT,category_level_1 INTEGER,category_level_2 INTEGER)');
DB::statement('CREATE TABLE exam_packages(id INTEGER PRIMARY KEY,exam_id INTEGER,package_id INTEGER,display_order INTEGER,created_at TEXT,updated_at TEXT)');
DB::statement('CREATE TABLE package_groups(id INTEGER PRIMARY KEY,package_id INTEGER,group_id INTEGER,created_at TEXT,updated_at TEXT)');
if(!DB::getSchemaBuilder()->hasColumn('languages','is_enabled'))DB::statement('ALTER TABLE languages ADD COLUMN is_enabled INTEGER DEFAULT 1');
$columns=DB::getSchemaBuilder()->getColumnListing('exams');
foreach(array_unique(array_merge(App\Services\Tech4LearnQuestionAuthoring::EXAM_FIELDS,['multi_language','status','slug'])) as $field){
 if(in_array($field,['groups','packages','language_ids','use_group_timer'],true)||in_array($field,$columns,true))continue;
 DB::statement('ALTER TABLE exams ADD COLUMN '.$field.' TEXT');
}
$routes->add((new Illuminate\Routing\Route(['GET'],'exams',fn()=>null))->name('exams.index'));
$payload=$service->newExam()['fields'];$payload['name']='Synthetic full exam';$payload['groups']=[$group->id];$payload['language_ids']=[$language->id];$payload['passing_percentage']=37.5;
$payload['category_level_1']=$category['id'];$payload['category_level_2']=$subcategory['id'];
$exam=$service->save($workspace,20,$actor,0,$payload,'new','exam-create','exams');
check((int)$exam['fields']['category_level_1']===$category['id']&&(int)$exam['fields']['category_level_2']===$subcategory['id'],'Native exam creation retains category assignment');
check((float)$exam['fields']['passing_percentage']===37.5,'Native create retains the exact pass threshold');
check($exam['fields']['groups']===[$group->id] && in_array($language->id,$exam['fields']['language_ids']),'Native exam scope and language saved');
$count=App\Models\Exam::count();$jobs=App\Jobs\ReconcileExamDocumentsForExamJob::$scheduled;
check($service->save($workspace,20,$actor,0,$payload,'new','exam-create','exams')===$exam && App\Models\Exam::count()===$count,'Exam create retry does not duplicate');
check(App\Jobs\ReconcileExamDocumentsForExamJob::$scheduled===$jobs,'Create retry does not schedule extra jobs');
$updated=$service->save($workspace,20,$actor,$exam['id'],['duration'=>75,'passing_percentage'=>42.25],$exam['revision'],'exam-update','exams');
check((int)$updated['fields']['duration']===75 && (float)$updated['fields']['passing_percentage']===42.25,'Native settings update retains duration and pass threshold');
$updated=$service->save($workspace,20,$actor,$exam['id'],['category_level_1'=>$category['id'],'category_level_2'=>$subcategory['id']],$updated['revision'],'exam-classification','exams');
check((int)$updated['fields']['category_level_1']===$category['id']&&(int)$updated['fields']['category_level_2']===$subcategory['id'],'Native exam category and subcategory assignment');
foreach([['category_level_1'=>$foreignCategory->id],['category_level_1'=>null,'category_level_2'=>$subcategory['id']],['category_level_2'=>$category['id']]] as $invalidCategory){
 try{$service->save($workspace,20,$actor,$exam['id'],$invalidCategory,$updated['revision'],'exam-bad-category-'.md5(json_encode($invalidCategory)),'exams');throw new RuntimeException('Expected category validation');}catch(Illuminate\Validation\ValidationException $e){}
 check($service->record('exams',App\Models\Exam::find($exam['id']))===$updated,'Invalid exam category does not change the paper');
}
try{$service->save($workspace,20,$actor,$exam['id'],['groups'=>[1]],$updated['revision'],'exam-foreign','exams');throw new RuntimeException('Expected foreign exam scope rejection');}catch(Illuminate\Validation\ValidationException|Illuminate\Database\Eloquent\ModelNotFoundException|Symfony\Component\HttpKernel\Exception\HttpException $e){}
check($service->record('exams',App\Models\Exam::find($exam['id']))===$updated,'Rejected exam scope leaves settings unchanged');
$app->instance('view',new Illuminate\View\Factory(new Illuminate\View\Engines\EngineResolver(),new Illuminate\View\FileViewFinder(new Illuminate\Filesystem\Filesystem(),[__DIR__]),new Illuminate\Events\Dispatcher($app)));
$app->instance('cache',new Illuminate\Cache\Repository(new Illuminate\Cache\ArrayStore()));
$withQuestion=$service->save($workspace,20,$actor,$exam['id'],['question_ids'=>[$q->id]],$updated['revision'],'exam-add','exams','add-questions');
check(App\Models\Exam::find($exam['id'])->questions()->count()===1,'Native question attachment');
check($service->save($workspace,20,$actor,$exam['id'],['question_ids'=>[$q->id]],$updated['revision'],'exam-add','exams','add-questions')===$withQuestion,'Question attachment retry');
try{$service->save($workspace,20,$actor,$exam['id'],['question_ids'=>[1,$q->id]],$withQuestion['revision'],'exam-mixed','exams','add-questions');throw new RuntimeException('Expected all-or-nothing selection rejection');}catch(Symfony\Component\HttpKernel\Exception\HttpException $e){check($e->getStatusCode()===422,'Foreign selection rejected');}
$removed=$service->save($workspace,20,$actor,$exam['id'],['question_ids'=>[$q->id]],$withQuestion['revision'],'exam-remove','exams','remove-questions');
check(App\Models\Exam::find($exam['id'])->questions()->count()===0 && App\Models\Question::find($q->id),'Removal preserves question bank');
check(App\Services\ExamDocumentInvalidationService::$calls===2,'Native paper invalidation occurs once per mutation');

$active=$service->save($workspace,20,$actor,$exam['id'],['status'=>'Active'],$removed['revision'],'activate','exams','set-status');
check($active['status']==='Active','Native activation');
check($service->save($workspace,20,$actor,$exam['id'],['status'=>'Active'],$removed['revision'],'activate','exams','set-status')===$active,'Activation retry does not toggle back');
$current=$service->save($workspace,20,$actor,$exam['id'],['status'=>'Active'],$active['revision'],'activate-again','exams','set-status');
$publicationBefore=$current;
$current=$service->save($workspace,20,$actor,$exam['id'],['result_after_finish'=>false],$current['revision'],'hide-results','exams','set-result-status');
check(!$current['fields']['result_after_finish'],'Native result hiding');
check($service->save($workspace,20,$actor,$exam['id'],['result_after_finish'=>false],$publicationBefore['revision'],'hide-results','exams','set-result-status')===$current,'Result publication retry does not toggle');
$current=$service->save($workspace,20,$actor,$exam['id'],['result_after_finish'=>false],$current['revision'],'hide-results-again','exams','set-result-status');
check(!$current['fields']['result_after_finish'],'Repeated desired state leaves results hidden');
$current=$service->save($workspace,20,$actor,$exam['id'],['result_after_finish'=>true],$current['revision'],'publish-results','exams','set-result-status');
check($current['fields']['result_after_finish'],'Native result publication');
try{$service->save($workspace,20,$actor,$exam['id'],['result_after_finish'=>'yes'],$current['revision'],'invalid-publication','exams','set-result-status');throw new RuntimeException('Expected boolean validation');}catch(Symfony\Component\HttpKernel\Exception\HttpException $e){}

check($current['status']==='Active','Desired publication status is idempotent with a new request');
$current=$service->save($workspace,20,$actor,$exam['id'],['name'=>'Part A','duration'=>30,'display_order'=>1],$current['revision'],'section-create','exams','create-section');
$sectionId=$current['sections'][0]['id'];
check(count($current['sections'])===1 && $current['sections'][0]['duration']===30,'Native section creation');
try{$service->save($workspace,20,$actor,$exam['id'],['name'=>'Too long','duration'=>60],$current['revision'],'section-overflow','exams','create-section');throw new RuntimeException('Expected total duration validation');}catch(Illuminate\Validation\ValidationException $e){}
check($service->record('exams',App\Models\Exam::find($exam['id']))===$current,'Overflow rejection leaves paper unchanged');
try{$service->save($workspace,20,$actor,$exam['id'],['section_id'=>1,'name'=>'Foreign','duration'=>1],$current['revision'],'foreign-section','exams','update-section');throw new RuntimeException('Expected section ownership rejection');}catch(Illuminate\Database\Eloquent\ModelNotFoundException $e){}
$current=$service->save($workspace,20,$actor,$exam['id'],['section_id'=>$sectionId,'name'=>'Part A renamed','duration'=>25],$current['revision'],'section-edit','exams','update-section');
check($current['sections'][0]['name']==='Part A renamed' && $current['sections'][0]['duration']===25,'Native section edit');
$q->subject_id=$subject['id'];$q->save();
$current=$service->save($workspace,20,$actor,$exam['id'],['question_ids'=>[$q->id]],$current['revision'],'re-add','exams','add-questions');
$beforeAssignment=$current;
$current=$service->save($workspace,20,$actor,$exam['id'],['question_ids'=>[$q->id],'question_section_id'=>$section['id']],$current['revision'],'assign-section','exams','assign-section');
check($current['revision']!==$beforeAssignment['revision'],'Assignment changes paper revision');
$assignedId=DB::table('exam_questions')->where('exam_id',$exam['id'])->value('exam_section_id');
check($assignedId && (int)$assignedId!==$sectionId,'Native assignment uses scoped section definition');
try{$service->save($workspace,20,$actor,$exam['id'],['subject_ids'=>[1],'durations'=>[5]],$current['revision'],'foreign-timer','exams','subject-timers');throw new RuntimeException('Expected foreign timer denial');}catch(Symfony\Component\HttpKernel\Exception\HttpException $e){}
try{$service->save($workspace,20,$actor,$exam['id'],['subject_ids'=>[$subject['id']],'durations'=>[-1]],$current['revision'],'negative-timer','exams','subject-timers');throw new RuntimeException('Expected invalid timer denial');}catch(Symfony\Component\HttpKernel\Exception\HttpException $e){}
$current=$service->save($workspace,20,$actor,$exam['id'],['subject_ids'=>[$subject['id']],'durations'=>[70]],$current['revision'],'subject-time','exams','subject-timers');
check($current['fields']['timer_mode']==='subject' && (int)$current['subject_durations'][0]['duration']===70,'Native subject timer');
try{$service->save($workspace,20,$actor,$exam['id'],['subject_ids'=>[$subject['id']],'durations'=>[80]],$current['revision'],'excess-timer','exams','subject-timers');throw new RuntimeException('Expected timer overflow rejection');}catch(Illuminate\Validation\ValidationException $e){}
$current=$service->save($workspace,20,$actor,$exam['id'],['section_id'=>$sectionId],$current['revision'],'remove-section','exams','remove-section');
check(!App\Models\ExamSection::find($sectionId),'Native empty section removal');
if(isset($argv[3])&&is_file(dirname($argv[3]).'/PackageController.php'))require dirname($argv[3]).'/PackageController.php';
$packageRestrictions=DB::table('tech4learn_workspaces')->where('id',$workspace)->value('restrictions');DB::table('tech4learn_workspaces')->where('id',$workspace)->update(['restrictions'=>'[]']);
$packageColumns=DB::getSchemaBuilder()->getColumnListing('packages');
foreach(array_merge(App\Services\Tech4LearnQuestionAuthoring::PACKAGE_FIELDS,['photo','created_at','updated_at']) as $field){if(in_array($field,['group_ids','tag_ids'],true)||in_array($field,$packageColumns,true))continue;DB::statement('ALTER TABLE packages ADD COLUMN '.$field.' TEXT');}
DB::statement('CREATE TABLE package_tags(id INTEGER PRIMARY KEY,organization_id INTEGER,name TEXT,slug TEXT,status INTEGER,created_at TEXT,updated_at TEXT)');
DB::statement('CREATE TABLE package_tag_package(package_id INTEGER,package_tag_id INTEGER,created_at TEXT,updated_at TEXT)');
$routes->add((new Illuminate\Routing\Route(['GET'],'packages',fn()=>null))->name('packages.index'));
$packageTag=App\Models\PackageTag::create(['organization_id'=>20,'name'=>'Synthetic tag','slug'=>'synthetic','status'=>true]);
$foreignPackageTag=App\Models\PackageTag::create(['organization_id'=>30,'name'=>'Foreign tag','slug'=>'foreign','status'=>true]);
$packageTagChoices=$controller->choices(Illuminate\Http\Request::create('/','GET'),$workspace,'package-tags');
check(in_array($packageTag->id,array_column($packageTagChoices['items'],'id'),true)&&!in_array($foreignPackageTag->id,array_column($packageTagChoices['items'],'id'),true),'Package tag choices stay scoped');
$packageFields=['name'=>'Synthetic package','package_type'=>'free','status'=>true,'group_ids'=>[$group->id],'tag_ids'=>[(string)$packageTag->id],'category_level_1'=>$category['id'],'category_level_2'=>$subcategory['id'],'pdf_title_text'=>'Preserve PDF title','show_pdf_download'=>true,'show_solution_pdf_download'=>false,'expiry_days'=>30];
$packageSaved=$service->save($workspace,20,$actor,0,$packageFields,'new','package-create','packages');
check($packageSaved['fields']['name']==='Synthetic package'&&$packageSaved['fields']['group_ids']===[$group->id],'Native package created with owned groups');
check($service->save($workspace,20,$actor,0,$packageFields,'new','package-create','packages')===$packageSaved,'Native package create replay');
$packageModel=App\Models\Package::findOrFail($packageSaved['id']);$packageModel->setTranslation('name','xx','Preserved translation');$packageModel->photo='uploads/package/synthetic.png';$packageModel->save();
$packageLinkedExam=App\Models\Exam::create(['organization_id'=>20,'name'=>'Synthetic linked paper','status'=>'Inactive']);
$packageModel->exams()->attach($packageLinkedExam->id,['display_order'=>7]);
$packageSaved=$service->record('packages',$packageModel->fresh());
$packageEdited=$service->save($workspace,20,$actor,$packageSaved['id'],['name'=>'Renamed package'],$packageSaved['revision'],'package-edit','packages');
check($packageEdited['fields']['pdf_title_text']==='Preserve PDF title'&&$packageEdited['fields']['show_solution_pdf_download']===false&&$packageEdited['fields']['tag_ids']===[(string)$packageTag->id],'Native package edit preserves documents and tags');
check((int)DB::table('exam_packages')->where('package_id',$packageSaved['id'])->value('display_order')===7&&$packageModel->fresh()->getTranslation('name','xx')==='Preserved translation'&&$packageModel->fresh()->photo==='uploads/package/synthetic.png','Package edit preserves linked exam ordering, translations and photo');
foreach([['group_ids'=>[1]],['tag_ids'=>[(string)$foreignPackageTag->id]],['category_level_1'=>$foreignCategory->id],['package_type'=>'paid'],['exam_id'=>[$packageLinkedExam->id]]] as $badPackage){
 try{$service->save($workspace,20,$actor,$packageSaved['id'],$badPackage,$packageEdited['revision'],'package-invalid-'.md5(json_encode($badPackage)),'packages');throw new RuntimeException('Expected package validation');}catch(Illuminate\Validation\ValidationException|Symfony\Component\HttpKernel\Exception\HttpException $e){}
 check($service->record('packages',$packageModel->fresh())===$packageEdited,'Rejected package edit has no partial changes');
}
DB::table('tech4learn_workspaces')->where('id',$workspace)->update(['restrictions'=>$packageRestrictions]);

echo "Native exam adapter: create, scope, languages, exact pass threshold, update and replay passed.\n";
}

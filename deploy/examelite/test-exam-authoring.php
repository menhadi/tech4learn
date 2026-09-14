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
DB::statement('ALTER TABLE languages ADD COLUMN is_enabled INTEGER DEFAULT 1');
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

echo "Native exam adapter: create, scope, languages, exact pass threshold, update and replay passed.\n";
}

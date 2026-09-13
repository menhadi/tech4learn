<?php
namespace App\Services {
 // Tracking and UI-language infrastructure only; attempt creation and marking are native.
 class StudentActivityTracker {
  const EXAM_STARTED='start',EXAM_SUBMITTED='submit';
  public static int $submissions=0;
  public static function track($event,...$args){if($event===self::EXAM_SUBMITTED)self::$submissions++;}
 }
 class UiLanguageService {public function supports(...$args){return false;}}
}
namespace {
function getConfiguration(){return null;}
require __DIR__.'/test-attempt-answers.php';
if(isset($argv[3]))foreach(['ExamGroupingService','StudentExamsController'] as $class)require dirname($argv[3]).'/'.$class.'.php';
require __DIR__.'/Tech4LearnStudentContext.php';
require __DIR__.'/Tech4LearnStudentAttempts.php';
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
if(!class_exists('DB'))class_alias(DB::class,'DB');
foreach(['email','phone','password','language'] as $column)DB::statement('ALTER TABLE students ADD COLUMN '.$column.' TEXT');
DB::statement('CREATE TABLE email_templates(id INTEGER PRIMARY KEY,key TEXT)');
DB::statement('CREATE TABLE student_groups(student_id INTEGER,group_id INTEGER)');
$app->instance('hash',new Illuminate\Hashing\BcryptHasher(['rounds'=>4]));
$app->instance('view',new class(new Illuminate\View\Engines\EngineResolver(),new Illuminate\View\FileViewFinder(new Illuminate\Filesystem\Filesystem(),[__DIR__]),new Illuminate\Events\Dispatcher($app)) extends Illuminate\View\Factory {
 public function make($view,$data=[],$mergeData=[]){return new Illuminate\View\View($this,new Illuminate\View\Engines\FileEngine(new Illuminate\Filesystem\Filesystem()),$view,__FILE__,$data);}
});
$app->instance(Illuminate\Contracts\Routing\ResponseFactory::class,new Illuminate\Routing\ResponseFactory($app['view'],$oldRedirect));
$routes->add((new Illuminate\Routing\Route(['GET'],'student/instructions/{id}',fn()=>null))->name('student.instructions'));
$routes->add((new Illuminate\Routing\Route(['GET'],'student/feedback',fn()=>null))->name('student.examFeedback'));
Carbon::setTestNow(Carbon::parse('2026-09-13 12:00:00','UTC'));
$paper->forceFill(['start_date'=>'2026-09-13 10:00:00','end_date'=>'2026-09-13 14:00:00','duration'=>60,'timer_mode'=>'none','proctor'=>false,'browser_tolerance'=>false,'attempt_count'=>1,'result_after_finish'=>true])->save();
$paper->questions()->sync([$q->id]);
$lifecycle=new App\Services\Tech4LearnStudentAttempts();
$newLearner='55555555-5555-5555-5555-555555555555';
$startId=$next();
$opened=$lifecycle->run($workspace,10,$newLearner,'Synthetic candidate',$paper->id,'start',['request_id'=>$startId]);
check($opened['attempt_id']>0&&count($opened['questions'])===1,'Native controller creates student attempt');
check(DB::table('student_groups')->count()===0,'Explicit paper access never enrols the student in all exam groups');
check($opened['questions'][0]['revision']===app(App\Services\Tech4LearnAttemptAnswers::class)->revision(App\Models\ExamStat::where('exam_result_id',$opened['attempt_id'])->first()),'First-load revision matches persisted row including defaults');
check(app('request')===$oldRequest&&app('redirect')===$oldRedirect&&$guard->user()===null,'Student context restores request and identity');
$resumed=$lifecycle->run($workspace,10,$newLearner,'Synthetic candidate',$paper->id,'start',['request_id'=>$next()]);
check($resumed['attempt_id']===$opened['attempt_id'],'Repeated start resumes native attempt');
Carbon::setTestNow(Carbon::parse('2026-09-13 12:00:10','UTC'));
$retryStart=$lifecycle->run($workspace,10,$newLearner,'Synthetic candidate',$paper->id,'start',['request_id'=>$startId]);
check($retryStart['remaining_seconds']<$opened['remaining_seconds'],'Start replay recalculates countdown instead of replaying stale time');
$paper->duration=90;$paper->save();
rejectAnswer(fn()=>$lifecycle->run($workspace,10,$newLearner,'Synthetic candidate',$paper->id,'start',['request_id'=>$next()]),'duration edit cannot silently extend attempt');
$paper->duration=60;$paper->save();
$answer=$lifecycle->run($workspace,10,$newLearner,'Synthetic candidate',$paper->id,'answer',['request_id'=>$next(),'attempt_id'=>$opened['attempt_id'],'question_id'=>$q->id,'fields'=>['option_selected'=>'7'],'revision'=>$opened['questions'][0]['revision']]);
check($answer['saved'],'Native lifecycle persists answer');
$submitId=$next();
$finished=$lifecycle->run($workspace,10,$newLearner,'Synthetic candidate',$paper->id,'submit',['request_id'=>$submitId,'attempt_id'=>$opened['attempt_id']]);
check($finished['completed']&&App\Services\StudentActivityTracker::$submissions===1,'Native controller finalises attempt once');
check($finished['result']['score_percent']===100.0&&$finished['result']['obtained_marks']===4.0,'Native numerical evaluator supplies the final score');
check($lifecycle->run($workspace,10,$newLearner,'Synthetic candidate',$paper->id,'submit',['request_id'=>$submitId,'attempt_id'=>$opened['attempt_id']])===$finished&&App\Services\StudentActivityTracker::$submissions===1,'Submission retry never marks twice');
rejectAnswer(fn()=>$lifecycle->run($workspace,10,$newLearner,'Synthetic candidate',$paper->id,'start',['request_id'=>$next()]),'attempt count exhausted');
DB::table('tech4learn_workspaces')->where('id',$workspace)->update(['restrictions'=>'["results"]']);
check($lifecycle->run($workspace,10,$newLearner,'Synthetic candidate',$paper->id,'submit',['request_id'=>$submitId,'attempt_id'=>$opened['attempt_id']])['result']===null,'Result restriction applies to retries');
DB::table('tech4learn_workspaces')->where('id',$workspace)->update(['restrictions'=>'[]']);
rejectAnswer(fn()=>$lifecycle->run($workspace,99,$newLearner,'Synthetic candidate',$paper->id,'submit',['request_id'=>$next(),'attempt_id'=>$opened['attempt_id']]),'foreign platform cannot read completed result');
rejectAnswer(fn()=>$lifecycle->run($workspace,10,$learner,'Synthetic candidate',$paper->id,'submit',['request_id'=>$next(),'attempt_id'=>$opened['attempt_id']]),'another learner cannot submit or read attempt');
rejectAnswer(fn()=>$lifecycle->run($workspace,10,$newLearner,'Synthetic candidate',$paper->id,'submit',['request_id'=>$next(),'attempt_id'=>$opened['attempt_id'],'learner_id'=>$learner]),'client identity override rejected');
$otherPaper=App\Models\Exam::where('organization_id',20)->where('id','!=',$paper->id)->first();
if(!$otherPaper){$otherPaper=$paper->replicate();$otherPaper->name='Another synthetic paper';$otherPaper->slug='another-synthetic-paper';$otherPaper->save();}
rejectAnswer(fn()=>$lifecycle->run($workspace,10,$newLearner,'Synthetic candidate',$otherPaper->id,'submit',['request_id'=>$next(),'attempt_id'=>$opened['attempt_id']]),'attempt cannot be substituted into another grant paper');
check(app('request')===$oldRequest&&$guard->user()===null,'Rejected requests preserve original identity');
$outerSession=new Illuminate\Session\Store('outer',new Illuminate\Session\ArraySessionHandler(5));$outerSession->start();$app->instance('session',$outerSession);
Illuminate\Support\Facades\Session::put('marker','outer');
rejectAnswer(fn()=>app(App\Services\Tech4LearnStudentContext::class)->run(20,$student,[],function(){check(Illuminate\Support\Facades\Session::get('marker')===null,'Private context does not inherit outer session');abort(409);}), 'native context failure');
check(app('session')===$outerSession&&Illuminate\Support\Facades\Session::get('marker')==='outer'&&app('request')===$oldRequest&&$guard->user()===null,'Failure restores session facade and request context');
$timeoutLearner='66666666-6666-6666-6666-666666666666';
$timed=$lifecycle->run($workspace,10,$timeoutLearner,'Synthetic timed candidate',$paper->id,'start',['request_id'=>$next()]);
Carbon::setTestNow(Carbon::parse('2026-09-13 14:01:00','UTC'));
$expired=$lifecycle->run($workspace,10,$timeoutLearner,'Synthetic timed candidate',$paper->id,'start',['request_id'=>$next()]);
check($expired['completed']&&$expired['attempt_id']===$timed['attempt_id'],'Closed paper resumes into native finalisation');
Carbon::setTestNow();
echo "Native student lifecycle: start, resume, answers, submission and restrictions passed.\n";
}

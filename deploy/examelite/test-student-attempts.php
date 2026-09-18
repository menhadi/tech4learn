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
if(isset($argv[3]))foreach(['ExamGroupingService','StudentExamsController'] as $class)require_once dirname($argv[3]).'/'.$class.'.php';
require __DIR__.'/Tech4LearnStudentContext.php';
require __DIR__.'/Tech4LearnStudentAttempts.php';
require_once __DIR__.'/Tech4LearnQuestionMedia.php';
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
rejectAnswer(fn()=>$lifecycle->run($workspace,10,$newLearner,'Synthetic candidate',$paper->id,'result',['request_id'=>$next(),'attempt_id'=>$opened['attempt_id']]),'Result read cannot submit an active attempt');
check(!App\Models\ExamResult::find($opened['attempt_id'])->end_time,'Result read leaves active attempt unchanged');
$submitId=$next();
$finished=$lifecycle->run($workspace,10,$newLearner,'Synthetic candidate',$paper->id,'submit',['request_id'=>$submitId,'attempt_id'=>$opened['attempt_id']]);
check($finished['completed']&&App\Services\StudentActivityTracker::$submissions===1,'Native controller finalises attempt once');
$beforeResultRead=App\Models\ExamResult::count();
$paper->result_after_finish=false;$paper->save();
check($lifecycle->run($workspace,10,$newLearner,'Synthetic candidate',$paper->id,'result',['request_id'=>$next(),'attempt_id'=>$opened['attempt_id']])['result']===null,'Hidden result stays private on refresh');
$paper->result_after_finish=true;$paper->save();
check($lifecycle->run($workspace,10,$newLearner,'Synthetic candidate',$paper->id,'result',['request_id'=>$next(),'attempt_id'=>$opened['attempt_id']])===$finished,'Published result can be refreshed');
check(App\Models\ExamResult::count()===$beforeResultRead&&App\Services\StudentActivityTracker::$submissions===1,'Result refresh creates and grades no attempt');

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
Carbon::setTestNow(Carbon::parse('2026-09-13 12:05:00','UTC'));
$beforeCount=App\Models\ExamResult::count();$beforeStudents=App\Models\Student::count();
$originalText=$q->fresh()->question;
foreach(['<svg><path/></svg>','<iframe src="/not-an-image"></iframe>','<math><menclose notation="circle"><mi>x</mi></menclose></math>'] as $unsupported){
 $q->question=$unsupported;$q->save();
 rejectAnswer(fn()=>$lifecycle->run($workspace,10,'77777777-7777-7777-7777-777777777777','Synthetic media candidate',$paper->id,'start',['request_id'=>$next()]),'unsupported display rejected');
 check(App\Models\ExamResult::count()===$beforeCount&&App\Models\Student::count()===$beforeStudents,'Unsupported display rolls back attempt and student provisioning');
}
$q->question=$originalText;$q->save();
$png='iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Y9ZQmcAAAAASUVORK5CYII=';
$imageSource='data:image/png;base64,'.$png;$asset=hash('sha256',$imageSource);
$q->question='<p>Diagram <img src="'.$imageSource.'" srcset="https://evil.test/leak" onerror="alert(1)"></p>';$q->explanation='<img src="/storage/question-images/private-answer.png">';$q->save();
$imageLearner='99999999-9999-9999-9999-999999999999';
$imageAttempt=$lifecycle->run($workspace,10,$imageLearner,'Synthetic image candidate',$paper->id,'start',['request_id'=>$next()]);
$imageHtml=$imageAttempt['questions'][0]['content']['question'];
check(str_contains($imageHtml,'t4l-media:'.$asset)&&!str_contains($imageHtml,'evil.test')&&!str_contains($imageHtml,'onerror')&&!str_contains($imageHtml,$png),'Image projection exposes only a scoped hash, never raw source or handlers');
$imageFields=['request_id'=>$next(),'attempt_id'=>$imageAttempt['attempt_id'],'question_id'=>$q->id,'asset'=>$asset];
$image=$lifecycle->run($workspace,10,$imageLearner,'Synthetic image candidate',$paper->id,'media',$imageFields);
check($image['mime']==='image/png'&&$image['base64']===$png,'Only referenced validated raster bytes are returned');
rejectAnswer(fn()=>$lifecycle->run($workspace,10,$learner,'Other learner',$paper->id,'media',$imageFields),'another student cannot read this attempt image');
$privateImage=$imageFields;$privateImage['asset']=hash('sha256','/storage/question-images/private-answer.png');
rejectAnswer(fn()=>$lifecycle->run($workspace,10,$imageLearner,'Synthetic image candidate',$paper->id,'media',$privateImage),'explanation images are never student question images');
$reader=new class extends App\Services\Tech4LearnQuestionMedia {public function testSource($source){return $this->bytes($source);}};
foreach(['file:///etc/passwd','https://evil.test/storage/question-images/a.png','/storage/question-images/%2e%2e/%2e%2e/private.png','/storage/learner-photos/private.png','javascript:alert(1)'] as $bad)rejectAnswer(fn()=>$reader->testSource($bad),'unsafe image source');
$q->question=$originalText;$q->save();
$beforeOptions=$q->only(['qtype_id','option1','option2','option3','option4','option5','option6']);
$q->forceFill(['qtype_id'=>1,'option1'=>'Synthetic first','option2'=>'Synthetic second','option3'=>'','option4'=>'','option5'=>'','option6'=>''])->save();
$paper->forceFill(['calculator_allowed'=>true,'option_shuffle'=>true])->save();
$controlsLearner='99999999-9999-9999-9999-999999999999';
$controlsAttempt=$lifecycle->run($workspace,10,$controlsLearner,'Synthetic controls candidate',$paper->id,'start',['request_id'=>$next()]);
check($controlsAttempt['settings']['calculator_allowed']&&$controlsAttempt['settings']['option_shuffle'],'Native calculator and shuffle settings are accepted');
$order=$controlsAttempt['questions'][0]['option_order'];sort($order);check($order===[1,2],'Shuffled options preserve original answer IDs and exclude empty choices');
$controlAnswer=$lifecycle->run($workspace,10,$controlsLearner,'Synthetic controls candidate',$paper->id,'answer',['request_id'=>$next(),'attempt_id'=>$controlsAttempt['attempt_id'],'question_id'=>$q->id,'revision'=>$controlsAttempt['questions'][0]['revision'],'fields'=>['option_selected'=>[2]]]);
check($controlAnswer['saved'],'Shuffled option is saved through native answer persistence');
$controlResume=$lifecycle->run($workspace,10,$controlsLearner,'Synthetic controls candidate',$paper->id,'start',['request_id'=>$next()]);
check(array_map('intval',$controlResume['questions'][0]['answer'])===[2],'Reshuffling on resume keeps the saved answer identity');
$q->forceFill($beforeOptions)->save();$paper->forceFill(['calculator_allowed'=>false,'option_shuffle'=>false])->save();
// Section time is captured from the native allocations, not the browser clock.
Carbon::setTestNow(Carbon::parse('2026-09-13 12:10:00','UTC'));
$timedPaper=$paper->replicate();$timedPaper->forceFill(['name'=>'Synthetic timed paper','duration'=>10,'timer_mode'=>'section','grouping_mode'=>'section','is_subject_timer'=>true])->save();
$secondQuestion=$q->replicate();$secondQuestion->save();
$sectionOne=App\Models\ExamSection::create(['exam_id'=>$timedPaper->id,'name'=>'First section','display_order'=>1,'duration'=>1]);
$sectionTwo=App\Models\ExamSection::create(['exam_id'=>$timedPaper->id,'name'=>'Second section','display_order'=>2,'duration'=>2]);
$timedPaper->questions()->sync([$q->id=>['exam_section_id'=>$sectionOne->id],$secondQuestion->id=>['exam_section_id'=>$sectionTwo->id]]);
$timedLearner='aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa';
$timed=$lifecycle->run($workspace,10,$timedLearner,'Synthetic timed candidate',$timedPaper->id,'start',['request_id'=>$next()]);
check($timed['section_clock']['active']['questions']===[$q->id]&&$timed['section_clock']['active']['remaining_seconds']===60&&$timed['remaining_seconds']===180,'Native section allocations set the active group and total time');
$saveTimed=function($questionId,$revision,$requestId)use($lifecycle,$workspace,$timedLearner,$timedPaper,$timed){return $lifecycle->run($workspace,10,$timedLearner,'Synthetic timed candidate',$timedPaper->id,'answer',['request_id'=>$requestId,'attempt_id'=>$timed['attempt_id'],'question_id'=>$questionId,'revision'=>$revision,'fields'=>['option_selected'=>'7']]);};
rejectAnswer(fn()=>$saveTimed($secondQuestion->id,$timed['questions'][1]['revision'],$next()),'future timed section cannot be answered');
$acceptedRequest=$next();$accepted=$saveTimed($q->id,$timed['questions'][0]['revision'],$acceptedRequest);
Carbon::setTestNow(Carbon::parse('2026-09-13 12:10:30','UTC'));
$sectionOne->duration=8;$sectionOne->save();
$timedResume=$lifecycle->run($workspace,10,$timedLearner,'Synthetic timed candidate',$timedPaper->id,'start',['request_id'=>$next()]);
check($timedResume['section_clock']['active']['remaining_seconds']===30,'Resume and later duration edits do not restart a section');
Carbon::setTestNow(Carbon::parse('2026-09-13 12:11:00','UTC'));
rejectAnswer(fn()=>$saveTimed($q->id,$accepted['revision'],$next()),'expired timed section cannot be answered');
check($saveTimed($q->id,$timed['questions'][0]['revision'],$acceptedRequest)===$accepted,'Accepted answer replay survives section expiry');
$nextSection=$lifecycle->run($workspace,10,$timedLearner,'Synthetic timed candidate',$timedPaper->id,'start',['request_id'=>$next()]);
check($nextSection['section_clock']['active']['questions']===[$secondQuestion->id]&&$nextSection['section_clock']['active']['remaining_seconds']===120,'Server advances at the exact section boundary');
$saveTimed($secondQuestion->id,$nextSection['questions'][1]['revision'],$next());
$timedPaper->timer_mode='none';$timedPaper->save();
rejectAnswer(fn()=>$lifecycle->run($workspace,10,$timedLearner,'Synthetic timed candidate',$timedPaper->id,'start',['request_id'=>$next()]),'changing timer mode cannot bypass captured schedule');
$timedPaper->timer_mode='section';$timedPaper->save();
Carbon::setTestNow(Carbon::parse('2026-09-13 12:13:00','UTC'));
$timedEnd=$lifecycle->run($workspace,10,$timedLearner,'Synthetic timed candidate',$timedPaper->id,'start',['request_id'=>$next()]);
check($timedEnd['completed'],'Native finalisation runs after the last section ends');
Carbon::setTestNow(Carbon::parse('2026-09-13 12:20:00','UTC'));
$subjectPaper=$paper->replicate();$subjectPaper->forceFill(['name'=>'Synthetic subject paper','duration'=>10,'timer_mode'=>'subject','grouping_mode'=>'subject','is_subject_timer'=>true])->save();
$subjectPaper->questions()->sync([$q->id]);
$subjectLearner='bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb';
$subjectAttempt=$lifecycle->run($workspace,10,$subjectLearner,'Synthetic subject candidate',$subjectPaper->id,'start',['request_id'=>$next()]);
check($subjectAttempt['section_clock']['mode']==='subject'&&$subjectAttempt['section_clock']['active']['remaining_seconds']===600,'Native automatic subject allocation is captured');
Carbon::setTestNow(Carbon::parse('2026-09-13 12:20:30','UTC'));
$subjectPaper->end_date='2026-09-13 12:20:40';$subjectPaper->save();
$subjectResume=$lifecycle->run($workspace,10,$subjectLearner,'Synthetic subject candidate',$subjectPaper->id,'start',['request_id'=>$next()]);
check($subjectResume['remaining_seconds']===10&&$subjectResume['section_clock']['active']['remaining_seconds']===10,'Earlier exam closure caps both overall and subject countdowns');
$clockRow=(array)DB::table('tech4learn_attempt_clocks')->where('attempt_id',$subjectAttempt['attempt_id'])->first();
DB::table('tech4learn_attempt_clocks')->where('attempt_id',$subjectAttempt['attempt_id'])->delete();
rejectAnswer(fn()=>$lifecycle->run($workspace,10,$subjectLearner,'Synthetic subject candidate',$subjectPaper->id,'start',['request_id'=>$next()]),'older timed attempts cannot invent a replacement schedule');
DB::table('tech4learn_attempt_clocks')->insert($clockRow);
Carbon::setTestNow(Carbon::parse('2026-09-13 12:20:40','UTC'));
check($lifecycle->run($workspace,10,$subjectLearner,'Synthetic subject candidate',$subjectPaper->id,'start',['request_id'=>$next()])['completed'],'Closing time reaches native finalisation before subject allocation ends');

Carbon::setTestNow(Carbon::parse('2026-09-13 12:30:00','UTC'));
$browserPaper=$paper->replicate();$browserPaper->forceFill(['name'=>'Synthetic browser paper','browser_tolerance'=>true,'tolerance_count'=>2,'timer_mode'=>'none'])->save();$browserPaper->questions()->sync([$q->id]);
$browserLearner='cccccccc-cccc-cccc-cccc-cccccccccccc';
$browserAttempt=$lifecycle->run($workspace,10,$browserLearner,'Synthetic browser candidate',$browserPaper->id,'start',['request_id'=>$next()]);
$event=['request_id'=>$next(),'attempt_id'=>$browserAttempt['attempt_id'],'event'=>'hidden'];
$eventReply=$lifecycle->run($workspace,10,$browserLearner,'Synthetic browser candidate',$browserPaper->id,'visibility',$event);
check($eventReply['tolerance_count']===1&&!$eventReply['completed'],'Native browser counter increments once');
check($lifecycle->run($workspace,10,$browserLearner,'Synthetic browser candidate',$browserPaper->id,'visibility',$event)===$eventReply,'Visibility retry cannot double-count a tab leave');
rejectAnswer(fn()=>$lifecycle->run($workspace,10,$browserLearner,'Synthetic browser candidate',$browserPaper->id,'visibility',$event+['tolerance_count'=>0]),'Browser aggregate counts are rejected');
rejectAnswer(fn()=>$lifecycle->run($workspace,10,$newLearner,'Synthetic foreign candidate',$browserPaper->id,'visibility',$event),'Visibility events cannot target another student');
$event['request_id']=$next();$browserEnd=$lifecycle->run($workspace,10,$browserLearner,'Synthetic browser candidate',$browserPaper->id,'visibility',$event);
check($browserEnd['completed']&&(int)App\Models\ExamResult::find($browserAttempt['attempt_id'])->tolerance_count===2,'Native submission occurs at the configured limit');
check($lifecycle->run($workspace,10,$browserLearner,'Synthetic browser candidate',$browserPaper->id,'visibility',$event)['completed'],'Final event retries do not reopen the exam');
$legacyBrowserLearner='dddddddd-dddd-dddd-dddd-dddddddddddd';
$legacyBrowser=$lifecycle->run($workspace,10,$legacyBrowserLearner,'Synthetic existing count',$browserPaper->id,'start',['request_id'=>$next()]);
App\Models\ExamResult::where('id',$legacyBrowser['attempt_id'])->update(['tolerance_count'=>3]);
check($lifecycle->run($workspace,10,$legacyBrowserLearner,'Synthetic existing count',$browserPaper->id,'visibility',['request_id'=>$next(),'attempt_id'=>$legacyBrowser['attempt_id'],'event'=>'hidden'])['completed'],'An existing over-limit count finalises');
check((int)App\Models\ExamResult::find($legacyBrowser['attempt_id'])->tolerance_count===3,'A lower configured limit never decreases the native counter');

Carbon::setTestNow();
require_once __DIR__.'/Tech4LearnPlatformController.php';
DB::table('organizations')->updateOrInsert(['id'=>10],['domain'=>'central.example.test','status'=>'active']);
require __DIR__.'/Tech4LearnStudentController.php';
$credential=bin2hex(random_bytes(32));$configurationFile=tempnam(sys_get_temp_dir(),'t4l-student-test-');
file_put_contents($configurationFile,json_encode(['_platform'=>['enabled'=>true,'organization_id'=>10,'token_hash'=>hash('sha256',$credential)]]));
$controller=new class($configurationFile) extends App\Http\Controllers\Tech4LearnStudentController {
 public function __construct(private string $path){}protected function configPath():string{return $this->path;}
};
try {
 $body=['learner_id'=>$newLearner,'name'=>'Synthetic candidate','exam_id'=>$paper->id,'fields'=>['request_id'=>$next(),'attempt_id'=>$opened['attempt_id']]];
 $makeRequest=fn($token,$data)=>Illuminate\Http\Request::create('https://central.example.test/api/tech4learn/v1/student/'.$workspace.'/submit','POST',[],[],[],['HTTP_AUTHORIZATION'=>'Bearer '.$token,'CONTENT_TYPE'=>'application/json'],json_encode($data));
 rejectAnswer(fn()=>$controller->attempt($makeRequest(str_repeat('0',64),$body),$workspace,'submit'),'student native endpoint requires central credential');
 $reply=$controller->attempt($makeRequest($credential,$body),$workspace,'submit')->getData(true);
 check($reply['organization_id']===10&&$reply['data']['completed'],'Credential-authenticated controller returns scoped native result');
 $foreignBody=$body;$foreignBody['learner_id']=$learner;
 $denied=$controller->attempt($makeRequest($credential,$foreignBody),$workspace,'submit')->getData(true);
 check($denied['error']['status']===404&&!isset($denied['data']),'Native controller converts ownership error into minimal public code');
 Carbon::setTestNow(Carbon::parse('2026-09-13 12:05:00','UTC'));
 $blankLearner='88888888-8888-8888-8888-888888888888';
 $blankAttempt=$lifecycle->run($workspace,10,$blankLearner,'Synthetic blank candidate',$paper->id,'start',['request_id'=>$next()]);
 $blankBody=['learner_id'=>$blankLearner,'name'=>'Synthetic blank candidate','exam_id'=>$paper->id,'fields'=>['request_id'=>$next(),'attempt_id'=>$blankAttempt['attempt_id'],'question_id'=>$q->id,'revision'=>$blankAttempt['questions'][0]['revision'],'fields'=>['option_selected'=>'']]];
 $blankRequest=$makeRequest($credential,$blankBody);$blankRequest->merge(['fields'=>null]);
 $blankReply=$controller->attempt($blankRequest,$workspace,'answer')->getData(true);
 check($blankReply['data']['saved'],'Raw JSON preserves a cleared answer despite global request normalisation');
 Carbon::setTestNow();
}finally{unlink($configurationFile);}
$router=new Illuminate\Routing\Router(new Illuminate\Events\Dispatcher($app),$app);$app->instance('router',$router);Illuminate\Support\Facades\Route::clearResolvedInstance('router');
$router->prefix('api')->middleware('api')->group(function(){require __DIR__.'/tech4learn-routes.php';});
$studentRoute=$router->getRoutes()->match(Illuminate\Http\Request::create('https://central.example.test/api/tech4learn/v1/student/'.$workspace.'/answer','POST'));
check(in_array('throttle:6000,1,t4l-student:',$studentRoute->gatherMiddleware(),true)&&in_array('throttle:api',$studentRoute->excludedMiddleware(),true),'Student route has a separate prefixed limit instead of the inherited shared-IP API bucket');
$authorRoute=$router->getRoutes()->match(Illuminate\Http\Request::create('https://central.example.test/api/tech4learn/v1/authoring/'.$workspace.'/questions/1','GET'));
check(in_array('throttle:30,1',$authorRoute->gatherMiddleware(),true)&&!in_array('throttle:api',$authorRoute->excludedMiddleware(),true),'Authoring rate limits remain intact');
$beforeHistoryCount=App\Models\ExamResult::count();$beforeHistoryStudents=App\Models\Student::count();$beforeHistorySubmissions=App\Services\StudentActivityTracker::$submissions;
$history=$lifecycle->run($workspace,10,$newLearner,'Synthetic candidate',$paper->id,'history',['request_id'=>$next()]);
check(count($history['items'])>0&&count($history['items'])<=50,'Submitted history is available without starting');
foreach($history['items'] as $row)check($row['exam_id']===$paper->id&&$row['completed']===true&&isset($row['finished_at']),'History stays within the granted paper');
$unknownHistory=$lifecycle->run($workspace,10,$next(),'Synthetic unmapped',$paper->id,'history',['request_id'=>$next()]);
check($unknownHistory['items']===[]&&App\Models\ExamResult::count()===$beforeHistoryCount&&App\Models\Student::count()===$beforeHistoryStudents&&App\Services\StudentActivityTracker::$submissions===$beforeHistorySubmissions,'History has no identity, attempt or grading writes');
$published=$paper->result_after_finish;$paper->result_after_finish=false;$paper->save();
$hiddenHistory=$lifecycle->run($workspace,10,$newLearner,'Synthetic candidate',$paper->id,'history',['request_id'=>$next()]);
foreach($hiddenHistory['items'] as $row)check($row['result']===null,'History respects hidden results');
$paper->result_after_finish=$published;$paper->save();
rejectAnswer(fn()=>$lifecycle->run($workspace,99,$newLearner,'Synthetic candidate',$paper->id,'history',['request_id'=>$next()]),'Foreign source cannot read history');
echo "Native student lifecycle: start, resume, answers, submission and restrictions passed.\n";
}

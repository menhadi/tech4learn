<?php
// Isolated SQLite records and the unmodified native ResultController.
require __DIR__.'/test-student-attempts.php';
if(isset($argv[3]))require dirname($argv[3]).'/ResultController.php';
require __DIR__.'/Tech4LearnResultMarking.php';
require __DIR__.'/Tech4LearnResultController.php';
use Illuminate\Support\Facades\DB;
use App\Models\{ExamResult,ExamStat};
$app->instance('auth',new class($guard){function __construct(private $guard){}function guard($name){return $this->guard;}function user(){return $this->guard->user();}});
Illuminate\Support\Facades\Facade::clearResolvedInstance('auth');
$routes->add((new Illuminate\Routing\Route(['GET'],'results',fn()=>null))->name('results.index'));
DB::statement('CREATE TABLE exam_result_details(id INTEGER PRIMARY KEY,organization_id INTEGER,exam_result_id INTEGER)');
$marking=new App\Services\Tech4LearnResultMarking();
$candidate=DB::table('tech4learn_workspace_users')->where('workspace_id',$workspace)->where('local_id',$newLearner)->where('kind','student')->value('external_id');
$manual=ExamResult::create(['organization_id'=>20,'student_id'=>$candidate,'exam_id'=>$paper->id,'start_time'=>'2026-09-13 12:00:00','end_time'=>'2026-09-13 12:30:00','total_marks'=>10,'obtained_marks'=>0,'percent'=>0,'result'=>'Pending']);
$pending=ExamStat::create(['organization_id'=>20,'student_id'=>$candidate,'exam_id'=>$paper->id,'exam_result_id'=>$manual->id,'question_id'=>$q->id,'marks'=>10,'marks_obtained'=>0,'ques_status'=>'P','answer'=>'Synthetic written answer']);
DB::table('exam_result_details')->insert(['organization_id'=>20,'exam_result_id'=>$manual->id]);
DB::table('exam_result_details')->insert(['organization_id'=>99,'exam_result_id'=>$manual->id]);
$review=$marking->review($workspace,10,$actor,$newLearner,$manual->id);
check(count($review['questions'])===1&&$review['questions'][0]['answer_text']==='Synthetic written answer','Pending answer available to scoped marker');
$extra=$pending->replicate();$extra->save();
$two=$marking->review($workspace,10,$actor,$newLearner,$manual->id);
rejectAnswer(fn()=>$marking->save($workspace,10,$actor,$newLearner,$manual->id,[$pending->id=>5],$two['revision'],$next()),'Partial pending marking cannot finalise the result');
$extra->delete();
$save=fn($marks,$revision=null,$request=null)=>$marking->save($workspace,10,$actor,$newLearner,$manual->id,$marks,$revision??$review['revision'],$request??$next());
foreach([[],[$pending->id=>11],[$pending->id=>-1],[$pending->id=>'5'],[$pending->id=>INF],[$pending->id+100=>5]] as $invalid)rejectAnswer(fn()=>$save($invalid),'Invalid or foreign marking');
rejectAnswer(fn()=>$save([$pending->id=>5],str_repeat('a',64)),'Stale marking revision');
rejectAnswer(fn()=>$marking->review($workspace,99,$actor,$newLearner,$manual->id),'Foreign source');
rejectAnswer(fn()=>$marking->review($workspace,10,$actor,$learner,$manual->id),'Foreign learner');
rejectAnswer(fn()=>$marking->review($workspace,10,$newLearner,$newLearner,$manual->id),'Learner cannot mark');
$manual->end_time=null;$manual->save();
rejectAnswer(fn()=>$marking->review($workspace,10,$actor,$newLearner,$manual->id),'Active attempt cannot be marked');
$manual->end_time='2026-09-13 12:30:00';$manual->save();
$review=$marking->review($workspace,10,$actor,$newLearner,$manual->id);
DB::table('organization_users')->where('user_id',1)->update(['status'=>0]);
rejectAnswer(fn()=>$marking->review($workspace,10,$actor,$newLearner,$manual->id),'Inactive marker membership');
DB::table('organization_users')->where('user_id',1)->update(['status'=>1]);
$app->instance(App\Http\Controllers\ResultController::class,new class extends App\Http\Controllers\ResultController {
 public function saveEvaluation(Illuminate\Http\Request $request,$id){ExamResult::where('id',$id)->update(['percent'=>99]);throw new RuntimeException('Synthetic native failure');}
});
try{$marking->save($workspace,10,$actor,$newLearner,$manual->id,[$pending->id=>5],$review['revision'],$next());throw new LogicException('Expected native failure');}
catch(RuntimeException $error){check($error->getMessage()==='Synthetic native failure','Native failure surfaced');}
$app->forgetInstance(App\Http\Controllers\ResultController::class);
check((float)$manual->fresh()->percent===0.0&&$pending->fresh()->ques_status==='P','Native failure rolls back result');
check(app('request')===$oldRequest&&app('redirect')===$oldRedirect&&$guard->user()===null,'Failed marking restores native context');
$request=$next();$marks=[$pending->id=>5];
$graded=$marking->save($workspace,10,$actor,$newLearner,$manual->id,$marks,$review['revision'],$request);
check($graded['score_percent']===50.0&&$graded['obtained_marks']===5.0&&$pending->fresh()->ques_status==='R','Native controller calculates marks and status');
check(DB::table('exam_result_details')->where('organization_id',20)->count()===0&&DB::table('exam_result_details')->where('organization_id',99)->count()===1,'Native report invalidation stays scoped');
check($marking->save($workspace,10,$actor,$newLearner,$manual->id,$marks,$review['revision'],$request)===$graded,'Lost response retry returns saved grading');
rejectAnswer(fn()=>$marking->save($workspace,10,$actor,$newLearner,$manual->id,[$pending->id=>6],$review['revision'],$request),'Request cannot be reused with changed marks');
DB::table('tech4learn_workspaces')->where('id',$workspace)->update(['restrictions'=>'["results"]']);
rejectAnswer(fn()=>$marking->save($workspace,10,$actor,$newLearner,$manual->id,$marks,$review['revision'],$request),'Revocation rejects even successful retries');
DB::table('tech4learn_workspaces')->where('id',$workspace)->update(['restrictions'=>'[]']);
check(app('request')===$oldRequest&&app('redirect')===$oldRedirect&&$guard->user()===null,'Native marking context restored');
check($marking->review($workspace,10,$actor,$newLearner,$manual->id)['questions']===[],'Graded answers leave pending queue');
$history=$marking->attempts($workspace,10,$actor,$newLearner);
check(count(array_filter($history['items'],fn($row)=>$row['attempt_id']===$manual->id&&$row['pending_count']===0))===1,'History contains scoped completed result');
check($marking->attempts($workspace,10,$actor,$next())===['items'=>[],'next'=>null],'Unmapped learner has empty results without provisioning');
$configFile=tempnam(sys_get_temp_dir(),'t4l-marking-');$token=bin2hex(random_bytes(32));
file_put_contents($configFile,json_encode(['_platform'=>['enabled'=>true,'organization_id'=>10,'token_hash'=>hash('sha256',$token)]]));
$controller=new class($configFile) extends App\Http\Controllers\Tech4LearnResultController {public function __construct(private string $file){}protected function configPath():string{return $this->file;}};
$gradingRequestId=$request;
try {
 $url='https://central.example.test/api/tech4learn/v1/results/'.$workspace.'/learners/'.$newLearner.'/attempts';
 $request=Illuminate\Http\Request::create($url.'?actor_id='.$actor,'GET',[],[],[],['HTTP_AUTHORIZATION'=>'Bearer '.$token]);
 $reply=$controller->attempts($request,$workspace,$newLearner);
 check(str_contains($reply->headers->get('Cache-Control'),'no-store')&&count($reply->getData(true)['items'])===count($history['items']),'Credential result history is private');
 $missing=Illuminate\Http\Request::create($url.'?actor_id='.$actor,'GET');
 rejectAnswer(fn()=>$controller->attempts($missing,$workspace,$newLearner),'Missing result credential');
 $body=['actor_id'=>$actor,'marks'=>$marks,'revision'=>$review['revision'],'request_id'=>$gradingRequestId];
 $write=Illuminate\Http\Request::create($url.'/'.$manual->id,'POST',[],[],[],['HTTP_AUTHORIZATION'=>'Bearer '.$token,'CONTENT_TYPE'=>'application/json'],json_encode($body));
 check($controller->save($write,$workspace,$newLearner,(string)$manual->id)->getData(true)['saved']===true,'Credential marking route replays successful native result');
 $bad=Illuminate\Http\Request::create($url.'/'.$manual->id,'POST',[],[],[],['HTTP_AUTHORIZATION'=>'Bearer '.$token,'CONTENT_TYPE'=>'application/json'],json_encode($body+['organization_id'=>99]));
 rejectAnswer(fn()=>$controller->save($bad,$workspace,$newLearner,(string)$manual->id),'Unknown write fields rejected');
}finally{unlink($configFile);}
echo "Scoped native result marking, validation and retries passed.\n";

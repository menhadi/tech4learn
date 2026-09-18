<?php
// Included by test-pdf-worker.php: synthetic database/files only, no application bootstrap.
// Exercise Laravel's actual payload, reservation, handler and delayed-release transport.
use Illuminate\Support\Facades\DB;
use Illuminate\Queue\{DatabaseQueue,CallQueuedHandler};
use App\Jobs\GenerateExamPdfJob;

DB::statement('CREATE TABLE jobs(id INTEGER PRIMARY KEY AUTOINCREMENT,queue TEXT,payload TEXT,attempts INTEGER,reserved_at INTEGER,available_at INTEGER,created_at INTEGER)');
$queue=new DatabaseQueue(DB::connection(),'jobs','pdf-fixture',400);
$queue->setContainer($app);$queue->setConnectionName('synthetic');
$dispatcher=new Illuminate\Bus\Dispatcher($app);
$app->instance(CallQueuedHandler::class,new CallQueuedHandler($dispatcher,$app));
$app->instance(App\Services\ExamDocumentLifecycleService::class,$lifecycle);
$paper->languages()->updateExistingPivot($lang->id,['translation_approved_at'=>now()]);
$build->refresh()->update(['status'=>'queued']);
$queue->push(new GenerateExamPdfJob($build->id));
check($queue->size()===1&&$build->fresh()->status==='queued','Enqueue alone does not complete a PDF');
$reserved=$queue->pop();
check($reserved!==null&&$reserved->attempts()===1&&$queue->pop()===null,'Database reservation prevents immediate duplicate consumption');
$payload=json_decode($reserved->getRawBody(),true,512,JSON_THROW_ON_ERROR);
check($payload['displayName']===GenerateExamPdfJob::class&&$payload['maxTries']===3&&$payload['timeout']===360,'Native job settings survive queue serialization');
$reserved->fire();
check($reserved->isDeleted()&&$queue->size()===0&&$build->fresh()->status==='ready','Native queued handler completes and acknowledges cached PDF');
check(file_get_contents($directory.'/current.pdf')===$pdf,'Queued completion preserves expected bytes');

$queue->push(new GenerateExamPdfJob($build->id));$locks->available=false;
$reserved=$queue->pop();$releasedBefore=$locks->released;$reserved->fire();
check($reserved->isReleased()&&$queue->size()===1&&$queue->pop()===null,'Contended build is delayed, not acknowledged or immediately retried');
check($locks->released===$releasedBefore&&file_get_contents($directory.'/current.pdf')===$pdf,'Contended worker does not release another lock or change the PDF');
$locks->available=true;
DB::table('jobs')->update(['available_at'=>time()-1]);
$retried=$queue->pop();check($retried->attempts()===2,'Delayed retry retains attempt count');$retried->fire();
check($queue->size()===0&&$build->fresh()->status==='ready','Released native job completes on retry');

$paper->languages()->updateExistingPivot($lang->id,['translation_approved_at'=>null]);
$queue->push(new GenerateExamPdfJob($build->id));$denied=$queue->pop();
try{$denied->fire();throw new RuntimeException('Expected queued approval denial');}
catch(RuntimeException $e){check($e->getMessage()==='Translation is not approved.','Queued execution rechecks approval');}
check(!$denied->isDeleted()&&$queue->size()===1&&$build->fresh()->status==='failed','Failed queued work remains unacknowledged');
check(file_get_contents($directory.'/current.pdf')===$pdf,'Queue failure preserves previous PDF');
// Deliberately release here; the production worker's backoff/failure loop is not simulated.
$denied->release(0);
$paper->languages()->updateExistingPivot($lang->id,['translation_approved_at'=>now()]);
$recovered=$queue->pop();check($recovered->attempts()===2,'Failed transport retry retains attempt count');$recovered->fire();
check($queue->size()===0&&$build->fresh()->status==='ready','Approved queued retry recovers');
echo "Native PDF database queue: serialization, reservation, completion, contention delay and approval retry passed.\n";

// Exercise the actual Worker exception policy, rather than manually releasing jobs.
$events=new Illuminate\Events\Dispatcher($app);
$app->instance(Illuminate\Contracts\Events\Dispatcher::class,$events);
$failedEvents=[];$releaseEvents=[];
$events->listen(Illuminate\Queue\Events\JobFailed::class,function($event)use(&$failedEvents){$failedEvents[]=$event;});
$events->listen(Illuminate\Queue\Events\JobReleasedAfterException::class,function($event)use(&$releaseEvents){$releaseEvents[]=$event;});
$factory=new class($queue) implements Illuminate\Contracts\Queue\Factory {
 public function __construct(private $queue){}
 public function connection($name=null){return $this->queue;}
};
$exceptions=new class implements Illuminate\Contracts\Debug\ExceptionHandler {
 public function report(Throwable $e){}
 public function shouldReport(Throwable $e){return true;}
 public function render($request,Throwable $e){throw $e;}
 public function renderForConsole($output,Throwable $e){throw $e;}
};
$runner=new Illuminate\Queue\Worker($factory,$events,$exceptions,fn()=>false);
$options=new Illuminate\Queue\WorkerOptions(backoff:'5,11',sleep:0,maxTries:9);
$paper->languages()->updateExistingPivot($lang->id,['translation_approved_at'=>null]);
$queue->push(new GenerateExamPdfJob($build->id));
for($attempt=1;$attempt<=3;$attempt++){
 $job=$queue->pop();check($job!==null&&$job->attempts()===$attempt,'Real worker receives retained attempt count');
 $releasedBefore=$locks->released;$started=time();
 try{$runner->process('synthetic',$job,$options);throw new RuntimeException('Expected worker approval denial');}
 catch(RuntimeException $e){check($e->getMessage()==='Translation is not approved.','Worker propagates native approval failure');}
 check($locks->released===$releasedBefore+1&&file_get_contents($directory.'/current.pdf')===$pdf,'Each failed attempt releases lock and preserves published bytes');
 if($attempt<3){
  $delay=$attempt===1?5:11;$queued=DB::table('jobs')->first();
  check($job->isReleased()&&!$job->hasFailed()&&$queue->size()===1&&$queue->pop()===null,'Worker automatically delays recoverable failure');
  check($queued->available_at>=$started+$delay&&$queued->available_at<=time()+$delay,'Native backoff selects the attempt delay');
  // Advance only the isolated fixture row; do not sleep or touch server queues.
  DB::table('jobs')->update(['available_at'=>time()-1]);
 }else{
  check($job->hasFailed()&&$job->isDeleted()&&!$job->isReleased()&&$queue->size()===0,'Native three-attempt limit overrides worker default and exhausts the job');
 }
}
check(count($failedEvents)===1&&count($releaseEvents)===2,'Worker emits one terminal failure and two retry events');
check($build->fresh()->status==='failed','Exhausted PDF remains failed rather than ready');
$paper->languages()->updateExistingPivot($lang->id,['translation_approved_at'=>now()]);
$queue->push(new GenerateExamPdfJob($build->id));$job=$queue->pop();
$runner->process('synthetic',$job,$options);
check($job->isDeleted()&&$queue->size()===0&&$build->fresh()->status==='ready','Explicit new request recovers after approval is restored');
check(count($failedEvents)===1&&file_get_contents($directory.'/current.pdf')===$pdf,'Recovery preserves artifact and adds no failure');
echo "Native PDF Worker processing: automatic backoff, native attempt exhaustion, failure events and explicit recovery passed.\n";

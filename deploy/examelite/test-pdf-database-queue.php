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

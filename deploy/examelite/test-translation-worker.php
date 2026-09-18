<?php
namespace App\Services {
    // Scripted service boundaries: this fixture verifies the actual job/queue,
    // not provider generation or PDF rendering, which have separate checks.
    class ExamTranslationService {
        public array $responses=[];
        public array $calls=[];
        public function translateNextBatch($exam,$language):array {
            $this->calls[]=[(int)$exam->id,(int)$language->id];
            $response=array_shift($this->responses);
            if($response instanceof \Throwable)throw $response;
            if(!is_array($response))throw new \LogicException('Unexpected translation invocation');
            return $response;
        }
    }
    class ExamDocumentLifecycleService {
        public array $calls=[];
        public array $actors=[];
        public function queue($exam,$package,$language,$type,$requestedBy=null){
            $this->calls[]=[(int)$exam->id,(int)$package->id,(int)$language->id,$type];
            $this->actors[]=$requestedBy;
        }
    }
}
namespace {
require __DIR__.'/test-exam-authoring.php';
$jobPath=$argv[5]??dirname(realpath($argv[1]),2).'/app/Jobs/TranslateExamLanguageJob.php';
require $jobPath;
use Illuminate\Support\Facades\DB;
use Illuminate\Queue\{DatabaseQueue,CallQueuedHandler};
use App\Models\{Exam,Language};
use App\Jobs\TranslateExamLanguageJob;

DB::statement('CREATE TABLE jobs(id INTEGER PRIMARY KEY AUTOINCREMENT,queue TEXT,payload TEXT,attempts INTEGER,reserved_at INTEGER,available_at INTEGER,created_at INTEGER)');
foreach(['show_pdf_download','show_solution_pdf_download'] as $column)if(!DB::getSchemaBuilder()->hasColumn('packages',$column))DB::statement('ALTER TABLE packages ADD COLUMN '.$column.' INTEGER');
$paper=Exam::create(['organization_id'=>20,'name'=>'Synthetic queued translation','status'=>'Inactive']);
$language=Language::create(['organization_id'=>20,'name'=>'Synthetic language','code'=>'zz-worker','is_enabled'=>true]);
$paper->languages()->attach($language->id,['translation_status'=>'pending','auto_pdf'=>true]);
DB::table('packages')->insert([
    ['id'=>9001,'organization_id'=>20,'name'=>'Questions only','show_pdf_download'=>1,'show_solution_pdf_download'=>0],
    ['id'=>9002,'organization_id'=>20,'name'=>'Solutions only','show_pdf_download'=>0,'show_solution_pdf_download'=>1],
]);
$paper->packages()->attach([9001,9002]);
$translator=new App\Services\ExamTranslationService();$documents=new App\Services\ExamDocumentLifecycleService();
$app->instance(App\Services\ExamTranslationService::class,$translator);
$app->instance(App\Services\ExamDocumentLifecycleService::class,$documents);
$cache=new Illuminate\Cache\Repository(new Illuminate\Cache\ArrayStore());
$app->instance(Illuminate\Contracts\Cache\Repository::class,$cache);
$events=new Illuminate\Events\Dispatcher($app);
$app->instance(Illuminate\Contracts\Events\Dispatcher::class,$events);
$app->instance(Illuminate\Log\Context\Repository::class,new Illuminate\Log\Context\Repository($events));
$queue=new DatabaseQueue(DB::connection(),'jobs','translation-fixture',300);
$queue->setContainer($app);$queue->setConnectionName('synthetic');
$dispatcher=new Illuminate\Bus\Dispatcher($app,fn($connection)=>$queue);
$app->instance(Illuminate\Contracts\Bus\Dispatcher::class,$dispatcher);
$app->instance(CallQueuedHandler::class,new CallQueuedHandler($dispatcher,$app));
$translator->responses=[['status'=>'pending'],['status'=>'ready']];
$nativeJob=new TranslateExamLanguageJob($paper->id,$language->id);
$modern=$nativeJob instanceof Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
$lockKey=$nativeJob->middleware()[0]->getLockKey($nativeJob);
TranslateExamLanguageJob::dispatch($paper->id,$language->id);
if($modern){
    TranslateExamLanguageJob::dispatch($paper->id,$language->id);
    check($queue->size()===1,'Native unique dispatch suppresses a duplicate pending job');
}
$job=$queue->pop();check($job&&$queue->pop()===null,'Reservation prevents immediate duplicate consumption');
$payload=json_decode($job->getRawBody(),true,512,JSON_THROW_ON_ERROR);
check($payload['maxTries']===($modern?1:3)&&$payload['timeout']===240,'Native translation retry and timeout settings survive serialization');
$job->fire();
check($job->isDeleted()&&$queue->size()===1&&$queue->pop()===null,'Incomplete native job schedules one delayed continuation');
$queued=DB::table('jobs')->first();check($queued->available_at>=time(),'Continuation is not immediately available');
DB::table('jobs')->update(['available_at'=>time()-1]);
$next=$queue->pop();$next->fire();
check($next->isDeleted()&&$queue->size()===0&&count($translator->calls)===2,'Continuation reaches ready and stops dispatching');
check($documents->calls===[],'Ready without approval does not request PDFs');
if($modern){
    $translator->responses=[['status'=>'pending'],['status'=>'ready']];
    $queue->push(new TranslateExamLanguageJob($paper->id,$language->id,true,1));
    $requested=$queue->pop();$requested->fire();
    $continuation=unserialize(json_decode(DB::table('jobs')->first()->payload,true,512,JSON_THROW_ON_ERROR)['data']['command']);
    check($continuation->approveWhenReady===true&&$continuation->requestedBy===1,'Delayed continuation preserves explicit approval and requesting actor');
    DB::table('jobs')->update(['available_at'=>time()-1]);$queue->pop()->fire();
    $pivot=DB::table('exam_languages')->where('exam_id',$paper->id)->where('language_id',$language->id)->first();
    check($pivot->translation_approved_at!==null&&(int)$pivot->translation_approved_by===1&&$documents->actors===[1,1],'Explicit native approval attributes automatic document requests');
    $documents->calls=[];$documents->actors=[];
}
$paper->languages()->updateExistingPivot($language->id,['translation_approved_at'=>now()]);
$translator->responses=[['status'=>'ready']];
$queue->push(new TranslateExamLanguageJob($paper->id,$language->id));$approved=$queue->pop();$approved->fire();
check($documents->calls===[[$paper->id,9001,$language->id,'questions'],[$paper->id,9002,$language->id,'solutions']],'Approved automatic documents follow native package flags');
$before=count($translator->calls);$held=$cache->lock($lockKey,300);check($held->get(),'Synthetic competing worker owns the overlap lock');
$queue->push(new TranslateExamLanguageJob($paper->id,$language->id));$contended=$queue->pop();$contended->fire();
check($contended->isReleased()&&!$contended->isDeleted()&&$queue->pop()===null,'Native overlap middleware delays contended work');
check(count($translator->calls)===$before&&$held->isOwnedByCurrentProcess(),'Contended job neither calls translator nor releases another lock');
$held->release();DB::table('jobs')->delete();
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
$options=new Illuminate\Queue\WorkerOptions(backoff:5,sleep:0,maxTries:9);
$limit=$modern?1:3;$failures=0;
$events->listen(Illuminate\Queue\Events\JobFailed::class,function()use(&$failures){$failures++;});
$translator->responses=array_fill(0,$limit,new RuntimeException('Synthetic translator failure'));
$queue->push(new TranslateExamLanguageJob($paper->id,$language->id));
for($attempt=1;$attempt<=$limit;$attempt++){
    $failed=$queue->pop();check($failed&&$failed->attempts()===$attempt,'Worker retains native attempt count');
    try{$runner->process('synthetic',$failed,$options);throw new LogicException('Expected translator failure');}
    catch(RuntimeException $e){check($e->getMessage()==='Synthetic translator failure','Worker propagates native translator failure');}
    $probe=$cache->lock($lockKey,300);check($probe->get(),'Failed handler releases its overlap lock');$probe->release();
    if($attempt<$limit){
        check($failed->isReleased()&&$queue->pop()===null,'Legacy failure uses worker backoff');
        DB::table('jobs')->update(['available_at'=>time()-1]);
    }else check($failed->hasFailed()&&$failed->isDeleted()&&$queue->size()===0,'Worker honours native attempt limit');
}
check($failures===1&&count($documents->calls)===2,'One terminal failure and no PDF requests after failed generation');
$translator->responses=[['status'=>'ready']];$paper->languages()->updateExistingPivot($language->id,['auto_pdf'=>false]);
$queue->push(new TranslateExamLanguageJob($paper->id,$language->id));$retry=$queue->pop();$runner->process('synthetic',$retry,$options);
check($retry->isDeleted()&&$queue->size()===0&&count($documents->calls)===2,'Explicit new request recovers and honours disabled automatic PDF setting');
if($modern){
    $paper->languages()->updateExistingPivot($language->id,['translation_status'=>'failed']);$before=count($translator->calls);
    $queue->push(new TranslateExamLanguageJob($paper->id,$language->id));$stopped=$queue->pop();$runner->process('synthetic',$stopped,$options);
    check($stopped->isDeleted()&&count($translator->calls)===$before,'Current native job does not repeat paid work for an already failed translation');
}
echo "Native translation queue: serialization, delayed continuation, overlap, approval/package gating, native failure policy and explicit recovery passed. Provider, PDF and daemon execution are separate checks.\n";
}

<?php
namespace App\Services {
    // Only the PDF boundary is replaced; native queue and translator run together.
    class ExamDocumentLifecycleService {
        public static int $queued=0;
        public function queue(...$args){self::$queued++;}
    }
}
namespace {
require __DIR__.'/translation-provider-fixture.php';
require __DIR__.'/test-exam-authoring.php';
require $argv[5]??dirname($argv[3]).'/ExamTranslationService.php';
require $argv[6]??dirname(realpath($argv[1]),2).'/app/Jobs/TranslateExamLanguageJob.php';
use Illuminate\Support\Facades\{DB,Cache};
use Illuminate\Queue\{DatabaseQueue,CallQueuedHandler};
use App\Models\{Exam,Language,Question,QuestionLang};
use App\Jobs\TranslateExamLanguageJob;
use App\Services\{ExamTranslationService,ExamDocumentLifecycleService};
use App\Support\AiProvider;

foreach(['syllabus','source_fingerprint','source_field_fingerprints'] as $column)DB::statement('ALTER TABLE exam_language_translations ADD COLUMN '.$column.' TEXT');
foreach(['si_answer1','translated_by','source_fingerprint','source_field_fingerprints'] as $column)if(!DB::getSchemaBuilder()->hasColumn('question_langs',$column))DB::statement('ALTER TABLE question_langs ADD COLUMN '.$column.' TEXT');
DB::statement('ALTER TABLE organizations ADD COLUMN saas_plan_id INTEGER');
DB::statement('CREATE TABLE saas_plans(id INTEGER PRIMARY KEY,features TEXT)');
DB::statement('CREATE TABLE configurations(id INTEGER PRIMARY KEY,organization_id INTEGER)');
DB::statement('CREATE TABLE jobs(id INTEGER PRIMARY KEY AUTOINCREMENT,queue TEXT,payload TEXT,attempts INTEGER,reserved_at INTEGER,available_at INTEGER,created_at INTEGER)');
DB::table('saas_plans')->insert(['id'=>9876,'features'=>json_encode(['ai_translation'=>true])]);
DB::table('organizations')->where('id',20)->update(['saas_plan_id'=>9876]);
DB::table('configurations')->insert([['id'=>9001,'organization_id'=>30],['id'=>9002,'organization_id'=>20]]);
$GLOBALS['t4lTestAiTranslation']=true;
$cache=new Illuminate\Cache\Repository(new Illuminate\Cache\ArrayStore());
$app->instance('cache',$cache);Cache::clearResolvedInstance('cache');
$app->instance(Illuminate\Contracts\Cache\Repository::class,$cache);
$events=new Illuminate\Events\Dispatcher($app);
$app->instance(Illuminate\Contracts\Events\Dispatcher::class,$events);
$app->instance(Illuminate\Log\Context\Repository::class,new Illuminate\Log\Context\Repository($events));
$queue=new DatabaseQueue(DB::connection(),'jobs','translation-journey',300);
$queue->setContainer($app);$queue->setConnectionName('synthetic');
$dispatcher=new Illuminate\Bus\Dispatcher($app,fn($connection)=>$queue);
$app->instance(Illuminate\Contracts\Bus\Dispatcher::class,$dispatcher);
$app->instance(CallQueuedHandler::class,new CallQueuedHandler($dispatcher,$app));
$paper=Exam::create(['organization_id'=>20,'name'=>'Synthetic connected translation','instruction'=>'Read carefully','status'=>'Inactive']);
$language=Language::create(['organization_id'=>20,'name'=>'Synthetic target','code'=>'zz-journey','is_enabled'=>true]);
$paper->languages()->attach($language->id,['translation_status'=>'pending','auto_translate'=>false,'auto_pdf'=>false]);
$questions=[];
for($i=0;$i<6;$i++){
    $source=Question::create(['organization_id'=>20,'question'=>'Synthetic source '.$i,'si_answer1'=>'Synthetic answer '.$i]);
    $questions[]=$source;$paper->questions()->attach($source->id);
}
$pivot=fn()=>DB::table('exam_languages')->where('exam_id',$paper->id)->where('language_id',$language->id)->first();
$count=fn()=>QuestionLang::where('language_id',$language->id)->count();
$run=function()use($queue){$job=$queue->pop();check($job!==null,'Native job is available');$job->fire();check($job->isDeleted(),'Native handler acknowledges successful work');};
TranslateExamLanguageJob::dispatch($paper->id,$language->id);
$run();
check($count()===5&&$pivot()->translation_status==='pending'&&$queue->size()===1,'Queued native translator persists the first batch and schedules remaining work');
check($queue->pop()===null&&$pivot()->translation_approved_at===null,'Continuation is delayed and partial work remains unapproved');
DB::table('jobs')->update(['available_at'=>time()-1]);$run();
check($count()===6&&$pivot()->translation_status==='ready'&&$queue->size()===0,'Queued continuation completes native translation without looping');
check(count(AiProvider::$payloads)===2&&array_unique(AiProvider::$owners)===[20],'Both native batches use only the owning organisation provider');
check($pivot()->translation_approved_at===null&&ExamDocumentLifecycleService::$queued===0,'Manual-review completion never approves or dispatches PDFs');
foreach($questions as $question){
    $target=QuestionLang::where('question_id',$question->id)->where('language_id',$language->id)->firstOrFail();
    check($target->si_answer1==='[synthetic target] '.$question->si_answer1,'Queue journey retains subjective model answers');
}
TranslateExamLanguageJob::dispatch($paper->id,$language->id);$run();
check(count(AiProvider::$payloads)===2&&$queue->size()===0,'Completed job replay does not buy another provider request');

// A late response must fail the real queued handler, preserve reviewed wording,
// and release BOTH the job overlap lock and translator lock for explicit retry.
$target=QuestionLang::where('question_id',$questions[0]->id)->where('language_id',$language->id)->firstOrFail();
$questions[0]->update(['question'=>'Changed source for queued refresh']);
AiProvider::$duringGeneration=fn()=>DB::table('question_langs')->where('id',$target->id)->update(['si_answer1'=>'Staff reviewed answer']);
TranslateExamLanguageJob::dispatch($paper->id,$language->id);$failed=$queue->pop();
try{$failed->fire();throw new LogicException('Expected late-response rejection');}
catch(RuntimeException $e){check(str_contains($e->getMessage(),'Translation inputs changed'),'Native job propagates stale-response rejection');}
check($pivot()->translation_status==='failed'&&$target->fresh()->si_answer1==='Staff reviewed answer','Failed native queued refresh preserves staff wording');
$failed->delete(); // Worker failure policy is exercised separately; end this reservation.
$probeJob=new TranslateExamLanguageJob($paper->id,$language->id);
foreach([$probeJob->middleware()[0]->getLockKey($probeJob),'exam-translation:'.$paper->id.':'.$language->id] as $key){
    $lock=$cache->lock($key,300);check($lock->get(),'Failed connected handler releases its native locks');$lock->release();
}
$paper->languages()->updateExistingPivot($language->id,['translation_status'=>'pending']);
TranslateExamLanguageJob::dispatch($paper->id,$language->id);$run();
check($pivot()->translation_status==='ready'&&$queue->size()===0&&$target->fresh()->si_answer1==='Staff reviewed answer','Explicit retry completes and retains unchanged reviewed fields');
check(ExamDocumentLifecycleService::$queued===0,'Failure and recovery never publish unapproved documents');
echo "Connected native translation: queued batching, owner selection, persistence, replay, late-response failure and explicit recovery passed. AI output is synthetic; PDF rendering and daemon operation remain separate.\n";
}

<?php
// Explicit opt-in fixture: real queue + native job + native renderer, isolated data.
if(PHP_SAPI!=='cli'||!isset($argv[8],$argv[9]))throw new InvalidArgumentException('Supply the seven worker fixture paths, runtime directory and loopback URL.');
$runtime=realpath($argv[8]);$printBase=$argv[9];
if(!$runtime||!is_file($runtime.'/scripts/render-exam-pdf.mjs')||!preg_match('#^http://127\.0\.0\.1:[1-9][0-9]*$#D',$printBase))throw new InvalidArgumentException('Prepared local runtime and loopback URL required.');
require __DIR__.'/test-pdf-worker.php';
use App\Models\{Exam,Question,Language,ExamPdfBuild};
use Illuminate\Support\Facades\DB;
use App\Services\{ExamDocumentLifecycleService,ExamPdfCacheService};
$app->setBasePath($runtime);
$app['config']->set('services.playwright',[
 'bypass_edge'=>false,
 'browsers_path'=>getenv('PLAYWRIGHT_BROWSERS_PATH')?:((PHP_OS_FAMILY==='Windows'?getenv('LOCALAPPDATA'):getenv('HOME').'/.cache').'/ms-playwright'),
]);
$renderLifecycle=new class($runtime.'/output',$printBase) extends ExamDocumentLifecycleService {
 public string $mode='healthy';public int $renders=0;
 public function __construct(private string $root,private string $base){}
 public function directory(Exam $exam,?App\Models\Package $package,?Language $language,string $type):string{return $this->root;}
 public function printUrl(ExamPdfBuild $build):string{$this->renders++;return $this->base.'/'.$this->mode;}
};
$app->instance(ExamDocumentLifecycleService::class,$renderLifecycle);
$paper=Exam::create(['organization_id'=>20,'name'=>'Real render fixture','status'=>'Inactive']);
$question=Question::create(['organization_id'=>20,'question'=>'First source version']);$paper->questions()->attach($question->id);
$lang=Language::create(['organization_id'=>20,'name'=>'English renderer fixture','code'=>'en','is_enabled'=>true]);
$build=ExamPdfBuild::create(['organization_id'=>20,'exam_id'=>$paper->id,'language_id'=>$lang->id,'document_type'=>'questions','status'=>'queued']);
$run=function()use($queue,$runner,$options,$build){
 $queue->push(new App\Jobs\GenerateExamPdfJob($build->id));$job=$queue->pop();check($job!==null,'Real render job reserved');
 $runner->process('synthetic',$job,$options);return $job;
};
$job=$run();$ready=$build->fresh();$current=$ready->current_path;
check($ready->status==='ready'&&$job->isDeleted()&&$queue->size()===0,'Queue acknowledges only after a real PDF is published');
$bytes=file_get_contents($current);check(str_starts_with($bytes,'%PDF-')&&strlen($bytes)>1000,'Published artifact contains generated PDF bytes');
check($ready->file_size===strlen($bytes)&&$ready->source_fingerprint,'Native build records generated size and fingerprint');
$firstFingerprint=$ready->source_fingerprint;$run();
check($renderLifecycle->renders===1&&file_get_contents($current)===$bytes,'Ready replay does not render or replace the PDF again');
$question->question='Changed source version';$question->save();
$build->refresh()->update(['status'=>'queued']);$renderLifecycle->mode='broken';$releasedBefore=$locks->released;
try{$run();throw new RuntimeException('Expected missing-image render failure');}
catch(Symfony\Component\Process\Exception\ProcessFailedException $error){check(str_contains($error->getProcess()->getErrorOutput(),'print image could not be loaded'),'Native renderer fails on a missing image');}
check($build->fresh()->status==='failed'&&file_get_contents($current)===$bytes,'Failed real render preserves the prior published PDF');
check($locks->released===$releasedBefore+1&&$queue->size()===1,'Failed render releases build lock and is scheduled for retry');
check(glob($runtime.'/output/.*.tmp.pdf')===[]&&glob($runtime.'/output/.current.*.pdf')===[],'Failed render leaves no partial activation artifact');
// Only synthetic queue rows are reset to exercise a new explicit request immediately.
DB::table('jobs')->delete();$build->refresh()->update(['status'=>'queued']);$renderLifecycle->mode='healthy';
$job=$run();$recovered=$build->fresh();
check($job->isDeleted()&&$queue->size()===0&&$recovered->status==='ready','Explicit request recovers through the real renderer');
check($recovered->source_fingerprint!==$firstFingerprint&&$renderLifecycle->renders===3,'Source change selects and publishes a newly rendered version');
echo "PASS: real Laravel queue -> native PDF worker -> native renderer -> published PDF; ready replay, failed image retention, lock/temporary cleanup and explicit recovery.\n";

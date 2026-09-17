<?php
namespace App\Services {
    // Isolate fingerprint inputs; this check exercises the native worker, not rendering.
    class ExamPdfCacheService {
        public const FINGERPRINT_SCHEMA_VERSION=1;
        public function fingerprint($exam,$language,$package,$solutions){return hash('sha256',json_encode([$exam->id,$language?->id,$package?->id,$solutions]));}
    }
}
namespace {
require __DIR__.'/test-exam-authoring.php';
require dirname($argv[3]).'/ExamDocumentLifecycleService.php';
$jobPath=$argv[5]??'';
if(!is_file($jobPath))throw new RuntimeException('Supply the native GenerateExamPdfJob.php path as argument five.');
require $jobPath;
use Illuminate\Support\Facades\{DB,Cache,File};
use App\Models\{Exam,ExamPdfBuild,Language};

DB::statement('CREATE TABLE exam_pdf_builds(id INTEGER PRIMARY KEY,organization_id INTEGER,exam_id INTEGER,package_id INTEGER,language_id INTEGER,document_type TEXT,status TEXT,current_path TEXT,version_path TEXT,source_fingerprint TEXT,file_size INTEGER,last_error TEXT,started_at TEXT,completed_at TEXT,created_at TEXT,updated_at TEXT)');
$app->instance('files',new Illuminate\Filesystem\Filesystem());File::clearResolvedInstance('files');
$locks=new class {
 public int $released=0;public bool $available=true;
 public function lock($key,$seconds){return new class($this){public function __construct(private $owner){}public function get(){return $this->owner->available;}public function release(){$this->owner->released++;}};}
};
$app->instance('cache',$locks);Cache::clearResolvedInstance('cache');
$directory=sys_get_temp_dir().'/t4l-worker-'.bin2hex(random_bytes(8));mkdir($directory.'/versions',0700,true);
$lifecycle=new class($directory) extends App\Services\ExamDocumentLifecycleService {
 public function __construct(private string $testDirectory){}
 public function directory(Exam $exam,?App\Models\Package $package,?Language $language,string $type):string{return $this->testDirectory;}
};
try {
 $paper=Exam::create(['organization_id'=>20,'name'=>'Synthetic worker paper','status'=>'Inactive']);
 $lang=Language::create(['organization_id'=>20,'name'=>'Worker language','code'=>'xx','is_enabled'=>true]);
 $paper->languages()->attach($lang->id,['translation_status'=>'ready','translation_approved_at'=>now()]);
 $fingerprint=app(App\Services\ExamPdfCacheService::class)->fingerprint($paper,$lang,null,false);
 // Deliberately not a rendered document: cached-artifact activation and failure states only.
 $pdf="%PDF-1.4\n".str_repeat('Synthetic fixture ',90)."\n%%EOF";
 $version=$directory.'/versions/'.$fingerprint.'.pdf';file_put_contents($version,$pdf);
 $build=ExamPdfBuild::create(['organization_id'=>20,'exam_id'=>$paper->id,'language_id'=>$lang->id,'document_type'=>'questions','status'=>'queued']);
 $worker=new App\Jobs\GenerateExamPdfJob($build->id);$worker->handle($lifecycle);
 check($build->fresh()->status==='ready'&&file_get_contents($directory.'/current.pdf')===$pdf,'Native worker activates a cached version and marks ready');
 check($build->fresh()->source_fingerprint===$fingerprint&&$locks->released===1,'Native worker records fingerprint and releases lock');
 $worker->handle($lifecycle);check(file_get_contents($directory.'/current.pdf')===$pdf&&$locks->released===2,'Repeated cached build preserves artifact');
 $paper->languages()->updateExistingPivot($lang->id,['translation_approved_at'=>null]);
 try{$worker->handle($lifecycle);throw new RuntimeException('Expected approval rejection');}
 catch(RuntimeException $e){check($e->getMessage()==='Translation is not approved.','Unapproved translation stops worker before rendering');}
 check($build->fresh()->status==='failed'&&$locks->released===3&&file_get_contents($directory.'/current.pdf')===$pdf,'Failed replacement preserves prior artifact and releases lock');
 check(glob($directory.'/*.tmp.pdf')===[]&&glob($directory.'/.current.*.pdf')===[],'No temporary activation files remain');
} finally {
 // Only this freshly-created random fixture directory can be removed.
 File::deleteDirectory($directory);
}
echo "Native PDF worker: cached activation, repeat execution, approval failure and prior-artifact preservation passed. Renderer and queue transport remain untested.\n";
}

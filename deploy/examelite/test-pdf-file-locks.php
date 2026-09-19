<?php
// Explicit local check of the native worker using Laravel's real file lock store.
if(PHP_SAPI!=='cli'||getenv('NODE_ENV')==='production')throw new RuntimeException('Local CLI fixture only.');
if(($argv[1]??null)==='probe'){
 require $argv[2];
 $store=new Illuminate\Cache\FileStore(new Illuminate\Filesystem\Filesystem(),$argv[3]);
 $lock=$store->lock($argv[4],400);
 if($lock->get()){$lock->release();echo 'available';}else echo 'busy';
 exit;
}
require __DIR__.'/test-pdf-worker.php';
use Illuminate\Support\Facades\{Cache,File};
use Illuminate\Cache\{Repository,FileStore};
use App\Jobs\GenerateExamPdfJob;
$root=sys_get_temp_dir().'/t4l-file-lock-'.bin2hex(random_bytes(12));
mkdir($root,0700,true);
$filesystem=new Illuminate\Filesystem\Filesystem();
$repository=new Repository(new FileStore($filesystem,$root));
$app->instance('cache',$repository);Cache::clearResolvedInstance('cache');
$key='exam-pdf-build:'.$build->id;
$holder=$repository->lock($key,400);
$probe=function(string $name)use($root,$argv):string{
 $process=new Symfony\Component\Process\Process([PHP_BINARY,__FILE__,'probe',$argv[1],$root,$name]);
 $process->setTimeout(20);$process->mustRun();return trim($process->getOutput());
};
try {
 check($holder->get(),'First owner acquires the real file lock');
 check($probe($key)==='busy','Independent PHP process observes the held lock');
 $before=$build->fresh()->getAttributes();
 (new GenerateExamPdfJob($build->id))->handle($lifecycle);
 check($build->fresh()->getAttributes()===$before,'Contended native worker leaves the build untouched');
 $outsider=$repository->lock($key,400);
 check(!$outsider->release()&&$probe($key)==='busy','Wrong owner cannot release the lock');
 check($probe($key.'-different')==='available','Unrelated builds are not blocked by this lock');
 check($holder->release()&&$probe($key)==='available','Owner release makes the lock available across processes');

 // Missing-build cleanup must also release a real lock, not just a test counter.
 $missing=(int)App\Models\ExamPdfBuild::max('id')+1000;
 try{(new GenerateExamPdfJob($missing))->handle($lifecycle);throw new RuntimeException('Expected missing build failure');}
 catch(Illuminate\Database\Eloquent\ModelNotFoundException $error){}
 check($probe('exam-pdf-build:'.$missing)==='available','Native missing-build failure releases its file lock');

 // Force the native approval rejection before any renderer/file write is reached.
 $paper->languages()->updateExistingPivot($lang->id,['translation_approved_at'=>null]);
 $build->refresh()->update(['status'=>'queued']);
 try{(new GenerateExamPdfJob($build->id))->handle($lifecycle);throw new RuntimeException('Expected approval failure');}
 catch(RuntimeException $error){check($error->getMessage()==='Translation is not approved.','Native approval check still runs with real locking');}
 check($build->fresh()->status==='failed'&&$probe($key)==='available','Failed build releases the real lock for recovery');
} finally {
 $holder->release();
 $resolved=realpath($root);$temp=realpath(sys_get_temp_dir());
 if(!$resolved||!$temp||!str_starts_with($resolved,$temp.DIRECTORY_SEPARATOR.'t4l-file-lock-'))throw new RuntimeException('Unsafe fixture cleanup path.');
 File::deleteDirectory($root);
}
echo "PASS: native PDF worker with real cross-process file locks, owner isolation, contention and failure release.\n";

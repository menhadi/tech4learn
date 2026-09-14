<?php
// Synthetic SQLite records and the unmodified native document publication service.
require __DIR__.'/test-exam-authoring.php';
require dirname($argv[3]).'/ExamDocumentLifecycleService.php';
require __DIR__.'/Tech4LearnExamDocuments.php';
require __DIR__.'/Tech4LearnDocumentController.php';
use Illuminate\Support\Facades\DB;
use App\Models\ExamPdfBuild;

$documents=new App\Services\Tech4LearnExamDocuments();
$documentRestrictions=DB::table('tech4learn_workspaces')->where('id',$workspace)->value('restrictions');
DB::table('tech4learn_workspaces')->where('id',$workspace)->update(['restrictions'=>'[]']);
DB::statement('CREATE TABLE exam_pdf_builds(id INTEGER PRIMARY KEY,organization_id INTEGER,exam_id INTEGER,package_id INTEGER,language_id INTEGER,document_type TEXT,status TEXT,current_path TEXT,created_at TEXT,updated_at TEXT)');
$oldStorage=$app->storagePath();$temp=sys_get_temp_dir().'/t4l-pdf-'.bin2hex(random_bytes(8));
mkdir($temp.'/app/exam-pdfs',0700,true);$app->useStoragePath($temp);
$inside=$temp.'/app/exam-pdfs/approved.pdf';$outside=$temp.'/outside.pdf';
$pdf="%PDF-1.4\n% Synthetic fixture, not an actual learner document\n%%EOF\n";
file_put_contents($inside,$pdf);file_put_contents($outside,$pdf);
$build=ExamPdfBuild::create(['organization_id'=>20,'exam_id'=>$packageLinkedExam->id,'package_id'=>$packageSaved['id'],'language_id'=>null,'document_type'=>'questions','status'=>'ready','current_path'=>$inside]);
$read=fn()=>$documents->read($workspace,10,$actor,$packageLinkedExam->id,$packageSaved['id'],null,'questions');
$reject=function(callable $operation,string $label){try{$operation();throw new RuntimeException('Expected rejection: '.$label);}catch(Symfony\Component\HttpKernel\Exception\HttpException|Illuminate\Database\Eloquent\ModelNotFoundException $e){}};
try {
    $result=$read();check(base64_decode($result['base64'],true)===$pdf&&$result['build_id']===$build->id&&!isset($result['path']),'Approved native document returns only bounded bytes and IDs');
    // The engine intentionally serves the previous approved artifact while rebuilding.
    $build->status='processing';$build->save();check($read()===$result,'Native prior artifact retained while replacement is processing');
    $reject(fn()=>$documents->read($workspace,30,$actor,$packageLinkedExam->id,$packageSaved['id'],null,'questions'),'Foreign source');
    $reject(fn()=>$documents->read($workspace,10,$actor,$packageLinkedExam->id,$packageSaved['id'],null,'solutions'),'Unapproved solution');
    $reject(fn()=>$documents->read($workspace,10,$actor,$packageLinkedExam->id,null,null,'questions'),'Wrong package selection');
    $reject(fn()=>$documents->read($workspace,10,$actor,$packageLinkedExam->id,$packageSaved['id'],999,'questions'),'Foreign language');
    $language=App\Models\Language::enabledForOrganization(20)->firstOrFail();
    $packageLinkedExam->languages()->syncWithoutDetaching([$language->id]);
    $build->language_id=$language->id;$build->save();
    $translated=$documents->read($workspace,10,$actor,$packageLinkedExam->id,$packageSaved['id'],$language->id,'questions');
    check($translated['language_id']===$language->id,'Explicit owned paper language selects its artifact');
    $language->is_enabled=false;$language->save();
    $reject(fn()=>$documents->read($workspace,10,$actor,$packageLinkedExam->id,$packageSaved['id'],$language->id,'questions'),'Disabled language');
    $language->is_enabled=true;$language->save();$build->language_id=null;
    $build->organization_id=30;$build->save();$reject($read,'Mismatched native build owner');$build->organization_id=20;
    $build->current_path=$outside;$build->save();$reject($read,'File outside native PDF storage');
    $build->current_path=$inside;$build->save();file_put_contents($inside,'Not a PDF');$reject($read,'Invalid PDF signature');
    $handle=fopen($inside,'wb');fwrite($handle,'%PDF-1.4');ftruncate($handle,App\Services\Tech4LearnExamDocuments::MAX_BYTES+1);fclose($handle);$reject($read,'Oversized PDF');file_put_contents($inside,$pdf);
    DB::table('tech4learn_workspaces')->where('id',$workspace)->update(['restrictions'=>'["exams"]']);$reject($read,'Restricted exams');
    DB::table('tech4learn_workspaces')->where('id',$workspace)->update(['restrictions'=>'[]']);
    DB::table('organizations')->where('id',20)->update(['status'=>'inactive']);$reject($read,'Inactive organisation');DB::table('organizations')->where('id',20)->update(['status'=>'active']);
    $staffId=DB::table('tech4learn_workspace_users')->where('workspace_id',$workspace)->where('local_id',$actor)->where('kind','staff')->value('external_id');
    DB::table('organization_users')->where('organization_id',20)->where('user_id',$staffId)->update(['status'=>0]);$reject($read,'Revoked staff');
    DB::table('organization_users')->where('organization_id',20)->where('user_id',$staffId)->update(['status'=>1]);
    check($read()===$result,'Restored access reads the same approved artifact');
    $configFile=tempnam(sys_get_temp_dir(),'t4l-pdf-config-');$token=bin2hex(random_bytes(32));
    file_put_contents($configFile,json_encode(['_platform'=>['enabled'=>true,'organization_id'=>10,'token_hash'=>hash('sha256',$token)]]));
    $controller=new class($configFile) extends App\Http\Controllers\Tech4LearnDocumentController {public function __construct(private string $file){}protected function configPath():string{return $this->file;}};
    try {
        $request=Illuminate\Http\Request::create('https://central.example.test/documents','GET',['actor_id'=>$actor,'package_id'=>(string)$packageSaved['id']],[],[],['HTTP_AUTHORIZATION'=>'Bearer '.$token]);
        $reply=$controller->read($request,$workspace,(string)$packageLinkedExam->id,'questions');
        check($reply->getData(true)['data']===$result&&str_contains($reply->headers->get('Cache-Control'),'no-store'),'Credential document read has private response');
        $request->query->set('path',$outside);$reject(fn()=>$controller->read($request,$workspace,(string)$packageLinkedExam->id,'questions'),'Caller cannot supply a path');$request->query->remove('path');
        $request->headers->remove('Authorization');$reject(fn()=>$controller->read($request,$workspace,(string)$packageLinkedExam->id,'questions'),'Missing dedicated credential');
    } finally {unlink($configFile);}
} finally {
    $app->useStoragePath($oldStorage);unlink($inside);unlink($outside);rmdir($temp.'/app/exam-pdfs');rmdir($temp.'/app');rmdir($temp);
    DB::table('tech4learn_workspaces')->where('id',$workspace)->update(['restrictions'=>$documentRestrictions]);
}
echo "Native approved document reads: scope, publication, bounds and storage confinement passed.\n";


<?php
require __DIR__.'/test-exam-documents.php';
use App\Models\{Exam,Package,Language,ExamPdfBuild};
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
$centralExam=Exam::create(['organization_id'=>10,'name'=>'Central PDF read fixture','status'=>'Inactive']);
$centralPackage=Package::create(['organization_id'=>10,'name'=>'Central PDF package']);$centralExam->packages()->attach($centralPackage->id);
$centralLanguage=Language::where('organization_id',10)->firstOrFail();$centralExam->languages()->attach($centralLanguage->id);
$oldStorage=$app->storagePath();$temp=sys_get_temp_dir().'/t4l-central-pdf-'.bin2hex(random_bytes(8));mkdir($temp.'/app/exam-pdfs',0700,true);$app->useStoragePath($temp);
$inside=$temp.'/app/exam-pdfs/approved.pdf';$outside=$temp.'/outside.pdf';$pdf="%PDF-1.4\n% Synthetic central fixture\n%%EOF\n";file_put_contents($inside,$pdf);file_put_contents($outside,$pdf);
$centralBuild=ExamPdfBuild::create(['organization_id'=>10,'exam_id'=>$centralExam->id,'package_id'=>$centralPackage->id,'language_id'=>$centralLanguage->id,'document_type'=>'questions','status'=>'ready','current_path'=>$inside]);
$readCentral=fn()=>$documents->read('',10,'',$centralExam->id,$centralPackage->id,$centralLanguage->id,'questions',true);
$statusCentral=fn()=>$documents->status('',10,'',$centralExam->id,$centralPackage->id,$centralLanguage->id,'questions',true);
try {
 $result=$readCentral();check(base64_decode($result['base64'],true)===$pdf&&!isset($result['path']),'Central approved native bytes remain private');check($statusCentral()['approved_available'],'Central ready status');
 foreach(['queued','processing','failed','stale'] as $state){$centralBuild->status=$state;$centralBuild->save();check($readCentral()===$result&&$statusCentral()['status']===$state,'Central native prior-artifact semantics preserved for '.$state);}
 $missing=$documents->status('',10,'',$centralExam->id,$centralPackage->id,$centralLanguage->id,'solutions',true);check($missing['status']==='not_built'&&!$missing['approved_available'],'Missing central document is explicit');
 $reject(fn()=>$documents->read('',20,'',$centralExam->id,$centralPackage->id,$centralLanguage->id,'questions',true),'Foreign credential owner');
 $reject(fn()=>$documents->read('',10,'',$packageLinkedExam->id,$packageSaved['id'],null,'questions',true),'Foreign paper');
 $reject(fn()=>$documents->read('',10,'',$centralExam->id,$packageSaved['id'],$centralLanguage->id,'questions',true),'Foreign package');
 $reject(fn()=>$documents->read('',10,'',$centralExam->id,$centralPackage->id,$language->id,'questions',true),'Foreign language');
 $centralLanguage->is_enabled=false;$centralLanguage->save();$reject($readCentral,'Disabled central language');$centralLanguage->is_enabled=true;$centralLanguage->save();
 $centralBuild->current_path=$outside;$centralBuild->save();$reject($readCentral,'Outside central PDF storage');check(!$statusCentral()['approved_available'],'Outside file not advertised');
 $centralBuild->current_path=$inside;$centralBuild->save();file_put_contents($inside,'Not PDF');$reject($readCentral,'Bad central PDF bytes');file_put_contents($inside,$pdf);
 $normal=$app->make(App\Services\ExamDocumentLifecycleService::class);
 $app->instance(App\Services\ExamDocumentLifecycleService::class,new class extends App\Services\ExamDocumentLifecycleService {
  public function approved(Exam $exam,?Package $package,?Language $language,string $type):?ExamPdfBuild {$build=parent::approved($exam,$package,$language,$type);DB::table('organizations')->where('id',10)->update(['status'=>'inactive']);return $build;}
 });
 try{$reject($readCentral,'Revoked central owner during document read');DB::table('organizations')->where('id',10)->update(['status'=>'active']);$reject($statusCentral,'Revoked central owner during status read');}finally{$app->instance(App\Services\ExamDocumentLifecycleService::class,$normal);DB::table('organizations')->where('id',10)->update(['status'=>'active']);}
 $configFile=tempnam(sys_get_temp_dir(),'t4l-central-pdf-config-');$token=bin2hex(random_bytes(32));file_put_contents($configFile,json_encode(['_platform'=>['enabled'=>true,'organization_id'=>10,'token_hash'=>hash('sha256',$token)]]));
 $controller=new class($configFile) extends App\Http\Controllers\Tech4LearnDocumentController {public function __construct(private string $file){}protected function configPath():string{return $this->file;}};
 try {
  $request=Request::create('https://central.example.test/','GET',['package_id'=>(string)$centralPackage->id,'language_id'=>(string)$centralLanguage->id],[],[],['HTTP_AUTHORIZATION'=>'Bearer '.$token]);
  $reply=$controller->centralRead($request,(string)$centralExam->id,'questions');check($reply->getData(true)['data']===$result&&str_contains($reply->headers->get('Cache-Control'),'no-store'),'Central download credential route');
  check($controller->centralStatus($request,(string)$centralExam->id,'questions')->getData(true)['data']===$statusCentral(),'Central status credential route');
  foreach(['actor_id'=>'override','path'=>$outside,'organization_id'=>'20'] as $key=>$value){$request->query->set($key,$value);$reject(fn()=>$controller->centralRead($request,(string)$centralExam->id,'questions'),'Central query override');$request->query->remove($key);}
  $request->headers->remove('Authorization');$reject(fn()=>$controller->centralRead($request,(string)$centralExam->id,'questions'),'Missing central credential');
 }finally{unlink($configFile);}
}finally{$app->useStoragePath($oldStorage);unlink($inside);unlink($outside);rmdir($temp.'/app/exam-pdfs');rmdir($temp.'/app');rmdir($temp);}
echo "Central native document reads: credential scope, approved artifacts, bounds and revocation passed.\n";

<?php
// Isolated native translation fingerprints, no provider calls or live writes.
require __DIR__.'/test-exam-translations.php';
use App\Models\{Exam,Language,Question,QuestionLang,ExamLanguageTranslation};
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
$centralPaper=Exam::create(['organization_id'=>10,'name'=>'Central review fixture','status'=>'Inactive']);
$centralTarget=Language::create(['organization_id'=>10,'name'=>'Central target','code'=>'zx','is_enabled'=>true]);
$centralEnglish=Language::firstOrCreate(['organization_id'=>10,'code'=>'en'],['name'=>'English','is_enabled'=>true]);
$centralPaper->languages()->attach([$centralTarget->id,$centralEnglish->id]);
$centralQuestion=Question::create(['organization_id'=>10,'question'=>'Central wording']);$centralPaper->questions()->attach($centralQuestion->id);
$beforeWorkspaces=DB::table('tech4learn_workspaces')->count();
$beforeJobs=App\Jobs\TranslateExamLanguageJob::$queued;
$centralRead=fn()=>$reader->centralReview(10,$centralPaper->id,$centralTarget->id);
$missing=$centralRead();check($missing['items'][0]['translation']===null&&!$missing['approved'],'Missing central translations stay missing');
$ct=QuestionLang::create(['question_id'=>$centralQuestion->id,'language_id'=>$centralTarget->id,'question'=>'Central translated wording','source_fingerprint'=>$native->questionFingerprint($centralQuestion),'source_field_fingerprints'=>$native->questionFieldFingerprints($centralQuestion)]);
ExamLanguageTranslation::create(['exam_id'=>$centralPaper->id,'language_id'=>$centralTarget->id,'name'=>'Central translated exam','source_fingerprint'=>$native->examFingerprint($centralPaper),'source_field_fingerprints'=>$native->examFieldFingerprints($centralPaper)]);
$complete=$centralRead();check($complete['progress']['remaining']===0&&!$complete['approved'],'Native central completeness does not imply approval');
$centralPaper->languages()->updateExistingPivot($centralTarget->id,['translation_status'=>'ready','translation_approved_at'=>now()]);check($centralRead()['approved'],'Native central approval is reported');
$centralQuestion->question='Changed central wording';$centralQuestion->save();check(in_array('question',$centralRead()['items'][0]['stale_fields'],true),'Native stale fields are preserved');
$en=$reader->centralReview(10,$centralPaper->id,$centralEnglish->id);check($en['is_source_language']&&$en['items'][0]['source']===$en['items'][0]['translation'],'Central English uses source wording');
for($i=0;$i<50;$i++){$row=Question::create(['organization_id'=>10,'question'=>'Central page '.$i]);$centralPaper->questions()->attach($row->id);}
$page=$centralRead();$next=$reader->centralReview(10,$centralPaper->id,$centralTarget->id,$page['next'],$page['revision']);check(count($page['items'])===50&&count($next['items'])===1,'Central review is bounded and revision-paged');
$reject(fn()=>$reader->centralReview(10,$centralPaper->id,$centralTarget->id,$page['next']));
$ct->question='Changed target';$ct->save();$reject(fn()=>$reader->centralReview(10,$centralPaper->id,$centralTarget->id,$page['next'],$page['revision']));
$reject(fn()=>$reader->centralReview(20,$centralPaper->id,$centralTarget->id));
$reject(fn()=>$reader->centralReview(10,$paper->id,$target->id));
$reject(fn()=>$reader->centralReview(10,$centralPaper->id,$target->id));
$centralPaper->questions()->attach($question->id);$reject($centralRead);$centralPaper->questions()->detach($question->id);
$centralTarget->is_enabled=false;$centralTarget->save();$reject($centralRead);$centralTarget->is_enabled=true;$centralTarget->save();
DB::table('organizations')->where('id',10)->update(['status'=>'inactive']);$reject($centralRead);DB::table('organizations')->where('id',10)->update(['status'=>'active']);
$ct->question='<p>Central image</p><img src="'.$image.'">';$ct->save();
$centralPaper->instruction='<img src="'.$image.'">';$centralPaper->save();
$imageReview=$centralRead();
check(str_contains($imageReview['items'][0]['translation']['question'],'t4l-media:'.$asset),'Central review rewrites media references');
$centralMedia=fn()=>$reader->centralMedia(10,$centralPaper->id,$centralTarget->id,$centralQuestion->id,$asset,$imageReview['revision']);
$bytes=$centralMedia();check($bytes['mime']==='image/png'&&$bytes['revision']===$imageReview['revision'],'Central translated media is revision scoped');
check($reader->centralMedia(10,$centralPaper->id,$centralTarget->id,0,$asset,$imageReview['revision'])['mime']==='image/png','Central exam wording media is supported');
$reject(fn()=>$reader->centralMedia(10,$centralPaper->id,$centralTarget->id,$question->id,$asset,$imageReview['revision']));
$reject(fn()=>$reader->centralMedia(10,$centralPaper->id,$centralTarget->id,$centralQuestion->id,str_repeat('0',64),$imageReview['revision']));
$reject(fn()=>$reader->centralMedia(20,$centralPaper->id,$centralTarget->id,$centralQuestion->id,$asset,$imageReview['revision']));
$normalMedia=$app->make(App\Services\Tech4LearnQuestionMedia::class);
$app->instance(App\Services\Tech4LearnQuestionMedia::class,new class extends App\Services\Tech4LearnQuestionMedia {
 protected function bytes(string $source):string {
  $bytes=parent::bytes($source);DB::table('organizations')->where('id',10)->update(['status'=>'inactive']);return $bytes;
 }
});
try{$reject($centralMedia);}finally{$app->instance(App\Services\Tech4LearnQuestionMedia::class,$normalMedia);DB::table('organizations')->where('id',10)->update(['status'=>'active']);}
$configFile=tempnam(sys_get_temp_dir(),'t4l-central-review-');$token=bin2hex(random_bytes(32));
file_put_contents($configFile,json_encode(['_platform'=>['enabled'=>true,'organization_id'=>10,'token_hash'=>hash('sha256',$token)]]));
$controller=new class($configFile) extends App\Http\Controllers\Tech4LearnTranslationController {public function __construct(private string $file){}protected function configPath():string{return $this->file;}};
try {
 $request=Request::create('https://central.example.test/','GET',[],[],[],['HTTP_AUTHORIZATION'=>'Bearer '.$token]);
 $reply=$controller->centralReview($request,(string)$centralPaper->id,(string)$centralTarget->id);
 check($reply->getData(true)['data']===$centralRead()&&str_contains($reply->headers->get('Cache-Control'),'no-store'),'Central review credential route is private');
 foreach(['actor_id'=>'override','organization_id'=>'20','after'=>'-1','revision'=>'bad'] as $key=>$value){$request->query->set($key,$value);$reject(fn()=>$controller->centralReview($request,(string)$centralPaper->id,(string)$centralTarget->id));$request->query->remove($key);}
 $request->query->set('revision',$imageReview['revision']);
 check($controller->centralMedia($request,(string)$centralPaper->id,(string)$centralTarget->id,(string)$centralQuestion->id,$asset)->getData(true)['data']['mime']==='image/png','Central media credential route returns only referenced raster');
 $request->query->set('path','outside');$reject(fn()=>$controller->centralMedia($request,(string)$centralPaper->id,(string)$centralTarget->id,(string)$centralQuestion->id,$asset));$request->query->remove('path');$request->query->remove('revision');
 $request->headers->remove('Authorization');$reject(fn()=>$controller->centralReview($request,(string)$centralPaper->id,(string)$centralTarget->id));
}finally{unlink($configFile);}
check(DB::table('tech4learn_workspaces')->count()===$beforeWorkspaces&&App\Jobs\TranslateExamLanguageJob::$queued===$beforeJobs,'Central read neither provisions workspace nor starts translation');
echo "Central translation review: native fingerprints, paging, credential owner and foreign-record denial passed.\n";


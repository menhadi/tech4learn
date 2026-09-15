<?php
// Synthetic data only. Native completeness/fingerprint logic runs without AI or jobs.
require __DIR__.'/test-exam-authoring.php';
require dirname($argv[3]).'/ExamTranslationService.php';
require __DIR__.'/Tech4LearnExamTranslations.php';
require __DIR__.'/Tech4LearnTranslationController.php';
use Illuminate\Support\Facades\DB;
use App\Models\{Exam,Language,Question,QuestionLang,ExamLanguageTranslation};

DB::table('tech4learn_workspaces')->where('id',$workspace)->update(['restrictions'=>'[]']);
foreach(['syllabus','source_fingerprint','source_field_fingerprints'] as $column)DB::statement('ALTER TABLE exam_language_translations ADD COLUMN '.$column.' TEXT');
foreach(['si_answer1','source_fingerprint','source_field_fingerprints'] as $column)if(!DB::getSchemaBuilder()->hasColumn('question_langs',$column))DB::statement('ALTER TABLE question_langs ADD COLUMN '.$column.' TEXT');
$paper=Exam::create(['organization_id'=>20,'name'=>'Synthetic translation paper','instruction'=>'Read carefully','status'=>'Inactive']);
$target=Language::create(['organization_id'=>20,'name'=>'Synthetic target','code'=>'zz','is_enabled'=>true]);
$english=Language::create(['organization_id'=>20,'name'=>'English','code'=>'en','is_enabled'=>true]);
$paper->languages()->attach([$target->id,$english->id]);
$question=Question::create(['organization_id'=>20,'question'=>'Synthetic source','option1'=>'First','option2'=>'Second']);
$paper->questions()->attach($question->id);
$reader=new App\Services\Tech4LearnExamTranslations();
$native=new App\Services\ExamTranslationService();
$read=fn()=>$reader->review($workspace,10,$actor,$paper->id,$target->id);
$missing=$read();
check($missing['progress']['remaining']===1&&!$missing['progress']['exam_content_ready']&&!$missing['approved'],'Missing native translations remain incomplete and unapproved');
check($missing['items'][0]['translation']===null&&in_array('question',$missing['items'][0]['stale_fields'],true),'Missing translation exposes source and native stale fields');
$translated=QuestionLang::create(array_merge($question->only(['question','option1','option2']),['question_id'=>$question->id,'language_id'=>$target->id,'source_fingerprint'=>$native->questionFingerprint($question),'source_field_fingerprints'=>$native->questionFieldFingerprints($question)]));
ExamLanguageTranslation::create(array_merge($paper->only(['name','instruction','syllabus']),['exam_id'=>$paper->id,'language_id'=>$target->id,'source_fingerprint'=>$native->examFingerprint($paper),'source_field_fingerprints'=>$native->examFieldFingerprints($paper)]));
$complete=$read();
check($complete['progress']['remaining']===0&&$complete['progress']['exam_content_ready']&&!$complete['approved'],'Complete translation still needs native approval');
check($complete['revision']!==$missing['revision']&&$complete['items'][0]['stale_fields']===[],'Translation changes invalidate review revision');
$translated->question='Edited translated wording';$translated->save();
check($read()['revision']!==$complete['revision'],'Target wording edits invalidate review revision');
$paper->languages()->updateExistingPivot($target->id,['translation_status'=>'ready','translation_approved_at'=>now()]);
check($read()['approved'],'Existing native approval is reported');
$question->option1='Changed source option';$question->save();
$stale=$read();check($stale['progress']['remaining']===1&&$stale['items'][0]['stale_fields']===['option1'],'Native per-field fingerprints detect stale source wording');
$en=$reader->review($workspace,10,$actor,$paper->id,$english->id);
check($en['approved']&&$en['items'][0]['source']===$en['items'][0]['translation']&&$en['items'][0]['stale_fields']===[],'English uses native source semantics');
for($i=0;$i<50;$i++){$extra=Question::create(['organization_id'=>20,'question'=>'Synthetic page '.$i]);$paper->questions()->attach($extra->id);}
$page=$read();$next=$reader->review($workspace,10,$actor,$paper->id,$target->id,$page['next'],$page['revision']);
check(count($page['items'])===50&&count($next['items'])===1&&$next['next']===null&&$page['revision']===$next['revision'],'Bounded cursor pages share one paper revision');
$reject=function(callable $operation){try{$operation();throw new RuntimeException('Expected access rejection');}catch(Symfony\Component\HttpKernel\Exception\HttpException|Illuminate\Database\Eloquent\ModelNotFoundException $e){}};
$reject(fn()=>$reader->review($workspace,10,$actor,$paper->id,$target->id,$page['next']));
$translated->question='Changed between pages';$translated->save();
try{$reader->review($workspace,10,$actor,$paper->id,$target->id,$page['next'],$page['revision']);throw new RuntimeException('Expected stale page rejection');}catch(Symfony\Component\HttpKernel\Exception\HttpException $e){check($e->getStatusCode()===409,'Changed translation cannot be mixed into an earlier review');}
$reject(fn()=>$reader->review($workspace,30,$actor,$paper->id,$target->id));
$reject(fn()=>$reader->review($workspace,10,$actor,1,$target->id));
$reject(fn()=>$reader->review($workspace,10,$actor,$paper->id,1));
$target->is_enabled=false;$target->save();$reject($read);$target->is_enabled=true;$target->save();
DB::table('organization_users')->where('organization_id',20)->where('user_id',1)->update(['status'=>0]);$reject($read);
DB::table('organization_users')->where('organization_id',20)->where('user_id',1)->update(['status'=>1]);
DB::table('tech4learn_workspaces')->where('id',$workspace)->update(['restrictions'=>'["exams"]']);$reject($read);
DB::table('tech4learn_workspaces')->where('id',$workspace)->update(['restrictions'=>'[]']);
$foreign=Question::create(['organization_id'=>30,'question'=>'Private foreign question']);$paper->questions()->attach($foreign->id);$reject($read);
$paper->questions()->detach($foreign->id);
check(ExamLanguageTranslation::where('exam_id',$paper->id)->count()===1&&QuestionLang::where('language_id',$target->id)->count()===1,'Review never creates translations or queues translation work');
$configFile=tempnam(sys_get_temp_dir(),'t4l-translation-config-');$token=bin2hex(random_bytes(32));
file_put_contents($configFile,json_encode(['_platform'=>['enabled'=>true,'organization_id'=>10,'token_hash'=>hash('sha256',$token)]]));
$controller=new class($configFile) extends App\Http\Controllers\Tech4LearnTranslationController {public function __construct(private string $file){}protected function configPath():string{return $this->file;}};
try {
    $request=Illuminate\Http\Request::create('https://central.example.test/translations','GET',['actor_id'=>$actor],[],[],['HTTP_AUTHORIZATION'=>'Bearer '.$token]);
    $reply=$controller->review($request,$workspace,(string)$paper->id,(string)$target->id);
    check($reply->getData(true)['data']===$read()&&str_contains($reply->headers->get('Cache-Control'),'no-store'),'Dedicated credential review is private');
    $request->query->set('organization_id','30');$reject(fn()=>$controller->review($request,$workspace,(string)$paper->id,(string)$target->id));$request->query->remove('organization_id');
    $request->query->set('after','-1');$reject(fn()=>$controller->review($request,$workspace,(string)$paper->id,(string)$target->id));$request->query->remove('after');
    $request->headers->remove('Authorization');$reject(fn()=>$controller->review($request,$workspace,(string)$paper->id,(string)$target->id));
} finally {unlink($configFile);}
echo "Native translation review: completeness, revisions, pagination and tenant access passed.\n";

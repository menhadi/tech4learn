<?php
namespace App\Services {
    // Native approval is exercised; worker dispatch is counted, never run.
    class ExamDocumentLifecycleService {public static int $queued=0;public function queue(...$args){self::$queued++;}}
}
namespace App\Jobs {
    class TranslateExamLanguageJob {public static int $queued=0;public static function dispatch(...$args){self::$queued++;}}
}
namespace {
// Synthetic data only. Native completeness/fingerprint logic runs without AI or jobs.
require __DIR__.'/test-exam-authoring.php';
require $GLOBALS['t4lTestTranslationService']??dirname($argv[3]).'/ExamTranslationService.php';
require dirname($argv[3]).'/ExamDocumentController.php';
require dirname($argv[3]).'/ExamDocumentBulkActionService.php';
require $GLOBALS['t4lTestQuestionLangController']??dirname($argv[3]).'/QuestionLangController.php';
require __DIR__.'/Tech4LearnTranslationEdits.php';
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
DB::table('users')->where('id',1)->update(['status'=>0]);
try{$read();throw new RuntimeException('Expected disabled native translation reviewer denial');}catch(Illuminate\Database\Eloquent\ModelNotFoundException $e){}
DB::table('users')->where('id',1)->update(['status'=>1]);
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
$image='data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII=';
$asset=hash('sha256',$image);
$translated->question='<p>Translated image</p><img src="'.$image.'">';$translated->save();
$imageReview=$read();
check(str_contains($imageReview['items'][0]['translation']['question'],'t4l-media:'.$asset)&&!str_contains($imageReview['items'][0]['translation']['question'],'data:image'),'Review rewrites translated image references');
$mediaRead=fn()=>$reader->media($workspace,10,$actor,$paper->id,$target->id,$question->id,$asset,$imageReview['revision']);
$bytes=$mediaRead();check($bytes['mime']==='image/png'&&base64_decode($bytes['base64'],true)===base64_decode(explode(',',$image,2)[1],true),'Only referenced translated raster bytes are returned');
$reject(fn()=>$reader->media($workspace,10,$actor,$paper->id,$target->id,$question->id,str_repeat('0',64),$imageReview['revision']));
$reject(fn()=>$reader->media($workspace,10,$actor,$paper->id,$target->id,$foreign->id,$asset,$imageReview['revision']));
$reject(fn()=>$reader->media($workspace,30,$actor,$paper->id,$target->id,$question->id,$asset,$imageReview['revision']));
$translated->question='Image removed';$translated->save();$reject($mediaRead);
$paper->instruction='<img src="'.$image.'">';$paper->save();$paperReview=$read();
check($reader->media($workspace,10,$actor,$paper->id,$target->id,0,$asset,$paperReview['revision'])['mime']==='image/png','Exam wording images use an explicit exam-level selector');
$normalMedia=$app->make(App\Services\Tech4LearnQuestionMedia::class);
$app->instance(App\Services\Tech4LearnQuestionMedia::class,new class extends App\Services\Tech4LearnQuestionMedia {
    protected function bytes(string $source):string {
        $bytes=parent::bytes($source);
        DB::table('organization_users')->where('organization_id',20)->where('user_id',1)->update(['status'=>0]);
        return $bytes;
    }
});
try {$reject(fn()=>$reader->media($workspace,10,$actor,$paper->id,$target->id,0,$asset,$paperReview['revision']));}
finally {$app->instance(App\Services\Tech4LearnQuestionMedia::class,$normalMedia);DB::table('organization_users')->where('organization_id',20)->where('user_id',1)->update(['status'=>1]);}
$approvalPaper=Exam::create(['organization_id'=>20,'name'=>'Approval fixture','status'=>'Inactive']);
$approvalPaper->languages()->attach($target->id,['auto_pdf'=>true]);
$approvalPaper->packages()->attach($packageSaved['id']);
$approvalQuestion=Question::create(['organization_id'=>20,'question'=>'Approval source']);$approvalPaper->questions()->attach($approvalQuestion->id);
$approvalTranslation=QuestionLang::create(['question_id'=>$approvalQuestion->id,'language_id'=>$target->id,'question'=>'Approval target','source_fingerprint'=>$native->questionFingerprint($approvalQuestion),'source_field_fingerprints'=>$native->questionFieldFingerprints($approvalQuestion)]);
ExamLanguageTranslation::create(['exam_id'=>$approvalPaper->id,'language_id'=>$target->id,'name'=>'Approval translated exam','source_fingerprint'=>$native->examFingerprint($approvalPaper),'source_field_fingerprints'=>$native->examFieldFingerprints($approvalPaper)]);
$approvalReview=$reader->review($workspace,10,$actor,$approvalPaper->id,$target->id);
$approvalFields=['language_id'=>$target->id,'translation_revision'=>$approvalReview['revision']];
$approvalRecord=$service->record('exams',$approvalPaper->fresh());
$approved=$service->save($workspace,20,$actor,$approvalPaper->id,$approvalFields,$approvalRecord['revision'],'translation-approve','exams','approve-translation');
$approvedPivot=$approvalPaper->languages()->first()->pivot;
check($approvedPivot->translation_status==='ready'&&(int)$approvedPivot->translation_approved_by===1&&filled($approvedPivot->translation_approved_at),'Native approval records mapped staff');
check(App\Services\ExamDocumentLifecycleService::$queued===1&&App\Jobs\TranslateExamLanguageJob::$queued===0,'Approval respects native automatic PDF setting without starting AI translation');
check($service->save($workspace,20,$actor,$approvalPaper->id,$approvalFields,$approvalRecord['revision'],'translation-approve','exams','approve-translation')===$approved&&App\Services\ExamDocumentLifecycleService::$queued===1,'Lost approval response replays without another PDF request');
$approvalTranslation->question='Target wording changed after review';$approvalTranslation->save();
$reject(fn()=>$service->save($workspace,20,$actor,$approvalPaper->id,$approvalFields,$approved['revision'],'translation-stale','exams','approve-translation'));
$approvalQuestion->question='Source changed';$approvalQuestion->save();
$incomplete=$reader->review($workspace,10,$actor,$approvalPaper->id,$target->id);
$reject(fn()=>$service->save($workspace,20,$actor,$approvalPaper->id,['language_id'=>$target->id,'translation_revision'=>$incomplete['revision']],$service->record('exams',$approvalPaper->fresh())['revision'],'translation-incomplete','exams','approve-translation'));
DB::table('organization_users')->where('organization_id',20)->where('user_id',1)->update(['status'=>0]);
$reject(fn()=>$service->save($workspace,20,$actor,$approvalPaper->id,$approvalFields,$approvalRecord['revision'],'translation-approve','exams','approve-translation'));
DB::table('organization_users')->where('organization_id',20)->where('user_id',1)->update(['status'=>1]);
check(App\Jobs\TranslateExamLanguageJob::$queued===0&&App\Services\ExamDocumentLifecycleService::$queued===1,'Stale, incomplete and revoked approvals dispatch no work');
$approvalTranslation->source_fingerprint=$native->questionFingerprint($approvalQuestion);$approvalTranslation->source_field_fingerprints=$native->questionFieldFingerprints($approvalQuestion);$approvalTranslation->save();
$foreignPdfPackage=App\Models\Package::create(['organization_id'=>30,'name'=>'Foreign PDF package']);$approvalPaper->packages()->attach($foreignPdfPackage->id);
$freshReview=$reader->review($workspace,10,$actor,$approvalPaper->id,$target->id);
$reject(fn()=>$service->save($workspace,20,$actor,$approvalPaper->id,['language_id'=>$target->id,'translation_revision'=>$freshReview['revision']],$service->record('exams',$approvalPaper->fresh())['revision'],'translation-foreign-package','exams','approve-translation'));
check(App\Services\ExamDocumentLifecycleService::$queued===1,'Approval cannot enqueue a foreign package PDF');
$approvalPaper->packages()->detach($foreignPdfPackage->id);
$approvalQuestion->question='Source needs another translation';$approvalQuestion->save();
$approvalPaper->languages()->updateExistingPivot($target->id,['translation_status'=>'failed','last_error'=>'Synthetic provider failure']);
$refreshReview=$reader->review($workspace,10,$actor,$approvalPaper->id,$target->id);
$refreshRecord=$service->record('exams',$approvalPaper->fresh());
$refreshFields=['language_id'=>$target->id,'translation_revision'=>$refreshReview['revision']];
$GLOBALS['t4lTestAiTranslation']=false;
$reject(fn()=>$service->save($workspace,20,$actor,$approvalPaper->id,$refreshFields,$refreshRecord['revision'],'translation-plan-denied','exams','refresh-translation'));
check(App\Jobs\TranslateExamLanguageJob::$queued===0,'Plan restriction blocks dispatch');
$GLOBALS['t4lTestAiTranslation']=true;
$refresh=$service->save($workspace,20,$actor,$approvalPaper->id,$refreshFields,$refreshRecord['revision'],'translation-refresh','exams','refresh-translation');
check($refresh['translation_requested']&&App\Jobs\TranslateExamLanguageJob::$queued===1,'Native bulk service queues one selected translation');
$retryPivot=$approvalPaper->languages()->first()->pivot;
check($retryPivot->translation_status==='pending'&&!$retryPivot->last_error&&!$retryPivot->translation_approved_at,'Explicit retry resets native failed stop state and prior approval');
check($service->save($workspace,20,$actor,$approvalPaper->id,$refreshFields,$refreshRecord['revision'],'translation-refresh','exams','refresh-translation')===$refresh&&App\Jobs\TranslateExamLanguageJob::$queued===1,'Translation refresh replay does not dispatch again');
$reject(fn()=>$service->save($workspace,20,$actor,$approvalPaper->id,['language_id'=>$target->id,'translation_revision'=>str_repeat('0',64)],$refreshRecord['revision'],'translation-refresh-stale','exams','refresh-translation'));
check(App\Jobs\TranslateExamLanguageJob::$queued===1,'Stale refresh cannot dispatch');
$approvalPaper->languages()->updateExistingPivot($target->id,['translation_status'=>'processing']);
$processingReview=$reader->review($workspace,10,$actor,$approvalPaper->id,$target->id);
$reject(fn()=>$service->save($workspace,20,$actor,$approvalPaper->id,['language_id'=>$target->id,'translation_revision'=>$processingReview['revision']],$service->record('exams',$approvalPaper->fresh())['revision'],'translation-already-processing','exams','refresh-translation'));
check(App\Jobs\TranslateExamLanguageJob::$queued===1,'Processing refresh cannot dispatch another job');
$approvalPaper->languages()->updateExistingPivot($target->id,['translation_status'=>'ready','translation_approved_at'=>now(),'translation_approved_by'=>1]);
$approvalQuestion->option1='Source option';$approvalQuestion->si_answer1='Source answer';$approvalQuestion->save();
$approvalTranslation->option1='<img src="'.$image.'">';$approvalTranslation->si_answer1='Preserved translated answer';
$hashes=$native->questionFieldFingerprints($approvalQuestion);$hashes['question']=str_repeat('0',64);$approvalTranslation->source_field_fingerprints=$hashes;$approvalTranslation->save();
$secondPaper=Exam::create(['organization_id'=>20,'name'=>'Another owned paper']);$secondPaper->questions()->attach($approvalQuestion->id);$secondPaper->languages()->attach($target->id,['translation_status'=>'ready','translation_approved_at'=>now()]);
$editReview=$reader->review($workspace,10,$actor,$approvalPaper->id,$target->id);
$editRecord=$service->record('exams',$approvalPaper->fresh());
$translationEdit=['language_id'=>$target->id,'translation_revision'=>$editReview['revision'],'question_id'=>$approvalQuestion->id,'wording'=>['question'=>'<p>Manually reviewed translation</p>']];
$manual=$service->save($workspace,20,$actor,$approvalPaper->id,$translationEdit,$editRecord['revision'],'translation-edit','exams','save-question-translation');
$edited=QuestionLang::findOrFail($approvalTranslation->id);
check($edited->question==='<p>Manually reviewed translation</p>'&&$edited->option1==='<img src="'.$image.'">'&&$edited->si_answer1==='Preserved translated answer','Native translation update preserves untouched images and unsupported short-answer field');
check($approvalQuestion->fresh()->question==='Source needs another translation'&&$native->questionFieldsNeedingTranslation($approvalQuestion->fresh(),$edited)===[],'Only translated wording changes; native field fingerprints record the reviewed source');
check(!$approvalPaper->languages()->first()->pivot->translation_approved_at&&!$secondPaper->languages()->first()->pivot->translation_approved_at,'Shared owned question edits clear affected language approvals');
check(App\Services\ExamDocumentInvalidationService::$questionCalls===1,'Edited translation invalidates native paper artifacts');
check($service->save($workspace,20,$actor,$approvalPaper->id,$translationEdit,$editRecord['revision'],'translation-edit','exams','save-question-translation')===$manual&&App\Services\ExamDocumentInvalidationService::$questionCalls===1,'Translation save replay does not invalidate twice');
$reject(fn()=>$service->save($workspace,20,$actor,$approvalPaper->id,$translationEdit,$manual['revision'],'translation-edit-stale','exams','save-question-translation'));
$newLanguage=Language::create(['organization_id'=>20,'name'=>'New translation','code'=>'yy','is_enabled'=>true]);$approvalPaper->languages()->attach($newLanguage->id);
$newReview=$reader->review($workspace,10,$actor,$approvalPaper->id,$newLanguage->id);
$newRecord=$service->record('exams',$approvalPaper->fresh());
$newFields=['language_id'=>$newLanguage->id,'translation_revision'=>$newReview['revision'],'question_id'=>$approvalQuestion->id,'wording'=>['question'=>'New target']];
$service->save($workspace,20,$actor,$approvalPaper->id,$newFields,$newRecord['revision'],'translation-new','exams','save-question-translation');
$newTarget=QuestionLang::where('question_id',$approvalQuestion->id)->where('language_id',$newLanguage->id)->sole();
check(in_array('option1',$native->questionFieldsNeedingTranslation($approvalQuestion,$newTarget),true),'Creating one field does not mark untranslated options current');
check(!in_array('option2',$native->questionFieldsNeedingTranslation($approvalQuestion,$newTarget),true),'Empty source and target fields need no invented translation');
$foreignPaper=Exam::create(['organization_id'=>30,'name'=>'Foreign paper']);$foreignPaper->questions()->attach($approvalQuestion->id);
$foreignReview=$reader->review($workspace,10,$actor,$approvalPaper->id,$newLanguage->id);
$reject(fn()=>$service->save($workspace,20,$actor,$approvalPaper->id,array_replace($newFields,['translation_revision'=>$foreignReview['revision']]),$service->record('exams',$approvalPaper->fresh())['revision'],'translation-cross-paper','exams','save-question-translation'));
$foreignPaper->questions()->detach($approvalQuestion->id);
$invalidReview=$reader->review($workspace,10,$actor,$approvalPaper->id,$newLanguage->id);$beforeTarget=$newTarget->fresh()->getAttributes();
try{$service->save($workspace,20,$actor,$approvalPaper->id,array_replace($newFields,['translation_revision'=>$invalidReview['revision'],'wording'=>['question'=>'']]),$service->record('exams',$approvalPaper->fresh())['revision'],'translation-invalid','exams','save-question-translation');throw new RuntimeException('Expected native translation validation');}catch(Illuminate\Validation\ValidationException $e){}
check($newTarget->fresh()->getAttributes()===$beforeTarget,'Native validation failure leaves translated wording and fingerprints unchanged');
$approvalPaper->languages()->updateExistingPivot($target->id,['translation_status'=>'ready','translation_approved_at'=>now(),'translation_approved_by'=>1]);
$examReview=$reader->review($workspace,10,$actor,$approvalPaper->id,$target->id);
$examRecord=$service->record('exams',$approvalPaper->fresh());
$examTarget=ExamLanguageTranslation::where('exam_id',$approvalPaper->id)->where('language_id',$target->id)->sole();
$examTarget->instruction='<img src="'.$image.'">';$examTarget->save();
$examReview=$reader->review($workspace,10,$actor,$approvalPaper->id,$target->id);
$examFields=['language_id'=>$target->id,'translation_revision'=>$examReview['revision'],'wording'=>['name'=>'Manually translated exam title']];
$invalidations=App\Services\ExamDocumentInvalidationService::$calls;
$examSaved=$service->save($workspace,20,$actor,$approvalPaper->id,$examFields,$examRecord['revision'],'exam-wording-edit','exams','save-exam-translation');
check($examTarget->fresh()->name==='Manually translated exam title'&&$examTarget->fresh()->instruction==='<img src="'.$image.'">'&&$approvalPaper->fresh()->name==='Approval fixture','Exam translation edit preserves source and untouched media');
check(!$approvalPaper->languages()->first()->pivot->translation_approved_at&&App\Services\ExamDocumentInvalidationService::$calls===$invalidations+1,'Exam translation save clears approval and invalidates native PDFs');
check($service->save($workspace,20,$actor,$approvalPaper->id,$examFields,$examRecord['revision'],'exam-wording-edit','exams','save-exam-translation')===$examSaved&&App\Services\ExamDocumentInvalidationService::$calls===$invalidations+1,'Exam wording retry preserves exactly one invalidation');
$reject(fn()=>$service->save($workspace,20,$actor,$approvalPaper->id,$examFields,$examSaved['revision'],'exam-wording-stale','exams','save-exam-translation'));
$approvalPaper->instruction='Source instructions need translation';$approvalPaper->save();
$newExamReview=$reader->review($workspace,10,$actor,$approvalPaper->id,$newLanguage->id);
$newExamRecord=$service->record('exams',$approvalPaper->fresh());
$service->save($workspace,20,$actor,$approvalPaper->id,['language_id'=>$newLanguage->id,'translation_revision'=>$newExamReview['revision'],'wording'=>['name'=>'New exam translation']],$newExamRecord['revision'],'exam-wording-new','exams','save-exam-translation');
$newExamTarget=ExamLanguageTranslation::where('exam_id',$approvalPaper->id)->where('language_id',$newLanguage->id)->sole();
check(isset($newExamTarget->source_field_fingerprints['name'],$newExamTarget->source_field_fingerprints['syllabus'])&&!isset($newExamTarget->source_field_fingerprints['instruction']),'Partial exam translation does not certify untouched fields');
$badExamReview=$reader->review($workspace,10,$actor,$approvalPaper->id,$newLanguage->id);
$beforeExam=$newExamTarget->getAttributes();
try{$service->save($workspace,20,$actor,$approvalPaper->id,['language_id'=>$newLanguage->id,'translation_revision'=>$badExamReview['revision'],'wording'=>['name'=>'']],$service->record('exams',$approvalPaper->fresh())['revision'],'exam-wording-invalid','exams','save-exam-translation');throw new RuntimeException('Expected exam translation validation');}catch(Illuminate\Validation\ValidationException $e){}
check($newExamTarget->fresh()->getAttributes()===$beforeExam,'Invalid exam title rolls back without changing translation');
$reject(fn()=>$service->save($workspace,20,$actor,$approvalPaper->id,['language_id'=>$newLanguage->id,'translation_revision'=>$badExamReview['revision'],'wording'=>['organization_id'=>30]],$service->record('exams',$approvalPaper->fresh())['revision'],'exam-wording-override','exams','save-exam-translation'));
$formulaReview=$reader->review($workspace,10,$actor,$approvalPaper->id,$newLanguage->id);
$formulaRecord=$service->record('exams',$approvalPaper->fresh());
$formulaWording='<p>Translated \\(x^{2}\\) and <math><mfrac><mi>y</mi><mn>2</mn></mfrac></math>.</p>';
$formulaFields=array_replace($newFields,['translation_revision'=>$formulaReview['revision'],'wording'=>['question'=>$formulaWording]]);
$formulaSaved=$service->save($workspace,20,$actor,$approvalPaper->id,$formulaFields,$formulaRecord['revision'],'translated-formula-replacement','exams','save-question-translation');
check($newTarget->fresh()->question===$formulaWording,'Translated checked TeX and retained MathML survive native save');
check($service->save($workspace,20,$actor,$approvalPaper->id,$formulaFields,$formulaRecord['revision'],'translated-formula-replacement','exams','save-question-translation')===$formulaSaved,'Translated formula replay retains the outcome');
echo "Native translation review and approval: fingerprints, pagination, media, formula replacement, scope and retry passed.\n";
}

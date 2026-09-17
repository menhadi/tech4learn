<?php
if(isset($argv[5]))$GLOBALS['t4lTestQuestionLangController']=$argv[5];
require __DIR__.'/test-central-translation-writes.php';
use App\Models\{Exam,Question,QuestionLang};
foreach([false,true] as $central){
 $owner=$central?10:20;$lang=$central?$centralTarget:$target;
 $paper=Exam::create(['organization_id'=>$owner,'name'=>'Synthetic model-answer paper','status'=>'Inactive']);$paper->languages()->attach($lang->id);
 $question=Question::create(['organization_id'=>$owner,'question'=>'Source','si_answer1'=>'Source model answer']);$paper->questions()->attach($question->id);
 $translation=QuestionLang::create(['question_id'=>$question->id,'language_id'=>$lang->id,'question'=>'Translated question','si_answer1'=>'Existing translated answer']);
 $read=fn()=>$central?$reader->centralReview(10,$paper->id,$lang->id):$reader->review($workspace,10,$actor,$paper->id,$lang->id);
 $record=fn()=>$service->record('exams',$paper->fresh());
 $fields=fn($wording)=>['language_id'=>$lang->id,'question_id'=>$question->id,'translation_revision'=>$read()['revision'],'wording'=>$wording];
 $save=fn($values,$revision,$key)=>$central?$service->saveCentralExamAction(10,$ca,$paper->id,$values,$revision,$key,'save-question-translation'):$service->save($workspace,20,$actor,$paper->id,$values,$revision,$key,'exams','save-question-translation');
 $payload=$fields(['si_answer1'=>'<p>Model <math><mi>x</mi></math></p>']);$revision=$record()['revision'];$key=$uuid();
 $result=$save($payload,$revision,$key);
 check(str_contains($translation->fresh()->si_answer1,'<math>')&&$question->fresh()->si_answer1==='Source model answer','Native translated answer saves formula without changing source');
 check($save($payload,$revision,$key)===$result,'Translated model answer retry is stable');
 $save($fields(['question'=>'Other wording edit']),$record()['revision'],$uuid());
 check(str_contains($translation->fresh()->si_answer1,'<math>'),'Editing another translated field preserves model answer');
 // Exercise the old native form directly: it does not submit this new field.
 $legacy=Illuminate\Http\Request::create('/','POST',['language_id'=>$lang->id,'question'=>'Legacy native wording']);
 $session=new Illuminate\Session\Store('model-answer-test',new Illuminate\Session\ArraySessionHandler(120));
 $legacy->setLaravelSession($session);
 $redirect=app('redirect');$nativeRedirect=new Illuminate\Routing\Redirector(app('url'));$nativeRedirect->setSession($session);
 app()->instance('redirect',$nativeRedirect);
 App\Support\Tenant::resolve(App\Models\Organization::findOrFail($owner)->domain);
 try{app(App\Http\Controllers\QuestionLangController::class)->update($legacy,$translation->id);}
 finally{app()->instance('redirect',$redirect);App\Support\Tenant::clear();}
 check(str_contains($translation->fresh()->si_answer1,'<math>'),'Legacy native form omission preserves existing model answer');
 $reject(fn()=>$save($fields(['si_answer1'=>'<script>unsafe</script>']),$record()['revision'],$uuid()));
 $save($fields(['si_answer1'=>null]),$record()['revision'],$uuid());
 check($translation->fresh()->si_answer1===null,'Explicit clear removes model answer');
 check(in_array('si_answer1',$read()['items'][0]['stale_fields'],true),'Cleared required model answer needs translation review');
 $imageFields=['language_id'=>$lang->id,'question_id'=>$question->id,'translation_revision'=>$read()['revision'],'field'=>'si_answer1','image'=>'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII='];
 $imageSave=fn($values,$revision,$key)=>$central?$service->saveCentralExamAction(10,$ca,$paper->id,$values,$revision,$key,'set-translation-image'):$service->save($workspace,20,$actor,$paper->id,$values,$revision,$key,'exams','set-translation-image');
 $revision=$record()['revision'];$key=$uuid();$writes=$imageDisk->writes;
 $imageResult=$imageSave($imageFields,$revision,$key);
 check(str_contains($translation->fresh()->si_answer1,'<img')&&str_contains($read()['items'][0]['translation']['si_answer1'],'t4l-media:'),'Model-answer image saved through native controller and private review');
 check($imageSave($imageFields,$revision,$key)===$imageResult&&$imageDisk->writes===$writes+1,'Model-answer image retry never duplicates upload');
 check($question->fresh()->si_answer1==='Source model answer','Translated image never changes source model answer');
}
echo "Native translated model answers: central/organisation writes, formulas, unchanged source, retries and explicit clearing passed.\n";

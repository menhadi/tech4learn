<?php
require __DIR__.'/test-retained-translation-images.php';
use App\Models\{Exam,ExamLanguageTranslation};
use Illuminate\Support\Facades\DB;
foreach([false,true] as $central){
 $owner=$central?10:20;$lang=$central?$centralTarget:$target;
 $paper=Exam::create(['organization_id'=>$owner,'name'=>'Source exam','instruction'=>'Source instructions','syllabus'=>'Source syllabus','status'=>'Inactive']);
 $paper->languages()->attach($lang->id,['translation_status'=>'ready','translation_approved_at'=>now()]);
 $source='/storage/images/upload/t4l/'.$owner.'/exam-retained.png';$other='/storage/images/upload/t4l/'.$owner.'/syllabus-retained.png';
 $translation=ExamLanguageTranslation::create(['exam_id'=>$paper->id,'language_id'=>$lang->id,'name'=>'Translated title','instruction'=>'<p>Before <img src="'.$source.'"> <math><mi>x</mi></math></p>','syllabus'=>'<img src="'.$other.'">']);
 $read=fn()=>$central?$reader->centralReview(10,$paper->id,$lang->id):$reader->review($workspace,10,$actor,$paper->id,$lang->id);
 $record=fn()=>$service->record('exams',$paper->fresh());
 $values=fn($text)=>['language_id'=>$lang->id,'translation_revision'=>$read()['revision'],'wording'=>['instruction'=>$text]];
 $save=fn($body,$revision,$key)=>$central?$service->saveCentralExamAction(10,$ca,$paper->id,$body,$revision,$key,'save-exam-translation'):$service->save($workspace,20,$actor,$paper->id,$body,$revision,$key,'exams','save-exam-translation');
 $opaque=$read()['translation']['instruction'];check(str_contains($opaque,'t4l-media:'.hash('sha256',$source)),'Exam review hides image source');
 $body=$values(str_replace(['Before','<mi>x</mi>'],['After','<msup><mi>x</mi><mn>2</mn></msup>'],$opaque));$revision=$record()['revision'];$key=$uuid();
 $saved=$save($body,$revision,$key);$stored=$translation->fresh()->instruction;
 check(str_contains($stored,$source)&&str_contains($stored,'After')&&str_contains($stored,'<msup>')&&!str_contains($stored,'t4l-media:'),'Exam image survives native translated wording/formula edits');
 check($translation->fresh()->syllabus==='<img src="'.$other.'">'&&$paper->fresh()->instruction==='Source instructions','Other translated field and source exam remain unchanged');
 check($paper->languages()->first()->pivot->translation_approved_at===null,'Edited translated exam requires approval again');
 check($save($body,$revision,$key)===$saved,'Exam image wording retry returns original receipt');
 $ledger=$central?'tech4learn_central_requests':'tech4learn_authoring_requests';$count=DB::table($ledger)->count();
 foreach(['<img src="'.$source.'">','<img src="https://example.invalid/image.png">','<img src="t4l-media:'.hash('sha256',$other).'">','<img src="t4l-media:'.str_repeat('0',64).'">','<img src="t4l-media:'.hash('sha256',$source).'" onerror="bad()">'] as $bad)$reject(fn()=>$save($values($bad),$record()['revision'],$uuid()));
 $title=$values($opaque);$title['wording']=['name'=>$opaque];$reject(fn()=>$save($title,$record()['revision'],$uuid()));
 check($translation->fresh()->instruction===$stored&&DB::table($ledger)->count()===$count,'Rejected exam image edits preserve stored wording and receipts');
 $reject(fn()=>$save($body,$record()['revision'],$uuid()));
 $translation->delete();$reject(fn()=>$save($values($opaque),$record()['revision'],$uuid()));
 check(ExamLanguageTranslation::where('exam_id',$paper->id)->count()===0,'Missing exam translation cannot adopt old image identity');
}
echo "Retained exam translation images: both owners, text/formulas, field isolation, source preservation, approval invalidation and replay passed.\n";

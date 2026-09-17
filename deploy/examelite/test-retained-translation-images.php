<?php
require __DIR__.'/test-central-translation-writes.php';
use App\Models\{Exam,Question,QuestionLang};
use Illuminate\Support\Facades\DB;

foreach([false,true] as $central){
    $owner=$central?10:20;$lang=$central?$centralTarget:$target;
    $imagePaper=Exam::create(['organization_id'=>$owner,'name'=>'Synthetic retained translation','status'=>'Inactive']);
    $imagePaper->languages()->attach($lang->id);
    $imageQuestion=Question::create(['organization_id'=>$owner,'question'=>'Source wording']);
    $imagePaper->questions()->attach($imageQuestion->id);
    $source='/storage/images/upload/t4l/'.$owner.'/translation-retained.png';
    $other='/storage/images/upload/t4l/'.$owner.'/other-field.png';
    $nativeTarget=QuestionLang::create(['question_id'=>$imageQuestion->id,'language_id'=>$lang->id,'question'=>'<p>Translated <img src="'.$source.'"> <math><mi>x</mi></math></p>','explanation'=>'<img src="'.$other.'">']);
    $read=fn()=>$central?$reader->centralReview(10,$imagePaper->id,$lang->id):$reader->review($workspace,10,$actor,$imagePaper->id,$lang->id);
    $record=fn()=>$service->record('exams',$imagePaper->fresh());
    $fields=fn($wording)=>['language_id'=>$lang->id,'question_id'=>$imageQuestion->id,'translation_revision'=>$read()['revision'],'wording'=>['question'=>$wording]];
    $save=fn($values,$revision,$key)=>$central?$service->saveCentralExamAction(10,$ca,$imagePaper->id,$values,$revision,$key,'save-question-translation'):$service->save($workspace,20,$actor,$imagePaper->id,$values,$revision,$key,'exams','save-question-translation');
    $before=$read();$opaque=$before['items'][0]['translation']['question'];
    check(str_contains($opaque,'t4l-media:'.hash('sha256',$source))&&!str_contains($opaque,$source),'Review returns only opaque translated image identity');
    $changed=str_replace('<mi>x</mi>','<msup><mi>x</mi><mn>2</mn></msup>',$opaque);
    $request=$uuid();$revision=$record()['revision'];$values=$fields($changed);
    $saved=$save($values,$revision,$request);
    $stored=$nativeTarget->fresh()->question;
    check(str_contains($stored,$source)&&str_contains($stored,'<msup>')&&!str_contains($stored,'t4l-media:'),'Native save restores the original image source with changed formula');
    check($nativeTarget->fresh()->explanation==='<img src="'.$other.'">','Untouched translated fields are preserved');
    check($save($values,$revision,$request)===$saved,'Mixed translation retry preserves saved outcome');
    $after=$read();$ledger=$central?'tech4learn_central_requests':'tech4learn_authoring_requests';$count=DB::table($ledger)->count();
    foreach([
        '<img src="'.$source.'">',
        '<img src="https://example.invalid/foreign.png">',
        '<img src="t4l-media:'.hash('sha256',$other).'">',
        '<img src="t4l-media:'.str_repeat('0',64).'">',
        '<img src="t4l-media:'.hash('sha256',$source).'" onerror="alert(1)">',
        '<img src="t4l-media:'.hash('sha256',$source).'" srcset="bad.png 2x">',
    ] as $bad)$reject(fn()=>$save($fields($bad),$record()['revision'],$uuid()));
    check($nativeTarget->fresh()->question===$stored&&DB::table($ledger)->count()===$count,'Rejected translated images leave native wording and retry ledger unchanged');
    $reject(fn()=>$save($values,$record()['revision'],$uuid()));
    $nativeTarget->delete();
    $reject(fn()=>$save($fields($changed),$record()['revision'],$uuid()));
    check(QuestionLang::where('question_id',$imageQuestion->id)->count()===0,'A missing translation cannot adopt an old image identity');
}
echo "Retained translation images: native organisation/central formula edits, scoped opaque restoration, stale review and retry checks passed.\n";

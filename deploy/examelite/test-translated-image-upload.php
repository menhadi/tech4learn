<?php
// Isolated helper check; no new route or native application bootstrap.
require __DIR__.'/test-central-translation-writes.php';
use App\Models\{Question,QuestionLang};
use App\Services\{Tech4LearnQuestionImageUpload,Tech4LearnQuestionMedia};
use Illuminate\Support\Facades\DB;

$uploader=app(Tech4LearnQuestionImageUpload::class);
$png='iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII=';
foreach([10,20] as $owner){
    $lang=$owner===10?$centralTarget:$target;
    $source=Question::create(['organization_id'=>$owner,'question'=>'Original source']);
    $translation=QuestionLang::create(['question_id'=>$source->id,'language_id'=>$lang->id,'question'=>'<p>Translated diagram <math><mi>x</mi></math></p>','explanation'=>'Keep explanation']);
    $stored=null;$before=$imageDisk->writes;
    $input=['field'=>'question','image'=>$png];
    $patch=$uploader->applyTranslation($source,$translation,$input,$stored);
    check($stored!==null&&str_starts_with($stored,'images/upload/t4l/'.$owner.'/'),'Translated file uses source owner');
    check($imageDisk->writes===$before+1&&count($patch)===1,'Translation upload stores one file and returns one field');
    check(str_contains($patch['question'],'<math><mi>x</mi></math>')&&str_contains($patch['question'],'Translated diagram'),'Existing formula and wording survive upload');
    check($translation->fresh()->question===$translation->question&&$source->fresh()->question==='Original source','Helper does not bypass native persistence');
    $translation->question=$patch['question'];
    $asset=array_key_first(app(Tech4LearnQuestionMedia::class)->sources($patch['question']));
    $replacement=null;$replaced=$uploader->applyTranslation($source,$translation,$input+['asset'=>$asset],$replacement);
    check($replacement!==$stored&&isset($imageDisk->files[$stored])&&substr_count($replaced['question'],'<img')===1,'Replacement retains old shared file and one image reference');
    $newAsset=array_key_first(app(Tech4LearnQuestionMedia::class)->sources($replaced['question']));
    $translation->question=$replaced['question'];$unused=null;
    $removed=$uploader->applyTranslation($source,$translation,['field'=>'question','remove'=>true,'asset'=>$newAsset],$unused);
    check(!str_contains($removed['question'],'<img')&&$unused===null&&isset($imageDisk->files[$replacement]),'Removal only removes reference, preserving shared bytes');
    $before=$imageDisk->writes;
    foreach([
        ['field'=>'si_answer1'],['field'=>'organization_id'],['image'=>base64_encode('invalid')],['asset'=>str_repeat('0',64)],['remove'=>true]
    ] as $bad){
        $reject(function()use($uploader,$source,$translation,$input,$bad){$path=null;$uploader->applyTranslation($source,$translation,array_replace($input,$bad),$path);});
    }
    $foreign=Question::create(['organization_id'=>$owner===10?20:10,'question'=>'Foreign']);
    $reject(function()use($uploader,$foreign,$translation,$input){$path=null;$uploader->applyTranslation($foreign,$translation,$input,$path);});
    $reject(function()use($uploader,$source,$input){$path=null;$uploader->applyTranslation($source,new QuestionLang(['question_id'=>$source->id]),$input,$path);});
    check($imageDisk->writes===$before,'Invalid field, image, missing target and mismatched source never write files');
    $uploader->discard($stored);$uploader->discard($replacement);
    check(!isset($imageDisk->files[$stored])&&!isset($imageDisk->files[$replacement]),'Caller can discard uploads after failed native transaction');
}
echo "Translated image helper: owner paths, preserved wording, replace/remove, rejected fields and cleanup passed.\n";

foreach([false,true] as $central){
    $owner=$central?10:20;$lang=$central?$centralTarget:$target;
    $paper=App\Models\Exam::create(['organization_id'=>$owner,'name'=>'Synthetic translated image paper','status'=>'Inactive']);
    $paper->languages()->attach($lang->id);
    $source=Question::create(['organization_id'=>$owner,'question'=>'Source unchanged']);$paper->questions()->attach($source->id);
    $translation=QuestionLang::create(['question_id'=>$source->id,'language_id'=>$lang->id,'question'=>'<p>Translated text</p>','explanation'=>'Preserve this']);
    $record=fn()=>$service->record('exams',$paper->fresh());
    $review=fn()=>$central?$reader->centralReview(10,$paper->id,$lang->id):$reader->review($workspace,10,$actor,$paper->id,$lang->id);
    $fields=fn()=>['language_id'=>$lang->id,'question_id'=>$source->id,'translation_revision'=>$review()['revision'],'field'=>'question','image'=>$png];
    $save=fn($values,$revision,$key)=>$central?$service->saveCentralExamAction(10,$ca,$paper->id,$values,$revision,$key,'set-translation-image'):$service->save($workspace,20,$actor,$paper->id,$values,$revision,$key,'exams','set-translation-image');
    $values=$fields();$revision=$record()['revision'];$key=$uuid();$writes=$imageDisk->writes;
    $saved=$save($values,$revision,$key);
    check($imageDisk->writes===$writes+1&&str_contains($translation->fresh()->question,'/storage/images/upload/t4l/'.$owner.'/'),'Native translated image action persists under correct owner');
    check($translation->fresh()->explanation==='Preserve this'&&$source->fresh()->question==='Source unchanged','Native image action preserves other wording and source');
    check($save($values,$revision,$key)===$saved&&$imageDisk->writes===$writes+1,'Lost-response retry does not upload or invalidate twice');
    $after=$review();check(str_contains($after['items'][0]['translation']['question'],'t4l-media:'),'Review projects new image as private opaque reference');
    check(DB::table('exam_languages')->where('exam_id',$paper->id)->where('language_id',$lang->id)->value('translation_status')==='pending','Image edit clears prior approval');
    $writes=$imageDisk->writes;$reject(fn()=>$save($values,$record()['revision'],$uuid()));
    foreach([['question_id'=>$foreign->id],['field'=>'organization_id'],['wording'=>['question'=>'injected']],['image'=>base64_encode('invalid')]] as $bad)$reject(fn()=>$save(array_replace($fields(),$bad),$record()['revision'],$uuid()));
    check($imageDisk->writes===$writes,'Stale review and invalid/foreign actions never write image files');
    $asset=array_key_first(app(Tech4LearnQuestionMedia::class)->sources($translation->fresh()->question));
    $save($fields()+['asset'=>$asset],$record()['revision'],$uuid());
    check(substr_count($translation->fresh()->question,'<img')===1,'Native replacement keeps one reference');
    $asset=array_key_first(app(Tech4LearnQuestionMedia::class)->sources($translation->fresh()->question));
    $remove=$fields();unset($remove['image']);$remove+=['asset'=>$asset,'remove'=>true];
    $save($remove,$record()['revision'],$uuid());
    check(!str_contains($translation->fresh()->question,'<img'),'Native reference removal persisted');
    $oldController=app(App\Http\Controllers\QuestionLangController::class);
    $app->instance(App\Http\Controllers\QuestionLangController::class,new class extends App\Http\Controllers\QuestionLangController {
        public function update(Illuminate\Http\Request $request,$id){throw new RuntimeException('synthetic native failure');}
    });
    $files=count($imageDisk->files);$before=$translation->fresh()->question;
    try{$save($fields(),$record()['revision'],$uuid());throw new RuntimeException('Expected failed save');}
    catch(RuntimeException $e){check($e->getMessage()==='synthetic native failure','Expected injected native failure');}
    finally{$app->instance(App\Http\Controllers\QuestionLangController::class,$oldController);}
    check(count($imageDisk->files)===$files&&$translation->fresh()->question===$before,'Failed native save rolls back wording and removes new upload');
    DB::table('exam_languages')->where('exam_id',$paper->id)->where('language_id',$lang->id)->update(['translation_status'=>'processing']);
    $writes=$imageDisk->writes;$reject(fn()=>$save($fields(),$record()['revision'],$uuid()));
    check($imageDisk->writes===$writes,'Processing translation cannot receive new uploads');
    DB::table('exam_languages')->where('exam_id',$paper->id)->where('language_id',$lang->id)->update(['translation_status'=>'pending']);
    $translation->delete();$reject(fn()=>$save($fields(),$record()['revision'],$uuid()));
    check($imageDisk->writes===$writes,'Missing translated wording cannot receive new uploads');
}
echo "Translated image native actions: central/organisation persistence, review/retry, scope and failed-save cleanup passed.\n";

<?php
// Isolated helper check; no new route or native application bootstrap.
require __DIR__.'/test-central-translation-writes.php';
use App\Models\{Question,QuestionLang};
use App\Services\{Tech4LearnQuestionImageUpload,Tech4LearnQuestionMedia};

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

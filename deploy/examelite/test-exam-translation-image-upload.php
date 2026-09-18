<?php
require __DIR__.'/test-translated-image-upload.php';
use App\Models\{Exam,ExamLanguageTranslation};
use App\Services\Tech4LearnQuestionMedia;
use Illuminate\Support\Facades\DB;
$temporary=sys_get_temp_dir().'/t4l-exam-upload-'.bin2hex(random_bytes(8));mkdir($temporary,0700,true);
$imageDisk=new class($temporary) {
 public int $writes=0;public array $files=[];
 public function __construct(private string $root){}
 public function disk($name){return $this;}
 public function path($path){return $this->root.'/'.$path;}
 public function put($path,$bytes){$this->writes++;$this->files[$path]=$bytes;if(!is_dir(dirname($this->path($path))))mkdir(dirname($this->path($path)),0700,true);return file_put_contents($this->path($path),$bytes)!==false;}
 public function delete($path){unset($this->files[$path]);if(is_file($this->path($path)))unlink($this->path($path));return true;}
};
$app->instance('filesystem',$imageDisk);Illuminate\Support\Facades\Storage::clearResolvedInstance('filesystem');
try {
foreach([false,true] as $central){
 $owner=$central?10:20;$lang=$central?$centralTarget:$target;
 $paper=Exam::create(['organization_id'=>$owner,'name'=>'Source paper','instruction'=>'Source unchanged','status'=>'Inactive']);$paper->languages()->attach($lang->id);
 $translation=ExamLanguageTranslation::create(['exam_id'=>$paper->id,'language_id'=>$lang->id,'name'=>'Translated paper','instruction'=>'<p>Translated <math><mi>x</mi></math></p>','syllabus'=>'Keep syllabus']);
 $record=fn()=>$service->record('exams',$paper->fresh());
 $review=fn()=>$central?$reader->centralReview(10,$paper->id,$lang->id):$reader->review($workspace,10,$actor,$paper->id,$lang->id);
 $fields=fn()=>['language_id'=>$lang->id,'question_id'=>0,'translation_revision'=>$review()['revision'],'field'=>'instruction','image'=>$png];
 $save=fn($body,$revision,$key)=>$central?$service->saveCentralExamAction(10,$ca,$paper->id,$body,$revision,$key,'set-translation-image'):$service->save($workspace,20,$actor,$paper->id,$body,$revision,$key,'exams','set-translation-image');
 $body=$fields();$revision=$record()['revision'];$key=$uuid();$writes=$imageDisk->writes;
 $saved=$save($body,$revision,$key);$stored=$translation->fresh()->instruction;
 check(str_contains($stored,'/storage/images/upload/t4l/'.$owner.'/')&&str_contains($stored,'<math>'),'Exam translation upload preserves formula and stores owner path');
 check($save($body,$revision,$key)===$saved&&$imageDisk->writes===$writes+1,'Exam image replay writes only once');
 check($translation->fresh()->syllabus==='Keep syllabus'&&$paper->fresh()->instruction==='Source unchanged','Exam image leaves source and other fields unchanged');
 $after=$review();$asset=array_key_first(app(Tech4LearnQuestionMedia::class)->sources($stored));
 $bytes=$central?$reader->centralMedia(10,$paper->id,$lang->id,0,$asset,$after['revision']):$reader->media($workspace,10,$actor,$paper->id,$lang->id,0,$asset,$after['revision']);
 check($bytes['base64']===$png,'Exam image private preview reads uploaded bytes');
 $reject(fn()=>$save($body,$record()['revision'],$uuid()));
 foreach(['name','question','organization_id'] as $field)$reject(fn()=>$save(array_replace($fields(),['field'=>$field]),$record()['revision'],$uuid()));
 $save($fields()+['asset'=>$asset],$record()['revision'],$uuid());
 check(substr_count($translation->fresh()->instruction,'<img')===1,'Replacement keeps one exam image');
 $asset=array_key_first(app(Tech4LearnQuestionMedia::class)->sources($translation->fresh()->instruction));$remove=$fields();unset($remove['image']);$remove+=['asset'=>$asset,'remove'=>true];$files=count($imageDisk->files);
 $save($remove,$record()['revision'],$uuid());check(!str_contains($translation->fresh()->instruction,'<img')&&count($imageDisk->files)===$files,'Reference removal preserves shared image bytes');
 $translation->refresh();$translation->instruction=str_repeat('x',199999);$translation->save();$files=count($imageDisk->files);
 $reject(fn()=>$save($fields(),$record()['revision'],$uuid()));
 check(count($imageDisk->files)===$files&&strlen($translation->fresh()->instruction)===199999,'Oversized native result rolls back and cleans the new upload');
 $translation->delete();$reject(fn()=>$save($fields(),$record()['revision'],$uuid()));check(count($imageDisk->files)===$files,'Missing exam translation cannot receive an image');
}
}finally{if(realpath($temporary)!==false&&str_starts_with(realpath($temporary),realpath(sys_get_temp_dir()).DIRECTORY_SEPARATOR.'t4l-exam-upload-'))(new Illuminate\Filesystem\Filesystem())->deleteDirectory($temporary);}
echo "Translated exam images: both owners, upload/read/replace/remove, exact retry, field scope, source preservation and rollback cleanup passed.\n";

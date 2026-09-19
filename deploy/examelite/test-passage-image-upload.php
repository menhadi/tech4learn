<?php
require __DIR__.'/test-staff-passage-media.php';
use App\Models\{Passage,PassageLang};
use App\Services\Tech4LearnQuestionMedia;
use Illuminate\Support\Facades\{DB,Storage};
use Illuminate\Http\Request;
$disk=new class {
 public array $files=[];public int $writes=0;
 public function disk($name){return $this;}
 public function put($path,$bytes){$this->writes++;$this->files[$path]=$bytes;return true;}
 public function delete($path){unset($this->files[$path]);return true;}
};
$app->instance('filesystem',$disk);Storage::clearResolvedInstance('filesystem');
$image=base64_encode($png);
foreach([false,true] as $centralOwner){
 $owner=$centralOwner?10:20;$languageId=$centralOwner?$centralLanguage->id:$lang->id;
 $model=Passage::findOrFail($centralOwner?$central['id']:$saved['id']);
 $row=PassageLang::where('passage_id',$model->id)->where('language_id',$languageId)->firstOrFail();
 $row->passage='<p>Saved passage <math><mi>x</mi></math></p>';$row->save();
 $snapshot=fn()=>$service->record('passages',$model->fresh());
 $save=function($fields,$revision,$request)use($centralOwner,$centralController,$controller,$workspace,$centralActor,$actor,$model){
  $r=Request::create('/','POST',['actor_id'=>$centralOwner?$centralActor:$actor,'fields'=>$fields,'revision'=>$revision,'request_id'=>$request]);
  $result=$centralOwner?$centralController->centralPassageImageWrite($r,(string)$model->id):$controller->passageImageWrite($r,$workspace,(string)$model->id);
  if($result['conflict']??false)abort(409);
  if(!$result['saved'])throw Illuminate\Validation\ValidationException::withMessages($result['errors']);
  return $result[$centralOwner?'record':'question'];
 };
 $before=$snapshot();$body=['language_id'=>$languageId,'image'=>$image];$key=$next();$writes=$disk->writes;
 $result=$save($body,$before['revision'],$key);$html=$row->fresh()->passage;
 check(str_contains($html,'/storage/images/upload/t4l/'.$owner.'/')&&str_contains($html,'<math>'),'Native passage image save preserves wording/formula and owner storage');
 check($save($body,$before['revision'],$key)===$result&&$disk->writes===$writes+1,'Native passage upload retry stores only once');
 foreach($before['fields']['passages'] as $otherLanguage=>$wording)if((int)$otherLanguage!==$languageId)
  check($result['fields']['passages'][$otherLanguage]===$wording,'Image write preserves every other saved passage language');
 $reject(fn()=>$save($body,$before['revision'],$next()),409);
 $reject(fn()=>$save($body+['remove'=>true],$before['revision'],$key),409);
 if($centralOwner)DB::table('organization_users')->where('organization_id',10)->where('user_id',$nativeActor)->update(['status'=>0]);
 else DB::table('tech4learn_workspaces')->where('id',$workspace)->update(['restrictions'=>'["questions"]']);
 $reject(fn()=>$save($body,$before['revision'],$key),403);
 if($centralOwner)DB::table('organization_users')->where('organization_id',10)->where('user_id',$nativeActor)->update(['status'=>1]);
 else DB::table('tech4learn_workspaces')->where('id',$workspace)->update(['restrictions'=>'[]']);
 $sources=app(Tech4LearnQuestionMedia::class)->sources($html);$asset=array_key_first($sources);$oldPath=substr($sources[$asset],strlen('/storage/'));
 check($disk->files[$oldPath]===$png,'Uploaded bytes are unchanged');
 foreach([['language_id'=>$centralOwner?$lang->id:$centralLanguage->id],['language_id'=>'3'],['image'=>'bad'],['field'=>'question'],['organization_id'=>99],['asset'=>str_repeat('0',64)]] as $patch){
  $reject(fn()=>$save(array_replace($body,$patch),$result['revision'],$next()),422);
  check($snapshot()===$result,'Invalid passage image action leaves wording untouched');
 }
 $replace=$save($body+['asset'=>$asset],$result['revision'],$next());
 check(isset($disk->files[$oldPath])&&$row->fresh()->passage!==$html,'Replacement keeps previous shared bytes');
 $newSources=app(Tech4LearnQuestionMedia::class)->sources($row->fresh()->passage);$newAsset=array_key_first($newSources);$count=count($disk->files);
 $remove=['language_id'=>$languageId,'asset'=>$newAsset,'remove'=>true];$key=$next();
 $removed=$save($remove,$replace['revision'],$key);
 check(!str_contains($row->fresh()->passage,'<img')&&count($disk->files)===$count,'Reference removal never deletes shared file bytes');
 check($save($remove,$replace['revision'],$key)===$removed,'Removal retry is idempotent');
 // A native update failure must roll back wording and discard only the new file.
 $events=PassageLang::getEventDispatcher();$fault=$events?clone $events:new Illuminate\Events\Dispatcher($app);
 $fault->listen('eloquent.saving: '.PassageLang::class,function(){throw new RuntimeException('Synthetic passage persistence failure');});
 PassageLang::setEventDispatcher($fault);
 try{$reject(fn()=>$save($body,$removed['revision'],$next()),422);}
 finally{if($events)PassageLang::setEventDispatcher($events);else PassageLang::unsetEventDispatcher();}
 check($snapshot()===$removed&&count($disk->files)===$count,'Native save failure rolls back and cleans the new upload');
 $row->refresh();$row->passage=str_repeat('x',199950);$row->save();$long=$snapshot();
 $reject(fn()=>$save($body,$long['revision'],$next()),422);
 check($snapshot()===$long&&count($disk->files)===$count,'Oversized saved wording also cleans the new upload');
}
foreach([
 ['api/tech4learn/v1/central/passages/1/image','Tech4LearnContentController@centralPassageImageWrite'],
 ['api/tech4learn/v1/authoring/'.$workspace.'/passages/1/image','Tech4LearnAuthoringController@passageImageWrite'],
] as [$path,$expected]){
 $route=$router->getRoutes()->match(Request::create('https://example.test/'.$path,'POST'));
 check(str_ends_with($route->getActionName(),$expected),'Installed passage image write route resolves');
}
echo "Native passage uploads: both owners, exact retry, scoped language, replacement/removal, preserved shared bytes and failure cleanup passed. Gateway/UI image controls remain pending.\n";

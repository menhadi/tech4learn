<?php
// Synthetic local images and SQLite only.
require __DIR__.'/test-central-package-authoring.php';
require __DIR__.'/Tech4LearnPackageImageUpload.php';
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Services\Tech4LearnQuestionMedia;
use App\Models\Package;
$oldPublic=public_path();$root=sys_get_temp_dir().'/t4l-central-package-'.bin2hex(random_bytes(10));
mkdir($root.'/uploads/package',0700,true);$app->usePublicPath($root);
$png='iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII=';
file_put_contents($root.'/uploads/package/original.png',base64_decode($png));
$centralPackage=Package::findOrFail($created['id']);$centralPackage->photo='uploads/package/original.png';$centralPackage->save();
$orgCopy=Package::create(['organization_id'=>20,'name'=>'Synthetic shared reference','package_type'=>'free','photo'=>$centralPackage->photo]);
$snapshot=fn()=>$service->record('packages',$centralPackage->fresh());
$send=function($fields,$revision,$key,$id=null)use($centralController,$centralActor,$centralPackage){
 $result=$centralController->centralPackageImageWrite(Request::create('/','POST',['actor_id'=>$centralActor,'fields'=>$fields,'revision'=>$revision,'request_id'=>$key]),(string)($id??$centralPackage->id));
 if($result['conflict']??false)abort(409);
 if(!$result['saved'])throw Illuminate\Validation\ValidationException::withMessages($result['errors']);
 return $result['record'];
};
$read=fn($asset)=>$centralController->centralPackageMedia(Request::create('/','GET'),(string)$centralPackage->id,$asset);
try {
 $before=$snapshot();$image=['image'=>$png,'asset'=>$before['photo_asset']];$key=$uuid();
 $saved=$send($image,$before['revision'],$key);$newPath=$centralPackage->fresh()->photo;
 check($newPath!=='uploads/package/original.png'&&is_file(public_path($newPath))&&is_file(public_path($orgCopy->photo)),'Central replacement retains the organisation-shared original');
 $count=count(glob($root.'/uploads/package/*'));
 check($send($image,$before['revision'],$key)===$saved&&count(glob($root.'/uploads/package/*'))===$count,'Central image retry stores once');
 check($read($saved['photo_asset'])['base64']===$png,'Central current image is readable as a confined raster');
 $deny(fn()=>$read($before['photo_asset']));
 $deny(fn()=>$centralController->centralPackageMedia(Request::create('/','GET'),(string)$orgCopy->id,hash('sha256',$orgCopy->photo)));
 $deny(fn()=>$centralController->centralPackageMedia(Request::create('/','GET',['path'=>'outside.png']),(string)$centralPackage->id,$saved['photo_asset']));
 foreach([['image'=>'invalid'],['photo'=>'/etc/passwd'],['field'=>'question'],['organization_id'=>20]] as $invalid)$deny(fn()=>$send(array_replace(['image'=>$png,'asset'=>$saved['photo_asset']],$invalid),$saved['revision'],$uuid()));
 $deny(fn()=>$send(['image'=>$png,'asset'=>$saved['photo_asset']],$saved['revision'],$uuid(),$orgCopy->id));
 $deny(fn()=>$send(['image'=>$png],$service->record('packages',$paid)['revision'],$uuid(),$paid->id));
 DB::table('users')->where('id',$nativeId)->update(['status'=>0]);$deny(fn()=>$send($image,$before['revision'],$key));DB::table('users')->where('id',$nativeId)->update(['status'=>1]);
 $originalDispatcher=Package::getEventDispatcher();$faultDispatcher=$originalDispatcher?clone $originalDispatcher:new Illuminate\Events\Dispatcher($app);
 $faultDispatcher->listen('eloquent.saving: '.Package::class,function(){throw new RuntimeException('Synthetic central image failure');});Package::setEventDispatcher($faultDispatcher);
 try{$send(['image'=>$png,'asset'=>$saved['photo_asset']],$saved['revision'],$uuid());throw new RuntimeException('Expected image rollback');}catch(RuntimeException $e){check($e->getMessage()==='Synthetic central image failure','Native save failure propagated');}finally{if($originalDispatcher)Package::setEventDispatcher($originalDispatcher);else Package::unsetEventDispatcher();}
 check($snapshot()===$saved&&count(glob($root.'/uploads/package/*'))===$count,'Failed central image save rolls back record and discards new file');
 DB::table('organizations')->where('id',10)->update(['status'=>'inactive']);$deny(fn()=>$read($saved['photo_asset']));DB::table('organizations')->where('id',10)->update(['status'=>'active']);
 $app->instance(Tech4LearnQuestionMedia::class,new class extends Tech4LearnQuestionMedia {
  public function readPackage(Package $package,int $owner,string $asset):array{$result=parent::readPackage($package,$owner,$asset);$package->photo=null;$package->save();return $result;}
 });
 $deny(fn()=>$read($saved['photo_asset']));$app->forgetInstance(Tech4LearnQuestionMedia::class);
 $centralPackage->refresh();$centralPackage->photo=$newPath;$centralPackage->save();$current=$snapshot();
 $remove=['remove'=>true,'asset'=>$current['photo_asset']];$removeKey=$uuid();$removed=$send($remove,$current['revision'],$removeKey);
 check($removed['photo_asset']===null&&count(glob($root.'/uploads/package/*'))===$count,'Removing central reference retains shared files');
 check($send($remove,$current['revision'],$removeKey)===$removed,'Central image removal retries once');
} finally {
 $app->forgetInstance(Tech4LearnQuestionMedia::class);$app->usePublicPath($oldPublic);
 foreach(glob($root.'/uploads/package/t4l-*') as $file)unlink($file);
 unlink($root.'/uploads/package/original.png');rmdir($root.'/uploads/package');rmdir($root.'/uploads');rmdir($root);
}
echo "Central package images: scoped preview, upload, rollback, replacement and removal passed.\n";

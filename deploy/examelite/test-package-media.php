<?php
// Native models and filesystem confinement, using only synthetic local records.
require __DIR__.'/test-exam-authoring.php';
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use App\Services\Tech4LearnQuestionMedia;

$oldPublic=public_path();
$root=sys_get_temp_dir().'/t4l-package-media-'.bin2hex(random_bytes(10));
mkdir($root.'/uploads/package',0700,true);
$app->usePublicPath($root);
$originalRestrictions=DB::table('tech4learn_workspaces')->where('id',$workspace)->value('restrictions');
DB::table('tech4learn_workspaces')->where('id',$workspace)->update(['restrictions'=>'[]']);
$png=base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII=');
file_put_contents($root.'/uploads/package/fixture.png',$png);
file_put_contents($root.'/outside.png',$png);
file_put_contents($root.'/uploads/package/invalid.png','not an image');
$package=App\Models\Package::create(['organization_id'=>20,'name'=>'Media fixture','photo'=>'uploads/package/fixture.png']);
$asset=hash('sha256',$package->photo);
$packageSnapshot=$service->record('packages',$package);
check($packageSnapshot['photo_asset']===$asset&&!array_key_exists('photo',$packageSnapshot['fields']),'Package snapshot exposes an opaque image reference, not the storage path');
$request=Request::create('/','GET');
$read=fn()=>$controller->packageMedia($request,$workspace,(string)$package->id,$asset);
$deny=function(callable $call){
 try{$call();throw new RuntimeException('Expected package media denial');}
 catch(Symfony\Component\HttpKernel\Exception\HttpException|Illuminate\Database\Eloquent\ModelNotFoundException $e){}
};
try {
 $result=$read();
 check($result['package_id']===$package->id&&$result['asset']===$asset&&$result['mime']==='image/png'&&base64_decode($result['base64'],true)===$png,'Owned native package raster is returned without a storage path');
 $deny(fn()=>$controller->packageMedia($request,$workspace,(string)$package->id,str_repeat('0',64)));
 $request->query->set('path','outside.png');$deny($read);$request->query->remove('path');
 $package->organization_id=30;$package->save();$deny($read);$package->organization_id=20;$package->save();
 DB::table('tech4learn_workspaces')->where('id',$workspace)->update(['restrictions'=>'["subjects"]']);$deny($read);
 DB::table('tech4learn_workspaces')->where('id',$workspace)->update(['restrictions'=>'[]']);
 DB::table('organizations')->where('id',20)->update(['status'=>'inactive']);$deny($read);DB::table('organizations')->where('id',20)->update(['status'=>'active']);
 foreach(['uploads/package/../../outside.png','https://foreign.example/fixture.png','uploads/package/invalid.png','uploads/package/missing.png',''] as $bad){
  $package->photo=$bad;$package->save();
  $deny(fn()=>$controller->packageMedia($request,$workspace,(string)$package->id,hash('sha256',$bad)));
 }
 $package->photo='uploads/package/fixture.png';$package->save();
 $app->instance(Tech4LearnQuestionMedia::class,new class extends Tech4LearnQuestionMedia {
  public function readPackage(App\Models\Package $package,int $owner,string $key):array {
   $result=parent::readPackage($package,$owner,$key);
   $package->photo=null;$package->save();return $result;
  }
 });
 $deny($read);
 check(true,'A photo removed during the read is not released');
} finally {
 $app->forgetInstance(Tech4LearnQuestionMedia::class);
 $app->usePublicPath($oldPublic);
 DB::table('tech4learn_workspaces')->where('id',$workspace)->update(['restrictions'=>$originalRestrictions]);
 // Only the exact synthetic files and empty directories created by this test.
 unlink($root.'/uploads/package/fixture.png');unlink($root.'/uploads/package/invalid.png');unlink($root.'/outside.png');
 rmdir($root.'/uploads/package');rmdir($root.'/uploads');rmdir($root);
}
echo "Native package media: current reference, ownership, restrictions and confined raster reads passed.\n";

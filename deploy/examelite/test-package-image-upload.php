<?php
require __DIR__.'/test-exam-authoring.php';
require __DIR__.'/Tech4LearnPackageImageUpload.php';
use Illuminate\Support\Facades\DB;
use App\Services\Tech4LearnQuestionMedia;

$oldPublic=public_path();$root=sys_get_temp_dir().'/t4l-package-upload-'.bin2hex(random_bytes(10));
mkdir($root.'/uploads/package',0700,true);$app->usePublicPath($root);
$originalRestrictions=DB::table('tech4learn_workspaces')->where('id',$workspace)->value('restrictions');
DB::table('tech4learn_workspaces')->where('id',$workspace)->update(['restrictions'=>'[]']);
$encoded='iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII=';
file_put_contents($root.'/uploads/package/original.png',base64_decode($encoded));
$package=App\Models\Package::create(['organization_id'=>20,'name'=>'Upload fixture','package_type'=>'free','photo'=>'uploads/package/original.png']);
$snapshot=fn()=>$service->record('packages',$package->fresh());
$save=fn($fields,$revision,$key)=>$service->save($workspace,20,$actor,$package->id,$fields,$revision,$key,'packages','set-image');
$deny=function(callable $call){try{$call();throw new RuntimeException('Expected image rejection');}catch(Symfony\Component\HttpKernel\Exception\HttpException|Illuminate\Validation\ValidationException|Illuminate\Database\Eloquent\ModelNotFoundException $e){}};
try {
 $before=$snapshot();$input=['image'=>$encoded,'asset'=>$before['photo_asset']];
 $deny(fn()=>$save($input+['photo'=>'../outside.png'],$before['revision'],'override-photo-path'));
 $saved=$save($input,$before['revision'],'package-photo');
 $path=$package->fresh()->photo;
 check($path!=='uploads/package/original.png'&&is_file(public_path($path))&&is_file($root.'/uploads/package/original.png'),'Replacement stores a new raster and retains the shared original');
 check($package->fresh()->name==='Upload fixture'&&$saved['photo_asset']===hash('sha256',$path),'Native model photo update preserves package details');
 $count=count(glob($root.'/uploads/package/*'));
 check($save($input,$before['revision'],'package-photo')===$saved&&count(glob($root.'/uploads/package/*'))===$count,'Lost response replay writes no second file');
 check(app(Tech4LearnQuestionMedia::class)->readPackage($package->fresh(),20,$saved['photo_asset'])['base64']===$encoded,'Uploaded photo is readable through the confined native preview');
 $deny(fn()=>$save($input,$saved['revision'],'wrong-photo-hash'));
 $deny(fn()=>$save(['image'=>'not base64','asset'=>$saved['photo_asset']],$saved['revision'],'invalid-photo'));
 $GLOBALS['t4lTestPlatformAdmin']=true;
 try{$deny(fn()=>$save(['image'=>$encoded,'asset'=>$saved['photo_asset']],$saved['revision'],'wrong-native-context'));}
 finally{$GLOBALS['t4lTestPlatformAdmin']=false;}
 check($snapshot()===$saved&&count(glob($root.'/uploads/package/*'))===$count,'Failed native context rolls back and cleans only the newly written file');
 $removed=$save(['remove'=>true,'asset'=>$saved['photo_asset']],$saved['revision'],'remove-package-photo');
 check($removed['photo_asset']===null&&is_file(public_path($path)),'Removal clears only the reference and preserves a potentially shared file');
 check($save(['remove'=>true,'asset'=>$saved['photo_asset']],$saved['revision'],'remove-package-photo')===$removed,'Removal replay is stable');
 $deny(fn()=>$save(['remove'=>true,'asset'=>$removed['photo_asset']],$removed['revision'],'remove-missing-photo'));
 $created=$save(['image'=>$encoded],$removed['revision'],'first-package-photo');
 check(filled($created['photo_asset']),'A package without a photo accepts its first upload');
 DB::table('organization_users')->where('organization_id',20)->where('user_id',1)->update(['status'=>0]);
 $deny(fn()=>$save(['image'=>$encoded],$removed['revision'],'first-package-photo'));
 DB::table('organization_users')->where('organization_id',20)->where('user_id',1)->update(['status'=>1]);
 check($snapshot()===$created,'Revoked retry leaves the saved photo unchanged');
} finally {
 $GLOBALS['t4lTestPlatformAdmin']=false;
 DB::table('organization_users')->where('organization_id',20)->where('user_id',1)->update(['status'=>1]);
 DB::table('tech4learn_workspaces')->where('id',$workspace)->update(['restrictions'=>$originalRestrictions]);
 $app->usePublicPath($oldPublic);
 // This unique temporary directory contains only this test's original and uploads.
 foreach(glob($root.'/uploads/package/*') as $file)unlink($file);
 rmdir($root.'/uploads/package');rmdir($root.'/uploads');rmdir($root);
}
echo "Native package photo writes: replacement, preservation, removal, replay and failure cleanup passed.\n";

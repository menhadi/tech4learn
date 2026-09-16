<?php
namespace App\Services;

use App\Models\Package;

/** Preserve shared old files: the native web form deletes its previous photo. */
final class Tech4LearnPackageImageUpload
{
 public function apply(Package $package,array $input,?string &$stored):array {
  abort_unless($package->package_type==='free',422,'Paid package management is not enabled in this workspace.');
  $current=trim((string)$package->photo);$asset=$input['asset']??null;
  $remove=($input['remove']??false)===true;
  abort_unless(!array_key_exists('remove',$input)||$remove,422);
  abort_unless($asset===null||(is_string($asset)&&preg_match('/^[a-f0-9]{64}$/D',$asset)),422);
  abort_unless($current===''?$asset===null:($asset!==null&&hash_equals(hash('sha256',$current),$asset)),409,'The package image changed. Reload before replacing it.');
  if($remove){abort_unless($current!==''&&!array_key_exists('image',$input),422);return ['photo'=>null];}
  [$bytes,$extension]=Tech4LearnQuestionImageUpload::decode($input['image']??null);
  $directory=public_path('uploads/package');
  if(!is_dir($directory))abort_unless(mkdir($directory,0755,true)||is_dir($directory),503);
  $stored='uploads/package/t4l-'.bin2hex(random_bytes(20)).'.'.$extension;
  abort_unless(file_put_contents(public_path($stored),$bytes,LOCK_EX)===strlen($bytes),503);
  return ['photo'=>$stored];
 }
 public function discard(string $path):void {
  abort_unless(preg_match('#^uploads/package/t4l-[a-f0-9]{40}\.(png|jpg|webp)$#D',$path),422);
  if(is_file(public_path($path)))unlink(public_path($path));
 }
}

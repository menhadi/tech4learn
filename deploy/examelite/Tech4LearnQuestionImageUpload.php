<?php
namespace App\Services;

use App\Models\Question;
use Illuminate\Support\Facades\Storage;

/** A bounded image write used only after authoring scope and revision checks. */
final class Tech4LearnQuestionImageUpload
{
 public const MAX_BYTES=524288;
 public function apply(Question $question,array $input,?string &$stored):array {
  $field=$input['field']??null;$encoded=$input['image']??null;$replace=$input['asset']??null;
  abort_unless(is_string($field)&&in_array($field,Tech4LearnQuestionMedia::AUTHORING_FIELDS,true),422);
  abort_unless(is_string($encoded)&&strlen($encoded)<=699052,422);
  $bytes=base64_decode($encoded,true);
  abort_unless(is_string($bytes)&&strlen($bytes)>0&&strlen($bytes)<=self::MAX_BYTES&&base64_encode($bytes)===$encoded,422);
  $info=@getimagesizefromstring($bytes);$extensions=['image/png'=>'png','image/jpeg'=>'jpg','image/webp'=>'webp'];
  abort_unless(isset($extensions[$info['mime']??''])&&($info[0]??0)>0&&($info[1]??0)>0&&$info[0]*$info[1]<=20000000,422);
  abort_unless($replace===null||(is_string($replace)&&preg_match('/^[a-f0-9]{64}$/D',$replace)),422);
  $html=(string)($question->$field??'');abort_unless(strlen($html)<=2000000,422);
  $document=new \DOMDocument();$previous=libxml_use_internal_errors(true);
  try{$document->loadHTML('<?xml encoding="UTF-8"><html><body>'.$html.'</body></html>',LIBXML_NONET|LIBXML_NOERROR|LIBXML_NOWARNING);}
  finally{libxml_clear_errors();libxml_use_internal_errors($previous);}
  $matches=[];foreach($document->getElementsByTagName('img') as $image)if(hash('sha256',trim($image->getAttribute('src')))===$replace)$matches[]=$image;
  abort_unless($replace!==null?count($matches)===1:$document->getElementsByTagName('img')->length<50,422);
  $body=$document->getElementsByTagName('body')->item(0);abort_unless($body,422);
  $path='images/upload/t4l/'.(int)$question->organization_id.'/'.bin2hex(random_bytes(20)).'.'.$extensions[$info['mime']];
  $image=$document->createElement('img');$image->setAttribute('src','/storage/'.$path);$image->setAttribute('alt','Question image');
  if($replace!==null)$matches[0]->parentNode->replaceChild($image,$matches[0]);else $body->appendChild($image);
  $updated='';foreach($body->childNodes as $node)$updated.=$document->saveHTML($node);
  abort_unless(strlen($updated)<=2000000,422);
  // Assign before writing so a failed write/transaction also removes a partial file.
  $stored=$path;abort_unless(Storage::disk('public')->put($path,$bytes),503);
  return [$field=>$updated];
 }
 public function discard(string $path):void {
  abort_unless(preg_match('#^images/upload/t4l/[1-9][0-9]*/[a-f0-9]{40}\.(png|jpg|webp)$#D',$path),422);
  Storage::disk('public')->delete($path);
 }
}

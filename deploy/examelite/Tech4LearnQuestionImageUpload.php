<?php
namespace App\Services;

use App\Models\{Question,QuestionLang,Exam,ExamLanguageTranslation};
use Illuminate\Support\Facades\Storage;

/** A bounded image write used only after authoring scope and revision checks. */
final class Tech4LearnQuestionImageUpload
{
 public const MAX_BYTES=524288;
 public function apply(Question $question,array $input,?string &$stored):array {
  return $this->applyField((int)$question->organization_id,$question->getAttributes(),$input,$stored);
 }
 /** Caller must hold the source/translation locks and authorisation checks. */
 public function applyTranslation(Question $source,QuestionLang $target,array $input,?string &$stored):array {
  abort_unless($source->exists&&$target->exists&&(int)$source->id===(int)$target->question_id,422);
  abort_unless(in_array($input['field']??null,Tech4LearnTranslationEdits::FIELDS,true),422);
  return $this->applyField((int)$source->organization_id,$target->getAttributes(),$input,$stored);
 }
 public function applyExamTranslation(Exam $source,ExamLanguageTranslation $target,array $input,?string &$stored):array {
  abort_unless($source->exists&&$target->exists&&(int)$source->id===(int)$target->exam_id,422);
  return $this->applyField((int)$source->organization_id,$target->getAttributes(),$input,$stored,['instruction','syllabus']);
 }
 private function applyField(int $owner,array $wording,array $input,?string &$stored,array $allowed=Tech4LearnQuestionMedia::AUTHORING_FIELDS):array {
  abort_unless($owner>0,422);
  $field=$input['field']??null;$encoded=$input['image']??null;$replace=$input['asset']??null;
  $remove=($input['remove']??false)===true;
  abort_unless(!array_key_exists('remove',$input)||$input['remove']===true,422);
  abort_unless(is_string($field)&&in_array($field,$allowed,true),422);
  if($remove)abort_unless(!array_key_exists('image',$input)&&$replace!==null,422);
  else {
  [$bytes,$extension]=self::decode($encoded);
  }
  abort_unless($replace===null||(is_string($replace)&&preg_match('/^[a-f0-9]{64}$/D',$replace)),422);
  $html=(string)($wording[$field]??'');abort_unless(strlen($html)<=2000000,422);
  $document=new \DOMDocument();$previous=libxml_use_internal_errors(true);
  try{$document->loadHTML('<?xml encoding="UTF-8"><html><body>'.$html.'</body></html>',LIBXML_NONET|LIBXML_NOERROR|LIBXML_NOWARNING);}
  finally{libxml_clear_errors();libxml_use_internal_errors($previous);}
  $matches=[];foreach($document->getElementsByTagName('img') as $image)if(hash('sha256',trim($image->getAttribute('src')))===$replace)$matches[]=$image;
  abort_unless($replace!==null?count($matches)===1:$document->getElementsByTagName('img')->length<50,422);
  $body=$document->getElementsByTagName('body')->item(0);abort_unless($body,422);
  if($remove){
   $matches[0]->parentNode->removeChild($matches[0]);
   $updated='';foreach($body->childNodes as $node)$updated.=$document->saveHTML($node);
   abort_unless(strlen($updated)<=2000000,422);
   return [$field=>$updated];
  }
  $path='images/upload/t4l/'.$owner.'/'.bin2hex(random_bytes(20)).'.'.$extension;
  $image=$document->createElement('img');$image->setAttribute('src','/storage/'.$path);$image->setAttribute('alt','Question image');
  if($replace!==null)$matches[0]->parentNode->replaceChild($image,$matches[0]);else $body->appendChild($image);
  $updated='';foreach($body->childNodes as $node)$updated.=$document->saveHTML($node);
  abort_unless(strlen($updated)<=2000000,422);
  // Assign before writing so a failed write/transaction also removes a partial file.
  $stored=$path;abort_unless(Storage::disk('public')->put($path,$bytes),503);
  return [$field=>$updated];
 }
 public static function decode(mixed $encoded):array {
  abort_unless(is_string($encoded)&&strlen($encoded)<=699052,422);
  $bytes=base64_decode($encoded,true);
  abort_unless(is_string($bytes)&&strlen($bytes)>0&&strlen($bytes)<=self::MAX_BYTES&&base64_encode($bytes)===$encoded,422);
  $info=@getimagesizefromstring($bytes);$extensions=['image/png'=>'png','image/jpeg'=>'jpg','image/webp'=>'webp'];
  abort_unless(isset($extensions[$info['mime']??''])&&($info[0]??0)>0&&($info[1]??0)>0&&$info[0]*$info[1]<=20000000,422);
  return [$bytes,$extensions[$info['mime']]];
 }
 public function discard(string $path):void {
  abort_unless(preg_match('#^images/upload/t4l/[1-9][0-9]*/[a-f0-9]{40}\.(png|jpg|webp)$#D',$path),422);
  Storage::disk('public')->delete($path);
 }
}

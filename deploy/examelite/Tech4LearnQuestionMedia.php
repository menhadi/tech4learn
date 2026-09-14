<?php
namespace App\Services;

use App\Models\{Question,ExamResult};
use Illuminate\Support\Facades\Storage;

/** Only raster images actually referenced by the granted paper's question. */
class Tech4LearnQuestionMedia
{
 public const MAX_BYTES=10485760;
 public function sources(string $html):array {
  abort_unless(strlen($html)<=2000000,422);
  $document=new \DOMDocument();$before=libxml_use_internal_errors(true);
  try{$document->loadHTML('<?xml encoding="UTF-8"><div>'.$html.'</div>',LIBXML_NONET|LIBXML_NOERROR|LIBXML_NOWARNING);}
  finally{libxml_clear_errors();libxml_use_internal_errors($before);}
  $sources=[];
  foreach($document->getElementsByTagName('img') as $image){$source=trim($image->getAttribute('src'));if($source!=='')$sources[hash('sha256',$source)]=$source;}
  abort_unless(count($sources)<=50,422);return $sources;
 }
 public function rewrite(string $html,bool $protect=false):string {
  if(!str_contains(strtolower($html),'<img'))return $html;
  abort_unless(strlen($html)<=2000000,422);
  $document=new \DOMDocument();$before=libxml_use_internal_errors(true);
  try{$document->loadHTML('<?xml encoding="UTF-8"><div>'.$html.'</div>',LIBXML_NONET|LIBXML_NOERROR|LIBXML_NOWARNING);}
  finally{libxml_clear_errors();libxml_use_internal_errors($before);}
  abort_unless($document->getElementsByTagName('img')->length<=50,422);
  foreach($document->getElementsByTagName('img') as $image){
   $source=trim($image->getAttribute('src'));abort_unless($source!=='',422);
   while($image->attributes->length)$image->removeAttributeNode($image->attributes->item(0));
   $image->setAttribute('src',($protect?'/t4l-question-media/':'t4l-media:').hash('sha256',$source));$image->setAttribute('alt','Question image');
  }
  $result='';foreach($document->getElementsByTagName('body')->item(0)->childNodes as $node)$result.=$document->saveHTML($node);
  return $result;
 }
 public function restore(string $html):string {
  return preg_replace('/(<img\b[^>]*\bsrc=")\/t4l-question-media\/([a-f0-9]{64})(")/i','$1t4l-media:$2$3',$html);
 }
 public function read(Question $question,ExamResult $attempt,string $key):array {
  abort_unless(!$attempt->end_time,403);
  return $this->readQuestion($question,$attempt,$key);
 }
 /** Caller has authenticated the mapped staff and pending result before reaching here. */
 public function readReview(Question $question,ExamResult $attempt,string $key):array {
  abort_unless($attempt->end_time,403);
  return $this->readQuestion($question,$attempt,$key);
 }
 private function readQuestion(Question $question,ExamResult $attempt,string $key):array {
  abort_unless(preg_match('/^[a-f0-9]{64}$/D',$key),422);
  abort_unless((int)$question->organization_id===(int)$attempt->organization_id,403);
  $translation=$question->langs()->where('language_id',(int)$attempt->language_id)->first();$sources=[];
  foreach(['question','option1','option2','option3','option4','option5','option6','hint'] as $field)$sources+=$this->sources((string)($translation?->$field??$question->$field??''));
  if($passage=$question->passage){
   abort_unless((int)$passage->organization_id===(int)$question->organization_id,403);
   $lang=$passage->langs()->where('language_id',(int)$question->language_id)->first()??$passage->langs()->first();
   $sources+=$this->sources((string)($lang?->passage??''));
  }
  abort_unless(isset($sources[$key]),404);
  $bytes=$this->bytes($sources[$key]);
  abort_unless(strlen($bytes)>0&&strlen($bytes)<=self::MAX_BYTES,422);
  $details=@getimagesizefromstring($bytes);$mime=$details['mime']??'';
  abort_unless(in_array($mime,['image/png','image/jpeg','image/gif','image/webp','image/avif'],true)&&($details[0]??0)*($details[1]??0)<=40000000,422);
  return ['mime'=>$mime,'base64'=>base64_encode($bytes),'asset'=>$key,'question_id'=>(int)$question->id,'attempt_id'=>(int)$attempt->id];
 }
 protected function bytes(string $source):string {
  if(preg_match('#^data:image/(?:png|jpeg|gif|webp|avif);base64,([a-zA-Z0-9+/=\r\n]+)$#D',$source,$match)){
   abort_unless(strlen($match[1])<=self::MAX_BYTES*1.34,422);$body=base64_decode($match[1],true);abort_unless(is_string($body),422);return $body;
  }
  $parts=parse_url($source);abort_unless(is_array($parts)&&!isset($parts['user'])&&!isset($parts['pass'])&&!isset($parts['port']),422);
  $host=strtolower($parts['host']??'');
  if($host==='cdn.examelite.com'){
   $path=app(LegacyCdnImage::class)->localPath($source);abort_unless(is_string($path),404);
   return $this->file($path,Storage::disk('public')->path('question-images/cdn-cache'));
  }
  abort_unless($host===''||$host==='examelite.com'||preg_match('/^[a-z0-9-]+\.examelite\.com$/D',$host),422);
  abort_unless(!isset($parts['scheme'])||in_array(strtolower($parts['scheme']),['http','https'],true),422);
  $path=rawurldecode($parts['path']??'');
  abort_unless(!str_contains($path,'\\')&&!preg_match('/[\x00-\x1f]/',$path)&&!in_array('..',explode('/',$path),true)&&!in_array('.',explode('/',$path),true),422);
  $relative=preg_replace('#^/?storage/#','',ltrim($path,'/'));
  abort_unless(str_starts_with($relative,'question-images/')||str_starts_with($relative,'images/upload/'),422);
  $root=str_starts_with($relative,'question-images/')?'question-images':'images/upload';
  return $this->file(Storage::disk('public')->path($relative),Storage::disk('public')->path($root));
 }
 private function file(string $path,string $root):string {
  $resolved=realpath($path);$base=realpath($root);
  abort_unless($resolved&&$base&&str_starts_with($resolved,$base.DIRECTORY_SEPARATOR)&&is_file($resolved),404);
  abort_unless(filesize($resolved)<=self::MAX_BYTES,422);
  $bytes=file_get_contents($resolved,false,null,0,self::MAX_BYTES+1);abort_unless(is_string($bytes)&&strlen($bytes)<=self::MAX_BYTES,422);return $bytes;
 }
}

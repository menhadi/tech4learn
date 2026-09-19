<?php
namespace App\Services;

use App\Http\Controllers\SubjectiveUploadController;
use Illuminate\Http\{Request,UploadedFile,JsonResponse};
use Illuminate\Support\Facades\{Storage,DB};

/** Internal transport only: caller must authorise the mapped attempt and revision. */
final class Tech4LearnPrivateAnswerUpload extends UploadedFile
{
 private ?string $createdName=null;
 private const NAME='/^[0-9]+_[0-9]+_[0-9]+_[0-9]+_[a-f0-9]{40}\.(jpg|jpeg|png|pdf|doc|docx|txt)$/D';

 private static function disk():\Illuminate\Filesystem\FilesystemAdapter {
  // Never use a configurable public disk or a web-accessible storage subdirectory.
  return Storage::build(['driver'=>'local','root'=>storage_path('app/t4l-private-answers'),'throw'=>true]);
 }

 public function storeAs($path,$name=null,$options=[]) {
  abort_unless($path==='student_answers'&&$options==='public'&&is_string($name)&&preg_match(self::NAME,$name),422);
  abort_unless($this->createdName===null,409,'This upload was already stored.');
  $this->createdName=$name;
  $stream=fopen($this->getRealPath(),'rb');
  if($stream===false)throw new \RuntimeException('Cannot read answer upload.');
  try{if(!self::disk()->put($name,$stream))throw new \RuntimeException('Cannot save private answer upload.');}
  finally{fclose($stream);}
  return 't4l-private-answers/'.$name;
 }

 private function discard():void {
  if($this->createdName!==null&&!self::disk()->delete($this->createdName))throw new \RuntimeException('Private answer upload needs cleanup.');
 }

 /** Keep native validation, attempt locks and persistence; replace only file transport. */
 public static function run(Request $request,?callable $before=null,?callable $complete=null):JsonResponse {
  $original=$request->file('answer_file');abort_unless($original instanceof UploadedFile&&!($original instanceof self),422);
  $upload=static::createFromBase($original,$original->isValid());
  // A fresh request avoids Laravel's cached convertedFiles retaining the public
  // transport after the original file has already been read during validation.
  $nativeRequest=Request::createFrom($request);
  $nativeRequest->files->set('answer_file',$upload);
  try {
   $response=DB::transaction(function()use($nativeRequest,$before,$complete){
    $early=$before? $before($nativeRequest):null;
    if($early!==null){abort_unless($early instanceof JsonResponse,503);return $early;}
    $native=app(SubjectiveUploadController::class)->upload($nativeRequest);
    abort_unless($native instanceof JsonResponse,503);
    if(($native->getData(true)['success']??false)===true&&$complete){$native=$complete($native);abort_unless($native instanceof JsonResponse,503);}
    return $native;
   });
   if(!$response instanceof JsonResponse||($response->getData(true)['success']??false)!==true){
    $upload->discard();
   }
   abort_unless($response instanceof JsonResponse,503);
   return $response;
  } catch(\Throwable $error) {
   try{$upload->discard();}catch(\Throwable $cleanup){try{report($cleanup);}catch(\Throwable $loggingFailure){}}
   throw $error;
  }
 }
}

<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use App\Services\Tech4LearnAnswerAttachments;
use App\Services\Tech4LearnAnswerUploadCoordinator;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

/** Server-to-server only. Tech4Learn must authenticate the learner grant or staff permission. */
class Tech4LearnAttachmentController extends Tech4LearnPlatformController
{
 public function attachment(Request $r,string $org,string $action){
  $central=$this->configuration($r)['_platform']['organization_id'];
  $this->uuid($org);
  abort_unless(in_array($action,['upload','read','review'],true),404);
  abort_unless(strlen($r->getContent())<=($action==='upload'?14000000:3000),422);
  try{$input=json_decode($r->getContent(),true,16,JSON_THROW_ON_ERROR);}catch(\JsonException){abort(422);}
  abort_unless(is_array($input),422);
  $keys=['learner_id','exam_id','attempt_id','question_id'];
  $keys=array_merge($keys,$action==='upload'?['request_id','revision','base64']:['asset']);
  if($action==='review')$keys[]='actor_id';
  abort_unless(array_diff(array_keys($input),$keys)===[]&&is_string($input['learner_id']??null),422);
  $this->uuid($input['learner_id']);
  if($action==='review'){abort_unless(is_string($input['actor_id']??null),422);$this->uuid($input['actor_id']);}
  foreach(['exam_id','attempt_id','question_id'] as $key)abort_unless(is_int($input[$key]??null)&&$input[$key]>0,422);
  $temp=null;
  try {
   if($action==='upload'){
    abort_unless(is_string($input['request_id']??null)&&is_string($input['revision']??null)&&is_string($input['base64']??null),422);
    $this->uuid($input['request_id']);
    abort_unless(preg_match('/^[a-f0-9]{64}$/D',$input['revision'])&&strlen($input['base64'])<=13981016,422);
    $bytes=base64_decode($input['base64'],true);
    abort_unless(is_string($bytes)&&strlen($bytes)>0&&strlen($bytes)<=10485760&&base64_encode($bytes)===$input['base64'],422);
    $temp=tempnam(sys_get_temp_dir(),'t4l-answer-');
    abort_unless(is_string($temp),503);
    abort_unless(chmod($temp,0600)&&file_put_contents($temp,$bytes)===strlen($bytes),503);
    $data=app(Tech4LearnAnswerUploadCoordinator::class)->save($org,$central,$input['learner_id'],$input['attempt_id'],$input['question_id'],array_intersect_key($input,array_flip(['exam_id','request_id','revision'])),new UploadedFile($temp,'answer','application/octet-stream',null,true));
   }else{
    abort_unless(is_string($input['asset']??null),422);
    $data=$action==='review'
     ?app(\App\Services\Tech4LearnResultMarking::class)->attachment($org,$central,$input['actor_id'],$input['learner_id'],$input['attempt_id'],$input['question_id'],$input['asset'],$input['exam_id'])
     :app(Tech4LearnAnswerAttachments::class)->read($org,$central,$input['learner_id'],$input['attempt_id'],$input['question_id'],$input['asset'],$input['exam_id']);
   }
   return $this->reply($central,['data'=>$data]);
  }catch(HttpExceptionInterface $e){
   $status=$e->getStatusCode();if(!in_array($status,[403,404,409,422,429,503],true))throw $e;
   return $this->reply($central,['error'=>['status'=>$status]]);
  }catch(\Illuminate\Database\Eloquent\ModelNotFoundException){
   return $this->reply($central,['error'=>['status'=>404]]);
  }finally{
   if(is_string($temp)&&is_file($temp))unlink($temp);
  }
 }
}

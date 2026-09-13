<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\Tech4LearnStudentAttempts;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class Tech4LearnStudentController extends Tech4LearnPlatformController
{
 public function attempt(Request $r,string $org,string $action){
  $central=$this->configuration($r)['_platform']['organization_id'];
  $this->uuid($org);
  // Preserve blank answers and intentional whitespace across Laravel's global
  // TrimStrings/ConvertEmptyStringsToNull middleware.
  abort_unless(strlen($r->getContent())<=($action==='proctor'?360000:30000),422);
  try{$input=json_decode($r->getContent(),true,32,JSON_THROW_ON_ERROR);}catch(\JsonException $e){abort(422);}
  abort_unless(is_array($input),422);
  abort_unless(array_diff(array_keys($input),['learner_id','name','exam_id','fields'])===[],422);
  abort_unless(is_string($input['learner_id']??null)&&is_string($input['name']??null)&&is_int($input['exam_id']??null)&&is_array($input['fields']??null),422);
  $this->uuid($input['learner_id']);
  abort_unless(strlen(json_encode($input,JSON_THROW_ON_ERROR))<=($action==='proctor'?360000:30000),422);
  try {
   $result=app(Tech4LearnStudentAttempts::class)->run($org,$central,$input['learner_id'],$input['name'],$input['exam_id'],$action,$input['fields']);
   return $this->reply($central,['data'=>$result]);
  }catch(HttpExceptionInterface $e){
   // Public error codes only; never forward engine traces, SQL or credentials.
   $status=$e->getStatusCode();
   if(!in_array($status,[403,404,409,422,429],true))throw $e;
   $code=match($e->getMessage()){
    'This paper needs media or formula display that is not available yet.'=>'display_unavailable',
    'This exam requires delivery controls that are not yet available in Tech4Learn.'=>'controls_unavailable',
    'The paper duration changed after this attempt started. Ask exam staff to restore its duration before resuming.'=>'duration_changed',
    'No attempts remain for this exam.'=>'attempts_exhausted',
    'This question is outside the current timed section.'=>'section_ended',
    'This timed attempt has no captured section schedule.'=>'schedule_missing',
    'The paper timer mode changed after this attempt started.'=>'timer_changed',
    'Capture interval has not elapsed.'=>'capture_interval',
    'Capture limit reached.'=>'capture_limit',
    default=>null,
   };
   return $this->reply($central,['error'=>['status'=>$status,'code'=>$code]]);
  }catch(\Illuminate\Database\Eloquent\ModelNotFoundException $e){
   return $this->reply($central,['error'=>['status'=>404]]);
  }
 }
}

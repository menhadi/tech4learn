<?php
namespace App\Services;

use App\Models\{Exam,ExamResult};
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/** Captures native group allocations once; caller holds the attempt/workspace lock. */
final class Tech4LearnAttemptClock
{
 public function initialise(string $workspace,Exam $exam,ExamResult $attempt,array $view,bool $isNew):void {
  $row=$this->row($workspace,$attempt);
  if($row)return;
  $mode=app(ExamGroupingService::class)->mode($exam);
  if(!$isNew){abort_unless($mode==='none',409,'This timed attempt has no captured section schedule.');return;}
  $groups=[];
  if($mode!=='none'){
   foreach($view['exam']->questions as $question){
    $key=(string)$question->exam_group_key;
    $stat=$view['examStats'][$question->id]??null;
    abort_unless($stat&&(int)$stat->exam_result_id===(int)$attempt->id,403);
    $seconds=(int)$stat->subject_time;
    abort_unless($seconds>=0&&$seconds<=604800,422,'Invalid native section duration.');
    if(!isset($groups[$key]))$groups[$key]=['key'=>$key,'label'=>(string)($question->exam_group_name??'Section'),'seconds'=>$seconds,'questions'=>[]];
    abort_unless($groups[$key]['seconds']===$seconds,409,'Native section durations are inconsistent.');
    $groups[$key]['questions'][]=(int)$question->id;
   }
   abort_unless(count($groups)>0&&count($groups)<=500&&array_sum(array_column($groups,'seconds'))>0,422,'This paper has no usable section time.');
  }
  DB::table('tech4learn_attempt_clocks')->insert(['attempt_id'=>$attempt->id,'workspace_id'=>$workspace,'organization_id'=>$attempt->organization_id,'student_id'=>$attempt->student_id,'exam_id'=>$attempt->exam_id,'mode'=>$mode,'groups'=>json_encode(array_values($groups),JSON_THROW_ON_ERROR),'created_at'=>now()]);
 }
 public function state(string $workspace,Exam $exam,ExamResult $attempt):?array {
  $row=$this->row($workspace,$attempt);$mode=app(ExamGroupingService::class)->mode($exam);
  if(!$row){abort_unless($mode==='none',409,'This timed attempt has no captured section schedule.');return null;}
  abort_unless($row->mode===$mode,409,'The paper timer mode changed after this attempt started.');
  if($mode==='none')return null;
  $groups=json_decode($row->groups,true,512,JSON_THROW_ON_ERROR);
  $elapsed=max(0,now()->timestamp-Carbon::parse($attempt->start_time)->timestamp);
  $end=0;$active=null;
  foreach($groups as $group){$end+=$group['seconds'];if($active===null&&$elapsed<$end)$active=$group+['remaining_seconds'=>$end-$elapsed];}
  return ['mode'=>$mode,'active'=>$active,'remaining_seconds'=>max(0,$end-$elapsed)];
 }
 public function assertQuestion(string $workspace,Exam $exam,ExamResult $attempt,int $question):void {
  $clock=$this->state($workspace,$exam,$attempt);
  if($clock!==null)abort_unless($clock['active']&&in_array($question,$clock['active']['questions'],true),409,'This question is outside the current timed section.');
 }
 private function row(string $workspace,ExamResult $attempt):?object {
  $row=DB::table('tech4learn_attempt_clocks')->where('attempt_id',$attempt->id)->where('workspace_id',$workspace)->first();
  if($row)abort_unless((int)$row->organization_id===(int)$attempt->organization_id&&(int)$row->student_id===(int)$attempt->student_id&&(int)$row->exam_id===(int)$attempt->exam_id,403);
  return $row;
 }
}

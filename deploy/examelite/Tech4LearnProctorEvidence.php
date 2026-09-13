<?php
namespace App\Services;

use App\Models\{Exam,ExamResult,Student};
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/** Private native evidence storage and scoped review; no public image paths. */
final class Tech4LearnProctorEvidence
{
 /** Server-credential callers must also enforce the staff permission in Tech4Learn. */
 private function reviewer(string $workspace,int $source,string $learner):array {
  foreach([$workspace,$learner] as $id)abort_unless(preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/D',$id),422);
  $w=DB::table('tech4learn_workspaces')->where('id',$workspace)->where('source_organization_id',$source)->first();abort_unless($w&&$w->organization_id,404);
  abort_unless(\App\Models\Organization::where('id',$w->organization_id)->where('status','active')->exists(),403);
  abort_unless(!in_array('results',json_decode($w->restrictions,true,512,JSON_THROW_ON_ERROR),true),403);
  $studentId=DB::table('tech4learn_workspace_users')->where('workspace_id',$workspace)->where('local_id',$learner)->where('kind','student')->value('external_id');
  $student=Student::where('organization_id',$w->organization_id)->findOrFail($studentId);
  return [(int)$w->organization_id,(int)$student->id];
 }
 private function visible(string $workspace,int $owner,int $student,int $attempt,int $exam) {
  return DB::table('tech4learn_proctor_evidence')->where('workspace_id',$workspace)->where('organization_id',$owner)->where('student_id',$student)->where('attempt_id',$attempt)->where('exam_id',$exam)->where('expires_at','>',now());
 }
 public function attempts(string $workspace,int $source,string $learner,int $after=0):array {
  abort_unless($after>=0,422);[$owner,$student]=$this->reviewer($workspace,$source,$learner);
  $rows=ExamResult::where('organization_id',$owner)->where('student_id',$student)->where('id','>',$after)
   ->whereExists(function($q)use($workspace,$owner,$student){$q->selectRaw('1')->from('tech4learn_proctor_evidence')->whereColumn('attempt_id','exam_results.id')->whereColumn('exam_id','exam_results.exam_id')->where('workspace_id',$workspace)->where('organization_id',$owner)->where('student_id',$student)->where('expires_at','>',now());})
   ->orderBy('id')->limit(51)->get(['id','exam_id','start_time','end_time']);
  $more=$rows->count()>50;$rows=$rows->take(50);
  return ['items'=>$rows->map(function($row)use($owner){
   $exam=Exam::where('organization_id',$owner)->findOrFail($row->exam_id);
   return ['attempt_id'=>(int)$row->id,'exam_id'=>(int)$row->exam_id,'exam_name'=>mb_substr(strip_tags((string)$exam->name),0,250),'started_at'=>$row->start_time?Carbon::parse($row->start_time)->toIso8601String():null,'finished_at'=>$row->end_time?Carbon::parse($row->end_time)->toIso8601String():null];
  })->values()->all(),'next'=>$more?(int)$rows->last()->id:null];
 }
 public function review(string $workspace,int $source,string $learner,int $attemptId,?string $capture=null):array {
  abort_unless($attemptId>0,422);[$owner,$student]=$this->reviewer($workspace,$source,$learner);
  $attempt=ExamResult::where('organization_id',$owner)->where('student_id',$student)->findOrFail($attemptId);
  Exam::where('organization_id',$owner)->findOrFail($attempt->exam_id);
  $query=$this->visible($workspace,$owner,$student,$attemptId,(int)$attempt->exam_id);
  if($capture===null){
   $rows=$query->orderBy('received_at')->orderBy('request_id')->limit(1200)->get(['request_id','attempt_id','received_at','expires_at']);
   return ['attempt_id'=>$attemptId,'items'=>$rows->map(fn($row)=>array_diff_key($this->receipt($row),['saved'=>true]))->values()->all()];
  }
  abort_unless(preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/D',$capture),422);
  $row=$query->where('request_id',$capture)->first();abort_unless($row,404);
  return ['attempt_id'=>$attemptId,'capture_id'=>$capture,'mime'=>'image/jpeg','base64'=>$row->image_base64,'expires_at'=>Carbon::parse($row->expires_at)->toIso8601String()];
 }
 public function capture(string $workspace,int $source,string $learner,int $attemptId,string $requestId,string $base64):array {
  foreach([$workspace,$learner,$requestId] as $id)abort_unless(preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/D',$id),422);
  abort_unless($attemptId>0&&strlen($base64)<=349528,422);
  $bytes=base64_decode($base64,true);abort_unless(is_string($bytes)&&strlen($bytes)>0&&strlen($bytes)<=262144&&base64_encode($bytes)===$base64,422);
  $image=@getimagesizefromstring($bytes);
  abort_unless(($image['mime']??'')==='image/jpeg'&&$image[0]>0&&$image[1]>0&&$image[0]<=1280&&$image[1]<=960,422);
  $hash=hash('sha256',$bytes);
  return DB::transaction(function()use($workspace,$source,$learner,$attemptId,$requestId,$base64,$hash){
   $w=DB::table('tech4learn_workspaces')->where('id',$workspace)->where('source_organization_id',$source)->lockForUpdate()->first();abort_unless($w&&$w->organization_id,404);
   abort_unless(\App\Models\Organization::where('id',$w->organization_id)->where('status','active')->exists(),403);
   abort_unless(!in_array('taking',json_decode($w->restrictions,true,512,JSON_THROW_ON_ERROR),true),403);
   $studentId=DB::table('tech4learn_workspace_users')->where('workspace_id',$workspace)->where('local_id',$learner)->where('kind','student')->value('external_id');
   $student=Student::where('organization_id',$w->organization_id)->where('status','Active')->findOrFail($studentId);
   $attempt=ExamResult::where('organization_id',$w->organization_id)->where('student_id',$student->id)->lockForUpdate()->findOrFail($attemptId);
   $exam=Exam::where('organization_id',$w->organization_id)->findOrFail($attempt->exam_id);
   $prior=DB::table('tech4learn_proctor_evidence')->where('workspace_id',$workspace)->where('request_id',$requestId)->first();
   if($prior){abort_unless((int)$prior->attempt_id===$attemptId&&(int)$prior->student_id===(int)$student->id&&hash_equals($prior->image_hash,$hash),409,'Capture request already used.');return $this->receipt($prior);}
   abort_unless($exam->proctor&&$exam->status==='Active'&&$exam->isFrontendVisible()&&$exam->allowsOnlineAttempt()&&!$attempt->end_time,403);
   $now=now();$start=Carbon::parse($attempt->start_time);abort_unless($start->lte($now),409);
   $minutes=(float)($attempt->total_test_time??$exam->duration??0);
   if($minutes>0)abort_unless($now->lt($start->copy()->addSeconds((int)($minutes*60))),409);
   if($exam->end_date)abort_unless($now->lt(Carbon::parse($exam->end_date)),409);
   $clock=app(Tech4LearnAttemptClock::class)->state($workspace,$exam,$attempt);
   if($clock!==null)abort_unless($clock['remaining_seconds']>0,409);
   $query=DB::table('tech4learn_proctor_evidence')->where('workspace_id',$workspace)->where('attempt_id',$attemptId);
   $last=$query->orderByDesc('received_at')->first();
   if($last)abort_unless(Carbon::parse($last->received_at)->addSeconds(25)->lte($now),429,'Capture interval has not elapsed.');
   abort_unless((clone $query)->count()<1200,422,'Capture limit reached.');
   $record=['workspace_id'=>$workspace,'request_id'=>$requestId,'organization_id'=>$w->organization_id,'student_id'=>$student->id,'attempt_id'=>$attemptId,'exam_id'=>$exam->id,'image_hash'=>$hash,'image_base64'=>$base64,'received_at'=>$now->toDateTimeString(),'expires_at'=>$now->copy()->addDays(30)->toDateTimeString()];
   DB::table('tech4learn_proctor_evidence')->insert($record);
   return $this->receipt((object)$record);
  });
 }
 private function receipt(object $row):array {return ['saved'=>true,'capture_id'=>$row->request_id,'attempt_id'=>(int)$row->attempt_id,'received_at'=>Carbon::parse($row->received_at)->toIso8601String(),'expires_at'=>Carbon::parse($row->expires_at)->toIso8601String()];}
 /** Maintenance only: records expire independently of continued learner activity. */
 public function purgeExpired(int $limit=500):int {
  abort_unless($limit>=1&&$limit<=500,422);
  return DB::transaction(function()use($limit){
   $rows=DB::table('tech4learn_proctor_evidence')->where('expires_at','<=',now())->orderBy('expires_at')->limit($limit)->lockForUpdate()->get(['workspace_id','request_id']);$deleted=0;
   foreach($rows as $row)$deleted+=DB::table('tech4learn_proctor_evidence')->where('workspace_id',$row->workspace_id)->where('request_id',$row->request_id)->where('expires_at','<=',now())->delete();
   return $deleted;
  });
 }
}

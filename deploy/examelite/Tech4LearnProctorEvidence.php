<?php
namespace App\Services;

use App\Models\{Exam,ExamResult,Student};
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/** Private native evidence store. Not exposed until capture/review adapters are ready. */
final class Tech4LearnProctorEvidence
{
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

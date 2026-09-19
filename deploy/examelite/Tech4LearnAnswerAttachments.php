<?php
namespace App\Services;

use Illuminate\Support\Facades\DB;

/** Internal reader. Public callers must separately authenticate a learner grant or staff permission. */
final class Tech4LearnAnswerAttachments
{
 private const MAX_BYTES=10485760;

 public function read(string $workspace,int $source,string $learner,int $attempt,int $question,string $asset,bool $review=false):array {
  foreach([$workspace,$learner] as $id)abort_unless(preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/D',$id),422);
  abort_unless($source>0&&$attempt>0&&$question>0&&preg_match('/^[a-f0-9]{64}$/D',$asset),422);
  $record=$this->record($workspace,$source,$learner,$attempt,$question,$review);
  $reference=$record->uploaded_answer_path;
  abort_unless(is_string($reference)&&hash_equals(hash('sha256',$reference),$asset),404);
  $prefix='t4l-private-answers/'.$record->organization_id.'_'.$record->student_id.'_'.$attempt.'_'.$question.'_';
  abort_unless(str_starts_with($reference,$prefix)&&preg_match('/^[a-f0-9]{40}\.(jpg|jpeg|png|pdf|doc|docx|txt)$/D',substr($reference,strlen($prefix))),404);
  $root=storage_path('app/t4l-private-answers');$base=realpath($root);
  $path=$root.DIRECTORY_SEPARATOR.basename($reference);$resolved=realpath($path);
  abort_unless($base&&$resolved&&!is_link($root)&&!is_link($path)&&str_starts_with($resolved,$base.DIRECTORY_SEPARATOR)&&is_file($resolved),404);
  $size=filesize($resolved);abort_unless(is_int($size)&&$size>0&&$size<=self::MAX_BYTES,422);
  $bytes=file_get_contents($resolved,false,null,0,self::MAX_BYTES+1);
  abort_unless(is_string($bytes)&&strlen($bytes)===$size,503);
  // Recheck native membership, restrictions, attempt state and reference after I/O.
  $fresh=$this->record($workspace,$source,$learner,$attempt,$question,$review);
  abort_unless($fresh->id===$record->id&&$fresh->uploaded_answer_path===$reference,409,'The attachment changed. Reload it.');
  $mime=(new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes);
  if(!in_array($mime,['text/plain','application/pdf','image/jpeg','image/png','application/msword','application/vnd.openxmlformats-officedocument.wordprocessingml.document'],true))$mime='application/octet-stream';
  return ['attempt_id'=>$attempt,'question_id'=>$question,'asset'=>$asset,'mime'=>$mime,'base64'=>base64_encode($bytes)];
 }

 private function record(string $workspace,int $source,string $learner,int $attempt,int $question,bool $review):object {
  $w=DB::table('tech4learn_workspaces')->where('id',$workspace)->where('source_organization_id',$source)->first();abort_unless($w&&$w->organization_id,404);
  abort_unless(DB::table('organizations')->where('id',$w->organization_id)->where('status','active')->exists(),403);
  abort_unless(!in_array($review?'results':'taking',json_decode($w->restrictions,true,512,JSON_THROW_ON_ERROR),true),403);
  $student=DB::table('tech4learn_workspace_users')->where('workspace_id',$workspace)->where('local_id',$learner)->where('kind','student')->value('external_id');
  $studentQuery=DB::table('students')->where('id',$student)->where('organization_id',$w->organization_id);
  if(!$review)$studentQuery->where('status','Active');
  abort_unless($studentQuery->exists(),403);
  $result=DB::table('exam_results')->where('id',$attempt)->where('organization_id',$w->organization_id)->where('student_id',$student)->first();abort_unless($result,404);
  abort_unless($review?(bool)$result->end_time:!$result->end_time,403);
  $exam=\App\Models\Exam::where('organization_id',$w->organization_id)->findOrFail($result->exam_id);
  if(!$review)abort_unless($exam->status==='Active'&&$exam->isFrontendVisible()&&$exam->allowsOnlineAttempt(),403);
  $stat=DB::table('exam_stats')->where('organization_id',$w->organization_id)->where('exam_result_id',$attempt)->where('exam_id',$result->exam_id)->where('student_id',$student)->where('question_id',$question)->first();
  abort_unless($stat,404);return $stat;
 }
}

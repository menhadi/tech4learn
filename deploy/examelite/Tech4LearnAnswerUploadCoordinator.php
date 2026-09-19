<?php
namespace App\Services;

use App\Models\{Organization,Student,Exam,ExamResult,ExamStat};
use Illuminate\Http\{UploadedFile,JsonResponse};
use Illuminate\Support\Facades\DB;

/** Private credential adapter; the gateway must authenticate the stored learner grant. */
final class Tech4LearnAnswerUploadCoordinator
{
 public function save(string $workspace,int $source,string $learner,int $attempt,int $question,array $input,UploadedFile $file):array {
  foreach([$workspace,$learner,$input['request_id']??''] as $id)abort_unless(is_string($id)&&preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/D',$id),422);
  $examId=$input['exam_id']??null;
  abort_unless($source>0&&$attempt>0&&$question>0&&is_int($examId)&&$examId>0&&array_diff(array_keys($input),['request_id','revision','exam_id'])===[],422);
  $revision=$input['revision']??null;abort_unless(is_string($revision)&&preg_match('/^[a-f0-9]{64}$/D',$revision),422);
  abort_unless($file->isValid()&&$file->getSize()>0&&$file->getSize()<=10485760,422);
  $hash=hash_file('sha256',$file->getRealPath());abort_unless(is_string($hash),422);
  $requestId=$input['request_id'];
  $fingerprint=hash('sha256',json_encode(['attachment',$source,$learner,$examId,$attempt,$question,$revision,$hash],JSON_THROW_ON_ERROR));
  $w=$this->workspace($workspace,$source,false);
  $studentId=DB::table('tech4learn_workspace_users')->where('workspace_id',$workspace)->where('local_id',$learner)->where('kind','student')->value('external_id');
  $student=Student::where('organization_id',$w->organization_id)->where('status','Active')->findOrFail($studentId);
  return app(Tech4LearnStudentContext::class)->run((int)$w->organization_id,$student,['exam_result_id'=>$attempt,'question_id'=>$question],function($request)use($workspace,$source,$learner,$student,$examId,$attempt,$question,$requestId,$revision,$fingerprint,$file){
   $request->files->set('answer_file',$file);$stat=null;
   $before=function()use($workspace,$source,$learner,$student,$examId,$attempt,$question,$requestId,$revision,$fingerprint,&$stat){
    $w=$this->workspace($workspace,$source,true);
    abort_unless(\App\Support\SaasAccess::featureEnabled('ai_subjective_analysis'),403);
    $mapped=DB::table('tech4learn_workspace_users')->where('workspace_id',$workspace)->where('local_id',$learner)->where('kind','student')->value('external_id');
    abort_unless((int)$mapped===(int)$student->id&&(int)$w->organization_id===(int)$student->organization_id,403);
    Student::where('organization_id',$w->organization_id)->where('status','Active')->lockForUpdate()->findOrFail($student->id);
    $result=ExamResult::where('organization_id',$w->organization_id)->where('student_id',$student->id)->where('exam_id',$examId)->lockForUpdate()->findOrFail($attempt);
    $exam=Exam::where('organization_id',$w->organization_id)->findOrFail($result->exam_id);
    $stat=ExamStat::where('organization_id',$w->organization_id)->where('student_id',$student->id)->where('exam_id',$exam->id)->where('exam_result_id',$attempt)->where('question_id',$question)->lockForUpdate()->firstOrFail();
    $prior=DB::table('tech4learn_attempt_requests')->where('workspace_id',$workspace)->where('request_id',$requestId)->first();
    if($prior){abort_unless(hash_equals($prior->fingerprint,$fingerprint),409,'Request ID already used.');return new JsonResponse(json_decode($prior->result,true,512,JSON_THROW_ON_ERROR));}
    abort_unless(!$result->end_time&&$exam->status==='Active'&&$exam->isFrontendVisible()&&$exam->allowsOnlineAttempt(),403);
    abort_unless(!$exam->browser_tolerance||(int)$exam->tolerance_count<=0||(int)$result->tolerance_count<(int)$exam->tolerance_count,409);
    $nativeQuestion=$stat->question()->where('organization_id',$w->organization_id)->firstOrFail();
    abort_unless(app(QuestionAnswerEvaluator::class)->questionType($nativeQuestion)==='subjective',422);
    app(Tech4LearnAttemptClock::class)->assertQuestion($workspace,$exam,$result,$question);
    abort_unless(hash_equals(app(Tech4LearnAttemptAnswers::class)->revision($stat),$revision),409,'This answer changed. Reload before uploading.');
    return null;
   };
   $complete=function(JsonResponse $response)use($workspace,$source,$learner,$student,$examId,$requestId,$fingerprint,$attempt,$question,&$stat){
    $w=$this->workspace($workspace,$source,true);
    $mapped=DB::table('tech4learn_workspace_users')->where('workspace_id',$workspace)->where('local_id',$learner)->where('kind','student')->value('external_id');
    abort_unless((int)$mapped===(int)$student->id&&(int)$w->organization_id===(int)$student->organization_id&&\App\Support\SaasAccess::featureEnabled('ai_subjective_analysis'),403);
    Student::where('organization_id',$w->organization_id)->where('status','Active')->findOrFail($student->id);
    $fresh=$stat->fresh();$path=$fresh->uploaded_answer_path;
    abort_unless(is_string($path)&&str_starts_with($path,'t4l-private-answers/')&&($response->getData(true)['path']??null)===$path,503);
    $receipt=['success'=>true,'saved'=>true,'exam_id'=>$examId,'attempt_id'=>$attempt,'question_id'=>$question,'asset'=>hash('sha256',$path),'revision'=>app(Tech4LearnAttemptAnswers::class)->revision($fresh)];
    DB::table('tech4learn_attempt_requests')->insert(['workspace_id'=>$workspace,'request_id'=>$requestId,'fingerprint'=>$fingerprint,'result'=>json_encode($receipt,JSON_THROW_ON_ERROR),'created_at'=>now()]);
    return new JsonResponse($receipt);
   };
   $response=Tech4LearnPrivateAnswerUpload::run($request,$before,$complete);
   abort_unless(($response->getData(true)['success']??false)===true,422,'ExamElite could not save this attachment.');
   return $response->getData(true);
  });
 }
 private function workspace(string $id,int $source,bool $lock):object {
  $query=DB::table('tech4learn_workspaces')->where('id',$id)->where('source_organization_id',$source);
  if($lock)$query->lockForUpdate();$w=$query->first();abort_unless($w&&$w->organization_id,404);
  abort_unless(Organization::where('id',$w->organization_id)->where('status','active')->exists(),403);
  abort_unless(!in_array('taking',json_decode($w->restrictions,true,512,JSON_THROW_ON_ERROR),true),403);
  return $w;
 }
}

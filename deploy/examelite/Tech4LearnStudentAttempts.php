<?php
namespace App\Services;
use App\Models\{Exam,ExamResult,Student};
use App\Http\Controllers\Students\StudentExamsController;
use Illuminate\Support\Facades\{DB,Hash};
use Illuminate\Contracts\View\View;

/** Caller is the credential-authenticated T4L server, using its stored student grant. */
final class Tech4LearnStudentAttempts
{
 public function run(string $workspace,int $source,string $learner,string $name,int $examId,string $action,array $fields):array {
  abort_unless(in_array($action,['start','answer','submit'],true)&&$examId>0,422);
  foreach([$workspace,$learner] as $id)abort_unless(preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/D',$id),422);
  $requestId=$fields['request_id']??'';abort_unless(is_string($requestId)&&preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/D',$requestId),422);
  $allowed=match($action){'start'=>['request_id','language_id'],'answer'=>['request_id','attempt_id','question_id','fields','revision'],'submit'=>['request_id','attempt_id']};
  abort_unless(array_diff(array_keys($fields),$allowed)===[],422);
  if(isset($fields['language_id']))abort_unless(is_int($fields['language_id'])&&$fields['language_id']>0,422);
  if($action==='answer')abort_unless(is_int($fields['question_id']??null)&&is_array($fields['fields']??null)&&is_string($fields['revision']??null),422);
  return DB::transaction(function()use($workspace,$source,$learner,$name,$examId,$action,$fields,$requestId){
   $w=DB::table('tech4learn_workspaces')->where('id',$workspace)->where('source_organization_id',$source)->lockForUpdate()->first();
   abort_unless($w&&$w->organization_id,404);$tenant=(int)$w->organization_id;
   abort_unless(\App\Models\Organization::where('id',$tenant)->where('status','active')->exists(),403);
   abort_unless(!in_array('taking',json_decode($w->restrictions,true,512,JSON_THROW_ON_ERROR),true),403);
   $showResults=!in_array('results',json_decode($w->restrictions,true,512,JSON_THROW_ON_ERROR),true);
   $exam=Exam::where('organization_id',$tenant)->findOrFail($examId);
   $nativeId=DB::table('tech4learn_workspace_users')->where('workspace_id',$workspace)->where('local_id',$learner)->where('kind','student')->value('external_id');
   if(!$nativeId){
    abort_unless($action==='start'&&mb_strlen($name)>0&&mb_strlen($name)<=255,422);
    // Explicit exam grant authorises this identity only. Do not enrol in every group.
    $student=Student::create(['organization_id'=>$tenant,'name'=>$name,'email'=>null,'phone'=>null,'password'=>Hash::make(bin2hex(random_bytes(32))),'status'=>'Active']);
    DB::table('tech4learn_workspace_users')->insert(['workspace_id'=>$workspace,'local_id'=>$learner,'kind'=>'student','external_id'=>$student->id]);
   }else $student=Student::where('organization_id',$tenant)->where('status','Active')->lockForUpdate()->findOrFail($nativeId);
   if($action==='answer'){
    $attempt=$this->attempt($tenant,$student->id,$examId,$fields['attempt_id']??null);
    return app(Tech4LearnAttemptAnswers::class)->save($workspace,$source,$learner,$attempt->id,(int)($fields['question_id']??0),$fields['fields']??[],(string)($fields['revision']??''),$requestId);
   }
   $fingerprint=hash('sha256',json_encode([$action,$source,$learner,$examId,$fields],JSON_THROW_ON_ERROR));
   $prior=DB::table('tech4learn_attempt_requests')->where('workspace_id',$workspace)->where('request_id',$requestId)->first();
   if($prior)abort_unless(hash_equals($prior->fingerprint,$fingerprint),409,'Request ID already used.');
   $attempt=$action==='submit'?$this->attempt($tenant,$student->id,$examId,$fields['attempt_id']??null):($prior?$this->attempt($tenant,$student->id,$examId,json_decode($prior->result,true)['attempt_id']):ExamResult::where('organization_id',$tenant)->where('student_id',$student->id)->where('exam_id',$examId)->whereNull('end_time')->lockForUpdate()->first());
   if($attempt?->end_time)return $this->completed($exam,$attempt,$showResults);
   if($action==='start'&&!$attempt){
    $count=ExamResult::where('organization_id',$tenant)->where('student_id',$student->id)->where('exam_id',$examId)->whereNotNull('end_time')->count();
    abort_unless((int)$exam->attempt_count===0||$count<(int)$exam->attempt_count,409,'No attempts remain for this exam.');
   }
   if($action==='start'){
    abort_unless(!$attempt||$attempt->total_test_time===null||(float)$attempt->total_test_time===(float)$exam->duration,409,'The paper duration changed after this attempt started. Ask exam staff to restore its duration before resuming.');
    // Do not silently launch modes whose internal controls are not wired yet.
    abort_unless(!$exam->proctor&&!$exam->browser_tolerance&&!$exam->option_shuffle&&!$exam->calculator_allowed&&($exam->timer_mode??'none')==='none',422,'This exam requires delivery controls that are not yet available in Tech4Learn.');
    abort_unless($exam->questions()->count()<=500,422,'This paper exceeds the current online question limit.');
   }
   // Native start rejects a closed paper before reaching its timeout handler.
   // An existing expired attempt must instead reach native finalisation.
   if($action==='start'&&$attempt){
    $duration=(float)($attempt->total_test_time??$exam->duration);
    $expired=$duration>0&&now()->greaterThanOrEqualTo(\Carbon\Carbon::parse($attempt->start_time)->addSeconds((int)($duration*60)));
    if($exam->end_date&&now()->greaterThanOrEqualTo(\Carbon\Carbon::parse($exam->end_date)))$expired=true;
    if($expired)$action='submit';
   }
   $language=$attempt?->language_id??($fields['language_id']??null);
   $result=app(Tech4LearnStudentContext::class)->run($tenant,$student,['lang'=>$language,'exam_result_id'=>$attempt?->id],function($request,$session)use($action,$examId,$tenant,$student,$exam,$attempt,$showResults){
    $native=app(StudentExamsController::class);
    $response=$action==='start'?$native->startExam($request,$examId):$native->finishExam($request);
    abort_unless(!$session->has('error'),409,(string)$session->get('error'));
    if($response instanceof View){
     $view=$response->getData();
     // Native first-load models omit database defaults. Hash persisted rows so the
     // first answer uses the same revision as a subsequent read or resume.
     $view['examStats']=\App\Models\ExamStat::where('organization_id',$tenant)->where('student_id',$student->id)->where('exam_result_id',$view['examResult']->id)->get()->keyBy('question_id');
     $payload=app(Tech4LearnAttemptPayload::class)->fromNativeView($view,$tenant,$student->id);
     foreach($payload['questions'] as $question){
      $texts=array_values($question['content']);$texts[]=$question['passage']['content']??'';
      foreach($texts as $text)abort_unless(!preg_match('/<(?:img|svg|math|math-field|iframe|video|audio|object|embed)\b|\\\\(?:\(|\[)|\$\$/i',$text),422,'This paper needs media or formula display that is not available yet.');
     }
     return $payload;
    }
    $ended=$attempt?->fresh()??ExamResult::where('organization_id',$tenant)->where('student_id',$student->id)->where('exam_id',$examId)->latest('id')->first();
    abort_unless($ended&&$ended->end_time,409,'ExamElite could not open or finish this attempt.');
    return $this->completed($exam,$ended,$showResults);
   });
   if(!$prior)DB::table('tech4learn_attempt_requests')->insert(['workspace_id'=>$workspace,'request_id'=>$requestId,'fingerprint'=>$fingerprint,'result'=>json_encode(['attempt_id'=>$result['attempt_id']],JSON_THROW_ON_ERROR),'created_at'=>now()]);
   return $result;
  });
 }
 private function attempt(int $tenant,int $student,int $exam,mixed $id):ExamResult {
  abort_unless(is_int($id)&&$id>0,422);
  return ExamResult::where('organization_id',$tenant)->where('student_id',$student)->where('exam_id',$exam)->lockForUpdate()->findOrFail($id);
 }
 private function completed(Exam $exam,ExamResult $result,bool $showResults):array {
  return ['attempt_id'=>(int)$result->id,'exam_id'=>(int)$exam->id,'completed'=>true,'result'=>$showResults&&$exam->result_after_finish?['status'=>$result->result,'score_percent'=>(float)$result->percent,'obtained_marks'=>(float)$result->obtained_marks,'total_marks'=>(float)$result->total_marks]:null];
 }
}

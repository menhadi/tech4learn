<?php
namespace App\Services;

use App\Models\{Exam,ExamResult,ExamStat,Organization,Student,User};
use App\Support\Tenant;
use Illuminate\Http\Request;
use Illuminate\Routing\Redirector;
use Illuminate\Session\{Store,ArraySessionHandler};
use Illuminate\Support\Facades\{Auth,DB};

/** Staff-only adapter. ExamElite remains responsible for calculating the result. */
final class Tech4LearnResultMarking
{
 private function scope(string $workspace,int $source,string $actor,string $learner,bool $allowUnmapped=false):array {
  foreach([$workspace,$actor,$learner] as $id)abort_unless(preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/D',$id),422);
  $w=DB::table('tech4learn_workspaces')->where('id',$workspace)->where('source_organization_id',$source)->lockForUpdate()->first();abort_unless($w&&$w->organization_id,404);
  $owner=(int)$w->organization_id;
  Organization::where('status','active')->findOrFail($owner);
  abort_unless(!in_array('results',json_decode($w->restrictions,true,512,JSON_THROW_ON_ERROR),true),403);
  $mapping=fn($id,$kind)=>DB::table('tech4learn_workspace_users')->where('workspace_id',$workspace)->where('local_id',$id)->where('kind',$kind)->value('external_id');
  $user=User::where('status',1)->lockForUpdate()->findOrFail($mapping($actor,'staff'));
  abort_unless(DB::table('organization_users')->where('organization_id',$owner)->where('user_id',$user->id)->where('status',1)->exists(),403);
  $studentId=$mapping($learner,'student');
  if($studentId===null&&$allowUnmapped)return [$owner,$user,0];
  $student=Student::where('organization_id',$owner)->findOrFail($studentId);
  return [$owner,$user,(int)$student->id];
 }
 private function attempt(int $owner,int $student,int $id):ExamResult {
  abort_unless($id>0,422);
  $attempt=ExamResult::where('organization_id',$owner)->where('student_id',$student)->lockForUpdate()->findOrFail($id);
  abort_unless($attempt->end_time,409,'Submit the attempt before marking.');
  Exam::where('organization_id',$owner)->lockForUpdate()->findOrFail($attempt->exam_id);
  return $attempt;
 }
 private function pending(ExamResult $attempt) {
  $rows=ExamStat::where('organization_id',$attempt->organization_id)->where('exam_result_id',$attempt->id)->where('ques_status','P')->orderBy('id')->lockForUpdate()->limit(501)->get();
  abort_unless($rows->count()<=500,422,'This attempt exceeds the marking limit.');
  foreach($rows as $row)abort_unless((int)$row->student_id===(int)$attempt->student_id&&(int)$row->exam_id===(int)$attempt->exam_id,409);
  return $rows;
 }
 private function revision(ExamResult $attempt,$rows):string {
  $exam=Exam::where('organization_id',$attempt->organization_id)->findOrFail($attempt->exam_id);
  $ids=$rows->pluck('question_id')->unique()->values()->all();
  $questions=\App\Models\Question::where('organization_id',$attempt->organization_id)->whereIn('id',$ids)->orderBy('id')->lockForUpdate()->get();
  abort_unless($questions->count()===count($ids),409);
  return hash('sha256',json_encode([$attempt->getAttributes(),$exam->passing_percentage,$rows->map(fn($row)=>$row->getAttributes())->all(),$questions->map(fn($row)=>[$row->getAttributes(),$this->content($row,$attempt)])->all()],JSON_THROW_ON_ERROR));
 }
 private function summary(ExamResult $attempt):array {
  return ['attempt_id'=>(int)$attempt->id,'result'=>(string)$attempt->result,'score_percent'=>(float)$attempt->percent,'obtained_marks'=>(float)$attempt->obtained_marks,'total_marks'=>(float)$attempt->total_marks];
 }
 private function content($question,ExamResult $attempt):array {
  $translated=$question->langs()->where('language_id',(int)$attempt->language_id)->lockForUpdate()->first();
  $passage=null;
  if($question->passage_id){
   $source=\App\Models\Passage::where('organization_id',$attempt->organization_id)->lockForUpdate()->findOrFail($question->passage_id);
   // Match the passage language/fallback used by the native student payload.
   $language=$source->langs()->where('language_id',(int)$question->language_id)->lockForUpdate()->first()??$source->langs()->orderBy('id')->lockForUpdate()->first();
   abort_unless($language&&trim((string)$language->passage)!=='',409,'Passage content is unavailable.');
   $passage=['name'=>mb_substr(strip_tags((string)$source->name),0,250),'html'=>(string)$language->passage];
  }
  return ['question_html'=>(string)($translated?->question??$question->question),'passage'=>$passage];
 }
 private function formattedSupported(array $display,$row):bool {
  // Until the staff media renderer is connected, never grade an incomplete display.
  $answers=(string)$row->answer.' '.app(Tech4LearnQuestionMedia::class)->reference((string)$row->correct_answer);
  $content=$display['question_html'].' '.($display['passage']['html']??'').' '.$answers;
  return !preg_match('/<img\b/i',(string)$row->answer)&&!preg_match('/<(?:svg|video|audio|iframe|object|script|style|canvas|embed|math-field)\b/i',$content);
 }
 private function html(mixed $value):string {return (string)$value;}
 public function attempts(string $workspace,int $source,string $actor,string $learner,int $after=0):array {
  abort_unless($after>=0,422);
  return DB::transaction(function()use($workspace,$source,$actor,$learner,$after){
   [$owner,,$student]=$this->scope($workspace,$source,$actor,$learner,true);
   if(!$student)return ['items'=>[],'next'=>null];
   $rows=ExamResult::where('organization_id',$owner)->where('student_id',$student)->whereNotNull('end_time')->where('id','>',$after)->orderBy('id')->limit(51)->get();
   $more=$rows->count()>50;$rows=$rows->take(50);
   return ['items'=>$rows->map(function($row)use($owner){
    $exam=Exam::where('organization_id',$owner)->findOrFail($row->exam_id);
    return $this->summary($row)+['exam_id'=>(int)$exam->id,'exam_name'=>mb_substr(strip_tags((string)$exam->name),0,250),'finished_at'=>\Carbon\Carbon::parse($row->end_time)->toIso8601String(),'pending_count'=>ExamStat::where('organization_id',$owner)->where('exam_result_id',$row->id)->where('student_id',$row->student_id)->where('ques_status','P')->count()];
   })->all(),'next'=>$more?(int)$rows->last()->id:null];
  });
 }
 public function review(string $workspace,int $source,string $actor,string $learner,int $id):array {
  return DB::transaction(function()use($workspace,$source,$actor,$learner,$id){
   [$owner,,$student]=$this->scope($workspace,$source,$actor,$learner);$attempt=$this->attempt($owner,$student,$id);$rows=$this->pending($attempt);
   return ['revision'=>$this->revision($attempt,$rows),'summary'=>$this->summary($attempt),'questions'=>$rows->map(function($row)use($owner,$attempt){
    $question=\App\Models\Question::where('organization_id',$owner)->findOrFail($row->question_id);
    $display=$this->content($question,$attempt);
    $media=app(Tech4LearnQuestionMedia::class);$display['question_html']=$media->rewrite($display['question_html']);
    if($display['passage'])$display['passage']['html']=$media->rewrite($display['passage']['html']);
    return $display+['stat_id'=>(int)$row->id,'question_id'=>(int)$row->question_id,'answer_html'=>$this->html($row->answer),'reference_html'=>$media->rewrite($media->reference((string)$row->correct_answer)),'review_supported'=>$this->formattedSupported($display,$row),'maximum_marks'=>(float)$row->marks];
   })->all()];
  });
 }
 public function attachment(string $workspace,int $source,string $actor,string $learner,int $id,int $question,string $asset,int $examId):array {
  return DB::transaction(function()use($workspace,$source,$actor,$learner,$id,$question,$asset,$examId){
   [$owner,,$student]=$this->scope($workspace,$source,$actor,$learner);
   $attempt=$this->attempt($owner,$student,$id);abort_unless((int)$attempt->exam_id===$examId,404);
   $data=app(Tech4LearnAnswerAttachments::class)->read($workspace,$source,$learner,$id,$question,$asset,$examId,true);
   $this->scope($workspace,$source,$actor,$learner);
   return $data;
  });
 }
 public function media(string $workspace,int $source,string $actor,string $learner,int $id,int $statId,string $asset):array {
  return DB::transaction(function()use($workspace,$source,$actor,$learner,$id,$statId,$asset){
   [$owner,,$student]=$this->scope($workspace,$source,$actor,$learner);$attempt=$this->attempt($owner,$student,$id);
   $stat=ExamStat::where('organization_id',$owner)->where('student_id',$student)->where('exam_id',$attempt->exam_id)->where('exam_result_id',$id)->where('ques_status','P')->findOrFail($statId);
   $question=\App\Models\Question::where('organization_id',$owner)->findOrFail($stat->question_id);
   return app(Tech4LearnQuestionMedia::class)->readReview($question,$attempt,$stat,$asset)+['stat_id'=>$statId];
  });
 }
 public function save(string $workspace,int $source,string $actor,string $learner,int $id,array $marks,string $revision,string $requestId):array {
  abort_unless(preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/D',$requestId)&&preg_match('/^[a-f0-9]{64}$/D',$revision),422);
  abort_unless(count($marks)>0&&count($marks)<=500,422);ksort($marks,SORT_NUMERIC);
  foreach($marks as $stat=>$value)abort_unless(preg_match('/^[1-9][0-9]{0,14}$/D',(string)$stat)&&(is_int($value)||is_float($value))&&is_finite((float)$value)&&$value>=0,422);
  $fingerprint=hash('sha256',json_encode(['manual-result',$source,$actor,$learner,$id,$marks,$revision],JSON_THROW_ON_ERROR));
  return DB::transaction(function()use($workspace,$source,$actor,$learner,$id,$marks,$revision,$requestId,$fingerprint){
   [$owner,$user,$student]=$this->scope($workspace,$source,$actor,$learner);$attempt=$this->attempt($owner,$student,$id);
   $prior=DB::table('tech4learn_authoring_requests')->where('workspace_id',$workspace)->where('request_id',$requestId)->first();
   if($prior){abort_unless(hash_equals($prior->fingerprint,$fingerprint),409);return json_decode($prior->result,true,512,JSON_THROW_ON_ERROR);}
   $rows=$this->pending($attempt);abort_unless(hash_equals($this->revision($attempt,$rows),$revision),409,'The attempt changed. Reload before marking.');
   abort_unless($rows->count()===count($marks),422,'Mark every pending answer together.');
   foreach($rows as $row)abort_unless(array_key_exists($row->id,$marks)&&is_numeric($row->marks)&&is_finite((float)$row->marks)&&$marks[$row->id]<=(float)$row->marks,422);
   foreach($rows as $row){$question=\App\Models\Question::where('organization_id',$owner)->findOrFail($row->question_id);abort_unless($this->formattedSupported($this->content($question,$attempt),$row),422,'This answer requires the media review screen.');}
   $this->invoke($owner,$user,$attempt,$marks);
   abort_unless($this->pending($attempt)->isEmpty(),500,'Native marking did not finish.');
   $result=$this->summary($attempt->fresh());
   DB::table('tech4learn_authoring_requests')->insert(['workspace_id'=>$workspace,'request_id'=>$requestId,'fingerprint'=>$fingerprint,'result'=>json_encode($result,JSON_THROW_ON_ERROR|JSON_PRESERVE_ZERO_FRACTION),'created_at'=>now()]);
   return $result;
  });
 }
 private function invoke(int $owner,User $user,ExamResult $attempt,array $marks):void {
  $organisation=Organization::where('status','active')->findOrFail($owner);
  $app=app();$oldRequest=$app['request'];$oldRedirect=$app['redirect'];$guard=Auth::guard('web');$oldUser=$guard->user();
  $session=new Store('t4l-marking',new ArraySessionHandler(5));$session->start();
  $request=Request::create('https://'.$organisation->domain.'/results','POST',['marks'=>$marks]);$request->setLaravelSession($session);$request->setUserResolver(fn()=>$user);
  $redirect=new Redirector($app['url']);$redirect->setSession($session);
  $app->instance('request',$request);$app->instance('redirect',$redirect);$guard->setUser($user);Tenant::clear();
  try {
   abort_unless((int)Tenant::resolve($organisation->domain)->id===$owner,403);
   $app->call([app(\App\Http\Controllers\ResultController::class),'saveEvaluation'],['request'=>$request,'id'=>$attempt->id]);
   abort_unless($session->has('success')&&!$session->has('error')&&!$session->has('errors'),422,'ExamElite could not save the marking.');
  }finally{$oldUser?$guard->setUser($oldUser):$guard->forgetUser();$app->instance('request',$oldRequest);$app->instance('redirect',$oldRedirect);Tenant::clear();$session->invalidate();}
 }
}

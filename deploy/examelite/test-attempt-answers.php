<?php
require __DIR__.'/test-exam-authoring.php';
if(isset($argv[3]))foreach(['QuestionAnswerEvaluator','ExamAnswerPersistenceService'] as $class){$file=dirname($argv[3]).'/'.$class.'.php';if(is_file($file))require_once $file;}
require __DIR__.'/Tech4LearnAttemptAnswers.php';
use Illuminate\Support\Facades\DB;
use App\Models\{Exam,ExamStat,ExamResult,Student};
use Carbon\Carbon;
DB::statement('CREATE TABLE tech4learn_attempt_requests(workspace_id TEXT,request_id TEXT,fingerprint TEXT,result TEXT,created_at TEXT,PRIMARY KEY(workspace_id,request_id))');
DB::statement('CREATE TABLE students(id INTEGER PRIMARY KEY,organization_id INTEGER,status TEXT,name TEXT,created_at TEXT,updated_at TEXT)');
foreach(['exam_results'=>new ExamResult,'exam_stats'=>new ExamStat] as $table=>$model){
 $columns=array_unique(array_merge($model->getFillable(),['created_at','updated_at']));
 DB::statement('CREATE TABLE '.$table.'(id INTEGER PRIMARY KEY,'.implode(',',array_map(fn($c)=>$c.' TEXT',$columns)).')');
}
$app['config']->set('app.timezone','UTC');Carbon::setTestNow(Carbon::parse('2026-09-13 12:00:00','UTC'));
$learner='33333333-3333-3333-3333-333333333333';
$student=Student::create(['organization_id'=>20,'status'=>'Active','name'=>'Synthetic learner']);
DB::table('tech4learn_workspace_users')->insert(['workspace_id'=>$workspace,'local_id'=>$learner,'kind'=>'student','external_id'=>$student->id]);
$paper=Exam::find($exam['id']);$paper->forceFill(['status'=>'Active','frontend_visible'=>true,'online_attempt_enabled'=>true,'allow_answer_change'=>true,'end_date'=>'2026-09-13 14:00:00'])->save();
$attempt=ExamResult::create(['organization_id'=>20,'student_id'=>$student->id,'exam_id'=>$paper->id,'start_time'=>'2026-09-13 11:45:00','total_test_time'=>60]);
$stat=ExamStat::create(['organization_id'=>20,'student_id'=>$student->id,'exam_id'=>$paper->id,'exam_result_id'=>$attempt->id,'question_id'=>$q->id,'correct_answer'=>'DO NOT EXPOSE','marks'=>4,'negative_marks'=>1]);
$answers=new App\Services\Tech4LearnAttemptAnswers();
$request='44444444-4444-4444-4444-444444444444';$revision=$answers->revision($stat->fresh());
$saved=$answers->save($workspace,10,$learner,$attempt->id,$q->id,['option_selected'=>'7'],$revision,$request);
check($stat->fresh()->answer==='7' && (bool)$stat->fresh()->answered,'Native numerical answer is persisted');
check(!str_contains(json_encode($saved),'DO NOT EXPOSE')&&!array_key_exists('answer',$saved),'Save response contains no answer key');
check($answers->save($workspace,10,$learner,$attempt->id,$q->id,['option_selected'=>'7'],$revision,$request)===$saved,'Lost-response answer retry is idempotent');
function rejectAnswer(callable $fn,string $message){try{$fn();throw new RuntimeException('Expected rejection: '.$message);}catch(Symfony\Component\HttpKernel\Exception\HttpException|Illuminate\Database\Eloquent\ModelNotFoundException|Illuminate\Validation\ValidationException $e){}}
$next=fn()=>Illuminate\Support\Str::uuid()->toString();
rejectAnswer(fn()=>$answers->save($workspace,10,$learner,$attempt->id,$q->id,['option_selected'=>'8'],$revision,$next()),'stale answer');
rejectAnswer(fn()=>$answers->save($workspace,10,$learner,$attempt->id,$q->id,['option_selected'=>'8'],$saved['revision'],$request),'changed retry input');
rejectAnswer(fn()=>$answers->save($workspace,99,$learner,$attempt->id,$q->id,['option_selected'=>'8'],$saved['revision'],$next()),'foreign platform');
rejectAnswer(fn()=>$answers->save($workspace,10,$actor,$attempt->id,$q->id,['option_selected'=>'8'],$saved['revision'],$next()),'staff is not learner');
rejectAnswer(fn()=>$answers->save($workspace,10,$learner,$attempt->id,1,['option_selected'=>'8'],$saved['revision'],$next()),'foreign question');
rejectAnswer(fn()=>$answers->save($workspace,10,$learner,$attempt->id,$q->id,['question_type'=>'subjective','option_selected'=>'8'],$saved['revision'],$next()),'forged question type');
rejectAnswer(fn()=>$answers->save($workspace,10,$learner,$attempt->id,$q->id,['option_selected'=>['answer']],$saved['revision'],$next()),'malformed numerical answer');
$second=$answers->save($workspace,10,$learner,$attempt->id,$q->id,['option_selected'=>'8'],$saved['revision'],$next());
check($stat->fresh()->answer==='8','Later revision saves');
check($answers->save($workspace,10,$learner,$attempt->id,$q->id,['option_selected'=>'7'],$revision,$request)===$saved && $stat->fresh()->answer==='8','Old retry never overwrites a newer answer');
$paper->forceFill(['allow_answer_change'=>false])->save();
$locked=$answers->save($workspace,10,$learner,$attempt->id,$q->id,['option_selected'=>'8','lock_answer'=>true],$second['revision'],$next());
check($locked['answer_locked'],'Native answer locking');
rejectAnswer(fn()=>$answers->save($workspace,10,$learner,$attempt->id,$q->id,['option_selected'=>'9'],$locked['revision'],$next()),'locked native answer');
$paper->forceFill(['allow_answer_change'=>true,'duration'=>600])->save();
Carbon::setTestNow(Carbon::parse('2026-09-13 12:45:00','UTC'));
rejectAnswer(fn()=>$answers->save($workspace,10,$learner,$attempt->id,$q->id,['option_selected'=>'9'],$locked['revision'],$next()),'captured attempt deadline, despite extended exam duration');
Carbon::setTestNow(Carbon::parse('2026-09-13 12:00:00','UTC'));
$attempt->end_time=now();$attempt->save();
rejectAnswer(fn()=>$answers->save($workspace,10,$learner,$attempt->id,$q->id,['option_selected'=>'9'],$locked['revision'],$next()),'submitted attempt');
check($answers->save($workspace,10,$learner,$attempt->id,$q->id,['option_selected'=>'7'],$revision,$request)===$saved,'Accepted save can be acknowledged after submit without applying it');
DB::table('tech4learn_workspaces')->where('id',$workspace)->update(['restrictions'=>'["taking"]']);
rejectAnswer(fn()=>$answers->save($workspace,10,$learner,$attempt->id,$q->id,['option_selected'=>'7'],$revision,$request),'revoked taking restriction applies to retries');
check($stat->fresh()->answer==='8','All rejected writes preserved final accepted answer');
DB::table('tech4learn_workspaces')->where('id',$workspace)->update(['restrictions'=>'[]']);
$attempt->end_time=null;$attempt->save();
foreach([
 ['qtype_id'=>4,'true_false'=>'true','submitted'=>'false'],
 ['qtype_id'=>1,'option1'=>'One','option2'=>'Two','correct_option_indices'=>[1],'submitted'=>[2]],
 ['qtype_id'=>1,'option1'=>'One','option2'=>'Two','correct_option_indices'=>[1,2],'submitted'=>[1,2]],
 ['qtype_id'=>3,'fill_blank_config'=>['version'=>1,'blanks'=>[['answers'=>['one']],['answers'=>['two']]]],'submitted'=>['one','wrong']],
] as $sample){
 $submitted=$sample['submitted'];unset($sample['submitted']);
 $question=App\Models\Question::create(array_merge(['organization_id'=>20,'question'=>'Synthetic answer type','marks'=>4],$sample));
 $row=ExamStat::create(['organization_id'=>20,'student_id'=>$student->id,'exam_id'=>$paper->id,'exam_result_id'=>$attempt->id,'question_id'=>$question->id]);
 $response=$answers->save($workspace,10,$learner,$attempt->id,$question->id,['option_selected'=>$submitted],$answers->revision($row->fresh()),$next());
 check($response['saved'] && (bool)$row->fresh()->answered,'Native supported answer type saved');
 if($question->qtype_id===4)check($row->fresh()->true_false==='false','False is an answered native value');
 if($question->qtype_id===1)check($row->fresh()->selected_option_indices===$submitted,'Native option indices preserved');
 if($question->qtype_id===3)check(json_decode($row->fresh()->answer,true)===$submitted,'Native blank values preserved');
}
Carbon::setTestNow();
if(isset($argv[3])){$file=dirname($argv[3]).'/MathContentNormalizer.php';if(is_file($file))require_once $file;}
require __DIR__.'/Tech4LearnAttemptPayload.php';
require_once __DIR__.'/Tech4LearnQuestionMedia.php';
$display=$q->fresh();$display->question_type='nat';$display->prefilled_answer='8';$display->answer_locked=false;
$display->explanation='PRIVATE EXPLANATION';$display->nat_config=['value'=>'PRIVATE CORRECT VALUE'];
$display->setRelation('passage',null);
$display->setRelation('langs',collect([new App\Models\QuestionLang(['language_id'=>1,'question'=>'Translated student question','answer'=>'PRIVATE TRANSLATION ANSWER'])]));
$paper->setRelation('questions',collect([$display]));
$native=['exam'=>$paper,'examResult'=>$attempt,'examStats'=>collect([$q->id=>$stat->fresh()]),'selectedLanguageId'=>1,'remainingTime'=>1200,'subjectDurations'=>['subject:2'=>1200],'configuration'=>['credential'=>'PRIVATE CONFIG']];
$projection=new App\Services\Tech4LearnAttemptPayload();$payload=$projection->fromNativeView($native,20,$student->id);
check($payload['questions'][0]['answer']==='8'&&$payload['questions'][0]['content']['question']==='Translated student question','Student receives own answer and selected translation');
check(!str_contains(json_encode($payload),'PRIVATE')&&!str_contains(json_encode($payload),'DO NOT EXPOSE'),'Native models, answer keys, grading configuration and configuration are excluded');
rejectAnswer(fn()=>$projection->fromNativeView($native,10,$student->id),'payload tenant mismatch');
rejectAnswer(fn()=>$projection->fromNativeView($native,20,$student->id+1),'payload student mismatch');
echo "Native attempt answers: identity scope, deadlines, submitted locks, revisions, retries, native answer locks and response minimisation passed.\n";

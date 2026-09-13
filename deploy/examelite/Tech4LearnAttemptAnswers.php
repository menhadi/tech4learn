<?php
namespace App\Services;

use App\Models\{Exam,ExamResult,ExamStat,Student};
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Private adapter. Call only after Tech4Learn authenticates the learner and exam grant. */
final class Tech4LearnAttemptAnswers
{
    public function revision(ExamStat $stat):string {
        $raw=$stat->getAttributes();ksort($raw);
        return hash('sha256',json_encode($raw,JSON_THROW_ON_ERROR));
    }

    public function save(string $workspace,int $source,string $learner,int $attempt,int $questionId,array $fields,string $revision,string $requestId):array {
        foreach([$workspace,$learner,$requestId] as $id)abort_unless(preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/D',$id),422);
        abort_unless($attempt>0&&$questionId>0&&preg_match('/^[a-f0-9]{64}$/D',$revision),422);
        abort_unless(!array_diff(array_keys($fields),['option_selected','review','bookmark','lock_answer','time_taken']),422);
        abort_unless(strlen(json_encode($fields,JSON_THROW_ON_ERROR))<=25000,422);
        foreach(['review','bookmark','lock_answer'] as $flag)if(isset($fields[$flag]))abort_unless(is_bool($fields[$flag]),422);
        if(isset($fields['time_taken']))abort_unless(is_int($fields['time_taken'])&&$fields['time_taken']>=0&&$fields['time_taken']<=604800,422);
        $fingerprint=hash('sha256',json_encode([$source,$learner,$attempt,$questionId,$fields,$revision],JSON_THROW_ON_ERROR));
        return DB::transaction(function()use($workspace,$source,$learner,$attempt,$questionId,$fields,$revision,$requestId,$fingerprint){
            $w=DB::table('tech4learn_workspaces')->where('id',$workspace)->where('source_organization_id',$source)->first();
            abort_unless($w&&$w->organization_id,404);
            abort_unless(!in_array('taking',json_decode($w->restrictions,true,512,JSON_THROW_ON_ERROR),true),403);
            $studentId=DB::table('tech4learn_workspace_users')->where('workspace_id',$workspace)->where('local_id',$learner)->where('kind','student')->value('external_id');
            $student=Student::where('organization_id',$w->organization_id)->where('status','Active')->findOrFail($studentId);
            // Serialise all changes to this attempt, including eventual submit operations.
            $result=ExamResult::where('organization_id',$w->organization_id)->where('student_id',$student->id)->lockForUpdate()->findOrFail($attempt);
            $exam=Exam::where('organization_id',$w->organization_id)->findOrFail($result->exam_id);
            $stat=ExamStat::where('organization_id',$w->organization_id)->where('exam_result_id',$result->id)->where('exam_id',$exam->id)->where('student_id',$student->id)->where('question_id',$questionId)->lockForUpdate()->firstOrFail();
            $question=$stat->question()->where('organization_id',$w->organization_id)->firstOrFail();
            // A previously accepted answer can be acknowledged after timeout/submission,
            // but it must never be applied again or overwrite a newer answer.
            $prior=DB::table('tech4learn_attempt_requests')->where('workspace_id',$workspace)->where('request_id',$requestId)->first();
            if($prior){abort_unless(hash_equals($prior->fingerprint,$fingerprint),409,'Request ID already used.');return json_decode($prior->result,true,512,JSON_THROW_ON_ERROR);}
            abort_unless(!$result->end_time,409,'This attempt has already been submitted.');
            abort_unless($exam->status==='Active'&&$exam->isFrontendVisible()&&$exam->allowsOnlineAttempt(),403);
            $now=Carbon::now();$start=Carbon::parse($result->start_time,config('app.timezone','UTC'));
            abort_unless($start->lte($now),409,'Attempt start time is invalid.');
            // The attempt captures its allotted duration at start; do not extend it
            // when staff later changes the exam duration.
            $minutes=(float)($result->total_test_time??$exam->duration??0);
            if($minutes>0)abort_unless($now->lt($start->copy()->addSeconds((int)($minutes*60))),409,'The time for this attempt has ended.');
            if($exam->end_date)abort_unless($now->lt(Carbon::parse($exam->end_date,config('app.timezone','UTC'))),409,'This exam has closed.');
            app(Tech4LearnAttemptClock::class)->assertQuestion($workspace,$exam,$result,$questionId);
            abort_unless(hash_equals($this->revision($stat),$revision),409,'This answer changed. Reload before saving.');
            $type=app(QuestionAnswerEvaluator::class)->questionType($question);
            $selected=$fields['option_selected']??null;
            $this->validateAnswer($type,$selected,$question);
            $answered=is_array($selected)?count(array_filter($selected,fn($v)=>trim((string)$v)!==''))>0:($selected!==null&&trim((string)$selected)!=='');
            $payload=array_merge($fields,['question_type'=>$type,'option_selected'=>$selected,'answered'=>$answered]);
            $saved=app(ExamAnswerPersistenceService::class)->save($stat,$payload);
            if(($saved['success']??false)!==true)throw ValidationException::withMessages(['answer'=>$saved['message']??'ExamElite could not save this answer.']);
            $response=['saved'=>true,'question_id'=>$questionId,'revision'=>$this->revision($stat->fresh()),'answer_locked'=>(bool)($saved['answer_locked']??false)];
            DB::table('tech4learn_attempt_requests')->insert(['workspace_id'=>$workspace,'request_id'=>$requestId,'fingerprint'=>$fingerprint,'result'=>json_encode($response,JSON_THROW_ON_ERROR),'created_at'=>now()]);
            return $response;
        });
    }

    private function validateAnswer(string $type,mixed $value,object $question):void {
        if($value===null)return;
        if(in_array($type,['multiple_choice_radio','multiple_choice_checkbox'],true)){
            abort_unless(is_array($value)&&array_is_list($value)&&count($value)<=($type==='multiple_choice_radio'?1:6),422);
            foreach($value as $index)abort_unless(is_int($index)&&$index>=1&&$index<=6&&filled($question->{'option'.$index}),422);
            abort_unless(count(array_unique($value))===count($value),422);return;
        }
        if($type==='true_false'){abort_unless(in_array($value,['true','false',''],true),422);return;}
        if($type==='fill_blank'){
            abort_unless(is_array($value)&&array_is_list($value)&&count($value)===app(QuestionAnswerEvaluator::class)->fillBlankCount($question),422);
            foreach($value as $answer)abort_unless(is_string($answer)&&mb_strlen($answer)<=2000,422);return;
        }
        abort_unless(in_array($type,['nat','subjective'],true)&&is_string($value)&&mb_strlen($value)<=20000,422);
        if($type==='nat'&&$value!=='')abort_unless(strlen($value)<=100&&is_numeric($value)&&is_finite((float)$value),422);
    }
}

<?php

namespace Tests\Feature;

use App\Models\{Organization,User,Group,Question,QuestionImportRun,Exam,Student,ExamResult,ExamStat};
use App\Services\QuestionImportRunService;
use App\Support\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{Auth,Cache,DB,Storage};
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FoundationExamJourneyTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): array
    {
        Cache::flush();Tenant::clear();
        $org=Organization::create(['name'=>'Synthetic Exam Organisation','slug'=>'synthetic-exams','domain'=>'synthetic-exams.test','status'=>'active']);
        $user=User::create(['name'=>'Synthetic owner','username'=>'synthetic-owner','email'=>'owner@example.invalid','password'=>'long synthetic password','status'=>'Active']);
        DB::table('organization_users')->insert(['organization_id'=>$org->id,'user_id'=>$user->id,'role'=>'owner','status'=>1,'created_at'=>now(),'updated_at'=>now()]);
        $group=Group::create(['organization_id'=>$org->id,'group_name'=>'Synthetic class']);
        $this->actingAs($user,'web');
        Storage::fake('local');
        return [$org,$user,$group];
    }

    public function test_native_chunk_import_exam_creation_student_attempt_and_result(): void
    {
        [$org,$owner,$group]=$this->owner();
        $csv="groups,question_type,question,option1,option2,correct_option_indices,marks,status,language\nSynthetic class,MCQ,What is two plus two?,4,5,1,2,active,English\n";
        $base='https://synthetic-exams.test';
        $upload=$this->postJson($base.'/question/import/large',['original_name'=>'synthetic.csv','total_bytes'=>strlen($csv),'total_chunks'=>1,'import_mode'=>'create'])->assertOk()->json('upload_id');
        $this->post($base.'/question/import/large/'.$upload.'/chunk',['chunk_index'=>0,'chunk'=>UploadedFile::fake()->createWithContent('synthetic.part',$csv)],['Accept'=>'application/json'])->assertOk();
        $this->postJson($base.'/question/import/large/'.$upload.'/finalize')->assertOk()->assertJson(['status'=>'queued']);
        $run=QuestionImportRun::where('upload_id',$upload)->firstOrFail();
        app(QuestionImportRunService::class)->process($run);
        $this->assertSame('completed',$run->fresh()->status,$run->fresh()->failure_message??'');
        $this->assertSame(1,(int)$run->fresh()->imported_rows,json_encode($run->fresh()->toArray()).' records='.Question::where('organization_id',$org->id)->count().($run->fresh()->error_report_path?Storage::disk('local')->get($run->fresh()->error_report_path):''));
        $question=Question::where('organization_id',$org->id)->firstOrFail();
        $this->post($base.'/exams',[
            'name'=>'Synthetic exam','test_type'=>'full_length','passing_percentage'=>50,'duration'=>30,'attempt_count'=>1,
            'start_date'=>now()->subMinute()->format('Y-m-d H:i:s'),'end_date'=>now()->addDay()->format('Y-m-d H:i:s'),
            'browser_tolerance'=>0,'random_question'=>0,'result_after_finish'=>1,'option_shuffle'=>0,'allow_answer_change'=>1,
            'grouping_mode'=>'none','proctor'=>0,'calculator_allowed'=>0,'groups'=>[$group->id], 'status'=>'Active','mode'=>'Exam',
        ])->assertSessionHasNoErrors()->assertRedirect();
        $exam=Exam::where('organization_id',$org->id)->where('name','Synthetic exam')->firstOrFail();
        $exam->questions()->attach($question->id);
        $student=Student::create(['organization_id'=>$org->id,'name'=>'Synthetic learner','email'=>'learner@example.invalid','password'=>'synthetic-unused-password','status'=>'Active']);
        Auth::forgetGuards();Sanctum::actingAs($student,['*'],'student-api');Tenant::clear();
        $exam->update(['start_date'=>now()->addHour()]);
        $this->postJson($base.'/api/student/exam/start/'.$exam->id)->assertForbidden();
        $exam->update(['start_date'=>now()->subMinute()]);
        $start=$this->postJson($base.'/api/student/exam/start/'.$exam->id)->assertOk()->assertJson(['success'=>true]);
        $resultId=$start->json('examResult.id');
        $questionPayload=$start->json('exam.questions.0');
        $this->assertArrayNotHasKey('correct_option_indices',$questionPayload);
        $statPayload=array_values($start->json('examStats'))[0];
        $this->assertArrayNotHasKey('correct_answer',$statPayload,'Answer keys must not reach a student before submission');
        $this->postJson($base.'/api/student/exam/save-answer',[
            'exam_result_id'=>$resultId,'question_id'=>$question->id,'question_type'=>'multiple_choice_radio','option_selected'=>[1],'answered'=>true,
        ])->assertOk()->assertJson(['success'=>true]);
        $this->postJson($base.'/api/student/exam/submit',['exam_result_id'=>$resultId])->assertOk()->assertJsonPath('result.result','Pass')->assertJsonPath('result.obtained_marks',2);
        $this->assertNotNull(ExamResult::findOrFail($resultId)->end_time);
        $this->assertSame('R',ExamStat::where('exam_result_id',$resultId)->firstOrFail()->ques_status);
        $endedAt=ExamResult::findOrFail($resultId)->end_time->toISOString();
        $exam->update(['result_after_finish'=>false]);
        $resubmitted=$this->postJson($base.'/api/student/exam/submit',['exam_result_id'=>$resultId])->assertOk()->assertJsonPath('result.result_after_finish',false);
        $this->assertArrayNotHasKey('obtained_marks',$resubmitted->json('result'));
        $this->assertArrayNotHasKey('percent',$resubmitted->json('result'));
        $this->assertArrayNotHasKey('result',$resubmitted->json('result'));
        $this->assertSame($endedAt,ExamResult::findOrFail($resultId)->end_time->toISOString());
        $this->assertSame(2.0,(float)ExamResult::findOrFail($resultId)->obtained_marks);
        $this->postJson($base.'/api/student/exam/save-answer',[
            'exam_result_id'=>$resultId,'question_id'=>$question->id,'question_type'=>'multiple_choice_radio','option_selected'=>[2],'answered'=>true,
        ])->assertStatus(409);
        // A later teacher correction must survive a delayed browser submission.
        ExamResult::findOrFail($resultId)->update(['obtained_marks'=>1,'percent'=>50]);
        Auth::forgetGuards();$this->actingAs($student,'student');Tenant::clear();
        $this->postJson($base.'/student/finish-exam',['exam_result_id'=>$resultId])->assertOk()->assertJson(['success'=>true]);
        $this->assertSame(1.0,(float)ExamResult::findOrFail($resultId)->obtained_marks);
        $this->assertSame($endedAt,ExamResult::findOrFail($resultId)->end_time->toISOString());
    }

    public function test_legacy_yes_questions_remain_attemptable_but_no_questions_do_not(): void
    {
        [$org]=$this->owner();
        $exam=Exam::create(['organization_id'=>$org->id,'name'=>'Synthetic legacy exam','slug'=>'synthetic-legacy','status'=>'Active','passing_percentage'=>50,'attempt_count'=>1,'duration'=>30,'mode'=>'Exam','start_date'=>now(),'end_date'=>now()->addDay()]);
        $type=\App\Models\Qtype::firstOrCreate(['type'=>'M'],['question_type'=>'Multiple Choice']);
        $question=Question::create(['organization_id'=>$org->id,'qtype_id'=>$type->id,'question'=>'Synthetic legacy question','status'=>'Yes']);
        $exam->questions()->attach($question->id);
        $this->assertTrue($exam->canAttemptOnline());
        $question->update(['status'=>'No']);$exam->unsetRelation('questions');
        $this->assertFalse($exam->canAttemptOnline());
    }

    public function test_foreign_import_and_revoked_submitter_are_denied(): void
    {
        [$org,$owner]=$this->owner();
        $run=QuestionImportRun::create(['upload_id'=>(string)\Illuminate\Support\Str::uuid(),'organization_id'=>$org->id,'created_by'=>$owner->id,'original_name'=>'synthetic.csv','status'=>'queued','total_bytes'=>10,'total_chunks'=>1]);
        $other=Organization::create(['name'=>'Synthetic Foreign','slug'=>'synthetic-foreign','domain'=>'foreign.test','status'=>'active']);
        $this->getJson('https://foreign.test/question/import/large/'.$run->upload_id.'/status')->assertForbidden();
        DB::table('organization_users')->where('user_id',$owner->id)->update(['status'=>0]);
        app(QuestionImportRunService::class)->process($run);
        $this->assertSame('failed',$run->fresh()->status);
        $this->assertSame(0,Question::where('organization_id',$org->id)->count());
    }

    public function test_manual_question_authoring_keeps_language_in_translation_table(): void
    {
        [$org,,$group]=$this->owner();
        $type=\App\Models\Qtype::firstOrCreate(['type'=>'M'],['question_type'=>'Multiple Choice']);
        $language=\App\Models\Language::create(['organization_id'=>$org->id,'name'=>'English','code'=>'en','is_enabled'=>true]);
        $this->post('https://synthetic-exams.test/questions',[
            'qtype_id'=>$type->id,'language_id'=>$language->id,'group_ids'=>[$group->id],
            'question'=>'Synthetic authored question','option1'=>'4','option2'=>'5','correct_answers'=>[1],'marks'=>2,'status'=>'Yes',
        ])->assertSessionHasNoErrors()->assertSessionHas('success')->assertRedirect();
        $question=Question::where('organization_id',$org->id)->where('question','Synthetic authored question')->firstOrFail();
        $this->assertSame('Synthetic authored question',$question->langs()->where('language_id',$language->id)->firstOrFail()->question);
    }

    public function test_api_subjective_submission_waits_for_manual_grading(): void
    {
        [$org]=$this->owner();
        $type=\App\Models\Qtype::firstOrCreate(['type'=>'S'],['question_type'=>'Subjective']);
        $question=Question::create(['organization_id'=>$org->id,'qtype_id'=>$type->id,'question'=>'Explain the synthetic problem','marks'=>2,'status'=>'Yes']);
        $exam=Exam::create(['organization_id'=>$org->id,'name'=>'Synthetic subjective exam','slug'=>'synthetic-subjective','result_after_finish'=>true,'status'=>'Active','passing_percentage'=>50,'attempt_count'=>1,'duration'=>30,'mode'=>'Exam','start_date'=>now()->subMinute(),'end_date'=>now()->addDay()]);
        $exam->questions()->attach($question->id);
        $student=Student::create(['organization_id'=>$org->id,'name'=>'Synthetic learner','email'=>'subjective@example.invalid','password'=>'synthetic-unused-password','status'=>'Active']);
        Auth::forgetGuards();Sanctum::actingAs($student,['*'],'student-api');Tenant::clear();
        $start=$this->postJson('https://synthetic-exams.test/api/student/exam/start/'.$exam->id)->assertOk();
        $id=$start->json('examResult.id');
        $this->postJson('https://synthetic-exams.test/api/student/exam/save-answer',['exam_result_id'=>$id,'question_id'=>$question->id,'question_type'=>'subjective','option_selected'=>'Synthetic explanation','answered'=>true])->assertOk();
        $this->postJson('https://synthetic-exams.test/api/student/exam/submit',['exam_result_id'=>$id])->assertOk()->assertJsonPath('result.result','Pending');
        $this->assertSame('P',ExamStat::where('exam_result_id',$id)->firstOrFail()->ques_status);
    }
}

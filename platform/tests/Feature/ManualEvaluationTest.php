<?php
namespace Tests\Feature;
use App\Models\{Organization,User,Student,Exam,ExamResult,ExamStat,Question,Qtype,SaasPlan};
use App\Support\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Cache,DB,Http};
use Tests\TestCase;

class ManualEvaluationTest extends TestCase
{
    use RefreshDatabase;
    private function fixture(): array
    {
        Cache::flush();Tenant::clear();
        $plan=SaasPlan::create(['name'=>'Synthetic grading','slug'=>'synthetic-grading','price'=>0,'billing_cycle'=>'monthly','status'=>true,'features'=>['reports'=>true,'ai_subjective_analysis'=>true,'ai_settings'=>true]]);
        $org=Organization::create(['name'=>'Synthetic grading','slug'=>'synthetic-grading','domain'=>'grading.test','status'=>'active','saas_plan_id'=>$plan->id]);
        $user=User::create(['name'=>'Synthetic teacher','username'=>'synthetic-teacher','email'=>'teacher@example.invalid','password'=>'unused','status'=>'Active']);
        DB::table('organization_users')->insert(['organization_id'=>$org->id,'user_id'=>$user->id,'role'=>'owner','status'=>1,'created_at'=>now(),'updated_at'=>now()]);
        $this->actingAs($user,'web');
        $student=Student::create(['organization_id'=>$org->id,'name'=>'Synthetic learner','email'=>'grading@example.invalid','password'=>'unused','status'=>'Active']);
        $exam=Exam::create(['organization_id'=>$org->id,'name'=>'Synthetic grading','slug'=>'synthetic-grading','status'=>'Active','mode'=>'Exam','duration'=>30,'passing_percentage'=>50,'attempt_count'=>1]);
        $result=ExamResult::create(['organization_id'=>$org->id,'exam_id'=>$exam->id,'student_id'=>$student->id,'start_time'=>now()->subMinute(),'end_time'=>now(),'total_test_time'=>30,'total_question'=>2,'total_marks'=>4,'result'=>'Pending']);
        $stats=[];
        $type=Qtype::firstOrCreate(['type'=>'S'],['question_type'=>'Subjective']);
        foreach([1,2] as $number) {
            $question=Question::create(['organization_id'=>$org->id,'qtype_id'=>$type->id,'question'=>'Synthetic explanation '.$number,'status'=>'Yes']);
            $stats[]=ExamStat::create(['organization_id'=>$org->id,'exam_id'=>$exam->id,'exam_result_id'=>$result->id,'student_id'=>$student->id,'question_id'=>$question->id,'ques_no'=>$number,'marks'=>2,'ques_status'=>'P']);
        }
        return [$result,$stats];
    }
    private function grade($result,array $marks)
    {
        return $this->postJson('https://grading.test/results/'.$result->id.'/save-evaluation',['marks'=>$marks]);
    }
    public function test_partial_marking_remains_pending_until_all_answers_are_marked(): void
    {
        [$result,$stats]=$this->fixture();
        $this->grade($result,[$stats[0]->id=>2])->assertRedirect();
        $this->assertSame('Pending',$result->fresh()->result);
        $this->grade($result,[$stats[1]->id=>1])->assertRedirect();
        $this->assertSame('Pass',$result->fresh()->result);
        $this->assertEquals(3,$result->fresh()->obtained_marks);
    }
    public function test_invalid_batch_does_not_partially_apply_marks(): void
    {
        [$result,$stats]=$this->fixture();
        $this->grade($result,[$stats[0]->id=>1,$stats[1]->id=>3])->assertUnprocessable();
        $this->assertSame('P',$stats[0]->fresh()->ques_status);
        $this->assertSame('P',$stats[1]->fresh()->ques_status);
    }
    public function test_open_attempt_cannot_be_marked(): void
    {
        [$result,$stats]=$this->fixture();$result->update(['end_time'=>null]);
        $this->grade($result,[$stats[0]->id=>1])->assertStatus(409);
    }
    public function test_unknown_or_already_marked_answer_is_rejected(): void
    {
        [$result,$stats]=$this->fixture();
        $this->grade($result,[999999=>1])->assertUnprocessable();
        $this->grade($result,[$stats[0]->id=>1])->assertRedirect();
        $this->grade($result,[$stats[0]->id=>2])->assertUnprocessable();
        $this->assertEquals(1,$stats[0]->fresh()->marks_obtained);
    }
    public function test_answer_from_another_attempt_cannot_be_injected_into_batch(): void
    {
        [$result,$stats]=$this->fixture();
        $other=$result->replicate();$other->save();
        $answer=$stats[0]->replicate();$answer->exam_result_id=$other->id;$answer->save();
        $this->grade($result,[$stats[0]->id=>1,$answer->id=>2])->assertUnprocessable();
        $this->assertSame('P',$stats[0]->fresh()->ques_status);
        $this->assertSame('P',$answer->fresh()->ques_status);
    }
    public function test_foreign_organisation_result_is_not_readable_or_markable(): void
    {
        [$result,$stats]=$this->fixture();
        $foreign=Organization::create(['name'=>'Synthetic other grading','slug'=>'other-grading','domain'=>'other-grading.test','status'=>'active']);
        $exam=$result->exam->replicate();$exam->organization_id=$foreign->id;$exam->slug='other-grading';$exam->save();
        $student=$result->student->replicate();$student->organization_id=$foreign->id;$student->email='other-grading@example.invalid';$student->save();
        $other=$result->replicate();$other->organization_id=$foreign->id;$other->exam_id=$exam->id;$other->student_id=$student->id;$other->save();
        $answer=$stats[0]->replicate();$answer->organization_id=$foreign->id;$answer->exam_id=$exam->id;$answer->student_id=$student->id;$answer->exam_result_id=$other->id;$answer->save();
        $this->get('https://grading.test/results/'.$other->id.'/evaluate')->assertNotFound();
        $this->grade($other,[$answer->id=>2])->assertNotFound();
        $this->assertSame('P',$answer->fresh()->ques_status);
    }
    public function test_negative_and_non_numeric_marks_are_rejected_but_fractional_marks_work(): void
    {
        [$result,$stats]=$this->fixture();
        foreach([-0.5,'invalid',null] as $marks)$this->grade($result,[$stats[0]->id=>$marks])->assertUnprocessable();
        $this->grade($result,[$stats[0]->id=>1.5,$stats[1]->id=>0])->assertRedirect();
        $this->assertSame('Fail',$result->fresh()->result);
        $this->assertEquals(1.5,$result->fresh()->obtained_marks);
    }
    public function test_ai_metadata_migration_preserves_existing_evidence_on_retry_and_rollback(): void
    {
        [,$stats]=$this->fixture();
        DB::table('exam_stats')->where('id',$stats[0]->id)->update(['ai_score'=>1.5,'ai_providers_used'=>'Synthetic provider']);
        $migration=require database_path('migrations/2026_10_08_000002_add_ai_assessment_metadata_to_exam_stats.php');
        $migration->up();$migration->down();
        $row=DB::table('exam_stats')->where('id',$stats[0]->id)->first();
        $this->assertEquals(1.5,$row->ai_score);
        $this->assertSame('Synthetic provider',$row->ai_providers_used);
    }
    public function test_ai_bulk_excludes_open_and_manually_marked_answers(): void
    {
        [$result,$stats]=$this->fixture();
        DB::table('exam_stats')->where('exam_result_id',$result->id)->update(['answer'=>'Synthetic explanation']);
        $result->update(['end_time'=>null]);
        $this->postJson('https://grading.test/ai/subjective/bulk-assess')->assertOk()->assertJson(['total'=>0]);
        $result->update(['end_time'=>now()]);
        $this->grade($result,[$stats[0]->id=>2,$stats[1]->id=>2])->assertRedirect();
        $this->postJson('https://grading.test/ai/subjective/bulk-assess')->assertOk()->assertJson(['total'=>0]);
        $this->assertEquals(4,$result->fresh()->obtained_marks);
    }
    public function test_intervening_teacher_grade_survives_simulated_ai_response(): void
    {
        [$result,$stats]=$this->fixture();
        \App\Models\Configuration::create(['organization_id'=>$result->organization_id,'openai_api_key'=>'synthetic-never-sent','openai_model'=>'synthetic-model','ai_provider'=>'openai']);
        DB::table('exam_stats')->where('id',$stats[0]->id)->update(['answer'=>'Synthetic explanation']);
        Http::preventStrayRequests();
        Http::fake(['https://api.openai.com/*'=>function()use($result,$stats){
            DB::table('exam_stats')->where('id',$stats[0]->id)->update(['marks_obtained'=>1,'ques_status'=>'R']);
            $result->update(['obtained_marks'=>1]);
            return Http::response(['choices'=>[['message'=>['content'=>'{"score":2}']]]]);
        }]);
        $this->postJson('https://grading.test/ai/subjective/bulk-assess')->assertStatus(409);
        Http::assertSentCount(1);
        $this->assertEquals(1,$stats[0]->fresh()->marks_obtained);
        $this->assertEquals(1,$result->fresh()->obtained_marks);
        $this->assertFalse((bool)$stats[0]->fresh()->ai_assessed);
    }
    public function test_simulated_ai_score_is_bounded_and_requires_teacher_publication(): void
    {
        [$result,$stats]=$this->fixture();
        \App\Models\Configuration::create(['organization_id'=>$result->organization_id,'openai_api_key'=>'synthetic-never-sent','openai_model'=>'synthetic-model','ai_provider'=>'openai']);
        DB::table('exam_stats')->where('id',$stats[0]->id)->update(['answer'=>'Synthetic explanation']);
        Http::preventStrayRequests();
        Http::fake(['https://api.openai.com/*'=>Http::response(['choices'=>[['message'=>['content'=>'{"score":999}']]]])]);
        $this->postJson('https://grading.test/ai/subjective/bulk-assess')->assertOk()->assertJson(['total'=>1]);
        Http::assertSentCount(1);
        $this->assertEquals(2,$stats[0]->fresh()->marks_obtained);
        $this->assertEquals(2,$stats[0]->fresh()->ai_score);
        $this->assertSame('P',$stats[0]->fresh()->ques_status);
        $this->assertSame('Pending',$result->fresh()->result);
        $this->postJson('https://grading.test/ai/subjective/bulk-assess')->assertOk()->assertJson(['total'=>0]);
        Http::assertSentCount(1);
        $this->grade($result,[$stats[0]->id=>1.5,$stats[1]->id=>1])->assertRedirect();
        $this->assertSame('Pass',$result->fresh()->result);
        $this->assertEquals(2.5,$result->fresh()->obtained_marks);
    }
}

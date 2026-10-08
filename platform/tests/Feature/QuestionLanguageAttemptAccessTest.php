<?php

namespace Tests\Feature;

use App\Models\{Organization,Student,Exam,Question,Language,QuestionLang,ExamResult,ExamStat,Qtype,SaasPlan};
use App\Support\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class QuestionLanguageAttemptAccessTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        Cache::flush();Tenant::clear();
        $plan=SaasPlan::create(['name'=>'Synthetic language plan','slug'=>'synthetic-language-plan','features'=>['guest_exams'=>true],'price'=>0,'billing_cycle'=>'monthly','status'=>true]);
        $org=Organization::create(['name'=>'Synthetic language tenant','slug'=>'synthetic-language','domain'=>'language.test','status'=>'active','saas_plan_id'=>$plan->id]);
        $student=Student::create(['organization_id'=>$org->id,'name'=>'Synthetic learner','email'=>'language@example.invalid','password'=>'synthetic password','status'=>'Active']);
        $exam=Exam::create(['organization_id'=>$org->id,'name'=>'Synthetic language exam','status'=>'Active','duration'=>30,'passing_percentage'=>50,'attempt_count'=>1,'mode'=>'Exam']);
        $type=Qtype::firstOrCreate(['type'=>'M'],['question_type'=>'Multiple Choice']);
        $question=Question::create(['organization_id'=>$org->id,'qtype_id'=>$type->id,'question'=>'Synthetic original question','explanation'=>'PRIVATE ORIGINAL ANSWER','option1'=>'4','option2'=>'5','si_answer1'=>1,'marks'=>2,'status'=>'Yes']);
        $language=Language::create(['organization_id'=>$org->id,'name'=>'English','code'=>'en','is_enabled'=>true]);
        QuestionLang::create(['question_id'=>$question->id,'language_id'=>$language->id,'question'=>'Synthetic translated question','explanation'=>'PRIVATE TRANSLATED ANSWER','option1'=>'Four','option2'=>'Five','si_answer1'=>1]);
        return [$org,$student,$exam,$question,$language];
    }

    private function attempt($org,$exam,$question,?int $studentId,?string $guestId=null): ExamResult
    {
        $result=ExamResult::create(['organization_id'=>$org->id,'exam_id'=>$exam->id,'student_id'=>$studentId,'guest_id'=>$guestId,'start_time'=>now(),'total_test_time'=>30,'total_question'=>1,'total_marks'=>2]);
        ExamStat::create(['organization_id'=>$org->id,'exam_id'=>$exam->id,'exam_result_id'=>$result->id,'student_id'=>$studentId,'guest_id'=>$guestId,'question_id'=>$question->id,'ques_no'=>1,'marks'=>2]);
        return $result;
    }

    public function test_student_translation_requires_own_open_attempt_and_withholds_explanations(): void
    {
        [$org,$student,$exam,$question,$language]=$this->fixture();
        $this->actingAs($student,'student');
        $url='https://language.test/questions/'.$question->id.'/language/'.$language->id;
        $this->getJson($url)->assertNotFound();
        $result=$this->attempt($org,$exam,$question,$student->id);
        $response=$this->getJson($url)->assertOk()->assertJsonPath('data.question','Synthetic translated question');
        $this->assertArrayNotHasKey('explanation',$response->json('data'));
        $this->assertStringNotContainsString('PRIVATE',$response->getContent());
        $unassigned=$question->replicate(['question_code']);$unassigned->save();
        $this->getJson('https://language.test/questions/'.$unassigned->id.'/language/'.$language->id)->assertNotFound();
        $other=Organization::create(['name'=>'Synthetic other language tenant','slug'=>'other-language','domain'=>'other-language.test','status'=>'active']);
        $foreignLanguage=Language::create(['organization_id'=>$other->id,'name'=>'Foreign language','code'=>'fr','is_enabled'=>true]);
        $this->getJson('https://language.test/questions/'.$question->id.'/language/'.$foreignLanguage->id)->assertNotFound();
        $result->update(['student_id'=>null]);
        $this->getJson($url)->assertNotFound();
        $result->update(['student_id'=>$student->id,'end_time'=>now()]);
        $this->getJson($url)->assertNotFound();
    }

    public function test_guest_translation_requires_matching_open_guest_attempt(): void
    {
        [$org,$student,$exam,$question,$language]=$this->fixture();
        $guest='11111111-1111-4111-8111-111111111111';
        $url='https://language.test/guest/questions/'.$question->id.'/language/'.$language->id;
        $this->getJson($url)->assertNotFound();
        $result=$this->attempt($org,$exam,$question,null,$guest);
        $this->withSession(['guest_id'=>'22222222-2222-4222-8222-222222222222'])->getJson($url)->assertNotFound();
        $response=$this->withSession(['guest_id'=>$guest])->getJson($url)->assertOk();
        $this->assertArrayNotHasKey('explanation',$response->json('data'));
        $result->update(['end_time'=>now()]);
        $this->getJson($url)->assertNotFound();
    }
}

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
        $question=Question::create(['organization_id'=>$org->id,'qtype_id'=>$type->id,'question'=>'Synthetic original question','explanation'=>'PRIVATE ORIGINAL ANSWER','option1'=>'4','option2'=>'5','correct_option_indices'=>[1],'marks'=>2,'status'=>'Yes']);
        $exam->questions()->attach($question->id);
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

    public function test_guest_requests_without_identity_cannot_change_a_registered_student_attempt(): void
    {
        [$org,$student,$exam,$question]=$this->fixture();
        $result=$this->attempt($org,$exam,$question,$student->id);
        $payload=['exam_result_id'=>$result->id,'question_id'=>$question->id,'question_type'=>'multiple_choice_radio','option_selected'=>[1],'answered'=>true];
        $this->postJson('https://language.test/guest/student/save-answer',$payload)->assertNotFound();
        $this->postJson('https://language.test/guest/student/finish-exam',['exam_result_id'=>$result->id])->assertNotFound();
        $this->get('https://language.test/guest/student/exam-feedback?exam_result_id='.$result->id)->assertNotFound();
        $this->assertNull($result->fresh()->end_time);
        $this->assertFalse((bool)ExamStat::where('exam_result_id',$result->id)->sole()->answered);
    }

    public function test_guest_can_save_and_finish_only_its_own_scoped_attempt(): void
    {
        [$org,$student,$exam,$question]=$this->fixture();
        $guest='11111111-1111-4111-8111-111111111111';
        $result=$this->attempt($org,$exam,$question,null,$guest);
        $payload=['exam_result_id'=>$result->id,'question_id'=>$question->id,'question_type'=>'multiple_choice_radio','option_selected'=>[1],'answered'=>true];
        $this->withSession(['guest_id'=>'22222222-2222-4222-8222-222222222222'])->postJson('https://language.test/guest/student/save-answer',$payload)->assertNotFound();
        $foreign=Organization::create(['name'=>'Synthetic foreign guest tenant','slug'=>'foreign-guest','domain'=>'foreign-guest.test','status'=>'active']);
        $otherExam=$exam->replicate();$otherExam->organization_id=$foreign->id;$otherExam->save();
        $otherQuestion=$question->replicate(['question_code']);$otherQuestion->organization_id=$foreign->id;$otherQuestion->save();
        $otherResult=$this->attempt($foreign,$otherExam,$otherQuestion,null,$guest);
        $this->withSession(['guest_id'=>$guest])->postJson('https://language.test/guest/student/save-answer',array_merge($payload,['exam_result_id'=>$otherResult->id,'question_id'=>$otherQuestion->id]))->assertNotFound();
        $this->postJson('https://language.test/guest/student/finish-exam',['exam_result_id'=>$otherResult->id])->assertNotFound();
        $this->assertNull($otherResult->fresh()->end_time);
        $this->withSession(['guest_id'=>$guest])->postJson('https://language.test/guest/student/save-answer',$payload)->assertOk()->assertJson(['success'=>true]);
        $this->postJson('https://language.test/guest/student/finish-exam',['exam_result_id'=>$result->id])->assertOk()->assertJson(['success'=>true]);
        $this->assertNotNull($result->fresh()->end_time);
        $this->assertSame('Pass',$result->fresh()->result);
        $this->assertEquals(2,$result->fresh()->obtained_marks);
        $this->postJson('https://language.test/guest/student/save-answer',$payload)->assertStatus(409);
    }

    public function test_guest_start_requires_a_completed_order_and_active_package(): void
    {
        [$org,$student,$exam,$question]=$this->fixture();
        $guest='11111111-1111-4111-8111-111111111111';
        $package=\App\Models\Package::create(['organization_id'=>$org->id,'name'=>'Synthetic guest package','slug'=>'guest-package','expiry_days'=>30,'amount'=>10,'package_type'=>'paid','status'=>true]);
        $exam->packages()->attach($package->id);
        $order=\App\Models\Order::create(['organization_id'=>$org->id,'guest_id'=>$guest,'total'=>10,'payment_method'=>'synthetic','payment_status'=>'Pending','status'=>'pending']);
        \App\Models\OrderItem::create(['order_id'=>$order->id,'package_id'=>$package->id,'name'=>'Synthetic guest package','price'=>10,'quantity'=>1]);
        $url='https://language.test/guest/exam/start/'.$exam->id;
        $this->withSession(['guest_id'=>$guest])->get($url)->assertRedirect();
        $this->assertSame(0,ExamResult::count());
        $order->update(['status'=>'completed','payment_status'=>'Completed']);
        $package->update(['status'=>false]);
        $this->get($url)->assertRedirect();
        $this->assertSame(0,ExamResult::count());
        $package->update(['status'=>true]);
        $this->get($url)->assertOk();
        $result=ExamResult::where('guest_id',$guest)->sole();
        $this->assertNull($result->student_id);
        $this->assertSame((int)$org->id,(int)$result->organization_id);
        $this->assertSame($guest,ExamStat::where('exam_result_id',$result->id)->sole()->guest_id);
        $this->postJson('https://language.test/guest/student/save-answer',['exam_result_id'=>$result->id,'question_id'=>$question->id,'question_type'=>'multiple_choice_radio','option_selected'=>[1],'answered'=>true])->assertOk();
        $this->postJson('https://language.test/guest/student/finish-exam',['exam_result_id'=>$result->id])->assertOk();
        $this->assertSame('Pass',$result->fresh()->result);
        $this->assertEquals(2,$result->fresh()->obtained_marks);
    }

    public function test_free_guest_checkout_creates_a_scoped_order_and_retry_keeps_one_activation(): void
    {
        [$org,$student,$exam]=$this->fixture();
        $package=\App\Models\Package::create(['organization_id'=>$org->id,'name'=>'Synthetic free course','slug'=>'free-course','expiry_days'=>30,'amount'=>0,'package_type'=>'free','status'=>true]);
        $exam->packages()->attach($package->id);
        $payload=['id'=>$package->id,'exam'=>$exam->id];
        $response=$this->postJson('https://language.test/checkout/enroll/exam',$payload)->assertOk();
        $order=\App\Models\Order::sole();
        $this->assertSame((int)$org->id,(int)$order->organization_id);
        $this->assertSame('completed',$order->status);
        $this->assertSame('Completed',$order->payment_status);
        $this->assertNotEmpty($order->guest_id);
        $this->assertNull($order->student_id);
        $this->followingRedirects()->get($response->json('redirectUrl'))->assertOk();
        $this->postJson('https://language.test/checkout/enroll/exam',$payload)->assertOk();
        $this->assertSame(1,\App\Models\Order::count());
    }

    public function test_direct_student_entry_requires_own_completed_scoped_active_package_order(): void
    {
        [$org,$student,$exam]=$this->fixture();
        $package=\App\Models\Package::create(['organization_id'=>$org->id,'name'=>'Synthetic course','slug'=>'direct-course','expiry_days'=>30,'amount'=>10,'package_type'=>'paid','status'=>true]);
        $exam->packages()->attach($package->id);
        $this->actingAs($student,'student');
        $this->get('https://language.test/exam/instructions/'.$exam->id)->assertForbidden();
        $this->get('https://language.test/exam/start/'.$exam->id)->assertForbidden();
        \Laravel\Sanctum\Sanctum::actingAs($student,['*'],'student-api');
        $url='https://language.test/api/student/exam/start/'.$exam->id;
        $this->postJson($url)->assertForbidden();
        $order=\App\Models\Order::create(['organization_id'=>$org->id,'student_id'=>$student->id,'total'=>10,'payment_method'=>'synthetic','status'=>'pending']);
        \App\Models\OrderItem::create(['order_id'=>$order->id,'package_id'=>$package->id,'name'=>'Synthetic course','price'=>10,'quantity'=>1]);
        $this->postJson($url)->assertForbidden();
        $order->update(['status'=>'completed','organization_id'=>null]);
        $this->postJson($url)->assertForbidden();
        $order->update(['organization_id'=>$org->id,'student_id'=>null,'guest_id'=>'synthetic-other-guest']);
        $this->postJson($url)->assertForbidden();
        $order->update(['student_id'=>$student->id,'guest_id'=>null]);
        $package->update(['status'=>false]);
        $this->postJson($url)->assertForbidden();
        $this->assertSame(0,ExamResult::count());
        $package->update(['status'=>true]);
        $this->postJson($url)->assertOk();
        $this->get('https://language.test/exam/instructions/'.$exam->id)->assertOk();
        $this->get('https://language.test/exam/start/'.$exam->id)->assertOk();
        $package->update(['status'=>false]);
        $this->postJson($url)->assertForbidden();
        $this->get('https://language.test/exam/start/'.$exam->id)->assertForbidden();
        $this->assertSame(1,ExamResult::count());
    }

    public function test_api_practice_entry_requires_the_creating_student(): void
    {
        [$org,$student,$exam]=$this->fixture();
        $other=Student::create(['organization_id'=>$org->id,'name'=>'Synthetic practice owner','email'=>'practice-owner@example.invalid','password'=>'synthetic password','status'=>'Active']);
        $exam->update(['is_student_practice'=>true,'created_by_student_id'=>$other->id]);
        \Laravel\Sanctum\Sanctum::actingAs($student,['*'],'student-api');
        $url='https://language.test/api/student/exam/start/'.$exam->id;
        $this->postJson($url)->assertNotFound();
        $this->assertSame(0,ExamResult::count());
        \Laravel\Sanctum\Sanctum::actingAs($other,['*'],'student-api');
        $this->postJson($url)->assertOk();
        $this->assertSame((int)$other->id,(int)ExamResult::sole()->student_id);
    }

    public function test_registered_student_activation_uses_only_completed_own_orders(): void
    {
        [$org,$student,$exam]=$this->fixture();
        Organization::where('slug','examelite')->update(['slug'=>'synthetic-default-platform']);
        $org->update(['slug'=>'examelite','settings'=>['is_primary_platform'=>true]]);
        $org->plan->update(['features'=>['guest_exams'=>true,'paid_packages'=>true]]);
        Cache::flush();Tenant::clear();
        $this->actingAs($student,'student');
        $package=\App\Models\Package::create(['organization_id'=>$org->id,'name'=>'Synthetic paid course','slug'=>'student-course','expiry_days'=>30,'amount'=>10,'package_type'=>'paid','status'=>true]);
        $exam->packages()->attach($package->id);
        $order=\App\Models\Order::create(['organization_id'=>$org->id,'student_id'=>$student->id,'total'=>10,'payment_method'=>'synthetic','payment_status'=>'Pending','status'=>'pending']);
        \App\Models\OrderItem::create(['order_id'=>$order->id,'package_id'=>$package->id,'name'=>'Synthetic paid course','price'=>10,'quantity'=>1]);
        $payload=['id'=>$package->id,'exam'=>$exam->id];
        $this->postJson('https://language.test/checkout/enroll/exam',$payload)->assertStatus(409);
        $package->update(['package_type'=>'free','amount'=>0]);
        $response=$this->postJson('https://language.test/checkout/enroll/exam',$payload)->assertOk();
        $completed=\App\Models\Order::where('status','completed')->sole();
        $this->assertSame((int)$student->id,(int)$completed->student_id);
        $this->assertSame((int)$org->id,(int)$completed->organization_id);
        $this->assertNull($completed->guest_id);
        $this->followingRedirects()->get($response->json('redirectUrl'))->assertOk();
        $this->postJson('https://language.test/checkout/enroll/exam',$payload)->assertOk();
        $this->assertSame(1,\App\Models\Order::where('status','completed')->count());
    }
}

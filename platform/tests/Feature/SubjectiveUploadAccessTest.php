<?php
namespace Tests\Feature;
use App\Models\{Organization,SaasPlan,Student,Exam,Question,Qtype,ExamResult,ExamStat};
use App\Support\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{Cache,Storage};
use Tests\TestCase;
class SubjectiveUploadAccessTest extends TestCase
{
    use RefreshDatabase;
    private function fixture(): array
    {
        Cache::flush();Tenant::clear();Storage::fake('local');Storage::fake('public');
        $plan=SaasPlan::create(['name'=>'Synthetic uploads','slug'=>'synthetic-uploads','price'=>0,'billing_cycle'=>'monthly','status'=>true,'features'=>['ai_subjective_analysis'=>true]]);
        $org=Organization::create(['name'=>'Synthetic uploads','slug'=>'synthetic-uploads','domain'=>'uploads.test','status'=>'active','saas_plan_id'=>$plan->id]);
        $student=Student::create(['organization_id'=>$org->id,'name'=>'Synthetic learner','email'=>'upload@example.invalid','password'=>'unused','status'=>'Active']);
        $exam=Exam::create(['organization_id'=>$org->id,'name'=>'Synthetic upload exam','slug'=>'upload-exam','status'=>'Active','mode'=>'Exam','duration'=>30,'passing_percentage'=>50,'attempt_count'=>1]);
        $type=Qtype::firstOrCreate(['type'=>'S'],['question_type'=>'Subjective']);
        $question=Question::create(['organization_id'=>$org->id,'qtype_id'=>$type->id,'question'=>'Synthetic explanation','status'=>'Yes']);
        $result=ExamResult::create(['organization_id'=>$org->id,'exam_id'=>$exam->id,'student_id'=>$student->id,'start_time'=>now(),'total_test_time'=>30,'total_question'=>1,'total_marks'=>2]);
        $stat=ExamStat::create(['organization_id'=>$org->id,'exam_id'=>$exam->id,'exam_result_id'=>$result->id,'student_id'=>$student->id,'question_id'=>$question->id,'ques_no'=>1,'marks'=>2]);
        $this->actingAs($student,'student');
        return [$org,$student,$question,$result,$stat];
    }
    private function upload($question,$result)
    {
        return $this->post('https://uploads.test/subjective-upload',[
            'question_id'=>$question->id,'exam_result_id'=>$result->id,
            'answer_file'=>UploadedFile::fake()->createWithContent('answer.txt','Synthetic explanation'),
        ],['Accept'=>'application/json']);
    }
    public function test_owned_open_answer_is_saved_privately_without_public_path(): void
    {
        [$org,,$question,$result,$stat]=$this->fixture();
        $response=$this->upload($question,$result)->assertOk()->assertJson(['success'=>true]);
        $this->assertArrayNotHasKey('path',$response->json());
        $path=$stat->fresh()->uploaded_answer_path;
        $this->assertStringStartsWith("student_answers_private/$org->id/$result->id/",$path);
        Storage::disk('local')->assertExists($path);
        $this->assertSame([],Storage::disk('public')->allFiles());
        $reader=new \ReflectionMethod(\App\Http\Controllers\AISubjectiveAssessmentController::class,'extractText');
        $controller=app(\App\Http\Controllers\AISubjectiveAssessmentController::class);
        $this->assertSame('Synthetic explanation',$reader->invoke($controller,$path));
        $this->assertSame('',$reader->invoke($controller,'student_answers_private/../../private.txt'));
    }
    public function test_submitted_result_cannot_receive_late_file(): void
    {
        [,,$question,$result,$stat]=$this->fixture();$result->update(['end_time'=>now()]);
        $this->upload($question,$result)->assertStatus(409);
        $this->assertNull($stat->fresh()->uploaded_answer_path);
        $this->assertSame([],Storage::disk('local')->allFiles());
    }
    public function test_another_student_result_is_rejected_before_storage(): void
    {
        [$org,,$question,$result,$stat]=$this->fixture();
        $other=Student::create(['organization_id'=>$org->id,'name'=>'Synthetic other','email'=>'other-upload@example.invalid','password'=>'unused','status'=>'Active']);
        $this->actingAs($other,'student');
        $this->upload($question,$result)->assertForbidden();
        $this->assertNull($stat->fresh()->uploaded_answer_path);
        $this->assertSame([],Storage::disk('local')->allFiles());
    }
    public function test_question_outside_attempt_is_rejected_before_storage(): void
    {
        [$org,,$question,$result,$stat]=$this->fixture();
        $unassigned=Question::create(['organization_id'=>$org->id,'qtype_id'=>$question->qtype_id,'question'=>'Synthetic unassigned','status'=>'Yes']);
        $this->upload($unassigned,$result)->assertNotFound();
        $this->assertNull($stat->fresh()->uploaded_answer_path);
        $this->assertSame([],Storage::disk('local')->allFiles());
    }
    private function extraction($question,$result)
    {
        return $this->post('https://uploads.test/student/answer-extraction',[
            'question_id'=>$question->id,'exam_result_id'=>$result->id,
            'file'=>UploadedFile::fake()->createWithContent('answer.txt','Synthetic extracted text'),
        ],['Accept'=>'application/json']);
    }
    public function test_owned_answer_text_extraction_is_private_and_does_not_save_evidence(): void
    {
        [,,$question,$result]=$this->fixture();
        $this->extraction($question,$result)->assertOk()->assertJson(['success'=>true,'text'=>'Synthetic extracted text'])
            ->assertHeader('Cache-Control','no-store, private');
        $this->assertSame([],Storage::disk('local')->allFiles());
    }
    public function test_foreign_result_never_reaches_the_extractor(): void
    {
        [$org,,$question,$result]=$this->fixture();
        $other=Student::create(['organization_id'=>$org->id,'name'=>'Synthetic other','email'=>'extract-other@example.invalid','password'=>'unused','status'=>'Active']);
        $this->actingAs($other,'student');
        $this->mock(\App\Services\StudentAnswerTextExtractor::class)->shouldNotReceive('extract');
        $this->extraction($question,$result)->assertForbidden();
    }
    public function test_submission_during_extraction_is_rechecked_before_returning_text(): void
    {
        [,,$question,$result]=$this->fixture();
        $this->mock(\App\Services\StudentAnswerTextExtractor::class)->shouldReceive('extract')->once()
            ->andReturnUsing(function()use($result){$result->update(['end_time'=>now()]);return 'Synthetic text';});
        $this->extraction($question,$result)->assertStatus(409);
    }
    public function test_assessment_reader_uses_bounded_docx_extraction(): void
    {
        [$org,,,$result]=$this->fixture();
        $path='student_answers_private/'.$org->id.'/'.$result->id.'/Synthetic.docx';
        Storage::disk('local')->put($path,'');
        $zip=new \ZipArchive;
        $this->assertTrue($zip->open(Storage::disk('local')->path($path),\ZipArchive::OVERWRITE)===true);
        $zip->addFromString('word/document.xml','<w:document><w:p><w:r><w:t>Synthetic retained document</w:t></w:r></w:p></w:document>');
        $zip->close();
        $reader=new \ReflectionMethod(\App\Http\Controllers\AISubjectiveAssessmentController::class,'extractText');
        $controller=app(\App\Http\Controllers\AISubjectiveAssessmentController::class);
        $this->assertSame('Synthetic retained document',$reader->invoke($controller,$path));
        $text='student_answers_private/'.$org->id.'/'.$result->id.'/Oversized.txt';
        Storage::disk('local')->put($text,str_repeat('x',524289));
        $this->assertSame('',$reader->invoke($controller,$text));
    }
}

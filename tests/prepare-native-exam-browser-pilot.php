<?php
// Reuses the CLI-only, fixed local SQLite and synthetic tenant guards.
require __DIR__.'/prepare-native-browser-pilot.php';
try {
if(in_array('--verify-result',$argv,true)) {
    $db->statement('PRAGMA query_only = ON');
    $fixture=json_decode(file_get_contents($base.'/exam-browser-fixture.json'),true,512,JSON_THROW_ON_ERROR);
    $result=App\Models\ExamResult::where(['organization_id'=>2,'exam_id'=>$fixture['exam'],'student_id'=>$fixture['student']])->sole();
    $stat=App\Models\ExamStat::where(['exam_result_id'=>$result->id,'student_id'=>$fixture['student'],'question_id'=>$fixture['question']])->sole();
    if(!$result->end_time || (float)$result->obtained_marks!==2.0 || $stat->ques_status!=='R')throw new RuntimeException('Submission not scored');
    echo "PASS: synthetic browser submission persisted its end time and correct two-mark score.\n";
    exit(0);
}
$fixture=$db->transaction(function()use($base){
    $credentials=json_decode(file_get_contents($base.'/local-pilot-credentials.json'),true,512,JSON_THROW_ON_ERROR);
    $suffix=bin2hex(random_bytes(8));
    $student=App\Models\Student::create([
        'organization_id'=>2,'name'=>'Synthetic exam browser learner',
        'email'=>'synthetic-exam-'.$suffix.'@example.invalid',
        'password'=>Illuminate\Support\Facades\Hash::make($credentials['password']),
        'status'=>'Active',
    ]);
    $type=App\Models\Qtype::firstOrCreate(['type'=>'M'],['question_type'=>'Multiple Choice']);
    $question=App\Models\Question::create([
        'organization_id'=>2,'qtype_id'=>$type->id,'question'=>'Synthetic browser: what is two plus two?',
        'option1'=>'4','option2'=>'5','correct_option_indices'=>[1],'marks'=>2,'negative_marks'=>0,'status'=>'Yes',
    ]);
    $exam=App\Models\Exam::create([
        'organization_id'=>2,'name'=>'Synthetic browser exam '.$suffix,'slug'=>'synthetic-browser-'.$suffix,
        'status'=>'Active','passing_percentage'=>50,'attempt_count'=>1,'duration'=>30,'mode'=>'Exam',
        'test_type'=>'full_length','start_date'=>now()->subMinute(),'end_date'=>now()->addDay(),
        'result_after_finish'=>true,'random_question'=>false,'option_shuffle'=>false,
        'allow_answer_change'=>true,'grouping_mode'=>'none','proctor'=>false,'browser_tolerance'=>false,
    ]);
    $exam->questions()->attach($question->id);
    return ['exam'=>$exam->id,'student'=>$student->id,'login'=>$student->email,'question'=>$question->id];
});
$file=$base.'/exam-browser-fixture.json';
if(is_link($file))throw new RuntimeException('Fixture metadata symlink refused');
file_put_contents($file,json_encode($fixture,JSON_THROW_ON_ERROR));
echo "Fresh synthetic student and exam prepared locally; metadata stays ignored.\n";
} catch(Throwable $error) {fwrite(STDERR,"BLOCKED: synthetic exam fixture requires review.\n");exit(1);}

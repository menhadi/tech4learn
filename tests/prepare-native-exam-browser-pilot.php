<?php
// Reuses the CLI-only, fixed local SQLite and synthetic tenant guards.
require __DIR__.'/prepare-native-browser-pilot.php';
try {
if(in_array('--verify-result',$argv,true)) {
    $db->statement('PRAGMA query_only = ON');
    $fixture=json_decode(file_get_contents($base.'/exam-browser-fixture.json'),true,512,JSON_THROW_ON_ERROR);
    $result=App\Models\ExamResult::where(['organization_id'=>2,'exam_id'=>$fixture['exam'],'student_id'=>$fixture['student']])->sole();
    $stat=App\Models\ExamStat::where(['exam_result_id'=>$result->id,'student_id'=>$fixture['student'],'question_id'=>$fixture['question']])->sole();
    if($fixture['subjective']??false) {
        $graded=in_array('--graded',$argv,true);
        if(!$result->end_time || $stat->ques_status!==($graded?'R':'P') || $stat->answer!=='Synthetic uploaded explanation' || !str_starts_with($stat->uploaded_answer_path??'', 'student_answers_private/2/'.$result->id.'/') || !Illuminate\Support\Facades\Storage::disk('local')->exists($stat->uploaded_answer_path))throw new RuntimeException('Written answer evidence missing');
        if($graded && ($result->result!=='Pass' || (float)$result->obtained_marks!==2.0))throw new RuntimeException('Published marking missing');
        echo $graded?"PASS: synthetic teacher marking published a two-mark pass with evidence retained.\n":"PASS: synthetic written answer and private evidence persisted; marking remains pending.\n";
        exit(0);
    }
    if(!$result->end_time || (float)$result->obtained_marks!==2.0 || $stat->ques_status!=='R')throw new RuntimeException('Submission not scored');
    echo "PASS: synthetic browser submission persisted its end time and correct two-mark score.\n";
    exit(0);
}
$subjective=in_array('--subjective',$argv,true);
if(in_array('--pdf',$argv,true)) {
    if(!$subjective)throw new RuntimeException('PDF fixture requires a written answer');
    $document=$base.'/synthetic-answer.pdf';
    if(is_link($document))throw new RuntimeException('Document fixture symlink refused');
    $stream="BT /F1 18 Tf 72 720 Td (Synthetic uploaded explanation) Tj ET";
    $objects=['<< /Type /Catalog /Pages 2 0 R >>','<< /Type /Pages /Kids [3 0 R] /Count 1 >>','<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>','<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>','<< /Length '.strlen($stream)." >>\nstream\n".$stream."\nendstream"];
    $pdf="%PDF-1.4\n";$offsets=[];
    foreach($objects as $index=>$object){$offsets[]=strlen($pdf);$pdf.=($index+1)." 0 obj\n".$object."\nendobj\n";}
    $xref=strlen($pdf);$pdf.="xref\n0 6\n0000000000 65535 f \n";
    foreach($offsets as $offset)$pdf.=sprintf("%010d 00000 n \n",$offset);
    $pdf.="trailer\n<< /Size 6 /Root 1 0 R >>\nstartxref\n".$xref."\n%%EOF\n";
    file_put_contents($document,$pdf);
}
if(in_array('--docx',$argv,true)) {
    if(!$subjective)throw new RuntimeException('DOCX fixture requires a written answer');
    $document=$base.'/synthetic-answer.docx';
    if(is_link($document))throw new RuntimeException('Document fixture symlink refused');
    $zip=new ZipArchive;
    if($zip->open($document,ZipArchive::CREATE|ZipArchive::OVERWRITE)!==true)throw new RuntimeException('Document fixture unavailable');
    $zip->addFromString('[Content_Types].xml','<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/></Types>');
    $zip->addFromString('_rels/.rels','<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/></Relationships>');
    $zip->addFromString('word/document.xml','<?xml version="1.0"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body><w:p><w:r><w:t>Synthetic uploaded explanation</w:t></w:r></w:p></w:body></w:document>');
    $zip->close();
}
if($subjective) {
    // The included guard has already restricted this to the fixed local SQLite fixture.
    $migration=require dirname(__DIR__).'/platform/database/migrations/2026_10_08_000001_add_subjective_answer_evidence_to_exam_stats.php';
    $migration->up();
}
$fixture=$db->transaction(function()use($base,$subjective){
    $credentials=json_decode(file_get_contents($base.'/local-pilot-credentials.json'),true,512,JSON_THROW_ON_ERROR);
    $suffix=bin2hex(random_bytes(8));
    if($subjective) {
        $plan=App\Models\SaasPlan::create(['name'=>'Synthetic browser uploads','slug'=>'synthetic-browser-'.$suffix,'price'=>0,'billing_cycle'=>'monthly','status'=>true,'features'=>['ai_subjective_analysis'=>true,'reports'=>true]]);
        App\Models\Organization::where('id',2)->update(['saas_plan_id'=>$plan->id]);
    }
    $student=App\Models\Student::create([
        'organization_id'=>2,'name'=>'Synthetic exam browser learner',
        'email'=>'synthetic-exam-'.$suffix.'@example.invalid',
        'password'=>Illuminate\Support\Facades\Hash::make($credentials['password']),
        'status'=>'Active',
    ]);
    $type=App\Models\Qtype::firstOrCreate(['type'=>$subjective?'S':'M'],['question_type'=>$subjective?'Subjective':'Multiple Choice']);
    $question=App\Models\Question::create([
        'organization_id'=>2,'qtype_id'=>$type->id,'question'=>$subjective?'Synthetic browser: explain your answer.':'Synthetic browser: what is two plus two?',
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
    return ['exam'=>$exam->id,'student'=>$student->id,'login'=>$student->email,'question'=>$question->id,'subjective'=>$subjective];
});
$file=$base.'/exam-browser-fixture.json';
if(is_link($file))throw new RuntimeException('Fixture metadata symlink refused');
file_put_contents($file,json_encode($fixture,JSON_THROW_ON_ERROR));
echo "Fresh synthetic student and exam prepared locally; metadata stays ignored.\n";
} catch(Throwable $error) {fwrite(STDERR,"BLOCKED: synthetic exam fixture requires review.\n");exit(1);}

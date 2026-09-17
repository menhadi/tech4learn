<?php
// One continuous native flow; do not seed a completed attempt or pending marks.
require __DIR__.'/test-result-marking.php';
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

DB::table('qtypes')->insert(['id'=>5,'question_type'=>'Subjective','type'=>'S']);
DB::table('tech4learn_workspaces')->where('id',$workspace)->update(['restrictions'=>'[]']);
Carbon::setTestNow(Carbon::parse('2026-09-13 12:00:00','UTC'));
try {
    $question=$service->save($workspace,20,$actor,0,[
        'qtype_id'=>5,'question'=>'<p>Explain why two plus two is four.</p>',
        'si_answer1'=>'Two pairs contain four items.','marks'=>10,'negative_marks'=>0,
        'language_id'=>$language->id,'group_ids'=>[$group->id],'status'=>'Yes',
    ],'new',$next());
    $settings=array_replace($service->newExam()['fields'],[
        'name'=>'Synthetic complete pilot paper','groups'=>[$group->id],
        'language_ids'=>[$language->id],'duration'=>30,'passing_percentage'=>50,
        'start_date'=>'2026-09-13 10:00:00','end_date'=>'2026-09-13 14:00:00',
        'timer_mode'=>'none','proctor'=>false,'browser_tolerance'=>false,
        'attempt_count'=>1,'result_after_finish'=>false,'frontend_visible'=>true,'online_attempt_enabled'=>true,
    ]);
    $exam=$service->save($workspace,20,$actor,0,$settings,'new',$next(),'exams');
    $exam=$service->save($workspace,20,$actor,$exam['id'],['question_ids'=>[$question['id']]],$exam['revision'],$next(),'exams','add-questions');
    $exam=$service->save($workspace,20,$actor,$exam['id'],['status'=>'Active'],$exam['revision'],$next(),'exams','set-status');
    $pilotLearner=$next();
    $run=fn($action,$fields)=>$lifecycle->run($workspace,10,$pilotLearner,'Synthetic pilot candidate',$exam['id'],$action,$fields);
    $started=$run('start',['request_id'=>$next()]);
    check(count($started['questions'])===1&&$started['questions'][0]['id']===$question['id'],'Created paper is delivered with its authored question');
    $answerRequest=['request_id'=>$next(),'attempt_id'=>$started['attempt_id'],'question_id'=>$question['id'],'revision'=>$started['questions'][0]['revision'],'fields'=>['option_selected'=>'Two pairs each have two items, giving four.']];
    $ack=$run('answer',$answerRequest);check($ack['saved'],'Written answer saves through the native engine');
    check($run('answer',$answerRequest)===$ack,'Lost answer acknowledgement retries safely');
    $resumed=$run('start',['request_id'=>$next()]);
    check($resumed['attempt_id']===$started['attempt_id'],'Resume keeps the original attempt');
    $submit=['request_id'=>$next(),'attempt_id'=>$started['attempt_id']];
    $finished=$run('submit',$submit);
    check($finished['completed']&&$finished['result']===null,'Submitted paper respects unpublished results');
    $review=$marking->review($workspace,10,$actor,$pilotLearner,$started['attempt_id']);
    check(count($review['questions'])===1&&str_contains($review['questions'][0]['answer_html'],'Two pairs'),'Native submission produces the actual pending written answer');
    $marks=[];foreach($review['questions'] as $row)$marks[$row['stat_id']]=7;
    $markRequest=$next();$graded=$marking->save($workspace,10,$actor,$pilotLearner,$started['attempt_id'],$marks,$review['revision'],$markRequest);
    check($graded['score_percent']===70.0&&$graded['obtained_marks']===7.0,'Native manual marking calculates the final score');
    check($marking->save($workspace,10,$actor,$pilotLearner,$started['attempt_id'],$marks,$review['revision'],$markRequest)===$graded,'Marking retry does not apply twice');
    check($run('result',['request_id'=>$next(),'attempt_id'=>$started['attempt_id']])['result']===null,'Marking does not publish hidden results');
    $exam=$service->record('exams',App\Models\Exam::findOrFail($exam['id']));
    $service->save($workspace,20,$actor,$exam['id'],['result_after_finish'=>true],$exam['revision'],$next(),'exams','set-result-status');
    $published=$run('result',['request_id'=>$next(),'attempt_id'=>$started['attempt_id']]);
    check($published['result']!==null,'Explicit publication exposes the completed result');
    $history=$run('history',['request_id'=>$next()]);
    check(count($history['items'])===1&&$history['items'][0]['attempt_id']===$started['attempt_id'],'Student history contains exactly the completed pilot attempt');
    check($run('submit',$submit)===$published,'Submission retry preserves the graded published result');
} finally {Carbon::setTestNow();}
echo "Pilot native workflow: create, assemble, activate, take, resume, submit, mark, publish and history passed.\n";

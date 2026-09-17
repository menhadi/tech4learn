<?php
// Reuse the actual native controller, isolated database and both author contexts.
require __DIR__.'/test-central-question-authoring.php';
use App\Models\Question;
use Illuminate\Support\Facades\DB;

foreach([false,true] as $central){
    $record=Question::findOrFail($central?$createdCentral['id']:$q->id);
    $source='/storage/images/upload/t4l/'.$record->organization_id.'/retained.png';
    $record->question='<p>Before <img src="'.$source.'" alt="Diagram"> <math><mi>x</mi></math></p>';
    $record->explanation='<img src="/storage/images/upload/other-field.png">';
    $record->save();
    $before=$service->snapshot($record->fresh());
    $save=function(array $fields,string $revision,string $request)use($central,$record,$service,$workspace,$actor,$centralActor){
        return $central?$service->saveCentralQuestion(10,$centralActor,$record->id,$fields,$revision,$request):$service->save($workspace,20,$actor,$record->id,$fields,$revision,$request);
    };
    $wording='<p>After <math><msup><mi>x</mi><mn>2</mn></msup></math> <img src="'.$source.'" alt="Diagram" width="120"></p>';
    try{
        if($central)$service->saveCentralQuestion(10,$centralActor,0,['question'=>$wording],'new',$nextId());
        else $service->save($workspace,20,$actor,0,['question'=>$wording],'new',$nextId());
        throw new RuntimeException('Expected new-question image injection rejection');
    }catch(Illuminate\Validation\ValidationException $e){}
    $request=$nextId();$saved=$save(['question'=>$wording],$before['revision'],$request);
    check(str_contains($saved['fields']['question'],$source)&&str_contains($saved['fields']['question'],'<msup>'),'Retained image and changed MathML survive native save');
    check($saved['fields']['nat_value']===$before['fields']['nat_value'],'Mixed wording leaves the native answer unchanged');
    check($save(['question'=>$wording],$before['revision'],$request)===$saved,'Mixed wording retry remains identical');
    $table=$central?'tech4learn_central_requests':'tech4learn_authoring_requests';$ledger=DB::table($table)->count();
    foreach([
        '<img src="/storage/images/upload/foreign.png">',
        '<img src="/storage/images/upload/other-field.png">',
        '<img src="https://example.invalid/added.png">',
        '<img src="t4l-media:'.str_repeat('a',64).'">',
        '<img src="'.$source.'" onerror="alert(1)">',
        '<img src="'.$source.'" srcset="https://example.invalid/a.png 2x">',
        '<img src="'.$source.'" style="background:url(x)">',
    ] as $bad){
        try{$save(['question'=>$bad],$saved['revision'],$nextId());throw new RuntimeException('Expected retained-image rejection');}
        catch(Illuminate\Validation\ValidationException $e){}
    }
    check(DB::table($table)->count()===$ledger&&$service->snapshot($record->fresh())===$saved,'Rejected sources and attributes leave records and ledger unchanged');
    try{$save(['question'=>$wording],$before['revision'],$nextId());throw new RuntimeException('Expected stale image wording rejection');}
    catch(Symfony\Component\HttpKernel\Exception\HttpException $e){check($e->getStatusCode()===409,'Image wording uses the saved revision');}
}
echo "Retained question images: native mixed wording, original-field scope, unsafe input rejection and retries passed.\n";

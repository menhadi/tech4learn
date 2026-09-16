<?php
// Installed native exam controller, synthetic SQLite records, no live writes.
require __DIR__.'/test-central-package-authoring.php';
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use App\Models\{Exam,Language};
$orgExamBefore=$service->record('exams',Exam::findOrFail($exam['id']));
$workspaceCount=DB::table('tech4learn_workspaces')->count();
$centralLanguage=Language::enabledForOrganization(10)->firstOrFail();
$draft=$centralController->centralTaxonomy(Request::create('/','GET'),'exams','new')['record'];
check($draft===$service->newExam(),'Central exam draft uses native defaults and test types');
$fields=array_replace($draft['fields'],['name'=>'Synthetic central exam','groups'=>[$centralGroup->id],'language_ids'=>[$centralLanguage->id],'packages'=>[$created['id']],'passing_percentage'=>37.5]);
$saveExam=function($id,$fields,$revision,$key)use($centralController,$centralActor){
 $result=$centralController->centralTaxonomyWrite(Request::create('/','POST',['actor_id'=>$centralActor,'fields'=>$fields,'revision'=>$revision,'request_id'=>$key]),'exams',$id?(string)$id:'new');
 if($result['conflict']??false)abort(409);
 if(!$result['saved'])throw Illuminate\Validation\ValidationException::withMessages($result['errors']);
 check($result['kind']==='exams','Central exam response kind');return $result['record'];
};
$key=$uuid();$centralExam=$saveExam(0,$fields,'new',$key);
check(Exam::findOrFail($centralExam['id'])->organization_id==10&&(float)$centralExam['fields']['passing_percentage']===37.5,'Native central exam creation preserves owner and fractional pass threshold');
check($centralExam['fields']['groups']===[$centralGroup->id]&&$centralExam['fields']['packages']===[$created['id']]&&in_array($centralLanguage->id,$centralExam['fields']['language_ids'],true),'Central exam relationships remain central');
check($saveExam(0,$fields,'new',$key)===$centralExam,'Central exam create replay is stable');
$edited=$saveExam($centralExam['id'],['name'=>'Edited central exam','instruction'=>'<p>Use \\(x^2\\) in the working.</p>'],$centralExam['revision'],$uuid());
check($edited['fields']['name']==='Edited central exam'&&$edited['fields']['groups']===$centralExam['fields']['groups']&&$edited['fields']['packages']===$centralExam['fields']['packages'],'Central exam patch preserves relationships');
check($centralController->centralTaxonomy(Request::create('/','GET'),'exams',(string)$centralExam['id'])['record']===$edited,'Central exam read returns native revision and metadata');
$deny(fn()=>$saveExam($centralExam['id'],['name'=>'Stale'],$centralExam['revision'],$uuid()));
$deny(fn()=>$saveExam($exam['id'],['name'=>'Foreign'],$orgExamBefore['revision'],$uuid()));
foreach([['groups'=>[$group->id]],['packages'=>[$packageSaved['id']]],['category_level_1'=>$category['id'],'packages'=>[]],['language_ids'=>[$language->id]],['organization_id'=>20],['instruction'=>'<script>bad</script>']] as $invalid){try{$deny(fn()=>$saveExam(0,array_replace($fields,$invalid),'new',$uuid()));}catch(RuntimeException $e){throw new RuntimeException('Expected central exam rejection for '.array_key_first($invalid),0,$e);}}
DB::table('users')->where('id',$nativeId)->update(['status'=>0]);$deny(fn()=>$saveExam(0,$fields,'new',$key));DB::table('users')->where('id',$nativeId)->update(['status'=>1]);
check($saveExam(0,$fields,'new',$key)===$centralExam,'Restored central author can replay exam result');
$choices=$controller->centralChoices(Request::create('/','GET'),'exams');
check(in_array($centralExam['id'],array_column($choices['items'],'id'),true)&&!in_array($exam['id'],array_column($choices['items'],'id'),true),'Central exam selector excludes organisation papers');
check($service->record('exams',Exam::findOrFail($exam['id']))===$orgExamBefore&&DB::table('tech4learn_workspaces')->count()===$workspaceCount,'Central exam writes preserve organisation records and create no workspace');
echo "Central native exams: create, settings, scope, metadata and retry checks passed.\n";

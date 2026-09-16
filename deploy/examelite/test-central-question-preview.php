<?php
// Synthetic database and native model/controller checks; no live writes.
require __DIR__.'/test-question-authoring.php';
require __DIR__.'/Tech4LearnContentController.php';
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Question;
use App\Services\Tech4LearnQuestionMedia;

$centralController=new class extends App\Http\Controllers\Tech4LearnContentController {
 public int $owner=10;
 protected function configuration(Request $r):array{return ['_platform'=>['organization_id'=>$this->owner]];}
 protected function reply(int $tenant,array $data){return ['organization_id'=>$tenant]+$data;}
};
$deny=function(callable $call,int $status=404){
 try{$call();throw new RuntimeException('Expected central preview denial');}
 catch(Illuminate\Database\Eloquent\ModelNotFoundException $e){check($status===404,'Expected missing scoped record');}
 catch(Symfony\Component\HttpKernel\Exception\HttpException $e){check($status===$e->getStatusCode(),'Expected central preview HTTP '.$status);}
};
$central=Question::create(['organization_id'=>10,'qtype_id'=>2,'question'=>'Central original','explanation'=>'<img src="'.$imageSource.'">','nat_config'=>['version'=>1,'mode'=>'exact','value'=>12],'marks'=>4]);
$id=(string)$central->id;
$get=fn(array $query=[])=>Request::create('/','GET',$query);
$beforeRows=Question::count();$beforeLedger=DB::table('tech4learn_authoring_requests')->count();
$snapshot=$centralController->centralDetail($get(),$id);
check($snapshot['id']===$central->id&&$snapshot['organization_id']===10&&$snapshot['fields']['nat_value']===12,'Central original preview retains native answer semantics');
check(!str_contains(json_encode($snapshot),$imageSource)&&str_contains($snapshot['fields']['explanation'],'t4l-media:'),'Central preview hides image storage locations');
$revision=$snapshot['revision'];$asset=hash('sha256',$imageSource);
$read=fn()=>$centralController->centralMedia($get(['revision'=>$revision]),$id,$asset);
$bytes=$read();
check($bytes['mime']==='image/png'&&$bytes['question_id']===$central->id&&$bytes['revision']===$revision,'Central image is bound to the preview version');
$deny(fn()=>$centralController->centralDetail($get(),(string)$q->id));
$deny(fn()=>$centralController->centralDetail($get(['organization_id'=>20]),$id),422);
$deny(fn()=>$centralController->centralDetail($get(),'0'),422);
$deny(fn()=>$centralController->centralMedia($get(),$id,$asset),422);
$deny(fn()=>$centralController->centralMedia($get(['revision'=>$revision,'path'=>'/etc/passwd']),$id,$asset),422);
$deny(fn()=>$centralController->centralMedia($get(['revision'=>str_repeat('0',64)]),$id,$asset),409);
$deny(fn()=>$centralController->centralMedia($get(['revision'=>$revision]),$id,str_repeat('0',64)));
DB::table('organizations')->where('id',10)->update(['status'=>'inactive']);$deny($read);
DB::table('organizations')->where('id',10)->update(['status'=>'active']);
$app->instance(Tech4LearnQuestionMedia::class,new class extends Tech4LearnQuestionMedia {
 public function readAuthoring(Question $question,int $owner,string $key):array {
  $result=parent::readAuthoring($question,$owner,$key);
  $question->question='Changed during image read';$question->save();return $result;
 }
});
$deny($read,409);$app->forgetInstance(Tech4LearnQuestionMedia::class);
check(Question::count()===$beforeRows&&DB::table('tech4learn_authoring_requests')->count()===$beforeLedger,'Preview creates no copies or write-ledger entries');
echo "Central question preview: native snapshot, opaque images, owner isolation, active platform and stale-read rejection passed.\n";

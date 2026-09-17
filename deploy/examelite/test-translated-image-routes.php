<?php
require __DIR__.'/test-translated-image-upload.php';
require_once __DIR__.'/Tech4LearnContentController.php';
use Illuminate\Http\Request;

$orgImageController=new class extends App\Http\Controllers\Tech4LearnAuthoringController {
    protected function configuration(Request $r):array{return ['_platform'=>['organization_id'=>10]];}
    protected function reply(int $tenant,array $data){return ['organization_id'=>$tenant]+$data;}
};
$centralImageController=new class extends App\Http\Controllers\Tech4LearnContentController {
    protected function configuration(Request $r):array{return ['_platform'=>['organization_id'=>10]];}
    protected function reply(int $tenant,array $data){return ['organization_id'=>$tenant]+$data;}
};
foreach([false,true] as $central){
    $owner=$central?10:20;$lang=$central?$centralTarget:$target;
    $paper=App\Models\Exam::create(['organization_id'=>$owner,'name'=>'Synthetic image route paper','status'=>'Inactive']);$paper->languages()->attach($lang->id);
    $source=App\Models\Question::create(['organization_id'=>$owner,'question'=>'Source']);$paper->questions()->attach($source->id);
    $translation=App\Models\QuestionLang::create(['question_id'=>$source->id,'language_id'=>$lang->id,'question'=>'Translated']);
    $review=$central?$reader->centralReview(10,$paper->id,$lang->id):$reader->review($workspace,10,$actor,$paper->id,$lang->id);
    $body=['actor_id'=>$central?$ca:$actor,'request_id'=>$uuid(),'revision'=>$service->record('exams',$paper->fresh())['revision'],'fields'=>['language_id'=>$lang->id,'question_id'=>$source->id,'translation_revision'=>$review['revision'],'field'=>'question','image'=>$png]];
    $send=fn($data,$id,$action='set-translation-image',$query='')=>$central
        ?$centralImageController->centralExamAction(Request::create('/'.$query,'POST',$data),$id,$action)
        :$orgImageController->examAction(Request::create('/'.$query,'POST',$data),$workspace,$id,$action);
    $writes=$imageDisk->writes;
    foreach(['new','0','-1','1e2'] as $id)$reject(fn()=>$send($body,$id));
    $reject(fn()=>$send($body,(string)$paper->id,'unknown'));
    $reject(fn()=>$send($body+['organization_id'=>999],(string)$paper->id));
    $reject(fn()=>$send($body,(string)$paper->id,'set-translation-image','?actor_id=override'));
    check($imageDisk->writes===$writes,'Malformed image action envelopes never upload');
    $stale=$send(array_replace($body,['revision'=>str_repeat('0',64)]),(string)$paper->id);
    check(($stale['conflict']??false)===true&&$imageDisk->writes===$writes,'Stale image actions return recoverable conflict');
    $saved=$send($body,(string)$paper->id);
    check($saved['saved']===true&&$saved['organization_id']===10&&($saved[$central?'record':'question']['id']??null)===$paper->id,'Private image route returns bounded owned exam outcome');
    check($send($body,(string)$paper->id)===$saved&&$imageDisk->writes===$writes+1,'Private image route retries upload once');
}
$router=new Illuminate\Routing\Router(new Illuminate\Events\Dispatcher($app),$app);
$app->instance('router',$router);Illuminate\Support\Facades\Route::clearResolvedInstance('router');
$router->prefix('api')->group(function(){require __DIR__.'/tech4learn-routes.php';});
foreach([
 ['api/tech4learn/v1/central/exams/1/actions/set-translation-image','Tech4LearnContentController@centralExamAction'],
 ['api/tech4learn/v1/authoring/'.$workspace.'/exams/1/actions/set-translation-image','Tech4LearnAuthoringController@examAction'],
] as [$path,$expected]){
 $route=$router->getRoutes()->match(Request::create('https://example.test/'.$path,'POST'));
 check(str_ends_with($route->getActionName(),$expected),'Installed translated image action route resolves');
}
echo "Translated image private routes: envelope bounds, owner overrides, conflict and retry passed.\n";

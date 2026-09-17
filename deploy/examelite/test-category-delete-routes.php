<?php
require __DIR__.'/test-category-deletion.php';
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

// Use the real controllers; only the central credential reader is isolated.
$orgDeleteController=new class extends App\Http\Controllers\Tech4LearnAuthoringController {
    protected function configuration(Request $r):array{return ['_platform'=>['organization_id'=>10]];}
    protected function reply(int $tenant,array $data){return ['organization_id'=>$tenant]+$data;}
};
foreach([20,10] as $owner)foreach(['categories','subcategories'] as $kind){
    $parent=$kind==='subcategories'?$make($owner):null;
    $record=$make($owner,$parent?->id);
    $body=['actor_id'=>$owner===10?$centralActor:$actor,'request_id'=>$nextId(),'revision'=>$service->record($kind,$record)['revision'],'fields'=>[]];
    $send=fn(array $data,string $targetKind,string $id,string $query='')=>$owner===10
        ?$centralController->centralCategoryDelete(Request::create('/'.$query,'POST',$data),$targetKind,$id)
        :$orgDeleteController->deleteCategory(Request::create('/'.$query,'POST',$data),$workspace,$targetKind,$id);
    foreach(['subjects','languages','exams','questions'] as $unsupported)$reject(fn()=>$send($body,$unsupported,(string)$record->id));
    foreach(['new','0','-1','1e2'] as $invalid)$reject(fn()=>$send($body,$kind,$invalid));
    foreach(['actor_id','request_id','revision'] as $required){$bad=$body;unset($bad[$required]);$reject(fn()=>$send($bad,$kind,(string)$record->id));}
    $reject(fn()=>$send(array_replace($body,['fields'=>['title'=>'Replacement']]),$kind,(string)$record->id));
    $reject(fn()=>$send($body+['organization_id'=>999],$kind,(string)$record->id));
    $reject(fn()=>$send($body,$kind,(string)$record->id,'?organization_id=999'));
    check(App\Models\Category::find($record->id)!==null,'Malformed delete requests leave record unchanged');
    $stale=$send(array_replace($body,['revision'=>str_repeat('0',64)]),$kind,(string)$record->id);
    check(($stale['saved']??true)===false&&($stale['conflict']??false)===true,'Stale deletion has recoverable conflict response');
    $deleted=$send($body,$kind,(string)$record->id);
    $ack=$owner===10?$deleted['record']:$deleted['question'];
    check($deleted['saved']===true&&$deleted['organization_id']===10&&$ack===['id'=>(int)$record->id,'deleted'=>true],'Controller returns scoped native deletion acknowledgement');
    check($send($body,$kind,(string)$record->id)===$deleted,'Controller deletion retries preserve acknowledgement');
}

// Check actual installed route registration, not a parallel route fixture.
$router=new Illuminate\Routing\Router(new Illuminate\Events\Dispatcher($app),$app);
$app->instance('router',$router);Illuminate\Support\Facades\Route::clearResolvedInstance('router');
$router->prefix('api')->group(function(){require __DIR__.'/tech4learn-routes.php';});
foreach([
    ['api/tech4learn/v1/central/taxonomy/categories/1/delete','Tech4LearnContentController@centralCategoryDelete'],
    ['api/tech4learn/v1/authoring/'.$workspace.'/taxonomy/subcategories/1/delete','Tech4LearnAuthoringController@deleteCategory'],
] as [$path,$expected]){
    $route=$router->getRoutes()->match(Request::create('https://example.test/'.$path,'POST'));
    check(str_ends_with($route->getActionName(),$expected),'Installed route resolves to the category deletion controller');
}
echo "Category deletion controllers and routes: input bounds, owner overrides, stale edits and retries passed.\n";

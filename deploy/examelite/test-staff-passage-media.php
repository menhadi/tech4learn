<?php
require __DIR__.'/test-retained-passage-images.php';
use App\Models\{Passage,PassageLang};
use App\Services\Tech4LearnQuestionMedia;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
$png=base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Y9ZQmcAAAAASUVORK5CYII=');
foreach([false,true] as $centralOwner){
    $owner=$centralOwner?10:20;$languageId=$centralOwner?$centralLanguage->id:$lang->id;
    $model=Passage::findOrFail($centralOwner?$central['id']:$saved['id']);
    $row=PassageLang::where('passage_id',$model->id)->where('language_id',$languageId)->firstOrFail();
    $source='data:image/png;base64,'.base64_encode($png.($centralOwner?'central':'organisation'));
    $row->passage='<p>Private preview <img src="'.$source.'"></p>';$row->save();
    $asset=hash('sha256',$source);$revision=$service->record('passages',$model->fresh())['revision'];
    $read=function($query,$key=null,$id=null,$language=null)use($centralOwner,$controller,$workspace,$model,$languageId,$asset){
        $r=Request::create('/','GET',$query);$id=(string)($id??$model->id);$language=(string)($language??$languageId);
        return $centralOwner?$controller->centralPassageMedia($r,$id,$language,$key??$asset):$controller->passageMedia($r,$workspace,$id,$language,$key??$asset);
    };
    $query=['revision'=>$revision];$data=$read($query);
    check($data['base64']===base64_encode($png.($centralOwner?'central':'organisation'))&&$data['revision']===$revision,'Staff preview returns only the selected owned passage diagram');
    $reject(fn()=>$read([]),422);
    $reject(fn()=>$read($query+['owner'=>99]),422);
    $reject(fn()=>$read(['revision'=>str_repeat('0',64)]),409);
    $reject(fn()=>$read($query,str_repeat('f',64)),404);
    $reject(fn()=>$read($query,null,$centralOwner?$saved['id']:$central['id']),404);
    $reject(fn()=>$read($query,null,null,$centralOwner?$lang->id:$centralLanguage->id),404);
    if($centralOwner)$reject(fn()=>$read($query,null,null,$secondLanguage->id),404);
    // The byte reader must not defeat post-read restriction/version checks.
    foreach(['wording','access'] as $mutation){
        $app->instance(Tech4LearnQuestionMedia::class,new class($mutation,$centralOwner,$owner,$workspace) extends Tech4LearnQuestionMedia {
            public function __construct(private $mutation,private $central,private $owner,private $workspace){}
            public function readPassage(Passage $passage,int $owner,int $language,string $key):array {
                $data=parent::readPassage($passage,$owner,$language,$key);
                if($this->mutation==='wording')$passage->langs()->where('language_id',$language)->update(['passage'=>'Changed during read']);
                elseif($this->central)DB::table('organizations')->where('id',$this->owner)->update(['status'=>'inactive']);
                else DB::table('tech4learn_workspaces')->where('id',$this->workspace)->update(['restrictions'=>'["questions"]']);
                return $data;
            }
        });
        $reject(fn()=>$read($query),$mutation==='wording'?409:($centralOwner?404:403));
        $app->forgetInstance(Tech4LearnQuestionMedia::class);
        $row->refresh();$row->passage='<p>Private preview <img src="'.$source.'"></p>';$row->save();
        DB::table('organizations')->where('id',$owner)->update(['status'=>'active']);
        DB::table('tech4learn_workspaces')->where('id',$workspace)->update(['restrictions'=>'[]']);
        $revision=$service->record('passages',$model->fresh())['revision'];$query=['revision'=>$revision];
    }
    $duplicate=$row->replicate();$duplicate->save();
    $query=['revision'=>$service->record('passages',$model->fresh())['revision']];
    $reject(fn()=>$read($query),409);$duplicate->delete();
}
$router=new Illuminate\Routing\Router(new Illuminate\Events\Dispatcher($app),$app);
$app->instance('router',$router);Illuminate\Support\Facades\Route::clearResolvedInstance('router');
$router->prefix('api')->group(function(){require __DIR__.'/tech4learn-routes.php';});
foreach([
 ['api/tech4learn/v1/central/passages/1/languages/1/media/'.str_repeat('a',64),'Tech4LearnAuthoringController@centralPassageMedia'],
 ['api/tech4learn/v1/authoring/'.$workspace.'/passages/1/languages/1/media/'.str_repeat('a',64),'Tech4LearnAuthoringController@passageMedia'],
] as [$path,$expected]){
    $route=$router->getRoutes()->match(Request::create('https://example.test/'.$path,'GET'));
    check(str_ends_with($route->getActionName(),$expected),'Installed staff passage media route resolves');
}
echo "Staff passage media: both owners, exact language/revision, foreign and hidden image denial, read-time revocation and duplicate denial passed. Gateway/UI connection remains pending.\n";

<?php
require __DIR__.'/test-passage-authoring.php';
use Illuminate\Support\Facades\DB;
use App\Models\{Passage,PassageLang};

// Restore fixture memberships revoked by the authoring checks above.
DB::table('tech4learn_workspaces')->where('id',$workspace)->update(['restrictions'=>'[]']);
DB::table('organization_users')->where('organization_id',10)->where('user_id',$nativeActor)->update(['status'=>1]);
foreach([false,true] as $centralOwner){
    $owner=$centralOwner?10:20;$languageId=$centralOwner?$centralLanguage->id:$lang->id;
    $model=Passage::findOrFail($centralOwner?$central['id']:$saved['id']);
    $source='/storage/images/upload/t4l/'.$owner.'/'.str_repeat($centralOwner?'a':'b',40).'.png';
    $row=PassageLang::where('passage_id',$model->id)->where('language_id',$languageId)->firstOrFail();
    $row->passage='<p>Original <img src="'.$source.'" alt="Diagram"></p>';$row->save();
    $read=fn()=>$service->record('passages',$model->fresh());
    $write=function($fields,$revision,$request)use($centralOwner,$service,$centralActor,$workspace,$actor,$model){
        return $centralOwner?$service->saveCentralTaxonomy(10,$centralActor,'passages',$model->id,$fields,$revision,$request)
            :$service->save($workspace,20,$actor,$model->id,$fields,$revision,$request,'passages');
    };
    $before=$read();$request=$next();
    $fields=['passages'=>[$languageId=>'<p>Corrected wording <img src="'.$source.'" alt="Diagram"><math><mn>2</mn></math></p>']];
    $result=$write($fields,$before['revision'],$request);
    check(str_contains($row->fresh()->passage,$source)&&str_contains($row->fresh()->passage,'Corrected wording'),'Native passage edit retains its diagram alongside text/formula changes');
    check($write($fields,$before['revision'],$request)===$result,'Lost passage media save response has an identical receipt');
    foreach([
        '<img src="/storage/images/upload/t4l/99/'.str_repeat('c',40).'.png">',
        '<img src="'.$source.'" onerror="alert(1)">',
        '<img src="https://example.invalid/new.png">',
    ] as $bad){
        $reject(fn()=>$write(['passages'=>[$languageId=>$bad]],$result['revision'],$next()),422);
        check($read()===$result,'Invalid retained image cannot change passage wording');
    }
    if($centralOwner){
        $reject(fn()=>$write(['passages'=>[$secondLanguage->id=>'<img src="'.$source.'">']],$result['revision'],$next()),422);
        check($read()===$result,'A diagram cannot be copied into another passage language through wording edits');
    }
    $reject(fn()=>$write($fields,$before['revision'],$next()),409);
    // Existing native duplicate rows are ambiguous: do not silently select one.
    $duplicate=$row->replicate();$duplicate->save();$duplicateBefore=$read();
    $reject(fn()=>$write($fields,$duplicateBefore['revision'],$next()),409);
    $duplicate->delete();
    $result=$read();
    $removed=$write(['passages'=>[$languageId=>'<p>Reviewed wording without diagram</p>']],$result['revision'],$next());
    check(!str_contains($removed['fields']['passages'][$languageId],'<img'),'Removing a passage image reference leaves file lifetime separate');
}
echo "Retained passage images: native edits for both owners, formula/text preservation, same-language scope, duplicate/stale denial and retry passed. Upload controls and protected staff previews remain separate.\n";

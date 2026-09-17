<?php
// Actual native category controller, isolated storage; no application bootstrap.
require __DIR__.'/test-central-question-authoring.php';
use Illuminate\Support\Facades\DB;
use App\Models\Category;

foreach(['packages','flashcard_sets'] as $table){
    if(!DB::getSchemaBuilder()->hasTable($table))DB::statement('CREATE TABLE '.$table.' (id INTEGER PRIMARY KEY, organization_id INTEGER, category_level_1 INTEGER, category_level_2 INTEGER)');
    foreach(['category_level_1','category_level_2'] as $field)if(!DB::getSchemaBuilder()->hasColumn($table,$field))DB::statement('ALTER TABLE '.$table.' ADD COLUMN '.$field.' INTEGER');
}
$remove=function(int $owner,string $kind,int $id,string $revision,string $request)use($service,$workspace,$actor,$centralActor){
    return $owner===10?$service->deleteCentralCategory(10,$centralActor,$kind,$id,$revision,$request):$service->save($workspace,20,$actor,$id,[],$revision,$request,$kind,'delete-category');
};
$make=fn(int $owner,?int $parent=null)=>Category::create(['organization_id'=>$owner,'title'=>'Disposable '.Illuminate\Support\Str::uuid(),'parent_id'=>$parent,'status'=>1])->fresh();
foreach([20,10] as $owner){
    $ledger=$owner===10?'tech4learn_central_requests':'tech4learn_authoring_requests';
    $parent=$make($owner);$child=$make($owner,$parent->id);
    $parentRevision=$service->record('categories',$parent)['revision'];
    $beforeLedger=DB::table($ledger)->count();
    $reject(fn()=>$remove($owner,'categories',$parent->id,$parentRevision,$nextId()));
    check(Category::find($parent->id)!==null&&DB::table($ledger)->count()===$beforeLedger,'Native in-use parent rejection is atomic');
    $childRevision=$service->record('subcategories',$child)['revision'];
    $GLOBALS['t4lTestSubcategoriesEnabled']=false;
    $reject(fn()=>$remove($owner,'subcategories',$child->id,$childRevision,$nextId()));
    $GLOBALS['t4lTestSubcategoriesEnabled']=true;
    $reject(fn()=>$remove($owner,'categories',$child->id,$childRevision,$nextId()));
    $reject(fn()=>$remove($owner,'subcategories',$parent->id,$parentRevision,$nextId()));
    $reject(fn()=>$remove($owner,'subcategories',$child->id,str_repeat('0',64),$nextId()));
    $request=$nextId();$deleted=$remove($owner,'subcategories',$child->id,$childRevision,$request);
    check($deleted===['id'=>(int)$child->id,'deleted'=>true]&&Category::find($child->id)===null,'Native child deletion confirmed');
    check($remove($owner,'subcategories',$child->id,$childRevision,$request)===$deleted,'Deleted-record retry returns prior acknowledgement');
    $reject(fn()=>$remove($owner,'subcategories',$child->id,str_repeat('0',64),$request));
    // Current authority must still be checked before replaying a deletion.
    if($owner===20){
        DB::table('tech4learn_workspaces')->where('id',$workspace)->update(['restrictions'=>'["subjects"]']);
        $reject(fn()=>$remove($owner,'subcategories',$child->id,$childRevision,$request));
        DB::table('tech4learn_workspaces')->where('id',$workspace)->update(['restrictions'=>'[]']);
    }else{
        $deleteAuthor=DB::table('tech4learn_central_users')->where('organization_id',10)->where('local_id',$centralActor)->value('external_id');
        DB::table('users')->where('id',$deleteAuthor)->update(['status'=>0]);
        $reject(fn()=>$remove($owner,'subcategories',$child->id,$childRevision,$request));
        DB::table('users')->where('id',$deleteAuthor)->update(['status'=>1]);
    }
    $foreign=$make($owner===10?20:10);
    $reject(fn()=>$remove($owner,'categories',$foreign->id,$service->record('categories',$foreign)['revision'],$nextId()));
    check(Category::find($foreign->id)!==null,'Foreign category preserved');
    // Native references in every supported consumer prevent removal.
    foreach(['exams','packages','flashcard_sets'] as $table)foreach(['category_level_1','category_level_2'] as $field){
        $used=$make($owner);$revision=$service->record('categories',$used)['revision'];
        $reference=DB::table($table)->insertGetId(['organization_id'=>$owner,$field=>$used->id]);
        $beforeLedger=DB::table($ledger)->count();
        $reject(fn()=>$remove($owner,'categories',$used->id,$revision,$nextId()));
        check(Category::find($used->id)!==null&&DB::table($ledger)->count()===$beforeLedger,'Native reference blocks deletion without ledger write');
        DB::table($table)->where('id',$reference)->delete();
        $remove($owner,'categories',$used->id,$revision,$nextId());
        check(Category::find($used->id)===null,'Unused category can be removed');
    }
    $parent->groups()->attach($owner===10?$centralGroup->id:$group->id);
    $parentRevision=$service->record('categories',$parent->fresh())['revision'];
    $remove($owner,'categories',$parent->id,$parentRevision,$nextId());
    check(DB::table('category_groups')->where('category_id',$parent->id)->count()===0,'Native delete detaches group associations');
}
echo "Native category deletion: scope, references, stale edits, disabled features, revoked actors and retries passed.\n";

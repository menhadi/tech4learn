<?php
// Isolated SQLite and native package controller; no live writes.
require __DIR__.'/test-exam-authoring.php';
use Illuminate\Support\Facades\DB;
use App\Models\{Package,PackageTag,Group,User};
DB::statement('CREATE TABLE tech4learn_central_users(organization_id INTEGER,local_id TEXT,external_id INTEGER UNIQUE,PRIMARY KEY(organization_id,local_id))');
DB::statement('CREATE TABLE tech4learn_central_requests(organization_id INTEGER,request_id TEXT,actor_id TEXT,fingerprint TEXT,result TEXT,created_at TEXT,PRIMARY KEY(organization_id,request_id))');
foreach(['username','email','password','ugroup_id','is_platform_admin','created_at','updated_at'] as $column)DB::statement('ALTER TABLE users ADD COLUMN '.$column.' TEXT');
foreach(['role','created_at','updated_at'] as $column)DB::statement('ALTER TABLE organization_users ADD COLUMN '.$column.' TEXT');
$app['config']->set('hashing',['driver'=>'bcrypt','bcrypt'=>['rounds'=>4]]);
$app->instance('hash',new Illuminate\Hashing\HashManager($app));
Illuminate\Support\Facades\Hash::clearResolvedInstance('hash');
require __DIR__.'/Tech4LearnContentController.php';
$centralController=new class extends App\Http\Controllers\Tech4LearnContentController {
 protected function configuration(Illuminate\Http\Request $r):array{return ['_platform'=>['organization_id'=>10]];}
 protected function reply(int $owner,array $data){return $data;}
};
$centralActor='cccccccc-1111-1111-1111-111111111111';$uuid=fn()=>(string)Illuminate\Support\Str::uuid();
$save=fn($id,$fields,$revision,$request)=>$service->saveCentralTaxonomy(10,$centralActor,'packages',$id,$fields,$revision,$request);
$deny=function(callable $call){try{$call();throw new RuntimeException('Expected central package rejection');}catch(Illuminate\Validation\ValidationException|Illuminate\Database\Eloquent\ModelNotFoundException|Symfony\Component\HttpKernel\Exception\HttpException $e){}};
$centralGroup=Group::where('organization_id',10)->firstOrFail();
$fields=['name'=>'Central free package','package_type'=>'free','status'=>true,'group_ids'=>[$centralGroup->id],'tag_ids'=>['Central tag'],'show_pdf_download'=>true,'pdf_title_text'=>'Preserve central paper title'];
$orgBefore=$service->record('packages',Package::findOrFail($packageSaved['id']));$workspaceCount=DB::table('tech4learn_workspaces')->count();
$key=$uuid();$created=$save(0,$fields,'new',$key);
check(Package::findOrFail($created['id'])->organization_id==10&&$created['fields']['group_ids']===[$centralGroup->id],'Central package and relationships have credential owner');
check($save(0,$fields,'new',$key)===$created,'Central package create retry is stable');
$tag=PackageTag::where('organization_id',10)->where('name','Central tag')->sole();
check(in_array((string)$tag->id,$created['fields']['tag_ids'],true),'New central tag belongs to the central owner');
$renamed=$save($created['id'],['name'=>'Renamed central package'],$created['revision'],$uuid());
check($renamed['fields']['pdf_title_text']==='Preserve central paper title'&&$renamed['fields']['group_ids']===$created['fields']['group_ids'],'Central package edit preserves settings and scope');
$deny(fn()=>$save($created['id'],['name'=>'Stale'],$created['revision'],$uuid()));
$deny(fn()=>$save($packageSaved['id'],['name'=>'Foreign'],$orgBefore['revision'],$uuid()));
foreach([['group_ids'=>[$group->id]],['tag_ids'=>[(string)$foreignPackageTag->id]],['tag_ids'=>'invalid'],['category_level_1'=>$category['id']],['organization_id'=>20],['package_type'=>'paid'],['photo'=>'/etc/passwd'],['description'=>'<script>invalid</script>']] as $invalid)$deny(fn()=>$save(0,array_replace($fields,$invalid),'new',$uuid()));
$disabledTag=PackageTag::create(['organization_id'=>10,'name'=>'Disabled tag','slug'=>'disabled-tag','status'=>false]);
$deny(fn()=>$save(0,array_replace($fields,['tag_ids'=>['Disabled tag']]),'new',$uuid()));
$paid=Package::create(['organization_id'=>10,'name'=>'Existing paid package','package_type'=>'paid']);
$deny(fn()=>$save($paid->id,['package_type'=>'free'],$service->record('packages',$paid)['revision'],$uuid()));
check($paid->fresh()->package_type==='paid','Central adapter cannot convert an existing paid package');
$nativeId=DB::table('tech4learn_central_users')->where('local_id',$centralActor)->value('external_id');
DB::table('users')->where('id',$nativeId)->update(['status'=>0]);$deny(fn()=>$save(0,$fields,'new',$key));DB::table('users')->where('id',$nativeId)->update(['status'=>1]);
check($save(0,$fields,'new',$key)===$created,'Restored author replays original outcome');
check($service->record('packages',Package::findOrFail($packageSaved['id']))===$orgBefore&&DB::table('tech4learn_workspaces')->count()===$workspaceCount,'Central package writes preserve organisation records and create no workspace');

$draft=$centralController->centralTaxonomy(Illuminate\Http\Request::create('/','GET'),'packages','new');
check($draft['record']['fields']['package_type']==='free'&&$draft['record']['revision']==='new','Central package draft defaults');
check($centralController->centralTaxonomy(Illuminate\Http\Request::create('/','GET'),'packages',(string)$created['id'])['record']===$renamed,'Central package read returns native snapshot');
$deny(fn()=>$centralController->centralTaxonomy(Illuminate\Http\Request::create('/','GET'),'packages',(string)$paid->id));
$deny(fn()=>$centralController->centralTaxonomy(Illuminate\Http\Request::create('/','GET'),'packages',(string)$packageSaved['id']));
$response=$centralController->centralTaxonomyWrite(Illuminate\Http\Request::create('/','POST',['actor_id'=>$centralActor,'fields'=>['name'=>'Controller rename'],'revision'=>$renamed['revision'],'request_id'=>$uuid()]),'packages',(string)$created['id']);
check($response['saved']&&$response['kind']==='packages'&&$response['record']['fields']['name']==='Controller rename','Central package write uses native controller envelope');
$choices=$controller->centralChoices(Illuminate\Http\Request::create('/','GET'),'packages');
check(in_array($created['id'],array_column($choices['items'],'id'),true)&&!in_array($packageSaved['id'],array_column($choices['items'],'id'),true)&&!in_array($paid->id,array_column($choices['items'],'id'),true),'Central package choices exclude foreign and paid records');
$tags=$controller->centralChoices(Illuminate\Http\Request::create('/','GET'),'package-tags');
check(in_array($tag->id,array_column($tags['items'],'id'),true)&&!in_array($foreignPackageTag->id,array_column($tags['items'],'id'),true)&&!in_array($disabledTag->id,array_column($tags['items'],'id'),true),'Central tag choices exclude foreign and disabled tags');
echo "Central package native authoring: scope, tags, metadata, paid denial and retries passed.\n";

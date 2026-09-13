<?php
// Actual model/copy checks use an isolated SQLite database, never the live database.
require __DIR__.'/test-content-copies.php';
require __DIR__.'/Tech4LearnPlatformController.php';
require __DIR__.'/Tech4LearnContentController.php';
$app=new Illuminate\Foundation\Application(__DIR__);
$app->instance('db',$db->getDatabaseManager());
Illuminate\Support\Facades\Facade::setFacadeApplication($app);
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
DB::statement('DROP TABLE tech4learn_workspaces');
DB::statement('CREATE TABLE tech4learn_workspaces(id TEXT PRIMARY KEY,source_organization_id INTEGER,organization_id INTEGER,restrictions TEXT)');
DB::statement('CREATE TABLE tech4learn_content_transfers(workspace_id TEXT,request_id TEXT,actor_id TEXT,direction TEXT,fingerprint TEXT,result TEXT,created_at TEXT,PRIMARY KEY(workspace_id,request_id))');
DB::statement('ALTER TABLE questions ADD COLUMN qtype_id INTEGER');
$org='11111111-1111-1111-1111-111111111111';$actor='22222222-2222-2222-2222-222222222222';
DB::table('tech4learn_workspaces')->insert(['id'=>$org,'source_organization_id'=>10,'organization_id'=>20,'restrictions'=>'[]']);
$controller=new class extends App\Http\Controllers\Tech4LearnContentController {
 protected function configuration(Request $r):array{return ['_platform'=>['organization_id'=>10]];}
 protected function reply(int $tenant,array $data){return $data;}
};
function rejects(int $status,callable $fn):void {try{$fn();}catch(Symfony\Component\HttpKernel\Exception\HttpException $e){check($e->getStatusCode()===$status,'Expected HTTP '.$status);return;}throw new RuntimeException('Expected rejection');}
$body=['actor_id'=>$actor,'request_id'=>'33333333-3333-3333-3333-333333333333','direction'=>'share','question_ids'=>[1]];
$send=fn($b)=>$controller->transfer(Request::create('/','POST',$b),$org);
$shared=$send($body);check($shared['count']===1,'Share completes');
$sharedId=$shared['items'][0]['target_id'];
check($send($body)===$shared,'Request replay returns original response');
check(DB::table('tech4learn_content_transfers')->count()===1,'One durable transfer record');
$history=$controller->history(Request::create('/','GET'),$org);
check(count($history['items'])===1 && $history['items'][0]['items'][0]['target_id']===$sharedId,'History retains source and destination provenance');
rejects(409,fn()=>$send(array_replace($body,['direction'=>'pull'])));
rejects(403,fn()=>$send(array_replace($body,['request_id'=>'44444444-4444-4444-4444-444444444444','question_ids'=>[1,$sharedId]])));
check(DB::table('tech4learn_content_transfers')->count()===1,'Mixed source selection has no partial commit');
$pulled=$send(array_replace($body,['request_id'=>'55555555-5555-5555-5555-555555555555','direction'=>'pull','question_ids'=>[$sharedId]]));
check((int)App\Models\Question::find($pulled['items'][0]['target_id'])->organization_id===10,'Pull destination is central');
$list=$controller->questions(Request::create('/','GET',['source'=>'organisation']),$org);
foreach($list['items'] as $row)check((int)App\Models\Question::find($row['id'])->organization_id===20,'Question list tenant isolation');
DB::table('tech4learn_workspaces')->where('id',$org)->update(['restrictions'=>'["questions"]']);
rejects(409,fn()=>$send(array_replace($body,['request_id'=>'66666666-6666-6666-6666-666666666666'])));
echo "Content API: bounded tenant reads, all-or-nothing ownership, request replay, provenance and pull passed.\n";

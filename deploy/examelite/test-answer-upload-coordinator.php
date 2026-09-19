<?php
require __DIR__.'/test-attempt-answers.php';
require_once rtrim($argv[2],'/\\').'/ExamStats.php';
require $argv[5];
require __DIR__.'/Tech4LearnStudentContext.php';
require __DIR__.'/Tech4LearnPrivateAnswerUpload.php';
require __DIR__.'/Tech4LearnAnswerUploadCoordinator.php';
require_once __DIR__.'/Tech4LearnPlatformController.php';
require __DIR__.'/Tech4LearnAnswerAttachments.php';
require __DIR__.'/Tech4LearnAttachmentController.php';
use Illuminate\Support\Facades\DB;
use Illuminate\Http\UploadedFile;
use App\Models\{Question,ExamResult,ExamStat};
$root=sys_get_temp_dir().'/t4l-upload-coordinator-'.bin2hex(random_bytes(12));mkdir($root,0700,true);
$app->useStoragePath($root.'/storage');$files=new Illuminate\Filesystem\Filesystem();
$app['config']->set('filesystems.disks.public',['driver'=>'local','root'=>$root.'/public','throw'=>true]);
$app->instance('filesystem',new Illuminate\Filesystem\FilesystemManager($app));
Illuminate\Support\Facades\Facade::clearResolvedInstance('filesystem');
$app->instance('log',new Psr\Log\NullLogger());
Illuminate\Support\Facades\Facade::clearResolvedInstance('log');
$app->instance(Illuminate\Contracts\Routing\ResponseFactory::class,new Illuminate\Routing\ResponseFactory($app['view'],$oldRedirect));
if(!DB::getSchemaBuilder()->hasColumn('exam_stats','uploaded_answer_path'))DB::statement('ALTER TABLE exam_stats ADD COLUMN uploaded_answer_path TEXT');
DB::table('qtypes')->insert(['id'=>91,'question_type'=>'Subjective','type'=>'S']);
$uploadQuestion=Question::create(['organization_id'=>20,'qtype_id'=>91,'question'=>'Synthetic written answer','si_answer1'=>'Reference answer']);
$paper->forceFill(['status'=>'Active','frontend_visible'=>true,'online_attempt_enabled'=>true,'timer_mode'=>'none','is_subject_timer'=>false,'duration'=>60,'end_date'=>null,'browser_tolerance'=>false,'allow_answer_change'=>true])->save();
$paper->questions()->attach($uploadQuestion->id);
$uploadAttempt=ExamResult::create(['organization_id'=>20,'student_id'=>$student->id,'exam_id'=>$paper->id,'start_time'=>now()->subMinutes(5),'total_test_time'=>60]);
$uploadStat=ExamStat::create(['organization_id'=>20,'student_id'=>$student->id,'exam_id'=>$paper->id,'exam_result_id'=>$uploadAttempt->id,'question_id'=>$uploadQuestion->id]);
$service=new App\Services\Tech4LearnAnswerUploadCoordinator();$revisions=new App\Services\Tech4LearnAttemptAnswers();
$credential=bin2hex(random_bytes(32));$configFile=$root.'/credential.json';
file_put_contents($configFile,json_encode(['_platform'=>['enabled'=>true,'organization_id'=>10,'token_hash'=>hash('sha256',$credential)]]));
DB::table('organizations')->where('id',10)->update(['domain'=>'central.example.test','status'=>'active']);
$controller=new class($configFile) extends App\Http\Controllers\Tech4LearnAttachmentController {
 public function __construct(private string $path){}protected function configPath():string{return $this->path;}
};
$call=function(string $action,array $body,?string $token=null)use($credential,$controller,$workspace){
 $request=Illuminate\Http\Request::create('https://central.example.test/api/tech4learn/v1/attachments/'.$workspace.'/'.$action,'POST',[],[],[],['HTTP_AUTHORIZATION'=>'Bearer '.($token??$credential),'CONTENT_TYPE'=>'application/json'],json_encode($body));
 return $controller->attachment($request,$workspace,$action)->getData(true);
};
$upload=function(string $text,string $id,string $revision)use($root,$service,$workspace,$learner,$uploadAttempt,$uploadQuestion){
 $path=tempnam($root,'input-');file_put_contents($path,$text);
 return $service->save($workspace,10,$learner,$uploadAttempt->id,$uploadQuestion->id,['request_id'=>$id,'revision'=>$revision,'exam_id'=>(int)$uploadAttempt->exam_id],new UploadedFile($path,'answer.txt','text/plain',null,true));
};
try {
 $revision=$revisions->revision($uploadStat->fresh());$id=$next();
 $identity=['learner_id'=>$learner,'exam_id'=>(int)$uploadAttempt->exam_id,'attempt_id'=>$uploadAttempt->id,'question_id'=>$uploadQuestion->id];
 $body=$identity+['request_id'=>$id,'revision'=>$revision,'base64'=>base64_encode('Synthetic attachment one.')];
 rejectAnswer(fn()=>$call('upload',$body,str_repeat('0',64)),'Attachment endpoint requires central credential');
 $saved=$call('upload',$body)['data'];
 check($call('upload',$body)['data']===$saved,'Credential-authenticated upload replays the native receipt');
 check(base64_decode($call('read',$identity+['asset'=>$saved['asset']])['data']['base64'])==='Synthetic attachment one.','Credential-authenticated attachment read returns exact private bytes');
 check($call('review',$identity+['asset'=>$saved['asset']])['error']['status']===403,'Review cannot read an open attempt');
 check($call('read',array_replace($identity,['exam_id'=>$identity['exam_id']+1])+['asset'=>$saved['asset']])['error']['status']===404,'Reader endpoint requires the granted exam');
 check($call('upload',array_replace($body,['base64'=>'not base64!']))['error']['status']===422,'Malformed attachment encoding is rejected');
 rejectAnswer(fn()=>$call('upload',$body+['path'=>'outside.txt']),'Client cannot supply a storage path');
 $router=new Illuminate\Routing\Router(new Illuminate\Events\Dispatcher($app),$app);$app->instance('router',$router);Illuminate\Support\Facades\Route::clearResolvedInstance('router');
 $router->prefix('api')->middleware('api')->group(function(){require __DIR__.'/tech4learn-routes.php';});
 foreach(['upload','read','review'] as $action){
  $route=$router->getRoutes()->match(Illuminate\Http\Request::create('https://central.example.test/api/tech4learn/v1/attachments/'.$workspace.'/'.$action,'POST'));
  check(str_ends_with($route->getActionName(),'Tech4LearnAttachmentController@attachment')&&in_array('throttle:120,1,t4l-answer-attachments:',$route->gatherMiddleware(),true),'Private attachment route and separate bounded throttle are registered');
 }
 check($saved['saved']&&$saved['attempt_id']===$uploadAttempt->id&&!isset($saved['path']),'Coordinator returns a bounded receipt without storage path');
 $directory=$root.'/storage/app/t4l-private-answers';$count=count($files->allFiles($directory));
 check($upload('Synthetic attachment one.',$id,$revision)===$saved&&count($files->allFiles($directory))===$count,'Lost-response retry creates no new file');
 rejectAnswer(fn()=>$upload('Changed bytes.',$id,$revision),'Receipt cannot be reused for different bytes');
 rejectAnswer(fn()=>$upload('Stale upload.',$next(),$revision),'Stale answer revision');
 $badPath=tempnam($root,'input-');file_put_contents($badPath,'Foreign fixture.');$badFile=new UploadedFile($badPath,'answer.txt','text/plain',null,true);
 rejectAnswer(fn()=>$service->save($workspace,99,$learner,$uploadAttempt->id,$uploadQuestion->id,['request_id'=>$next(),'revision'=>$saved['revision'],'exam_id'=>(int)$uploadAttempt->exam_id],$badFile),'Foreign source');
 rejectAnswer(fn()=>$service->save($workspace,10,'99999999-9999-9999-9999-999999999999',$uploadAttempt->id,$uploadQuestion->id,['request_id'=>$next(),'revision'=>$saved['revision'],'exam_id'=>(int)$uploadAttempt->exam_id],$badFile),'Unmapped learner');
 rejectAnswer(fn()=>$service->save($workspace,10,$learner,$uploadAttempt->id,$uploadQuestion->id,['request_id'=>$next(),'revision'=>$saved['revision'],'exam_id'=>(int)$uploadAttempt->exam_id+1],$badFile),'Attempt must belong to the granted exam');
 DB::table('tech4learn_workspaces')->where('id',$workspace)->update(['restrictions'=>'["taking"]']);
 rejectAnswer(fn()=>$upload('Synthetic attachment one.',$id,$revision),'Taking restriction denies replay');
 DB::table('tech4learn_workspaces')->where('id',$workspace)->update(['restrictions'=>'[]']);
 DB::table('qtypes')->where('id',91)->update(['type'=>'NAT','question_type'=>'Numerical Answer']);
 rejectAnswer(fn()=>$upload('Wrong question type.',$next(),$saved['revision']),'Non-subjective question');
 DB::table('qtypes')->where('id',91)->update(['type'=>'S','question_type'=>'Subjective']);
 $GLOBALS['t4lTestSubjectiveAllowed']=false;rejectAnswer(fn()=>$upload('Synthetic attachment one.',$id,$revision),'Feature revocation denies replay');$GLOBALS['t4lTestSubjectiveAllowed']=true;
 $currentPath=$uploadStat->fresh()->uploaded_answer_path;$currentRevision=$revisions->revision($uploadStat->fresh());
 DB::statement("CREATE TRIGGER fail_upload_receipt BEFORE INSERT ON tech4learn_attempt_requests BEGIN SELECT RAISE(ABORT, 'Synthetic receipt failure'); END");
 try{$upload('Must roll back with receipt.',$next(),$currentRevision);throw new RuntimeException('Expected receipt failure');}catch(Illuminate\Database\QueryException $error){}
 DB::statement('DROP TRIGGER fail_upload_receipt');
 check($uploadStat->fresh()->uploaded_answer_path===$currentPath&&count($files->allFiles($directory))===$count,'Receipt failure rolls back native reference and removes new private bytes');
 DB::statement("CREATE TRIGGER revoke_upload_access AFTER UPDATE ON exam_stats BEGIN UPDATE tech4learn_workspaces SET restrictions='[\"taking\"]'; END");
 rejectAnswer(fn()=>$upload('Access changes during native save.',$next(),$currentRevision),'Access is rechecked before the upload receipt commits');
 DB::statement('DROP TRIGGER revoke_upload_access');
 check($uploadStat->fresh()->uploaded_answer_path===$currentPath&&count($files->allFiles($directory))===$count,'Late access denial rolls back the native reference and private file');
 $uploadAttempt->end_time=now();$uploadAttempt->save();
 check(base64_decode($call('review',$identity+['asset'=>$saved['asset']])['data']['base64'])==='Synthetic attachment one.','Submitted attachment available to the review endpoint');
 check($upload('Synthetic attachment one.',$id,$revision)===$saved,'Accepted receipt can replay after submission without another write');
 rejectAnswer(fn()=>$upload('Late attachment.',$next(),$currentRevision),'New upload after submission');
 check(count($files->allFiles($directory))===$count,'Rejected and replayed requests preserve private files');
 echo "PASS: native private attachment coordinator, revision checks, exact replay, revoked capability and joint receipt/file rollback.\n";
} finally {
 $GLOBALS['t4lTestSubjectiveAllowed']=true;
 $resolved=realpath($root);$temp=realpath(sys_get_temp_dir());
 if(!$resolved||!$temp||!str_starts_with($resolved,$temp.DIRECTORY_SEPARATOR.'t4l-upload-coordinator-'))throw new RuntimeException('Unsafe fixture cleanup path');
 $files->deleteDirectory($root);
}

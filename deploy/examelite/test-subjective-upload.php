<?php
namespace {
if(PHP_SAPI!=='cli'||getenv('NODE_ENV')==='production'||!isset($argv[1],$argv[2]))throw new \RuntimeException('Local vendor and controller source paths required.');
require $argv[1];
}
namespace App\Support {
// Isolated host and feature context; no deployed application configuration.
class Tenant {public static function hostId($host){return 20;}}
class SaasAccess {public static function abortIfFeatureDisabled($feature):void{}}
}
namespace App\Models {
// Minimal storage models: the actual controller and Laravel validation/filesystem run.
class ExamResult extends \Illuminate\Database\Eloquent\Model {protected $table='exam_results';protected $guarded=[];public $timestamps=false;}
class ExamStats extends \Illuminate\Database\Eloquent\Model {protected $table='exam_stats';protected $guarded=[];public $timestamps=false;}
class Exam extends \Illuminate\Database\Eloquent\Model {protected $table='exams';protected $guarded=[];public $timestamps=false;}
}
namespace {
require $argv[2];
use Illuminate\Database\Capsule\Manager;
use Illuminate\Http\{Request,UploadedFile};
use Illuminate\Support\Facades\Facade;
function checkUpload($ok,$message){if(!$ok)throw new RuntimeException($message);}
$app=new Illuminate\Foundation\Application(__DIR__);
$db=new Manager($app);$db->addConnection(['driver'=>'sqlite','database'=>':memory:']);$db->setAsGlobal();$db->bootEloquent();
$schema=$db->schema();
$schema->create('questions',fn($t)=>$t->integer('id')->primary());
$schema->create('exams',function($t){$t->integer('id')->primary();$t->integer('organization_id');$t->boolean('allow_answer_change')->nullable();$t->integer('duration')->default(60);$t->text('end_date')->nullable();});
$schema->create('exam_results',function($t){$t->integer('id')->primary();$t->integer('student_id');$t->integer('organization_id');$t->integer('exam_id');$t->text('end_time')->nullable();$t->text('start_time');$t->integer('total_test_time')->nullable();});
$schema->create('exam_stats',function($t){$t->integer('id')->primary();$t->integer('exam_result_id');$t->integer('question_id');$t->integer('organization_id');$t->text('uploaded_answer_path')->nullable();$t->text('answer_locked_at')->nullable();});
$db->table('questions')->insert(['id'=>10]);
$db->table('exams')->insert(['id'=>1,'organization_id'=>20,'allow_answer_change'=>true]);
Carbon\Carbon::setTestNow(Carbon\Carbon::parse('2026-09-19 12:00:00','UTC'));
foreach([1,2] as $id){$db->table('exam_results')->insert(['id'=>$id,'student_id'=>$id,'organization_id'=>20,'exam_id'=>1,'start_time'=>'2026-09-19 11:45:00','total_test_time'=>60]);$db->table('exam_stats')->insert(['id'=>$id,'exam_result_id'=>$id,'question_id'=>10,'organization_id'=>20]);}
$root=sys_get_temp_dir().'/t4l-answer-upload-'.bin2hex(random_bytes(12));mkdir($root,0700,true);
$app->instance('config',new Illuminate\Config\Repository(['filesystems'=>['disks'=>['public'=>['driver'=>'local','root'=>$root.'/stored','throw'=>false]]]]));
$app['config']->set('database.default','default');
$app['config']->set('database.connections.default',['driver'=>'sqlite','database'=>':memory:']);
$app->instance('db',$db->getDatabaseManager());
$filesystem=new Illuminate\Filesystem\Filesystem();$app->instance('files',$filesystem);
$app->instance('filesystem',new Illuminate\Filesystem\FilesystemManager($app));
$app->instance('log',new Psr\Log\NullLogger());
$guard=new class {public int $student=1;public function id(){return $this->student;}};
$app->instance('auth',new class($guard){public function __construct(private $guard){}public function guard($name){return $this->guard;}});
$view=new Illuminate\View\Factory(new Illuminate\View\Engines\EngineResolver(),new Illuminate\View\FileViewFinder($filesystem,[__DIR__]),new Illuminate\Events\Dispatcher($app));
$url=new Illuminate\Routing\UrlGenerator(new Illuminate\Routing\RouteCollection(),Request::create('https://owned.example.test'));
$app->instance(Illuminate\Contracts\Routing\ResponseFactory::class,new Illuminate\Routing\ResponseFactory($view,new Illuminate\Routing\Redirector($url)));
Facade::setFacadeApplication($app);
$validator=new Illuminate\Validation\Factory(new Illuminate\Translation\Translator(new Illuminate\Translation\ArrayLoader(),'en'),$app);
$validator->setPresenceVerifier(new Illuminate\Validation\DatabasePresenceVerifier($db->getDatabaseManager()));
Request::macro('validate',function($rules)use($validator){return $validator->make($this->all(),$rules)->validate();});
$controller=new App\Http\Controllers\SubjectiveUploadController();
$send=function(int $attempt,string $text,bool $fail=false)use($root,$controller){
 $tmp=tempnam($root,'source-');file_put_contents($tmp,$text);
 $file=$fail?new class($tmp,'answer.txt','text/plain',null,true) extends UploadedFile {public function storeAs($path,$name=null,$options=[]){return false;}}:new UploadedFile($tmp,'answer.txt','text/plain',null,true);
 $request=Request::create('https://owned.example.test/subjective-upload','POST',['question_id'=>10,'exam_result_id'=>$attempt],[],['answer_file'=>$file]);
 return $controller->upload($request)->getData(true);
};
try {
 $first=$send(1,'First synthetic student answer.');$guard->student=2;
 $second=$send(2,'Second synthetic student answer.');
 checkUpload($first['success']&&$second['success'],'Both native uploads succeed: '.json_encode([$first,$second]));
 checkUpload($first['path']!==$second['path'],'Same-question uploads cannot overwrite another student');
 checkUpload(preg_match('#^student_answers/20_1_1_10_[a-f0-9]{40}\.txt$#D',$first['path']),'Upload names bind owner/student/attempt/question and random identity');
 checkUpload(file_get_contents($root.'/stored/'.$first['path'])==='First synthetic student answer.','First upload bytes survive the second upload');
 checkUpload(file_get_contents($root.'/stored/'.$second['path'])==='Second synthetic student answer.','Second upload retains its own bytes');
 $again=$send(2,'Updated synthetic answer.');checkUpload($again['success']&&$again['path']!==$second['path'],'Repeated uploads use distinct paths');
 $before=App\Models\ExamStats::find(2)->uploaded_answer_path;
 $failed=$send(2,'Synthetic failed disk write.',true);
 checkUpload(!$failed['success']&&App\Models\ExamStats::find(2)->uploaded_answer_path===$before,'Failed storage does not erase the saved answer path');
 $denied=$send(1,'Foreign answer attempt.');checkUpload(!$denied['success'],'Native ownership check denies another student attempt');
 $count=count($filesystem->allFiles($root.'/stored'));
 $db->table('exam_results')->where('id',2)->update(['end_time'=>'2026-09-19 12:00:00']);
 $closed=$send(2,'After submission.');checkUpload(!$closed['success'],'Submitted attempts cannot replace an attachment');
 $db->table('exam_results')->where('id',2)->update(['end_time'=>null]);
 $db->table('exams')->where('id',1)->update(['allow_answer_change'=>false]);
 $db->table('exam_stats')->where('id',2)->update(['answer_locked_at'=>'2026-09-19 12:00:00']);
 $locked=$send(2,'After answer locking.');checkUpload(!$locked['success'],'Locked answers cannot replace an attachment');
 checkUpload(count($filesystem->allFiles($root.'/stored'))===$count&&App\Models\ExamStats::find(2)->uploaded_answer_path===$before,'Rejected writes create no file and preserve the saved answer');
 $db->table('exams')->where('id',1)->update(['allow_answer_change'=>true]);
 $allowed=$send(2,'Changes explicitly allowed.');checkUpload($allowed['success'],'Native allow-answer-change setting permits attachment replacement');
 $db->table('exams')->where('id',1)->update(['allow_answer_change'=>null]);
 checkUpload($send(2,'Legacy setting defaults to allow.')['success'],'Null setting matches native answer persistence default');
 $before=App\Models\ExamStats::find(2)->uploaded_answer_path;$count=count($filesystem->allFiles($root.'/stored'));
 $db->table('exams')->where('id',1)->update(['duration'=>120]);
 $db->table('exam_results')->where('id',2)->update(['start_time'=>'2026-09-19 11:00:00']);
 checkUpload(!$send(2,'At exact original deadline.')['success'],'Captured attempt duration is not extended by later exam edits');
 $db->table('exam_results')->where('id',2)->update(['start_time'=>'2026-09-19 12:01:00']);
 checkUpload(!$send(2,'Before start.')['success'],'Future start cannot accept an attachment');
 $db->table('exam_results')->where('id',2)->update(['start_time'=>'2026-09-19 11:45:00']);
 $db->table('exams')->where('id',1)->update(['end_date'=>'2026-09-19 12:00:00']);
 checkUpload(!$send(2,'At exam closing time.')['success'],'Exam closing deadline rejects uploads');
 checkUpload(count($filesystem->allFiles($root.'/stored'))===$count&&App\Models\ExamStats::find(2)->uploaded_answer_path===$before,'Deadline rejection preserves both files and saved path');
 echo "PASS: native subjective uploads isolate files and reject failed storage, foreign, submitted, locked and expired writes.\n";
} finally {
 $resolved=realpath($root);$temp=realpath(sys_get_temp_dir());
 if(!$resolved||!$temp||!str_starts_with($resolved,$temp.DIRECTORY_SEPARATOR.'t4l-answer-upload-'))throw new RuntimeException('Unsafe fixture cleanup path');
 $filesystem->deleteDirectory($root);
 Carbon\Carbon::setTestNow();
}
}

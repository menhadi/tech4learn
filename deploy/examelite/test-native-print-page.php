<?php
// Opt-in local fixture. Load native definitions, never the deployed bootstrap.
if(PHP_SAPI!=='cli'||getenv('NODE_ENV')==='production'||!isset($argv[8]))throw new InvalidArgumentException('Supply authoring paths, cache, lifecycle, image resolver and a new output HTML path.');
$output=$argv[8];
if(file_exists($output)||!is_dir(dirname($output)))throw new InvalidArgumentException('Output must be new in an existing local directory.');
require __DIR__.'/test-exam-authoring.php';
require $argv[5];
require $argv[6];
require $argv[7];
foreach(['ExamGroupingService','MathContentNormalizer','ExamPdfImageEmbedder','ExamPrintController'] as $name)require_once dirname($argv[3]).'/'.$name.'.php';
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use App\Models\{Exam,Question,Language};
DB::statement('CREATE TABLE configurations(id INTEGER PRIMARY KEY,organization_id INTEGER,name TEXT,updated_at TEXT)');
if(!DB::getSchemaBuilder()->hasColumn('organizations','settings'))DB::statement('ALTER TABLE organizations ADD COLUMN settings TEXT');
if(!DB::getSchemaBuilder()->hasColumn('exam_questions','id')){
 DB::statement('ALTER TABLE exam_questions ADD COLUMN id INTEGER');
 DB::statement('UPDATE exam_questions SET id=rowid');
}
$app->instance('files',new Illuminate\Filesystem\Filesystem());
$app->instance(Illuminate\Contracts\Routing\ResponseFactory::class,new Illuminate\Routing\ResponseFactory($app['view'],$app['redirect']));
$app['config']->set('app.name','Synthetic Academy');
DB::table('organizations')->where('id',20)->update(['settings'=>json_encode(['exam_pdf_template'=>['show_website'=>'hide','show_logo'=>'hide']])]);
$paper=Exam::create(['organization_id'=>20,'name'=>'Native print acceptance','slug'=>'native-print-acceptance','status'=>'Inactive','duration'=>60,'grouping_mode'=>'none','timer_mode'=>'none']);
$english=Language::firstOrCreate(['organization_id'=>20,'code'=>'en'],['name'=>'English','is_enabled'=>true]);
$paper->languages()->attach($english->id,['translation_status'=>'ready']);
$q=Question::create(['organization_id'=>20,'qtype_id'=>1,'question'=>'<p>Evaluate the polynomial \\(x^2+2x+1\\) at x = 1.</p>','option1'=>'0','option2'=>'2','option3'=>'4','option4'=>'8','marks'=>4,'negative_marks'=>1]);
$paper->questions()->attach($q->id);
$request=Request::create('https://owned.example.test/print/native-print-acceptance?pdf_render=1&lang=en');
$app->instance('request',$request);$url->setRequest($request);
// The constructor dependency is only used by download/access methods; print itself
// still runs unchanged, including native language, grouping and HTML generation.
$controller=(new ReflectionClass(App\Http\Controllers\ExamPrintController::class))->newInstanceWithoutConstructor();
$response=$controller->print($request,$paper->slug);
check($response instanceof Illuminate\Http\Response&&$response->getStatusCode()===200,'Actual native print controller returns HTML');
$html=$response->getContent();
foreach(['Native print acceptance','Question 1','option-content">0','MathJax/MathJax.js','x^2+2x+1'] as $text)check(str_contains($html,$text),'Native print retains '.$text);
check(!str_contains($html,'Correct Answer'),'Question paper does not reveal solutions');
$foreign=Request::create('https://central.example.test/print/native-print-acceptance?pdf_render=1');
check(!str_contains((string)$controller->print($foreign,$paper->slug),'Evaluate the polynomial'),'Foreign organisation cannot read the paper');
if(file_put_contents($output,$html)===false)throw new RuntimeException('Cannot write synthetic print HTML');
echo "PASS: actual native print controller, language/grouping/normalisation, zero option and foreign-organisation isolation.\n";

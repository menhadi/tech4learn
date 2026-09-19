<?php
if(PHP_SAPI!=='cli'||getenv('NODE_ENV')==='production'||!isset($argv[1],$argv[2]))throw new RuntimeException('Supply local vendor and native extractor paths.');
require $argv[1];require __DIR__.'/Tech4LearnNativeAnswerExtraction.php';
$app=new Illuminate\Foundation\Application(__DIR__);
function checkExtraction($ok,$message){if(!$ok)throw new RuntimeException($message);}
function denyExtraction(callable $call,int $status,?string $message=null){try{$call();throw new RuntimeException('Expected extraction failure');}catch(Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $e){checkExtraction($e->getStatusCode()===$status,'Expected bounded failure status');if($message!==null)checkExtraction($e->getMessage()===$message,'Expected safe extraction guidance');}}
function extractor(string $script,float $timeout=20,?array $paths=null){return new class($script,$timeout,$paths) extends App\Services\Tech4LearnNativeAnswerExtraction {
 public function __construct(private string $source,private float $seconds,private ?array $paths){}
 protected function script():string{return $this->source;}
 protected function timeout():float{return $this->seconds;}
 protected function languagePaths():array{return $this->paths??parent::languagePaths();}
};}
$native=extractor(realpath($argv[2]));
$before=glob(sys_get_temp_dir().'/t4l-extract-*');sort($before);
checkExtraction($native->text("Synthetic   answer\nwith  spaces.",'text/plain')===['text'=>'Synthetic answer with spaces.'],'Actual native text extraction and cleaning');
denyExtraction(fn()=>$native->text('','text/plain'),422);
denyExtraction(fn()=>$native->text('Text','text/html'),422);
denyExtraction(fn()=>$native->text('Text','text/plain','eng; command'),422);
denyExtraction(fn()=>$native->text(str_repeat('x',10485761),'text/plain'),422);
$fixture=tempnam(sys_get_temp_dir(),'t4l-extraction-script-');
$languageConfig=tempnam(sys_get_temp_dir(),'t4l-extraction-languages-');
try {
 $english=[['code'=>'eng','name'=>'English']];
 checkExtraction(extractor($fixture,20,[])->languages()===$english,'Missing native configuration offers English only');
 file_put_contents($languageConfig,json_encode(['languages'=>[
  ['code'=>'hin','name'=>'Hindi','google'=>'hi','ocrspace'=>'hin','tesseract'=>'hin','private'=>'never expose'],
  ['code'=>'hin','name'=>'Hindi (native)'],['code'=>'invalid;command','name'=>'Rejected'],
 ]]));
 $configured=extractor($fixture,20,[$languageConfig,$fixture]);
 checkExtraction($configured->languages()===array_merge($english,[['code'=>'hin','name'=>'Hindi (native)']]),'Native precedence, duplicate replacement and public labels only');
 file_put_contents($fixture,'<?php echo json_encode(["success"=>true,"text"=>$_POST["lang"]]);');
 checkExtraction($configured->text('Test','text/plain','hin')===['text'=>'hin'],'Configured language reaches the native extractor');
 denyExtraction(fn()=>$configured->text('Test','text/plain','ben'),422);
 denyExtraction(fn()=>$configured->text('Test','text/plain','hin+eng'),422);
 foreach(['not json',str_repeat('x',65537),json_encode(['languages'=>[['code'=>'hin','name'=>"bad\0label"]]])] as $invalidConfig){
  file_put_contents($languageConfig,$invalidConfig);
  denyExtraction(fn()=>$configured->languages(),503);
 }
 foreach([
  ['echo "not json";',503],
  ['echo json_encode(["success"=>false,"error"=>"private secret"]);',422],
  ['echo json_encode(["success"=>true,"text"=>str_repeat("x",20001)]);',422],
  ['echo str_repeat("x",150000);',422],
  ['exit(9);',503],
 ] as [$code,$status]){
  file_put_contents($fixture,'<?php '.$code);denyExtraction(fn()=>extractor($fixture)->text('Test','text/plain'),$status);
 }
 foreach([''," \n\t", "text\0text"] as $unreadable){
  file_put_contents($fixture,'<?php echo '.var_export(json_encode(['success'=>true,'text'=>$unreadable]),true).';');
  denyExtraction(fn()=>extractor($fixture)->text('Test','text/plain'),422,'Text could not be extracted.');
 }
 file_put_contents($fixture,'<?php usleep(1000000); echo json_encode(["success"=>true,"text"=>"too late"]);');
 denyExtraction(fn()=>extractor($fixture,0.1)->text('Test','text/plain'),503);
 file_put_contents($fixture,'<?php file_put_contents(sys_get_temp_dir()."/native-temporary-image.png","fixture"); echo json_encode(["success"=>true,"text"=>"ok","secret"=>"never return"]);');
 checkExtraction(extractor($fixture)->text('Test','text/plain')===['text'=>'ok'],'Native extras never leave the adapter');
}finally{unlink($fixture);unlink($languageConfig);}
$after=glob(sys_get_temp_dir().'/t4l-extract-*');sort($after);
checkExtraction($after===$before,'Success, invalid output and timeout clean private temporary files');
echo "PASS: native text extractor, input/output bounds, timeout and private temporary cleanup.\n";

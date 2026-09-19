<?php
// Real native URL signer and print action, with synthetic data and key only.
if(!isset($argv[9])||file_exists($argv[9])||!is_dir(dirname($argv[9])))throw new InvalidArgumentException('Supply the print fixture arguments and a new solution HTML path.');
require __DIR__.'/test-native-print-page.php';
require_once dirname($argv[3]).'/QuestionAnswerEvaluator.php';
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use App\Services\ExamDocumentLifecycleService;
// Register Laravel's actual request signature method, not a success stub.
(new Illuminate\Foundation\Providers\FoundationServiceProvider($app))->registerRequestSignatureValidation();
$url->setKeyResolver(fn()=>'synthetic-local-print-fixture-key');
$routes->add((new Illuminate\Routing\Route(['GET'],'print/{id}',fn()=>null))->name('exam.print'));
$routes->refreshNameLookups();
$q->forceFill(['correct_option_indices'=>[3],'explanation'=>'Substituting x = 1 gives \\(1+2+1=4\\).'])->save();
$build=new App\Models\ExamPdfBuild(['exam_id'=>$paper->id,'language_id'=>$english->id,'document_type'=>'solutions']);
$build->setRelation('exam',$paper)->setRelation('package',null);
$app->instance('request',$request);$url->setRequest($request);
$signed=app(ExamDocumentLifecycleService::class)->printUrl($build);
$valid=Request::create($signed);
check($valid->hasValidSignature(),'Native lifecycle generates a valid signed print URL');
$solution=$controller->print($valid,$paper->slug);
check($solution instanceof Illuminate\Http\Response&&$solution->getStatusCode()===200,'Valid native signature opens the solution paper');
$solutionHtml=$solution->getContent();
foreach(['Correct Answer','<strong>C.</strong> 4','Substituting x = 1','Solutions'] as $text)check(str_contains($solutionHtml,$text),'Native solution contains '.$text);
parse_str(parse_url($signed,PHP_URL_QUERY),$parameters);
$expired=$url->temporarySignedRoute('exam.print',now()->subMinute(),['id'=>$paper->slug,'solution'=>1,'pdf_render'=>1,'lang'=>$english->id]);
$unsigned='https://owned.example.test/print/'.$paper->slug.'?solution=1&pdf_render=1';
$cases=[
 'unsigned'=>$unsigned,
 'expired'=>$expired,
 'host changed'=>str_replace('owned.example.test','central.example.test',$signed),
 'language changed'=>$signed.'&lang=999',
 'signature changed'=>str_replace($parameters['signature'],str_repeat('0',64),$signed),
];
foreach($cases as $name=>$address){
 try{$controller->print(Request::create($address),$paper->slug);throw new RuntimeException('Expected signature denial: '.$name);}
 catch(Symfony\Component\HttpKernel\Exception\HttpException $error){check($error->getStatusCode()===403,'Rejected '.$name.' solution request');}
}
// A correctly signed URL for another tenant still cannot expose this paper.
$url->setRequest(Request::create('https://central.example.test/'));
$foreignSigned=$url->temporarySignedRoute('exam.print',now()->addMinute(),['id'=>$paper->slug,'solution'=>1,'pdf_render'=>1]);
check(!str_contains((string)$controller->print(Request::create($foreignSigned),$paper->slug),'Correct Answer'),'Valid signature does not override organisation ownership');
if(file_put_contents($argv[9],$solutionHtml)===false)throw new RuntimeException('Cannot write synthetic solution HTML');
echo "PASS: native lifecycle signatures and solution print; unsigned/expired/tampered/foreign requests denied.\n";

<?php
if(PHP_SAPI!=='cli'||getenv('NODE_ENV')==='production'||!isset($argv[1],$argv[2]))throw new RuntimeException('Supply local vendor and native application source paths.');
require $argv[1];
require rtrim($argv[2],'/\\').'/Models/Configuration.php';
require rtrim($argv[2],'/\\').'/Support/AiProvider.php';
require __DIR__.'/Tech4LearnAiSettingsView.php';
$app=new Illuminate\Foundation\Application(__DIR__);
Illuminate\Database\Eloquent\Model::encryptUsing(new Illuminate\Encryption\Encrypter(random_bytes(32),'AES-256-CBC'));
function checkAiView($ok,$message){if(!$ok)throw new RuntimeException($message);}
function denyAiView(callable $call,int $status){try{$call();throw new RuntimeException('Expected denial');}catch(Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $e){checkAiView($e->getStatusCode()===$status,'Unexpected denial');}}
$secret=bin2hex(random_bytes(32));
$configuration=new App\Models\Configuration();
$configuration->forceFill(['organization_id'=>10,'openai_api_key'=>$secret,'openai_model'=>'configured-test-model',
 'deepseek_api_key'=>'  ','deepseek_vision_model'=>'configured-test-vision','google_gemini_api_key'=>null,
 'ai_provider_priority'=>['openai','google','deepseek','anthropic'],
 'ai_task_priorities'=>['translation'=>['google','openai','anthropic','deepseek']],
 'email'=>'private@example.invalid','messaging_credentials'=>['secret'=>$secret]]);
$before=$configuration->getAttributes();$service=new App\Services\Tech4LearnAiSettingsView();
$view=$service->read($configuration,10);
checkAiView(array_keys($view)===['providers','priority','task_priorities'],'Only approved fields leave the projection');
checkAiView($view['priority']===App\Support\AiProvider::priority($configuration),'Native priority remains authoritative');
checkAiView($view['task_priorities']['translation']===['google','openai','anthropic','deepseek'],'Task-specific native ordering');
checkAiView($view['task_priorities']['subjective_assessment']===$view['priority'],'Native task fallback');
checkAiView($view['providers'][1]===['code'=>'openai','credential_saved'=>true,'configured_model'=>'configured-test-model','configured_vision_model'=>null],'Saved credential is represented only as a boolean');
checkAiView($view['providers'][0]['configured_model']===null&&!$view['providers'][0]['credential_saved'],'Missing model is not replaced with an invented provider default');
checkAiView(!$view['providers'][2]['credential_saved']&&$view['providers'][2]['configured_vision_model']==='configured-test-vision','Blank keys and separate native vision model');
$encoded=json_encode($view,JSON_THROW_ON_ERROR);
checkAiView(!str_contains($encoded,$secret)&&!str_contains($encoded,'private@example.invalid'),'Credentials and unrelated settings never leave');
checkAiView($configuration->getAttributes()===$before,'Reading does not mutate native settings');
denyAiView(fn()=>$service->read($configuration,20),403);
denyAiView(fn()=>$service->read($configuration,0),403);
$configuration->openai_model="bad\0model";
denyAiView(fn()=>$service->read($configuration,10),503);
echo "PASS: native AI settings projection, provider/task priority, owner check and credential minimisation.\n";

<?php
namespace App\Services;

use Symfony\Component\Process\Process;

/** Bounded adapter to ExamElite's existing extractor; never grades or saves an answer. */
class Tech4LearnNativeAnswerExtraction
{
 protected function script():string {return public_path('extract_file.php');}
 protected function binary():string {return PHP_SAPI==='cli'?PHP_BINARY:PHP_BINDIR.DIRECTORY_SEPARATOR.'php';}
 protected function timeout():float {return 20;}

 public function text(string $bytes,string $mime,string $language='eng'):array {
  $extensions=['text/plain'=>'txt','application/pdf'=>'pdf','image/jpeg'=>'jpg','image/png'=>'png',
   'application/msword'=>'doc','application/vnd.openxmlformats-officedocument.wordprocessingml.document'=>'docx'];
  abort_unless(isset($extensions[$mime])&&strlen($bytes)>0&&strlen($bytes)<=10485760,422);
  abort_unless(preg_match('/^[a-z]{3}(?:\+[a-z]{3}){0,3}$/D',$language),422);
  $script=$this->script();$binary=$this->binary();abort_unless(is_file($script)&&is_readable($script)&&is_file($binary)&&is_executable($binary),503);
  $base=realpath(sys_get_temp_dir());abort_unless(is_string($base),503);
  $directory=$base.DIRECTORY_SEPARATOR.'t4l-extract-'.bin2hex(random_bytes(16));
  abort_unless(mkdir($directory,0700),503);$process=null;
  try {
   $file=$directory.DIRECTORY_SEPARATOR.'answer.'.$extensions[$mime];
   abort_unless(file_put_contents($file,$bytes)===strlen($bytes)&&chmod($file,0600),503);
   // Run the unmodified native script in a separate process because it calls exit.
   $wrapper='$_SERVER["REQUEST_METHOD"]="POST"; $_POST=["lang"=>$argv[3]]; $_GET=[]; $_FILES=["file"=>["name"=>basename($argv[1]),"tmp_name"=>$argv[1],"error"=>0,"size"=>filesize($argv[1])]]; require $argv[2];';
   $process=new Process([$binary,'-d','memory_limit=128M','-r',$wrapper,'--',$file,$script,$language],dirname($script),['TMPDIR'=>$directory,'TMP'=>$directory,'TEMP'=>$directory]);
   $process->setTimeout($this->timeout());$process->setIdleTimeout($this->timeout());
   $output='';$count=0;
   $process->run(function($type,$chunk)use(&$output,&$count){
    $count+=strlen($chunk);abort_unless($count<=128000,422,'Extracted text exceeds the answer limit.');
    if($type===Process::OUT)$output.=$chunk;
   });
   abort_unless($process->isSuccessful(),503);
   try{$result=json_decode($output,true,16,JSON_THROW_ON_ERROR);}catch(\JsonException){abort(503);}
   abort_unless(is_array($result)&&($result['success']??false)===true&&is_string($result['text']??null),422,'Text could not be extracted.');
   $text=$result['text'];abort_unless(trim($text)!==''&&strlen($text)<=20000&&!str_contains($text,"\0"),422,'Extracted text exceeds the answer limit.');
   return ['text'=>$text];
  }catch(\Symfony\Component\Process\Exception\ProcessTimedOutException){
   abort(503,'Text extraction timed out.');
  }finally{
   if($process&&$process->isRunning())$process->stop(0);
   // Delete only this invocation's verified private directory, including native temporary images.
   $resolved=realpath($directory);
   if($resolved&&$resolved===$directory&&!is_link($directory)&&dirname($resolved)===$base)
    (new \Illuminate\Filesystem\Filesystem())->deleteDirectory($directory);
  }
 }
}

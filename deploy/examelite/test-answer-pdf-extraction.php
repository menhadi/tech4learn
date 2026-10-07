<?php
// Isolated document smoke test. Never bootstrap the configured native application.
if(PHP_SAPI!=='cli'||getenv('NODE_ENV')==='production'||!isset($argv[1],$argv[2]))throw new RuntimeException('Supply local vendor and native extractor paths.');
require $argv[1];
require __DIR__.'/Tech4LearnNativeAnswerExtraction.php';
$app=new Illuminate\Foundation\Application(__DIR__);
function checkPdfExtraction(bool $ok,string $message):void {if(!$ok)throw new RuntimeException($message);}
function syntheticAnswerPdf(string $answer):string {
 $literal=str_replace(['\\','(',')'],['\\\\','\\(','\\)'],$answer);
 $stream="BT /F1 12 Tf 40 760 Td (".$literal.") Tj ET\n";
 $objects=['<< /Type /Catalog /Pages 2 0 R >>','<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
  '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>',
  '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>','<< /Length '.strlen($stream).' >>' . "\nstream\n".$stream.'endstream'];
 $pdf="%PDF-1.4\n";$offsets=[0];
 foreach($objects as $i=>$object){$offsets[]=strlen($pdf);$pdf.=($i+1)." 0 obj\n".$object."\nendobj\n";}
 $xref=strlen($pdf);$pdf.="xref\n0 6\n0000000000 65535 f \n";
 foreach(array_slice($offsets,1) as $offset)$pdf.=sprintf("%010d 00000 n \n",$offset);
 return $pdf."trailer\n<< /Size 6 /Root 1 0 R >>\nstartxref\n".$xref."\n%%EOF\n";
}
$source=realpath($argv[2]);checkPdfExtraction(is_string($source)&&is_file($source),'Missing native extractor');
$directory=sys_get_temp_dir().DIRECTORY_SEPARATOR.'t4l-pdf-extraction-'.bin2hex(random_bytes(16));
checkPdfExtraction(mkdir($directory,0700),'Cannot create isolated fixture directory');
$before=glob(sys_get_temp_dir().'/t4l-extract-*');sort($before);
try {
 $script=$source;$compatibility='unmodified native script';
 if(PHP_OS_FAMILY==='Windows'){
  // The native Linux shell redirects stderr to /dev/null, which cmd.exe cannot
  // open. Only adapt that platform-specific sink in a private temporary copy.
  $native=file_get_contents($source);
  checkPdfExtraction(substr_count($native,'2>/dev/null')>=1,'Unknown native stderr shape');
  $script=$directory.DIRECTORY_SEPARATOR.'extract_file.php';
  checkPdfExtraction(file_put_contents($script,str_replace('2>/dev/null','2>NUL',$native))!==false,'Cannot prepare native platform copy');
  $compatibility='native script with Windows stderr-sink adaptation only';
 }
 $extractor=new class($script) extends App\Services\Tech4LearnNativeAnswerExtraction {
  public function __construct(private string $source){}
  protected function script():string{return $this->source;}
  protected function languagePaths():array{return [];}
 };
 $answer='Two pairs each have two items, giving four.';
 $pdf=syntheticAnswerPdf($answer);
 checkPdfExtraction((new finfo(FILEINFO_MIME_TYPE))->buffer($pdf)==='application/pdf','Fixture must be a real PDF');
 checkPdfExtraction($extractor->text($pdf,'application/pdf')===['text'=>$answer],'Native PDF extraction did not return the synthetic answer; verify pdftotext availability');
 // The adapter must keep native parser failure bounded and retain no temp files.
 try{$extractor->text('%PDF-1.4 invalid document','application/pdf');throw new RuntimeException('Malformed PDF accepted');}
 catch(Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $e){checkPdfExtraction($e->getStatusCode()===422&&$e->getMessage()==='Text could not be extracted.','Unsafe PDF failure guidance');}
 $after=glob(sys_get_temp_dir().'/t4l-extract-*');sort($after);
 checkPdfExtraction($after===$before,'Native extraction left private temporary files');
 echo "PASS: real PDF bytes → bounded private adapter → native pdftotext extraction; malformed-file guidance and temporary cleanup (".$compatibility.").\n";
}finally{
 $resolved=realpath($directory);$base=realpath(sys_get_temp_dir());
 if($resolved&&$base&&dirname($resolved)===$base&&!is_link($directory)&&preg_match('/^t4l-pdf-extraction-[a-f0-9]{32}$/D',basename($resolved)))
  (new Illuminate\Filesystem\Filesystem())->deleteDirectory($resolved);
}

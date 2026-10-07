<?php
namespace App\Services;
use Illuminate\Http\UploadedFile;
use Symfony\Component\Process\Process;
class StudentAnswerTextExtractor
{
    public function extract(UploadedFile $file): string
    {
        $path=$file->getRealPath();
        $extension=strtolower($file->getClientOriginalExtension());
        if($extension==='txt')$text=file_get_contents($path);
        elseif($extension==='docx') {
            $zip=new \ZipArchive;
            if($zip->open($path)!==true)throw new \RuntimeException('Answer document could not be read.');
            try {
                $entry=$zip->statName('word/document.xml');
                if(!$entry || $entry['size']>524288)throw new \RuntimeException('Answer document is too large to extract.');
                $xml=$zip->getFromName('word/document.xml');
                if($xml===false || stripos($xml,'<!DOCTYPE')!==false)throw new \RuntimeException('Answer document could not be read.');
                $text=html_entity_decode(strip_tags(str_replace('</w:p>',"\n",$xml)),ENT_QUOTES|ENT_XML1,'UTF-8');
            } finally {$zip->close();}
        } else {
            $command=match($extension) {
                'pdf'=>['pdftotext','-enc','UTF-8',$path,'-'],
                'doc'=>['antiword',$path],
                'jpg','jpeg','png'=>['tesseract',$path,'stdout','-l','eng','--psm','6'],
                default=>throw new \RuntimeException('Unsupported answer file type.'),
            };
            $process=new Process($command);$process->setTimeout(20);$process->setIdleTimeout(10);
            $text='';$bytes=0;
            try {
                $process->run(function($type,$chunk)use(&$text,&$bytes){
                    $bytes+=strlen($chunk);
                    if($bytes>524288)throw new \RuntimeException('Extracted answer is too large.');
                    if($type===Process::OUT)$text.=$chunk;
                });
            } catch(\Throwable $error) {$process->stop(0);throw new \RuntimeException('Answer extraction is unavailable or exceeded its limit.');}
            if(!$process->isSuccessful())throw new \RuntimeException('Answer extraction is unavailable for this file.');
        }
        if(strlen($text)>524288 || !mb_check_encoding($text,'UTF-8'))throw new \RuntimeException('Answer text is too large or not UTF-8.');
        $text=trim(str_replace(["\r\n","\r","\f"],"\n",$text));
        if($text==='')throw new \RuntimeException('No answer text was found.');
        return $text;
    }
}

<?php
namespace Tests\Unit;

use App\Services\StudentAnswerTextExtractor;
use Illuminate\Http\UploadedFile;
use PHPUnit\Framework\TestCase;

class StudentAnswerTextExtractorTest extends TestCase
{
    public function test_plain_text_normalizes_line_endings(): void
    {
        $file=UploadedFile::fake()->createWithContent('answer.txt',"  Synthetic\r\nanswer\ftext\f  ");
        $this->assertSame("Synthetic\nanswer\ntext",(new StudentAnswerTextExtractor)->extract($file));
    }

    public function test_oversized_text_is_rejected(): void
    {
        $this->expectException(\RuntimeException::class);
        (new StudentAnswerTextExtractor)->extract(UploadedFile::fake()->createWithContent('answer.txt',str_repeat('x',524289)));
    }

    public function test_invalid_utf8_is_rejected(): void
    {
        $this->expectException(\RuntimeException::class);
        (new StudentAnswerTextExtractor)->extract(UploadedFile::fake()->createWithContent('answer.txt',"\xff\xfe"));
    }

    public function test_empty_text_is_rejected(): void
    {
        $this->expectException(\RuntimeException::class);
        (new StudentAnswerTextExtractor)->extract(UploadedFile::fake()->createWithContent('answer.txt'," \r\n\t"));
    }

    public function test_docx_expansion_limit_is_checked_before_extraction(): void
    {
        $this->assertRejectedDocument('<w:document>'.str_repeat('x',524289).'</w:document>');
    }

    public function test_docx_external_entity_declaration_is_rejected(): void
    {
        $this->assertRejectedDocument('<!DOCTYPE answer [<!ENTITY secret SYSTEM "file:///unavailable">]><w:document>&secret;</w:document>');
    }

    private function assertRejectedDocument(string $xml): void
    {
        $path=tempnam(sys_get_temp_dir(),'synthetic-answer-');
        try {
            $zip=new \ZipArchive;
            $this->assertTrue($zip->open($path,\ZipArchive::OVERWRITE)===true);
            $zip->addFromString('word/document.xml',$xml);
            $zip->close();
            $this->expectException(\RuntimeException::class);
            (new StudentAnswerTextExtractor)->extract(new UploadedFile($path,'answer.docx',null,null,true));
        } finally {unlink($path);}
    }
}

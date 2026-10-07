<?php

namespace Tests\Unit;

use App\Models\AdmissionExamDefinition;
use App\Services\AdmissionOfficialSourceProbeService;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class AdmissionOfficialSourceProbeServiceTest extends TestCase
{
    private AdmissionExamDefinition $exam;

    protected function setUp(): void
    {
        parent::setUp();

        $this->exam = new AdmissionExamDefinition([
            'name' => 'NEET UG',
            'official_domains' => ['neet.nta.nic.in'],
            'document_delivery_domains' => ['cdnbbsr.s3waas.gov.in'],
        ]);
    }

    public function test_it_verifies_listing_link_pdf_signature_and_hash(): void
    {
        $listing = 'https://neet.nta.nic.in/documents/';
        $source = 'https://cdnbbsr.s3waas.gov.in/path/result.pdf';
        $pdf = "%PDF-1.7\nprobe";

        Http::fake([
            $listing => Http::response('<a href="'.$source.'">Result</a>', 200, ['Content-Type' => 'text/html']),
            $source => Http::response($pdf, 200, ['Content-Type' => 'application/pdf']),
        ]);

        $result = app(AdmissionOfficialSourceProbeService::class)
            ->probe($this->exam, $source, $listing);

        self::assertTrue($result['listing_verified']);
        self::assertSame('pdf', $result['format']);
        self::assertSame(strlen($pdf), $result['size_bytes']);
        self::assertSame(hash('sha256', $pdf), $result['sha256']);
    }

    public function test_it_rejects_a_cdn_file_not_linked_by_the_authority_page(): void
    {
        $listing = 'https://neet.nta.nic.in/documents/';
        $source = 'https://cdnbbsr.s3waas.gov.in/path/result.pdf';

        Http::fake([
            $listing => Http::response('<html><body>No matching file</body></html>'),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('does not link');

        app(AdmissionOfficialSourceProbeService::class)
            ->probe($this->exam, $source, $listing);
    }

    public function test_it_rejects_false_pdf_content(): void
    {
        $source = 'https://neet.nta.nic.in/result.pdf';

        Http::fake([
            $source => Http::response('<html>Error</html>', 200, ['Content-Type' => 'application/pdf']),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('no PDF signature');

        app(AdmissionOfficialSourceProbeService::class)->probe($this->exam, $source);
    }
}

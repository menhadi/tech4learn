<?php

namespace Tests\Unit;

use App\Models\AdmissionExamDefinition;
use App\Services\AdmissionOfficialSourcePolicy;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class AdmissionOfficialSourcePolicyTest extends TestCase
{
    private AdmissionExamDefinition $exam;

    protected function setUp(): void
    {
        parent::setUp();

        $this->exam = new AdmissionExamDefinition([
            'name' => 'JEE Main',
            'official_domains' => ['nta.ac.in', 'josaa.nic.in'],
            'document_delivery_domains' => ['cdnbbsr.s3waas.gov.in'],
        ]);
    }

    public function test_exact_and_subdomains_of_an_official_authority_are_allowed(): void
    {
        $policy = new AdmissionOfficialSourcePolicy;

        self::assertSame('nta.ac.in', $policy->assertAllowed($this->exam, 'https://nta.ac.in/results.pdf'));
        self::assertSame('archive.josaa.nic.in', $policy->assertAllowed($this->exam, 'https://archive.josaa.nic.in/or-cr'));
    }

    public function test_government_cdn_requires_an_official_listing_page(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new AdmissionOfficialSourcePolicy)->assertAllowed(
            $this->exam,
            'https://cdnbbsr.s3waas.gov.in/path/result.pdf'
        );
    }

    public function test_government_cdn_is_accepted_with_an_official_listing_page(): void
    {
        $host = (new AdmissionOfficialSourcePolicy)->assertAllowed(
            $this->exam,
            'https://cdnbbsr.s3waas.gov.in/path/result.pdf',
            'https://nta.ac.in/notices'
        );

        self::assertSame('cdnbbsr.s3waas.gov.in', $host);
    }

    public function test_lookalike_domain_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new AdmissionOfficialSourcePolicy)->assertAllowed(
            $this->exam,
            'https://josaa.nic.in.example.com/cutoffs.pdf'
        );
    }

    public function test_non_https_resource_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new AdmissionOfficialSourcePolicy)->assertAllowed(
            $this->exam,
            'http://nta.ac.in/results.pdf'
        );
    }
}

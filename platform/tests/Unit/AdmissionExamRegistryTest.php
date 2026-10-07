<?php

namespace Tests\Unit;

use App\Services\AdmissionExamRegistry;
use InvalidArgumentException;
use Tests\TestCase;

class AdmissionExamRegistryTest extends TestCase
{
    public function test_jee_and_neet_blueprints_are_reusable_and_valid(): void
    {
        $registry = app(AdmissionExamRegistry::class);

        $jee = $registry->blueprint('jee-main-paper-1');
        $neet = $registry->blueprint('neet-ug');

        self::assertSame(300, $jee['score_schema']['max_marks']);
        self::assertContains('josaa.nic.in', $jee['official_domains']);
        self::assertContains('jeemain.nta.nic.in', $jee['official_domains']);
        self::assertContains('josaa.admissions.nic.in', $jee['official_domains']);
        self::assertSame(720, $neet['score_schema']['max_marks']);
        self::assertContains('mcc.nic.in', $neet['official_domains']);
        self::assertContains('cdnbbsr.s3waas.gov.in', $neet['document_delivery_domains']);
        self::assertContains('category', $jee['rank_dimensions']);
        self::assertContains('category', $neet['rank_dimensions']);
    }

    public function test_unknown_exam_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        app(AdmissionExamRegistry::class)->blueprint('unknown-exam');
    }
}

<?php

namespace Tests\Unit;

use App\Services\PhoneNumberService;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class PhoneNumberServiceTest extends TestCase
{
    private PhoneNumberService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new PhoneNumberService;
    }

    public function test_it_normalizes_a_local_indian_mobile_number(): void
    {
        $this->assertSame('+919876543210', $this->service->normalize('98765 43210', '+91'));
    }

    public function test_it_preserves_an_international_number(): void
    {
        $this->assertSame('+447700900123', $this->service->normalize('+44 7700 900123', '+91'));
    }

    public function test_it_masks_all_but_the_last_four_digits(): void
    {
        $masked = $this->service->mask('+919876543210');
        $this->assertStringEndsWith('3210', $masked);
        $this->assertStringNotContainsString('98765', $masked);
    }

    public function test_it_rejects_an_invalid_number(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->service->normalize('123');
    }
}

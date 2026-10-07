<?php

namespace Tests\Unit;

use App\Services\PypContentService;
use PHPUnit\Framework\TestCase;

class PypContentServiceTest extends TestCase
{
    public function test_taxonomy_tokens_are_readable_and_resolve_to_the_original_id(): void
    {
        $service = new PypContentService;
        $token = $service->token('Physical Chemistry', 247);

        $this->assertSame('physical-chemistry-247', $token);
        $this->assertSame(247, $service->idFromToken($token));
    }

    public function test_invalid_taxonomy_tokens_do_not_resolve_to_an_item(): void
    {
        $service = new PypContentService;

        $this->assertSame(0, $service->idFromToken('physical-chemistry'));
    }
}

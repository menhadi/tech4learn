<?php

namespace Tests\Unit;

use App\Services\SourceUrlNormalizer;
use PHPUnit\Framework\TestCase;

class SourceUrlNormalizerTest extends TestCase
{
    public function test_it_normalizes_equivalent_source_urls_for_duplicate_detection(): void
    {
        $normalizer = new SourceUrlNormalizer;

        $first = $normalizer->normalize('HTTPS://Example.COM:443/questions/42/?b=2&utm_source=test&a=1#answer');
        $second = $normalizer->normalize('https://example.com/questions/42?a=1&b=2');

        $this->assertSame($second, $first);
        $this->assertSame($normalizer->hash($first), $normalizer->hash($second));
    }
}

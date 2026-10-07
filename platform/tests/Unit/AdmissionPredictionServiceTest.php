<?php

namespace Tests\Unit;

use App\Services\AdmissionPredictionService;
use PHPUnit\Framework\TestCase;

class AdmissionPredictionServiceTest extends TestCase
{
    public function test_it_interpolates_a_bounded_rank_range_from_official_points(): void
    {
        $result = (new AdmissionPredictionService)->interpolateRank([
            ['marks' => 100, 'rank_min' => 9000, 'rank_max' => 11000],
            ['marks' => 150, 'rank_min' => 4000, 'rank_max' => 5000],
            ['marks' => 200, 'rank_min' => 900, 'rank_max' => 1100],
        ], 175);

        $this->assertTrue($result['available']);
        $this->assertSame('official_observation_interpolation', $result['method']);
        $this->assertLessThan($result['rank_max'], $result['rank_min']);
        $this->assertGreaterThanOrEqual($result['rank_min'], $result['central_estimate']);
        $this->assertLessThanOrEqual($result['rank_max'], $result['central_estimate']);
        $this->assertSame(3, $result['observation_count']);
    }

    public function test_it_refuses_to_extrapolate_beyond_official_data(): void
    {
        $result = (new AdmissionPredictionService)->interpolateRank([
            ['marks' => 100, 'rank_min' => 9000, 'rank_max' => 11000],
            ['marks' => 150, 'rank_min' => 4000, 'rank_max' => 5000],
        ], 200);

        $this->assertFalse($result['available']);
        $this->assertSame('unavailable', $result['confidence']);
        $this->assertNull($result['rank_min']);
    }
}

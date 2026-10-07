<?php

namespace Tests\Unit;

use App\Services\JeeMainStatisticsParser;
use PHPUnit\Framework\TestCase;

class JeeMainStatisticsParserTest extends TestCase
{
    public function test_it_extracts_shift_percentile_distribution_with_page_provenance(): void
    {
        $text = "cover\f"
            ."JEE (Main) 2026 Session 1\nDate and Shift Wise Percentile Score\n"
            ."21.01.2026 S1 99.0068916- 99.9992186 1,307\n"
            ."21.01.2026 S2 98.0129533-98.9968158 1355\f"
            ."JEE (Main) 2026 Session 2\n"
            ."02.04.2026 S1 100.0000000-100.0000000 1";

        $rows = (new JeeMainStatisticsParser)->parse($text);

        self::assertCount(3, $rows);
        self::assertSame(2026, $rows[0]['exam_year']);
        self::assertSame(1, $rows[0]['session']);
        self::assertSame('2026-01-21', $rows[0]['exam_date']);
        self::assertSame(1307, $rows[0]['candidate_count']);
        self::assertSame(['page' => 2], $rows[0]['provenance']);
        self::assertSame(2, $rows[2]['session']);
    }
}

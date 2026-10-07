<?php

namespace Tests\Unit;

use App\Services\MccSeatMatrixParser;
use PHPUnit\Framework\TestCase;

class MccSeatMatrixParserTest extends TestCase
{
    public function test_it_reassembles_multiline_seat_matrix_rows(): void
    {
        $header = $this->line([
            'StateName', 'InstituteType', 'Institute', 'Quota', 'Branch',
            'Category', 'TotalSeats', 'SeatGender',
        ]);
        $first = $this->line([
            'Andaman And', 'All India', 'Example Medical College, Main Road',
            '', '', '', '', '',
        ]);
        $second = $this->line([
            'Nicobar Islands', 'except Central University', 'Port Blair (200101)',
            'All India', 'MBBS (MBBS)', 'OP NO', '6', 'Both',
        ]);

        $rows = (new MccSeatMatrixParser)->parse(
            "Seat Matrix for Round 1\n{$header}\n{$first}\n{$second}\f"
        );

        self::assertCount(1, $rows);
        self::assertSame('Andaman And Nicobar Islands', $rows[0]['state_name']);
        self::assertSame('All India except Central University', $rows[0]['institution_type']);
        self::assertSame('200101', $rows[0]['institution_code']);
        self::assertSame('MBBS (MBBS)', $rows[0]['program_name']);
        self::assertSame(6, $rows[0]['seat_count']);
        self::assertSame(['page' => 1], $rows[0]['provenance']);
    }

    public function test_it_separates_contiguous_rows_using_institution_codes(): void
    {
        $header = $this->line([
            'StateName', 'InstituteType', 'Institute', 'Quota', 'Branch',
            'Category', 'TotalSeats', 'SeatGender',
        ]);
        $rowsText = implode("\n", [
            $this->line(['', '', 'College One', '', '', '', '', '']),
            $this->line(['State A', 'All India', '', 'All India', 'MBBS', 'OP NO', '10', 'Both']),
            $this->line(['', '', '(200101)', '', '', '', '', '']),
            $this->line(['', '', 'College Two', '', '', '', '', '']),
            $this->line(['State B', 'All India', '', 'All India', 'BDS', 'SC NO', '2', 'Both']),
            $this->line(['', '', '(200102)', '', '', '', '', '']),
        ]);

        $rows = (new MccSeatMatrixParser)->parse("{$header}\n{$rowsText}\f");

        self::assertCount(2, $rows);
        self::assertSame('200101', $rows[0]['institution_code']);
        self::assertSame('200102', $rows[1]['institution_code']);
    }

    private function line(array $values): string
    {
        $widths = [18, 40, 72, 14, 16, 14, 14, 12];
        $line = '';
        foreach ($values as $index => $value) {
            $line .= str_pad($value, $widths[$index]);
        }

        return rtrim($line);
    }
}

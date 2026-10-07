<?php

namespace App\Services;

use App\Contracts\AdmissionPdfParser;

class MccSeatMatrixParser implements AdmissionPdfParser
{
    private const COLUMNS = [
        'state_name' => 'StateName',
        'institution_type' => 'InstituteType',
        'institution_name' => 'Institute',
        'quota' => 'Quota',
        'program_name' => 'Branch',
        'category' => 'Category',
        'seat_count' => 'TotalSeats',
        'seat_gender' => 'SeatGender',
    ];

    public function parse(string $text): array
    {
        $records = [];

        foreach (explode("\f", $text) as $pageIndex => $page) {
            $lines = explode("\n", $page);
            $headerIndex = $this->headerIndex($lines);
            if ($headerIndex === null) {
                continue;
            }

            $positions = $this->columnPositions($lines[$headerIndex]);
            if (count($positions) !== count(self::COLUMNS)) {
                continue;
            }

            $current = [];
            $hasAnchor = false;
            foreach (array_slice($lines, $headerIndex + 1) as $line) {
                if (trim($line) === '') {
                    if ($this->isComplete($current)) {
                        $records[] = $this->normalize($current, $pageIndex + 1);
                    }
                    $current = [];
                    $hasAnchor = false;
                    continue;
                }
                if (str_contains($line, 'Seat Matrix for Round')
                    || str_contains($line, 'StateName')) {
                    continue;
                }

                $slices = $this->sliceLine($line, $positions);
                foreach ($slices as $field => $value) {
                    if ($value === '') {
                        continue;
                    }
                    $current[$field] = trim(($current[$field] ?? '').' '.$value);
                }

                if ($this->isComplete($slices)) {
                    $hasAnchor = true;
                }

                if ($hasAnchor && $this->hasInstitutionCode($current)) {
                    $records[] = $this->normalize($current, $pageIndex + 1);
                    $current = [];
                    $hasAnchor = false;
                }
            }

            if ($this->isComplete($current)) {
                $records[] = $this->normalize($current, $pageIndex + 1);
            }
        }

        return $records;
    }

    private function headerIndex(array $lines): ?int
    {
        foreach ($lines as $index => $line) {
            if (str_contains($line, 'StateName')
                && str_contains($line, 'InstituteType')
                && str_contains($line, 'TotalSeats')
                && str_contains($line, 'SeatGender')) {
                return $index;
            }
        }

        return null;
    }

    private function columnPositions(string $header): array
    {
        $positions = [];
        $offset = 0;
        foreach (self::COLUMNS as $field => $label) {
            $position = strpos($header, $label, $offset);
            if ($position === false) {
                return [];
            }
            $positions[$field] = $position;
            $offset = $position + strlen($label);
        }

        asort($positions);

        return $positions;
    }

    private function sliceLine(string $line, array $positions): array
    {
        $fields = array_keys($positions);
        $starts = array_values($positions);
        $values = [];

        foreach ($fields as $index => $field) {
            $start = $starts[$index];
            $length = isset($starts[$index + 1]) ? $starts[$index + 1] - $start : null;
            $values[$field] = trim(
                $length === null ? substr($line, $start) : substr($line, $start, $length)
            );
        }

        return $values;
    }

    private function isComplete(array $record): bool
    {
        return isset($record['seat_count'], $record['seat_gender'])
            && preg_match('/^\d+$/', trim($record['seat_count'])) === 1
            && trim($record['seat_gender']) !== '';
    }

    private function hasInstitutionCode(array $record): bool
    {
        return preg_match('/\\(\\d{6}\\)/', $record['institution_name'] ?? '') === 1;
    }

    private function normalize(array $record, int $page): array
    {
        $record = array_map(
            fn ($value) => trim(preg_replace('/\s+/', ' ', (string) $value)),
            $record
        );

        $institutionCode = null;
        if (preg_match('/\((\d{6})\)\s*$/', $record['institution_name'] ?? '', $match)) {
            $institutionCode = $match[1];
        }

        return [
            'record_type' => 'seat_matrix',
            'state_name' => $record['state_name'] ?? null,
            'institution_type' => $record['institution_type'] ?? null,
            'institution_code' => $institutionCode,
            'institution_name' => $record['institution_name'] ?? null,
            'quota' => $record['quota'] ?? null,
            'program_name' => $record['program_name'] ?? null,
            'category' => $record['category'] ?? null,
            'seat_count' => (int) $record['seat_count'],
            'seat_gender' => $record['seat_gender'],
            'provenance' => ['page' => $page],
        ];
    }
}

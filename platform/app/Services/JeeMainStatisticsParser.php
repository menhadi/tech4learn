<?php

namespace App\Services;

use App\Contracts\AdmissionPdfParser;
use DateTimeImmutable;

class JeeMainStatisticsParser implements AdmissionPdfParser
{
    public function parse(string $text): array
    {
        $records = [];

        foreach (explode("\f", $text) as $pageIndex => $page) {
            if (! preg_match('/JEE\s*\(Main\)\s*(\d{4})\s*Session\s*[-:]?\s*([12])/i', $page, $heading)) {
                continue;
            }

            $year = (int) $heading[1];
            $session = (int) $heading[2];
            preg_match_all(
                '/(\d{2}\.\d{2}\.\d{4})\s+(S\d+)\s+'
                .'(\d{1,3}\.\d{7})\s*[--]\s*(\d{1,3}\.\d{7})\s+([\d,]+)/',
                $page,
                $matches,
                PREG_SET_ORDER
            );

            foreach ($matches as $match) {
                $date = DateTimeImmutable::createFromFormat('d.m.Y', $match[1]);
                if (! $date) {
                    continue;
                }

                $records[] = [
                    'record_type' => 'percentile_distribution',
                    'exam_year' => $year,
                    'session' => $session,
                    'exam_date' => $date->format('Y-m-d'),
                    'shift' => strtoupper($match[2]),
                    'percentile_min' => (float) $match[3],
                    'percentile_max' => (float) $match[4],
                    'candidate_count' => (int) str_replace(',', '', $match[5]),
                    'provenance' => ['page' => $pageIndex + 1],
                ];
            }
        }

        return $records;
    }
}

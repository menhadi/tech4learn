<?php

namespace App\Services;

use Illuminate\Support\Collection;

class ExamDisplayOrder
{
    public static function newestYearFirst(Collection $exams): Collection
    {
        return $exams->sort(function ($left, $right) {
            $yearComparison = self::year($right) <=> self::year($left);
            if ($yearComparison !== 0) {
                return $yearComparison;
            }

            $createdComparison = ($right->created_at?->getTimestamp() ?? 0) <=> ($left->created_at?->getTimestamp() ?? 0);

            return $createdComparison !== 0
                ? $createdComparison
                : ((int) $right->id <=> (int) $left->id);
        })->values();
    }

    private static function year($exam): int
    {
        $name = $exam->name ?? '';

        if (is_array($name)) {
            $name = $name[app()->getLocale()] ?? reset($name) ?: '';
        } elseif (is_string($name)) {
            $decoded = json_decode($name, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $name = $decoded[app()->getLocale()] ?? reset($decoded) ?: '';
            }
        }

        preg_match_all('/(?:19|20)\d{2}/', strip_tags((string) $name), $matches);

        return empty($matches[0]) ? 0 : max(array_map('intval', $matches[0]));
    }
}
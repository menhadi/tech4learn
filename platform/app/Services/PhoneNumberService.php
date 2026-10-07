<?php

namespace App\Services;

use InvalidArgumentException;

class PhoneNumberService
{
    public function normalize(?string $phone, string $defaultCountryCode = '+91'): ?string
    {
        if (blank($phone)) {
            return null;
        }

        $raw = trim((string) $phone);
        $hasPlus = str_starts_with($raw, '+');
        $digits = preg_replace('/\D+/', '', $raw);

        if (! $digits || strlen($digits) < 8 || strlen($digits) > 15) {
            throw new InvalidArgumentException('Enter a valid mobile number with 8 to 15 digits.');
        }

        if (! $hasPlus) {
            $country = preg_replace('/\D+/', '', $defaultCountryCode) ?: '91';
            if (strlen($digits) === 10) {
                $digits = $country.$digits;
            }
        }

        return '+'.$digits;
    }

    public function mask(?string $phone): string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone);

        return strlen($digits) > 4 ? str_repeat('•', max(0, strlen($digits) - 4)).substr($digits, -4) : $digits;
    }
}

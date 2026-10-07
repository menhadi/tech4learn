<?php

namespace App\Contracts;

interface AdmissionPdfParser
{
    /** @return array<int, array<string, mixed>> */
    public function parse(string $text): array;
}

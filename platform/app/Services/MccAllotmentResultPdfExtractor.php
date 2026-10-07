<?php

namespace App\Services;

use RuntimeException;
use Symfony\Component\Process\Process;

class MccAllotmentResultPdfExtractor
{
    public function extract(string $path): array
    {
        $resolved = realpath($path);
        if ($resolved === false || ! is_file($resolved) || ! is_readable($resolved)) {
            throw new RuntimeException("The MCC allotment-result PDF is not readable: {$path}");
        }

        $process = new Process([
            (string) config('admission_prediction.pdf.python_binary', 'python'),
            base_path('scripts/admission_prediction/extract_mcc_allotment_result.py'),
            $resolved,
        ]);
        $process->setTimeout((int) config('admission_prediction.pdf.allotment_timeout_seconds', 1800));
        $process->run();

        if (! $process->isSuccessful()) {
            throw new RuntimeException('MCC allotment extraction failed: '.trim($process->getErrorOutput()));
        }

        $records = [];
        foreach (preg_split('/\R/', trim($process->getOutput())) as $lineNumber => $line) {
            if (trim($line) === '') {
                continue;
            }
            $record = json_decode($line, true);
            if (! is_array($record)) {
                throw new RuntimeException('MCC allotment extractor returned invalid JSON on line '.($lineNumber + 1).'.');
            }
            $records[] = $record;
        }
        if ($records === []) {
            throw new RuntimeException('MCC allotment extraction returned no cutoff rows.');
        }

        return $records;
    }
}

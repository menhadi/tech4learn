<?php

namespace App\Services;

use RuntimeException;
use Symfony\Component\Process\Process;

class MccSeatMatrixPdfExtractor
{
    public function extract(string $path): array
    {
        $resolved = realpath($path);
        if ($resolved === false || ! is_file($resolved) || ! is_readable($resolved)) {
            throw new RuntimeException("The MCC seat-matrix PDF is not readable: {$path}");
        }

        $python = (string) config('admission_prediction.pdf.python_binary', 'python');
        $script = base_path('scripts/admission_prediction/extract_mcc_seat_matrix.py');
        $process = new Process([$python, $script, $resolved]);
        $process->setTimeout((int) config('admission_prediction.pdf.table_timeout_seconds', 600));
        $process->run();

        if (! $process->isSuccessful()) {
            throw new RuntimeException(
                'MCC table extraction failed: '.trim($process->getErrorOutput())
            );
        }

        $records = [];
        foreach (preg_split('/\R/', trim($process->getOutput())) as $lineNumber => $line) {
            if (trim($line) === '') {
                continue;
            }
            $record = json_decode($line, true);
            if (! is_array($record)) {
                throw new RuntimeException(
                    'MCC extractor returned invalid JSON on line '.($lineNumber + 1).'.'
                );
            }
            $records[] = $record;
        }

        if ($records === []) {
            throw new RuntimeException('MCC table extraction returned no seat rows.');
        }

        return $records;
    }
}

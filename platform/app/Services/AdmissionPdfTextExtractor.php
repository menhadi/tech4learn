<?php

namespace App\Services;

use RuntimeException;
use Symfony\Component\Process\Process;

class AdmissionPdfTextExtractor
{
    public function extract(string $path): string
    {
        $resolved = realpath($path);
        if ($resolved === false || ! is_file($resolved) || ! is_readable($resolved)) {
            throw new RuntimeException("The PDF is not readable: {$path}");
        }

        $binary = (string) config('admission_prediction.pdf.pdftotext_binary', 'pdftotext');
        $process = new Process([$binary, '-layout', '-enc', 'UTF-8', $resolved, '-']);
        $process->setTimeout((int) config('admission_prediction.pdf.timeout_seconds', 180));
        $process->run();

        if (! $process->isSuccessful()) {
            throw new RuntimeException(
                'PDF text extraction failed: '.trim($process->getErrorOutput())
            );
        }

        $text = $process->getOutput();
        if (trim($text) === '') {
            throw new RuntimeException('The PDF contains no extractable text.');
        }

        return str_replace(["\r\n", "\r"], "\n", $text);
    }
}

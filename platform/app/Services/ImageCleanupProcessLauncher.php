<?php

namespace App\Services;

use Symfony\Component\Process\Process;

class ImageCleanupProcessLauncher
{
    public function start(int $runId): bool
    {
        if (PHP_OS_FAMILY === 'Windows') return false;
        $php = $this->phpBinary();
        if (! $php || ! is_file(base_path('artisan'))) return false;
        $command = sprintf(
            'nohup %s %s image-cleanup:process %s --no-interaction > /dev/null 2>&1 &',
            escapeshellarg($php), escapeshellarg(base_path('artisan')), escapeshellarg('--run='.$runId)
        );
        try {
            $process = new Process(['/bin/sh', '-c', $command], base_path());
            $process->setTimeout(10);
            $process->run();
            return $process->isSuccessful();
        } catch (\Throwable $exception) {
            report($exception);
            return false;
        }
    }

    private function phpBinary(): ?string
    {
        foreach (array_unique(array_filter([
            config('paper_processing.php_cli_binary'), PHP_BINARY,
            '/usr/bin/php8.4', '/usr/bin/php8.3', '/usr/bin/php8.2', '/usr/bin/php',
        ])) as $candidate) {
            if (is_file($candidate) && is_executable($candidate) && ! str_contains(strtolower(basename($candidate)), 'fpm')) return $candidate;
        }
        return null;
    }
}

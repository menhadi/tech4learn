<?php

namespace App\Services;

use Symfony\Component\Process\Process;

class ExamQualityAuditProcessLauncher
{
    public function start(int $auditId): bool
    {
        return $this->startCommand(['--audit='.$auditId]);
    }

    public function startBatch(string $batchToken, int $auditCount): int
    {
        $auditCount = max(1, $auditCount);
        $workerCount = min($auditCount, max(1, (int) config('paper_processing.workers', 4)));
        $started = 0;

        foreach (range(1, $workerCount) as $worker) {
            if ($this->startBatchWorker($batchToken, $worker)) $started++;
        }

        return $started;
    }

    public function startBatchWorker(string $batchToken, ?int $worker = null): bool
    {
        if (! preg_match('/^[a-f0-9-]{36}$/i', $batchToken)) return false;

        return $this->startCommand([
            '--batch='.$batchToken,
            '--limit=1',
            '--worker=immediate-batch'.($worker ? '-'.$worker : ''),
        ]);
    }

    private function startCommand(array $arguments): bool
    {
        if (PHP_OS_FAMILY === 'Windows') return false;

        $php = $this->phpBinary();
        $artisan = base_path('artisan');
        if ($php === null || ! is_file($artisan)) {
            report(new \RuntimeException('No usable PHP CLI binary was found for the immediate audit worker.'));
            return false;
        }

        $argumentString = implode(' ', array_map('escapeshellarg', $arguments));
        $command = sprintf(
            'nohup %s %s exam-quality:process %s --no-interaction > /dev/null 2>&1 &',
            escapeshellarg($php),
            escapeshellarg($artisan),
            $argumentString
        );

        try {
            $process = new Process(['/bin/sh', '-c', $command], base_path());
            $process->setTimeout(10);
            $process->run();

            if (! $process->isSuccessful()) {
                report(new \RuntimeException('Could not launch the immediate audit worker: '.trim($process->getErrorOutput())));
                return false;
            }

            return true;
        } catch (\Throwable $exception) {
            report($exception);
            return false;
        }
    }

    private function phpBinary(): ?string
    {
        $configured = (string) config('paper_processing.php_cli_binary', '');
        $candidates = array_unique(array_filter([
            $configured,
            str_contains(strtolower(basename(PHP_BINARY)), 'fpm') ? null : PHP_BINARY,
            '/usr/bin/php8.4',
            '/usr/bin/php8.3',
            '/usr/bin/php8.2',
            '/usr/bin/php',
        ]));

        foreach ($candidates as $candidate) {
            if (is_file($candidate) && is_executable($candidate)) return $candidate;
        }

        return null;
    }
}

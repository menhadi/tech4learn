<?php

namespace App\Services;

use Symfony\Component\Process\Process;

class SourceExamImportProcessLauncher
{
    public function start(int $importId): bool
    {
        return $importId > 0 && $this->startCommand([
            '--import='.$importId,
            '--limit=1',
            '--worker=immediate-'.$importId,
        ]);
    }

    public function startBatch(string $batchToken, int $importCount): int
    {
        if (! $this->validBatchToken($batchToken)) return 0;

        $workers = min(max(1, $importCount), max(1, (int) config('paper_processing.workers', 4)));
        $started = 0;
        foreach (range(1, $workers) as $worker) {
            if ($this->startBatchWorker($batchToken, $worker)) $started++;
        }

        return $started;
    }

    public function startBatchWorker(string $batchToken, ?int $worker = null): bool
    {
        if (! $this->validBatchToken($batchToken)) return false;

        return $this->startCommand([
            '--batch='.$batchToken,
            '--limit=1',
            '--worker=immediate-batch'.($worker ? '-'.$worker : ''),
        ]);
    }

    private function startCommand(array $arguments): bool
    {
        if (PHP_OS_FAMILY === 'Windows') return false;

        $php = (string) config('paper_processing.php_cli_binary', '/usr/bin/php');
        $artisan = base_path('artisan');
        if (! is_executable($php) || ! is_file($artisan)) {
            report(new \RuntimeException("Source extraction worker executable is unavailable: {$php}"));
            return false;
        }

        $command = sprintf(
            'nohup %s %s source-exams:process %s --no-interaction > /dev/null 2>&1 &',
            escapeshellarg($php),
            escapeshellarg($artisan),
            implode(' ', array_map('escapeshellarg', $arguments))
        );

        try {
            $process = new Process(['/bin/sh', '-c', $command], base_path());
            $process->setTimeout(10);
            $process->mustRun();
            return true;
        } catch (\Throwable $exception) {
            report($exception);
            return false;
        }
    }

    private function validBatchToken(string $token): bool
    {
        return (bool) preg_match('/^[a-f0-9-]{36}$/i', $token);
    }
}

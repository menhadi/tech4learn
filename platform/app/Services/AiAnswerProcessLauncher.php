<?php

namespace App\Services;

use Symfony\Component\Process\Process;

class AiAnswerProcessLauncher
{
    public function startBatch(string $batchToken, int $runCount): int
    {
        if (PHP_OS_FAMILY === 'Windows' || ! preg_match('/^[a-f0-9-]{36}$/i', $batchToken)) return 0;
        $workers = min(max(1, $runCount), max(1, (int) config('paper_processing.workers', 4)));
        $started = 0;
        foreach (range(1, $workers) as $worker) {
            $php = $this->phpBinary();
            if (! $php) break;
            $command = sprintf(
                'nohup %s %s ai-answers:process %s --limit=1 --worker=%s --no-interaction > /dev/null 2>&1 &',
                escapeshellarg($php), escapeshellarg(base_path('artisan')), escapeshellarg('--batch='.$batchToken),
                escapeshellarg('immediate-'.$worker)
            );
            $process = new Process(['/bin/sh', '-c', $command], base_path());
            $process->setTimeout(10); $process->run();
            if ($process->isSuccessful()) $started++;
        }
        return $started;
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

<?php

namespace App\Console\Commands;

use App\Services\JosaaOrCrAdapter;
use Illuminate\Console\Command;
use Throwable;

class ExtractJosaaOrCr extends Command
{
    protected $signature = 'admission-prediction:extract-josaa
        {round : JoSAA round value}
        {institute-type : Institute type value}
        {institute : Institute selector value}
        {program : Academic-program selector value}
        {seat-type : Seat-type selector value}
        {--sample=3 : Number of sample rows to print}';

    protected $description = 'Dry-run JoSAA opening/closing-rank extraction without publishing rows';

    public function handle(JosaaOrCrAdapter $adapter): int
    {
        try {
            $rows = $adapter->fetchRows([
                'round' => (string) $this->argument('round'),
                'institute_type' => (string) $this->argument('institute-type'),
                'institute' => (string) $this->argument('institute'),
                'program' => (string) $this->argument('program'),
                'seat_type' => (string) $this->argument('seat-type'),
            ]);

            $this->info('Extracted cutoff rows: '.count($rows));
            $this->line('Canonical SHA-256: '.hash(
                'sha256',
                json_encode($rows, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
            ));

            foreach (array_slice($rows, 0, max(0, (int) $this->option('sample'))) as $index => $row) {
                $this->line(json_encode(
                    ['row' => $index + 1] + $row,
                    JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
                ));
            }

            return $rows === [] ? self::FAILURE : self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}

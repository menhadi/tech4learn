<?php

namespace App\Console\Commands;

use App\Models\AdmissionExamDefinition;
use App\Services\{AdmissionExamRegistry, AdmissionOfficialSourceProbeService};
use Illuminate\Console\Command;
use Throwable;

class ProbeAdmissionOfficialSource extends Command
{
    protected $signature = 'admission-prediction:probe-source
        {exam : Configured exam code}
        {url : Exact official resource URL}
        {--listing= : Approved authority page that links to a delivery/CDN URL}';

    protected $description = 'Probe an official admission source without publishing its data';

    public function handle(
        AdmissionExamRegistry $registry,
        AdmissionOfficialSourceProbeService $probe
    ): int {
        try {
            $blueprint = $registry->blueprint((string) $this->argument('exam'));
            $exam = new AdmissionExamDefinition($blueprint);
            $result = $probe->probe(
                $exam,
                (string) $this->argument('url'),
                $this->option('listing') ? (string) $this->option('listing') : null
            );

            $this->table(
                ['Check', 'Value'],
                collect($result)->map(fn ($value, $key) => [
                    $key,
                    is_bool($value) ? ($value ? 'true' : 'false') : (string) $value,
                ])->values()->all()
            );

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}

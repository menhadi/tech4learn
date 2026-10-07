<?php

namespace App\Console\Commands;

use App\Services\JosaaOrCrAdapter;
use Illuminate\Console\Command;
use Throwable;

class ProbeJosaaOrCr extends Command
{
    protected $signature = 'admission-prediction:probe-josaa
        {--round= : JoSAA round value, such as 1}
        {--institute-type= : Institute type value, such as IIT, NIT, or ALL}
        {--institute= : Institute selector value}
        {--program= : Academic-program selector value}';

    protected $description = 'Dry-run JoSAA postback discovery without publishing cutoff rows';

    public function handle(JosaaOrCrAdapter $adapter): int
    {
        try {
            $selections = [];
            foreach ([
                'round' => 'round',
                'institute-type' => 'institute_type',
                'institute' => 'institute',
                'program' => 'program',
            ] as $option => $key) {
                if ($this->option($option) !== null) {
                    $selections[$key] = (string) $this->option($option);
                }
            }
            $sets = $adapter->optionSets($selections);

            foreach ($sets as $name => $options) {
                $this->info($name.': '.count($options).' options');
                $this->table(
                    ['Value', 'Label'],
                    collect($options)->take(20)->map(
                        fn ($label, $value) => [(string) $value, $label]
                    )->values()->all()
                );
            }

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}

<?php

namespace App\Services;

use App\Models\AdmissionExamDefinition;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class AdmissionExamRegistry
{
    public function all(): array
    {
        $blueprints = config('admission_prediction.exams', []);

        foreach ($blueprints as $key => $blueprint) {
            $this->validate($key, $blueprint);
        }

        return $blueprints;
    }

    public function blueprint(string $code): array
    {
        $blueprint = config("admission_prediction.exams.{$code}");
        if (! is_array($blueprint)) {
            throw new InvalidArgumentException("Unknown admission exam [{$code}].");
        }

        $this->validate($code, $blueprint);

        return $blueprint;
    }

    /** @return array<string, AdmissionExamDefinition> */
    public function sync(): array
    {
        return DB::transaction(function (): array {
            $synced = [];

            foreach ($this->all() as $code => $blueprint) {
                $synced[$code] = AdmissionExamDefinition::query()->updateOrCreate(
                    ['code' => $code],
                    [
                        'name' => $blueprint['name'],
                        'country_code' => $blueprint['country_code'],
                        'authorities' => $blueprint['authorities'],
                        'official_domains' => $blueprint['official_domains'],
                        'document_delivery_domains' => $blueprint['document_delivery_domains'],
                        'score_schema' => $blueprint['score_schema'],
                        'rank_dimensions' => $blueprint['rank_dimensions'],
                        'admission_dimensions' => $blueprint['admission_dimensions'],
                        'resource_kinds' => $blueprint['resource_kinds'],
                        'config_version' => (int) config('admission_prediction.schema_version', 1),
                        'enabled' => true,
                    ]
                );
            }

            return $synced;
        });
    }

    private function validate(string $key, array $blueprint): void
    {
        $required = [
            'code', 'name', 'country_code', 'authorities', 'official_domains',
            'document_delivery_domains', 'score_schema', 'rank_dimensions', 'admission_dimensions', 'resource_kinds',
        ];

        foreach ($required as $field) {
            if (! Arr::has($blueprint, $field)) {
                throw new InvalidArgumentException("Admission exam [{$key}] is missing [{$field}].");
            }
        }

        if ($blueprint['code'] !== $key) {
            throw new InvalidArgumentException("Admission exam key [{$key}] must match its code.");
        }

        if (($blueprint['score_schema']['max_marks'] ?? 0) <= 0) {
            throw new InvalidArgumentException("Admission exam [{$key}] must define positive maximum marks.");
        }

        if ($blueprint['official_domains'] === []) {
            throw new InvalidArgumentException("Admission exam [{$key}] must define official domains.");
        }
    }
}

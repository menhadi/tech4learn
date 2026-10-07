<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdmissionExamDefinition extends Model
{
    protected $fillable = [
        'code', 'name', 'country_code', 'authorities', 'official_domains',
        'document_delivery_domains', 'score_schema', 'rank_dimensions', 'admission_dimensions',
        'resource_kinds', 'config_version', 'enabled',
    ];

    protected $casts = [
        'authorities' => 'array',
        'official_domains' => 'array',
        'document_delivery_domains' => 'array',
        'score_schema' => 'array',
        'rank_dimensions' => 'array',
        'admission_dimensions' => 'array',
        'resource_kinds' => 'array',
        'enabled' => 'boolean',
    ];

    public function officialResources()
    {
        return $this->hasMany(AdmissionOfficialResource::class);
    }

    public function datasetVersions()
    {
        return $this->hasMany(AdmissionDatasetVersion::class);
    }

    public function predictionModels()
    {
        return $this->hasMany(AdmissionPredictionModel::class);
    }
}

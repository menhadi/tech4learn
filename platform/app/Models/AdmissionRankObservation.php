<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdmissionRankObservation extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'marks' => 'decimal:3',
        'max_marks' => 'decimal:3',
        'percentile' => 'decimal:7',
        'subject_marks' => 'array',
        'dimensions' => 'array',
        'provenance' => 'array',
    ];

    public function datasetVersion()
    {
        return $this->belongsTo(AdmissionDatasetVersion::class, 'admission_dataset_version_id');
    }

    public function officialResource()
    {
        return $this->belongsTo(AdmissionOfficialResource::class, 'admission_official_resource_id');
    }
}

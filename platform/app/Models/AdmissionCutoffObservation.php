<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdmissionCutoffObservation extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
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

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdmissionPredictionModel extends Model
{
    protected $fillable = [
        'admission_exam_definition_id', 'admission_dataset_version_id',
        'model_kind', 'version', 'algorithm', 'status', 'artifact_disk',
        'artifact_path', 'feature_schema', 'metrics', 'trained_at', 'activated_at',
    ];

    protected $casts = [
        'feature_schema' => 'array',
        'metrics' => 'array',
        'trained_at' => 'datetime',
        'activated_at' => 'datetime',
    ];

    public function examDefinition()
    {
        return $this->belongsTo(AdmissionExamDefinition::class, 'admission_exam_definition_id');
    }

    public function datasetVersion()
    {
        return $this->belongsTo(AdmissionDatasetVersion::class, 'admission_dataset_version_id');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdmissionDatasetVersion extends Model
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_VALIDATED = 'validated';
    public const STATUS_PUBLISHED = 'published';
    public const STATUS_RETIRED = 'retired';

    protected $fillable = [
        'admission_exam_definition_id', 'created_by', 'approved_by', 'version',
        'schema_version', 'status', 'validation_summary', 'notes',
        'approved_at', 'published_at',
    ];

    protected $casts = [
        'validation_summary' => 'array',
        'approved_at' => 'datetime',
        'published_at' => 'datetime',
    ];

    public function examDefinition()
    {
        return $this->belongsTo(AdmissionExamDefinition::class, 'admission_exam_definition_id');
    }

    public function officialResources()
    {
        return $this->belongsToMany(
            AdmissionOfficialResource::class,
            'admission_dataset_resources'
        )->withTimestamps();
    }

    public function rankObservations()
    {
        return $this->hasMany(AdmissionRankObservation::class);
    }

    public function cutoffObservations()
    {
        return $this->hasMany(AdmissionCutoffObservation::class);
    }
}

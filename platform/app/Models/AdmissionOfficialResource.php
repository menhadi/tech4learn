<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdmissionOfficialResource extends Model
{
    public const STATUS_REGISTERED = 'registered';
    public const STATUS_EXTRACTED = 'extracted';
    public const STATUS_VERIFIED = 'verified';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_SUPERSEDED = 'superseded';

    protected $fillable = [
        'admission_exam_definition_id', 'created_by', 'verified_by',
        'supersedes_resource_id', 'title', 'resource_kind', 'exam_year',
        'source_url', 'listing_url', 'source_host', 'listing_host', 'source_fingerprint', 'file_disk',
        'file_path', 'mime_type', 'sha256', 'status', 'published_on',
        'extracted_at', 'verified_at', 'extraction_metadata', 'metadata',
        'review_notes',
    ];

    protected $casts = [
        'published_on' => 'date',
        'extracted_at' => 'datetime',
        'verified_at' => 'datetime',
        'extraction_metadata' => 'array',
        'metadata' => 'array',
    ];

    public function examDefinition()
    {
        return $this->belongsTo(AdmissionExamDefinition::class, 'admission_exam_definition_id');
    }

    public function datasetVersions()
    {
        return $this->belongsToMany(
            AdmissionDatasetVersion::class,
            'admission_dataset_resources'
        )->withTimestamps();
    }

    public function supersededResource()
    {
        return $this->belongsTo(self::class, 'supersedes_resource_id');
    }

    public function replacements()
    {
        return $this->hasMany(self::class, 'supersedes_resource_id');
    }
}

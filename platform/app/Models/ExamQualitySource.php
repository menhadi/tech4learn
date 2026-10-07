<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExamQualitySource extends Model
{
    protected $hidden = ['file_path', 'source_url', 'provider_file_id'];
    protected $fillable = [
        'organization_id', 'exam_id', 'role', 'kind', 'label', 'storage_disk', 'file_path',
        'source_url', 'provider', 'provider_file_id', 'provider_uploaded_at', 'is_active',
    ];

    protected $casts = ['is_active' => 'boolean', 'provider_uploaded_at' => 'datetime'];

    public function exam() { return $this->belongsTo(Exam::class); }
    public function organization() { return $this->belongsTo(Organization::class); }
}

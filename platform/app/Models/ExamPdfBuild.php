<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExamPdfBuild extends Model
{
    protected $fillable = [
        'organization_id', 'exam_id', 'package_id', 'language_id', 'document_type',
        'status', 'source_fingerprint', 'current_path', 'version_path', 'file_size',
        'page_count', 'last_error', 'queued_at', 'started_at', 'completed_at', 'requested_by',
    ];

    protected $casts = [
        'queued_at' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function exam() { return $this->belongsTo(Exam::class); }
    public function package() { return $this->belongsTo(Package::class); }
    public function language() { return $this->belongsTo(Language::class); }
}
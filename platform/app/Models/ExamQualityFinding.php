<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExamQualityFinding extends Model
{
    protected $fillable = [
        'organization_id', 'audit_id', 'exam_id', 'question_id', 'source',
        'issue_type', 'severity', 'status', 'title', 'details', 'confidence',
        'evidence', 'screenshot_path', 'reviewed_by', 'reviewed_at',
    ];

    protected $casts = ['evidence' => 'array', 'reviewed_at' => 'datetime'];

    public function audit() { return $this->belongsTo(ExamQualityAudit::class, 'audit_id'); }
    public function exam() { return $this->belongsTo(Exam::class); }
    public function question() { return $this->belongsTo(Question::class); }
    public function reviewer() { return $this->belongsTo(User::class, 'reviewed_by'); }
}

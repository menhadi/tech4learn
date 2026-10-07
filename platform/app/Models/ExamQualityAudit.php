<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExamQualityAudit extends Model
{
    protected $fillable = [
        'organization_id', 'exam_id', 'requested_by', 'public_token', 'status',
        'include_ai', 'include_visual', 'include_source', 'question_limit', 'total_questions',
        'checked_questions', 'passed_questions', 'warning_count', 'error_count',
        'options', 'failure_message', 'started_at', 'completed_at',
    ];

    protected $casts = [
        'include_ai' => 'boolean', 'include_visual' => 'boolean', 'include_source' => 'boolean', 'options' => 'array',
        'started_at' => 'datetime', 'completed_at' => 'datetime',
    ];

    public function exam() { return $this->belongsTo(Exam::class); }
    public function requester() { return $this->belongsTo(User::class, 'requested_by'); }
    public function organization() { return $this->belongsTo(Organization::class); }
    public function findings() { return $this->hasMany(ExamQualityFinding::class, 'audit_id'); }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QuestionRepairDraft extends Model
{
    protected $fillable = [
        'organization_id', 'audit_id', 'exam_id', 'question_id', 'status',
        'original_payload', 'proposed_payload', 'changed_fields', 'evidence',
        'confidence', 'failure_message', 'question_updated_at', 'created_by',
        'reviewed_by', 'reviewed_at', 'published_at',
    ];

    protected $casts = [
        'original_payload' => 'array', 'proposed_payload' => 'array',
        'changed_fields' => 'array', 'evidence' => 'array',
        'question_updated_at' => 'datetime', 'reviewed_at' => 'datetime',
        'published_at' => 'datetime',
    ];

    public function audit() { return $this->belongsTo(ExamQualityAudit::class, 'audit_id'); }
    public function exam() { return $this->belongsTo(Exam::class); }
    public function question() { return $this->belongsTo(Question::class); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
    public function reviewer() { return $this->belongsTo(User::class, 'reviewed_by'); }
}
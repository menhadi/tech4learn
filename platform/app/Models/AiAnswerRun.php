<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiAnswerRun extends Model
{
    protected $fillable = [
        'organization_id', 'exam_id', 'batch_token', 'requested_by', 'status',
        'provider', 'additional_instructions', 'total_questions', 'processed_questions',
        'ready_count', 'discrepancy_count', 'failed_count', 'options',
        'failure_message', 'started_at', 'completed_at',
    ];

    protected $casts = [
        'options' => 'array', 'started_at' => 'datetime', 'completed_at' => 'datetime',
    ];

    public function organization() { return $this->belongsTo(Organization::class); }
    public function exam() { return $this->belongsTo(Exam::class); }
    public function requester() { return $this->belongsTo(User::class, 'requested_by'); }
    public function drafts() { return $this->hasMany(AiAnswerDraft::class, 'run_id'); }
    public function curriculumEvents() { return $this->hasMany(AiCurriculumEvent::class, 'run_id'); }
}

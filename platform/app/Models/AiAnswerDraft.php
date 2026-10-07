<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiAnswerDraft extends Model
{
    protected $fillable = [
        'organization_id', 'run_id', 'exam_id', 'question_id', 'status', 'mode',
        'original_payload', 'proposed_payload', 'changed_fields', 'discrepancies',
        'provider', 'model', 'confidence', 'failure_message', 'question_updated_at',
        'reviewed_by', 'reviewed_at', 'published_by', 'published_at',
    ];

    protected $casts = [
        'original_payload' => 'array', 'proposed_payload' => 'array',
        'changed_fields' => 'array', 'discrepancies' => 'array',
        'question_updated_at' => 'datetime', 'reviewed_at' => 'datetime', 'published_at' => 'datetime',
    ];

    public function run() { return $this->belongsTo(AiAnswerRun::class, 'run_id'); }
    public function exam() { return $this->belongsTo(Exam::class); }
    public function question() { return $this->belongsTo(Question::class); }
    public function reviewer() { return $this->belongsTo(User::class, 'reviewed_by'); }
    public function publisher() { return $this->belongsTo(User::class, 'published_by'); }
    public function curriculumEvents() { return $this->hasMany(AiCurriculumEvent::class, 'draft_id'); }
}

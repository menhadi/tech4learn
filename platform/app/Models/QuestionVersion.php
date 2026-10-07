<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QuestionVersion extends Model
{
    protected $fillable = ['organization_id', 'question_id', 'repair_draft_id', 'ai_answer_draft_id', 'payload', 'created_by'];
    protected $casts = ['payload' => 'array'];
    public function question() { return $this->belongsTo(Question::class); }
    public function repairDraft() { return $this->belongsTo(QuestionRepairDraft::class); }
    public function aiAnswerDraft() { return $this->belongsTo(AiAnswerDraft::class); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
}
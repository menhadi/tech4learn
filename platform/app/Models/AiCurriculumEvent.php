<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiCurriculumEvent extends Model
{
    protected $fillable = [
        'organization_id', 'run_id', 'draft_id', 'question_id',
        'entity_type', 'entity_id', 'name', 'created_by',
    ];

    public function run() { return $this->belongsTo(AiAnswerRun::class, 'run_id'); }
    public function draft() { return $this->belongsTo(AiAnswerDraft::class, 'draft_id'); }
    public function question() { return $this->belongsTo(Question::class); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
}

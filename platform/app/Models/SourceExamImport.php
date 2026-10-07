<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SourceExamImport extends Model
{
    protected $fillable = ['organization_id','created_by','exam_id','name','status','question_source_name','question_source_path','question_source_type','answer_source_name','answer_source_path','answer_source_type','solution_source_name','solution_source_path','solution_source_type','settings','detected_questions','ready_questions','review_questions','failure_message','processing_started_at','processing_completed_at','published_at'];
    protected $casts = ['settings' => 'array', 'processing_started_at' => 'datetime', 'processing_completed_at' => 'datetime', 'published_at' => 'datetime'];
    protected $hidden = ['question_source_path','answer_source_path','solution_source_path'];
    public function drafts() { return $this->hasMany(SourceExamQuestionDraft::class); }
    public function exam() { return $this->belongsTo(Exam::class); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
}

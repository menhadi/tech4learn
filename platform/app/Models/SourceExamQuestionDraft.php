<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SourceExamQuestionDraft extends Model
{
    protected $fillable = ['organization_id','source_exam_import_id','question_id','paper_question_number','printed_question_number','status','payload','source_evidence','ai_fields','review_notes'];
    protected $casts = ['payload' => 'array', 'source_evidence' => 'array', 'ai_fields' => 'array'];
    public function sourceImport() { return $this->belongsTo(SourceExamImport::class, 'source_exam_import_id'); }
    public function question() { return $this->belongsTo(Question::class); }
}

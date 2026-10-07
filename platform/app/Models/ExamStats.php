<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExamStats extends Model
{
    protected $table = 'exam_stats';
    protected $fillable = [
        'organization_id',
        'exam_result_id', 'exam_id', 'question_id', 'answer',
        'marks_obtained', 'ai_assessed', 'uploaded_answer_path',
        'extracted_answer_text'
    ];
    
    public function question()
    {
        return $this->belongsTo(Question::class);
    }
}

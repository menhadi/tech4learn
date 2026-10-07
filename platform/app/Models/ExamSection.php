<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExamSection extends Model
{
    protected $fillable = ['exam_id', 'question_section_id', 'name', 'display_order', 'duration'];

    protected $casts = [
        'display_order' => 'integer',
        'duration' => 'integer',
    ];

    public function exam()
    {
        return $this->belongsTo(Exam::class);
    }

    public function definition()
    {
        return $this->belongsTo(QuestionSection::class, 'question_section_id');
    }

    public function questions()
    {
        return $this->belongsToMany(Question::class, 'exam_questions')
            ->withPivot('exam_section_id');
    }
}

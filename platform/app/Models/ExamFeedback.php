<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExamFeedback extends Model
{
    use HasFactory;

    protected $table = 'exam_feedbacks';

    protected $fillable = [
        'organization_id',
        'exam_result_id',
        'guest_id',
        'student_id',
        'test_instructions',
        'language_of_questions',
        'text_experience',
        'feedback',
    ];

    public function examResult()
    {
        return $this->belongsTo(ExamResult::class);
    }

    public function student()
    {
        return $this->belongsTo(Student::class);
    }
}
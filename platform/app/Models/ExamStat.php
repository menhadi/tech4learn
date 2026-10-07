<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExamStat extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'exam_result_id',
        'exam_id',
        'guest_id',
        'student_id',
        'user_id',
        'question_id',
        'subject_id',
        'exam_section_id',
        'subject_time',
        'is_section',
        'ques_no',
        'options',
        'attempt_time',
        'opened',
        'answered',
        'answer_locked_at',
        'review',
        'option_selected',
        'answer',
        'true_false',
        'fill_blank',
        'correct_answer',
        'marks',
        'negative_marks',
        'marks_obtained',
        'ques_status',
        'closed',
        'checking_time',
        'time_taken',
        'bookmark',
        'selected_option_indices',
        'si_answers',
    ];

    protected $casts = [
        'selected_option_indices' => 'array',
        'si_answers' => 'array',
        'answer_locked_at' => 'datetime',
    ];

    public function examResult()
    {
        return $this->belongsTo(ExamResult::class);
    }

    public function exam()
    {
        return $this->belongsTo(Exam::class);
    }

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function question()
    {
        return $this->belongsTo(Question::class);
    }

    public function subject()
    {
        return $this->belongsTo(Subject::class);
    }

    public function section()
    {
        return $this->belongsTo(ExamSection::class, 'exam_section_id');
    }
}

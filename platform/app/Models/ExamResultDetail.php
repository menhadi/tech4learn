<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExamResultDetail extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'exam_id',
        'exam_result_id',
        'total_students',
        'correct_questions',
        'incorrect_questions',
        'right_marks',
        'left_questions',
        'left_question_marks',
        'rank',
        'negative_marks',
        'formatted_total_test_time',
        'formatted_test_time',
        'subject_reports',
        'question_reports',
    ];

    protected $casts = [
        'subject_reports' => 'array',
        'question_reports' => 'array',
    ];
}
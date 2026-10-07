<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudentFlashcardCourseProgress extends Model
{
    protected $table = 'student_flashcard_course_progress';
    protected $fillable = ['student_id', 'package_id', 'current_flashcard_set_id', 'current_flashcard_id', 'last_studied_at'];
    protected $casts = ['last_studied_at' => 'datetime'];
}

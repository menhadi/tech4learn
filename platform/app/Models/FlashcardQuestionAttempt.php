<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FlashcardQuestionAttempt extends Model
{
    protected $fillable = [
        'organization_id',
        'student_id',
        'guest_id',
        'flashcard_id',
        'question_id',
        'attempts',
        'correct_attempts',
        'wrong_attempts',
        'last_is_correct',
        'last_attempted_at',
    ];

    protected $casts = [
        'last_is_correct' => 'boolean',
        'last_attempted_at' => 'datetime',
    ];

    public function flashcard()
    {
        return $this->belongsTo(Flashcard::class);
    }

    public function question()
    {
        return $this->belongsTo(Question::class);
    }

    public function student()
    {
        return $this->belongsTo(Student::class);
    }
}

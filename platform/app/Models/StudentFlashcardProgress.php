<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StudentFlashcardProgress extends Model
{
    use HasFactory;

    protected $table = 'student_flashcard_progress';

    protected $fillable = [
        'student_id',
        'package_id',
        'flashcard_set_id',
        'flashcard_id',
        'viewed_at',
        'last_answer_correct',
        'correct_answered_at',
        'wrong_attempts',
        'correct_attempts',
        'confidence',
        'confidence_awarded',
        'points_earned',
        'last_interaction_at',
    ];

    protected $casts = [
        'viewed_at' => 'datetime',
        'last_answer_correct' => 'boolean',
        'correct_answered_at' => 'datetime',
        'confidence_awarded' => 'boolean',
        'last_interaction_at' => 'datetime',
    ];

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function package()
    {
        return $this->belongsTo(Package::class);
    }

    public function set()
    {
        return $this->belongsTo(FlashcardSet::class, 'flashcard_set_id');
    }

    public function card()
    {
        return $this->belongsTo(Flashcard::class, 'flashcard_id');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudentFlashcardPointEvent extends Model
{
    protected $fillable = [
        'student_id', 'package_id', 'flashcard_set_id', 'flashcard_id',
        'points', 'cards_studied', 'correct_answers', 'occurred_at',
    ];

    protected $casts = ['occurred_at' => 'datetime'];

    public function student()
    {
        return $this->belongsTo(Student::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StudentFlashcardPoint extends Model
{
    use HasFactory;

    protected $fillable = [
        'student_id',
        'package_id',
        'flashcard_set_id',
        'total_points',
        'cards_studied',
        'correct_answers',
        'last_activity_at',
    ];

    protected $casts = [
        'last_activity_at' => 'datetime',
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
}

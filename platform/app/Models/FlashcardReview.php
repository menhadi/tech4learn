<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FlashcardReview extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'student_id',
        'package_id',
        'flashcard_id',
        'response',
        'points',
        'reviewed_at',
    ];

    protected $casts = [
        'reviewed_at' => 'datetime',
    ];

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function card()
    {
        return $this->belongsTo(Flashcard::class, 'flashcard_id');
    }

    public function package()
    {
        return $this->belongsTo(Package::class);
    }
}

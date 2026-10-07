<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class QuestionsReport extends Model
{
    use HasFactory;

    protected $table = "questions_report";

    protected $fillable = [
        'organization_id',
        'guest_id',
        'student_id',
        'question_id',
        'flashcard_id',
        'flashcard_set_id',
        'report_source',
        'subject_id',
        'question_type',
        'message',
        'status',
        'guest_name',
        'guest_email',
    ];

    protected $casts = [
        'student_id' => 'integer',
        'question_id' => 'integer',
        'flashcard_id' => 'integer',
        'flashcard_set_id' => 'integer',
        'subject_id' => 'integer',
    ];

    // ✅ Relationships

    public function question()
    {
        return $this->belongsTo(Question::class, 'question_id');
    }

    public function subject()
    {
        return $this->belongsTo(Subject::class, 'subject_id');
    }

    public function student()
    {
        return $this->belongsTo(Student::class, 'student_id');
    }

    public function flashcard()
    {
        return $this->belongsTo(Flashcard::class, 'flashcard_id');
    }

    public function flashcardSet()
    {
        return $this->belongsTo(FlashcardSet::class, 'flashcard_set_id');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QuickQuizAnswer extends Model
{
    protected $fillable = [
        'quick_quiz_session_id',
        'question_id',
        'answer_payload',
        'correct_answer',
        'is_correct',
        'answered_at',
    ];

    protected $casts = [
        'answer_payload' => 'array',
        'is_correct' => 'boolean',
        'answered_at' => 'datetime',
    ];

    public function session()
    {
        return $this->belongsTo(QuickQuizSession::class, 'quick_quiz_session_id');
    }

    public function question()
    {
        return $this->belongsTo(Question::class);
    }
}

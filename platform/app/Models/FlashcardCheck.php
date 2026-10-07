<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FlashcardCheck extends Model
{
    protected $fillable = ['flashcard_id', 'source_question_id', 'difficulty', 'question', 'options', 'correct_answer', 'explanation', 'sort_order', 'status'];
    protected $casts = ['options' => 'array', 'status' => 'boolean'];
    public function card() { return $this->belongsTo(Flashcard::class, 'flashcard_id'); }
    public function sourceQuestion() { return $this->belongsTo(Question::class, 'source_question_id'); }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Flashcard extends Model
{
    use HasFactory;

    protected $fillable = [
        'flashcard_set_id',
        'title',
        'front',
        'back',
        'card_type',
        'options',
        'explanation',
        'hint',
        'difficulty',
        'source_label',
        'source_url',
        'source_question_id',
        'ai_generated',
        'sort_order',
        'status',
    ];

    protected $casts = [
        'status' => 'boolean',
        'ai_generated' => 'boolean',
        'options' => 'array',
    ];

    public function set()
    {
        return $this->belongsTo(FlashcardSet::class, 'flashcard_set_id');
    }

    public function checks()
    {
        return $this->hasMany(FlashcardCheck::class)->orderBy('sort_order')->orderBy('id');
    }

    public function reviews()
    {
        return $this->hasMany(FlashcardReview::class);
    }

    public function sourceQuestion()
    {
        return $this->belongsTo(Question::class, 'source_question_id');
    }

    public function sourceQuestions()
    {
        return $this->belongsToMany(Question::class, 'flashcard_question_links', 'flashcard_id', 'question_id')
            ->withPivot('sort_order')
            ->withTimestamps()
            ->orderBy('flashcard_question_links.sort_order')
            ->orderBy('questions.id');
    }
}

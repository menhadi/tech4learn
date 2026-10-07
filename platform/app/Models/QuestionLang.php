<?php


namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class QuestionLang extends Model
{
    use HasFactory;

    protected $fillable = [
        'question_id',
        'language_id',
        'question',
        'option1',
        'option2',
        'option3',
        'option4',
        'option5',
        'option6',
        'hint',
        'explanation',
        'fill_blank',
        'si_answer1',
        'translated_by',
        'source_fingerprint',
    ];

    public function question()
    {
        return $this->belongsTo(Question::class);
    }

    public function language()
    {
        return $this->belongsTo(Language::class);
    }
}
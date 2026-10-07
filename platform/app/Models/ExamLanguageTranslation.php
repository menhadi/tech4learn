<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExamLanguageTranslation extends Model
{
    protected $fillable = [
        'exam_id',
        'language_id',
        'name',
        'instruction',
        'syllabus',
        'translated_by',
        'source_fingerprint',
    ];

    public function exam()
    {
        return $this->belongsTo(Exam::class);
    }

    public function language()
    {
        return $this->belongsTo(Language::class);
    }
}

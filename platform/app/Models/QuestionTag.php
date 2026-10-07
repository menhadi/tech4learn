<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class QuestionTag extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'name',
        'slug',
        'status',
    ];

    protected $casts = [
        'status' => 'boolean',
    ];

    public function questions()
    {
        return $this->belongsToMany(Question::class, 'question_question_tag', 'question_tag_id', 'question_id')
            ->withTimestamps();
    }
}

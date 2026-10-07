<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class QuestionShareItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'batch_id',
        'source_question_id',
        'target_question_id',
        'status',
        'message',
    ];
}

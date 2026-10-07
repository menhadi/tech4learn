<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class QuestionShareBatch extends Model
{
    use HasFactory;

    protected $fillable = [
        'source_organization_id',
        'target_organization_id',
        'direction',
        'created_by',
        'question_count',
        'notes',
    ];

    public function items()
    {
        return $this->hasMany(QuestionShareItem::class, 'batch_id');
    }
}

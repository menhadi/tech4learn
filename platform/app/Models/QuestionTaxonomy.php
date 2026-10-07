<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QuestionTaxonomy extends Model
{
    protected $fillable = [
        'organization_id',
        'question_id',
        'group_id',
        'subject_id',
        'topic_id',
        'stopic_id',
    ];

    public function question() { return $this->belongsTo(Question::class); }
    public function group() { return $this->belongsTo(Group::class); }
    public function subject() { return $this->belongsTo(Subject::class); }
    public function topic() { return $this->belongsTo(Topic::class); }
    public function stopic() { return $this->belongsTo(Stopic::class); }
}

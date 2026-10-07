<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QuestionSection extends Model
{
    protected $fillable = ['organization_id', 'name', 'display_order', 'status'];

    protected $casts = ['display_order' => 'integer', 'status' => 'boolean'];

    public function groups()
    {
        return $this->belongsToMany(Group::class, 'question_section_groups')->withTimestamps();
    }

    public function questions()
    {
        return $this->hasMany(Question::class);
    }

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QuickQuizSession extends Model
{
    protected $fillable = [
        'public_id',
        'organization_id',
        'student_id',
        'guest_id',
        'group_id',
        'category_id',
        'subcategory_id',
        'package_id',
        'subject_id',
        'question_ids',
        'question_count',
        'answered_count',
        'correct_count',
        'status',
        'source',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'question_ids' => 'array',
        'question_count' => 'integer',
        'answered_count' => 'integer',
        'correct_count' => 'integer',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function answers()
    {
        return $this->hasMany(QuickQuizAnswer::class);
    }

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function group()
    {
        return $this->belongsTo(Group::class);
    }

    public function package()
    {
        return $this->belongsTo(Package::class);
    }
}

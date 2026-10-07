<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudentActivityEvent extends Model
{
    protected $fillable = [
        'organization_id',
        'student_id',
        'guest_id',
        'exam_id',
        'package_id',
        'order_id',
        'exam_result_id',
        'event_name',
        'source',
        'url',
        'referrer',
        'ip_address',
        'user_agent',
        'is_bot',
        'bot_name',
        'bot_reason',
        'metadata',
        'occurred_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'is_bot' => 'boolean',
        'occurred_at' => 'datetime',
    ];

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function exam()
    {
        return $this->belongsTo(Exam::class);
    }

    public function package()
    {
        return $this->belongsTo(Package::class);
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function examResult()
    {
        return $this->belongsTo(ExamResult::class);
    }
}

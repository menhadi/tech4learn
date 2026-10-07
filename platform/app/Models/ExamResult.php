<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExamResult extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'exam_id',
        'language_id',
        'guest_id',
        'guest_name',
        'guest_email',
        'guest_ip_address',
        'guest_user_agent',
        'guest_is_bot',
        'guest_bot_reason',
        'guest_result_prompted_at',
        'guest_result_viewed_at',
        'guest_contact_email_sent_at',
        'student_id',
        'user_id',
        'start_time',
        'end_time',
        'attempt_time',
        'total_test_time',
        'test_time',
        'pause_time',
        'total_question',
        'total_attempt',
        'total_answered',
        'total_marks',
        'obtained_marks',
        'result',
        'percent',
        'finalized_time',
        'tolerance_count',
        'ai_performance_analysis',
        'ai_performance_analysis_source',
        'ai_performance_analysis_provider',
        'ai_performance_analysis_generated_at',
    ];

    protected $casts = [
        'guest_is_bot' => 'boolean',
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'guest_result_prompted_at' => 'datetime',
        'guest_result_viewed_at' => 'datetime',
        'guest_contact_email_sent_at' => 'datetime',
        'ai_performance_analysis' => 'array',
        'ai_performance_analysis_generated_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saved(function (ExamResult $result) {
            if ($result->wasChanged('guest_email') && filled($result->guest_email) && !$result->guest_contact_email_sent_at) {
                app(\App\Services\StudentLifecycleEmailService::class)->sendGuestWelcome($result);
            }
        });
    }
    public function exam()
    {
        return $this->belongsTo(Exam::class);
    }

    public function language()
    {
        return $this->belongsTo(Language::class);
    }
    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // ==========================================
    // <-- CHANGE YAHAN HAI
    // Function ka naam 'feedbacks' se 'feedback' kar diya hai
    // ==========================================
    /**
     * Get all of the feedback for the ExamResult.
     */
    public function feedback()
    {
        // Humne keys (exam_result_id, id) ko bhi define kar diya hai, ye best practice hai
        return $this->hasMany(\App\Models\ExamFeedback::class, 'exam_result_id', 'id');
    }
    // ==========================================
    // END OF CHANGE
    // ==========================================
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class ContentNormalizationRun extends Model
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'organization_id', 'requested_by', 'mode', 'status', 'filters',
        'cursor_question_id', 'total_questions', 'processed_questions', 'stats',
        'child_run_ids', 'restored_child_run_ids', 'restore_exam_ids',
        'restored_exam_ids', 'restore_skipped', 'error',
        'started_at', 'finished_at',
    ];

    protected $casts = [
        'filters' => 'array',
        'stats' => 'array',
        'child_run_ids' => 'array',
        'restored_child_run_ids' => 'array',
        'restore_exam_ids' => 'array',
        'restored_exam_ids' => 'array',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function requester()
    {
        return $this->belongsTo(User::class, 'requested_by');
    }
}

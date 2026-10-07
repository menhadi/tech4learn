<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ImageCleanupRun extends Model
{
    protected $fillable = [
        'organization_id', 'exam_id', 'requested_by', 'batch_token', 'status',
        'provider', 'model', 'quality', 'action', 'instructions', 'total_images',
        'processed_images', 'ready_count', 'failed_count', 'failure_message',
        'started_at', 'stop_requested_at', 'completed_at',
    ];

    protected $casts = [
        'started_at' => 'datetime', 'stop_requested_at' => 'datetime', 'completed_at' => 'datetime',
    ];

    public function exam() { return $this->belongsTo(Exam::class); }
    public function requester() { return $this->belongsTo(User::class, 'requested_by'); }
    public function items() { return $this->hasMany(ImageCleanupItem::class, 'run_id'); }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OfficialExamSourceRun extends Model
{
    protected $fillable = [
        'official_exam_source_id', 'status', 'found_count', 'created_count', 'skipped_count',
        'review_count', 'diagnostics', 'failure_message', 'started_at', 'completed_at',
    ];

    protected $casts = ['diagnostics' => 'array', 'started_at' => 'datetime', 'completed_at' => 'datetime'];

    public function source() { return $this->belongsTo(OfficialExamSource::class, 'official_exam_source_id'); }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ImageCleanupItem extends Model
{
    protected $fillable = [
        'organization_id', 'run_id', 'exam_id', 'question_id', 'field', 'image_index',
        'status', 'original_src', 'original_field_html', 'proposed_field_html', 'output_path',
        'provider', 'model', 'request_id', 'failure_message', 'instructions', 'published_by', 'published_at',
    ];

    protected $casts = ['published_at' => 'datetime'];

    public function run() { return $this->belongsTo(ImageCleanupRun::class, 'run_id'); }
    public function exam() { return $this->belongsTo(Exam::class); }
    public function question() { return $this->belongsTo(Question::class); }
    public function publisher() { return $this->belongsTo(User::class, 'published_by'); }
}

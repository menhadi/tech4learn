<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ImageConversionItem extends Model
{
    protected $fillable = [
        'organization_id', 'run_id', 'exam_id', 'question_id', 'field', 'image_index',
        'status', 'original_src', 'source_path', 'backup_path', 'png_path', 'new_src',
        'failure_message', 'converted_at',
    ];

    protected $casts = ['converted_at' => 'datetime'];

    public function run() { return $this->belongsTo(ImageConversionRun::class, 'run_id'); }
    public function exam() { return $this->belongsTo(Exam::class); }
    public function question() { return $this->belongsTo(Question::class); }
}

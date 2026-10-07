<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OfficialExamSourceRule extends Model
{
    protected $fillable = [
        'official_exam_source_id', 'name', 'priority', 'match_pattern', 'language_mode',
        'language_id', 'extractor_script', 'ready_policy', 'exam_name_template', 'enabled',
        'version', 'settings',
    ];

    protected $casts = ['enabled' => 'boolean', 'settings' => 'array'];

    public function source() { return $this->belongsTo(OfficialExamSource::class, 'official_exam_source_id'); }
    public function language() { return $this->belongsTo(Language::class); }
}

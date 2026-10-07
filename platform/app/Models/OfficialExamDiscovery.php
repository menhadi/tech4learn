<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OfficialExamDiscovery extends Model
{
    protected $fillable = [
        'organization_id', 'official_exam_source_id', 'official_exam_source_rule_id',
        'official_exam_source_run_id', 'exam_id', 'source_exam_import_id', 'external_exam_key',
        'content_hash', 'observed_content_hash', 'status', 'exam_name', 'question_url',
        'answer_url', 'combined_url', 'archive_url', 'document_hashes', 'metadata',
        'source_config_version', 'rule_version', 'failure_message', 'first_seen_at',
        'last_seen_at', 'revised_at',
    ];

    protected $casts = [
        'document_hashes' => 'array', 'metadata' => 'array', 'first_seen_at' => 'datetime',
        'last_seen_at' => 'datetime', 'revised_at' => 'datetime',
    ];

    public function source() { return $this->belongsTo(OfficialExamSource::class, 'official_exam_source_id'); }
    public function rule() { return $this->belongsTo(OfficialExamSourceRule::class, 'official_exam_source_rule_id'); }
    public function run() { return $this->belongsTo(OfficialExamSourceRun::class, 'official_exam_source_run_id'); }
    public function exam() { return $this->belongsTo(Exam::class); }
    public function sourceImport() { return $this->belongsTo(SourceExamImport::class, 'source_exam_import_id'); }
}

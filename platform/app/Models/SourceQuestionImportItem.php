<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SourceQuestionImportItem extends Model
{
    protected $fillable = ['run_id','row_number','source_url','source_url_hash','adapter','status','metadata','payload','audit_result','content_hash','error_message','fetched_at','audited_at','repaired_at','published_at','question_id'];
    protected $casts = ['metadata' => 'array', 'payload' => 'array', 'audit_result' => 'array', 'fetched_at' => 'datetime', 'audited_at' => 'datetime', 'repaired_at' => 'datetime', 'published_at' => 'datetime'];
    public function run() { return $this->belongsTo(SourceQuestionImportRun::class, 'run_id'); }
    public function question() { return $this->belongsTo(Question::class); }
}

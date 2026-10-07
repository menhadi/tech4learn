<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QuestionRepairReleaseItem extends Model
{
    protected $fillable = ['release_id','question_id','repair_draft_id','published_version_id','restored_version_id','status','restored_at'];
    protected $casts = ['restored_at'=>'datetime'];
    public function release() { return $this->belongsTo(QuestionRepairRelease::class, 'release_id'); }
    public function question() { return $this->belongsTo(Question::class); }
    public function repairDraft() { return $this->belongsTo(QuestionRepairDraft::class); }
    public function publishedVersion() { return $this->belongsTo(QuestionVersion::class, 'published_version_id'); }
    public function restoredVersion() { return $this->belongsTo(QuestionVersion::class, 'restored_version_id'); }
};

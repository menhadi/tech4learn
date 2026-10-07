<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QuestionRepairRelease extends Model
{
    protected $fillable = ['organization_id','audit_id','exam_id','name','status','created_by','published_at','restored_at'];
    protected $casts = ['published_at'=>'datetime','restored_at'=>'datetime'];
    public function audit() { return $this->belongsTo(ExamQualityAudit::class, 'audit_id'); }
    public function exam() { return $this->belongsTo(Exam::class); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
    public function items() { return $this->hasMany(QuestionRepairReleaseItem::class, 'release_id'); }
};

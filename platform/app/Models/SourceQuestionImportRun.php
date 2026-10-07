<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SourceQuestionImportRun extends Model
{
    protected $fillable = ['organization_id','created_by','status','mode','adapter','total','processed','ready','published','duplicates','failed','options','failure_message'];
    protected $casts = ['options' => 'array'];
    public function items() { return $this->hasMany(SourceQuestionImportItem::class, 'run_id'); }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExamQualitySourceProfile extends Model
{
    protected $fillable = ['organization_id', 'name', 'settings', 'created_by'];

    protected $casts = ['settings' => 'array'];

    public function organization() { return $this->belongsTo(Organization::class); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
}

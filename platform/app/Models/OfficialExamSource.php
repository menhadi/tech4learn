<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OfficialExamSource extends Model
{
    protected $fillable = [
        'organization_id', 'created_by', 'website_name', 'name', 'source_url', 'driver',
        'check_interval_minutes', 'enabled', 'automation_mode', 'config_version',
        'discovery_settings', 'exam_defaults', 'last_checked_at', 'last_success_at',
        'next_check_at', 'last_error',
    ];

    protected $casts = [
        'enabled' => 'boolean', 'discovery_settings' => 'array', 'exam_defaults' => 'array',
        'last_checked_at' => 'datetime', 'last_success_at' => 'datetime', 'next_check_at' => 'datetime',
    ];

    public function rules() { return $this->hasMany(OfficialExamSourceRule::class)->orderBy('priority')->orderBy('id'); }
    public function discoveries() { return $this->hasMany(OfficialExamDiscovery::class); }
    public function runs() { return $this->hasMany(OfficialExamSourceRun::class); }
    public function organization() { return $this->belongsTo(Organization::class); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
}

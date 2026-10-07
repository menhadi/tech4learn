<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SaasLead extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'preferred_plan',
        'institute_name',
        'contact_name',
        'email',
        'phone',
        'institute_type',
        'expected_students',
        'message',
        'source_url',
        'ip_address',
        'user_agent',
        'status',
    ];

    protected $casts = [
        'expected_students' => 'integer',
    ];

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }
}
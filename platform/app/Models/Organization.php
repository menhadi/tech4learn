<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Organization extends Model
{
    use HasFactory;

    protected $fillable = [
        'saas_plan_id',
        'name',
        'slug',
        'domain',
        'subdomain',
        'email',
        'phone',
        'logo',
        'favicon',
        'status',
        'trial_ends_at',
        'subscription_ends_at',
        'settings',
    ];

    protected $casts = [
        'settings' => 'array',
        'trial_ends_at' => 'datetime',
        'subscription_ends_at' => 'datetime',
    ];

    public function plan()
    {
        return $this->belongsTo(SaasPlan::class, 'saas_plan_id');
    }

    public function users()
    {
        return $this->belongsToMany(User::class, 'organization_users')
            ->withPivot(['role', 'status'])
            ->withTimestamps();
    }

    public function organizationUsers()
    {
        return $this->hasMany(OrganizationUser::class);
    }

    public function packages()
    {
        return $this->hasMany(Package::class);
    }

    public function exams()
    {
        return $this->hasMany(Exam::class);
    }

    public function students()
    {
        return $this->hasMany(Student::class);
    }
}

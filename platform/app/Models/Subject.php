<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Subject extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'subject_name',
        'ordering',
    ];

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function groups()
    {
        return $this->belongsToMany(Group::class, 'subject_groups', 'subject_id', 'group_id');
    }

    public function topics()
    {
        return $this->hasMany(Topic::class);
    }
}
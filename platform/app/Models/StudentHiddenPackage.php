<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StudentHiddenPackage extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'student_id',
        'package_id',
        'hidden_at',
    ];

    protected $casts = [
        'hidden_at' => 'datetime',
    ];

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function package()
    {
        return $this->belongsTo(Package::class);
    }
}

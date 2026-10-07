<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Cart extends Model
{
    use HasFactory;

    protected $fillable = [
        'student_id',
        'package_id',
        'name',
        'description',
        'price',
        'image',
        'quantity',
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

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExamPackage extends Model
{
    use HasFactory;

    protected $fillable = [
        'package_id',
        'exam_id',
    ];
}
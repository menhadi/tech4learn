<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExamProctorImage extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'exam_id',
        'guest_id',
        'student_id',
        'image_path'
    ];
}
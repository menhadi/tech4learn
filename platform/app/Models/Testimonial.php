<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations; // <-- 1. Import kiya

class Testimonial extends Model
{
    use HasFactory, HasTranslations; // <-- 2. Trait ko 'use' kiya

    /**
     * Define karo kaun se fields translate honge.
     */
    public $translatable = ['name', 'feedback']; // <-- 3. Ye array add kiya

    protected $fillable = [
        'organization_id',
        'image_url',
        'name',
        'feedback',
    ];
}
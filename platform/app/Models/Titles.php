<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations; // <-- 1. Import kiya

class Titles extends Model
{
    use HasFactory, HasTranslations; // <-- 2. Trait ko 'use' kiya

    /**
     * Define karo kaun se fields translate honge.
     * In columns ko humne migration mein JSON banaya tha.
     */
    public $translatable = ['title', 'sub_title']; // <-- 3. Ye array add kiya

    protected $fillable = [
        'organization_id',
        'section_key',
        'title',
        'sub_title',
    ];
}

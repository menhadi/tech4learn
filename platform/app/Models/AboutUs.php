<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations; // <-- 1. YEH IMPORT ADD KIYA HAI

class AboutUs extends Model
{
    use HasFactory, HasTranslations; // <-- 2. YAHAN 'HasTranslations' ADD KIYA HAI

    /**
     * Define karo kaun se fields translate honge.
     */
    public $translatable = ['title', 'description']; // <-- 3. YEH ARRAY ADD KIYA HAI

    protected $fillable = [
        'image_url',
        'title',
        'description',
        'meta_title',
        'meta_description',
        'meta_keywords',
        'canonical_url',
        'og_title',
        'og_description',
        'og_image',
        'robots_meta',
        'seo_schema',
    ];

}
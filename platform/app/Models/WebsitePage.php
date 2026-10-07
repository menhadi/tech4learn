<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations; // <-- 1. Import kiya

class WebsitePage extends Model
{
    use HasFactory, HasTranslations; // <-- 2. Trait ko 'use' kiya

    /**
     * Define karo kaun se fields translate honge.
     * In columns ko humne migration mein JSON banaya tha.
     */
    public $translatable = ['short_title', 'title', 'description']; // <-- 3. Ye array add kiya

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'organization_id',
        'short_title',
        'title', 
        'description',
        'show_in_menu',
        'show_in_footer', // <-- Ye aapka existing code hai
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

    /**
     * The attributes that should be cast.
     * * Yeh ensure karta hai ki 'show_in_menu' hamesha boolean (true/false) ki tarah treat ho.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'show_in_menu' => 'boolean',
        'show_in_footer' => 'boolean', // <-- Ye aapka existing code hai
    ];
    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

}
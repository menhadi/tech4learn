<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    use HasFactory;

    protected $table = 'category';

    protected $casts = [
        'show_in_header' => 'boolean',
    ];

    protected $fillable = [
        'organization_id',
        'parent_id',
        'title',
        'description',
        'slug',
        'status',
        'header_display_order',
        'display_order',
        'show_in_header',
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
     * Parent Category
     */
    public function parent()
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    /**
     * Child Categories
     */
    public function children()
    {
        return $this->hasMany(Category::class, 'parent_id')->displayOrdered();
    }

    /**
     * Scope for active categories
     */
    public function scopeActive($query)
    {
        return $query->where('status', 1);
    }

    /**
     * Scope for parent categories only
     */
    public function scopeParents($query)
    {
        return $query->whereNull('parent_id');
    }

    /**
     * Scope for child categories only
     */
    public function scopeChildren($query)
    {
        return $query->whereNotNull('parent_id');
    }
    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function groups() { return $this->belongsToMany(Group::class, 'category_groups')->withPivot('display_order')->withTimestamps(); }
    public function scopeDisplayOrdered($query) { return $query->orderByRaw('CASE WHEN display_order IS NULL OR display_order = 0 THEN 1 ELSE 0 END')->orderBy('display_order')->orderBy('title')->orderBy('id'); }
    public function packages() { return $this->hasMany(Package::class, 'category_level_1'); }
    public function subcategoryPackages() { return $this->hasMany(Package::class, 'category_level_2'); }
    public function exams() { return $this->hasMany(Exam::class, 'category_level_1'); }
}

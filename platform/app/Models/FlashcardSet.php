<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FlashcardSet extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'package_id',
        'group_id',
        'category_level_1',
        'category_level_2',
        'subject_id',
        'topic_id',
        'stopic_id',
        'created_by',
        'title',
        'display_order',
        'source_type',
        'source_file',
        'status',
        'meta_title',
        'canonical_url',
        'meta_description',
        'meta_keywords',
        'og_title',
        'og_image',
        'og_description',
        'robots_meta',
        'seo_schema',
    ];

    protected $casts = [
        'status' => 'boolean',
    ];

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function package()
    {
        return $this->belongsTo(Package::class);
    }

    public function group()
    {
        return $this->belongsTo(Group::class);
    }

    public function category()
    {
        return $this->belongsTo(Category::class, 'category_level_1');
    }

    public function subcategory()
    {
        return $this->belongsTo(Category::class, 'category_level_2');
    }

    public function subject()
    {
        return $this->belongsTo(Subject::class);
    }

    public function topic()
    {
        return $this->belongsTo(Topic::class);
    }

    public function stopic()
    {
        return $this->belongsTo(Stopic::class);
    }

    public function cards()
    {
        return $this->hasMany(Flashcard::class);
    }
}

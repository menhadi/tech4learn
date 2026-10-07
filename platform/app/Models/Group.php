<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations; // <-- 1. Import kiya

class Group extends Model
{
    use HasFactory, HasTranslations; // <-- 2. Trait ko 'use' kiya

    /**
     * Define karo kaun se fields translate honge.
     */
    public $translatable = ['group_name']; // <-- 3. Ye array add kiya

    protected $fillable = [
        'organization_id',
        'group_name',
        'slug',
        'description',
        'display_order',
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

    // Existing Relationships (Ye pehle se the)
    public function examGroups()
    {
        return $this->hasMany(ExamGroup::class, 'group_id');
    }

    public function questionGroups()
    {
        return $this->hasMany(QuestionGroup::class, 'group_id');
    }

    public function studentGroups()
    {
        return $this->hasMany(StudentGroup::class, 'group_id');
    }

    public function subjectGroups()
    {
        return $this->hasMany(SubjectGroup::class, 'group_id');
    }

    public function subjects()
    {
        return $this->belongsToMany(Subject::class, 'subject_groups', 'group_id', 'subject_id');
    }

    public function userGroups()
    {
        return $this->hasMany(UserGroup::class, 'group_id');
    }

    public function packageGroup()
    {
        return $this->hasMany(PackageGroup::class, 'group_id');
    }

    public function packages()
    {
        // Many-to-Many relationship with Package model via package_groups table
        return $this->belongsToMany(Package::class, 'package_groups', 'group_id', 'package_id')
                    ->withTimestamps()
                    ->orderByRaw('CASE WHEN packages.display_order IS NULL OR packages.display_order = 0 THEN 1 ELSE 0 END')
                    ->orderBy('packages.display_order')
                    ->orderBy('packages.name')
                    ->orderBy('packages.id');
    }

    // ✅ NAYA RELATIONSHIP ADD KIYA GAYA HAI YAHAN
    /**
     * The exams that belong to the group.
     * Defines a many-to-many relationship with the Exam model.
     * Uses the 'exam_groups' pivot table.
     */
    public function exams()
    {
        // Many-to-Many relationship with Exam model via exam_groups table
        return $this->belongsToMany(Exam::class, 'exam_groups', 'group_id', 'exam_id')
                    ->withTimestamps(); // Agar exam_groups table mein timestamp columns hain (created_at, updated_at)
    }
    public function questionSections()
    {
        return $this->belongsToMany(QuestionSection::class, 'question_section_groups')->withTimestamps();
    }

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function categories() { return $this->belongsToMany(Category::class, 'category_groups')->withPivot('display_order')->withTimestamps()->orderByRaw('CASE WHEN category_groups.display_order IS NULL OR category_groups.display_order = 0 THEN 1 ELSE 0 END')->orderBy('category_groups.display_order')->orderByRaw('CASE WHEN category.display_order IS NULL OR category.display_order = 0 THEN 1 ELSE 0 END')->orderBy('category.display_order')->orderBy('category.title'); }

    public function scopeDisplayOrdered($query) { return $query->orderByRaw('CASE WHEN display_order IS NULL OR display_order = 0 THEN 1 ELSE 0 END')->orderBy('display_order')->orderBy('group_name')->orderBy('id'); }
}

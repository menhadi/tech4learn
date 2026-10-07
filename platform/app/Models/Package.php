<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations; // <-- Yeh pehle se tha

class Package extends Model
{
    use HasFactory, HasTranslations; // <-- Yeh pehle se tha

    /**
     * Define karo kaun se fields translate honge.
     */
    public $translatable = ['name', 'description']; // <-- Yeh pehle se tha

    protected $fillable = [
        'organization_id',
        'name',
        'slug',
        'slug2',
        'description',
        'amount',
        'discounted_amount',
        'package_type',
        'auto_enroll_on_registration',
        'status', // <-- Yeh pehle se tha
        'show_pdf_download',
        'show_solution_pdf_download',
        'pdf_title_text',
        'solution_pdf_title_text',
        'pdf_header_text',
        'pdf_footer_text',
        'pdf_watermark_text',
        'solution_pdf_header_text',
        'solution_pdf_footer_text',
        'solution_pdf_watermark_text',
        'flashcards_enabled',
        'guest_flashcards_enabled',
        'ai_flashcard_generation_enabled',
        'photo',
        'expiry_days',
        'display_order',

        'category_level_1',
        'category_level_2',
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

    // ✅✅✅ YEH NAYA BLOCK ADD KIYA GAYA HAI ✅✅✅
    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'status' => 'boolean', // Taa_ki 1/0 ko true/false maana jaaye
        'auto_enroll_on_registration' => 'boolean',
        'show_pdf_download' => 'boolean',
        'show_solution_pdf_download' => 'boolean',
        'flashcards_enabled' => 'boolean',
        'guest_flashcards_enabled' => 'boolean',
        'ai_flashcard_generation_enabled' => 'boolean',
    ];
    // ✅✅✅ END OF NEW BLOCK ✅✅✅

    public function groups()
    {
        return $this->belongsToMany(Group::class, 'package_groups', 'package_id', 'group_id');
    }

    public function exams()
    {
        return $this->belongsToMany(Exam::class, 'exam_packages', 'package_id', 'exam_id')->withPivot('display_order')->orderByRaw('CASE WHEN exam_packages.display_order IS NULL OR exam_packages.display_order = 0 THEN 1 ELSE 0 END')->orderBy('exam_packages.display_order')->orderBy('exams.name')->orderBy('exams.id');
    }

    public function tags()
    {
        return $this->belongsToMany(PackageTag::class, 'package_tag_package', 'package_id', 'package_tag_id')
            ->withTimestamps();
    }

    public function flashcardSets()
    {
        return $this->hasMany(FlashcardSet::class);
    }

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function scopeDisplayOrdered($query) { return $query->orderByRaw('CASE WHEN display_order IS NULL OR display_order = 0 THEN 1 ELSE 0 END')->orderBy('display_order')->orderBy('name')->orderBy('id'); }

    public function category() { return $this->belongsTo(Category::class, 'category_level_1'); }
    public function subcategory() { return $this->belongsTo(Category::class, 'category_level_2'); }
}

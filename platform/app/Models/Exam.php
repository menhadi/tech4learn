<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Exam extends Model
{
    use HasFactory;

    public function availabilityStatus(?\Carbon\CarbonInterface $at = null): string
    {
        $at ??= now();
        if ($this->start_date && $at->lt($this->start_date)) {
            return 'Upcoming';
        }
        if ($this->end_date && $at->gt($this->end_date)) {
            return 'Expired';
        }
        return 'Live';
    }

    public function isAvailableAt(?\Carbon\CarbonInterface $at = null): bool
    {
        return $this->availabilityStatus($at) === 'Live';
    }

    public const TEST_TYPE_FULL_LENGTH = 'full_length';
    public const TEST_TYPE_SUBJECT = 'subject_test';
    public const TEST_TYPE_TOPIC = 'topic_test';
    public const TEST_TYPE_SUBTOPIC = 'subtopic_test';
    public const TEST_TYPE_SECTIONAL = 'sectional_test';
    public const TEST_TYPE_PREVIOUS_YEAR = 'previous_year';
    public const TEST_TYPE_OTHER = 'other';

    public static function testTypeLabels(): array
    {
        return [
            self::TEST_TYPE_FULL_LENGTH => 'Full-Length Tests',
            self::TEST_TYPE_SUBJECT => 'Subject Tests',
            self::TEST_TYPE_TOPIC => 'Topic Tests',
            self::TEST_TYPE_SUBTOPIC => 'Subtopic Tests',
            self::TEST_TYPE_SECTIONAL => 'Sectional Tests',
            self::TEST_TYPE_PREVIOUS_YEAR => 'Previous Year Papers',
            self::TEST_TYPE_OTHER => 'Other Tests',
        ];
    }

    protected $fillable = [
        'organization_id',
        'created_by_student_id',
        'is_student_practice',
        'name',
        'display_order',
        'test_type',
        'test_subject_id',
        'test_topic_id',
        'test_stopic_id',
        'slug',
        'passing_percentage',
        'instruction',
        'syllabus',
        'duration',
        'is_subject_timer',
        'timer_mode',
        'grouping_mode',
        'attempt_count',
        'start_date',
        'end_date',
        'offline_enabled',
        'show_answer_sheet',
        'negative_marking',
        'random_question',
        'result_after_finish',
        'mode',
        'instant_result',
        'option_shuffle',
        'allow_answer_change',
        'multi_language',
        'math_editor',
        'browser_tolerance',
        'proctor',
        'calculator_allowed', // ✅ ADDED THIS
        'tolerance_count',
        'status',

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


    protected $casts = [
        'offline_enabled' => 'boolean',
        'start_date' => 'datetime',
        'end_date' => 'datetime',
        'is_student_practice' => 'boolean',
        'attempt_count' => 'integer',
        'browser_tolerance' => 'boolean',
        'random_question' => 'boolean',
        'result_after_finish' => 'boolean',
        'option_shuffle' => 'boolean',
        'allow_answer_change' => 'boolean',
        'is_subject_timer' => 'boolean',
        'proctor' => 'boolean',
        'calculator_allowed' => 'boolean',
        'negative_marking' => 'boolean',
        'multi_language' => 'boolean',
    ];

    public function scopeDisplayOrdered($query)
    {
        return $query->orderByRaw('CASE WHEN display_order IS NULL OR display_order = 0 THEN 1 ELSE 0 END')
            ->orderBy('display_order')
            ->orderBy('name')
            ->orderBy('id');
    }

    public function scopeActive($query)
    {
        return $query->where($this->qualifyColumn('status'), 'Active');
    }

    public function packages()
    {
        return $this->belongsToMany(Package::class, 'exam_packages')->withPivot('display_order');
    }

    public function groups()
    {
        return $this->belongsToMany(Group::class, 'exam_groups');
    }

    public function languages()
    {
        return $this->belongsToMany(Language::class, 'exam_languages')
            ->withPivot(['translation_status', 'last_error', 'translating_at', 'translated_at', 'auto_translate', 'auto_pdf', 'translation_approved_at', 'translation_approved_by'])
            ->withTimestamps();
    }

    public function languageTranslations()
    {
        return $this->hasMany(ExamLanguageTranslation::class);
    }

    public function pdfBuilds()
    {
        return $this->hasMany(ExamPdfBuild::class);
    }
    public function questions()
    {
        return $this->belongsToMany(Question::class, 'exam_questions')
            ->withPivot('exam_section_id');
    }

    public function canAttemptOnline(): bool
    {
        if ($this->status !== 'Active') return false;
        if ($this->relationLoaded('questions')) return $this->questions->contains(fn ($question) => in_array(strtolower((string) $question->status), ['active','yes'], true));
        return $this->questions()->whereIn('questions.status', ['active','Active','Yes','yes'])->exists();
    }

    public function scopeStandalonePdfPapers($query)
    {
        return $query->active()->whereDoesntHave('packages')
            ->whereHas('qualitySources', fn ($source) => $source
                ->whereColumn('exam_quality_sources.organization_id', 'exams.organization_id')
                ->where('is_active', true)->whereIn('role', ['questions', 'combined'])->where('file_path', 'like', '%.pdf'));
    }

    public function sections()
    {
        return $this->hasMany(ExamSection::class)->orderBy('display_order')->orderBy('id');
    }

    public function qualitySources()
    {
        return $this->hasMany(ExamQualitySource::class);
    }

    public function subjectDurations()
    {
        return $this->hasMany(ExamSubjectDuration::class);
    }

    public function testSubject()
    {
        return $this->belongsTo(Subject::class, 'test_subject_id');
    }

    public function testTopic()
    {
        return $this->belongsTo(Topic::class, 'test_topic_id');
    }

    public function testSubtopic()
    {
        return $this->belongsTo(Stopic::class, 'test_stopic_id');
    }

    public function results()
    {
        return $this->hasMany(ExamResult::class, 'exam_id');
    }

    /**
     * Get a comma-separated list of group names for the exam.
     *
     * @return string|null
     */
    public function getGroupsListAttribute()
    {
        // Pehle check karein ki groups load hue hain ya nahi
        if (! $this->relationLoaded('groups')) {
            return null;
        }

        return $this->groups->pluck('group_name')->implode(', ');
    }

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function creatorStudent()
    {
        return $this->belongsTo(Student::class, 'created_by_student_id');
    }

    public function category() { return $this->belongsTo(Category::class, 'category_level_1'); }
    public function subcategory() { return $this->belongsTo(Category::class, 'category_level_2'); }
}

<?php

namespace App\Models; // ✅✅✅ YEH LINE FIX HO GAYI HAI ✅✅✅

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Question extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'question_code',
        'source_url',
        'source_reference',
        'ai_generated',
        'original_question_id',
        'qtype_id',
        'subject_id',
        'question_section_id',
        'topic_id',
        'stopic_id',
        'diff_id',
        'passage_id',
        'question',
        'option1',
        'option2',
        'option3',
        'option4',
        'option5',
        'option6',
        'marks',
        'negative_marks',
        'scoring_policy',
        'hint',
        'explanation',
        'answer',
        'true_false',
        'fill_blank',
        'fill_blank_config',
        'nat_config',
        'status',
        'correct_option_indices',
        'si_answer1',
    ];

    protected $casts = [
        'fill_blank_config' => 'array',
        'nat_config' => 'array',
        'correct_option_indices' => 'array',
    ];

    protected $hidden = [
        'source_url',
        'source_reference',
    ];

    protected static function booted()
    {

        static::created(function (Question $question) {
            if (! $question->question_code) {
                $question->forceFill(['question_code' => 'EWQ-'.str_pad((string) $question->id, 10, '0', STR_PAD_LEFT)])->saveQuietly();
            }
        });
    }

    public function qtype()
    {
        return $this->belongsTo(Qtype::class);
    }

    public function subject()
    {
        return $this->belongsTo(Subject::class);
    }

    public function questionSection()
    {
        return $this->belongsTo(QuestionSection::class);
    }

    public function topic()
    {
        return $this->belongsTo(Topic::class);
    }

    public function stopic()
    {
        return $this->belongsTo(Stopic::class);
    }

    public function diff()
    {
        return $this->belongsTo(Diff::class);
    }

    public function language()
    {
        return $this->belongsTo(Language::class);
    }

    public function passage()
    {
        return $this->belongsTo(Passage::class);
    }

    public function groups()
    {
        return $this->belongsToMany(Group::class, 'question_groups', 'question_id', 'group_id');
    }

    public function taxonomies()
    {
        return $this->hasMany(QuestionTaxonomy::class);
    }

    public function tags()
    {
        return $this->belongsToMany(QuestionTag::class, 'question_question_tag', 'question_id', 'question_tag_id')
            ->withTimestamps();
    }
    
    public function exams()
    {
        return $this->belongsToMany(Exam::class, 'exam_questions');
    }

    // Yeh function humne pichle step mein add kiya tha
    public function langs()
    {
        return $this->hasMany(QuestionLang::class);
    }
    public function versions()
    {
        return $this->hasMany(QuestionVersion::class);
    }
    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }


    public function correctOptionIndices(): array
    {
        return collect((array) $this->correct_option_indices)
            ->map(fn ($index) => (int) $index)
            ->filter(fn ($index) => $index >= 1 && $index <= 6)
            ->unique()->sort()->values()->all();
    }

    public function optionValue(int $index): ?string
    {
        if ($index < 1 || $index > 6) return null;
        $value = $this->{'option'.$index};
        return $value === null || $value === '' ? null : (string) $value;
    }

    public function correctOptionValues(): array
    {
        return collect($this->correctOptionIndices())->map(fn ($index) => $this->optionValue($index))
            ->filter(fn ($value) => $value !== null && $value !== '')->values()->all();
    }
}

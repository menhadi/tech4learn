<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use App\Support\SaasAccess;

class Language extends Model
{
    use HasFactory;
    protected $fillable = [
        'organization_id',
        'source_language_id',
        'name',
        'code',
        'value1',
        'value2',
        'is_enabled',
    ];

    public function scopeForOrganization($query, ?int $organizationId = null)
    {
        $organizationId = $organizationId ?: SaasAccess::organization()?->id;

        if ($organizationId && Schema::hasColumn($this->getTable(), 'organization_id')) {
            $query->where('organization_id', $organizationId);
        }

        return $query;
    }

    public function scopeEnabledForOrganization($query, ?int $organizationId = null)
    {
        $query->forOrganization($organizationId);

        if (Schema::hasColumn($this->getTable(), 'is_enabled')) {
            $query->where('is_enabled', true);
        }

        return $query;
    }

    public function exams()
    {
        return $this->belongsToMany(Exam::class, 'exam_languages')
            ->withPivot(['translation_status', 'last_error', 'translating_at', 'translated_at', 'auto_translate', 'auto_pdf', 'translation_approved_at', 'translation_approved_by'])
            ->withTimestamps();
    }
    public function sourceLanguage()
    {
        return $this->belongsTo(Language::class, 'source_language_id');
    }
}

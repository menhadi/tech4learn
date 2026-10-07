<?php

namespace App\Services;

use App\Models\Exam;
use App\Models\Language;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class ExamLanguageService
{
    public function english(int $organizationId): ?Language
    {
        return Language::enabledForOrganization($organizationId)
            ->whereRaw('LOWER(code) = ?', ['en'])
            ->first()
            ?: Language::enabledForOrganization($organizationId)
                ->whereRaw('LOWER(name) = ?', ['english'])
                ->first()
            ?: Language::enabledForOrganization($organizationId)->orderBy('id')->first();
    }

    public function normalizeIds(int $organizationId, array $languageIds): array
    {
        $ids = collect($languageIds)->map(fn ($id) => (int) $id)->filter()->unique()->values();
        $english = $this->english($organizationId);
        if ($english) $ids->prepend((int) $english->id);
        $ids = $ids->unique()->values();

        if (Language::enabledForOrganization($organizationId)->whereIn('id', $ids)->count() !== $ids->count()) {
            throw ValidationException::withMessages([
                'language_ids' => 'One or more selected languages are unavailable for this organization.',
            ]);
        }

        return $ids->all();
    }

    public function sync(Exam $exam, array $languageIds): void
    {
        $ids = $this->normalizeIds((int) $exam->organization_id, $languageIds);
        $englishId = $this->english((int) $exam->organization_id)?->id;
        $existing = $exam->languages()->get()->keyBy('id');
        $sync = [];

        foreach ($ids as $id) {
            $pivot = $existing->get($id)?->pivot;
            $sync[$id] = [
                'translation_status' => (int) $id === (int) $englishId
                    ? 'ready'
                    : ($pivot?->translation_status ?: 'pending'),
                'last_error' => $pivot?->last_error,
                'translating_at' => $pivot?->translating_at,
                'translated_at' => (int) $id === (int) $englishId
                    ? ($pivot?->translated_at ?: now())
                    : $pivot?->translated_at,
            ];
        }

        $exam->languages()->sync($sync);
        $exam->forceFill(['multi_language' => count($ids) > 1])->saveQuietly();
        $exam->unsetRelation('languages');
    }

    public function available(Exam $exam): Collection
    {
        $exam->loadMissing('languages');
        if ($exam->languages->isNotEmpty()) {
            return $exam->languages->sortBy(fn (Language $language) => strtolower($language->code) === 'en' ? '0' : '1'.strtolower($language->name))->values();
        }

        $english = $this->english((int) $exam->organization_id);
        return $english ? collect([$english]) : collect();
    }

    public function resolve(Exam $exam, int|string|null $requested): ?Language
    {
        $languages = $this->available($exam);
        $selected = is_numeric($requested)
            ? $languages->firstWhere('id', (int) $requested)
            : $languages->first(fn (Language $language) => strtolower((string) $language->code) === strtolower((string) $requested));

        return $selected ?: $languages->first(fn (Language $language) => strtolower((string) $language->code) === 'en')
            ?: $languages->first();
    }

    public function isEnglish(?Language $language): bool
    {
        return $language && (strtolower((string) $language->code) === 'en' || strtolower((string) $language->name) === 'english');
    }

    public function rememberPreference(Language $language): void
    {
        $code = strtolower((string) $language->code);
        session()->put('preferred_exam_language', $code);

        if (app(UiLanguageService::class)->supports($code, (int) $language->organization_id)) {
            session()->put('locale', $code);
            session()->put('direction', app(UiLanguageService::class)->direction($code));
        }

        if ($student = Auth::guard('student')->user()) {
            $student->forceFill(['language' => $code])->save();
        }
    }

    public function display(Exam $exam, ?Language $language): array
    {
        if (! $language || $this->isEnglish($language)) {
            return ['name' => $exam->name, 'instruction' => $exam->instruction, 'syllabus' => $exam->syllabus];
        }

        $translation = $exam->languageTranslations()->where('language_id', $language->id)->first();
        return [
            'name' => $translation?->name ?: $exam->name,
            'instruction' => $translation?->instruction ?: $exam->instruction,
            'syllabus' => $translation?->syllabus ?: $exam->syllabus,
        ];
    }
}

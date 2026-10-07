<?php

namespace App\Services;

use App\Models\Exam;
use App\Models\Language;
use App\Models\Package;
use App\Models\QuestionLang;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ExamPdfCacheService
{
    public const TEMPLATE_VERSION = 2;

    public function resolveLanguage(Exam $exam, int|string|null $requested): ?Language
    {
        $preferred = $requested
            ?: session('preferred_exam_language')
            ?: Auth::guard('student')->user()?->language
            ?: session('locale')
            ?: 'en';

        return app(ExamLanguageService::class)->resolve($exam, $preferred);
    }

    public function overlayTranslations(Collection $questions, ?Language $language): Collection
    {
        if (! $language || app(ExamLanguageService::class)->isEnglish($language)) {
            return $questions;
        }

        $translations = QuestionLang::query()
            ->where('language_id', $language->id)
            ->whereIn('question_id', $questions->pluck('id'))
            ->get()->keyBy('question_id');

        foreach ($questions as $question) {
            $translation = $translations->get($question->id);
            if (! $translation) continue;
            foreach (['question', 'option1', 'option2', 'option3', 'option4', 'option5', 'option6', 'hint', 'explanation', 'fill_blank', 'si_answer1'] as $field) {
                if (filled($translation->{$field})) $question->{$field} = $translation->{$field};
            }
        }

        return $questions;
    }

    public function fingerprint(Exam $exam, ?Language $language, ?Package $package, bool $solution): string
    {
        $questionVersions = DB::table('exam_questions')
            ->join('questions', 'questions.id', '=', 'exam_questions.question_id')
            ->leftJoin('question_langs', function ($join) use ($language) {
                $join->on('question_langs.question_id', '=', 'questions.id')
                    ->where('question_langs.language_id', '=', $language?->id ?: 0);
            })
            ->where('exam_questions.exam_id', $exam->id)
            ->orderBy('exam_questions.id')
            ->get([
                'exam_questions.id as pivot_id', 'exam_questions.question_id',
                'exam_questions.exam_section_id', 'questions.updated_at as source_updated_at',
                'question_langs.updated_at as translation_updated_at',
            ])->all();

        $examTranslationUpdated = $language
            ? $exam->languageTranslations()->where('language_id', $language->id)->value('updated_at')
            : null;
        $configuration = function_exists('getConfiguration') ? getConfiguration() : null;

        return hash('sha256', json_encode([
            'template' => self::TEMPLATE_VERSION,
            'exam' => [$exam->id, $exam->updated_at?->toJSON(), $examTranslationUpdated],
            'exam_content' => $exam->getAttributes(),
            'questions' => $questionVersions,
            'language' => [$language?->id, $language?->code],
            'package' => [$package?->id, $package?->updated_at?->toJSON()],
            'configuration' => [$configuration?->id, $configuration?->updated_at?->toJSON()],
            'solution' => $solution,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    public function path(Exam $exam, ?Language $language, ?Package $package, bool $solution, string $fingerprint): string
    {
        $kind = $solution ? 'solutions' : 'questions';
        $languageCode = Str::slug($language?->code ?: 'en') ?: 'en';
        $packageKey = $package?->id ?: 0;

        return storage_path("app/exam-pdfs/{$exam->organization_id}/{$exam->id}/{$packageKey}/{$languageCode}/{$kind}/{$fingerprint}.pdf");
    }

    public function removeSuperseded(string $activePath): void
    {
        foreach (glob(dirname($activePath).DIRECTORY_SEPARATOR.'*.pdf') ?: [] as $path) {
            if ($path !== $activePath && is_file($path)) @unlink($path);
        }
    }
}

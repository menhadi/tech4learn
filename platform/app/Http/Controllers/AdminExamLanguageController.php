<?php

namespace App\Http\Controllers;

use App\Models\Exam;
use App\Services\ExamLanguageService;
use App\Services\ExamTranslationService;
use App\Support\Tenant;
use Illuminate\Http\JsonResponse;

class AdminExamLanguageController extends Controller
{
    public function prepare(
        int $exam,
        int $language,
        ExamLanguageService $languages,
        ExamTranslationService $translations
    ): JsonResponse {
        $examModel = Exam::query()
            ->where('organization_id', Tenant::id())
            ->findOrFail($exam);
        $languageModel = $languages->available($examModel)->firstWhere('id', $language);
        abort_unless($languageModel, 404);

        return response()->json($translations->translateNextBatch($examModel, $languageModel));
    }
}

<?php

namespace App\Services;

use App\Models\ExamResult;
use App\Support\AiProvider;
use App\Support\SaasAccess;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class StudentPerformanceInsightService
{
    public function build(ExamResult $result, array $comparisonData, array $timeAnalysis, iterable $subjectAnalysis, iterable $topicAnalysis, iterable $difficultyStats): array
    {
        $fallback = $this->fallbackInsight($result, $comparisonData, $timeAnalysis, $subjectAnalysis, $topicAnalysis, $difficultyStats);

        if (! SaasAccess::featureEnabled('ai_student_analysis')) {
            return $fallback;
        }

        if ($stored = $this->storedInsight($result)) {
            return $this->withFreshDataPoints($result, $stored, $comparisonData, $subjectAnalysis, $topicAnalysis, $difficultyStats);
        }

        $provider = AiProvider::firstAvailable(getConfiguration());

        if (! $provider) {
            return $fallback;
        }

        $prompt = $this->prompt($result, $comparisonData, $timeAnalysis, $subjectAnalysis, $topicAnalysis, $difficultyStats);
        $content = $this->callAi($provider, $prompt);
        $aiInsight = $this->extractJson($content);

        if (! $this->validInsight($aiInsight)) {
            return $fallback;
        }

        $insight = [
            'result_id' => $result->id,
            'exam_id' => $result->exam_id,
            'exam_name' => $result->exam->name ?? 'Exam',
            'source' => 'ai',
            'provider' => $provider['stored_name'] ?? strtoupper($provider['provider']),
            'language_code' => $this->insightLanguage($result)['code'],
            'summary' => $this->cleanText($aiInsight['summary'] ?? $fallback['summary']),
            'strengths' => $this->cleanList($aiInsight['strengths'] ?? []),
            'weaknesses' => $this->cleanList($aiInsight['weaknesses'] ?? []),
            'improvements' => $this->cleanList($aiInsight['improvements'] ?? []),
            'data_points' => $this->dataPoints($result, $comparisonData, $subjectAnalysis, $topicAnalysis, $difficultyStats),
        ];

        $this->persistInsight($result, $insight);

        return $insight;
    }

    private function prompt(ExamResult $result, array $comparisonData, array $timeAnalysis, iterable $subjectAnalysis, iterable $topicAnalysis, iterable $difficultyStats): string
    {
        $outputLanguage = $this->insightLanguage($result);
        $payload = [
            'output_language' => $outputLanguage,
            'exam' => $result->exam->name ?? 'Exam',
            'score_percent' => round((float) ($result->percent ?? 0), 2),
            'obtained_marks' => round((float) ($result->obtained_marks ?? 0), 2),
            'total_marks' => round((float) ($result->total_marks ?? 0), 2),
            'result' => $result->result,
            'rank' => $comparisonData['rank'] ?? null,
            'percentile' => $comparisonData['percentile'] ?? null,
            'average_score' => round((float) ($comparisonData['avg_score'] ?? 0), 2),
            'time_analysis' => $timeAnalysis,
            'subjects' => collect($subjectAnalysis)->map(fn ($item) => [
                'name' => $item['name'] ?? null,
                'accuracy' => $item['accuracy'] ?? null,
                'status' => $item['status'] ?? null,
            ])->values(),
            'topics' => collect($topicAnalysis)->map(fn ($item) => [
                'name' => $item->name ?? null,
                'accuracy' => $item->accuracy ?? null,
                'status' => $item->status ?? null,
            ])->values(),
            'difficulty' => collect($difficultyStats)->map(fn ($item) => [
                'level' => $item->diff_level ?? null,
                'total' => $item->total ?? 0,
                'correct' => $item->correct ?? 0,
                'wrong' => $item->wrong ?? 0,
                'skipped' => $item->skipped ?? 0,
            ])->values(),
            'previous_performance_context' => $this->previousPerformanceContext($result),
        ];

        return "You are an exam performance coach for school and competitive exam students.\n"
            . "Write all student-facing values in {$outputLanguage['name']} ({$outputLanguage['code']}); keep JSON keys in English.\n"
            . "Analyze this result and return ONLY valid JSON with keys: summary, strengths, weaknesses, improvements.\n"
            . "Rules: be specific, practical, short, supportive, and avoid mentioning any AI provider name. "
            . "Each strengths/weaknesses/improvements value must be an array of 2-4 concise strings.\n\n"
            . json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function storedInsight(ExamResult $result): ?array
    {
        if (! Schema::hasColumn('exam_results', 'ai_performance_analysis')) {
            return null;
        }

        $stored = $result->ai_performance_analysis;

        if (is_string($stored)) {
            $stored = json_decode($stored, true);
        }

        if (! $this->validInsight($stored)) {
            return null;
        }

        $storedResultId = (int) ($stored['result_id'] ?? 0);
        $storedExamId = (int) ($stored['exam_id'] ?? 0);

        if ($storedResultId !== (int) $result->id || $storedExamId !== (int) $result->exam_id) {
            return null;
        }
        if (($stored['language_code'] ?? 'en') !== $this->insightLanguage($result)['code']) {
            return null;
        }

        return $stored;
    }

    private function insightLanguage(ExamResult $result): array
    {
        $language = $result->language ?: $result->student?->language;
        if (is_object($language)) return ['code' => strtolower((string) $language->code), 'name' => (string) $language->name];
        $code = strtolower((string) ($language ?: session('preferred_exam_language', 'en')));
        $configured = \App\Models\Language::enabledForOrganization($result->organization_id)->where('code', $code)->first();

        return ['code' => $code ?: 'en', 'name' => $configured?->name ?: ($code === 'en' ? 'English' : strtoupper($code))];
    }

    private function persistInsight(ExamResult $result, array $insight): void
    {
        if (! Schema::hasColumn('exam_results', 'ai_performance_analysis')) {
            return;
        }

        DB::table('exam_results')
            ->where('id', $result->id)
            ->update([
                'ai_performance_analysis' => json_encode($insight, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'ai_performance_analysis_source' => $insight['source'] ?? 'ai',
                'ai_performance_analysis_provider' => $insight['provider'] ?? null,
                'ai_performance_analysis_generated_at' => now(),
            ]);
    }

    private function withFreshDataPoints(ExamResult $result, array $insight, array $comparisonData, iterable $subjectAnalysis, iterable $topicAnalysis, iterable $difficultyStats): array
    {
        $freshDataPoints = $this->dataPoints($result, $comparisonData, $subjectAnalysis, $topicAnalysis, $difficultyStats);

        if (($insight['data_points'] ?? []) === $freshDataPoints) {
            return $insight;
        }

        $insight['data_points'] = $freshDataPoints;

        if (Schema::hasColumn('exam_results', 'ai_performance_analysis')) {
            DB::table('exam_results')
                ->where('id', $result->id)
                ->update([
                    'ai_performance_analysis' => json_encode($insight, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ]);
        }

        return $insight;
    }

    private function previousPerformanceContext(ExamResult $result): array
    {
        if (! $result->student_id) {
            return [];
        }

        return ExamResult::query()
            ->with('exam:id,name')
            ->where('student_id', $result->student_id)
            ->where('id', '!=', $result->id)
            ->when($result->organization_id, fn ($query) => $query->where('organization_id', $result->organization_id))
            ->whereNotNull('end_time')
            ->latest('end_time')
            ->take(3)
            ->get()
            ->map(function (ExamResult $previous) {
                $stored = $previous->ai_performance_analysis;

                if (is_string($stored)) {
                    $stored = json_decode($stored, true);
                }

                return [
                    'exam' => $previous->exam->name ?? 'Previous exam',
                    'score_percent' => round((float) ($previous->percent ?? 0), 2),
                    'result' => $previous->result,
                    'previous_summary' => is_array($stored) ? ($stored['summary'] ?? null) : null,
                    'previous_weaknesses' => is_array($stored) ? array_slice($stored['weaknesses'] ?? [], 0, 3) : [],
                    'previous_improvements' => is_array($stored) ? array_slice($stored['improvements'] ?? [], 0, 3) : [],
                ];
            })
            ->values()
            ->all();
    }

    private function callAi(array $provider, string $prompt): ?string
    {
        try {
            return AiProvider::generateText($provider, $prompt, 'Return only valid JSON.', 0.2, 4096, 45);
        } catch (\Throwable $exception) {
            Log::warning('Student performance AI insight failed', ['provider' => $provider['provider'] ?? null, 'message' => $exception->getMessage()]);
            return null;
        }
    }

    private function extractJson(?string $content): ?array
    {
        if (! $content) {
            return null;
        }

        $content = trim($content);
        $content = preg_replace('/^```json\s*/i', '', $content);
        $content = preg_replace('/^```\s*/', '', $content);
        $content = preg_replace('/\s*```$/', '', $content);

        $decoded = json_decode($content, true);

        if (json_last_error() === JSON_ERROR_NONE) {
            return $decoded;
        }

        if (preg_match('/\{.*\}/s', $content, $matches)) {
            $decoded = json_decode($matches[0], true);
            return json_last_error() === JSON_ERROR_NONE ? $decoded : null;
        }

        return null;
    }

    private function validInsight(?array $insight): bool
    {
        return is_array($insight)
            && ! empty($insight['summary'])
            && ! empty($insight['strengths'])
            && ! empty($insight['weaknesses'])
            && ! empty($insight['improvements']);
    }

    private function fallbackInsight(ExamResult $result, array $comparisonData, array $timeAnalysis, iterable $subjectAnalysis, iterable $topicAnalysis, iterable $difficultyStats): array
    {
        $subjects = collect($subjectAnalysis);
        $topics = collect($topicAnalysis);
        $difficulties = collect($difficultyStats);

        $strongSubjects = $subjects->where('status', 'Strong')->pluck('name')->take(3)->values();
        $weakSubjects = $subjects->where('status', 'Weak')->pluck('name')->take(3)->values();
        $weakTopics = $topics->where('status', 'Weak')->pluck('name')->take(3)->values();
        $toughDifficulty = $difficulties->sortByDesc(fn ($item) => (int) ($item->wrong ?? 0) + (int) ($item->skipped ?? 0))->first();

        $strengths = $strongSubjects->isNotEmpty()
            ? $strongSubjects->map(fn ($name) => "Strong accuracy in {$name}.")->all()
            : ['You completed the attempt and now have clear performance data to work with.'];

        if (($timeAnalysis['rapid_fire'] ?? 0) > 0) {
            $strengths[] = 'You solved some questions quickly and correctly.';
        }

        $weaknesses = $weakSubjects->isNotEmpty()
            ? $weakSubjects->map(fn ($name) => "Needs more practice in {$name}.")->all()
            : ['Review incorrect and skipped questions to find your weak areas.'];

        if ($weakTopics->isNotEmpty()) {
            $weaknesses[] = 'Weak topics: ' . $weakTopics->implode(', ') . '.';
        }

        $improvements = [
            'Revise weak topics first, then attempt a short mixed practice set.',
            'Review every wrong answer and write the reason for the mistake.',
        ];

        if (($timeAnalysis['careless_mistake'] ?? 0) > 0) {
            $improvements[] = 'Slow down slightly on easy questions to reduce careless mistakes.';
        }

        if ($toughDifficulty && (($toughDifficulty->wrong ?? 0) + ($toughDifficulty->skipped ?? 0)) > 0) {
            $improvements[] = 'Practice more ' . ($toughDifficulty->diff_level ?? 'difficult') . ' level questions.';
        }

        return [
            'result_id' => $result->id,
            'exam_id' => $result->exam_id,
            'exam_name' => $result->exam->name ?? 'Exam',
            'source' => 'rules',
            'provider' => null,
            'summary' => $this->summaryForScore($result, (float) ($result->percent ?? 0), $comparisonData),
            'strengths' => array_values(array_slice(array_unique($strengths), 0, 4)),
            'weaknesses' => array_values(array_slice(array_unique($weaknesses), 0, 4)),
            'improvements' => array_values(array_slice(array_unique($improvements), 0, 4)),
            'data_points' => $this->dataPoints($result, $comparisonData, $subjectAnalysis, $topicAnalysis, $difficultyStats),
        ];
    }

    private function dataPoints(ExamResult $result, array $comparisonData, iterable $subjectAnalysis, iterable $topicAnalysis, iterable $difficultyStats): array
    {
        $weakTopic = collect($topicAnalysis)->sortBy('accuracy')->first();
        $weakSubject = collect($subjectAnalysis)->sortBy('accuracy')->first();
        $difficulty = collect($difficultyStats)->sortByDesc(fn ($item) => (int) ($item->wrong ?? 0) + (int) ($item->skipped ?? 0))->first();

        return array_values(array_filter([
            'Score: ' . round((float) ($result->percent ?? 0), 1) . '%',
            isset($comparisonData['rank']) ? 'Rank: ' . $comparisonData['rank'] : null,
            isset($comparisonData['percentile']) ? 'Percentile: ' . round((float) $comparisonData['percentile'], 1) . '%' : null,
            $weakSubject ? 'Lowest subject: ' . ($weakSubject['name'] ?? 'Subject') . ' (' . round((float) ($weakSubject['accuracy'] ?? 0), 1) . '%)' : null,
            $weakTopic ? 'Lowest topic: ' . ($weakTopic->name ?? 'Topic') . ' (' . round((float) ($weakTopic->accuracy ?? 0), 1) . '%)' : null,
            $difficulty ? 'Difficulty focus: ' . ($difficulty->diff_level ?? 'Level') : null,
        ]));
    }

    private function summaryForScore(ExamResult $result, float $percent, array $comparisonData): string
    {
        $examName = $result->exam->name ?? null;
        $prefix = $examName ? "{$examName}: " : '';

        if ($percent >= 75) {
            return $prefix . 'Good performance at ' . round($percent, 1) . '%. Focus on accuracy consistency and the few remaining weak topics.';
        }

        if ($percent >= 45) {
            return $prefix . 'Moderate performance at ' . round($percent, 1) . '%. A focused revision plan can quickly improve your score.';
        }

        return $prefix . 'This attempt scored ' . round($percent, 1) . '% and shows clear improvement areas. Start with basics, weak topics, and careful review.';
    }

    private function cleanText(?string $value): string
    {
        return trim(strip_tags((string) $value));
    }

    private function cleanList(array $items): array
    {
        return collect($items)
            ->map(fn ($item) => $this->cleanText((string) $item))
            ->filter()
            ->take(4)
            ->values()
            ->all();
    }
}

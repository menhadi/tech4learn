<?php

namespace App\Http\Controllers\Students;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use App\Models\ExamResult;
use App\Models\ExamStat;
use App\Models\ExamResultDetail;
use App\Models\Subject;
use App\Models\Group;
use App\Models\Topic; // ✅ Import Topic
use App\Helpers\ResultHelper;
use Illuminate\Support\Facades\Validator;
use App\Models\Exam;
use Carbon\Carbon;
use App\Models\ExamProctorImage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use App\Support\Tenant;
use App\Services\StudentPerformanceInsightService;
use App\Services\StudentActivityTracker;
use App\Services\ExamLanguageService;

class StudentDashboardController extends Controller
{
    private function tenantId(): ?int
    {
        return class_exists(Tenant::class) ? Tenant::hostId(request()->getHost()) : null;
    }

    private function scopeSubjectTenant($query, ?int $tenantId)
    {
        return $query->when($tenantId, function ($query) use ($tenantId) {
            $query->whereHas('groups', fn ($groupQuery) => $groupQuery->where('groups.organization_id', $tenantId));
        });
    }

    private function scopeTopicTenant($query, ?int $tenantId)
    {
        return $query->when($tenantId, function ($query) use ($tenantId) {
            $query->whereHas('subject.groups', fn ($groupQuery) => $groupQuery->where('groups.organization_id', $tenantId));
        });
    }

    private function scopeOfficialExamResults($query)
    {
        return $query->whereHas('exam', function ($examQuery) {
            $examQuery->where(function ($examQuery) {
                $examQuery->whereNull('is_student_practice')
                    ->orWhere('is_student_practice', false);
            });
        });
    }

    public function dashboard()
    {
        $studentId = Auth::guard('student')->id();
        $student = Auth::guard('student')->user();
        $tenantId = Tenant::hostId(request()->getHost()) ?: ($student->organization_id ?? null);
        $studentGroups = $student->groups->pluck('id');

        $examResults = $this->scopeOfficialExamResults(ExamResult::where('student_id', $studentId))
            ->when($tenantId, fn ($query) => $query->where('organization_id', $tenantId))
            ->whereNotNull('end_time')
            ->selectRaw('COUNT(*) as total_exams, SUM(CASE WHEN result = "Pass" THEN 1 ELSE 0 END) as best_result_count, AVG(obtained_marks) as avg_obtained_marks, AVG(percent) as avg_percentile')
            ->first();

        $failedExams = $this->scopeOfficialExamResults(ExamResult::where('student_id', $studentId))
            ->when($tenantId, fn ($query) => $query->where('organization_id', $tenantId))
            ->where('result', 'Fail')
            ->with('exam:id,name')
            ->orderBy('start_time', 'desc')
            ->limit(5)
            ->get();

        $recentExams = $this->scopeOfficialExamResults(ExamResult::where('student_id', $studentId))
            ->when($tenantId, fn ($query) => $query->where('organization_id', $tenantId))
            ->whereNotNull('end_time')
            ->with('exam:id,name')
            ->latest('end_time')
            ->limit(5)
            ->get();

        $examLanguageService = app(ExamLanguageService::class);
        $preferredLanguage = $student->language ?: session('preferred_exam_language') ?: session('locale') ?: 'en';
        $failedExams->concat($recentExams)->each(function ($result) use ($examLanguageService, $preferredLanguage) {
            if (! $result->exam) {
                return;
            }

            $language = $examLanguageService->resolve(
                $result->exam,
                $result->language_id ?: $preferredLanguage
            );
            $result->exam->setAttribute('display_name', $examLanguageService->display($result->exam, $language)['name']);
        });
        $recentComparisons = $this->comparisonDataForResults($recentExams, $tenantId);
        $recentExams->each(function ($result) use ($recentComparisons) {
            $result->display_rank = $recentComparisons[$result->id]['rank'] ?? null;
        });

        $latestInsightResult = $recentExams->first(function ($result) {
            $insight = $result->ai_performance_analysis;

            if (is_string($insight)) {
                $insight = json_decode($insight, true);
            }

            return is_array($insight) && ! empty($insight['summary']);
        });
        $latestRankResult = $recentExams->first();
        $latestComparisonData = $latestRankResult ? ($recentComparisons[$latestRankResult->id] ?? []) : [];
        $latestPerformanceInsight = $latestInsightResult
            ? $this->refreshDashboardInsightPoints($latestInsightResult, $tenantId, $latestComparisonData)
            : null;
        $dashboardGroupRank = $latestRankResult ? $this->dashboardGroupRank($latestRankResult, $student, $tenantId, $studentId) : null;

        $totalAttempts = (int) ($examResults->total_exams ?? 0);
        $avgPercent = round((float) ($examResults->avg_percentile ?? 0), 1);
        $recentScores = $recentExams->pluck('percent')->filter(fn ($score) => $score !== null)->map(fn ($score) => round((float) $score, 1))->values();
        $lastScore = $recentScores->first();
        $previousScore = $recentScores->get(1);
        $trendText = ($lastScore !== null && $previousScore !== null)
            ? ($lastScore >= $previousScore ? 'Latest score improved by ' : 'Latest score dropped by ') . abs(round($lastScore - $previousScore, 1)) . '% from the previous attempt.'
            : 'Complete more exams to build a clearer performance trend.';

        $overallPerformance = [
            'summary' => $totalAttempts > 0
                ? "Across {$totalAttempts} completed exams, your average score is {$avgPercent}%. Your latest rank and percentile update as new attempts are submitted."
                : 'Start your first exam to build a combined performance analysis.',
            'points' => array_values(array_filter([
                "Attempts: {$totalAttempts}",
                "Average score: {$avgPercent}%",
                isset($latestComparisonData['rank']) ? "Latest rank: {$latestComparisonData['rank']}" : null,
                isset($latestComparisonData['percentile']) ? 'Latest percentile: ' . round((float) $latestComparisonData['percentile'], 1) . '%' : null,
                $lastScore !== null ? "Latest score: {$lastScore}%" : null,
            ])),
            'tips' => $totalAttempts > 0
                ? array_values(array_filter([
                    $trendText,
                    $failedExams->isNotEmpty() ? 'Review failed exams first: ' . $failedExams->pluck('exam.display_name')->filter()->take(2)->implode(', ') . '.' : 'Keep practicing mixed sets to maintain consistency.',
                    $avgPercent < 50 ? 'Focus on basics and weak topics before attempting full-length tests.' : 'Use timed practice to improve speed without losing accuracy.',
                ]))
                : [
                    'Start with easy level practice tests daily.',
                    'Check My Reports after each attempt for topic-wise performance.',
                    'Keep a daily practice habit for consistent progress.',
                ],
        ];

        $upcomingExams = Exam::where('status', 'Active')
            ->when($tenantId, fn ($query) => $query->where('organization_id', $tenantId))
            ->whereHas('groups', function ($query) use ($studentGroups) {
                $query->whereIn('group_id', $studentGroups);
            })
            ->where('end_date', '>', Carbon::now())
            ->orderBy('start_date', 'asc')
            ->limit(5)
            ->get();

        $upcomingExams->each(function (Exam $exam) use ($examLanguageService, $preferredLanguage) {
            $language = $examLanguageService->resolve($exam, $preferredLanguage);
            $exam->setAttribute('display_name', $examLanguageService->display($exam, $language)['name']);
        });
        
        return view('students.dashboard', [
            'totalExams' => $examResults->total_exams,
            'bestResultCount' => $examResults->best_result_count,
            'dashboardGroupRank' => $dashboardGroupRank,
            'avgObtainedMarks' => $examResults->avg_obtained_marks,
            'avgPercentile' => $examResults->avg_percentile,
            'failedExams' => $failedExams,
            'recentExams' => $recentExams,
            'upcomingExams' => $upcomingExams,
            'latestInsightResult' => $latestInsightResult,
            'latestPerformanceInsight' => $latestPerformanceInsight,
            'overallPerformance' => $overallPerformance,
        ]);
    }

    public function quickQuizzes()
    {
        $student = Auth::guard('student')->user();
        $tenantId = Tenant::hostId(request()->getHost()) ?: ($student->organization_id ?? null);
        $assignedQuizGroups = $student->groups
            ->filter(fn ($group) => ! $tenantId || (int) $group->organization_id === (int) $tenantId)
            ->sortBy(fn ($group) => [(int) ($group->display_order ?: PHP_INT_MAX), (int) $group->id])
            ->values();
        $fallbackQuizGroups = Group::query()
            ->when($tenantId, fn ($query, $id) => $query->where('organization_id', $id))
            ->whereHas('packages', fn ($query) => $query->where('status', 1))
            ->orderBy('display_order')
            ->limit(12)
            ->get();

        return view('students.quick-quizzes', [
            'quizGroups' => $assignedQuizGroups
                ->concat($fallbackQuizGroups)
                ->unique('id')
                ->take(12)
                ->values(),
            'quickQuizDefaultGroupId' => $assignedQuizGroups->first()?->id,
        ]);
    }
    private function comparisonDataForResults($results, ?int $tenantId): array
    {
        $examIds = $results->pluck('exam_id')->filter()->unique()->values();

        if ($examIds->isEmpty()) {
            return [];
        }

        $scoreBands = $this->scopeOfficialExamResults(ExamResult::query())
            ->when($tenantId, fn ($query) => $query->where('organization_id', $tenantId))
            ->whereIn('exam_id', $examIds)
            ->whereNotNull('end_time')
            ->select('exam_id', 'percent')
            ->selectRaw('COUNT(*) as frequency')
            ->groupBy('exam_id', 'percent')
            ->get()
            ->groupBy('exam_id');

        return $results->mapWithKeys(function (ExamResult $result) use ($scoreBands) {
            $bands = $scoreBands->get($result->exam_id, collect());
            $totalCandidates = (int) $bands->sum('frequency');
            $rank = (int) $bands
                ->where('percent', '>', (float) $result->percent)
                ->sum('frequency') + 1;
            $percentile = $totalCandidates > 1
                ? round((($totalCandidates - $rank) / ($totalCandidates - 1)) * 100, 2)
                : ($totalCandidates === 1 ? 100 : null);

            return [$result->id => array_filter([
                'rank' => $totalCandidates > 0 ? $rank : null,
                'percentile' => $percentile,
                'total_students' => $totalCandidates,
            ], fn ($value) => $value !== null)];
        })->all();
    }

    private function comparisonDataForResult(ExamResult $result, ?int $tenantId): array
    {
        if (! $result->exam_id) {
            return [];
        }

        $baseQuery = $this->scopeOfficialExamResults(ExamResult::where('exam_id', $result->exam_id))
            ->when($tenantId, fn ($query) => $query->where('organization_id', $tenantId))
            ->whereNotNull('end_time');

        $totalCandidates = (clone $baseQuery)->count();
        $rank = (clone $baseQuery)
            ->where('percent', '>', $result->percent)
            ->count() + 1;
        $percentile = $totalCandidates > 1
            ? round((($totalCandidates - $rank) / ($totalCandidates - 1)) * 100, 2)
            : ($totalCandidates === 1 ? 100 : null);

        return array_filter([
            'rank' => $totalCandidates > 0 ? $rank : null,
            'percentile' => $percentile,
            'total_students' => $totalCandidates,
        ], fn ($value) => $value !== null);
    }

    private function refreshDashboardInsightPoints(ExamResult $result, ?int $tenantId, ?array $comparisonData = null): ?array
    {
        $insight = $result->ai_performance_analysis;

        if (is_string($insight)) {
            $insight = json_decode($insight, true);
        }

        if (! is_array($insight) || empty($insight['summary'])) {
            return null;
        }

        $comparisonData ??= $this->comparisonDataForResult($result, $tenantId);
        $existingPoints = collect($insight['data_points'] ?? [])
            ->reject(fn ($point) => Str::startsWith((string) $point, ['Rank:', 'Percentile:']))
            ->values()
            ->all();

        $rankPoints = array_values(array_filter([
            isset($comparisonData['rank']) ? 'Rank: ' . $comparisonData['rank'] : null,
            isset($comparisonData['percentile']) ? 'Percentile: ' . round((float) $comparisonData['percentile'], 1) . '%' : null,
        ]));

        $insight['data_points'] = array_values(array_merge(array_slice($existingPoints, 0, 2), $rankPoints));

        return $insight;
    }

    public function leaderboard()
    {
        $studentId = Auth::guard('student')->id();
        $student = Auth::guard('student')->user();
        $tenantId = Tenant::hostId(request()->getHost()) ?: ($student->organization_id ?? null);

        $latestResult = $this->scopeOfficialExamResults(ExamResult::query())
            ->with(['exam.groups:id,group_name', 'exam.packages:id,name,category_level_1,category_level_2'])
            ->where('student_id', $studentId)
            ->when($tenantId, fn ($query) => $query->where('organization_id', $tenantId))
            ->whereNotNull('end_time')
            ->whereNotNull('percent')
            ->latest('end_time')
            ->first();

        $boards = collect();

        if ($latestResult?->exam) {
            $exam = $latestResult->exam;
            $group = $exam->groups->first() ?: $student->groups->first();
            $package = $exam->packages->first();
            $categoryId = $exam->category_level_1 ?: ($package->category_level_1 ?? null);
            $subcategoryId = $exam->category_level_2 ?: ($package->category_level_2 ?? null);
            $categoryTitle = $categoryId
                ? DB::table('category')->where('id', $categoryId)->value('title')
                : null;
            $subcategoryTitle = $subcategoryId
                ? DB::table('category')->where('id', $subcategoryId)->where('parent_id', $categoryId)->value('title')
                : null;

            if ($group) {
                $boards->push($this->leaderboardBoard(
                    $group->group_name . ' Rank',
                    'Group leaderboard',
                    'Students in this group',
                    fn ($query) => $query->join('exam_groups', 'exam_results.exam_id', '=', 'exam_groups.exam_id')
                        ->where('exam_groups.group_id', $group->id),
                    $studentId,
                    $tenantId
                ));
            }

            if ($categoryId && $categoryTitle) {
                $boards->push($this->leaderboardBoard(
                    $categoryTitle . ' Rank',
                    'Category leaderboard',
                    'Students in this category',
                    fn ($query) => $query->leftJoin('exam_packages', 'exam_results.exam_id', '=', 'exam_packages.exam_id')
                        ->leftJoin('packages', 'exam_packages.package_id', '=', 'packages.id')
                        ->where(function ($categoryQuery) use ($categoryId) {
                            $categoryQuery->where('exams.category_level_1', $categoryId)
                                ->orWhere('packages.category_level_1', $categoryId);
                        }),
                    $studentId,
                    $tenantId
                ));
            }

            if ($subcategoryId && $subcategoryTitle) {
                $boards->push($this->leaderboardBoard(
                    $subcategoryTitle . ' Rank',
                    'Subcategory leaderboard',
                    'Students in this subcategory',
                    fn ($query) => $query->leftJoin('exam_packages', 'exam_results.exam_id', '=', 'exam_packages.exam_id')
                        ->leftJoin('packages', 'exam_packages.package_id', '=', 'packages.id')
                        ->where(function ($subcategoryQuery) use ($subcategoryId) {
                            $subcategoryQuery->where('exams.category_level_2', $subcategoryId)
                                ->orWhere('packages.category_level_2', $subcategoryId);
                        }),
                    $studentId,
                    $tenantId
                ));
            }

            if ($package) {
                $boards->push($this->leaderboardBoard(
                    $this->plainName($package->name) . ' Rank',
                    'Package leaderboard',
                    'Students in this package',
                    fn ($query) => $query->join('exam_packages', 'exam_results.exam_id', '=', 'exam_packages.exam_id')
                        ->where('exam_packages.package_id', $package->id),
                    $studentId,
                    $tenantId
                ));
            }

            $boards->push($this->leaderboardBoard(
                $exam->name . ' Rank',
                'Exam leaderboard',
                'Students in this exam',
                fn ($query) => $query->where('exam_results.exam_id', $exam->id),
                $studentId,
                $tenantId
            ));

            if ($subjectBoard = $this->subjectLeaderboardBoard($latestResult, $studentId, $tenantId)) {
                $boards->push($subjectBoard);
            }
        }

        return view('students.leaderboard', [
            'boards' => $boards->filter()->values(),
            'latestResult' => $latestResult,
        ]);
    }

    private function dashboardGroupRank(ExamResult $latestResult, $student, ?int $tenantId, int $studentId): ?int
    {
        $latestResult->loadMissing('exam.groups:id,group_name');
        $group = $latestResult->exam?->groups?->first() ?: $student->groups->first();

        if (! $group) {
            return null;
        }

        $period = request('leaderboard_period', 'year');
        $cacheKey = 'student.dashboard.group-rank.'.($tenantId ?: 'platform').'.'.$group->id.'.'.$studentId.'.'.$latestResult->id.'.'.$period;

        return Cache::remember($cacheKey, now()->addSeconds(60), function () use ($group, $studentId, $tenantId, $period) {
            $rankedStudents = DB::table('exam_results')
            ->join('students', 'exam_results.student_id', '=', 'students.id')
            ->join('exams', 'exam_results.exam_id', '=', 'exams.id')
            ->join('exam_groups', 'exam_results.exam_id', '=', 'exam_groups.exam_id')
            ->where('exam_groups.group_id', $group->id)
            ->whereNotNull('exam_results.student_id')
            ->whereNotNull('exam_results.end_time')
            ->whereNotNull('exam_results.percent')
            ->where(function ($query) {
                $query->whereNull('exams.is_student_practice')
                    ->orWhere('exams.is_student_practice', false);
            })
            ->when($period !== 'all', fn ($query) => $query->whereYear('exam_results.end_time', now()->year))
            ->when($tenantId, function ($query) use ($tenantId) {
                $query->where('exam_results.organization_id', $tenantId)
                    ->where('exams.organization_id', $tenantId);
            })
            ->select('students.id as student_id')
            ->selectRaw('MAX(exam_results.percent) as score_percent')
            ->selectRaw('MIN(COALESCE(exam_results.total_test_time, 999999)) as best_time')
            ->selectRaw('MAX(exam_results.end_time) as last_attempt')
            ->groupBy('students.id');

        $current = DB::query()
            ->fromSub(clone $rankedStudents, 'ranked_students')
            ->where('student_id', $studentId)
            ->first();

        if (! $current) {
            return null;
        }

        $studentsAhead = DB::query()
            ->fromSub($rankedStudents, 'ranked_students')
            ->where(function ($query) use ($current) {
                $query->where('score_percent', '>', $current->score_percent)
                    ->orWhere(function ($sameScore) use ($current) {
                        $sameScore->where('score_percent', $current->score_percent)
                            ->where('best_time', '<', $current->best_time);
                    })
                    ->orWhere(function ($sameScoreAndTime) use ($current) {
                        $sameScoreAndTime->where('score_percent', $current->score_percent)
                            ->where('best_time', $current->best_time)
                            ->where('last_attempt', '>', $current->last_attempt);
                    });
            })
            ->count();

            return $studentsAhead + 1;
        });
    }

    private function visibleLeaderboardEntries(Collection $rows, int $studentId, int $currentIndex): Collection
    {
        $visibleRows = $currentIndex >= 10
            ? $rows->take(9)
            : $rows->take(10);

        if ($currentIndex >= 10) {
            $visibleRows->put($currentIndex, $rows->get($currentIndex));
        }

        return $visibleRows
            ->map(function ($row, int $actualIndex) use ($studentId) {
                return [
                    'rank' => $actualIndex + 1,
                    'student_id' => (int) $row->student_id,
                    'name' => $row->student_name,
                    'photo' => $row->photo,
                    'score' => round((float) $row->score_percent, 1),
                    'time' => (int) $row->best_time,
                    'attempts' => (int) $row->attempts,
                    'is_current' => (int) $row->student_id === $studentId,
                ];
            })
            ->values();
    }

    private function leaderboardBoard(string $label, string $title, string $subtitle, callable $scope, int $studentId, ?int $tenantId): ?array
    {
        $query = DB::table('exam_results')
            ->join('students', 'exam_results.student_id', '=', 'students.id')
            ->join('exams', 'exam_results.exam_id', '=', 'exams.id')
            ->whereNotNull('exam_results.student_id')
            ->whereNotNull('exam_results.end_time')
            ->whereNotNull('exam_results.percent')
            ->where(function ($query) {
                $query->whereNull('exams.is_student_practice')
                    ->orWhere('exams.is_student_practice', false);
            })
            ->when(request('leaderboard_period', 'year') !== 'all', fn ($query) => $query->whereYear('exam_results.end_time', now()->year))
            ->when($tenantId, function ($query) use ($tenantId) {
                $query->where('exam_results.organization_id', $tenantId)
                    ->where('exams.organization_id', $tenantId);
            });

        $scope($query);

        $rows = $query
            ->select(
                'students.id as student_id',
                'students.name as student_name',
                'students.photo',
                DB::raw('MAX(exam_results.percent) as score_percent'),
                DB::raw('MIN(COALESCE(exam_results.total_test_time, 999999)) as best_time'),
                DB::raw('COUNT(DISTINCT exam_results.id) as attempts'),
                DB::raw('MAX(exam_results.end_time) as last_attempt')
            )
            ->groupBy('students.id', 'students.name', 'students.photo')
            ->orderByDesc('score_percent')
            ->orderBy('best_time')
            ->orderByDesc('last_attempt')
            ->get()
            ->values();

        if ($rows->isEmpty()) {
            return null;
        }

        $currentIndex = $rows->search(fn ($row) => (int) $row->student_id === $studentId);

        if ($currentIndex === false) {
            return null;
        }

        $totalStudents = $rows->count();
        $studentRank = $currentIndex + 1;
        $percentile = $totalStudents > 1
            ? round((($totalStudents - $studentRank) / ($totalStudents - 1)) * 100, 1)
            : 100;

        return [
            'label' => $label,
            'title' => $title,
            'subtitle' => $subtitle,
            'rank' => $studentRank,
            'percentile' => $percentile,
            'total_students' => $totalStudents,
            'top' => $this->visibleLeaderboardEntries($rows, $studentId, $currentIndex),
        ];
    }

    private function subjectLeaderboardBoard(ExamResult $latestResult, int $studentId, ?int $tenantId): ?array
    {
        $subject = DB::table('exam_stats')
            ->join('questions', 'exam_stats.question_id', '=', 'questions.id')
            ->join('subjects', 'questions.subject_id', '=', 'subjects.id')
            ->where('exam_stats.exam_result_id', $latestResult->id)
            ->whereNotNull('questions.subject_id')
            ->select('subjects.id', 'subjects.subject_name', DB::raw('COUNT(*) as total_questions'))
            ->groupBy('subjects.id', 'subjects.subject_name')
            ->orderByDesc('total_questions')
            ->first();

        if (! $subject) {
            return null;
        }

        $query = DB::table('exam_results')
            ->join('students', 'exam_results.student_id', '=', 'students.id')
            ->join('exams', 'exam_results.exam_id', '=', 'exams.id')
            ->join('exam_stats', 'exam_results.id', '=', 'exam_stats.exam_result_id')
            ->join('questions', 'exam_stats.question_id', '=', 'questions.id')
            ->where('questions.subject_id', $subject->id)
            ->whereNotNull('exam_results.student_id')
            ->whereNotNull('exam_results.end_time')
            ->where(function ($query) {
                $query->whereNull('exams.is_student_practice')
                    ->orWhere('exams.is_student_practice', false);
            })
            ->when(request('leaderboard_period', 'year') !== 'all', fn ($query) => $query->whereYear('exam_results.end_time', now()->year))
            ->when($tenantId, function ($query) use ($tenantId) {
                $query->where('exam_results.organization_id', $tenantId)
                    ->where('exams.organization_id', $tenantId);
            });

        $rows = $query
            ->select(
                'students.id as student_id',
                'students.name as student_name',
                'students.photo',
                DB::raw('ROUND((SUM(CASE WHEN exam_stats.ques_status = "R" THEN 1 ELSE 0 END) / COUNT(*)) * 100, 2) as score_percent'),
                DB::raw('SUM(COALESCE(exam_stats.time_taken, 0)) as best_time'),
                DB::raw('COUNT(DISTINCT exam_results.id) as attempts'),
                DB::raw('MAX(exam_results.end_time) as last_attempt')
            )
            ->groupBy('students.id', 'students.name', 'students.photo')
            ->orderByDesc('score_percent')
            ->orderBy('best_time')
            ->orderByDesc('last_attempt')
            ->get()
            ->values();

        if ($rows->isEmpty()) {
            return null;
        }

        $currentIndex = $rows->search(fn ($row) => (int) $row->student_id === $studentId);

        if ($currentIndex === false) {
            return null;
        }

        $totalStudents = $rows->count();
        $studentRank = $currentIndex + 1;
        $percentile = $totalStudents > 1
            ? round((($totalStudents - $studentRank) / ($totalStudents - 1)) * 100, 1)
            : 100;

        return [
            'label' => $subject->subject_name . ' Rank',
            'title' => 'Subject leaderboard',
            'subtitle' => 'Students in this subject',
            'rank' => $studentRank,
            'percentile' => $percentile,
            'total_students' => $totalStudents,
            'top' => $this->visibleLeaderboardEntries($rows, $studentId, $currentIndex),
        ];
    }

    private function plainName($value): string
    {
        if (is_array($value)) {
            return (string) ($value[app()->getLocale()] ?? $value['en'] ?? collect($value)->first() ?? 'Package');
        }

        if (is_string($value) && Str::startsWith($value, '{')) {
            $decoded = json_decode($value, true);

            if (is_array($decoded)) {
                return (string) ($decoded[app()->getLocale()] ?? $decoded['en'] ?? collect($decoded)->first() ?? 'Package');
            }
        }

        return (string) ($value ?: 'Package');
    }

    public function showResults(Request $request)
    {
        $studentId = Auth::guard('student')->id();
        $tenantId = $this->tenantId();
        
        $resultsQuery = $this->scopeOfficialExamResults(ExamResult::where('student_id', $studentId))
            ->when($tenantId, fn ($query) => $query->where('organization_id', $tenantId))
            ->whereNotNull('end_time')
            ->with('exam:id,name,result_after_finish,passing_percentage'); 

        $allResultsForStats = $resultsQuery->get();

        $stats = [
            'total_attempts' => $allResultsForStats->count(),
            'rank' => null,
            'percentile' => null,
            'highest_score' => $allResultsForStats->max('percent') ?? 0,
        ];

        $latestResultForStats = $allResultsForStats->sortByDesc('end_time')->first();

        if ($latestResultForStats) {
            $latestComparison = $this->comparisonDataForResult($latestResultForStats, $tenantId);
            $stats['rank'] = $latestComparison['rank'] ?? null;
            $stats['percentile'] = $latestComparison['percentile'] ?? null;
        }

        $results = $resultsQuery->latest()->get()->map(function ($result) use ($tenantId) {
            $comparison = $this->comparisonDataForResult($result, $tenantId);
            $result->display_rank = $comparison['rank'] ?? null;
            $result->display_percentile = $comparison['percentile'] ?? null;

            return $result;
        });

        return view('students.results', compact('results', 'stats'));
    }

    // ==========================================
    // ✅ UPDATED VIEW RESULT (PREMIUM ANALYTICS)
    // ==========================================
    public function viewResult($id)
    {
        $studentId = Auth::guard('student')->id();
        $tenantId = $this->tenantId();

        // 1. Fetch Result
        $result = ExamResult::with(['exam', 'student'])
            ->where('student_id', $studentId)
            ->when($tenantId, fn ($query) => $query->where('organization_id', $tenantId))
            ->findOrFail($id);

        if (! $result->exam) {
            return redirect()->route('student.results')->with('error', 'This exam is no longer available.');
        }

        if ($result->exam->result_after_finish == 0) {
            return redirect()->route('student.results')->with('error', 'Result is currently hidden by Admin.');
        }

        StudentActivityTracker::track(StudentActivityTracker::RESULT_VIEWED, [
            'organization_id' => $result->organization_id,
            'student_id' => $studentId,
            'exam_id' => $result->exam_id,
            'exam_result_id' => $result->id,
            'metadata' => [
                'percent' => $result->percent,
                'result' => $result->result,
            ],
        ]);

        // 2. Report Generation
        $resultDetail = ExamResultDetail::where('exam_result_id', $id)->first();
        if (!$resultDetail) {
            if (class_exists(ResultHelper::class)) {
                $resultDetail = ResultHelper::computeResultDetails($result, $id);
            }
        }

        // Always render solutions from live answer rows. Cached report JSON can be empty or
        // stale for older attempts, which previously left the entire solutions panel blank.
        $questionReports = ExamStat::query()
            ->where('exam_result_id', $id)
            ->whereHas('question')
            ->with(['question.diff', 'question.qtype'])
            ->orderByRaw('CASE WHEN ques_no IS NULL THEN 1 ELSE 0 END')
            ->orderBy('ques_no')
            ->orderBy('id')
            ->get();

        foreach ($questionReports as $report) {
            $report->time_taken = (int) ($report->time_taken ?? 0);
        }

        $resultTranslations = collect();
        if ($result->language_id) {
            $resultTranslations = \App\Models\QuestionLang::where('language_id', $result->language_id)
                ->whereIn('question_id', $questionReports->pluck('question_id')->filter())
                ->get()
                ->keyBy('question_id');
        }

        $groupedQuestions = $questionReports->groupBy(
            fn ($report) => $report->subject_id ? (string) $report->subject_id : 'general'
        );
        $subjectIds = $groupedQuestions->keys()->filter(fn ($key) => ctype_digit((string) $key));
        $subjectNames = $this->scopeSubjectTenant(Subject::whereIn('id', $subjectIds), $tenantId)
            ->pluck('subject_name', 'id');

        foreach ($groupedQuestions->keys() as $subjectKey) {
            if ($subjectKey === 'general') {
                $subjectNames->put('general', 'General Questions');
            } elseif (! $subjectNames->has((int) $subjectKey)) {
                $subjectNames->put((int) $subjectKey, 'Subject #'.$subjectKey);
            }
        }
        $topicsMap = $this->scopeTopicTenant(Topic::query(), $tenantId)
            ->pluck('name', 'id'); // For View Mapping

        $formattedTotalTestTime = $resultDetail ? ($resultDetail->formatted_total_test_time ?? 'N/A') : gmdate("H:i:s", $result->total_test_time);
        $formattedTestTime = $resultDetail ? ($resultDetail->formatted_test_time ?? 'N/A') : gmdate("H:i:s", $result->test_time);
        
        $toleranceCount = $result->tolerance_count ?? 0;
        $exam = $result->exam;
        $isPracticeResult = (bool) ($exam->is_student_practice ?? false);
        $examFinishedByTolerance = ($exam && $exam->browser_tolerance && $toleranceCount >= $exam->tolerance_count);

        $proctorImages = ExamProctorImage::where('exam_id', $result->exam_id)
            ->where('student_id', $studentId)
            ->when($tenantId, fn ($query) => $query->where('organization_id', $tenantId))
            ->get();

        $comparisonData = [
            'student_score' => $result->percent,
            'topper_score' => null,
            'avg_score' => null,
            'student_time' => round(($result->total_test_time ?? 0) / 60, 1),
            'topper_time' => null,
            'percentile' => null,
            'total_students' => null,
            'rank' => null,
        ];

        if (! $isPracticeResult) {
            // 3. Topper & Percentile Logic
            $comparisonQuery = ExamResult::where('exam_id', $result->exam_id)
                ->when($tenantId, fn ($query) => $query->where('organization_id', $tenantId))
                ->whereNotNull('end_time')
                ->whereNotNull('percent');

            $topperResult = (clone $comparisonQuery)
                ->orderBy('percent', 'desc')
                ->orderBy('total_test_time', 'asc')
                ->first();

            $totalCandidates = (clone $comparisonQuery)->count();
            $rank = (clone $comparisonQuery)
                ->where('percent', '>', $result->percent)
                ->count() + 1;

            $percentile = $totalCandidates > 1 ? round((($totalCandidates - $rank) / ($totalCandidates - 1)) * 100, 2) : 100;

            $comparisonData = array_merge($comparisonData, [
                'topper_score' => $topperResult ? $topperResult->percent : $result->percent,
                'avg_score' => (clone $comparisonQuery)->avg('percent') ?? 0,
                'topper_time' => round(($topperResult->total_test_time ?? 0) / 60, 1),
                'percentile' => $percentile,
                'total_students' => $totalCandidates,
                'rank' => $rank,
            ]);
        }

        // 4. Topic Analysis
        $topicAnalysis = DB::table('exam_stats')
            ->join('questions', 'exam_stats.question_id', '=', 'questions.id')
            ->leftJoin('topics', 'questions.topic_id', '=', 'topics.id')
            ->where('exam_stats.exam_result_id', $id)
            ->whereNotNull('topics.name')
            ->select(
                'topics.name',
                DB::raw('COUNT(*) as total_q'),
                DB::raw('SUM(CASE WHEN exam_stats.ques_status = "R" THEN 1 ELSE 0 END) as correct_q')
            )
            ->groupBy('topics.id', 'topics.name')
            ->get()
            ->map(function($topic) {
                $topic->accuracy = $topic->total_q > 0 ? round(($topic->correct_q / $topic->total_q) * 100, 1) : 0;
                if ($topic->accuracy >= 80) $topic->status = 'Strong';
                elseif ($topic->accuracy >= 50) $topic->status = 'Average';
                else $topic->status = 'Weak';
                return $topic;
            });

        // 5. Time Matrix Logic
        $globalAvgTimePerQues = DB::table('exam_stats')
            ->where('exam_id', $result->exam_id)
            ->when($tenantId, fn ($query) => $query->where('organization_id', $tenantId))
            ->where('answered', 1)
            ->avg('time_taken') ?? 60;

        $timeAnalysis = ['wasted_time' => 0, 'careless_mistake' => 0, 'rapid_fire' => 0, 'struggle_win' => 0];
        
        foreach ($questionReports as $report) {
            if(!$report->answered) continue;
            $time = $report->time_taken;
            $isCorrect = ($report->ques_status == 'R');
            $fastThreshold = $globalAvgTimePerQues * 0.5;
            
            if ($isCorrect) {
                if ($time < $globalAvgTimePerQues) $timeAnalysis['rapid_fire']++;
                else $timeAnalysis['struggle_win']++;
            } else {
                if ($time < $fastThreshold) $timeAnalysis['careless_mistake']++;
                else $timeAnalysis['wasted_time']++;
            }
        }

        // 6. Existing Subject Analysis
        $subjectAnalysis = [];
        if ($resultDetail && $resultDetail->subject_reports) {
            $rawSubjectReports = is_array($resultDetail->subject_reports)
                ? $resultDetail->subject_reports
                : json_decode($resultDetail->subject_reports, true);
            if (!empty($rawSubjectReports)) {
                $subjectIds = array_column($rawSubjectReports, 'subject_id');
                $subjectNamesMap = $this->scopeSubjectTenant(Subject::whereIn('id', $subjectIds), $tenantId)
                    ->pluck('subject_name', 'id');
                foreach ($rawSubjectReports as $report) {
                    $subId = $report['subject_id'];
                    $totalQ = $report['total_questions'];
                    $correctQ = $report['correct_questions'];
                    $accuracy = ($totalQ > 0) ? ($correctQ / $totalQ) * 100 : 0;
                    $subjectAnalysis[] = [
                        'name' => $subjectNamesMap[$subId] ?? 'Subject #' . $subId,
                        'total' => $totalQ,
                        'correct' => $correctQ,
                        'accuracy' => round($accuracy, 1),
                        'status' => $accuracy >= 75 ? 'Strong' : ($accuracy >= 40 ? 'Average' : 'Weak')
                    ];
                }
            }
        }

        // 7. Difficulty Stats
        $difficultyStats = ExamStat::where('exam_result_id', $id)
            ->join('questions', 'exam_stats.question_id', '=', 'questions.id')
            ->join('diffs', 'questions.diff_id', '=', 'diffs.id')
            ->select('diffs.diff_level', 
                DB::raw('COUNT(*) as total'), 
                DB::raw('SUM(CASE WHEN exam_stats.ques_status = "R" THEN 1 ELSE 0 END) as correct'),
                DB::raw('SUM(CASE WHEN exam_stats.ques_status = "W" THEN 1 ELSE 0 END) as wrong'),
                DB::raw('SUM(CASE WHEN exam_stats.answered = 0 THEN 1 ELSE 0 END) as skipped'))
            ->groupBy('diffs.diff_level')
            ->get();

        // 8. History
        $history = $this->scopeOfficialExamResults(ExamResult::where('student_id', $studentId))
            ->when($tenantId, fn ($query) => $query->where('organization_id', $tenantId))
            ->whereNotNull('percent')
            ->orderBy('created_at', 'desc')
            ->take(5)
            ->with('exam:id,name')
            ->get();

        $performanceInsight = app(StudentPerformanceInsightService::class)->build(
            $result,
            $comparisonData,
            $timeAnalysis,
            $subjectAnalysis,
            $topicAnalysis,
            $difficultyStats
        );

        return view('students.view_result', compact(
            'result', 'exam', 'questionReports', 'proctorImages',
            'formattedTotalTestTime', 'formattedTestTime', 'toleranceCount',
            'examFinishedByTolerance', 'comparisonData', 'timeAnalysis', 'globalAvgTimePerQues',
            'subjectAnalysis', 'topicAnalysis', 'difficultyStats', 'history',
            'resultDetail',
            'groupedQuestions', 'subjectNames', 'topicsMap', 'performanceInsight', 'isPracticeResult', 'resultTranslations'
        ));
    }

    public function showBookmarks()
    {
        $studentId = Auth::guard('student')->id();
        $tenantId = $this->tenantId();
        $bookmarkedQuestions = ExamStat::where('bookmark', true)
            ->whereHas('question')
            ->whereHas('examResult', function ($query) use ($studentId, $tenantId) {
                $query->where('student_id', $studentId)
                    ->when($tenantId, fn ($query) => $query->where('organization_id', $tenantId));
            })
            ->with(['examResult.exam'])
            ->get();

        $bookmarksByExam = $bookmarkedQuestions->filter(fn ($stat) => $stat->examResult && $stat->examResult->exam)
            ->groupBy('examResult.exam_id')
            ->map(function ($group) {
                $exam = $group->first()->examResult->exam;
                return [
                    'exam_id' => $exam->id,
                    'exam_name' => $exam->name,
                    'total_bookmarks' => $group->count(),
                    'questions' => $group,
                ];
        });

        return view('students.bookmarks', compact('bookmarksByExam'));
    }

    public function viewBookmarks($examId)
    {
        $studentId = Auth::guard('student')->id();
        $tenantId = $this->tenantId();
        $bookmarkedQuestions = ExamStat::where('bookmark', true)
            ->whereHas('question')
            ->whereHas('examResult', function ($query) use ($studentId, $examId, $tenantId) {
                $query->where('student_id', $studentId)
                    ->where('exam_id', $examId)
                    ->when($tenantId, fn ($query) => $query->where('organization_id', $tenantId));
            })
            ->with(['question', 'examResult.exam'])
            ->get();

        $firstBookmark = $bookmarkedQuestions->first();
        $examName = $firstBookmark?->examResult?->exam?->name ?? 'Question Review';

        return view('students.view_bookmarks', compact('bookmarkedQuestions', 'examName'));
    }

    public function bookmarkQuestion(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'question_id' => 'required|integer',
            'bookmark' => 'required|boolean',
            'exam_result_id' => 'nullable|integer',
            'exam_stat_id' => 'nullable|integer',
        ]);

        if ($validator->fails()) { return response()->json(['errors' => $validator->errors()], 422); }

        $studentId = Auth::guard('student')->id();
        $tenantId = $this->tenantId();
        $examStat = ExamStat::query()
            ->where('student_id', $studentId)
            ->when($tenantId, fn ($query) => $query->where('organization_id', $tenantId))
            ->when($request->filled('exam_stat_id'), fn ($query) => $query->where('id', $request->integer('exam_stat_id')))
            ->when(! $request->filled('exam_stat_id'), fn ($query) => $query
                ->where('question_id', $request->question_id)
                ->when($request->filled('exam_result_id'), fn ($query) => $query->where('exam_result_id', $request->integer('exam_result_id')))
            )
            ->firstOrFail();
            
        $examStat->bookmark = $request->bookmark;
        $examStat->save();

        return response()->json(['success' => true]);
    }
}

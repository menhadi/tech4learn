<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ExamStat;
use App\Models\Exam;
use App\Models\Group;
use App\Models\Package;
use App\Models\Student;
use App\Models\ExamFeedback;
use App\Models\ExamProctorImage;
use App\Models\ExamResultDetail;
use App\Models\ExamResult;
use App\Models\Subject;
use App\Models\Topic;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth; // ✅ Auth Import Added
use App\Helpers\ResultHelper;
use App\Services\StudentPerformanceInsightService;

class ResultController extends Controller
{
    private function tenantId(): ?int
    {
        return class_exists(\App\Support\Tenant::class) ? \App\Support\Tenant::id() : null;
    }

    private function tenantResultQuery()
    {
        $query = ExamResult::query();
        $tenantId = $this->tenantId();

        if ($tenantId) {
            $query->where('exam_results.organization_id', $tenantId);
        }

        return $query;
    }

    private function tenantExamStatQuery()
    {
        $query = ExamStat::query();
        $tenantId = $this->tenantId();

        if ($tenantId) {
            $query->where('exam_stats.organization_id', $tenantId);
        }

        return $query;
    }

    public function index(Request $request)
    {
        // --- 1. FILTER FIX: Handle Group ID Input ---
        $selectedGroupIds = $request->input('group_id');
        
        if (!is_array($selectedGroupIds)) {
            $selectedGroupIds = $selectedGroupIds ? [$selectedGroupIds] : [];
        }

        $selectedExamId = $request->input('exam_id');
        $selectedPackageId = $request->input('package_id');
        $selectedStatus = $request->input('status');
        $studentNameOrEnroll = $request->input('student');
        $perPage = (int) $request->input('per_page', 50);
        $perPage = in_array($perPage, [50, 100, 500], true) ? $perPage : 50;
        
        $hasFilters = $request->filled('exam_id')
            || $request->filled('package_id')
            || !empty($selectedGroupIds)
            || $request->filled('status')
            || $request->filled('student');
        $hasSearch = true;

        $groups = Group::query()
            ->when($this->tenantId(), function ($q, $tenantId) {
                $q->where('organization_id', $tenantId);
            })
            ->get();
        $packages = Package::query()
            ->when($this->tenantId(), function ($q, $tenantId) {
                $q->where('organization_id', $tenantId);
            })
            ->when(!empty($selectedGroupIds), function ($q) use ($selectedGroupIds) {
                $q->whereHas('groups', function ($groupQuery) use ($selectedGroupIds) {
                    $groupQuery->whereIn('groups.id', $selectedGroupIds);
                });
            })
            ->orderBy('name')
            ->get();

        $exams = Exam::query()
            ->when($this->tenantId(), function ($q, $tenantId) {
                $q->where('organization_id', $tenantId);
            })
            ->when(!empty($selectedGroupIds), function ($q) use ($selectedGroupIds) {
                $q->whereHas('groups', function ($groupQuery) use ($selectedGroupIds) {
                    $groupQuery->whereIn('groups.id', $selectedGroupIds);
                });
            })
            ->when($selectedPackageId, function ($q) use ($selectedPackageId) {
                $q->whereHas('packages', function ($packageQuery) use ($selectedPackageId) {
                    $packageQuery->where('packages.id', $selectedPackageId);
                });
            })
            ->orderBy('name')
            ->get();

        $query = $this->tenantResultQuery()->with(['exam', 'student'])->withCount('feedback');

        // --- 🔒 SECURITY LOGIC START ---
        $currentUser = Auth::user();

        // Check 1: Agar user exist karta hai aur wo "Super Admin" (0) nahi hai
        if ($currentUser && $currentUser->ugroup_id != 0) {
            
            // Helper se current user ke groups nikalo
            $assignedGroupIds = getUserGroupIds();

            // Check 2: Agar user ke paas groups hain, to filter karo
            if (!empty($assignedGroupIds)) {
                // Hum check kar rahe hain ki Result jis Exam ka hai, wo Exam user ke Group ka hai ya nahi
                $query->whereHas('exam.groups', function ($q) use ($assignedGroupIds) {
                    // ✅ FIX: 'groups.id' use kiya taaki ambiguity na ho
                    $q->whereIn('groups.id', $assignedGroupIds);
                });
            } else {
                // 🛑 FAIL-SAFE: Agar staff hai par group ID nahi mili, kuch mat dikhao
                $query->whereRaw('1 = 0');
            }
        }
        // --- 🔒 SECURITY LOGIC END ---

        // Filter: Groups (Dropdown Selection)
        if (!empty($selectedGroupIds)) {
            $query->whereHas('exam.groups', function ($q) use ($selectedGroupIds) {
                $q->whereIn('groups.id', $selectedGroupIds);
            });
        }

        // Filter: Exam & Others
        if ($selectedPackageId) {
            $query->whereHas('exam.packages', function ($q) use ($selectedPackageId) {
                $q->where('packages.id', $selectedPackageId);
            });
        }
        if ($selectedExamId) { $query->where('exam_id', $selectedExamId); }
        if ($selectedStatus) { $query->where('result', $selectedStatus); }
        if ($studentNameOrEnroll) {
            $query->whereHas('student', function ($q) use ($studentNameOrEnroll) {
                $q->where('name', 'like', '%' . $studentNameOrEnroll . '%')
                  ->orWhere('enroll', 'like', '%' . $studentNameOrEnroll . '%');
            });
        }
        
        // Stats Variables
        $stats = null; $topStudents = collect(); $weakStudents = collect(); $toughestExams = collect(); $results = collect();
        $statusCounts = collect(); $scoreBands = collect();

        // NOTE: Stats calculation ke liye bhi hum same secured query use karenge
        if ($hasSearch) {
            // Agar staff hai to bina search ke bhi data load karte waqt filter hona chahiye
            
            $queryForStats = clone $query;
            $allFilteredResults = $queryForStats->get();

            if ($allFilteredResults->isNotEmpty()) {
                $totalResults = $allFilteredResults->count();
                $totalPassed = $allFilteredResults->where('result', 'Pass')->count();
                $statusCounts = $allFilteredResults->groupBy('result')->map->count();
                $scoreBands = collect([
                    '0-40' => $allFilteredResults->filter(fn ($result) => (float) $result->percent < 40)->count(),
                    '40-60' => $allFilteredResults->filter(fn ($result) => (float) $result->percent >= 40 && (float) $result->percent < 60)->count(),
                    '60-80' => $allFilteredResults->filter(fn ($result) => (float) $result->percent >= 60 && (float) $result->percent < 80)->count(),
                    '80-100' => $allFilteredResults->filter(fn ($result) => (float) $result->percent >= 80)->count(),
                ]);
                $stats = [
                    'total_results' => $totalResults,
                    'average_score' => $allFilteredResults->avg('percent'),
                    'pass_rate' => $totalResults > 0 ? ($totalPassed / $totalResults) * 100 : 0,
                    'highest_score' => $allFilteredResults->max('percent'),
                    'lowest_score' => $allFilteredResults->min('percent'),
                ];
                $topStudents = $allFilteredResults->sortBy([['percent', 'desc'],['total_test_time', 'asc']])->take(5);
                $weakStudents = $allFilteredResults->sortBy('percent')->take(5);
            }

            // --- 2. RANK LOGIC: Agar Exam select hai to Rank wise sort karo ---
            if ($selectedExamId) {
                $query->orderBy('percent', 'desc')->orderBy('total_test_time', 'asc');
            } else {
                $query->latest();
            }
            $results = $query->paginate($perPage);

            // Toughest Exam Logic (Sirf tab jab "All Exams" ho)
            if (!$selectedExamId) {
                $toughestExamQuery = $this->tenantResultQuery()->with('exam:id,name');
                
                // 🔒 Apply Security Filter to Toughest Exam Query too
                if ($currentUser && $currentUser->ugroup_id != 0) {
                    $assignedGroupIds = getUserGroupIds();
                    if (!empty($assignedGroupIds)) {
                        $toughestExamQuery->whereHas('exam.groups', function ($q) use ($assignedGroupIds) {
                            $q->whereIn('groups.id', $assignedGroupIds);
                        });
                    }
                }

                if (!empty($selectedGroupIds)) {
                    $toughestExamQuery->whereHas('exam.groups', function ($q) use ($selectedGroupIds) {
                        $q->whereIn('groups.id', $selectedGroupIds);
                    });
                }
                $toughestExams = $toughestExamQuery->select('exam_id', DB::raw('AVG(percent) as average_score'), DB::raw('COUNT(*) as total_takers'))
                    ->groupBy('exam_id')->having('total_takers', '>=', 5)->orderBy('average_score', 'asc')->take(5)->get();
            }
        }

        return view('results.index', compact('results', 'groups', 'packages', 'exams', 'selectedGroupIds', 'selectedPackageId', 'selectedExamId', 'selectedStatus', 'studentNameOrEnroll', 'hasSearch', 'hasFilters', 'perPage', 'stats', 'topStudents', 'weakStudents', 'toughestExams', 'statusCounts', 'scoreBands'));
    }

    public function view($id)
    {
        // 🔒 Load with exam.groups to check permission
        $result = $this->tenantResultQuery()->with(['exam.groups', 'student'])->findOrFail($id);
        
        // --- 🔒 SECURITY CHECK ---
        $currentUser = Auth::user();
        if ($currentUser && $currentUser->ugroup_id != 0) {
            $assignedGroupIds = getUserGroupIds();
            $examGroups = $result->exam?->groups?->pluck('id')->toArray() ?? [];
            
            if (! empty($assignedGroupIds) && empty(array_intersect($assignedGroupIds, $examGroups))) {
                return redirect()->route('results.index')->with('error', 'Unauthorized access to this result.');
            }
        }
        // --- 🔒 END SECURITY CHECK ---

        $resultDetail = ExamResultDetail::where('exam_result_id', $id)
            ->when($this->tenantId(), function ($q, $tenantId) {
                $q->where('organization_id', $tenantId);
            })
            ->first();
        if (!$resultDetail && class_exists(\App\Helpers\ResultHelper::class)) {
            $resultDetail = \App\Helpers\ResultHelper::computeResultDetails($result, $id);
        }
        
        $questionReports = $resultDetail ? json_decode($resultDetail->question_reports ?? '[]', false) : [];
        
        // Data Collection
        $reportsCollection = collect($questionReports);
        $groupedQuestions = $reportsCollection->groupBy('subject_id');
        $subjectNames = Subject::whereIn('id', $groupedQuestions->keys())->pluck('subject_name', 'id');
        
        // Topic Map
        $topicsMap = Topic::pluck('name', 'id');

        $proctorImages = ExamProctorImage::where('exam_id', $result->exam_id)
            ->where('student_id', $result->student_id)
            ->when($this->tenantId(), function ($q, $tenantId) {
                $q->where('organization_id', $tenantId);
            })
            ->get();

        $formattedTotalTestTime = $resultDetail ? ($resultDetail->formatted_total_test_time ?? 'N/A') : gmdate("H:i:s", $result->total_test_time);
        $formattedTestTime = $resultDetail ? ($resultDetail->formatted_test_time ?? 'N/A') : gmdate("H:i:s", $result->test_time);
        
        // Comparison Logic
        $comparisonQuery = $this->tenantResultQuery()
            ->where('exam_id', $result->exam_id)
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

        $comparisonData = [
            'student_score' => $result->percent,
            'topper_score' => $topperResult ? $topperResult->percent : $result->percent,
            'avg_score' => (clone $comparisonQuery)->avg('percent') ?? 0,
            'percentile' => $percentile,
            'total_students' => $totalCandidates,
            'rank' => $rank
        ];

        // Topic Analysis
        $topicAnalysis = DB::table('exam_stats')
            ->when($this->tenantId(), function ($q, $tenantId) {
                $q->where('exam_stats.organization_id', $tenantId);
            })
            ->join('questions', 'exam_stats.question_id', '=', 'questions.id')
            ->leftJoin('topics', 'questions.topic_id', '=', 'topics.id')
            ->where('exam_stats.exam_result_id', $id)
            ->whereNotNull('topics.name')
            ->select(
                'topics.name',
                DB::raw('COUNT(*) as total_q'),
                DB::raw('SUM(CASE WHEN exam_stats.ques_status = "R" THEN 1 ELSE 0 END) as correct_q'),
                DB::raw('AVG(exam_stats.time_taken) as avg_time')
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

        // Time Analysis
        $globalAvgTimePerQues = DB::table('exam_stats')
            ->when($this->tenantId(), function ($q, $tenantId) {
                $q->where('organization_id', $tenantId);
            })
            ->where('exam_id', $result->exam_id)
            ->where('answered', 1)
            ->avg('time_taken') ?? 60;

        $timeAnalysis = [ 'wasted_time' => 0, 'careless_mistake' => 0, 'rapid_fire' => 0, 'struggle_win' => 0 ];

        foreach($reportsCollection as $stat) {
            if (!$stat->answered) continue;
            
            $time = $stat->time_taken;
            $isCorrect = $stat->ques_status == 'R';
            $fastThreshold = $globalAvgTimePerQues * 0.5;

            if ($isCorrect) {
                if ($time < $globalAvgTimePerQues) $timeAnalysis['rapid_fire']++;
                else $timeAnalysis['struggle_win']++;
            } else {
                if ($time < $fastThreshold) $timeAnalysis['careless_mistake']++;
                else $timeAnalysis['wasted_time']++;
            }
        }

        // Subject Analysis
        $subjectAnalysis = [];
        if ($resultDetail && $resultDetail->subject_reports) {
            $rawSubjectReports = json_decode($resultDetail->subject_reports, true);
            if (!empty($rawSubjectReports)) {
                $subjectIds = array_column($rawSubjectReports, 'subject_id');
                $subjectNamesMap = Subject::whereIn('id', $subjectIds)->pluck('subject_name', 'id');
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

        // Difficulty Stats
        $difficultyStats = $this->tenantExamStatQuery()
            ->where('exam_result_id', $id)
            ->join('questions', 'exam_stats.question_id', '=', 'questions.id')
            ->join('diffs', 'questions.diff_id', '=', 'diffs.id')
            ->select('diffs.diff_level', 
                DB::raw('COUNT(*) as total'), 
                DB::raw('SUM(CASE WHEN exam_stats.ques_status = "R" THEN 1 ELSE 0 END) as correct'),
                DB::raw('SUM(CASE WHEN exam_stats.ques_status = "W" THEN 1 ELSE 0 END) as wrong'),
                DB::raw('SUM(CASE WHEN exam_stats.answered = 0 THEN 1 ELSE 0 END) as skipped'))
            ->groupBy('diffs.diff_level')
            ->get();

        $performanceInsight = app(StudentPerformanceInsightService::class)->build(
            $result,
            $comparisonData,
            $timeAnalysis,
            $subjectAnalysis,
            $topicAnalysis,
            $difficultyStats
        );

        return view('results.view', compact(
            'result', 'questionReports', 'proctorImages',
            'formattedTotalTestTime', 'formattedTestTime',
            'comparisonData', 'timeAnalysis', 'globalAvgTimePerQues',
            'subjectAnalysis', 'topicAnalysis', 'difficultyStats', 
            'resultDetail', 'groupedQuestions', 'subjectNames', 'topicsMap', 'performanceInsight'
        ));
    }

    public function viewFeedback($id)
    {
        $examResult = $this->tenantResultQuery()->with(['exam.groups', 'student'])->findOrFail($id);

        // --- 🔒 SECURITY CHECK ---
        $currentUser = Auth::user();
        if ($currentUser && $currentUser->ugroup_id != 0) {
            $assignedGroupIds = getUserGroupIds();
            $examGroups = $examResult->exam?->groups?->pluck('id')->toArray() ?? [];
            
            if (! empty($assignedGroupIds) && empty(array_intersect($assignedGroupIds, $examGroups))) {
                return redirect()->route('results.index')->with('error', 'Unauthorized access.');
            }
        }
        // --- 🔒 END CHECK ---

        $feedbacks = ExamFeedback::where('exam_result_id', $id)
            ->when($this->tenantId(), function ($q, $tenantId) {
                $q->where('organization_id', $tenantId);
            })
            ->get();
        return view('results.feedback', compact('examResult', 'feedbacks'));
    }

    public function destroy($id)
    {
        $result = $this->tenantResultQuery()->with('exam.groups')->findOrFail($id);

        // --- 🔒 SECURITY CHECK ---
        $currentUser = Auth::user();
        if ($currentUser && $currentUser->ugroup_id != 0) {
            $assignedGroupIds = getUserGroupIds();
            $examGroups = $result->exam?->groups?->pluck('id')->toArray() ?? [];
            
            if (! empty($assignedGroupIds) && empty(array_intersect($assignedGroupIds, $examGroups))) {
                return redirect()->route('results.index')->with('error', 'Unauthorized deletion.');
            }
        }
        // --- 🔒 END CHECK ---

        $this->tenantExamStatQuery()->where('exam_result_id', $id)->delete();
        ExamResultDetail::where('exam_result_id', $id)
            ->when($this->tenantId(), function ($q, $tenantId) {
                $q->where('organization_id', $tenantId);
            })
            ->delete();
        ExamFeedback::where('exam_result_id', $id)
            ->when($this->tenantId(), function ($q, $tenantId) {
                $q->where('organization_id', $tenantId);
            })
            ->delete();
        $result->delete();
        return redirect()->route('results.index')->with('success', 'Result record deleted permanently.');
    }

    // ==========================================
    // ✅ NEW METHODS FOR MANUAL EVALUATION
    // ==========================================

    // 1. Show Evaluation Form (Pending Questions Only)
    public function evaluate($id)
    {
        $result = $this->tenantResultQuery()->with(['student', 'exam.groups'])->findOrFail($id);
        
        // --- 🔒 SECURITY CHECK ---
        $currentUser = Auth::user();
        if ($currentUser && $currentUser->ugroup_id != 0) {
            $assignedGroupIds = getUserGroupIds();
            $examGroups = $result->exam?->groups?->pluck('id')->toArray() ?? [];
            
            if (! empty($assignedGroupIds) && empty(array_intersect($assignedGroupIds, $examGroups))) {
                return redirect()->route('results.index')->with('error', 'Unauthorized access.');
            }
        }
        // --- 🔒 END CHECK ---

        // Fetch only Pending (Subjective) questions to show in form
        $questions = $this->tenantExamStatQuery()
            ->where('exam_result_id', $id)
            ->where('ques_status', 'P') 
            ->with('question')
            ->get();

        return view('results.evaluate', compact('result', 'questions'));
    }

    // 2. Save Marks & Fix Pending Status
    public function saveEvaluation(Request $request, $id)
    {
        $examResult = $this->tenantResultQuery()->with('exam.groups')->findOrFail($id);

        // --- 🔒 SECURITY CHECK ---
        $currentUser = Auth::user();
        if ($currentUser && $currentUser->ugroup_id != 0) {
            $assignedGroupIds = getUserGroupIds();
            $examGroups = $examResult->exam?->groups?->pluck('id')->toArray() ?? [];
            
            if (! empty($assignedGroupIds) && empty(array_intersect($assignedGroupIds, $examGroups))) {
                return redirect()->route('results.index')->with('error', 'Unauthorized action.');
            }
        }
        // --- 🔒 END CHECK ---

        return DB::transaction(function () use ($request, $id) {
        $examResult = $this->tenantResultQuery()->with('exam')->lockForUpdate()->findOrFail($id);
        abort_unless($examResult->end_time, 409, 'Submit the exam before marking.');
        $marksData = $request->validate(['marks'=>'required|array|min:1','marks.*'=>'required|numeric|min:0'])['marks'];
        $pending = $this->tenantExamStatQuery()->where('exam_result_id',$id)->where('ques_status','P')->lockForUpdate()->get()->keyBy('id');
        foreach ($marksData as $statId => $marks) {
            $stat = $pending->get($statId);
            if (!$stat || (float)$marks > (float)$stat->marks) {
                throw \Illuminate\Validation\ValidationException::withMessages(['marks'=>'Marks must belong to a pending answer and stay within its maximum.']);
            }
        }

        if (is_array($marksData)) {
            foreach ($marksData as $statId => $marks) {
                $stat = $this->tenantExamStatQuery()
                    ->where('exam_result_id', $id)
                    ->where('id', $statId)
                    ->first();
                if ($stat) {
                    $stat->update([
                        'marks_obtained' => $marks,
                        'ques_status' => ($marks > 0) ? 'R' : 'W', 
                    ]);
                }
            }
        }

        // ✅✅✅ CRITICAL FIX: Delete Old Report Cache ✅✅✅
        // Ye as-is rakha hai taaki recalculation sahi ho
        ExamResultDetail::where('exam_result_id', $id)
            ->when($this->tenantId(), function ($q, $tenantId) {
                $q->where('organization_id', $tenantId);
            })
            ->delete();
        // ✅✅✅ END FIX ✅✅✅

        // Re-calculate Result (Pass/Fail)
        $totalObtained = $this->tenantExamStatQuery()
            ->where('exam_result_id', $id)
            ->sum('marks_obtained');
        $exam = $examResult->exam;
        
        $passingMarks = $examResult->total_marks * ($exam->passing_percentage / 100);
        $hasPending = $this->tenantExamStatQuery()->where('exam_result_id',$id)->where('ques_status','P')->exists();
        $finalResult = $hasPending ? 'Pending' : ($totalObtained >= $passingMarks ? 'Pass' : 'Fail');
        $percent = $examResult->total_marks > 0 ? ($totalObtained / $examResult->total_marks) * 100 : 0;

        $examResult->update([
            'obtained_marks' => $totalObtained,
            'result' => $finalResult,
            'percent' => $percent
        ]);

        return redirect()->route('results.index')->with('success', 'Manual grading saved. Report updated successfully!');
        });
    }
}

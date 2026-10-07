<?php

namespace App\Http\Controllers\Students;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ExamResult;
use App\Models\ExamStat;
use App\Models\ExamResultDetail;
use App\Models\Subject;
use App\Helpers\ResultHelper; // Yeh zaroori hai
use Illuminate\Support\Facades\Validator;
use App\Models\Exam;
use Carbon\Carbon;

class ApiStudentDashboardController extends Controller
{
    /**
     * Mobile App ke Dashboard ke liye stats fetch karo
     */
    public function getDashboardStats(Request $request)
    {
        $student = $request->user();
        $studentId = $student->id;
        $tenantId = $student->organization_id;
        $studentGroups = $student->groups->pluck('id');

        $examResults = ExamResult::where('student_id', $studentId)
            ->when($tenantId, fn ($query) => $query->where('organization_id', $tenantId))
            ->whereNotNull('end_time')
            ->selectRaw('COUNT(*) as total_exams')
            ->selectRaw('SUM(CASE WHEN result = ? THEN 1 ELSE 0 END) as best_result_count', ['Pass'])
            ->selectRaw('AVG(obtained_marks) as avg_obtained_marks')
            ->selectRaw('AVG(percent) as avg_percentile')
            ->first();

        $failedExams = ExamResult::where('student_id', $studentId)
            ->when($tenantId, fn ($query) => $query->where('organization_id', $tenantId))
            ->where('result', 'Fail')
            ->with('exam:id,name')
            ->orderByDesc('start_time')
            ->limit(5)
            ->get();

        $upcomingExams = Exam::where('status', 'Active')
            ->when($tenantId, fn ($query) => $query->where('organization_id', $tenantId))
            ->whereHas('groups', function ($query) use ($studentGroups) {
                $query->whereIn('group_id', $studentGroups);
            })
            ->where('end_date', '>', Carbon::now())
            ->orderBy('start_date')
            ->limit(5)
            ->get(['id', 'name', 'status', 'start_date', 'end_date']);

        return response()->json([
            'success' => true,
            'data' => [
                'totalExams' => $examResults->total_exams,
                'bestResultCount' => $examResults->best_result_count,
                'avgObtainedMarks' => round($examResults->avg_obtained_marks ?? 0, 2),
                'avgPercentile' => round($examResults->avg_percentile ?? 0, 2),
                'failedExams' => $failedExams,
                'upcomingExams' => $upcomingExams,
            ],
        ]);
    }

    /**
     * Mobile App ke liye Exam Results ki list fetch karo
     */
    public function getResultsList(Request $request)
    {
        $student = $request->user();
        $studentId = $student->id;
        $tenantId = $student->organization_id;

        $baseResultsQuery = ExamResult::where('student_id', $studentId)
            ->when($tenantId, fn ($query) => $query->where('organization_id', $tenantId))
            ->whereNotNull('end_time');

        $statsRow = (clone $baseResultsQuery)
            ->selectRaw('COUNT(*) as total_attempts')
            ->selectRaw('SUM(CASE WHEN result = ? THEN 1 ELSE 0 END) as passed_count', ['Pass'])
            ->selectRaw('SUM(CASE WHEN result = ? THEN 1 ELSE 0 END) as failed_count', ['Fail'])
            ->selectRaw('MAX(percent) as highest_score')
            ->first();

        $stats = [
            'total_attempts' => (int) ($statsRow->total_attempts ?? 0),
            'passed_count' => (int) ($statsRow->passed_count ?? 0),
            'failed_count' => (int) ($statsRow->failed_count ?? 0),
            'highest_score' => (float) ($statsRow->highest_score ?? 0),
        ];

        $resultsQuery = (clone $baseResultsQuery)->with('exam:id,name');

        if ($request->status === 'passed') {
            $resultsQuery->where('result', 'Pass');
        } elseif ($request->status === 'failed') {
            $resultsQuery->where('result', 'Fail');
        }

        $results = $resultsQuery->latest()->paginate(20);

        return response()->json([
            'success' => true,
            'stats' => $stats,
            'results' => $results,
        ]);
    }

    /**
     * Mobile App ke liye detailed result view fetch karo
     */
    public function getResultDetail(Request $request, $id)
    {
        $student = $request->user();
        $studentId = $student->id;
        $tenantId = $student->organization_id;

        $result = ExamResult::where('id', $id)
            ->where('student_id', $studentId)
            ->when($tenantId, fn ($query) => $query->where('organization_id', $tenantId))
            ->with('exam')
            ->firstOrFail();
        
        // ResultHelper use karke details compute karo (Copied from viewResult())
        $resultDetail = ExamResultDetail::where('exam_result_id', $id)->first();
        if (!$resultDetail) {
            $resultDetail = ResultHelper::computeResultDetails($result, $id);
        }

        $subjectReports = collect(json_decode($resultDetail->subject_reports));
        $questionReports = ExamStat::where('exam_result_id', $id)->with(['question.diff'])->get();

        foreach ($questionReports as $report) {
            $report->formatted_time_taken = ResultHelper::formatTime($report->time_taken);
        }

        $subjects = Subject::whereIn('id', collect($subjectReports)->pluck('subject_id'))->get()->keyBy('id');
        $examFinishedByTolerance = false;
        if ($result->tolerance_count) {
            $examFinishedByTolerance = $result->tolerance_count === $result->exam->tolerance_count;
        }

        // JSON response
        return response()->json([
            'success' => true,
            'data' => [
                'result' => $result,
                'totalStudents' => $resultDetail->total_students,
                'correctQuestions' => $resultDetail->correct_questions,
                'rightMarks' => $resultDetail->right_marks,
                'leftQuestions' => $resultDetail->left_questions,
                'rank' => $resultDetail->rank,
                'incorrectQuestions' => $resultDetail->incorrect_questions,
                'negativeMarks' => $resultDetail->negative_marks,
                'leftQuestionMarks' => $resultDetail->left_question_marks,
                'formattedTotalTestTime' => $resultDetail->formatted_total_test_time,
                'formattedTestTime' => $resultDetail->formatted_test_time,
                'subjectReports' => $subjectReports,
                'subjects' => $subjects,
                'questionReports' => $questionReports,
                'toleranceCount' => $result->tolerance_count,
                'examFinishedByTolerance' => $examFinishedByTolerance,
            ]
        ]);
    }

    /**
     * Mobile App ke liye Bookmarks ki list fetch karo
     */
    public function getBookmarkList(Request $request)
    {
        $student = $request->user();
        $studentId = $student->id;
        $tenantId = $student->organization_id;
        $bookmarkedQuestions = ExamStat::where('bookmark', true)
            ->whereHas('examResult', function ($query) use ($studentId, $tenantId) {
                $query->where('student_id', $studentId)
                    ->when($tenantId, fn ($query) => $query->where('organization_id', $tenantId));
            })
            ->with(['examResult.exam', 'question']) // Question bhi saath mein bhej do
            ->get();

        $bookmarksByExam = $bookmarkedQuestions->groupBy('examResult.exam.name')->map(function ($group) {
            return [
                'total_bookmarks' => $group->count(),
                'questions' => $group
            ];
        });

        return response()->json([
            'success' => true,
            'bookmarksByExam' => $bookmarksByExam
        ]);
    }

    /**
     * Mobile App ke liye Bookmark toggle karo
     * (NOTE: Humne logic fix kiya hai - ab exam_result_id zaroori hai)
     */
    public function toggleBookmark(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'exam_result_id' => 'required|integer',
            'question_id' => 'required|integer',
            'bookmark' => 'required|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $student = $request->user();
        $studentId = $student->id;
        $tenantId = $student->organization_id;

        $examStat = ExamStat::where('exam_result_id', $request->exam_result_id)
            ->where('question_id', $request->question_id)
            ->where('student_id', $studentId)
            ->whereHas('examResult', function ($query) use ($studentId, $tenantId) {
                $query->where('student_id', $studentId)
                    ->when($tenantId, fn ($query) => $query->where('organization_id', $tenantId));
            })
            ->first();

        if (!$examStat) {
            return response()->json(['success' => false, 'message' => 'Exam stat record not found.'], 404);
        }

        $examStat->bookmark = $request->bookmark;
        $examStat->save();

        return response()->json(['success' => true, 'message' => 'Bookmark updated.']);
    }
}
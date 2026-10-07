<?php

namespace App\Http\Controllers\Students;

use App\Http\Controllers\Controller;
use App\Models\Exam;
use App\Models\ExamResult;
use App\Models\Order;
use App\Models\Package;
use App\Models\ExamStat;
use App\Models\StudentHiddenPackage;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;

class ApiMyExamController extends Controller
{
    /**
     * Mobile App ke liye 'My Exams' list, pending exams, aur stats fetch karo.
     */
    public function getMyExams(Request $request)
    {
        // 1. Authenticated student ko token se fetch karo
        $student = $request->user();
        $studentId = $student->id;
        $organizationId = (int) $student->organization_id;
        $studentGroups = $student->groups->pluck('id');

        // 2. Pending Exam (Website wala same logic)
        $pendingExam = ExamResult::where('student_id', $studentId)
            ->whereNull('end_time')
            ->with('exam')
            ->first();

        // 3. Performance Stats (Website wala same logic)
        $performanceStats = ExamResult::where('student_id', $studentId)
            ->whereNotNull('end_time')
            ->selectRaw('
                COUNT(*) as total_attempts,
                AVG(percent) as average_score,
                SUM(CASE WHEN result = "Pass" THEN 1 ELSE 0 END) as passed_exams
            ')
            ->first();

        $successRate = 0;
        if ($performanceStats && $performanceStats->total_attempts > 0) {
            $successRate = ($performanceStats->passed_exams / $performanceStats->total_attempts) * 100;
        }

        // 4. Purchased Exams (Website wala same logic)
        $purchasedPackageIds = Order::where('student_id', $studentId)
            ->where('status', 'completed')
            ->with('items.package')
            ->get()
            ->flatMap(function ($order) {
                return $order->items->pluck('package.id');
            })
            ->filter()
            ->unique();

        $hiddenPackageIds = Schema::hasTable('student_hidden_packages')
            ? StudentHiddenPackage::where('student_id', $studentId)->pluck('package_id')
            : collect();

        $purchasedPackages = \App\Models\Package::where('organization_id', $organizationId)
            ->whereIn('id', $purchasedPackageIds)
            ->whereNotIn('id', $hiddenPackageIds)
            ->with(['exams' => function ($query) use ($studentGroups) {
                $query->where('status', 'Active')
                      ->whereHas('groups', function ($q) use ($studentGroups) {
                          $q->whereIn('group_id', $studentGroups);
                      })
                      ->withSum('questions', 'marks')
                      ->latest();
            }])
            ->get();

        // 5. Attempt/Status logic for Purchased Exams (Website wala same logic)
        $allExamIds = $purchasedPackages->flatMap(function ($package) {
            return $package->exams->pluck('id');
        });

        $attemptCounts = ExamResult::whereIn('exam_id', $allExamIds)
            ->where('student_id', $studentId)
            ->whereNotNull('end_time')
            ->selectRaw('exam_id, COUNT(*) as attempt_count')
            ->groupBy('exam_id')
            ->pluck('attempt_count', 'exam_id');

        $latestResults = ExamResult::where('student_id', $studentId)
            ->whereIn('exam_id', $allExamIds)
            ->whereNotNull('end_time')
            ->selectRaw('exam_id, MAX(id) as latest_result_id')
            ->groupBy('exam_id')
            ->pluck('latest_result_id', 'exam_id');

        foreach ($purchasedPackages as $package) {
            foreach ($package->exams as $exam) {
                $attemptCount = $attemptCounts->get($exam->id, 0);
                $exam->attempts_left = ($exam->attempt_count == 0) ? 'Unlimited' : max(0, $exam->attempt_count - $attemptCount);
                
                $now = Carbon::now();
                $exam->exam_status = $now->between(Carbon::parse($exam->start_date), Carbon::parse($exam->end_date)) ? 'Live' : ($now->lt(Carbon::parse($exam->start_date)) ? 'Upcoming' : 'Expired');
                
                $exam->latest_result_id = $latestResults->get($exam->id);
            }
        }

        // 6. General Exams (Website wala same logic)
        $generalExams = Exam::where('organization_id', $organizationId)
            ->where('status', 'Active')
            ->whereDoesntHave('packages')
            ->whereHas('groups', function ($query) use ($studentGroups) {
                $query->whereIn('group_id', $studentGroups);
            })
            ->withSum('questions', 'marks')
            ->latest()
            ->get();

        // 7. Attempt/Status logic for General Exams (Website wala same logic)
        $generalExamIds = $generalExams->pluck('id');
        $generalAttemptCounts = ExamResult::whereIn('exam_id', $generalExamIds)->where('student_id', $studentId)->whereNotNull('end_time')->selectRaw('exam_id, COUNT(*) as attempt_count')->groupBy('exam_id')->pluck('attempt_count', 'exam_id');
        $generalLatestResults = ExamResult::where('student_id', $studentId)->whereIn('exam_id', $generalExamIds)->whereNotNull('end_time')->selectRaw('exam_id, MAX(id) as latest_result_id')->groupBy('exam_id')->pluck('latest_result_id', 'exam_id');

        foreach ($generalExams as $exam) {
            $attemptCount = $generalAttemptCounts->get($exam->id, 0);
            $exam->attempts_left = ($exam->attempt_count == 0) ? 'Unlimited' : max(0, $exam->attempt_count - $attemptCount);
            $now = Carbon::now();
            $exam->exam_status = $now->between(Carbon::parse($exam->start_date), Carbon::parse($exam->end_date)) ? 'Live' : ($now->lt(Carbon::parse($exam->start_date)) ? 'Upcoming' : 'Expired');
            $exam->latest_result_id = $generalLatestResults->get($exam->id);
        }

        // 8. FINAL STEP: View ke bajaye JSON return karo
        return response()->json([
            'success' => true,
            'data' => [
                'pendingExam' => $pendingExam,
                'performanceStats' => [
                    'total_attempts' => $performanceStats->total_attempts ?? 0,
                    'average_score' => round($performanceStats->average_score ?? 0, 2),
                    'passed_exams' => $performanceStats->passed_exams ?? 0,
                    'successRate' => round($successRate, 2),
                ],
                'purchasedPackages' => $purchasedPackages,
                'generalExams' => $generalExams,
            ]
        ]);
    }

    /**
     * Exam details fetch karo (App ke liye)
     * (Yeh function MyExamController se copy kiya hai)
     */
    public function getExamDetails(Request $request, $id)
    {
        $exam = $this->accessibleExamQuery($request)
            ->with(['questions.subject'])
            ->findOrFail($id);
        $totalMarks = $exam->questions->sum('marks');
        return response()->json([
            'exam' => $exam,
            'total_marks' => $totalMarks
        ]);
    }

    /**
     * Attempts check karo (App ke liye)
     * (Yeh function MyExamController se copy kiya hai, bas Auth logic badla hai)
     */
    public function checkAttempts(Request $request, $id)
    {
        $studentId = $request->user()->id; // <-- AUTH LOGIC CHANGE
        $exam = $this->accessibleExamQuery($request)->findOrFail($id);
        
        if ($exam->attempt_count == 0) {
            return response()->json(['attempts_left' => 'Unlimited']);
        }
        
        $attemptCount = ExamResult::where('exam_id', $id)
            ->where('student_id', $studentId)
            ->whereNotNull('end_time')
            ->count();
            
        return response()->json([
            'attempts_left' => max(0, $exam->attempt_count - $attemptCount)
        ]);
    }

    /**
     * Pending exam ko finalize karo (App ke liye)
     * (Yeh function MyExamController se copy kiya hai, bas Auth logic aur Response badla hai)
     */
    public function finalizePending(Request $request)
    {
        $studentId = $request->user()->id; // <-- AUTH LOGIC CHANGE
        
        $examResult = ExamResult::where('student_id', $studentId)
            ->whereNull('end_time')
            ->first(); // Fail hone ke bajaye null check karenge

        if (!$examResult) {
            return response()->json(['success' => false, 'message' => 'No pending exam found to finalize.'], 404);
        }

        $exam = Exam::where('organization_id', $request->user()->organization_id)
            ->find($examResult->exam_id);

        if (!$exam) {
            return response()->json(['success' => false, 'message' => 'Exam associated with the result not found.'], 404);
        }

        // --- Baaki saara calculation logic 100% SAME hai ---
        $startTime = Carbon::parse($examResult->start_time)->setTimezone(config('app.timezone'));
        $endTime = Carbon::now()->setTimezone(config('app.timezone'));
        $testTime = $startTime->diffInSeconds($endTime, false);

        if ($testTime < 0) $testTime = 0;

        $examStats = ExamStat::with(['question.qtype'])->where('exam_result_id', $examResult->id)->get();
        $obtainedMarks = 0;
        
        foreach($examStats as $stat) {
            $isCorrect = false;
            $marksObtained = 0;

            $marksToAll = $stat->question && app(\App\Services\QuestionAnswerEvaluator::class)->awardsMarksToAll($stat->question);

            if (($stat->answered || $marksToAll) && $stat->question) {
                $isCorrect = app(\App\Services\QuestionAnswerEvaluator::class)->isCorrect($stat->question, $stat);
            }

            if ($isCorrect) {
                $marksObtained = $stat->marks;
            } elseif ($stat->answered && ! $marksToAll && $stat->negative_marks) {
                $marksObtained = -1 * $stat->negative_marks;
            }

            if($stat->ques_status == null) {
                 $stat->update([
                    'ques_status' => $isCorrect ? 'R' : 'W',
                    'marks_obtained' => $marksObtained
                ]);
            }
            
            $obtainedMarks += $marksObtained;
        }

        $totalAnswered = $examStats->where('answered', true)->count();
        $passingMarks = $examResult->total_marks * ($exam->passing_percentage / 100);
        $result = $obtainedMarks >= $passingMarks ? 'Pass' : 'Fail';
        $percent = $examResult->total_marks > 0 ? ($obtainedMarks / $examResult->total_marks) * 100 : 0;

        $examResult->update([
            'end_time' => $endTime,
            'test_time' => $testTime,
            'obtained_marks' => $obtainedMarks,
            'result' => $result,
            'percent' => $percent,
            'finalized_time' => $endTime,
            'total_answered' => $totalAnswered,
        ]);
        
        // --- Logic End ---

        // Redirect ke bajaye JSON return karo
        return response()->json(['success' => true, 'message' => 'Your pending exam has been finalized.']);
    }

    private function accessibleExamQuery(Request $request)
    {
        $student = $request->user();
        $groupIds = $student->groups()->pluck('groups.id');
        $purchasedPackageIds = Order::query()
            ->where('organization_id', $student->organization_id)
            ->where('student_id', $student->id)
            ->where('status', 'completed')
            ->whereHas('items', fn ($query) => $query->whereNotNull('package_id'))
            ->with('items:id,order_id,package_id')
            ->get()
            ->flatMap(fn ($order) => $order->items->pluck('package_id'))
            ->filter()
            ->unique();

        return Exam::query()
            ->where('organization_id', $student->organization_id)
            ->whereHas('groups', fn ($query) => $query->whereIn('groups.id', $groupIds))
            ->where(function ($query) use ($purchasedPackageIds) {
                $query->whereDoesntHave('packages');
                if ($purchasedPackageIds->isNotEmpty()) {
                    $query->orWhereHas('packages', fn ($packageQuery) => $packageQuery->whereIn('packages.id', $purchasedPackageIds));
                }
            });
    }
}

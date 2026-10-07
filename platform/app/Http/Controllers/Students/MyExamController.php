<?php

namespace App\Http\Controllers\Students;

use App\Http\Controllers\Controller;
use App\Models\Exam;
use App\Models\OrderItem;
use App\Models\Package;
use App\Models\ExamResult;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use App\Models\Order; 
use App\Models\ExamStat;
use App\Models\Group;
use App\Models\StudentHiddenPackage;
use App\Helpers\ResultHelper; 
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use App\Services\StudentActivityTracker;
use App\Services\ExamDisplayOrder;
use App\Services\StudentFreePackageEnrollmentService;


class MyExamController extends Controller
{
    public function __construct(private StudentFreePackageEnrollmentService $freePackageEnrollmentService)
    {
    }

    private function tenantId(): ?int
    {
        return class_exists(\App\Support\Tenant::class) ? \App\Support\Tenant::hostId(request()->getHost()) : null;
    }

    private function tenantExamQuery()
    {
        return Exam::query()->when($this->tenantId(), function ($q, $tenantId) {
            $q->where('organization_id', $tenantId);
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

    private function ensureStudentCanAccessExam(Exam $exam): void
    {
        if ($exam->is_student_practice && (int) $exam->created_by_student_id !== (int) Auth::guard('student')->id()) {
            abort(404);
        }
    }

    public function index()
    {
        $studentId = Auth::guard('student')->id();
        StudentActivityTracker::track(StudentActivityTracker::MY_EXAMS_OPENED, [
            'organization_id' => $this->tenantId(),
            'student_id' => $studentId,
        ]);

        $studentGroups = Auth::guard('student')->user()->groups->pluck('id');
        $hasSelectedGroup = $studentGroups->isNotEmpty();
        
        // --- Pending Exam Logic ---
        $pendingExam = $this->scopeOfficialExamResults(ExamResult::where('student_id', $studentId))
            ->when($this->tenantId(), function ($query, $tenantId) {
                $query->where('organization_id', $tenantId);
            })
            ->whereNull('end_time')
            ->with('exam')
            ->first();

        // Performance Stats
        $performanceStats = $this->scopeOfficialExamResults(ExamResult::where('student_id', $studentId))
            ->when($this->tenantId(), function ($query, $tenantId) {
                $query->where('organization_id', $tenantId);
            })
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

        // --- Purchased Exams Logic ---
        $purchasedPackageIds = Order::where('student_id', $studentId)
            ->when($this->tenantId(), function ($query, $tenantId) {
                $query->where(function ($query) use ($tenantId) {
                    $query->where('organization_id', $tenantId)
                        ->orWhereNull('organization_id');
                });
            })
            ->where('status', 'completed')
            ->with('items.package')
            ->get()
            ->flatMap(function ($order) {
                return $order->items->pluck('package.id');
            })
            ->filter()
            ->unique();
        $hiddenPackageIds = Schema::hasTable('student_hidden_packages')
            ? StudentHiddenPackage::where('student_id', $studentId)
                ->when($this->tenantId(), fn ($query, $tenantId) => $query->where('organization_id', $tenantId))
                ->pluck('package_id')
                ->map(fn ($id) => (int) $id)
            : collect();

        // ✅ FIXED: Removed 'whereHas(groups)' constraint. 
        // Ab agar package khareeda hai, to uske andar ke Active exams dikhenge, chahe group koi bhi ho.
        $purchasedPackages = Package::whereIn('id', $purchasedPackageIds)
            ->whereNotIn('id', $hiddenPackageIds)
            ->when($this->tenantId(), function ($query, $tenantId) {
                $query->where('organization_id', $tenantId);
            })
            ->get();

        $hiddenPackages = Package::query()
            ->whereIn('id', $purchasedPackageIds)
            ->whereIn('id', $hiddenPackageIds)
            ->when($this->tenantId(), fn ($query, $tenantId) => $query->where('organization_id', $tenantId))
            ->withCount(['exams' => function ($query) {
                $query->whereIn('status', ['Active', 'active', 'ACTIVE', '1', 1, true, 'Published', 'published', 'Enabled', 'enabled']);
            }])
            ->orderBy('name')
            ->get();
            
        $this->loadInitialPackageExamBatches($purchasedPackages);


        $allExamIds = $purchasedPackages->flatMap(function ($package) {
            return $package->exams->pluck('id');
        });

        $attemptCounts = ExamResult::whereIn('exam_id', $allExamIds)
            ->where('student_id', $studentId)
            ->when($this->tenantId(), function ($query, $tenantId) {
                $query->where('organization_id', $tenantId);
            })
            ->whereNotNull('end_time')
            ->selectRaw('exam_id, COUNT(*) as attempt_count')
            ->groupBy('exam_id')
            ->pluck('attempt_count', 'exam_id');

        $latestResults = ExamResult::where('student_id', $studentId)
            ->whereIn('exam_id', $allExamIds)
            ->when($this->tenantId(), function ($query, $tenantId) {
                $query->where('organization_id', $tenantId);
            })
            ->whereNotNull('end_time')
            ->selectRaw('exam_id, MAX(id) as latest_result_id')
            ->groupBy('exam_id')
            ->pluck('latest_result_id', 'exam_id');
            
        foreach ($purchasedPackages as $package) {
            $this->hydrateStudentExamState($package->exams, $attemptCounts, $latestResults);
        }

        // --- General Exams Logic (Keep Groups check here for free exams) ---
        $generalExams = $this->tenantExamQuery()
            ->whereIn('status', ['Active', 'active', 'ACTIVE', '1', 1, true, 'Published', 'published', 'Enabled', 'enabled'])
            ->when(Schema::hasColumn('exams', 'is_student_practice'), fn ($query) => $query->where('is_student_practice', false))
            ->whereDoesntHave('packages')
            ->whereHas('groups', function ($query) use ($studentGroups) {
                $query->whereIn('group_id', $studentGroups);
            })
            ->withSum('questions', 'marks')
            ->latest()
            ->get();
        
        $generalExamIds = $generalExams->pluck('id');
        $generalAttemptCounts = ExamResult::whereIn('exam_id', $generalExamIds)
            ->where('student_id', $studentId)
            ->when($this->tenantId(), function ($query, $tenantId) {
                $query->where('organization_id', $tenantId);
            })
            ->whereNotNull('end_time')
            ->selectRaw('exam_id, COUNT(*) as attempt_count')
            ->groupBy('exam_id')
            ->pluck('attempt_count', 'exam_id');

        $generalLatestResults = ExamResult::where('student_id', $studentId)
            ->whereIn('exam_id', $generalExamIds)
            ->when($this->tenantId(), function ($query, $tenantId) {
                $query->where('organization_id', $tenantId);
            })
            ->whereNotNull('end_time')
            ->selectRaw('exam_id, MAX(id) as latest_result_id')
            ->groupBy('exam_id')
            ->pluck('latest_result_id', 'exam_id');

        foreach ($generalExams as $exam) {
            $attemptCount = $generalAttemptCounts->get($exam->id, 0);
            $exam->attempts_left = ($exam->attempt_count == 0) ? 'Unlimited' : max(0, $exam->attempt_count - $attemptCount);
            $now = Carbon::now();
            $exam->exam_status = $now->between(Carbon::parse($exam->start_date), Carbon::parse($exam->end_date)) ? 'Live' : ($now->lt(Carbon::parse($exam->start_date)) ? 'Upcoming' : 'Expired');
            $exam->latest_result_id = $generalLatestResults->get($exam->id);
        }

        $groups = Group::query()
            ->when($this->tenantId(), function ($q, $tenantId) {
                $q->where('organization_id', $tenantId);
            })
            ->withCount(['packages as free_packages_count' => function ($query) {
                $query->where('package_type', 'free')
                    ->where('packages.status', 1)
                    ->whereHas('exams', fn ($examQuery) => $examQuery->whereIn('exams.status', ['Active', 'active', 'ACTIVE', '1', 1, true, 'Published', 'published', 'Enabled', 'enabled']));
            }])
            ->whereHas('packages', function ($query) {
                $query->where('package_type', 'free')
                    ->where('packages.status', 1)
                    ->whereHas('exams', fn ($examQuery) => $examQuery->whereIn('exams.status', ['Active', 'active', 'ACTIVE', '1', 1, true, 'Published', 'published', 'Enabled', 'enabled']));
            })
            ->orderBy('group_name')
            ->get();
        
        return view('students.my_exams', compact(
            'purchasedPackages',
            'hiddenPackages',
            'generalExams',
            'performanceStats',
            'successRate',
            'pendingExam',
            'groups',
            'hasSelectedGroup',
        ));
    }
    
    public function packageExams(Request $request, Package $package)
    {
        $studentId = (int) Auth::guard('student')->id();
        $tenantId = $this->tenantId();

        abort_unless((int) $package->organization_id === (int) $tenantId, 404);

        $ownsPackage = OrderItem::query()
            ->where('package_id', $package->id)
            ->whereHas('order', function ($query) use ($studentId, $tenantId) {
                $query->where('student_id', $studentId)
                    ->where('status', 'completed')
                    ->when($tenantId, function ($query, $tenantId) {
                        $query->where(function ($query) use ($tenantId) {
                            $query->where('organization_id', $tenantId)
                                ->orWhereNull('organization_id');
                        });
                    });
            })
            ->exists();

        abort_unless($ownsPackage, 403);

        $offset = max(0, (int) $request->integer('offset', 0));
        $limit = 20;
        $orderedExamIds = $this->orderedPackageExamIds($package);
        $total = $orderedExamIds->count();
        $pageExams = $this->loadPackageExamBatch($orderedExamIds, $offset, $limit);

        $examIds = $pageExams->pluck('id');
        $attemptCounts = ExamResult::whereIn('exam_id', $examIds)
            ->where('student_id', $studentId)
            ->when($tenantId, fn ($query, $tenantId) => $query->where('organization_id', $tenantId))
            ->whereNotNull('end_time')
            ->selectRaw('exam_id, COUNT(*) as attempt_count')
            ->groupBy('exam_id')
            ->pluck('attempt_count', 'exam_id');
        $latestResults = ExamResult::whereIn('exam_id', $examIds)
            ->where('student_id', $studentId)
            ->when($tenantId, fn ($query, $tenantId) => $query->where('organization_id', $tenantId))
            ->whereNotNull('end_time')
            ->selectRaw('exam_id, MAX(id) as latest_result_id')
            ->groupBy('exam_id')
            ->pluck('latest_result_id', 'exam_id');

        $this->hydrateStudentExamState($pageExams, $attemptCounts, $latestResults);

        $html = $pageExams->map(function ($exam, $index) use ($package, $offset) {
            return view('students.partials.my_exam_row', [
                'exam' => $exam,
                'package' => $package,
                'examIndex' => $offset + $index + 1,
            ])->render();
        })->implode('');
        $nextOffset = $offset + $pageExams->count();

        return response()->json([
            'html' => $html,
            'next_offset' => $nextOffset,
            'shown' => $nextOffset,
            'total' => $total,
            'has_more' => $nextOffset < $total,
        ]);
    }

    private function loadInitialPackageExamBatches($packages): void
    {
        $tenantId = $this->tenantId();
        $packages->load(['exams' => function ($query) use ($tenantId) {
            $query->whereIn('status', ['Active', 'active', 'ACTIVE', '1', 1, true, 'Published', 'published', 'Enabled', 'enabled'])
                ->when($tenantId, fn ($query, $tenantId) => $query->where('exams.organization_id', $tenantId))
                ->select(['exams.id', 'exams.name', 'exams.created_at']);
        }]);

        $orderedIdsByPackage = $packages->mapWithKeys(function ($package) {
            $orderedIds = ExamDisplayOrder::newestYearFirst($package->exams)
                ->pluck('id')
                ->map(fn ($examId) => (int) $examId)
                ->values();

            return [$package->id => $orderedIds];
        });
        $initialIds = $orderedIdsByPackage
            ->flatMap(fn ($examIds) => $examIds->take(20))
            ->unique()
            ->values();
        $examsById = $initialIds->isEmpty()
            ? collect()
            : $this->tenantExamQuery()
                ->whereIn('id', $initialIds)
                ->withSum('questions', 'marks')
                ->get()
                ->keyBy('id');

        foreach ($packages as $package) {
            $orderedIds = $orderedIdsByPackage->get($package->id, collect());
            $package->dashboard_exam_total = $orderedIds->count();
            $package->setRelation('exams', $orderedIds
                ->take(20)
                ->map(fn ($examId) => $examsById->get($examId))
                ->filter()
                ->values());
        }
    }
    private function orderedPackageExamIds(Package $package)
    {
        $tenantId = $this->tenantId();
        $summaries = $package->exams()
            ->whereIn('status', ['Active', 'active', 'ACTIVE', '1', 1, true, 'Published', 'published', 'Enabled', 'enabled'])
            ->when($tenantId, fn ($query, $tenantId) => $query->where('exams.organization_id', $tenantId))
            ->select(['exams.id', 'exams.name', 'exams.created_at'])
            ->get();

        return ExamDisplayOrder::newestYearFirst($summaries)
            ->pluck('id')
            ->map(fn ($examId) => (int) $examId)
            ->values();
    }

    private function loadPackageExamBatch($orderedExamIds, int $offset, int $limit)
    {
        $batchIds = $orderedExamIds->slice($offset, $limit)->values();
        if ($batchIds->isEmpty()) {
            return collect();
        }

        $examsById = $this->tenantExamQuery()
            ->whereIn('id', $batchIds)
            ->withSum('questions', 'marks')
            ->get()
            ->keyBy('id');

        return $batchIds
            ->map(fn ($examId) => $examsById->get($examId))
            ->filter()
            ->values();
    }
    private function hydrateStudentExamState($exams, $attemptCounts, $latestResults): void
    {
        $now = Carbon::now();
        foreach ($exams as $exam) {
            $attemptCount = $attemptCounts->get($exam->id, 0);
            $exam->attempts_left = ((int) $exam->attempt_count === 0)
                ? 'Unlimited'
                : max(0, (int) $exam->attempt_count - (int) $attemptCount);
            $startDate = Carbon::parse($exam->start_date);
            $endDate = Carbon::parse($exam->end_date);
            $exam->exam_status = $now->between($startDate, $endDate)
                ? 'Live'
                : ($now->lt($startDate) ? 'Upcoming' : 'Expired');
            $exam->latest_result_id = $latestResults->get($exam->id);
        }
    }

    public function getExamDetails($id)
    {
        $studentId = Auth::guard('student')->id();
        $exam = $this->tenantExamQuery()
            ->with(['questions:id,subject_id,marks', 'questions.subject:id,subject_name'])
            ->findOrFail($id);
        $this->ensureStudentCanAccessExam($exam);

        $totalMarks = $exam->questions->sum('marks');
        $attemptCount = ExamResult::where('exam_id', $exam->id)
            ->where('student_id', $studentId)
            ->when($this->tenantId(), function ($query, $tenantId) {
                $query->where('organization_id', $tenantId);
            })
            ->whereNotNull('end_time')
            ->count();
        $attemptsLeft = ((int) $exam->attempt_count === 0)
            ? 'Unlimited'
            : max(0, (int) $exam->attempt_count - $attemptCount);
        $subjectDetails = $exam->questions
            ->groupBy('subject_id')
            ->map(function ($questions) {
                $subject = $questions->first()->subject;

                return [
                    'subject_name' => $subject->subject_name ?? 'General',
                    'total_questions' => $questions->count(),
                ];
            })
            ->values();

        return response()->json([
            'exam' => [
                'id' => $exam->id,
                'name' => $exam->name,
                'slug' => $exam->slug,
                'duration' => $exam->duration,
                'start_date' => $exam->start_date,
                'end_date' => $exam->end_date,
                'mode' => $exam->mode,
                'type' => $exam->type,
                'exam_type' => $exam->exam_type,
                'passing_percentage' => $exam->passing_percentage,
                'negative_marking' => $exam->negative_marking,
                'attempt_count' => $exam->attempt_count,
                'attempts_left' => $attemptsLeft,
                'total_questions' => $exam->questions->count(),
                'subject_details' => $subjectDetails,
                'syllabus' => $exam->syllabus,
            ],
            'total_marks' => $totalMarks,
        ]);
    }

    public function checkAttempts($id)
    {
        $studentId = Auth::guard('student')->id();
        $exam = $this->resolveExam($id);
        if ($exam->attempt_count == 0) {
            return response()->json(['attempts_left' => 'Unlimited']);
        }
        
        $attemptCount = ExamResult::where('exam_id', $exam->id)
            ->where('student_id', $studentId)
            ->when($this->tenantId(), function ($query, $tenantId) {
                $query->where('organization_id', $tenantId);
            })
            ->whereNotNull('end_time')
            ->count();
            
        return response()->json([
            'attempts_left' => max(0, $exam->attempt_count - $attemptCount)
        ]);
    }

    private function resolveExam($id): Exam
    {
        $exam = $this->tenantExamQuery()
            ->where(function ($query) use ($id) {
                if (is_numeric($id)) {
                    $query->where('id', $id);
                }

                $query->orWhere('slug', $id);
            })
            ->firstOrFail();

        $this->ensureStudentCanAccessExam($exam);

        return $exam;
    }

    // Finalize pending exam with hard stop.
    public function finalizePending(Request $request)
    {
        $studentId = Auth::guard('student')->id();
        
        $examResult = $this->scopeOfficialExamResults(ExamResult::where('student_id', $studentId))
            ->when($this->tenantId(), function ($query, $tenantId) {
                $query->where('organization_id', $tenantId);
            })
            ->whereNull('end_time')
            ->first(); 

        if (!$examResult) {
            return redirect()->back()->with('error', 'No pending exam found to finalize.');
        }

        $exam = $this->tenantExamQuery()->findOrFail($examResult->exam_id);
        $this->ensureStudentCanAccessExam($exam);

        // 1. Time Calculation Logic
        $startTime = Carbon::parse($examResult->start_time);
        $endTime = Carbon::now(); 
        
        // Calculate theoretical max end time based on duration
        $maxEndTimeDuration = $startTime->copy()->addMinutes($exam->duration);
        
        // Movie Ticket Logic: Check if exam has a hard end date
        if ($exam->end_date) {
            $examAbsoluteEndTime = Carbon::parse($exam->end_date);
            
            // If duration goes beyond closing time, clip it
            if ($maxEndTimeDuration->gt($examAbsoluteEndTime)) {
                $maxEndTimeDuration = $examAbsoluteEndTime;
            }
            
            // Ensure actual end time doesn't exceed closing time
            if ($endTime->gt($examAbsoluteEndTime)) {
                $endTime = $examAbsoluteEndTime;
            }
        }

        // Clip end time if it exceeds duration
        if ($exam->duration > 0 && $endTime->gt($maxEndTimeDuration)) {
            $endTime = $maxEndTimeDuration;
        }

        $testTime = $startTime->diffInSeconds($endTime);

        // 2. Marks Calculation
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
            } elseif ($stat->answered && ! $marksToAll && $stat->negative_marks > 0) {
                $marksObtained = -1 * $stat->negative_marks;
            }

            $stat->update([
                'ques_status' => $isCorrect ? 'R' : 'W',
                'marks_obtained' => $marksObtained
            ]);
            
            $obtainedMarks += $marksObtained;
        }

        // 3. Final Result Update
        $totalAnswered = $examStats->where('answered', true)->count();
        $passingMarks = $examResult->total_marks * ($exam->passing_percentage / 100);
        $resultStatus = $obtainedMarks >= $passingMarks ? 'Pass' : 'Fail';
        $percent = $examResult->total_marks > 0 ? ($obtainedMarks / $examResult->total_marks) * 100 : 0;

        $examResult->update([
            'end_time' => $endTime,
            'test_time' => $testTime,
            'obtained_marks' => $obtainedMarks,
            'result' => $resultStatus,
            'percent' => $percent,
            'finalized_time' => Carbon::now(),
            'total_answered' => $totalAnswered,
        ]);

        // 4. Generate Report
        if (class_exists(\App\Helpers\ResultHelper::class)) {
            \App\Helpers\ResultHelper::computeResultDetails($examResult, $examResult->id);
        }
        
        return redirect()->route('student.myexams')->with('success', 'Exam finalized successfully. You can now view the result.');
    }

    private function studentHasPackageEnrollment(int $studentId, int $packageId): bool
    {
        return OrderItem::query()
            ->where('package_id', $packageId)
            ->whereHas('order', function ($query) use ($studentId) {
                $query->where('student_id', $studentId)
                    ->where('status', 'completed')
                    ->when($this->tenantId(), function ($orderQuery, $tenantId) {
                        $orderQuery->where(function ($tenantQuery) use ($tenantId) {
                            $tenantQuery->where('organization_id', $tenantId)
                                ->orWhereNull('organization_id');
                        });
                    });
            })
            ->exists();
    }

    private function ensureTenantPackage(Package $package): void
    {
        if ($this->tenantId() && (int) $package->organization_id !== (int) $this->tenantId()) {
            abort(404);
        }
    }

    public function hidePackage(Package $package)
    {
        $this->ensureTenantPackage($package);
        $student = Auth::guard('student')->user();

        abort_unless($this->studentHasPackageEnrollment((int) $student->id, (int) $package->id), 404);

        StudentHiddenPackage::updateOrCreate(
            ['student_id' => $student->id, 'package_id' => $package->id],
            ['organization_id' => $this->tenantId(), 'hidden_at' => now()]
        );

        StudentActivityTracker::track(StudentActivityTracker::PACKAGE_HIDDEN, [
            'organization_id' => $this->tenantId(),
            'student_id' => $student->id,
            'package_id' => $package->id,
            'source' => 'student',
        ]);

        return back()->with('success', 'Package removed from My Exams. You can restore it from Hidden Packages.');
    }

    public function restorePackage(Package $package)
    {
        $this->ensureTenantPackage($package);
        $student = Auth::guard('student')->user();

        StudentHiddenPackage::where('student_id', $student->id)
            ->where('package_id', $package->id)
            ->delete();

        StudentActivityTracker::track(StudentActivityTracker::PACKAGE_RESTORED, [
            'organization_id' => $this->tenantId(),
            'student_id' => $student->id,
            'package_id' => $package->id,
            'source' => 'student',
        ]);

        return back()->with('success', 'Package restored to My Exams.');
    }
    public function selectGroup(Request $request) {
        
        $validator = Validator::make($request->all(), [
            'group_id' => 'required|exists:groups,id',
        ]);

        if ($validator->fails()) {

            return response()->json([
                'status' => false,
                'errors' => $validator->errors(),
            ], 422);

        }

        $student = auth()->guard('student')->user();

        $groupId = $request->group_id;
        $tenantId = $this->tenantId();

        if ($tenantId && ! Group::where('organization_id', $tenantId)->where('id', $groupId)->exists()) {
            return response()->json([
                'status' => false,
                'message' => 'Invalid group selected.',
            ], 422);
        }

        // Prevent duplicate group attach
        if ($student->groups()->where('group_id', $groupId)->exists()) {

            return response()->json([
                'status' => false,
                'message' => 'Group already selected.',
            ]);

        }

        DB::beginTransaction();

        try {

            // Attach group
            $student->groups()->attach($groupId);

            $addedPackages = $this->freePackageEnrollmentService->enrollForGroup(
                $student,
                (int) $groupId,
                $tenantId,
                $request
            );

            DB::commit();

            return response()->json([
                'status' => true,
                'message' => 'Your exam group is ready.',
                'packages_added' => $addedPackages->count(),
            ]);

        } catch (\Exception $e) {

            DB::rollback();

            return response()->json([
                'status' => false,
                'message' => 'Something went wrong.',
                'error' => $e->getMessage(), // remove in production
            ], 500);

        }
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Exam;
use App\Models\ExamResult;
use App\Models\ExamStat;
use App\Models\Flashcard;
use App\Models\Group;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Package;
use App\Models\Student;
use App\Models\StudentActivityEvent;
use App\Models\StudentFlashcardPoint;
use App\Models\StudentFlashcardProgress;
use App\Services\StudentActivityTracker;
use App\Support\CategoryHierarchy;
use App\Support\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class DemoStudentController extends Controller
{
    public function index(Request $request)
    {
        $tenantId = $this->tenantId();
        $perPage = (int) $request->input('per_page', 50);
        $perPage = in_array($perPage, [50, 100, 500], true) ? $perPage : 50;

        $demoQuery = Student::query()
            ->where('is_demo', true)
            ->when($tenantId, fn ($query) => $query->where('organization_id', $tenantId));

        $demoStudents = (clone $demoQuery)
            ->with('groups:id,group_name')
            ->withCount('examResults')
            ->withAvg('examResults', 'percent')
            ->withCount('flashcardProgress')
            ->withSum('flashcardPoints as study_points_total', 'total_points')
            ->when($request->filled('batch'), fn ($query) => $query->where('demo_batch_id', $request->batch))
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = trim((string) $request->search);
                $query->where(function ($inner) use ($search) {
                    $inner->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('demo_batch_id', 'like', "%{$search}%");
                });
            })
            ->latest('demo_generated_at')
            ->paginate($perPage)
            ->withQueryString();

        $batches = (clone $demoQuery)
            ->select('demo_batch_id', DB::raw('COUNT(*) as total'), DB::raw('MAX(demo_generated_at) as last_generated_at'))
            ->whereNotNull('demo_batch_id')
            ->groupBy('demo_batch_id')
            ->orderByDesc('last_generated_at')
            ->get();

        $demoStudentIds = (clone $demoQuery)->pluck('id');
        $resultStats = ExamResult::query()
            ->whereIn('student_id', $demoStudentIds)
            ->selectRaw('COUNT(*) as total_results, AVG(percent) as average_percent')
            ->first();
        $studyStats = StudentFlashcardPoint::query()
            ->whereIn('student_id', $demoStudentIds)
            ->selectRaw('COUNT(DISTINCT student_id) as learners, COALESCE(SUM(cards_studied), 0) as cards, COALESCE(SUM(total_points), 0) as points')
            ->first();

        $stats = [
            'students' => (clone $demoQuery)->count(),
            'batches' => $batches->count(),
            'results' => (int) ($resultStats->total_results ?? 0),
            'average_percent' => round((float) ($resultStats->average_percent ?? 0), 2),
            'study_learners' => (int) ($studyStats->learners ?? 0),
            'study_cards' => (int) ($studyStats->cards ?? 0),
            'study_points' => (int) ($studyStats->points ?? 0),
        ];

        $groups = Group::query()
            ->when($tenantId, fn ($query) => $query->where('organization_id', $tenantId))
            ->orderBy('group_name')
            ->get(['id', 'group_name']);

        $parentCategories = Category::query()
            ->with(['groups:id', 'children' => fn ($query) => $query->orderBy('title')])
            ->when($tenantId, fn ($query) => $query->where('organization_id', $tenantId))
            ->whereNull('parent_id')
            ->orderBy('title')
            ->get();

        return view('demo_students.index', compact('demoStudents', 'batches', 'groups', 'stats', 'parentCategories'));
    }

    public function generate(Request $request)
    {
        $validated = $request->validate([
            'count' => ['required', 'integer', 'min:1', 'max:200'],
            'group_mode' => ['required', 'in:selected,all'],
            'group_ids' => ['nullable', 'array'],
            'group_ids.*' => ['integer'],
            'category_level_1' => ['nullable', 'integer'],
            'category_level_2' => ['nullable', 'integer'],
            'packages_per_student' => ['required', 'integer', 'min:1', 'max:20'],
            'exams_per_student' => ['required', 'integer', 'min:1', 'max:10'],
            'generate_study_cards' => ['nullable', 'boolean'],
            'study_cards_per_student' => ['nullable', 'integer', 'min:0', 'max:100'],
            'score_min' => ['required', 'numeric', 'min:0', 'max:100'],
            'score_max' => ['required', 'numeric', 'min:0', 'max:100'],
        ]);

        if ((float) $validated['score_min'] > (float) $validated['score_max']) {
            return back()->withErrors(['score_min' => 'Minimum score cannot be greater than maximum score.'])->withInput();
        }

        $tenantId = $this->tenantId();
        $categoryId = ! empty($validated['category_level_1']) ? (int) $validated['category_level_1'] : null;
        $subcategoryId = ! empty($validated['category_level_2']) ? (int) $validated['category_level_2'] : null;

        if ($subcategoryId && ! $categoryId) {
            return back()->withErrors(['category_level_1' => 'Select a category before selecting a subcategory.'])->withInput();
        }

        $groups = Group::query()
            ->when($tenantId, fn ($query) => $query->where('organization_id', $tenantId))
            ->when($validated['group_mode'] === 'selected', fn ($query) => $query->whereIn('id', $validated['group_ids'] ?? []))
            ->orderBy('group_name')
            ->get();

        if ($tenantId && $categoryId) {
            $validatedGroupIds = $validated['group_mode'] === 'selected' ? $groups->pluck('id')->all() : [];
            CategoryHierarchy::validate($categoryId, $subcategoryId, $validatedGroupIds, $tenantId);

            if ($validated['group_mode'] === 'all') {
                $categoryGroupIds = Category::whereKey($categoryId)->first()?->groups()->pluck('groups.id') ?? collect();
                if ($categoryGroupIds->isNotEmpty()) {
                    $groups = $groups->whereIn('id', $categoryGroupIds)->values();
                }
            }
        }

        if ($groups->isEmpty()) {
            return back()->withErrors(['group_ids' => 'Please select at least one group with packages or exams.'])->withInput();
        }

        $batchId = 'DEMO-' . now()->format('YmdHis') . '-' . strtoupper(Str::random(4));
        $created = 0;
        $results = 0;
        $studyCards = 0;
        $studyPoints = 0;
        $generateStudyCards = (bool) ($validated['generate_study_cards'] ?? false);
        $studyCardsPerStudent = (int) ($validated['study_cards_per_student'] ?? 0);

        DB::transaction(function () use ($validated, $tenantId, $groups, $categoryId, $subcategoryId, $batchId, $generateStudyCards, $studyCardsPerStudent, &$created, &$results, &$studyCards, &$studyPoints) {
            for ($index = 0; $index < (int) $validated['count']; $index++) {
                $group = $groups[$index % $groups->count()];
                $student = $this->createDemoStudent($tenantId, $group, $batchId, $index + 1);
                $created++;

                $packages = $this->packagesForGroup($group, $tenantId, $categoryId, $subcategoryId)
                    ->shuffle()
                    ->take((int) $validated['packages_per_student'])
                    ->values();

                if ($packages->isNotEmpty()) {
                    $this->createDemoOrder($student, $packages, $tenantId);
                }

                $exams = $this->examsForGroup($group, $tenantId, $packages, $categoryId, $subcategoryId)
                    ->shuffle()
                    ->take((int) $validated['exams_per_student'])
                    ->values();

                foreach ($exams as $exam) {
                    $this->createDemoResult(
                        $student,
                        $exam,
                        $tenantId,
                        (float) $validated['score_min'],
                        (float) $validated['score_max']
                    );
                    $results++;
                }

                if ($generateStudyCards && $studyCardsPerStudent > 0) {
                    $study = $this->createDemoStudyCardProgress($student, $group, $tenantId, $packages, $studyCardsPerStudent);
                    $studyCards += $study['cards'];
                    $studyPoints += $study['points'];
                }
            }
        });

        return redirect()
            ->route('demo-students.index', ['batch' => $batchId])
            ->with('success', "{$created} demo students, {$results} demo exam attempts, and {$studyCards} study-card reviews were created.");
    }

    public function destroy(Student $student)
    {
        $this->authorizeDemoStudent($student);
        $this->deleteDemoStudents(collect([$student->id]));

        return back()->with('success', 'Demo student deleted.');
    }

    public function deleteBatch(Request $request)
    {
        $validated = $request->validate([
            'batch_id' => ['required', 'string'],
        ]);

        $studentIds = Student::query()
            ->where('is_demo', true)
            ->where('demo_batch_id', $validated['batch_id'])
            ->when($this->tenantId(), fn ($query, $tenantId) => $query->where('organization_id', $tenantId))
            ->pluck('id');

        $this->deleteDemoStudents($studentIds);

        return redirect()->route('demo-students.index')->with('success', 'Demo batch deleted.');
    }

    private function tenantId(): ?int
    {
        try {
            return Tenant::id();
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function createDemoStudent(?int $tenantId, Group $group, string $batchId, int $sequence): Student
    {
        $name = $this->demoName($sequence);
        $safeBatch = strtolower(str_replace('-', '.', $batchId));

        $student = Student::create([
            'organization_id' => $tenantId,
            'name' => $name,
            'email' => "demo.{$safeBatch}.{$sequence}@example.invalid",
            'password' => Hash::make(Str::random(24)),
            'address' => 'Demo profile',
            'phone' => $this->demoPhone($batchId, $sequence),
            'status' => 'Active',
            'reg_code' => 'DEMO-' . strtoupper(Str::random(8)),
            'reg_status' => 'Live',
            'email_verified_at' => now(),
            'is_demo' => true,
            'demo_batch_id' => $batchId,
            'demo_created_by' => Auth::id(),
            'demo_generated_at' => now(),
        ]);

        $student->groups()->sync([$group->id]);

        return $student;
    }

    private function packagesForGroup(Group $group, ?int $tenantId, ?int $categoryId = null, ?int $subcategoryId = null): Collection
    {
        return Package::query()
            ->with('exams')
            ->where('status', true)
            ->when($tenantId, fn ($query) => $query->where('organization_id', $tenantId))
            ->when($categoryId, fn ($query) => $query->where('category_level_1', $categoryId))
            ->when($subcategoryId, fn ($query) => $query->where('category_level_2', $subcategoryId))
            ->whereHas('groups', fn ($query) => $query->where('groups.id', $group->id))
            ->orderBy('name')
            ->get();
    }

    private function examsForGroup(Group $group, ?int $tenantId, Collection $packages, ?int $categoryId = null, ?int $subcategoryId = null): Collection
    {
        $packageExams = $packages
            ->flatMap(fn (Package $package) => $package->exams)
            ->filter(fn (Exam $exam) => $this->isActiveExam($exam, $tenantId))
            ->unique('id')
            ->sortBy(fn (Exam $exam) => strtolower((string) $exam->name))
            ->values();

        if ($packageExams->isNotEmpty()) {
            return $packageExams;
        }

        return Exam::query()
            ->when($tenantId, fn ($query) => $query->where('organization_id', $tenantId))
            ->when($categoryId, fn ($query) => $query->where('category_level_1', $categoryId))
            ->when($subcategoryId, fn ($query) => $query->where('category_level_2', $subcategoryId))
            ->whereHas('groups', fn ($query) => $query->where('groups.id', $group->id))
            ->orderBy('name')
            ->get()
            ->filter(fn (Exam $exam) => $this->isActiveExam($exam, $tenantId))
            ->values();
    }

    private function createDemoOrder(Student $student, Collection $packages, ?int $tenantId): void
    {
        $total = $packages->sum(fn (Package $package) => (float) ($package->discounted_amount ?: $package->amount ?: 0));

        [$firstName, $lastName] = $this->splitName($student->name);
        $orderAttributes = [
            'student_id' => $student->id,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $student->email,
            'phone' => $student->phone ?: '0000000000',
            'address' => 'Demo profile',
            'city' => 'Demo City',
            'state' => 'Demo State',
            'zip' => '000000',
            'payment_method' => 'demo',
            'status' => 'completed',
            'total' => $total,
            'notes' => 'Demo enrollment generated for leaderboard preview.',
        ];

        foreach ([
            'organization_id' => $tenantId,
            'name' => $student->name,
            'payment_status' => 'Completed',
            'discount' => 0,
        ] as $column => $value) {
            if (Schema::hasColumn('orders', $column)) {
                $orderAttributes[$column] = $value;
            }
        }

        $order = Order::create($orderAttributes);

        foreach ($packages as $package) {
            OrderItem::create([
                'order_id' => $order->id,
                'package_id' => $package->id,
                'name' => $this->plainText($package->name),
                'price' => (float) ($package->discounted_amount ?: $package->amount ?: 0),
                'quantity' => 1,
            ]);
        }
    }

    private function createDemoResult(Student $student, Exam $exam, ?int $tenantId, float $scoreMin, float $scoreMax): void
    {
        $questions = $exam->questions()
            ->select('questions.id', 'questions.subject_id', 'questions.marks', 'questions.negative_marks', 'questions.answer')
            ->limit(250)
            ->get();

        $targetPercent = $this->randomFloat($scoreMin, $scoreMax);
        $startedAt = now()->subDays(random_int(0, 45))->subMinutes(random_int(25, 180));
        $durationSeconds = random_int(900, max(1200, (int) ($exam->duration ?: 60) * 60));
        $endedAt = (clone $startedAt)->addSeconds($durationSeconds);

        $totalQuestions = max(1, $questions->count());
        $totalMarks = max(1.0, (float) $questions->sum(fn ($question) => (float) ($question->marks ?: 1)));

        $result = ExamResult::create([
            'organization_id' => $tenantId,
            'exam_id' => $exam->id,
            'student_id' => $student->id,
            'start_time' => $startedAt,
            'end_time' => $endedAt,
            'attempt_time' => $endedAt,
            'total_test_time' => $durationSeconds,
            'test_time' => $durationSeconds,
            'pause_time' => null,
            'total_question' => $totalQuestions,
            'total_attempt' => 0,
            'total_answered' => 0,
            'total_marks' => $totalMarks,
            'obtained_marks' => 0,
            'result' => 'Fail',
            'percent' => 0,
            'finalized_time' => $endedAt,
            'tolerance_count' => 0,
        ]);

        $attempted = 0;
        $answered = 0;
        $obtained = 0.0;
        $statsCreated = 0;
        $correctChance = max(0, min(100, $targetPercent)) / 100;

        foreach ($questions as $questionIndex => $question) {
            if (! $question->subject_id) {
                continue;
            }

            $isAnswered = random_int(1, 100) <= 92;
            $isCorrect = $isAnswered && (random_int(1, 100) / 100 <= $correctChance);
            $marks = (float) ($question->marks ?: 1);
            $negativeMarks = (float) ($question->negative_marks ?: 0);
            $marksObtained = 0.0;

            if ($isAnswered) {
                $attempted++;
                $answered++;
                $marksObtained = $isCorrect ? $marks : -abs($negativeMarks);
                $obtained += $marksObtained;
            }

            ExamStat::create([
                'organization_id' => $tenantId,
                'exam_result_id' => $result->id,
                'exam_id' => $exam->id,
                'student_id' => $student->id,
                'question_id' => $question->id,
                'subject_id' => $question->subject_id,
                'ques_no' => $questionIndex + 1,
                'opened' => 1,
                'answered' => $isAnswered ? 1 : 0,
                'review' => 0,
                'option_selected' => $isAnswered ? 'demo' : null,
                'correct_answer' => $question->answer,
                'marks' => $marks,
                'negative_marks' => $negativeMarks,
                'marks_obtained' => $marksObtained,
                'ques_status' => $isAnswered ? ($isCorrect ? 'R' : 'W') : null,
                'time_taken' => random_int(12, 180),
                'bookmark' => 0,
            ]);
            $statsCreated++;
        }

        if ($statsCreated === 0) {
            $obtained = round($totalMarks * ($targetPercent / 100), 2);
        }

        $percent = round(max(0, min(100, ($obtained / $totalMarks) * 100)), 2);
        $passing = (float) ($exam->passing_percentage ?: 0);

        $result->update([
            'total_attempt' => $attempted,
            'total_answered' => $answered,
            'obtained_marks' => round($obtained, 2),
            'percent' => $percent,
            'result' => $percent >= $passing ? 'Pass' : 'Fail',
        ]);
    }

    private function deleteDemoStudents(Collection $studentIds): void
    {
        if ($studentIds->isEmpty()) {
            return;
        }

        DB::transaction(function () use ($studentIds) {
            $resultIds = ExamResult::whereIn('student_id', $studentIds)->pluck('id');
            ExamStat::whereIn('exam_result_id', $resultIds)->delete();
            ExamResult::whereIn('id', $resultIds)->delete();

            $orderIds = Order::whereIn('student_id', $studentIds)->pluck('id');
            OrderItem::whereIn('order_id', $orderIds)->delete();
            Order::whereIn('id', $orderIds)->delete();

            StudentFlashcardProgress::whereIn('student_id', $studentIds)->delete();
            StudentFlashcardPoint::whereIn('student_id', $studentIds)->delete();
            StudentActivityEvent::whereIn('student_id', $studentIds)->delete();

            DB::table('student_groups')->whereIn('student_id', $studentIds)->delete();
            Student::whereIn('id', $studentIds)->where('is_demo', true)->delete();
        });
    }

    private function createDemoStudyCardProgress(Student $student, Group $group, ?int $tenantId, Collection $packages, int $limit): array
    {
        if ($limit <= 0) {
            return ['cards' => 0, 'points' => 0];
        }

        $packageIds = $packages->pluck('id')->filter()->values();

        $cards = Flashcard::query()
            ->with('set:id,organization_id,package_id,group_id,status')
            ->where('status', true)
            ->whereHas('set', function ($query) use ($tenantId, $packageIds, $group) {
                $query->where('status', true)
                    ->when($tenantId, fn ($setQuery) => $setQuery->where('organization_id', $tenantId))
                    ->where(function ($scope) use ($packageIds, $group) {
                        $scope->where('group_id', $group->id);

                        if ($packageIds->isNotEmpty()) {
                            $scope->orWhereIn('package_id', $packageIds);
                        }
                    });
            })
            ->inRandomOrder()
            ->limit($limit)
            ->get(['id', 'flashcard_set_id']);

        if ($cards->isEmpty()) {
            return ['cards' => 0, 'points' => 0];
        }

        $sets = [];
        $totalPoints = 0;
        $totalCards = 0;
        $activityRows = [];

        foreach ($cards as $card) {
            $lastActivityAt = now()->subDays(random_int(0, 30))->subMinutes(random_int(1, 720));
            $answeredCorrect = random_int(1, 100) <= 78;
            $wrongAttempts = $answeredCorrect ? random_int(0, 1) : random_int(1, 3);
            $correctAttempts = $answeredCorrect ? 1 : 0;
            $confidence = ['again', 'hard', 'good', 'easy'][random_int(0, 3)];
            $confidenceAwarded = in_array($confidence, ['good', 'easy'], true);
            $points = 0;

            if ($answeredCorrect) {
                $points += $wrongAttempts > 0 ? 3 : 2;
            }

            if ($confidenceAwarded) {
                $points += 1;
            }

            StudentFlashcardProgress::updateOrCreate(
                [
                    'student_id' => $student->id,
                    'flashcard_id' => $card->id,
                ],
                [
                    'package_id' => $card->set?->package_id,
                    'flashcard_set_id' => $card->flashcard_set_id,
                    'viewed_at' => $lastActivityAt,
                    'last_answer_correct' => $answeredCorrect,
                    'correct_answered_at' => $answeredCorrect ? $lastActivityAt : null,
                    'wrong_attempts' => $wrongAttempts,
                    'correct_attempts' => $correctAttempts,
                    'confidence' => $confidence,
                    'confidence_awarded' => $confidenceAwarded,
                    'points_earned' => $points,
                    'last_interaction_at' => $lastActivityAt,
                ]
            );

            $activityRows[] = $this->demoActivityRow(
                $student,
                $tenantId,
                StudentActivityTracker::STUDY_CARD_ANSWERED,
                $card->set?->package_id,
                [
                    'flashcard_set_id' => $card->flashcard_set_id,
                    'flashcard_id' => $card->id,
                    'correct' => $answeredCorrect,
                ],
                $lastActivityAt
            );

            $setId = $card->flashcard_set_id;
            $sets[$setId]['package_id'] = $card->set?->package_id;
            $sets[$setId]['cards'] = ($sets[$setId]['cards'] ?? 0) + 1;
            $sets[$setId]['correct'] = ($sets[$setId]['correct'] ?? 0) + ($answeredCorrect ? 1 : 0);
            $sets[$setId]['points'] = ($sets[$setId]['points'] ?? 0) + $points;
            if (! isset($sets[$setId]['last_activity_at']) || $lastActivityAt->greaterThan($sets[$setId]['last_activity_at'])) {
                $sets[$setId]['last_activity_at'] = $lastActivityAt;
            }

            $totalCards++;
            $totalPoints += $points;
        }

        foreach ($sets as $setId => $data) {
            $activityRows[] = $this->demoActivityRow($student, $tenantId, StudentActivityTracker::STUDY_CARDS_OPENED, $data['package_id'], [
                'flashcard_set_id' => $setId,
                'cards' => $data['cards'],
            ], (clone $data['last_activity_at'])->subMinutes(5));

            $activityRows[] = $this->demoActivityRow($student, $tenantId, StudentActivityTracker::STUDY_CARD_COMPLETED, $data['package_id'], [
                'flashcard_set_id' => $setId,
                'cards' => $data['cards'],
                'correct' => $data['correct'],
            ], $data['last_activity_at']);

            StudentFlashcardPoint::updateOrCreate(
                [
                    'student_id' => $student->id,
                    'flashcard_set_id' => $setId,
                ],
                [
                    'package_id' => $data['package_id'],
                    'total_points' => $data['points'],
                    'cards_studied' => $data['cards'],
                    'correct_answers' => $data['correct'],
                    'last_activity_at' => $data['last_activity_at'],
                ]
            );
        }

        if ($activityRows) {
            StudentActivityEvent::insert($activityRows);
        }

        return ['cards' => $totalCards, 'points' => $totalPoints];
    }

    private function demoActivityRow(Student $student, ?int $tenantId, string $eventName, ?int $packageId, array $metadata, $occurredAt): array
    {
        $occurredAt = $occurredAt instanceof \Carbon\CarbonInterface ? $occurredAt : now();

        return [
            'organization_id' => $tenantId,
            'student_id' => $student->id,
            'guest_id' => null,
            'exam_id' => null,
            'package_id' => $packageId,
            'order_id' => null,
            'exam_result_id' => null,
            'event_name' => $eventName,
            'source' => 'student',
            'url' => null,
            'referrer' => null,
            'ip_address' => null,
            'user_agent' => 'demo-generator',
            'metadata' => json_encode($metadata),
            'occurred_at' => $occurredAt->toDateTimeString(),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    private function authorizeDemoStudent(Student $student): void
    {
        abort_unless($student->is_demo, 404);

        $tenantId = $this->tenantId();
        if ($tenantId) {
            abort_unless((int) $student->organization_id === (int) $tenantId, 404);
        }
    }

    private function isActiveExam(Exam $exam, ?int $tenantId): bool
    {
        if ($tenantId && (int) $exam->organization_id !== (int) $tenantId) {
            return false;
        }

        return in_array((string) $exam->status, ['1', 'true', 'Active', 'active', 'Published', 'published'], true);
    }

    private function plainText($value): string
    {
        if (is_array($value)) {
            return (string) (reset($value) ?: '');
        }

        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return (string) (reset($decoded) ?: $value);
            }
        }

        return (string) $value;
    }

    private function randomFloat(float $min, float $max): float
    {
        if ($min === $max) {
            return $min;
        }

        return round($min + (mt_rand() / mt_getrandmax()) * ($max - $min), 2);
    }

    private function splitName(string $name): array
    {
        $parts = preg_split('/\s+/', trim($name), 2);

        return [$parts[0] ?? 'Demo', $parts[1] ?? 'Student'];
    }

    private function demoPhone(string $batchId, int $sequence): string
    {
        $digits = preg_replace('/\D/', '', $batchId) ?: now()->format('His');

        return '9' . substr(str_pad($digits . $sequence, 9, '0'), -9);
    }

    private function demoName(int $sequence): string
    {
        $firstNames = ['Aarav', 'Vivaan', 'Aditya', 'Arjun', 'Sai', 'Ishaan', 'Kabir', 'Rohan', 'Ananya', 'Diya', 'Kavya', 'Meera', 'Nisha', 'Priya', 'Sneha', 'Aditi', 'Rahul', 'Aman', 'Karan', 'Saurabh'];
        $middleNames = ['Kumar', 'Raj', 'Prasad', 'Kiran', 'Deep', 'Chandra', 'Nath', 'Rani', 'Devi', 'Lal'];
        $lastNames = ['Sharma', 'Verma', 'Patel', 'Gupta', 'Kumar', 'Singh', 'Reddy', 'Nair', 'Das', 'Mishra', 'Yadav', 'Jain', 'Roy', 'Mehta', 'Khan', 'Chauhan'];
        $abbreviations = ['MH', 'MNV', 'AK', 'SK', 'RK', 'PK', 'VK', 'AM', 'SM', 'NK', 'RS', 'AS', 'VG', 'AV', 'KM', 'MS', 'SS', 'AR', 'NS', 'PS', 'RKV', 'SPM', 'AKS', 'VKS', 'MHS', 'SNP', 'KRM', 'DPS', 'RNS', 'JKS'];
        $firstName = $firstNames[($sequence - 1) % count($firstNames)];

        if ($sequence % 4 === 0) {
            return $abbreviations[($sequence - 1) % count($abbreviations)];
        }

        if ($sequence % 7 === 0) {
            return $firstName;
        }

        if ($sequence % 5 === 0) {
            return $firstName . ' '
                . $middleNames[($sequence - 1) % count($middleNames)] . ' '
                . $lastNames[random_int(0, count($lastNames) - 1)];
        }

        return $firstName . ' ' . $lastNames[random_int(0, count($lastNames) - 1)];
    }
}

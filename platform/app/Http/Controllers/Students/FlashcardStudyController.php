<?php

namespace App\Http\Controllers\Students;

use App\Http\Controllers\Controller;
use App\Models\Flashcard;
use App\Models\FlashcardReview;
use App\Models\Order;
use App\Models\Package;
use App\Models\QuestionsReport;
use App\Models\StudentFlashcardPoint;
use App\Models\StudentFlashcardPointEvent;
use App\Models\StudentFlashcardProgress;
use App\Models\StudentFlashcardCourseProgress;
use App\Services\FlashcardQuestionRotationService;
use App\Services\StudentActivityTracker;
use App\Support\FlashcardStudyHierarchy;
use App\Support\SaasAccess;
use App\Support\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class FlashcardStudyController extends Controller
{
    private function tenantId(): ?int
    {
        return Tenant::hostId(request()->getHost()) ?: Tenant::id();
    }

    public function index()
    {
        SaasAccess::abortIfFeatureDisabled('flashcards');

        $student = Auth::guard('student')->user();
        $packageIds = $this->accessiblePackageQuery($student)
            ->pluck('packages.id')
            ->all();

        $packages = Package::query()
            ->with([
                'groups:id,group_name',
                'flashcardSets' => fn ($query) => $query->where('status', true)->withCount(['cards' => fn ($cardQuery) => $cardQuery->where('status', true)]),
            ])
            ->whereIn('id', $packageIds)
            ->orderBy('name')
            ->get();

        $reviewedCards = FlashcardReview::query()
            ->where('student_id', $student->id)
            ->whereIn('package_id', $packageIds)
            ->selectRaw('package_id, count(distinct flashcard_id) as reviewed_count, sum(points) as total_points')
            ->groupBy('package_id')
            ->get()
            ->keyBy('package_id');

        $pointSummaries = StudentFlashcardPoint::query()
            ->where('student_id', $student->id)
            ->whereIn('package_id', $packageIds)
            ->selectRaw('package_id, sum(total_points) as total_points, sum(cards_studied) as cards_studied, sum(correct_answers) as correct_answers')
            ->groupBy('package_id')
            ->get()
            ->keyBy('package_id');

        return view('students.flashcards.index', compact('packages', 'reviewedCards', 'pointSummaries'));
    }

    public function show(Request $request, Package $package)
    {
        SaasAccess::abortIfFeatureDisabled('flashcards');

        $student = Auth::guard('student')->user();
        $package = $this->accessiblePackageQuery($student)
            ->where('packages.id', $package->id)
            ->firstOrFail();

        $package->load([
            'flashcardSets' => function ($query) {
                $query->where('status', true)
                    ->with([
                        'group:id,group_name',
                        'category:id,title',
                        'subcategory:id,title',
                        'subject:id,subject_name,ordering',
                        'topic:id,name,display_order',
                        'stopic:id,name,display_order',
                        'cards' => fn ($cardQuery) => $cardQuery->where('status', true)
                            ->with([
                                'sourceQuestion.qtype:id,type,question_type',
                                'sourceQuestion.diff:id,diff_level',
                                'sourceQuestions.qtype:id,type,question_type',
                                'sourceQuestions.diff:id,diff_level',
                                  'checks' => fn ($checkQuery) => $checkQuery->where('status', true)->orderBy('sort_order'),
                            ])
                            ->orderBy('sort_order')
                            ->orderBy('id'),
                    ])
                    ->orderByRaw('CASE WHEN display_order IS NULL OR display_order = 0 THEN 1 ELSE 0 END')
                    ->orderBy('display_order')
                    ->orderBy('title');
            },
        ]);

        app(FlashcardQuestionRotationService::class)->attachDisplayQuestions(
            $package->flashcardSets->flatMap(fn ($set) => $set->cards),
            $student->id,
            null,
            $package->organization_id
        );

        $cardIds = $package->flashcardSets
            ->flatMap(fn ($set) => $set->cards->pluck('id'))
            ->all();

        StudentActivityTracker::track(StudentActivityTracker::STUDY_CARDS_OPENED, [
            'student_id' => $student->id,
            'organization_id' => $package->organization_id,
            'package_id' => $package->id,
            'source' => 'student',
            'metadata' => [
                'package_name' => $package->name,
                'sets' => $package->flashcardSets->count(),
                'cards' => count($cardIds),
            ],
        ]);

        $reviews = FlashcardReview::query()
            ->where('student_id', $student->id)
            ->whereIn('flashcard_id', $cardIds)
            ->latest('reviewed_at')
            ->get()
            ->keyBy('flashcard_id');

        $progressByCard = StudentFlashcardProgress::query()
            ->where('student_id', $student->id)
            ->whereIn('flashcard_id', $cardIds)
            ->get()
            ->keyBy('flashcard_id');

        $courseProgress = StudentFlashcardCourseProgress::where('student_id', $student->id)->where('package_id', $package->id)->first();

        $pointSummary = StudentFlashcardPoint::query()
            ->where('student_id', $student->id)
            ->where('package_id', $package->id)
            ->selectRaw('sum(total_points) as total_points, sum(cards_studied) as cards_studied, sum(correct_answers) as correct_answers')
            ->first();

        if ($request->query('leaderboard_period', 'year') === 'all') {
            $leaderboard = StudentFlashcardPoint::query()
                ->with('student:id,name,email,photo')
                ->where('package_id', $package->id)
                ->selectRaw('student_id, sum(total_points) as total_points, sum(cards_studied) as cards_studied, sum(correct_answers) as correct_answers, max(last_activity_at) as last_activity_at')
                ->groupBy('student_id');
        } else {
            $leaderboard = StudentFlashcardPointEvent::query()
                ->with('student:id,name,email,photo')
                ->where('package_id', $package->id)
                ->whereYear('occurred_at', now()->year)
                ->selectRaw('student_id, sum(points) as total_points, sum(cards_studied) as cards_studied, sum(correct_answers) as correct_answers, max(occurred_at) as last_activity_at')
                ->groupBy('student_id');
        }

        $leaderboard = $leaderboard
            ->orderByDesc('total_points')
            ->orderByDesc('correct_answers')
            ->orderByDesc('cards_studied')
            ->limit(10)
            ->get();

        $studyIndexHierarchy = FlashcardStudyHierarchy::build($package->flashcardSets);
        $studyFilter = [
            'section' => $request->query('study_section') ?: null,
            'topic' => $request->query('study_topic') ?: null,
            'subtopic' => $request->query('study_subtopic') ?: null,
        ];
        $hasStudyFilter = collect($studyFilter)->filter(fn ($value) => filled($value))->isNotEmpty();
        $activeStudySets = $hasStudyFilter
            ? FlashcardStudyHierarchy::filterSets($package->flashcardSets, $studyFilter['section'], $studyFilter['topic'], $studyFilter['subtopic'])
            : collect();
        $studyHierarchy = $hasStudyFilter ? FlashcardStudyHierarchy::build($activeStudySets) : collect();
        $activeStudyTitle = $hasStudyFilter
            ? FlashcardStudyHierarchy::selectionTitle($studyIndexHierarchy, $studyFilter['section'], $studyFilter['topic'], $studyFilter['subtopic'])
            : null;

        return view('students.flashcards.show', compact('package', 'reviews', 'progressByCard', 'courseProgress', 'pointSummary', 'leaderboard', 'studyHierarchy', 'studyIndexHierarchy', 'studyFilter', 'hasStudyFilter', 'activeStudyTitle'));
    }

    public function leaderboard(Request $request, Package $package)
    {
        SaasAccess::abortIfFeatureDisabled('flashcards');
        $student = Auth::guard('student')->user();
        $package = $this->accessiblePackageQuery($student)
            ->where('packages.id', $package->id)
            ->firstOrFail();

        $leaders = $this->flashcardLeaderboardQuery($package, $request->query('leaderboard_period', 'year'));

        return view('students.flashcards.partials.leaderboard_rows', compact('leaders'));
    }

    private function flashcardLeaderboardQuery(Package $package, string $period)
    {
        if ($period === 'all') {
            $query = StudentFlashcardPoint::query()
                ->with('student:id,name,email,photo')
                ->where('package_id', $package->id)
                ->selectRaw('student_id, sum(total_points) as total_points, sum(cards_studied) as cards_studied, sum(correct_answers) as correct_answers')
                ->groupBy('student_id');
        } else {
            $query = StudentFlashcardPointEvent::query()
                ->with('student:id,name,email,photo')
                ->where('package_id', $package->id)
                ->whereYear('occurred_at', now()->year)
                ->selectRaw('student_id, sum(points) as total_points, sum(cards_studied) as cards_studied, sum(correct_answers) as correct_answers')
                ->groupBy('student_id');
        }

        return $query->orderByDesc('total_points')
            ->orderByDesc('correct_answers')
            ->orderByDesc('cards_studied')
            ->limit(10)
            ->get();
    }
    public function review(Request $request, Flashcard $flashcard)
    {
        SaasAccess::abortIfFeatureDisabled('flashcards');

        $data = $request->validate([
            'response' => ['required', 'in:again,hard,good,easy'],
        ]);

        $flashcard->load('set.package');
        $student = Auth::guard('student')->user();
        $package = $flashcard->set?->package;

        abort_if(! $package || ! $this->studentCanAccessPackage($student, $package), 404);

        $points = [
            'again' => 0,
            'hard' => 5,
            'good' => 10,
            'easy' => 15,
        ][$data['response']];

        FlashcardReview::create([
            'organization_id' => $this->tenantId(),
            'student_id' => $student->id,
            'package_id' => $package->id,
            'flashcard_id' => $flashcard->id,
            'response' => $data['response'],
            'points' => $points,
            'reviewed_at' => now(),
        ]);

        $this->recordProgress($student, $flashcard, 'confidence', [
            'confidence' => $data['response'],
        ]);

        return redirect()->back()->with('success', 'Study card progress saved.');
    }

    public function track(Request $request, Flashcard $flashcard)
    {
        SaasAccess::abortIfFeatureDisabled('flashcards');

        $data = $request->validate([
            'event' => ['required', 'in:view,answer'],
            'correct' => ['nullable', 'boolean'],
            'question_id' => ['nullable', 'integer'],
        ]);

        $flashcard->load(['set.package', 'sourceQuestion', 'sourceQuestions']);
        $student = Auth::guard('student')->user();
        $package = $flashcard->set?->package;

        abort_if(! $package || ! $this->studentCanAccessPackage($student, $package), 404);

        $result = $this->recordProgress($student, $flashcard, $data['event'], [
            'correct' => (bool) ($data['correct'] ?? false),
        ]);

        if ($data['event'] === 'answer') {
            $questionId = ! empty($data['question_id']) ? (int) $data['question_id'] : null;

            if ($questionId) {
                app(FlashcardQuestionRotationService::class)->recordAttempt(
                    $flashcard,
                    $questionId,
                    (bool) ($data['correct'] ?? false),
                    $student->id,
                    null,
                    $package->organization_id
                );
            }

            $this->trackStudyCardAnswer($student, $flashcard, (bool) ($data['correct'] ?? false), $questionId);
        }

        return response()->json($result);
    }

    public function report(Request $request, Flashcard $flashcard)
    {
        SaasAccess::abortIfFeatureDisabled('flashcards');

        $data = $request->validate([
            'message' => ['nullable', 'string', 'max:800'],
            'report_type' => ['nullable', 'string', 'max:80'],
        ]);

        $flashcard->load(['set.package', 'set.subject', 'sourceQuestion', 'sourceQuestions']);

        $student = Auth::guard('student')->user();
        $set = $flashcard->set;
        $package = $set?->package;

        abort_if(! $student || ! $set || ! $package || ! $this->studentCanAccessPackage($student, $package), 404);

        $question = $flashcard->sourceQuestion ?: $flashcard->sourceQuestions->first();

        QuestionsReport::create([
            'organization_id' => $this->tenantId(),
            'student_id' => $student->id,
            'question_id' => $question?->id,
            'flashcard_id' => $flashcard->id,
            'flashcard_set_id' => $set->id,
            'report_source' => 'flashcard',
            'subject_id' => $question?->subject_id ?: $set?->subject_id,
            'question_type' => $data['report_type'] ?: 'Study Card Issue',
            'message' => $data['message'] ?? '',
            'status' => 'Pending',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Study card report submitted.',
        ]);
    }

    private function accessiblePackageQuery($student)
    {
        $ownedPackageIds = Order::query()
            ->when($this->tenantId(), function ($query, $tenantId) {
                $query->where(function ($query) use ($tenantId) {
                    $query->where('organization_id', $tenantId)
                        ->orWhereNull('organization_id');
                });
            })
            ->where('student_id', $student->id)
            ->where('status', 'completed')
            ->whereHas('items')
            ->with('items:id,order_id,package_id')
            ->get()
            ->flatMap(fn ($order) => $order->items->pluck('package_id'))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $studentGroupIds = $student->groups()->pluck('groups.id')->all();

        return Package::query()
            ->where('status', 1)
            ->where('flashcards_enabled', true)
            ->when($this->tenantId(), fn ($query, $tenantId) => $query->where('organization_id', $tenantId))
            ->whereHas('flashcardSets.cards', fn ($query) => $query->where('status', true))
            ->where(function ($query) use ($ownedPackageIds, $studentGroupIds) {
                $query->whereIn('packages.id', $ownedPackageIds)
                    ->orWhere(function ($freeQuery) use ($studentGroupIds) {
                        $freeQuery->where('package_type', 'free')
                            ->whereHas('groups', fn ($groupQuery) => $groupQuery->whereIn('groups.id', $studentGroupIds));
                    });
            });
    }

    private function studentCanAccessPackage($student, Package $package): bool
    {
        return $this->accessiblePackageQuery($student)
            ->where('packages.id', $package->id)
            ->exists();
    }

    private function trackStudyCardAnswer($student, Flashcard $flashcard, bool $isCorrect, ?int $questionId = null): void
    {
        $flashcard->loadMissing('set.package');

        $set = $flashcard->set;
        $package = $set?->package;

        StudentActivityTracker::track(StudentActivityTracker::STUDY_CARD_ANSWERED, [
            'student_id' => $student->id,
            'organization_id' => $package?->organization_id ?: $this->tenantId(),
            'package_id' => $package?->id,
            'source' => 'student',
            'metadata' => [
                'flashcard_set_id' => $set?->id,
                'flashcard_id' => $flashcard->id,
                'question_id' => $questionId,
                'correct' => $isCorrect,
            ],
        ]);

        if (! $set) {
            return;
        }

        $completionKey = 'study_card_completed_' . $student->id . '_' . $set->id;

        if (! session()->has($completionKey) && $this->studentCompletedStudySet($student->id, $set->id)) {
            session([$completionKey => true]);

            StudentActivityTracker::track(StudentActivityTracker::STUDY_CARD_COMPLETED, [
                'student_id' => $student->id,
                'organization_id' => $package?->organization_id ?: $this->tenantId(),
                'package_id' => $package?->id,
                'source' => 'student',
                'metadata' => [
                    'flashcard_set_id' => $set->id,
                    'flashcard_id' => $flashcard->id,
                ],
            ]);
        }
    }

    private function studentCompletedStudySet(int $studentId, int $setId): bool
    {
        $activeCardIds = Flashcard::query()
            ->where('flashcard_set_id', $setId)
            ->where('status', true)
            ->pluck('id');

        if ($activeCardIds->isEmpty()) {
            return false;
        }

        $reviewed = StudentFlashcardProgress::query()
            ->where('student_id', $studentId)
            ->whereIn('flashcard_id', $activeCardIds)
            ->whereNotNull('viewed_at')
            ->count();

        return $reviewed >= $activeCardIds->count();
    }

    private function recordProgress($student, Flashcard $flashcard, string $event, array $payload = []): array
    {
        $flashcard->loadMissing('set.package');

        $set = $flashcard->set;
        $package = $set?->package;
        $now = now();

        if (! $set) {
            return ['points_awarded' => 0, 'total_points' => 0];
        }

        $progress = StudentFlashcardProgress::firstOrCreate(
            [
                'student_id' => $student->id,
                'flashcard_id' => $flashcard->id,
            ],
            [
                'package_id' => $package?->id,
                'flashcard_set_id' => $set->id,
                'points_earned' => 0,
            ]
        );

        $pointsAwarded = 0;
        $cardsStudiedDelta = 0;
        $correctAnswersDelta = 0;

        if ($event === 'view' && ! $progress->viewed_at) {
            $progress->viewed_at = $now;
            $pointsAwarded = 1;
            $cardsStudiedDelta = 1;
        }

        if ($event === 'answer') {
            $isCorrect = (bool) ($payload['correct'] ?? false);
            $progress->last_answer_correct = $isCorrect;

            if (! $progress->viewed_at) {
                $progress->viewed_at = $now;
                $pointsAwarded += 1;
                $cardsStudiedDelta = 1;
            }

            if ($isCorrect) {
                $progress->correct_attempts = (int) $progress->correct_attempts + 1;

                if (! $progress->correct_answered_at) {
                    $progress->correct_answered_at = $now;
                    $pointsAwarded += ((int) $progress->wrong_attempts > 0) ? 3 : 2;
                    $correctAnswersDelta = 1;
                }
            } else {
                $progress->wrong_attempts = (int) $progress->wrong_attempts + 1;
            }
        }

        if ($event === 'confidence') {
            $confidence = (string) ($payload['confidence'] ?? '');
            $progress->confidence = $confidence;

            if (in_array($confidence, ['good', 'easy'], true) && ! $progress->confidence_awarded) {
                $pointsAwarded = 1;
                $progress->confidence_awarded = true;
            }
        }

        // Keep every study-card award in multiples of five (1 => 5, 2 => 10, 5 => 25).

        $pointsAwarded *= 5;


        if ($pointsAwarded > 0) {
            $progress->points_earned = (int) $progress->points_earned + $pointsAwarded;
        }

        $progress->package_id = $package?->id;
        $progress->flashcard_set_id = $set->id;
        $progress->last_interaction_at = $now;
        $progress->save();

        StudentFlashcardCourseProgress::updateOrCreate(
            ['student_id' => $student->id, 'package_id' => $package?->id],
            ['current_flashcard_set_id' => $set->id, 'current_flashcard_id' => $flashcard->id, 'last_studied_at' => $now]
        );

        $summary = StudentFlashcardPoint::firstOrCreate(
            [
                'student_id' => $student->id,
                'flashcard_set_id' => $set->id,
            ],
            [
                'package_id' => $package?->id,
                'total_points' => 0,
                'cards_studied' => 0,
                'correct_answers' => 0,
            ]
        );

        if ($pointsAwarded > 0 || $cardsStudiedDelta > 0 || $correctAnswersDelta > 0) {
            $summary->package_id = $package?->id;
            $summary->total_points = (int) $summary->total_points + $pointsAwarded;
            $summary->cards_studied = (int) $summary->cards_studied + $cardsStudiedDelta;
            $summary->correct_answers = (int) $summary->correct_answers + $correctAnswersDelta;
            $summary->last_activity_at = $now;
            $summary->save();

            if ($pointsAwarded > 0) {
                StudentFlashcardPointEvent::create([
                    'student_id' => $student->id,
                    'package_id' => $package?->id,
                    'flashcard_set_id' => $set->id,
                    'flashcard_id' => $flashcard->id,
                    'points' => $pointsAwarded,
                    'cards_studied' => $cardsStudiedDelta,
                    'correct_answers' => $correctAnswersDelta,
                    'occurred_at' => $now,
                ]);
            }
        }

        return [
            'points_awarded' => $pointsAwarded,
            'total_points' => (int) $summary->total_points,
            'cards_studied' => (int) $summary->cards_studied,
            'correct_answers' => (int) $summary->correct_answers,
        ];
    }
}

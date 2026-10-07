<?php

namespace App\Http\Controllers;

use App\Models\StudentActivityEvent;
use App\Models\ExamResult;
use App\Models\StudentFlashcardPoint;
use App\Services\StudentActivityTracker;
use App\Support\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class StudentFunnelReportController extends Controller
{
    public function index(Request $request)
    {
        $tenantId = $this->tenantId();
        $dateFrom = $request->filled('date_from')
            ? Carbon::parse($request->date_from)->startOfDay()
            : now()->subDays(30)->startOfDay();
        $dateTo = $request->filled('date_to')
            ? Carbon::parse($request->date_to)->endOfDay()
            : now()->endOfDay();
        $source = $request->input('source', 'all');
        $traffic = in_array($request->input('traffic', 'human'), ['human', 'bot', 'all'], true)
            ? $request->input('traffic', 'human')
            : 'human';
        $perPageOptions = [50, 100, 500];
        $guestPerPage = $this->perPage($request->input('guest_per_page', 50), $perPageOptions);
        $activityPerPage = $this->perPage($request->input('activity_per_page', 50), $perPageOptions);
        $pdfPerPage = $this->perPage($request->input('pdf_per_page', 50), $perPageOptions);
        $guestContactPerPage = $this->perPage($request->input('guest_contact_per_page', 50), $perPageOptions);

        $allActivityQuery = StudentActivityEvent::query()
            ->when($tenantId, fn ($query) => $query->where('organization_id', $tenantId))
            ->whereBetween('occurred_at', [$dateFrom, $dateTo])
            ->when($source !== 'all', fn ($query) => $query->where('source', $source));

        $botEventsDetected = (clone $allActivityQuery)->where('is_bot', true)->count();
        $baseQuery = $this->applyTrafficFilter(clone $allActivityQuery, $traffic, 'is_bot');

        $eventCounts = (clone $baseQuery)
            ->select(
                'event_name',
                DB::raw('COUNT(*) as total_events'),
                DB::raw('COUNT(DISTINCT student_id) as students'),
                DB::raw('COUNT(DISTINCT guest_id) as guests')
            )
            ->groupBy('event_name')
            ->get()
            ->keyBy('event_name');

        $studentSteps = [
            ['event' => 'student_registered', 'label' => 'Registered'],
            ['event' => 'student_verified', 'label' => 'Verified'],
            ['event' => 'student_logged_in', 'label' => 'Logged In'],
            ['event' => 'my_exams_opened', 'label' => 'Opened My Exams'],
            ['event' => 'exam_instructions_opened', 'label' => 'Opened Instructions'],
            ['event' => 'exam_started', 'label' => 'Started Exam'],
            ['event' => 'exam_submitted', 'label' => 'Submitted Exam'],
            ['event' => 'result_viewed', 'label' => 'Viewed Result'],
        ];

        $guestContactEvents = (clone $baseQuery)
            ->where('event_name', StudentActivityTracker::GUEST_CONTACT_SAVED)
            ->whereNotNull('exam_result_id');
        $guestContactIds = (clone $guestContactEvents)
            ->select('exam_result_id')
            ->distinct();
        $guestContactSavedAt = (clone $guestContactEvents)
            ->selectRaw('MAX(occurred_at)')
            ->whereColumn('student_activity_events.exam_result_id', 'exam_results.id');

        $savedGuestContacts = ExamResult::query()
            ->with('exam:id,name')
            ->select('exam_results.*')
            ->selectSub($guestContactSavedAt, 'contact_saved_at')
            ->whereIn('exam_results.id', $guestContactIds)
            ->where(function ($query) {
                $query->whereNotNull('guest_name')
                    ->orWhereNotNull('guest_email');
            })
            ->when($tenantId, fn ($query) => $query->where('exam_results.organization_id', $tenantId))
            ->orderByDesc('contact_saved_at')
            ->paginate($guestContactPerPage, ['*'], 'guest_contact_page')
            ->withQueryString();

        $guestSteps = [
            ['event' => 'guest_exam_instructions_opened', 'label' => 'Guest Instructions'],
            ['event' => 'guest_exam_started', 'label' => 'Guest Started Exam'],
            ['event' => 'guest_exam_submitted', 'label' => 'Guest Submitted Exam'],
            ['event' => 'guest_result_prompted', 'label' => 'Asked for Details'],
            ['event' => 'guest_contact_saved', 'label' => 'Contact Saved'],
        ];

        $studentFunnel = $this->buildFunnel($studentSteps, $eventCounts, 'students');
        $guestFunnel = $this->buildFunnel($guestSteps, $eventCounts, 'guests', [
            StudentActivityTracker::GUEST_CONTACT_SAVED => $savedGuestContacts->total(),
        ]);
        $studySteps = [
            [
                'event' => StudentActivityTracker::STUDY_CARDS_OPENED,
                'guest_event' => StudentActivityTracker::GUEST_STUDY_CARDS_OPENED,
                'label' => 'Opened Study Cards',
            ],
            [
                'event' => StudentActivityTracker::STUDY_CARD_ANSWERED,
                'guest_event' => StudentActivityTracker::GUEST_STUDY_CARD_ANSWERED,
                'label' => 'Answered Study Card',
            ],
            [
                'event' => StudentActivityTracker::STUDY_CARD_COMPLETED,
                'guest_event' => StudentActivityTracker::GUEST_STUDY_CARD_COMPLETED,
                'label' => 'Completed Study Set',
            ],
        ];
        $studyCardFunnel = $this->buildCombinedFunnel($studySteps, $eventCounts);
        $quickQuizSteps = [
            ['event' => StudentActivityTracker::QUICK_QUIZ_PROMPT_SHOWN, 'label' => 'Prompt Seen'],
            ['event' => StudentActivityTracker::QUICK_QUIZ_OPENED, 'label' => 'Opened Quick Quiz'],
            ['event' => StudentActivityTracker::QUICK_QUIZ_STARTED, 'label' => 'Started Quick Quiz'],
            ['event' => StudentActivityTracker::QUICK_QUIZ_ANSWERED, 'label' => 'Answered a Question'],
            ['event' => StudentActivityTracker::QUICK_QUIZ_COMPLETED, 'label' => 'Completed Quick Quiz'],
            ['event' => StudentActivityTracker::QUICK_QUIZ_EXPLANATION_LOGIN_SELECTED, 'label' => 'Selected Login for Explanation'],
        ];
        $quickQuizFunnel = $this->buildMetricFunnel(collect($quickQuizSteps)->map(function ($step) use ($eventCounts) {
            return [
                'label' => $step['label'],
                'count' => $this->countFor($eventCounts, $step['event'], 'students')
                    + $this->countFor($eventCounts, $step['event'], 'guests'),
            ];
        })->all());
        $quickQuizStats = [
            'students' => $this->countFor($eventCounts, StudentActivityTracker::QUICK_QUIZ_STARTED, 'students'),
            'guests' => $this->countFor($eventCounts, StudentActivityTracker::QUICK_QUIZ_STARTED, 'guests'),
            'completed' => $this->countFor($eventCounts, StudentActivityTracker::QUICK_QUIZ_COMPLETED, 'students')
                + $this->countFor($eventCounts, StudentActivityTracker::QUICK_QUIZ_COMPLETED, 'guests'),
        ];

        $studyPointQuery = StudentFlashcardPoint::query()
            ->when($tenantId, function ($query) use ($tenantId) {
                $query->whereHas('student', fn ($studentQuery) => $studentQuery->where('organization_id', $tenantId));
            })
            ->whereBetween('last_activity_at', [$dateFrom, $dateTo]);

        $studyStats = [
            'learners' => (clone $studyPointQuery)->distinct()->count('student_id'),
            'sets' => (clone $studyPointQuery)->count(),
            'cards' => (int) (clone $studyPointQuery)->sum('cards_studied'),
            'correct' => (int) (clone $studyPointQuery)->sum('correct_answers'),
            'points' => (int) (clone $studyPointQuery)->sum('total_points'),
            'guest_cards' => $this->totalEventsFor($eventCounts, StudentActivityTracker::GUEST_STUDY_CARD_ANSWERED),
            'event_learners' => (int) ($studyCardFunnel[0]['count'] ?? 0),
        ];

        $summary = [
            'registered' => $this->countFor($eventCounts, 'student_registered', 'students'),
            'started' => $this->countFor($eventCounts, 'exam_started', 'students'),
            'submitted' => $this->countFor($eventCounts, 'exam_submitted', 'students'),
            'guest_submitted' => $this->countFor($eventCounts, 'guest_exam_submitted', 'guests'),
            'quick_quiz_participants' => $quickQuizStats['students'] + $quickQuizStats['guests'],
            'study_learners' => max($studyStats['learners'], $studyStats['event_learners']),
            'study_cards' => $studyStats['cards'] + $studyStats['guest_cards'],
            'study_points' => $studyStats['points'],
            'pdf_downloads' => $this->totalEventsFor($eventCounts, StudentActivityTracker::PDF_DOWNLOADED),
        ];

        $pdfDownloads = (clone $baseQuery)
            ->where('event_name', StudentActivityTracker::PDF_DOWNLOADED)
            ->with(['student:id,name,email', 'exam:id,name', 'package:id,name'])
            ->latest('occurred_at')
            ->paginate($pdfPerPage, ['*'], 'pdf_page')
            ->withQueryString();

        $recentEvents = (clone $baseQuery)
            ->with(['student:id,name,email', 'exam:id,name'])
            ->latest('occurred_at')
            ->paginate($activityPerPage, ['*'], 'activity_page')
            ->withQueryString();

        $recentGuestAttempts = ExamResult::query()
            ->with('exam:id,name')
            ->whereNotNull('guest_id')
            ->when($tenantId, fn ($query) => $query->where('organization_id', $tenantId))
            ->whereBetween('start_time', [$dateFrom, $dateTo])
            ->when($traffic !== 'all', fn ($query) => $query->where('guest_is_bot', $traffic === 'bot'))
            ->latest('start_time')
            ->paginate($guestPerPage, ['*'], 'guest_page')
            ->withQueryString();

        return view('reports.student_funnel', [
            'studentFunnel' => $studentFunnel,
            'guestFunnel' => $guestFunnel,
            'studyCardFunnel' => $studyCardFunnel,
            'quickQuizFunnel' => $quickQuizFunnel,
            'quickQuizStats' => $quickQuizStats,
            'studyStats' => $studyStats,
            'summary' => $summary,
            'recentEvents' => $recentEvents,
            'recentGuestAttempts' => $recentGuestAttempts,
            'savedGuestContacts' => $savedGuestContacts,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'source' => $source,
            'traffic' => $traffic,
            'botEventsDetected' => $botEventsDetected,
            'perPageOptions' => $perPageOptions,
            'guestPerPage' => $guestPerPage,
            'activityPerPage' => $activityPerPage,
            'pdfPerPage' => $pdfPerPage,
            'pdfDownloads' => $pdfDownloads,
            'guestContactPerPage' => $guestContactPerPage,
        ]);
    }

    private function applyTrafficFilter($query, string $traffic, string $column)
    {
        return match ($traffic) {
            'bot' => $query->where($column, true),
            'all' => $query,
            default => $query->where($column, false),
        };
    }
    private function tenantId(): ?int
    {
        return class_exists(Tenant::class) ? Tenant::id() : null;
    }

    private function buildFunnel(array $steps, $eventCounts, string $identityColumn, array $countOverrides = []): array
    {
        $previous = null;

        return collect($steps)->map(function ($step) use ($eventCounts, $identityColumn, $countOverrides, &$previous) {
            $count = array_key_exists($step['event'], $countOverrides)
                ? (int) $countOverrides[$step['event']]
                : $this->countFor($eventCounts, $step['event'], $identityColumn);
            $conversion = $previous === null ? 100 : ($previous > 0 ? round(($count / $previous) * 100, 1) : 0);
            $dropOff = $previous === null ? 0 : max(0, $previous - $count);
            $previous = $count;

            return [
                'event' => $step['event'],
                'label' => $step['label'],
                'count' => $count,
                'conversion' => $conversion,
                'drop_off' => $dropOff,
            ];
        })->all();
    }

    private function buildMetricFunnel(array $steps): array
    {
        $previous = null;

        return collect($steps)->map(function ($step) use (&$previous) {
            $count = (int) $step['count'];
            $conversion = $previous === null ? 100 : ($previous > 0 ? round(($count / $previous) * 100, 1) : 0);
            $dropOff = $previous === null ? 0 : max(0, $previous - $count);
            $previous = $count;

            return [
                'label' => $step['label'],
                'count' => $count,
                'conversion' => $conversion,
                'drop_off' => $dropOff,
            ];
        })->all();
    }

    private function buildCombinedFunnel(array $steps, $eventCounts): array
    {
        $previous = null;

        return collect($steps)->map(function ($step) use ($eventCounts, &$previous) {
            $studentCount = $this->countFor($eventCounts, $step['event'], 'students');
            $guestCount = $this->countFor($eventCounts, $step['guest_event'], 'guests');
            $count = $studentCount + $guestCount;
            $conversion = $previous === null ? 100 : ($previous > 0 ? round(($count / $previous) * 100, 1) : 0);
            $dropOff = $previous === null ? 0 : max(0, $previous - $count);
            $previous = $count;

            return [
                'event' => $step['event'],
                'label' => $step['label'],
                'count' => $count,
                'students' => $studentCount,
                'guests' => $guestCount,
                'conversion' => $conversion,
                'drop_off' => $dropOff,
            ];
        })->all();
    }

    private function countFor($eventCounts, string $eventName, string $identityColumn): int
    {
        return (int) optional($eventCounts->get($eventName))->{$identityColumn};
    }

    private function totalEventsFor($eventCounts, string $eventName): int
    {
        return (int) optional($eventCounts->get($eventName))->total_events;
    }

    private function perPage($value, array $allowed): int
    {
        $value = (int) $value;

        return in_array($value, $allowed, true) ? $value : 50;
    }
}

<?php

namespace App\Services;

use App\Models\StudentActivityEvent;
use App\Support\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class StudentActivityTracker
{
    public const STUDENT_REGISTERED = 'student_registered';
    public const STUDENT_VERIFIED = 'student_verified';
    public const STUDENT_LOGGED_IN = 'student_logged_in';
    public const PACKAGE_ENROLLED = 'package_enrolled';
    public const PACKAGE_HIDDEN = 'package_hidden';
    public const PACKAGE_RESTORED = 'package_restored';
    public const MY_EXAMS_OPENED = 'my_exams_opened';
    public const EXAM_INSTRUCTIONS_OPENED = 'exam_instructions_opened';
    public const EXAM_STARTED = 'exam_started';
    public const EXAM_SUBMITTED = 'exam_submitted';
    public const RESULT_VIEWED = 'result_viewed';
    public const GUEST_EXAM_INSTRUCTIONS_OPENED = 'guest_exam_instructions_opened';
    public const GUEST_EXAM_STARTED = 'guest_exam_started';
    public const GUEST_EXAM_SUBMITTED = 'guest_exam_submitted';
    public const GUEST_RESULT_PROMPTED = 'guest_result_prompted';
    public const GUEST_CONTACT_SAVED = 'guest_contact_saved';
    public const STUDY_CARDS_OPENED = 'study_cards_opened';
    public const STUDY_CARD_ANSWERED = 'study_card_answered';
    public const STUDY_CARD_COMPLETED = 'study_card_completed';
    public const GUEST_STUDY_CARDS_OPENED = 'guest_study_cards_opened';
    public const GUEST_STUDY_CARD_ANSWERED = 'guest_study_card_answered';
    public const GUEST_STUDY_CARD_COMPLETED = 'guest_study_card_completed';
    public const PDF_DOWNLOADED = 'pdf_downloaded';
    public const SOLUTION_PDF_PROMPTED = 'solution_pdf_prompted';
    public const SOLUTION_PDF_LOGIN_SELECTED = 'solution_pdf_login_selected';
    public const QUICK_QUIZ_PROMPT_SHOWN = 'quick_quiz_prompt_shown';
    public const QUICK_QUIZ_OPENED = 'quick_quiz_opened';
    public const QUICK_QUIZ_DISMISSED = 'quick_quiz_dismissed';
    public const QUICK_QUIZ_STARTED = 'quick_quiz_started';
    public const QUICK_QUIZ_ANSWERED = 'quick_quiz_answered';
    public const QUICK_QUIZ_COMPLETED = 'quick_quiz_completed';
    public const QUICK_QUIZ_EXPLANATION_PROMPTED = 'quick_quiz_explanation_prompted';
    public const QUICK_QUIZ_EXPLANATION_LOGIN_SELECTED = 'quick_quiz_explanation_login_selected';

    public static function trackPdfDownload(array $context = [], ?Request $request = null): void
    {
        $request ??= request();
        $studentId = $context['student_id'] ?? Auth::guard('student')->id();
        $guestId = $context['guest_id'] ?? session('guest_id') ?? $request->cookie('guest_id');

        if (! $studentId && ! Auth::check() && ! $guestId) {
            $guestId = (string) Str::uuid();
            $request->session()->put('guest_id', $guestId);
        }

        $context['student_id'] = $studentId;
        $context['guest_id'] = $guestId;
        $context['source'] = $context['source'] ?? ($studentId ? 'student' : (Auth::check() ? 'admin' : 'guest'));
        $context['metadata'] = array_merge(['document_type' => 'pdf'], $context['metadata'] ?? []);

        self::track(self::PDF_DOWNLOADED, $context, $request);
    }

    public static function track(string $eventName, array $context = [], ?Request $request = null): void
    {
        try {
            $request ??= request();
            $studentId = $context['student_id'] ?? Auth::guard('student')->id();
            $guestId = $context['guest_id'] ?? session('guest_id') ?? $request->cookie('guest_id');
            $organizationId = $context['organization_id'] ?? self::tenantId($request);
            $metadata = $context['metadata'] ?? [];
            $bot = ($studentId || Auth::check())
                ? ['is_bot' => false, 'name' => null, 'reason' => null]
                : BotTrafficDetector::detect($request);

            $knownKeys = ['organization_id', 'student_id', 'guest_id', 'exam_id', 'package_id', 'order_id', 'exam_result_id', 'source', 'metadata'];
            foreach (array_diff_key($context, array_flip($knownKeys)) as $key => $value) {
                $metadata[$key] = $value;
            }

            $routeName = optional($request->route())->getName();
            if ($routeName) {
                $metadata['route'] = $routeName;
            }
            if ($bot['is_bot']) {
                $metadata['bot_name'] = $bot['name'];
                $metadata['bot_reason'] = $bot['reason'];
            }

            $identity = [
                'student_id' => $studentId,
                'guest_id' => $guestId,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ];

            if (self::isDuplicate($eventName, $organizationId, $context, $identity, $bot['is_bot'])) {
                return;
            }

            StudentActivityEvent::create([
                'organization_id' => $organizationId,
                'student_id' => $studentId,
                'guest_id' => $guestId,
                'exam_id' => $context['exam_id'] ?? null,
                'package_id' => $context['package_id'] ?? null,
                'order_id' => $context['order_id'] ?? null,
                'exam_result_id' => $context['exam_result_id'] ?? null,
                'event_name' => $eventName,
                'source' => $context['source'] ?? ($studentId ? 'student' : ($guestId ? 'guest' : 'web')),
                'url' => $request->fullUrl(),
                'referrer' => $request->headers->get('referer'),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'is_bot' => $bot['is_bot'],
                'bot_name' => $bot['name'],
                'bot_reason' => $bot['reason'],
                'metadata' => $metadata ?: null,
                'occurred_at' => now(),
            ]);
        } catch (\Throwable $exception) {
            Log::warning('Student activity tracking failed', ['event_name' => $eventName, 'message' => $exception->getMessage()]);
        }
    }

    private static function isDuplicate(string $eventName, ?int $organizationId, array $context, array $identity, bool $isBot): bool
    {
        $minutes = $isBot ? 60 : match ($eventName) {
            self::PDF_DOWNLOADED => 10,
            self::SOLUTION_PDF_PROMPTED, self::SOLUTION_PDF_LOGIN_SELECTED => 3,
            self::QUICK_QUIZ_PROMPT_SHOWN, self::QUICK_QUIZ_OPENED, self::QUICK_QUIZ_DISMISSED,
            self::QUICK_QUIZ_EXPLANATION_PROMPTED, self::QUICK_QUIZ_EXPLANATION_LOGIN_SELECTED => 3,
            self::MY_EXAMS_OPENED, self::EXAM_INSTRUCTIONS_OPENED, self::GUEST_EXAM_INSTRUCTIONS_OPENED, self::GUEST_EXAM_STARTED,
            self::STUDY_CARDS_OPENED, self::GUEST_STUDY_CARDS_OPENED => 3,
            default => 0,
        };

        if ($minutes === 0) {
            return false;
        }

        $query = StudentActivityEvent::query()
            ->where('event_name', $eventName)
            ->where('occurred_at', '>=', now()->subMinutes($minutes))
            ->when($organizationId, fn ($q) => $q->where('organization_id', $organizationId))
            ->when($context['exam_id'] ?? null, fn ($q, $id) => $q->where('exam_id', $id))
            ->when($context['package_id'] ?? null, fn ($q, $id) => $q->where('package_id', $id))
            ->when($context['exam_result_id'] ?? null, fn ($q, $id) => $q->where('exam_result_id', $id));

        if ($identity['student_id']) {
            $query->where('student_id', $identity['student_id']);
        } elseif ($identity['guest_id'] && ! $isBot) {
            $query->where('guest_id', $identity['guest_id']);
        } else {
            $query->where('ip_address', $identity['ip_address'])->where('user_agent', $identity['user_agent']);
        }

        return $query->exists();
    }

    private static function tenantId(Request $request): ?int
    {
        return class_exists(Tenant::class) ? Tenant::hostId($request->getHost()) : null;
    }
}

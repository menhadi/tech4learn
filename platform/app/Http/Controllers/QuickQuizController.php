<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Stopic;
use App\Models\Group;
use App\Models\Topic;
use App\Models\Question;
use App\Models\Package;
use App\Models\QuickQuizAnswer;
use App\Models\QuickQuizSession;
use App\Models\Subject;
use App\Services\QuestionAnswerEvaluator;
use App\Services\QuickQuizQuestionPoolService;
use App\Services\StudentActivityTracker;
use App\Support\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class QuickQuizController extends Controller
{
    public function __construct(
        private QuickQuizQuestionPoolService $questionPool,
        private QuestionAnswerEvaluator $evaluator
    ) {
    }

    public function options(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'group_id' => ['required', 'integer'],
            'category_id' => ['nullable', 'integer'],
            'subject_id' => ['nullable', 'integer'],
            'topic_id' => ['nullable', 'integer'],
            'package_id' => ['nullable', 'integer'],
            'pyp_only' => ['nullable', 'boolean'],
        ]);
        $tenantId = $this->tenantId($request);
        $group = Group::query()
            ->when($tenantId, fn ($query, $id) => $query->where('organization_id', $id))
            ->findOrFail($validated['group_id']);
        $this->validatePackageScope($validated, $group->id, $tenantId);
        $cacheKey = 'quick_quiz_options:v4:'.implode(':', [
            $tenantId ?: 'global',
            $group->id,
            $validated['category_id'] ?? 0,
            $validated['subject_id'] ?? 0,
            $validated['topic_id'] ?? 0,
            $validated['package_id'] ?? 0,
            ! empty($validated['pyp_only']) ? 1 : 0,
        ]);
        $cached = Cache::get($cacheKey);
        if (is_array($cached)) {
            return response()->json($cached);
        }

        $filters = array_filter([
            'group_id' => $group->id,
            'category_id' => $validated['category_id'] ?? null,
            'subject_id' => $validated['subject_id'] ?? null,
            'topic_id' => $validated['topic_id'] ?? null,
            'package_id' => $validated['package_id'] ?? null,
            'pyp_only' => ! empty($validated['pyp_only']),
        ]);
        $response = [
            'group' => ['id' => $group->id, 'label' => $this->displayText($group->group_name)],
        ];

        if (empty($validated['subject_id'])) {
            if (empty($validated['category_id'])) {
                $categoryIds = $this->questionPool->categoryIdsForGroup($group->id);
                $response['categories'] = Category::query()
                    ->when($tenantId, fn ($query, $id) => $query->where('organization_id', $id))
                    ->where('status', 1)
                    ->whereIn('id', $categoryIds)
                    ->get(['id', 'title'])
                    ->map(fn ($category) => [
                        'id' => $category->id,
                        'label' => $this->displayText($category->title),
                    ])
                    ->sortBy('label', SORT_NATURAL | SORT_FLAG_CASE)
                    ->values();
            }

            $subjectIds = $this->questionPool->dimensionIds('subject_id', $filters, $tenantId);
            $response['subjects'] = Subject::query()
                ->whereIn('id', $subjectIds)
                ->get(['id', 'subject_name'])
                ->map(fn ($subject) => [
                    'id' => $subject->id,
                    'label' => $this->displayText($subject->subject_name),
                ])
                ->sortBy('label', SORT_NATURAL | SORT_FLAG_CASE)
                ->values();
        } elseif (empty($validated['topic_id'])) {
            $topicIds = $this->questionPool->dimensionIds('topic_id', $filters, $tenantId);
            $response['topics'] = Topic::query()
                ->whereIn('id', $topicIds)
                ->get(['id', 'name'])
                ->map(fn ($topic) => [
                    'id' => $topic->id,
                    'label' => $this->displayText($topic->name),
                ])
                ->sortBy('label', SORT_NATURAL | SORT_FLAG_CASE)
                ->values();
        } else {
            $subtopicIds = $this->questionPool->dimensionIds('stopic_id', $filters, $tenantId);
            $response['subtopics'] = Stopic::query()
                ->whereIn('id', $subtopicIds)
                ->get(['id', 'name'])
                ->map(fn ($subtopic) => [
                    'id' => $subtopic->id,
                    'label' => $this->displayText($subtopic->name),
                ])
                ->sortBy('label', SORT_NATURAL | SORT_FLAG_CASE)
                ->values();
        }

        Cache::put($cacheKey, $response, now()->addMinutes(15));

        return response()->json($response);
    }

    public function start(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'group_id' => ['required', 'integer'],
            'subject_id' => ['nullable', 'integer'],
            'category_id' => ['nullable', 'integer'],
            'topic_id' => ['nullable', 'integer'],
            'stopic_id' => ['nullable', 'integer'],
            'package_id' => ['nullable', 'integer'],
            'pyp_only' => ['nullable', 'boolean'],
            'question_count' => ['required', 'integer', Rule::in([5, 10])],
            'source' => ['nullable', 'string', 'max:40'],
        ]);
        $tenantId = $this->tenantId($request);
        $group = Group::query()
            ->when($tenantId, fn ($query, $id) => $query->where('organization_id', $id))
            ->findOrFail($validated['group_id']);
        $this->validatePackageScope($validated, $group->id, $tenantId);


        $questions = $this->questionPool->pick($validated, $tenantId, (int) $validated['question_count']);
        if ($questions->count() < (int) $validated['question_count']) {
            return response()->json([
                'message' => "Only {$questions->count()} questions match this selection. Try fewer filters or choose 5 questions.",
                'available_count' => $questions->count(),
            ], 422);
        }

        $guestId = $this->guestId($request);
        $studentId = Auth::guard('student')->id();
        $session = QuickQuizSession::create([
            'public_id' => (string) Str::uuid(),
            'organization_id' => $tenantId,
            'student_id' => $studentId,
            'guest_id' => $studentId ? null : $guestId,
            'group_id' => $group->id,
            'category_id' => $validated['category_id'] ?? null,
            'subcategory_id' => $validated['subcategory_id'] ?? null,
            'package_id' => $validated['package_id'] ?? null,
            'subject_id' => $validated['subject_id'] ?? null,
            'question_ids' => $questions->pluck('id')->values()->all(),
            'question_count' => $questions->count(),
            'status' => 'started',
            'source' => $validated['source'] ?? 'homepage',
            'started_at' => now(),
        ]);

        StudentActivityTracker::track(StudentActivityTracker::QUICK_QUIZ_STARTED, [
            'organization_id' => $tenantId,
            'student_id' => $studentId,
            'guest_id' => $studentId ? null : $guestId,
            'package_id' => $session->package_id,
            'source' => $studentId ? 'student' : 'guest',
            'metadata' => $this->trackingMetadata($session),
        ], $request);

        return response()->json([
            'session_id' => $session->public_id,
            'question' => $this->questionResource($questions->first(), 0, $session->question_count),
            'progress' => $this->progressResource($session),
        ]);
    }

    private function validatePackageScope(array $validated, int $groupId, ?int $tenantId): void
    {
        if (empty($validated['package_id'])) {
            return;
        }

        Package::query()
            ->where('status', 1)
            ->when($tenantId, fn ($query, $id) => $query->where('organization_id', $id))
            ->whereHas('groups', fn ($query) => $query->where('groups.id', $groupId))
            ->findOrFail((int) $validated['package_id']);
    }

    public function show(Request $request, string $publicId): JsonResponse
    {
        $session = $this->accessibleSession($request, $publicId);
        $answeredIds = $session->answers()->pluck('question_id');
        $nextId = collect($session->question_ids)->first(fn ($id) => ! $answeredIds->contains($id));
        $question = $nextId ? Question::with(['qtype', 'subject', 'topic'])->find($nextId) : null;

        return response()->json([
            'session_id' => $session->public_id,
            'question' => $question
                ? $this->questionResource($question, array_search($question->id, $session->question_ids, true), $session->question_count)
                : null,
            'progress' => $this->progressResource($session),
            'completed' => $session->status === 'completed',
            'summary' => $session->status === 'completed' ? $this->summaryResource($session) : null,
        ]);
    }

    public function history(Request $request): JsonResponse
    {
        $studentId = Auth::guard('student')->id();
        if (! $studentId) {
            return response()->json(['authenticated' => false, 'sessions' => []]);
        }

        $sessions = QuickQuizSession::query()
            ->with('group:id,group_name')
            ->where('student_id', $studentId)
            ->when($this->tenantId($request), fn ($query, $id) => $query->where('organization_id', $id))
            ->latest('started_at')
            ->limit(10)
            ->get()
            ->map(fn (QuickQuizSession $session) => [
                'session_id' => $session->public_id,
                'group' => $this->displayText($session->group?->group_name) ?: 'Quick quiz',
                'status' => $session->status,
                'answered' => $session->answered_count,
                'correct' => $session->correct_count,
                'total' => $session->question_count,
                'score_percent' => $this->scorePercent($session),
                'taken_at' => optional($session->completed_at ?? $session->started_at)->toIso8601String(),
                'taken_label' => optional($session->completed_at ?? $session->started_at)?->diffForHumans(),
            ])
            ->values();

        return response()->json(['authenticated' => true, 'sessions' => $sessions]);
    }

    public function answer(Request $request, string $publicId): JsonResponse
    {
        $validated = $request->validate([
            'question_id' => ['required', 'integer'],
            'selected_options' => ['nullable', 'array', 'max:6'],
            'selected_options.*' => ['integer', 'between:1,6'],
            'answer' => ['nullable', 'string', 'max:2000'],
        ]);
        $session = $this->accessibleSession($request, $publicId);
        abort_unless(in_array((int) $validated['question_id'], array_map('intval', $session->question_ids), true), 404);

        $question = Question::with(['qtype', 'subject', 'topic'])->findOrFail($validated['question_id']);
        $existing = $session->answers()->where('question_id', $question->id)->first();
        $type = $this->evaluator->questionType($question);
        $payload = [
            'selected_options' => collect($validated['selected_options'] ?? [])->map(fn ($value) => (int) $value)->unique()->sort()->values()->all(),
            'answer' => trim((string) ($validated['answer'] ?? '')),
            'type' => $type,
        ];

        if (! $existing) {
            $stat = (object) [
                'answered' => true,
                'selected_option_indices' => $payload['selected_options'],
                'true_false' => $payload['answer'],
                'answer' => $payload['answer'],
                'correct_answer' => $this->evaluator->correctAnswerSnapshot($question),
            ];
            $isCorrect = $this->evaluator->isCorrect($question, $stat);

            $existing = QuickQuizAnswer::create([
                'quick_quiz_session_id' => $session->id,
                'question_id' => $question->id,
                'answer_payload' => $payload,
                'correct_answer' => $this->evaluator->correctAnswerSnapshot($question),
                'is_correct' => $isCorrect,
                'answered_at' => now(),
            ]);

            $session = DB::transaction(function () use ($session) {
                $locked = QuickQuizSession::query()->lockForUpdate()->findOrFail($session->id);
                $locked->answered_count = $locked->answers()->count();
                $locked->correct_count = $locked->answers()->where('is_correct', true)->count();
                if ($locked->answered_count >= $locked->question_count) {
                    $locked->status = 'completed';
                    $locked->completed_at = now();
                }
                $locked->save();

                return $locked->fresh();
            });

            StudentActivityTracker::track(StudentActivityTracker::QUICK_QUIZ_ANSWERED, [
                'organization_id' => $session->organization_id,
                'student_id' => $session->student_id,
                'guest_id' => $session->guest_id,
                'package_id' => $session->package_id,
                'source' => $session->student_id ? 'student' : 'guest',
                'metadata' => array_merge($this->trackingMetadata($session), [
                    'question_id' => $question->id,
                    'is_correct' => $isCorrect,
                    'position' => array_search($question->id, $session->question_ids, true) + 1,
                ]),
            ], $request);

            if ($session->status === 'completed') {
                StudentActivityTracker::track(StudentActivityTracker::QUICK_QUIZ_COMPLETED, [
                    'organization_id' => $session->organization_id,
                    'student_id' => $session->student_id,
                    'guest_id' => $session->guest_id,
                    'package_id' => $session->package_id,
                    'source' => $session->student_id ? 'student' : 'guest',
                    'metadata' => array_merge($this->trackingMetadata($session), [
                        'correct_count' => $session->correct_count,
                        'score_percent' => $this->scorePercent($session),
                    ]),
                ], $request);
            }
        }

        $position = array_search($question->id, $session->question_ids, true);
        $nextId = $session->question_ids[$position + 1] ?? null;
        $nextQuestion = $nextId ? Question::with(['qtype', 'subject', 'topic'])->find($nextId) : null;

        return response()->json([
            'correct' => (bool) $existing->is_correct,
            'correct_options' => $this->evaluator->correctOptionIndices($question),
            'correct_answer' => $this->correctAnswerLabel($question),
            'explanation' => null,
            'explanation_locked' => true,
            'next_question' => $nextQuestion
                ? $this->questionResource($nextQuestion, $position + 1, $session->question_count)
                : null,
            'progress' => $this->progressResource($session),
            'completed' => $session->status === 'completed',
            'summary' => $session->status === 'completed' ? $this->summaryResource($session) : null,
        ]);
    }

    public function explanation(Request $request, string $publicId, Question $question): JsonResponse
    {
        if (! Auth::guard('student')->check() && ! Auth::check()) {
            return response()->json(['message' => 'Please log in to view explanations.'], 401);
        }

        $session = $this->accessibleSession($request, $publicId);
        abort_unless(in_array((int) $question->id, array_map('intval', $session->question_ids), true), 404);
        abort_unless($session->status === 'completed', 403, 'Explanations are available after completing the quiz.');
        abort_unless($session->answers()->where('question_id', $question->id)->exists(), 403);

        return response()->json([
            'question_id' => $question->id,
            'explanation' => $question->explanation ?: 'A detailed explanation is not available for this question yet.',
        ]);
    }

    public function activity(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'action' => ['required', Rule::in(['prompt_shown', 'opened', 'dismissed', 'explanation_prompted', 'explanation_login_selected'])],
            'session_id' => ['nullable', 'uuid'],
            'question_id' => ['nullable', 'integer'],
            'source' => ['nullable', 'string', 'max:40'],
        ]);
        $events = [
            'prompt_shown' => StudentActivityTracker::QUICK_QUIZ_PROMPT_SHOWN,
            'opened' => StudentActivityTracker::QUICK_QUIZ_OPENED,
            'dismissed' => StudentActivityTracker::QUICK_QUIZ_DISMISSED,
            'explanation_prompted' => StudentActivityTracker::QUICK_QUIZ_EXPLANATION_PROMPTED,
            'explanation_login_selected' => StudentActivityTracker::QUICK_QUIZ_EXPLANATION_LOGIN_SELECTED,
        ];
        $guestId = $this->guestId($request);

        StudentActivityTracker::track($events[$validated['action']], [
            'organization_id' => $this->tenantId($request),
            'student_id' => Auth::guard('student')->id(),
            'guest_id' => Auth::guard('student')->check() ? null : $guestId,
            'source' => Auth::guard('student')->check() ? 'student' : 'guest',
            'metadata' => [
                'quiz_session_id' => $validated['session_id'] ?? null,
                'question_id' => $validated['question_id'] ?? null,
                'placement' => $validated['source'] ?? 'homepage',
            ],
        ], $request);

        return response()->json(['success' => true]);
    }

    private function accessibleSession(Request $request, string $publicId): QuickQuizSession
    {
        $studentId = Auth::guard('student')->id();
        $guestId = session('guest_id') ?? $request->cookie('guest_id');
        $session = QuickQuizSession::query()
            ->where('public_id', $publicId)
            ->when($this->tenantId($request), fn ($query, $id) => $query->where('organization_id', $id))
            ->where(function ($query) use ($studentId, $guestId) {
                if ($studentId) {
                    $query->where('student_id', $studentId);
                    if ($guestId) {
                        $query->orWhere(fn ($q) => $q->whereNull('student_id')->where('guest_id', $guestId));
                    }
                } elseif ($guestId) {
                    $query->where('guest_id', $guestId);
                } else {
                    $query->whereRaw('1 = 0');
                }
            })
            ->firstOrFail();

        if ($studentId && ! $session->student_id) {
            $session->forceFill(['student_id' => $studentId, 'guest_id' => null])->save();
        }

        return $session;
    }

    private function questionResource(Question $question, int $position, int $total): array
    {
        $type = $this->evaluator->questionType($question);
        $options = collect(range(1, 6))
            ->map(fn ($index) => ['index' => $index, 'label' => chr(64 + $index), 'html' => $question->optionValue($index)])
            ->filter(fn ($option) => filled($option['html']))
            ->values();

        if ($type === 'true_false' && $options->isEmpty()) {
            $options = collect([
                ['index' => 1, 'label' => 'T', 'html' => 'True', 'value' => 'True'],
                ['index' => 2, 'label' => 'F', 'html' => 'False', 'value' => 'False'],
            ]);
        }

        return [
            'id' => $question->id,
            'position' => $position + 1,
            'total' => $total,
            'html' => $question->question,
            'type' => $type,
            'multiple' => $type === 'multiple_choice_checkbox',
            'options' => $options,
            'subject' => $question->subject?->subject_name,
            'topic' => $question->topic?->name,
        ];
    }

    private function progressResource(QuickQuizSession $session): array
    {
        return [
            'answered' => $session->answered_count,
            'total' => $session->question_count,
            'correct' => $session->correct_count,
            'percent' => $session->question_count > 0
                ? round(($session->answered_count / $session->question_count) * 100)
                : 0,
        ];
    }

    private function correctAnswerLabel(Question $question): ?string
    {
        $type = $this->evaluator->questionType($question);
        if (in_array($type, ['multiple_choice_radio', 'multiple_choice_checkbox'], true)) {
            return collect($this->evaluator->correctOptionIndices($question))
                ->map(fn ($index) => 'Option '.chr(64 + (int) $index))
                ->implode(', ');
        }

        return $this->evaluator->correctAnswerSnapshot($question);
    }

    private function summaryResource(QuickQuizSession $session): array
    {
        $isAuthenticated = Auth::guard('student')->check();

        return [
            'correct' => $session->correct_count,
            'wrong' => max(0, $session->answered_count - $session->correct_count),
            'total' => $session->question_count,
            'score_percent' => $this->scorePercent($session),
            'is_authenticated' => $isAuthenticated,
            'review' => $isAuthenticated && $session->status === 'completed' ? $this->reviewResource($session) : [],
            'practice_builder_url' => $isAuthenticated ? route('student.practice-builder.index') : null,
            'login_url' => $isAuthenticated ? null : route('student.signin', [
                'redirect' => route('student.quick-quizzes', ['quick_quiz' => $session->public_id]),
                'action' => 'quick_quiz',
                'group_id' => $session->group_id,
                'quick_quiz_session_id' => $session->public_id,
            ]),
        ];
    }

    private function reviewResource(QuickQuizSession $session): array
    {
        $answers = $session->answers()
            ->with(['question.qtype', 'question.subject', 'question.topic'])
            ->get()
            ->keyBy('question_id');

        return collect($session->question_ids)
            ->map(function ($questionId, $index) use ($answers) {
                $answer = $answers->get($questionId);
                $question = $answer?->question;
                if (! $answer || ! $question) {
                    return null;
                }

                return [
                    'question_id' => $question->id,
                    'position' => $index + 1,
                    'question' => $question->question,
                    'subject' => $question->subject?->subject_name,
                    'topic' => $question->topic?->name,
                    'is_correct' => (bool) $answer->is_correct,
                    'your_answer' => $this->submittedAnswerLabel($answer),
                    'correct_answer' => $this->correctAnswerLabel($question),
                    'explanation' => $question->explanation ?: 'A detailed explanation is not available for this question yet.',
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    private function submittedAnswerLabel(QuickQuizAnswer $answer): string
    {
        $payload = $answer->answer_payload ?? [];
        $selected = collect($payload['selected_options'] ?? [])
            ->map(fn ($index) => 'Option '.chr(64 + (int) $index))
            ->implode(', ');

        return $selected ?: (trim((string) ($payload['answer'] ?? '')) ?: 'No answer');
    }

    private function trackingMetadata(QuickQuizSession $session): array
    {
        return [
            'quiz_session_id' => $session->public_id,
            'group_id' => $session->group_id,
            'category_id' => $session->category_id,
            'subcategory_id' => $session->subcategory_id,
            'subject_id' => $session->subject_id,
            'question_count' => $session->question_count,
            'placement' => $session->source,
        ];
    }

    private function scorePercent(QuickQuizSession $session): int
    {
        return $session->question_count > 0
            ? (int) round(($session->correct_count / $session->question_count) * 100)
            : 0;
    }


    private function guestId(Request $request): string
    {
        $guestId = (string) (session('guest_id') ?? $request->cookie('guest_id') ?? Str::uuid());
        $request->session()->put('guest_id', $guestId);
        Cookie::queue(cookie('guest_id', $guestId, 60 * 24 * 365, '/', null, $request->isSecure(), true, false, 'Lax'));

        return $guestId;
    }

    private function tenantId(Request $request): ?int
    {
        return class_exists(Tenant::class) ? Tenant::hostId($request->getHost()) : null;
    }

    private function displayText($value): string
    {
        if (is_array($value)) {
            return (string) ($value['en'] ?? reset($value) ?: '');
        }

        if (is_string($value) && str_starts_with(trim($value), '{')) {
            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                return (string) ($decoded['en'] ?? reset($decoded) ?: $value);
            }
        }

        return (string) ($value ?? '');
    }
}

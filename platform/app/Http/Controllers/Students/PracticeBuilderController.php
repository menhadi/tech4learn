<?php

namespace App\Http\Controllers\Students;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Diff;
use App\Models\Exam;
use App\Models\ExamResult;
use App\Models\Group;
use App\Models\Question;
use App\Models\Qtype;
use App\Models\Stopic;
use App\Models\Subject;
use App\Models\Topic;
use App\Support\CategoryHierarchy;
use App\Support\Tenant;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class PracticeBuilderController extends Controller
{
    private function tenantId(): ?int
    {
        return class_exists(Tenant::class) ? Tenant::hostId(request()->getHost()) : null;
    }

    public function index(Request $request)
    {
        $student = Auth::guard('student')->user();
        $tenantId = $this->tenantId() ?: $student->organization_id;
        $studentGroupIds = $student->groups()
            ->when($tenantId, fn ($q) => $q->where('groups.organization_id', $tenantId))
            ->pluck('groups.id')
            ->map(fn ($id) => (int) $id)
            ->values();

        $groups = Group::query()
            ->whereIn('id', $studentGroupIds)
            ->orderBy('group_name')
            ->get(['id', 'group_name']);

        $subjects = Subject::query()
            ->whereHas('groups', fn ($q) => $q->whereIn('groups.id', $studentGroupIds))
            ->with('groups:id')
            ->orderBy('subject_name')
            ->get(['id', 'subject_name']);

        $topics = Topic::query()
            ->whereHas('subject.groups', fn ($q) => $q->whereIn('groups.id', $studentGroupIds))
            ->orderBy('name')
            ->get(['id', 'subject_id', 'name']);

        $subtopics = Stopic::query()
            ->whereHas('subject.groups', fn ($q) => $q->whereIn('groups.id', $studentGroupIds))
            ->orderBy('name')
            ->get(['id', 'subject_id', 'topic_id', 'name']);

        $parentCategories = Category::query()
            ->with(['groups:id', 'children' => fn ($query) => $query->orderBy('title')])
            ->when($tenantId, fn ($query) => $query->where('organization_id', $tenantId))
            ->whereNull('parent_id')
            ->where(function ($query) use ($studentGroupIds) {
                $query->whereDoesntHave('groups')
                    ->orWhereHas('groups', fn ($groupQuery) => $groupQuery->whereIn('groups.id', $studentGroupIds));
            })
            ->orderBy('title')
            ->get();

        $questionTypes = Qtype::displayOrdered(['id', 'question_type']);
        $difficulties = Diff::query()->orderBy('diff_level')->get(['id', 'diff_level']);

        $recentPracticeExams = Exam::query()
            ->where('created_by_student_id', $student->id)
            ->where('is_student_practice', true)
            ->withCount('questions')
            ->latest()
            ->take(10)
            ->get();

        $practiceResultSummary = collect();
        $latestPracticeResult = null;

        if ($recentPracticeExams->isNotEmpty()) {
            $practiceResults = ExamResult::query()
                ->where('student_id', $student->id)
                ->whereIn('exam_id', $recentPracticeExams->pluck('id'))
                ->whereNotNull('end_time')
                ->latest()
                ->get();

            $practiceResultSummary = $practiceResults
                ->groupBy('exam_id')
                ->map(function ($results) {
                    return [
                        'latest' => $results->first(),
                        'attempts' => $results->count(),
                        'best_percent' => $results->max('percent'),
                    ];
                });

            $latestPracticeResult = $practiceResults->first();
        }

        $practiceSuggestions = $this->practiceSuggestions($latestPracticeResult, $recentPracticeExams->count());

        return view('students.practice_builder.index', compact(
            'groups',
            'parentCategories',
            'subjects',
            'topics',
            'subtopics',
            'questionTypes',
            'difficulties',
            'recentPracticeExams',
            'practiceResultSummary',
            'practiceSuggestions'
        ));
    }

    public function store(Request $request)
    {
        $student = Auth::guard('student')->user();
        $tenantId = $this->tenantId() ?: $student->organization_id;
        $studentGroupIds = $student->groups()
            ->when($tenantId, fn ($q) => $q->where('groups.organization_id', $tenantId))
            ->pluck('groups.id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        $validated = $request->validate([
            'group_id' => ['required', 'integer', Rule::in($studentGroupIds)],
            'category_level_1' => ['nullable', 'integer'],
            'category_level_2' => ['nullable', 'integer'],
            'subject_id' => ['nullable', 'integer'],
            'topic_id' => ['nullable', 'integer'],
            'stopic_id' => ['nullable', 'integer'],
            'qtype_id' => ['nullable', 'integer'],
            'diff_id' => ['nullable', 'integer'],
            'question_count' => ['required', 'integer', Rule::in([5, 10, 20, 25, 50, 100])],
            'duration' => ['required', 'integer', 'min:5', 'max:240'],
        ]);

        $group = Group::whereIn('id', $studentGroupIds)->findOrFail($validated['group_id']);
        $categoryId = ! empty($validated['category_level_1']) ? (int) $validated['category_level_1'] : null;
        $subcategoryId = subcategories_enabled() && ! empty($validated['category_level_2']) ? (int) $validated['category_level_2'] : null;

        if ($tenantId && $categoryId) {
            CategoryHierarchy::validate($categoryId, $subcategoryId, [(int) $group->id], (int) $tenantId);
        } elseif ($subcategoryId) {
            return back()->withErrors(['category_level_1' => 'Select a category before selecting a subcategory.'])->withInput();
        }
        $selectedGroupName = mb_strtolower(trim((string) $group->group_name));
        $matchingGroupIds = Group::query()
            ->when($tenantId, fn ($query) => $query->where('organization_id', $tenantId))
            ->get(['id', 'group_name'])
            ->filter(fn ($candidate) => mb_strtolower(trim((string) $candidate->group_name)) === $selectedGroupName)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        if (empty($matchingGroupIds)) {
            $matchingGroupIds = [(int) $validated['group_id']];
        }

        $requestedCount = (int) $validated['question_count'];
        $optionalFilterKeys = ['subject_id', 'topic_id', 'stopic_id', 'qtype_id', 'diff_id'];
        $hasOptionalFilters = collect($optionalFilterKeys)->contains(fn ($key) => filled($validated[$key] ?? null));
        $filtersRelaxed = false;

        $buildQuestionsQuery = function (bool $includeOptionalFilters = true) use ($validated, $matchingGroupIds, $categoryId, $subcategoryId) {
            return Question::query()
                ->where(function ($query) use ($matchingGroupIds) {
                    $query->whereHas('groups', fn ($q) => $q->whereIn('groups.id', $matchingGroupIds))
                        ->orWhereHas('exams.groups', fn ($q) => $q->whereIn('groups.id', $matchingGroupIds))
                        ->orWhereHas('exams.packages.groups', fn ($q) => $q->whereIn('groups.id', $matchingGroupIds));
                })
                ->when($categoryId, function ($query, $categoryId) {
                    $query->where(function ($categoryScope) use ($categoryId) {
                        $categoryScope->whereHas('exams', fn ($examQuery) => $examQuery->where('exams.category_level_1', $categoryId))
                            ->orWhereHas('exams.packages', fn ($packageQuery) => $packageQuery->where('packages.category_level_1', $categoryId));
                    });
                })
                ->when($subcategoryId, function ($query, $subcategoryId) {
                    $query->where(function ($subcategoryScope) use ($subcategoryId) {
                        $subcategoryScope->whereHas('exams', fn ($examQuery) => $examQuery->where('exams.category_level_2', $subcategoryId))
                            ->orWhereHas('exams.packages', fn ($packageQuery) => $packageQuery->where('packages.category_level_2', $subcategoryId));
                    });
                })
                ->when($includeOptionalFilters && ($validated['subject_id'] ?? null), fn ($q, $subjectId) => $q->where('subject_id', $subjectId))
                ->when($includeOptionalFilters && ($validated['topic_id'] ?? null), fn ($q, $topicId) => $q->where('topic_id', $topicId))
                ->when($includeOptionalFilters && ($validated['stopic_id'] ?? null), fn ($q, $stopicId) => $q->where('stopic_id', $stopicId))
                ->when($includeOptionalFilters && ($validated['qtype_id'] ?? null), fn ($q, $qtypeId) => $q->where('qtype_id', $qtypeId))
                ->when($includeOptionalFilters && ($validated['diff_id'] ?? null), fn ($q, $diffId) => $q->where('diff_id', $diffId));
        };

        $questionsQuery = $buildQuestionsQuery(true);
        $availableCount = (clone $questionsQuery)->count();
        if ($availableCount < $requestedCount && $hasOptionalFilters) {
            $groupOnlyQuery = $buildQuestionsQuery(false);
            $groupOnlyCount = (clone $groupOnlyQuery)->count();

            if ($groupOnlyCount >= $requestedCount) {
                $questionsQuery = $groupOnlyQuery;
                $availableCount = $groupOnlyCount;
                $filtersRelaxed = true;
            }
        }

        if ($availableCount < $requestedCount) {
            Log::warning('Practice builder found too few questions', [
                'student_id' => $student->id,
                'student_email' => $student->email,
                'selected_group_id' => $validated['group_id'],
                'matching_group_ids' => $matchingGroupIds,
                'requested_count' => $requestedCount,
                'available_count' => $availableCount,
                'filters' => [
                    'category_level_1' => $categoryId,
                    'category_level_2' => $subcategoryId,
                    'subject_id' => $validated['subject_id'] ?? null,
                    'topic_id' => $validated['topic_id'] ?? null,
                    'stopic_id' => $validated['stopic_id'] ?? null,
                    'qtype_id' => $validated['qtype_id'] ?? null,
                    'diff_id' => $validated['diff_id'] ?? null,
                ],
                'diagnostic_counts' => [
                    'direct' => Question::whereHas('groups', fn ($q) => $q->whereIn('groups.id', $matchingGroupIds))->count(),
                    'exam_groups' => Question::whereHas('exams.groups', fn ($q) => $q->whereIn('groups.id', $matchingGroupIds))->count(),
                    'package_groups' => Question::whereHas('exams.packages.groups', fn ($q) => $q->whereIn('groups.id', $matchingGroupIds))->count(),
                    'subject_groups' => Question::whereHas('subject.groups', fn ($q) => $q->whereIn('groups.id', $matchingGroupIds))->count(),
                ],
            ]);

            return back()
                ->withInput()
                ->with('error', "Only {$availableCount} questions match your filters. Please reduce the count or choose wider filters.");
        }

        $usedQuestionIds = DB::table('exam_questions')
            ->join('exams', 'exams.id', '=', 'exam_questions.exam_id')
            ->where('exams.created_by_student_id', $student->id)
            ->where('exams.is_student_practice', true)
            ->pluck('exam_questions.question_id')
            ->unique()
            ->values();

        $freshQuestionsQuery = clone $questionsQuery;
        if ($usedQuestionIds->isNotEmpty()) {
            $freshQuestionsQuery->whereNotIn('questions.id', $usedQuestionIds->all());
        }

        $freshCount = (clone $freshQuestionsQuery)->count();
        if ($freshCount >= $requestedCount) {
            $questionsQuery = $freshQuestionsQuery;
        } elseif ($freshCount > 0) {
            return back()
                ->withInput()
                ->with('error', "Only {$freshCount} unused questions remain for these filters. Please reduce the count to {$freshCount} or choose wider filters. Previously used questions will repeat only after this pool is exhausted.");
        }

        $questions = $questionsQuery
            ->inRandomOrder()
            ->limit($requestedCount)
            ->get(['id', 'negative_marks']);

        $exam = DB::transaction(function () use ($student, $tenantId, $validated, $questions, $group, $categoryId, $subcategoryId) {
            $name = $this->practiceExamName($group, $validated);

            $exam = Exam::create([
                'organization_id' => $tenantId,
                'created_by_student_id' => $student->id,
                'is_student_practice' => true,
                'name' => $name,
                'slug' => $this->uniquePracticeSlug($name),
                'category_level_1' => $categoryId,
                'category_level_2' => $subcategoryId,
                'passing_percentage' => 0,
                'instruction' => 'This is your personal practice test generated from available questions. Complete it like a regular exam and review your result after submission.',
                'duration' => $validated['duration'],
                'attempt_count' => 0,
                'start_date' => Carbon::now()->subMinute(),
                'end_date' => Carbon::now()->addDays(30),
                'show_answer_sheet' => true,
                'negative_marking' => $questions->contains(fn ($question) => (float) $question->negative_marks > 0),
                'random_question' => false,
                'result_after_finish' => true,
                'mode' => 'Preparation',
                'instant_result' => true,
                'option_shuffle' => false,
                'multi_language' => true,
                'math_editor' => false,
                'browser_tolerance' => false,
                'proctor' => false,
                'calculator_allowed' => false,
                'tolerance_count' => 0,
                'status' => 'Active',
            ]);

            $exam->questions()->sync($questions->pluck('id')->all());
            $exam->groups()->sync([$group->id]);

            return $exam;
        });

        return redirect()
            ->route('student.practice-builder.index')
            ->with('created_practice_exam_id', $exam->id)
            ->with('success', $filtersRelaxed
                ? 'Practice test created. Some optional filters were too narrow, so questions were selected from the full group pool. Click Start when you are ready.'
                : 'Practice test created. Click Start when you are ready.');
    }

    private function uniquePracticeSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'practice-test';
        $slug = $base;
        $counter = 2;

        while (Exam::where('slug', $slug)->exists()) {
            $slug = $base . '-' . $counter;
            $counter++;
        }

        return $slug;
    }

    private function practiceExamName(Group $group, array $validated): string
    {
        $parts = [
            'Practice Test',
            $group->group_name,
        ];

        if (! empty($validated['category_level_1'])) {
            $parts[] = optional(Category::find($validated['category_level_1']))->title;
        }

        if (subcategories_enabled() && ! empty($validated['category_level_2'])) {
            $parts[] = optional(Category::find($validated['category_level_2']))->title;
        }

        if (! empty($validated['subject_id'])) {
            $parts[] = optional(Subject::find($validated['subject_id']))->subject_name;
        }

        if (! empty($validated['topic_id'])) {
            $parts[] = optional(Topic::find($validated['topic_id']))->name;
        }

        if (! empty($validated['stopic_id'])) {
            $parts[] = optional(Stopic::find($validated['stopic_id']))->name;
        }

        $name = collect($parts)
            ->filter(fn ($part) => filled($part))
            ->map(fn ($part) => trim((string) $part))
            ->implode(' - ');

        return Str::limit($name, 180, '');
    }

    private function practiceSuggestions(?ExamResult $latestPracticeResult, int $practiceCount): array
    {
        if (! $latestPracticeResult) {
            return [
                [
                    'title' => $practiceCount > 0 ? 'Finish your latest practice test' : 'Start with a short test',
                    'text' => $practiceCount > 0
                        ? 'Complete one pending practice test first, then use the result here to choose your next step.'
                        : 'Create a 20-question test from your main group. Use subject or topic filters only when you want focused practice.',
                ],
                [
                    'title' => 'Use wider filters first',
                    'text' => 'A wider question pool gives a better mixed practice set. Narrow it later when you know the weak area.',
                ],
            ];
        }

        $percent = (float) ($latestPracticeResult->percent ?? 0);

        if ($percent < 40) {
            return [
                [
                    'title' => 'Repeat the weak area',
                    'text' => 'Your latest practice score is below 40%. Create a smaller topic-wise test and review each wrong answer.',
                ],
                [
                    'title' => 'Keep the test short',
                    'text' => 'Use 10 or 20 questions until accuracy improves, then move to longer practice tests.',
                ],
            ];
        }

        if ($percent >= 70) {
            return [
                [
                    'title' => 'Increase the challenge',
                    'text' => 'Your latest practice score is strong. Try a longer test or choose a harder difficulty filter.',
                ],
                [
                    'title' => 'Track speed now',
                    'text' => 'Focus on time per question and avoid careless mistakes in repeated practice.',
                ],
            ];
        }

        return [
            [
                'title' => 'Build consistency',
                'text' => 'Your latest practice score is in the middle range. Take another focused test from the same group.',
            ],
            [
                'title' => 'Review before repeating',
                'text' => 'Open the latest result, check wrong answers, then create the next practice test with a narrower topic.',
            ],
        ];
    }
}

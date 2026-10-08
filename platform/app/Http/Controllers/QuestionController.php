<?php

namespace App\Http\Controllers;

use App\Models\Question;
use App\Models\QuestionTag;
use App\Models\QuestionSection;
use App\Models\Flashcard;
use App\Models\FlashcardSet;
use App\Models\Package;
use App\Models\Qtype;
use App\Models\Subject;
use App\Models\Topic;
use App\Models\Stopic;
use App\Models\Diff;
use App\Models\Passage;
use App\Models\Group;
use App\Models\Language;
use App\Models\QuestionLang;
use App\Models\PassageLang;
use App\Models\Exam;
use App\Models\Category;

use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Schema;
use App\Http\Controllers\AITranslationController;
use App\Support\SaasAccess;
use App\Services\CurriculumTaxonomyService;

class QuestionController extends Controller
{
    private function tenantId(): ?int
    {
        return class_exists(\App\Support\Tenant::class) ? \App\Support\Tenant::id() : null;
    }

    private function tenantSubjects()
    {
        return Subject::query()->where('organization_id', (int) \App\Support\Tenant::id());

    }

    private function tenantSections()
    {
        return QuestionSection::query()->where('organization_id', (int) \App\Support\Tenant::id());
    }

    private function tenantTopics()
    {
        return Topic::query()->whereHas('group', fn ($q) => $q->where('organization_id', (int) \App\Support\Tenant::id()));

    }

    private function tenantStopics()
    {
        return Stopic::query()->whereHas('group', fn ($q) => $q->where('organization_id', (int) \App\Support\Tenant::id()));

    }

    private function tenantPassages()
    {
        return Passage::query()->when($this->tenantId(), function ($q, $tenantId) {
            $q->where('organization_id', $tenantId);
        });
    }

    private function tenantQuestionTags()
    {
        $tenantId = $this->tenantId();

        return QuestionTag::query()->where(function ($query) use ($tenantId) {
            $query->whereNull('organization_id')
                ->orWhere('organization_id', $tenantId);
        });
    }

    private function ensureTenantOwns($model): void
    {
        if ((int) ($model->organization_id ?? 0) !== (int) \App\Support\Tenant::id()) {
            abort(404);
        }
    }

    private function safeInternalReturnUrl(?string $returnUrl): ?string
    {
        if (! $returnUrl || ! str_starts_with($returnUrl, '/') || str_starts_with($returnUrl, '//')) {
            return null;
        }

        $parts = parse_url($returnUrl);
        if ($parts === false || isset($parts['scheme']) || isset($parts['host'])) {
            return null;
        }

        return url($returnUrl);
    }

    private function validateTenantRelations(Request $request): void
    {
        if ($request->filled('question_section_id')) {
            $section = $this->tenantSections()->findOrFail($request->question_section_id);
            $selectedGroups = collect((array) $request->input('group_ids', []))->map(fn ($id) => (int) $id);
            abort_unless($section->groups()->whereIn('groups.id', $selectedGroups)->exists(), 422, 'The selected section is not linked to the selected group.');
        }

        if ($request->filled('passage_id')) {
            $this->tenantPassages()->findOrFail($request->passage_id);
        }
        if ($request->filled('language_id')) {
            Language::enabledForOrganization($this->tenantId())->findOrFail($request->language_id);
        }

        app(CurriculumTaxonomyService::class)->validateSelection(
            (int) $this->tenantId(),
            (array) $request->input('group_ids', []),
            $request->filled('subject_id') ? (int) $request->subject_id : null,
            $request->filled('topic_id') ? (int) $request->topic_id : null,
            $request->filled('stopic_id') ? (int) $request->stopic_id : null,
        );
    }
    private function validateTenantQuestionTags(Request $request): void
    {
        $tagIds = collect($request->input('tag_ids', []))
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        if ($tagIds->isEmpty()) {
            return;
        }

        $allowedCount = $this->tenantQuestionTags()
            ->whereIn('id', $tagIds)
            ->count();

        if ($allowedCount !== $tagIds->count()) {
            abort(422, 'Invalid question tag selected.');
        }
    }

    public function index(Request $request)
    {
        if (! subcategories_enabled()) {
            $request->query->remove('subcategory');
        }

        $tenantId = \App\Support\Tenant::id();
        $query = Question::query()->where('organization_id', $tenantId);
        $groupIds = getUserGroupIds();

        if (!empty($groupIds)) {
            $query->whereHas('groups', function ($q) use ($groupIds) {
                $q->whereIn('group_id', $groupIds);
            });
        }

        if ($request->filled('group')) {

            $groupId = $request->group;

            $query->whereHas('groups', function ($q) use ($groupId) {
                $q->where('group_id', $groupId);
            });
        }

        if ($request->filled('package')) {

            $query->whereHas('exams.packages', function ($q) use ($request) {
                $q->where('packages.id', $request->package);
            });

        }

        if ($request->filled('category')) {
            $categoryId = $request->input('category');
            $query->whereHas('exams', function ($q) use ($categoryId) {
                $q->where('category_level_1', $categoryId)
                    ->orWhereHas('packages', function ($packageQuery) use ($categoryId) {
                        $packageQuery->where('category_level_1', $categoryId);
                    });
            });
        }

        if ($request->filled('subcategory')) {
            $subcategoryId = $request->input('subcategory');
            $query->whereHas('exams', function ($q) use ($subcategoryId) {
                $q->where('category_level_2', $subcategoryId)
                    ->orWhereHas('packages', function ($packageQuery) use ($subcategoryId) {
                        $packageQuery->where('category_level_2', $subcategoryId);
                    });
            });
        }

        if ($request->filled('search')) {
            $query->where('question', 'like', '%' . $request->input('search') . '%');
        }

        if ($request->filled('subject')) {
            $query->where('subject_id', $request->input('subject'));
        }

        if ($request->filled('topic')) {
            $query->where('topic_id', $request->input('topic'));
        }

        if ($request->filled('exam')) {
            $query->whereHas('exams', function ($q) use ($request) {
                // $q->where('exam_id', $request->input('exam'));
                $q->where('exams.id', $request->input('exam'));
            });
        }

        if ($request->input('exam_assignment') === 'assigned') {
            $query->whereHas('exams');
        } elseif ($request->input('exam_assignment') === 'unassigned') {
            $query->whereDoesntHave('exams');
        }

        if ($request->filled('subtopic')) {
            $query->where('stopic_id', $request->input('subtopic'));
        }

        if ($request->filled('qtype')) {
            $query->where('qtype_id', $request->input('qtype'));
        }

        if ($request->filled('diff')) {
            $query->where('diff_id', $request->input('diff'));
        }

        if ($request->filled('language')) {
            $query->whereHas('langs', function ($translation) use ($request) {
                $translation->where('language_id', $request->input('language'));
            });
        }

        if ($request->filled('tag')) {
            $query->whereHas('tags', function ($tagQuery) use ($request) {
                $tagQuery->where('question_tags.id', $request->input('tag'));
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('marks_min')) {
            $query->where('marks', '>=', $request->input('marks_min'));
        }

        if ($request->filled('marks_max')) {
            $query->where('marks', '<=', $request->input('marks_max'));
        }

        if ($request->filled('negative_marks_min')) {
            $query->where('negative_marks', '>=', $request->input('negative_marks_min'));
        }

        if ($request->filled('negative_marks_max')) {
            $query->where('negative_marks', '<=', $request->input('negative_marks_max'));
        }

        if ($request->filled('has_image')) {
            $imageFilter = $request->input('has_image');
            $imageMatch = function ($q) {
                $q->where('question', 'like', '%<img%')
                    ->orWhere('question', 'like', '%.png%')
                    ->orWhere('question', 'like', '%.jpg%')
                    ->orWhere('question', 'like', '%.jpeg%')
                    ->orWhere('question', 'like', '%.webp%')
                    ->orWhere('question', 'like', '%.gif%');
            };

            if ($imageFilter === 'yes') {
                $query->where($imageMatch);
            } elseif ($imageFilter === 'no') {
                $query->where(function ($q) {
                    $q->where('question', 'not like', '%<img%')
                        ->where('question', 'not like', '%.png%')
                        ->where('question', 'not like', '%.jpg%')
                        ->where('question', 'not like', '%.jpeg%')
                        ->where('question', 'not like', '%.webp%')
                        ->where('question', 'not like', '%.gif%');
                });
            }
        }

        if ($request->filled('has_passage')) {
            if ($request->input('has_passage') === 'yes') {
                $query->whereNotNull('passage_id');
            } elseif ($request->input('has_passage') === 'no') {
                $query->whereNull('passage_id');
            }
        }

        if ($request->filled('ai_generated') && Schema::hasColumn('questions', 'ai_generated')) {
            if ($request->input('ai_generated') === 'yes') {
                $query->whereNotNull('ai_generated')->where('ai_generated', '!=', '');
            } elseif ($request->input('ai_generated') === 'no') {
                $query->where(function ($q) {
                    $q->whereNull('ai_generated')->orWhere('ai_generated', '');
                });
            }
        }

        $perPage = (int) $request->input('per_page', 50);
        if (! in_array($perPage, [50, 100, 500], true)) {
            $perPage = 50;
        }
        $questions = $query
            ->select([
                'questions.id',
                'questions.organization_id',
                'questions.question_code',
                'questions.subject_id',
                'questions.topic_id',
                'questions.stopic_id',
                'questions.qtype_id',
                'questions.diff_id',
                'questions.passage_id',
                'questions.question',
                'questions.marks',
                'questions.negative_marks',
                'questions.status',
                'questions.created_at',
            ])
            ->withCount('exams')
            ->with('subject:id,subject_name', 'topic:id,name', 'stopic:id,name', 'qtype:id,question_type', 'diff:id,diff_level', 'langs.language:id,name,code', 'groups:id,group_name', 'tags:id,name')
            ->orderByDesc('questions.created_at')
            ->orderByDesc('questions.id')
            ->simplePaginate($perPage)
            ->withQueryString();

        // AJAX pagination/search replaces only the table. Returning here avoids
        // rebuilding every large filter collection on each page request.
        if ($request->ajax() || $request->expectsJson()) {
            return view('questions.partials.list', compact('questions', 'perPage'));
        }

        $subjects = $this->tenantSubjects()->orderBy('subject_name')->get();
        $topics = $this->tenantTopics()->orderBy('name')->get();
        $stopics = $this->tenantStopics()->orderBy('name')->get();
        $qtypes = Qtype::displayOrdered();
        $diffs = Diff::orderBy('diff_level')->get();
        $languages = Language::enabledForOrganization($this->tenantId())->orderBy('name')->get();
        $questionTags = $this->tenantQuestionTags()->where('status', true)->orderBy('name')->get();
        $categoryIdsForGroup = collect();

        if ($request->filled('group')) {
            $groupIdForCategories = $request->integer('group');
            $examCategoryIds = Exam::where('organization_id', $tenantId)
                ->where(function ($query) use ($groupIdForCategories) {
                    $query->whereHas('groups', fn ($groupQuery) => $groupQuery->where('groups.id', $groupIdForCategories))
                        ->orWhereHas('packages.groups', fn ($packageGroupQuery) => $packageGroupQuery->where('groups.id', $groupIdForCategories));
                })
                ->get(['category_level_1', 'category_level_2'])
                ->flatMap(fn ($exam) => [$exam->category_level_1, $exam->category_level_2]);
            $packageCategoryIds = Package::where('organization_id', $tenantId)
                ->where(function ($query) use ($groupIdForCategories) {
                    $query->whereHas('groups', fn ($groupQuery) => $groupQuery->where('groups.id', $groupIdForCategories))
                        ->orWhereHas('exams.groups', fn ($examGroupQuery) => $examGroupQuery->where('groups.id', $groupIdForCategories));
                })
                ->get(['category_level_1', 'category_level_2'])
                ->flatMap(fn ($package) => [$package->category_level_1, $package->category_level_2]);
            $categoryIdsForGroup = $examCategoryIds->merge($packageCategoryIds)->filter()->unique()->values();
        }

        $parentCategories = Category::where('organization_id', $tenantId)
            ->whereNull('parent_id')
            ->where('status', 1)
            ->when($request->filled('group'), fn ($query) => $query->whereIn('id', $categoryIdsForGroup))
            ->orderBy('title')
            ->get();
        $childCategories = Category::where('organization_id', $tenantId)
            ->whereNotNull('parent_id')
            ->where('status', 1)
            ->when($request->filled('group'), fn ($query) => $query->whereIn('id', $categoryIdsForGroup))
            ->when($request->filled('category'), fn ($query) => $query->where('parent_id', $request->category))
            ->orderBy('title')
            ->get();

        $allExamGroups = Group::where('organization_id', $tenantId)
            ->whereHas('exams', fn ($examQuery) => $examQuery->where('exams.organization_id', $tenantId))
            ->select('groups.id', 'groups.group_name')
            ->orderBy('groups.group_name')
            ->get();

        $allExamPackages = Package::where('organization_id', $tenantId)
        ->when($request->filled('group'), function ($query) use ($request) {
            $query->whereHas('groups', function ($groupQuery) use ($request) {
                $groupQuery->where('groups.id', $request->group);
            });
        })
        ->when($request->filled('category'), function ($query) use ($request) {
            $query->where(function ($categoryQuery) use ($request) {
                $categoryQuery->where('category_level_1', $request->category)
                    ->orWhereHas('exams', function ($examQuery) use ($request) {
                        $examQuery->where('category_level_1', $request->category);
                    });
            });
        })
        ->when($request->filled('subcategory'), function ($query) use ($request) {
            $query->where(function ($subcategoryQuery) use ($request) {
                $subcategoryQuery->where('category_level_2', $request->subcategory)
                    ->orWhereHas('exams', function ($examQuery) use ($request) {
                        $examQuery->where('category_level_2', $request->subcategory);
                    });
            });
        })
        ->select('id', 'name')
        ->orderBy('name')
        ->get();

        $examsQuestions = Exam::where('organization_id', $tenantId)
        ->when($request->filled('group'), function ($query) use ($request) {
            $query->whereHas('groups', function ($groupQuery) use ($request) {
                $groupQuery->where('groups.id', $request->group);
            });
        })
        ->when($request->filled('category'), function ($query) use ($request) {
            $query->where(function ($categoryQuery) use ($request) {
                $categoryQuery->where('category_level_1', $request->category)
                    ->orWhereHas('packages', function ($packageQuery) use ($request) {
                        $packageQuery->where('category_level_1', $request->category);
                    });
            });
        })
        ->when($request->filled('subcategory'), function ($query) use ($request) {
            $query->where(function ($subcategoryQuery) use ($request) {
                $subcategoryQuery->where('category_level_2', $request->subcategory)
                    ->orWhereHas('packages', function ($packageQuery) use ($request) {
                        $packageQuery->where('category_level_2', $request->subcategory);
                    });
            });
        })
        ->when($request->filled('package'), function ($query) use ($request) {
            $query->whereHas('packages', function ($packageQuery) use ($request) {
                $packageQuery->where('packages.id', $request->package);
            });
        })
        ->select('id', 'name')
        ->orderBy('name')
        ->get();

        $selectedGroup = $request->group ?? null;
        $selectedPackage = $request->package ?? null;
        $selectedExam = $request->exam ?? null;
        $selectedCategory = $request->category ?? null;
        $selectedSubcategory = $request->subcategory ?? null;
        $advancedFilterKeys = [
            'category',
            'subcategory',
            'package',
            'exam',
            'exam_assignment',
            'subject',
            'topic',
            'subtopic',
            'qtype',
            'diff',
            'language',
            'status',
            'tag',
            'marks_min',
            'marks_max',
            'negative_marks_min',
            'negative_marks_max',
            'has_image',
            'has_passage',
            'ai_generated',
        ];
        $advancedFiltersActive = collect($advancedFilterKeys)->contains(fn ($key) => $request->filled($key));

        return view('questions.index', compact('allExamGroups', 'allExamPackages', 'questions', 'subjects', 'topics', 'stopics', 'qtypes', 'diffs', 'languages', 'questionTags', 'parentCategories', 'childCategories', 'perPage', 'examsQuestions', 'selectedGroup', 'selectedPackage', 'selectedExam', 'selectedCategory', 'selectedSubcategory', 'advancedFiltersActive'));
    }

    public function create()
    {
        $languages = Language::enabledForOrganization($this->tenantId())->orderBy('name')->get();
        $qtypes = Qtype::displayOrdered();
        $subjects = $this->tenantSubjects()->orderBy('subject_name')->get();
        $topics = $this->tenantTopics()->orderBy('name')->get();
        $stopics = $this->tenantStopics()->orderBy('name')->get();
        $diffs = Diff::orderBy('diff_level')->get();
        $passages = $this->tenantPassages()->orderBy('name')->get();
        $groups = Group::where('organization_id', \App\Support\Tenant::id())->orderBy('group_name')->get();
        $questionTags = $this->tenantQuestionTags()->where('status', true)->orderBy('name')->get();
        return view('questions.action', compact('qtypes', 'subjects', 'topics', 'stopics', 'diffs', 'passages', 'groups', 'languages', 'questionTags'));
    }

    public function getSubjectsByGroup(Request $request)
    {
        $groupIds = collect((array) $request->input('group_ids', []))->map(fn ($id) => (int) $id)->filter()->unique()->values();
        $ownedCount = Group::where('organization_id', \App\Support\Tenant::id())->whereIn('id', $groupIds)->count();
        abort_unless($ownedCount === $groupIds->count(), 422, 'Invalid group selected.');

        $subjects = Subject::where('organization_id', \App\Support\Tenant::id())
            ->whereHas('groups', fn ($query) => $query->whereIn('groups.id', $groupIds), '=', $groupIds->count())
            ->orderBy('subject_name')
            ->get();

        return response()->json($subjects);
    }

    public function getSectionsByGroup(Request $request)
    {
        $groupIds = collect((array) $request->input('group_ids', []))->map(fn ($id) => (int) $id)->filter()->unique();
        $ownedCount = Group::where('organization_id', \App\Support\Tenant::id())->whereIn('id', $groupIds)->count();
        abort_unless($ownedCount === $groupIds->count(), 422, 'Invalid group selected.');

        return response()->json($this->tenantSections()->where('status', true)
            ->whereHas('groups', fn ($query) => $query->whereIn('groups.id', $groupIds))
            ->orderByRaw('CASE WHEN display_order = 0 THEN 1 ELSE 0 END')
            ->orderBy('display_order')->orderBy('name')->get(['id', 'name']));
    }
    public function edit(Question $question)
    {
        $this->ensureTenantOwns($question);
        $languages = Language::enabledForOrganization($this->tenantId())->orderBy('name')->get();
        $qtypes = Qtype::displayOrdered();
        $subjects = $this->tenantSubjects()->orderBy('subject_name')->get();
        $topics = $this->tenantTopics()->orderBy('name')->get();
        $stopics = $this->tenantStopics()->orderBy('name')->get();
        $diffs = Diff::orderBy('diff_level')->get();
        $passages = $this->tenantPassages()->orderBy('name')->get();
        $groups = Group::where('organization_id', \App\Support\Tenant::id())->orderBy('group_name')->get();
        $question->loadMissing('tags');
        $questionTags = $this->tenantQuestionTags()->where('status', true)->orderBy('name')->get();
        return view('questions.action', compact('question', 'qtypes', 'subjects', 'topics', 'stopics', 'diffs', 'passages', 'groups', 'languages', 'questionTags'));
    }

    public function store(Request $request)
    {
        try {
            SaasAccess::abortIfLimitReached('questions');
            $request->validate([
                'qtype_id' => 'required|integer|exists:qtypes,id',
                'subject_id' => 'nullable|integer|exists:subjects,id',
                'question_section_id' => 'nullable|integer|exists:question_sections,id',
                // ✅ Yahan topic_id aur stopic_id ko 'nullable' kiya gaya hai
                'topic_id' => 'nullable|integer|exists:topics,id',
                'stopic_id' => 'nullable|integer|exists:stopics,id',
                'diff_id' => 'nullable|integer|exists:diffs,id',
                'passage_id' => 'nullable|integer|exists:passages,id',
                'question' => 'nullable|string',
                'source_url' => 'nullable|url|max:2000',
                'source_reference' => 'nullable|string|max:255',
                'option1' => 'nullable|string',
                'option2' => 'nullable|string',
                'option3' => 'nullable|string',
                'option4' => 'nullable|string',
                'option5' => 'nullable|string',
                'option6' => 'nullable|string',
                'marks' => 'nullable|numeric',
                'negative_marks' => 'nullable|numeric',
                'scoring_policy' => 'nullable|in:NORMAL,MTA',
                'hint' => 'nullable|string',
                'explanation' => 'nullable|string',
                'answer' => 'nullable|string|max:15',
                'true_false' => 'nullable|string|max:5',
                'fill_blank' => 'nullable|string',
                'fill_blank_answers' => 'nullable|array|max:20',
                'fill_blank_answers.*.accepted_answers' => 'nullable|string|max:2000',
                'nat_mode' => 'nullable|in:exact,range,tolerance',
                'nat_value' => 'nullable|numeric',
                'nat_min' => 'nullable|numeric',
                'nat_max' => 'nullable|numeric',
                'nat_tolerance' => 'nullable|numeric|min:0',
                'status' => 'nullable|string|max:3',
                'correct_answers' => 'nullable|array|max:6',
                'correct_answers.*' => 'integer|min:1|max:6',
                'si_answer1' => 'nullable|string',
                'group_ids' => 'required|array',
                'group_ids.*' => 'exists:groups,id',
                'tag_ids' => 'nullable|array',
                'tag_ids.*' => 'integer|exists:question_tags,id',
                'language_id' => 'required|integer|exists:languages,id',
            ]);
            $this->validateTenantRelations($request);
            $this->validateTenantQuestionTags($request);

            $data = $request->all();
            $data['organization_id'] = \App\Support\Tenant::id();
            $data['scoring_policy'] = strtoupper((string) ($data['scoring_policy'] ?? 'NORMAL')) ?: 'NORMAL';

            // ✅ SENIOR DEV FIX: Clean data based on Question Type to prevent MathLive formula mixing
            $qType = Qtype::find($request->qtype_id);
            if ($qType) {
                if ($qType->type == 'M') { // Multiple Choice
                    $data['fill_blank'] = null; // Important: Clear any formula stuck here
                    $data['true_false'] = null;
                    $data['si_answer1'] = null;
                } 
                elseif ($qType->type == 'T') { // True/False
                    $data['fill_blank'] = null;
                    $data['si_answer1'] = null;
                }
                elseif ($qType->type == 'F') { // Fill in blanks
                    $data['true_false'] = null;
                    $data['si_answer1'] = null;
                }
            }
            // ✅ END FIX
            $data = $this->prepareAnswerConfiguration($request, $data, $qType);

            $correctOptionIndices = collect($request->input('correct_answers', []))
                ->map(fn ($index) => (int) $index)->filter(fn ($index) => $index >= 1 && $index <= 6)
                ->unique()->sort()->values()->all();
            $data['correct_option_indices'] = $correctOptionIndices;

            $question = Question::create($data);
            $question->groups()->sync($request->group_ids);
            app(CurriculumTaxonomyService::class)->syncQuestion($question->fresh(['topic', 'stopic']), $request->group_ids);
            $question->tags()->sync($request->input('tag_ids', []));

            QuestionLang::create([
                'question_id' => $question->id,
                'language_id' => $request->language_id,
                'question' => $request->question,
                'option1' => $request->option1,
                'option2' => $request->option2,
                'option3' => $request->option3,
                'option4' => $request->option4,
                'option5' => $request->option5,
                'option6' => $request->option6,
                'hint' => $request->hint,
                'explanation' => $request->explanation,
                'fill_blank' => $question->fill_blank,
            ]);

            if ($request->filled('flashcard_set_id')) {
                $flashcardSet = FlashcardSet::where('organization_id', \App\Support\Tenant::id())
                    ->findOrFail($request->integer('flashcard_set_id'));

                $card = $this->questionToFlashcardPayload($question->fresh(['qtype']), (int) $flashcardSet->cards()->max('sort_order') + 1);

                if ($request->input('return_to') === 'edit' && $request->filled('card_id')) {
                    $flashcard = Flashcard::where('flashcard_set_id', $flashcardSet->id)
                        ->findOrFail($request->integer('card_id'));

                    if ($card) {
                        $flashcard->update([
                            'source_question_id' => $question->id,
                            'card_type' => $card['card_type'] ?? 'basic',
                            'options' => $card['options'] ?? null,
                            'difficulty' => $card['difficulty'] ?? 'Medium',
                            'hint' => $card['hint'] ?? null,
                            'explanation' => $card['explanation'] ?? null,
                        ]);

                        $nextOrder = (int) $flashcard->sourceQuestions()->max('flashcard_question_links.sort_order') + 1;
                        $flashcard->sourceQuestions()->syncWithoutDetaching([
                            $question->id => ['sort_order' => $nextOrder],
                        ]);
                    }

                    return redirect()
                        ->route('flashcards.cards.edit', [$flashcardSet, $flashcard])
                        ->with('success', $card ? 'Question created and linked to this flashcard.' : 'Question created, but it could not be linked as an objective flashcard.');
                }

                if ($card) {
                    $createdCard = $flashcardSet->cards()->create($card);
                    $createdCard->sourceQuestions()->syncWithoutDetaching([
                        $question->id => ['sort_order' => 1],
                    ]);
                }

                $redirectRoute = $request->input('return_to') === 'create'
                    ? 'flashcards.cards.create'
                    : 'flashcards.show';

                return redirect()
                    ->route($redirectRoute, $flashcardSet)
                    ->with('success', $card ? 'Question created and added to flashcards.' : 'Question created, but it could not be converted into an objective flashcard.');
            }

            return redirect()->route('questions.index')->with('success', 'Question created successfully.');
        } catch (ValidationException $e) {
            if ($request->filled('flashcard_set_id') && $request->input('return_to') === 'edit' && $request->filled('card_id')) {
                return redirect()
                    ->route('flashcards.cards.edit', [$request->integer('flashcard_set_id'), $request->integer('card_id')])
                    ->withErrors($e->validator)
                    ->withInput();
            }

            if ($request->filled('flashcard_set_id') && $request->input('return_to') === 'create') {
                return redirect()
                    ->route('flashcards.cards.create', $request->integer('flashcard_set_id'))
                    ->withErrors($e->validator)
                    ->withInput();
            }

            return redirect()->route('questions.create')->withErrors($e->validator)->withInput();
        } catch (\Exception $e) {
            if ($request->filled('flashcard_set_id') && $request->input('return_to') === 'edit' && $request->filled('card_id')) {
                return redirect()
                    ->route('flashcards.cards.edit', [$request->integer('flashcard_set_id'), $request->integer('card_id')])
                    ->with('error', 'Failed to create question.')
                    ->withInput();
            }

            if ($request->filled('flashcard_set_id') && $request->input('return_to') === 'create') {
                return redirect()
                    ->route('flashcards.cards.create', $request->integer('flashcard_set_id'))
                    ->with('error', 'Failed to create question.')
                    ->withInput();
            }

            return redirect()->route('questions.create')->with('error', 'Failed to create question.');
        }
    }

    public function getLangData($questionId, $languageId)
    {
        $tenantId=\App\Support\Tenant::id();
        $attempts=\Illuminate\Support\Facades\DB::table('exam_results')
            ->where('organization_id',$tenantId)->whereNull('end_time')->whereNotNull('start_time');
        if (request()->is('guest/questions/*')) {
            $guestId=session('guest_id') ?? request()->cookie('guest_id');
            if (!is_string($guestId) || $guestId==='') {
                return response()->json(['status'=>false,'message'=>'Question not found.'],404);
            }
            $attempts->where('guest_id',$guestId)->whereNull('student_id');
        } else {
            $student=\Illuminate\Support\Facades\Auth::guard('student')->user();
            if (!$student || (int)$student->organization_id!==(int)$tenantId || $student->status!=='Active') {
                return response()->json(['status'=>false,'message'=>'Question not found.'],404);
            }
            $attempts->where('student_id',$student->id);
        }
        if (!\Illuminate\Support\Facades\DB::table('exam_stats')->where('organization_id',$tenantId)
            ->where('question_id',$questionId)->whereIn('exam_result_id',$attempts->select('id'))->exists()
            || !\App\Models\Language::where('organization_id',$tenantId)->whereKey($languageId)->exists()) {
            return response()->json(['status'=>false,'message'=>'Question not found.'],404);
        }
        try {
            $question = Question::where('organization_id', $tenantId)->find($questionId);
            if (!$question) {
                 return response()->json(['status' => false, 'message' => 'Question not found.'], 404);
            }

            $questionLang = QuestionLang::where('question_id', $questionId)
                ->where('language_id', $languageId)
                ->first();

            $passageContent = null;
            $passageName = null;
            if ($question->passage_id) {
                $passage = $question->passage;
                if($passage && (int)$passage->organization_id===(int)$tenantId) {
                    $passageName = $passage->name;
                    $passageLang = PassageLang::where('passage_id', $question->passage_id)
                                    ->where('language_id', $languageId)
                                    ->first();
                    $passageContent = $passageLang ? $passageLang->passage : null; 
                }
            }

            $defaultData = [
                'question' => $question->question,
                'hint' => $question->hint,
                'option1' => $question->option1,
                'option2' => $question->option2,
                'option3' => $question->option3,
                'option4' => $question->option4,
                'option5' => $question->option5,
                'option6' => $question->option6,
            ];

            $translatedData = [];
            if ($questionLang) {
                $translatedData = [
                    'question' => $questionLang->question,
                    'hint' => $questionLang->hint,
                    'option1' => $questionLang->option1,
                    'option2' => $questionLang->option2,
                    'option3' => $questionLang->option3,
                    'option4' => $questionLang->option4,
                    'option5' => $questionLang->option5,
                    'option6' => $questionLang->option6,
                ];
            }

            $finalData = array_merge($defaultData, array_filter($translatedData, function($value) { 
                return $value !== null && $value !== ''; 
            }));

            $finalData['passage'] = $passageContent;
            $finalData['passage_name'] = $passageName;
            $finalData['question_type'] = $question->qtype->type ?? 'M';

            return response()->json([
                'status' => true,
                'message' => 'Data fetched successfully.',
                'data' => $finalData
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Question language data could not be loaded.',
                'data' => null
            ], 500);
        }
    }

    public function remove(Request $request)
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:questions,id'
        ]);

        try {
            $ids = collect($request->ids)->map(fn ($id) => (int) $id)->unique()->values();
            $questions = Question::where('organization_id', \App\Support\Tenant::id())
                ->whereIn('id', $ids);

            if ((clone $questions)->count() !== $ids->count()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'One or more selected questions could not be found.'
                ], 404);
            }

            if ((clone $questions)->whereHas('exams')->exists()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Cannot delete selected questions because one or more are attached to an exam.'
                ], 422);
            }

            $questions->delete();

            return response()->json([
                'status' => 'success',
                'message' => 'Selected questions deleted successfully.'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete questions.'
            ], 500);
        }
    }

    public function update(Request $request, Question $question)
    {
        $this->ensureTenantOwns($question);
        try {
            $request->validate([
                'qtype_id' => 'required|integer|exists:qtypes,id',
                'subject_id' => 'nullable|integer|exists:subjects,id',
                'question_section_id' => 'nullable|integer|exists:question_sections,id',
                // ✅ Yahan topic_id aur stopic_id ko 'nullable' kiya gaya hai
                'topic_id' => 'nullable|integer|exists:topics,id',
                'stopic_id' => 'nullable|integer|exists:stopics,id',
                'diff_id' => 'nullable|integer|exists:diffs,id',
                'passage_id' => 'nullable|integer|exists:passages,id',
                'question' => 'nullable|string',
                'source_url' => 'nullable|url|max:2000',
                'source_reference' => 'nullable|string|max:255',
                'option1' => 'nullable|string',
                'option2' => 'nullable|string',
                'option3' => 'nullable|string',
                'option4' => 'nullable|string',
                'option5' => 'nullable|string',
                'option6' => 'nullable|string',
                'marks' => 'nullable|numeric',
                'negative_marks' => 'nullable|numeric',
                'scoring_policy' => 'nullable|in:NORMAL,MTA',
                'hint' => 'nullable|string',
                'explanation' => 'nullable|string',
                'answer' => 'nullable|string|max:15',
                'true_false' => 'nullable|string|max:5',
                'fill_blank' => 'nullable|string',
                'fill_blank_answers' => 'nullable|array|max:20',
                'fill_blank_answers.*.accepted_answers' => 'nullable|string|max:2000',
                'nat_mode' => 'nullable|in:exact,range,tolerance',
                'nat_value' => 'nullable|numeric',
                'nat_min' => 'nullable|numeric',
                'nat_max' => 'nullable|numeric',
                'nat_tolerance' => 'nullable|numeric|min:0',
                'status' => 'nullable|string|max:3',
                'correct_answers' => 'nullable|array|max:6',
                'correct_answers.*' => 'integer|min:1|max:6',
                'si_answer1' => 'nullable|string',
                'group_ids' => 'required|array',
                'group_ids.*' => 'exists:groups,id',
                'tag_ids' => 'nullable|array',
                'tag_ids.*' => 'integer|exists:question_tags,id',
                'language_id' => 'required|integer|exists:languages,id',
            ]);
            $this->validateTenantRelations($request);
            $this->validateTenantQuestionTags($request);

            $data = $request->all();
            $data['scoring_policy'] = strtoupper((string) ($data['scoring_policy'] ?? 'NORMAL')) ?: 'NORMAL';

            // ✅ SENIOR DEV FIX: Clean data on UPDATE as well
            $qType = Qtype::find($request->qtype_id);
            if ($qType) {
                if ($qType->type == 'M') {
                    $data['fill_blank'] = null;
                    $data['true_false'] = null;
                    $data['si_answer1'] = null;
                } elseif ($qType->type == 'T') {
                    $data['fill_blank'] = null;
                    $data['si_answer1'] = null;
                } elseif ($qType->type == 'F') {
                    $data['true_false'] = null;
                    $data['si_answer1'] = null;
                }
            }
            // ✅ END FIX
            $data = $this->prepareAnswerConfiguration($request, $data, $qType);

            $correctOptionIndices = collect($request->input('correct_answers', []))
                ->map(fn ($index) => (int) $index)->filter(fn ($index) => $index >= 1 && $index <= 6)
                ->unique()->sort()->values()->all();
            $data['correct_option_indices'] = $correctOptionIndices;

            $question->update($data);
            $question->groups()->sync($request->group_ids);
            app(CurriculumTaxonomyService::class)->syncQuestion($question->fresh(['topic', 'stopic']), $request->group_ids);
            $question->tags()->sync($request->input('tag_ids', []));

            $returnUrl = $this->safeInternalReturnUrl($request->input('return_url'));

            return ($returnUrl ? redirect()->to($returnUrl) : redirect()->route('questions.index'))
                ->with('success', 'Question updated successfully.');
        } catch (ValidationException $e) {
            return redirect()->route('questions.edit', [
                'question' => $question->id,
                'return_url' => $request->input('return_url'),
            ])->withErrors($e->validator)->withInput();
        } catch (\Exception $e) {
            return redirect()->route('questions.edit', [
                'question' => $question->id,
                'return_url' => $request->input('return_url'),
            ])->with('error', 'Failed to update question.');
        }
    }

    public function destroy($id)
    {
        try {
            $question = Question::findOrFail($id);

            $this->ensureTenantOwns($question);
            if ($question->exams()->exists()) {
                return redirect()->route('questions.index')->with('error', 'Cannot delete question as it is attached to an exam.');
            }
            $question->delete();
            return redirect()->route('questions.index')->with('success', 'Question deleted successfully.');
        } catch (\Exception $e) {
            return redirect()->route('questions.index')->with('error', 'Failed to delete question.');
        }
    }

    private function prepareAnswerConfiguration(Request $request, array $data, ?Qtype $qType): array
    {
        $type = strtoupper(trim((string) $qType?->type));
        $data['fill_blank_config'] = null;
        $data['nat_config'] = null;

        if ($type === 'F' || $type === 'B') {
            $blanks = collect($request->input('fill_blank_answers', []))
                ->map(function ($blank) {
                    $answers = preg_split('/\s*\|\s*/u', (string) ($blank['accepted_answers'] ?? ''), -1, PREG_SPLIT_NO_EMPTY);
                    $answers = array_values(array_unique(array_filter(array_map('trim', $answers), fn ($answer) => $answer !== '')));
                    return $answers ? ['answers' => $answers] : null;
                })
                ->filter()
                ->values()
                ->all();

            if (! $blanks && $request->filled('fill_blank')) {
                $blanks = [['answers' => [trim(strip_tags((string) $request->fill_blank))]]];
            }

            if (! $blanks) {
                throw ValidationException::withMessages([
                    'fill_blank_answers' => 'Add at least one blank and its accepted answer.',
                ]);
            }

            $data['fill_blank_config'] = ['version' => 1, 'blanks' => $blanks];
            $data['fill_blank'] = $blanks[0]['answers'][0];
            $data['true_false'] = null;
            $data['si_answer1'] = null;

        } elseif ($type === 'NAT') {
            $mode = $request->input('nat_mode', 'exact');
            $config = ['version' => 1, 'mode' => $mode];

            if ($mode === 'range') {
                if (! $request->filled('nat_min') || ! $request->filled('nat_max')) {
                    throw ValidationException::withMessages(['nat_min' => 'Minimum and maximum values are required for a NAT range.']);
                }
                $min = (float) $request->nat_min;
                $max = (float) $request->nat_max;
                $config['min'] = min($min, $max);
                $config['max'] = max($min, $max);
            } else {
                if (! $request->filled('nat_value')) {
                    throw ValidationException::withMessages(['nat_value' => 'The numerical answer is required.']);
                }
                $config['value'] = (float) $request->nat_value;
                if ($mode === 'tolerance') {
                    $config['tolerance'] = abs((float) $request->input('nat_tolerance', 0));
                }
            }

            $data['nat_config'] = $config;
            $data['fill_blank'] = null;
            $data['true_false'] = null;
            $data['si_answer1'] = null;

        } else {
            $data['fill_blank'] = null;
        }

        return $data;
    }
    private function questionToFlashcardPayload(Question $question, int $sortOrder): ?array
    {
        $type = $question->qtype?->type;
        $front = trim((string) $question->question);

        if ($front === '') {
            return null;
        }

        $payload = [
            'front' => $front,
            'explanation' => $question->explanation,
            'hint' => $question->hint ? strip_tags((string) $question->hint) : null,
            'difficulty' => 'Medium',
            'source_label' => 'Question #' . $question->id,
            'source_question_id' => $question->id,
            'ai_generated' => false,
            'sort_order' => $sortOrder,
            'status' => false,
        ];

        if ($type === 'T') {
            $answer = trim((string) $question->true_false);

            return $answer === '' ? null : array_merge($payload, [
                'card_type' => 'true_false',
                'options' => ['True', 'False'],
                'back' => ucfirst(strtolower($answer)),
                'explanation' => $payload['explanation'] ?: 'Correct answer: ' . ucfirst(strtolower($answer)) . '.',
            ]);
        }

        if ($type === 'F') {
            $answer = trim((string) $question->fill_blank);

            return $answer === '' ? null : array_merge($payload, [
                'card_type' => 'fill_blank',
                'options' => null,
                'back' => $answer,
                'explanation' => $payload['explanation'] ?: 'Correct answer: ' . $answer . '.',
            ]);
        }

        if ($type === 'M') {
            $options = collect(range(1, 6))
                ->map(fn ($index) => trim(strip_tags((string) $question->{'option' . $index})))
                ->filter()
                ->values();

            $answers = collect($question->correctOptionValues())
                ->map(fn ($answer) => trim(strip_tags((string) $answer)))
                ->filter()
                ->unique()
                ->values();

            if ($options->count() < 2 || $answers->isEmpty()) {
                return null;
            }

            return array_merge($payload, [
                'card_type' => $answers->count() > 1 ? 'multi_select' : 'mcq',
                'options' => $options->all(),
                'back' => $answers->implode(' || '),
                'explanation' => $payload['explanation'] ?: 'Correct answer: ' . $answers->implode(', ') . '.',
            ]);
        }

        return null;
    }
}

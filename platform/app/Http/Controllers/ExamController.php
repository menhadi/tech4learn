<?php

namespace App\Http\Controllers;

use App\Models\Exam;
use App\Models\ExamSection;
use App\Models\Category;
use App\Models\Group;
use App\Models\Package;
use App\Models\Question;
use App\Models\QuestionRepairDraft;
use App\Models\QuestionSection;
use App\Models\Subject;
use App\Models\ExamResult;
use App\Models\ExamQualitySource;
use App\Models\QuestionsReport;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth; // âœ… Auth Import Zaroori Hai
use Illuminate\Support\Facades\Cache; // âœ… Cache Import Zaroori Hai
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use App\Support\SaasAccess;
use App\Services\CurriculumTaxonomyService;
use App\Services\ExamLanguageService;
use App\Services\ExamScopeService;
use App\Services\ManualExamPaperService;
use App\Services\QuestionRepairService;
use Illuminate\Support\Str;

class ExamController extends Controller
{
    private function ensureCanEditPaper(): void
    {
        abort_unless(user_can_route_action('questions.edit', 'edit') || user_can_route_action('exams.edit', 'edit'), 403);
    }

    private function syncQualitySources(Request $request, Exam $exam): void
    {
        $tenantId = (int) \App\Support\Tenant::id();
        $storage = app(\App\Services\ExamQualitySourceStorage::class);
        $removeIds = collect($request->input('remove_source_ids', []))->map(fn ($id) => (int) $id)->filter();
        $removedSources = collect();
        if ($removeIds->isNotEmpty()) {
            $removedSources = ExamQualitySource::where('organization_id', $tenantId)->where('exam_id', $exam->id)
                ->whereIn('id', $removeIds)->get();
            $removedSources->each(function (ExamQualitySource $source) use ($storage) {
                    $storage->delete($source);
                    $source->delete();
                });
        }

        $roles = [
            'questions' => ['source_question_pdf', 'source_question_url'],
            'answers' => ['source_answer_pdf', 'source_answer_url'],
            'combined' => ['source_combined_pdf', 'source_combined_url'],
        ];
        foreach ($roles as $role => [$fileField, $urlField]) {
            $stored = null;
            if ($request->hasFile($fileField)) {
                $stored = $storage->store($request->file($fileField), $tenantId, (int) $exam->id, $role);
            } elseif ($request->filled($urlField)) {
                $submittedUrl = trim((string) $request->input($urlField));
                $existingUrl = ExamQualitySource::where('organization_id', $tenantId)
                    ->where('exam_id', $exam->id)
                    ->where('role', $role)
                    ->where('is_active', true)
                    ->latest('id')
                    ->value('source_url');
                $wasExplicitlyRemoved = $removedSources->contains(
                    fn (ExamQualitySource $source) => $source->role === $role
                        && trim((string) $source->source_url) === $submittedUrl
                );

                if (! $wasExplicitlyRemoved && trim((string) $existingUrl) !== $submittedUrl) {
                    $stored = $storage->storeUrl(
                        $submittedUrl,
                        $tenantId,
                        (int) $exam->id,
                        $role
                    );
                }
            }

            if ($stored) {
                $existing = ExamQualitySource::where('organization_id', $tenantId)
                    ->where('exam_id', $exam->id)
                    ->where('role', $role)
                    ->get();

                ExamQualitySource::create(array_merge($stored, [
                    'organization_id' => $tenantId, 'exam_id' => $exam->id, 'role' => $role,
                    'kind' => 'file', 'is_active' => true,
                ]));

                $existing->each(function (ExamQualitySource $source) use ($storage) {
                    $storage->delete($source);
                    $source->delete();
                });
            }
        }
    }

    private function tenantSubjects()
    {
        return Subject::query()->where('organization_id', (int) \App\Support\Tenant::id());
    }

    private function tenantTopics()
    {
        return \App\Models\Topic::query()->whereHas('group', fn ($q) => $q->where('organization_id', (int) \App\Support\Tenant::id()));
    }

    private function tenantStopics()
    {
        return \App\Models\Stopic::query()->whereHas('group', fn ($q) => $q->where('organization_id', (int) \App\Support\Tenant::id()));
    }

    private function ensureTenantOwns($model): void
    {
        if ((int) ($model->organization_id ?? 0) !== (int) \App\Support\Tenant::id()) {
            abort(404);
        }
    }

    private function examSectionForDefinition(Exam $exam, ?QuestionSection $definition): ?ExamSection
    {
        if (! $definition) return null;
        return $exam->sections()->firstOrCreate(
            ['question_section_id' => $definition->id],
            ['name' => $definition->name, 'display_order' => $definition->display_order, 'duration' => null]
        );
    }

    private function availableSectionDefinitions(Exam $exam)
    {
        $groupIds = $this->effectiveExamGroupIds($exam);
        return QuestionSection::query()->where('organization_id', \App\Support\Tenant::id())->where('status', true)
            ->whereHas('groups', fn ($query) => $query->whereIn('groups.id', $groupIds))
            ->orderByRaw('CASE WHEN display_order = 0 THEN 1 ELSE 0 END')->orderBy('display_order')->orderBy('name')->get();
    }
    private function effectiveExamGroupIds(Exam $exam): array
    {
        $exam->loadMissing(['groups:id', 'packages.groups:id']);

        return $exam->groups->pluck('id')
            ->merge($exam->packages->flatMap(fn (Package $package) => $package->groups->pluck('id')))
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function normalizeExamGroupSelection(Request $request, ?Exam $exam = null): void
    {
        $groupIds = collect((array) $request->input('groups', []))
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique();

        if ($groupIds->isEmpty()) {
            $packageIds = collect((array) $request->input('packages', []))
                ->map(fn ($id) => (int) $id)
                ->filter()
                ->unique();

            if ($packageIds->isNotEmpty()) {
                $groupIds = Package::query()
                    ->where('organization_id', \App\Support\Tenant::id())
                    ->whereIn('id', $packageIds)
                    ->with('groups:id')
                    ->get()
                    ->flatMap(fn (Package $package) => $package->groups->pluck('id'))
                    ->map(fn ($id) => (int) $id)
                    ->filter()
                    ->unique();
            }
        }

        if ($groupIds->isEmpty() && $exam) {
            $groupIds = collect($this->effectiveExamGroupIds($exam));
        }

        if ($groupIds->isNotEmpty()) {
            $request->merge(['groups' => $groupIds->values()->all()]);
        }

        if ($request->boolean('browser_tolerance') && ! $request->filled('tolerance_count')) {
            $request->merge(['tolerance_count' => 0]);
        }
    }
    /**
     * Display a listing of the resource.
     * âœ… UPDATED: Added Group Filtering Logic for Staff Users
     */
    public function index(Request $request)
    {
        if (! subcategories_enabled()) {
            $request->query->remove('subcategory');
        }

        $query = Exam::query()->where('organization_id', \App\Support\Tenant::id())
            ->with(['groups', 'packages:id,slug'])
            ->withCount(['questions', 'results', 'results as passed_count' => function ($q) {
                $q->where('result', 'Pass');
            }]);

        if (Schema::hasColumn('exams', 'is_student_practice')) {
            $query->where('is_student_practice', false);
        }

        // --- ðŸ”’ SECURITY LOGIC START ---
        $currentUser = Auth::user();

        // Check 1: Agar user exist karta hai aur wo "Super Admin" (0) nahi hai
        if ($currentUser && $currentUser->ugroup_id != 0) {
            
            // Helper se current user ke groups nikalo
            $assignedGroupIds = getUserGroupIds();

            // Check 2: Agar user ke paas groups hain, to filter karo
            if (!empty($assignedGroupIds)) {
                $query->where(function ($groupScope) use ($assignedGroupIds) {
                    $groupScope->whereHas('groups', function ($q) use ($assignedGroupIds) {
                        $q->whereIn('groups.id', $assignedGroupIds);
                    })->orWhereHas('packages.groups', function ($q) use ($assignedGroupIds) {
                        $q->whereIn('groups.id', $assignedGroupIds);
                    });
                });
            }
        }
        // --- ðŸ”’ SECURITY LOGIC END ---

        if ($request->has('search')) {
            $query->where('name', 'like', '%' . $request->input('search') . '%');
        }

        if ($request->has('filter')) {
            if ($request->filter == 'active') {
                $query->where('status', 'Active');
            } elseif ($request->filter == 'unpublished_pdfs') {
                $query->where('status', 'Inactive')->whereDoesntHave('questions', fn ($q) => $q->where('questions.status', 'active'))
                    ->whereHas('qualitySources', fn ($q) => $q->where('is_active', true)->whereIn('role', ['questions', 'combined'])->whereNotNull('file_path'));
            } elseif ($request->filter == 'upcoming') {
                $query->where('start_date', '>', Carbon::now());
            } elseif ($request->filter == 'ended') {
                $query->where('end_date', '<', Carbon::now());
            }
        }

        if ($request->filled('group')) {
            $query->whereHas('groups', function ($q) use ($request) {
                $q->where('groups.id', $request->group);
            });
        }

        if ($request->filled('category')) {
            $query->where(function ($categoryQuery) use ($request) {
                $categoryQuery->where('category_level_1', $request->category)
                    ->orWhereHas('packages', function ($packageQuery) use ($request) {
                        $packageQuery->where('category_level_1', $request->category);
                    });
            });
        }

        if ($request->filled('subcategory')) {
            $query->where(function ($subcategoryQuery) use ($request) {
                $subcategoryQuery->where('category_level_2', $request->subcategory)
                    ->orWhereHas('packages', function ($packageQuery) use ($request) {
                        $packageQuery->where('category_level_2', $request->subcategory);
                    });
            });
        }

        if ($request->filled('package')) {
            $query->whereHas('packages', function ($q) use ($request) {
                $q->where('packages.id', $request->package);
            });
        }

        if ($request->filled('exam')) {
            $query->where('exams.id', $request->exam);
        }

        $perPage = (int) $request->input('per_page', 50);
        $perPage = in_array($perPage, [50, 100, 500], true) ? $perPage : 50;
        $sort = $request->input('sort', 'newest');
        match ($sort) {
            'display' => $query->displayOrdered(),
            'name' => $query->orderBy('name')->orderBy('id'),
            'oldest' => $query->orderBy('created_at')->orderBy('id'),
            default => $query->orderByDesc('created_at')->orderByDesc('id'),
        };
        $exams = $query->paginate($perPage)->withQueryString();

        // --- STATS CALCULATION (Filtered for Staff) ---
        $statsQuery = Exam::query()->where('organization_id', \App\Support\Tenant::id());
        if (Schema::hasColumn('exams', 'is_student_practice')) {
            $statsQuery->where('is_student_practice', false);
        }
        if ($currentUser && $currentUser->ugroup_id != 0) {
            $assignedGroupIds = getUserGroupIds();
            if (!empty($assignedGroupIds)) {
                $statsQuery->whereHas('groups', function ($q) use ($assignedGroupIds) {
                    $q->whereIn('groups.id', $assignedGroupIds);
                });
            }
        }

        $totalExams = $statsQuery->count();
        
        // Active Exams Count
        $activeQuery = clone $statsQuery;
        $activeExams = $activeQuery->where('status', 'Active')->where('end_date', '>=', Carbon::now())->count();
        
        $totalAttempts = ExamResult::where('organization_id', \App\Support\Tenant::id())->count();
        $totalPassed = ExamResult::where('organization_id', \App\Support\Tenant::id())
            ->where('result', 'Pass')
            ->count();
        $globalPassRate = $totalAttempts > 0 ? round(($totalPassed / $totalAttempts) * 100, 1) : 0;

        $allExamGroups = Exam::where('organization_id', \App\Support\Tenant::id())
            ->when(Schema::hasColumn('exams', 'is_student_practice'), fn ($q) => $q->where('is_student_practice', false))
            ->with('groups')
            ->get()
            ->pluck('groups')
            ->flatten()
            ->unique('id')
            ->values();

        $dependentOptions = $this->dependentFilterOptionData($request, \App\Support\Tenant::id());
        $parentCategories = $dependentOptions['categories'];
        $childCategories = $dependentOptions['subcategories'];
        $allExamPackages = $dependentOptions['packages'];
        $allExamList = $dependentOptions['exams'];

        $selectedPackage = $request->package ?? null;
        $selectedExam = $request->exam ?? null;
        $selectedCategory = $request->category ?? null;
        $selectedSubcategory = $request->subcategory ?? null;

        $stats = [
            'total' => $totalExams,
            'active' => $activeExams,
            'attempts' => $totalAttempts,
            'pass_rate' => $globalPassRate
        ];

        return view('exams.index', compact('exams', 'stats', 'allExamGroups', 'parentCategories', 'childCategories', 'allExamPackages', 'allExamList', 'selectedPackage', 'selectedExam', 'selectedCategory', 'selectedSubcategory', 'perPage'));
    }

    public function show(Request $request)
    {
        $tenantId = \App\Support\Tenant::id();

        //$query = QuestionsReport::query()->with(['question', 'subject', 'student']);
        $query = QuestionsReport::query()
            ->leftJoin('exam_questions', 'questions_report.question_id', '=', 'exam_questions.question_id')
            ->leftJoin('exams', 'exam_questions.exam_id', '=', 'exams.id')
            ->leftJoin('flashcard_sets', 'questions_report.flashcard_set_id', '=', 'flashcard_sets.id')
            ->leftJoin('packages as flashcard_packages', 'flashcard_sets.package_id', '=', 'flashcard_packages.id')
            ->select(
                'questions_report.*',
                DB::raw('COALESCE(exams.name, flashcard_sets.title) as exam_name'),
                'flashcard_packages.name as package_name',
                'flashcard_sets.title as flashcard_set_title'
            )
            ->where('questions_report.organization_id', $tenantId)
            ->with(['question', 'subject', 'student', 'flashcard.set.package', 'flashcardSet.subject']);

        $reportType = $request->routeIs('exams.studyCardReports') ? 'study_cards' : 'questions';
        if ($reportType === 'study_cards') {
            $query->where(function ($q) {
                $q->whereNotNull('questions_report.flashcard_id')
                    ->orWhere('questions_report.report_source', 'flashcard');
            });
        } else {
            $query->whereNull('questions_report.flashcard_id')
                ->where(function ($q) {
                    $q->whereNull('questions_report.report_source')
                        ->orWhere('questions_report.report_source', '!=', 'flashcard');
                });
        }

        // --- ðŸ”’ SECURITY LOGIC START ---
        $currentUser = Auth::user();

        if ($request->has('search') && $request->search != '') {
            $search = $request->input('search');

            $query->where(function ($q) use ($search) {

                // Search in report message
                $q->where('message', 'like', "%{$search}%")

                // Search in question
                ->orWhereHas('question', function ($q2) use ($search) {
                    $q2->where('question', 'like', "%{$search}%");
                })

                // Search in subject
                ->orWhereHas('subject', function ($q2) use ($search) {
                    $q2->where('subject_name', 'like', "%{$search}%");
                })

                // Search in student
                ->orWhereHas('student', function ($q2) use ($search) {
                    $q2->where('name', 'like', "%{$search}%")
                       ->orWhere('email', 'like', "%{$search}%");
                })

                // Search in study cards
                ->orWhereHas('flashcard', function ($q2) use ($search) {
                    $q2->where('front', 'like', "%{$search}%")
                       ->orWhere('back', 'like', "%{$search}%");
                })

                // Search in study card set
                ->orWhereHas('flashcardSet', function ($q2) use ($search) {
                    $q2->where('title', 'like', "%{$search}%");
                })

                // Search in issue type
                ->orWhere('questions_report.question_type', 'like', "%{$search}%")

                // Search in study card package
                ->orWhere('flashcard_packages.name', 'like', "%{$search}%");

            });
        }

        if ($request->filled('exam')) {
            $query->where('exam_questions.exam_id', $request->exam);
        }

        if ($request->filled('status')) {
            if ($request->status !== 'all') {
                $query->where('questions_report.status', $request->status);
            }
        } else {
            $query->whereNotIn('questions_report.status', ['Resolved', 'Closed']);
        }



        $reports = $query->latest('questions_report.created_at')->paginate(10);

        // --- STATS CALCULATION (Filtered for Staff) ---
        $statsQuery = QuestionsReport::query()->where('organization_id', $tenantId);

        if ($reportType === 'study_cards') {
            $statsQuery->where(fn ($q) => $q->whereNotNull('flashcard_id')->orWhere('report_source', 'flashcard'));
        } else {
            $statsQuery->whereNull('flashcard_id')
                ->where(fn ($q) => $q->whereNull('report_source')->orWhere('report_source', '!=', 'flashcard'));
        }

        $total = (clone $statsQuery)->count();

        $pending = (clone $statsQuery)
            ->where('status', 'Pending')
            ->count();

        $resolved = (clone $statsQuery)
            ->where('status', 'Resolved')
            ->count();

        $closed = (clone $statsQuery)
            ->where('status', 'Closed')
            ->count();
        
        $examIds = DB::table('questions_report')
            ->join('exam_questions', 'questions_report.question_id', '=', 'exam_questions.question_id')
            ->where('questions_report.organization_id', $tenantId)
            ->distinct()
            ->pluck('exam_questions.exam_id');

        $exams = Exam::where('organization_id', $tenantId)
            ->whereIn('id', $examIds)
            ->orderBy('name')
            ->get();

        
        $stats = [
            'total' => $total,
            'pending' => $pending,
            'resolved' => $resolved,
            'closed' => $closed,
        ];

        return view('exams.reports', compact('reports', 'stats', 'exams', 'reportType'));
    }

    public function studyCardReports(Request $request)
    {
        return $this->show($request);
    }

    public function create()
    {
        $groups = Group::where('organization_id', \App\Support\Tenant::id())->orderBy('group_name')->get();
        $packages = Package::where('organization_id', \App\Support\Tenant::id())->with('groups:id')->orderBy('name')->get();

        $parentCategories = Category::where('organization_id', \App\Support\Tenant::id())->whereNull('parent_id')
            ->where('status', 1)
            ->orderBy('title')
            ->get();

        $childCategories = Category::where('organization_id', \App\Support\Tenant::id())->whereNotNull('parent_id')
            ->where('status', 1)
            ->orderBy('title')
            ->get();

        $testSubjects = Subject::where('organization_id', (int) \App\Support\Tenant::id())->with(['topics' => fn ($query) => $query->with(['stopics' => fn ($subtopics) => $subtopics->orderBy('name')])->orderBy('name')])
            ->orderBy('subject_name')
            ->get();
        $testTypeLabels = Exam::testTypeLabels();
        $languages = \App\Models\Language::enabledForOrganization((int) \App\Support\Tenant::id())->orderBy('name')->get();
        return view('exams.action', compact('groups', 'packages', 'parentCategories', 'childCategories', 'testSubjects', 'testTypeLabels', 'languages'));
    }

    public function store(Request $request)
    {
        SaasAccess::abortIfLimitReached('exams');

        $requestedGroupingMode = in_array($request->input('grouping_mode'), ['none', 'subject', 'section'], true) ? $request->input('grouping_mode') : 'subject';
        $useGroupTimer = $requestedGroupingMode !== 'none' && $request->boolean('use_group_timer');
        $request->merge(['grouping_mode' => $requestedGroupingMode, 'timer_mode' => $useGroupTimer ? $requestedGroupingMode : 'none', 'is_subject_timer' => $useGroupTimer ? 1 : 0]);

        $request->validate([
            'name' => 'required|string|max:255',
            'passing_percentage' => 'nullable|integer|min:0|max:100',
            'display_order' => 'nullable|integer|min:0',
            'test_type' => ['required', Rule::in(array_keys(Exam::testTypeLabels()))],
            'test_subject_id' => [
                Rule::requiredIf(fn () => in_array($request->input('test_type'), [Exam::TEST_TYPE_SUBJECT, Exam::TEST_TYPE_TOPIC, Exam::TEST_TYPE_SUBTOPIC], true)),
                'nullable',
                'integer',
                Rule::exists('subjects', 'id'),
            ],
            'test_topic_id' => [
                Rule::requiredIf(fn () => in_array($request->input('test_type'), [Exam::TEST_TYPE_TOPIC, Exam::TEST_TYPE_SUBTOPIC], true)),
                'nullable',
                'integer',
                Rule::exists('topics', 'id')->where(fn ($query) => $query->where('subject_id', $request->input('test_subject_id'))),
            ],
            'test_stopic_id' => [
                Rule::requiredIf(fn () => $request->input('test_type') === Exam::TEST_TYPE_SUBTOPIC),
                'nullable',
                'integer',
                Rule::exists('stopics', 'id')->where(fn ($query) => $query
                    ->where('subject_id', $request->input('test_subject_id'))
                    ->where('topic_id', $request->input('test_topic_id'))),
            ],
            'duration' => 'required|integer|min:0',
            'attempt_count' => 'required|integer|min:0',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'browser_tolerance' => 'required|boolean',
            'random_question' => 'required|boolean',
            'result_after_finish' => 'required|boolean',
            'option_shuffle' => 'required|boolean',
            'allow_answer_change' => 'required|boolean',
            'grouping_mode' => ['required', Rule::in(['none', 'subject', 'section'])],
            'timer_mode' => ['required', Rule::in(['none', 'subject', 'section'])],
            'is_subject_timer' => 'nullable|boolean',
            'proctor' => 'required|boolean',
            'calculator_allowed' => 'required|boolean', // âœ… Feature Preserved
            'groups' => 'nullable|array',
            'groups.*' => ['integer', Rule::exists('groups', 'id')->where(fn ($query) => $query->where('organization_id', \App\Support\Tenant::id()))],
            'packages' => 'nullable|array',
            'packages.*' => ['integer', Rule::exists('packages', 'id')->where(fn ($query) => $query->where('organization_id', \App\Support\Tenant::id()))],
            'language_ids' => 'nullable|array',
            'language_ids.*' => ['integer', Rule::exists('languages', 'id')->where(fn ($query) => $query->where('organization_id', \App\Support\Tenant::id())->where('is_enabled', true))],
            'tolerance_count' => 'nullable|integer', // Validating the nullable field

            'source_question_pdf' => 'nullable|file|mimes:pdf|max:51200',
            'source_answer_pdf' => 'nullable|file|mimes:pdf|max:51200',
            'source_combined_pdf' => 'nullable|file|mimes:pdf|max:51200',
            'source_question_url' => 'nullable|url|max:2000',
            'source_answer_url' => 'nullable|url|max:2000',
            'source_combined_url' => 'nullable|url|max:2000',
            'remove_source_ids' => 'nullable|array',
            'remove_source_ids.*' => 'integer',
            'category_level_1' => 'sometimes|nullable|numeric',
            'category_level_2' => 'sometimes|nullable|numeric',
        ]);

        $scope = app(ExamScopeService::class)->resolve(
            (int) \App\Support\Tenant::id(),
            (array) $request->input('packages', []),
            (array) $request->input('groups', []),
            $request->integer('category_level_1') ?: null,
            $request->integer('category_level_2') ?: null,
        );
        $languageIds = app(ExamLanguageService::class)->normalizeIds((int) \App\Support\Tenant::id(), (array) $request->input('language_ids', []));
        app(CurriculumTaxonomyService::class)->validateSelection(
            (int) \App\Support\Tenant::id(), $scope['group_ids'],
            $request->integer('test_subject_id') ?: null, $request->integer('test_topic_id') ?: null, $request->integer('test_stopic_id') ?: null
        );

        $data = $request->except(['groups', 'packages', 'language_ids', 'source_question_pdf', 'source_answer_pdf', 'source_combined_pdf', 'source_question_url', 'source_answer_url', 'source_combined_url', 'remove_source_ids']);
        $data['organization_id'] = \App\Support\Tenant::id();
        $data['category_level_1'] = $scope['category_level_1'];
        $data['category_level_2'] = $scope['category_level_2'];
        $data['slug'] = $this->uniqueExamSlug($request->input('slug') ?: $request->input('name'));
        $data['passing_percentage'] = $request->filled('passing_percentage') ? $request->integer('passing_percentage') : 0;
        $data['timer_mode'] = $request->input('timer_mode', 'none');
        $data['is_subject_timer'] = $data['timer_mode'] !== 'none';
        if (! in_array($data['test_type'], [Exam::TEST_TYPE_SUBJECT, Exam::TEST_TYPE_TOPIC, Exam::TEST_TYPE_SUBTOPIC], true)) {
            $data['test_subject_id'] = null;
        }
        if (! in_array($data['test_type'], [Exam::TEST_TYPE_TOPIC, Exam::TEST_TYPE_SUBTOPIC], true)) {
            $data['test_topic_id'] = null;
        }
        if ($data['test_type'] !== Exam::TEST_TYPE_SUBTOPIC) {
            $data['test_stopic_id'] = null;
        }
        
        // âœ… BUG FIX: Handle null tolerance_count
        $data['tolerance_count'] = $request->filled('tolerance_count') ? $request->input('tolerance_count') : 0;

        $exam = Exam::create($data);

        app(ExamScopeService::class)->sync($exam, $scope);
        app(ExamLanguageService::class)->sync($exam, $languageIds);

        $this->syncQualitySources($request, $exam);
        return redirect()->route('exams.index')->with('success', 'Exam created successfully.');
    }

    public function edit(Exam $exam)
    {
        $this->ensureTenantOwns($exam);
        $exam->loadMissing(['qualitySources', 'languages']);
        // ðŸ”’ Security Check: Staff apne group ka hi exam edit kare
        $currentUser = Auth::user();
        if ($currentUser && $currentUser->ugroup_id != 0) {
            $assignedGroupIds = getUserGroupIds();
            $examGroups = $this->effectiveExamGroupIds($exam);

            // Direct exam groups and groups inherited through packages both grant access.
            if (! empty($assignedGroupIds) && empty(array_intersect($assignedGroupIds, $examGroups))) {
                return redirect()->route('exams.index')->with('error', 'You do not have permission to edit this exam.');
            }
        }

        $groups = Group::where('organization_id', \App\Support\Tenant::id())->orderBy('group_name')->get();
        $packages = Package::where('organization_id', \App\Support\Tenant::id())->with('groups:id')->orderBy('name')->get();

        $parentCategories = Category::where('organization_id', \App\Support\Tenant::id())->whereNull('parent_id')
            ->where('status', 1)
            ->orderBy('title')
            ->get();

        $childCategories = Category::where('organization_id', \App\Support\Tenant::id())->whereNotNull('parent_id')
            ->where('status', 1)
            ->orderBy('title')
            ->get();

        $testSubjects = Subject::where('organization_id', (int) \App\Support\Tenant::id())->with(['topics' => fn ($query) => $query->with(['stopics' => fn ($subtopics) => $subtopics->orderBy('name')])->orderBy('name')])
            ->orderBy('subject_name')
            ->get();
        $testTypeLabels = Exam::testTypeLabels();
        $languages = \App\Models\Language::enabledForOrganization((int) \App\Support\Tenant::id())->orderBy('name')->get();
        return view('exams.action', compact('exam', 'groups', 'packages', 'parentCategories', 'childCategories', 'testSubjects', 'testTypeLabels', 'languages'));
    }

    public function update(Request $request, Exam $exam)
    {
        $this->ensureTenantOwns($exam);


        $requestedGroupingMode = $request->input('grouping_mode', $exam->grouping_mode ?: ($exam->timer_mode === 'section' ? 'section' : 'subject'));
        $requestedGroupingMode = in_array($requestedGroupingMode, ['none', 'subject', 'section'], true) ? $requestedGroupingMode : 'subject';
        $useGroupTimer = $requestedGroupingMode !== 'none' && $request->boolean('use_group_timer', $exam->timer_mode !== 'none' || $exam->is_subject_timer);
        $request->merge(['grouping_mode' => $requestedGroupingMode, 'timer_mode' => $useGroupTimer ? $requestedGroupingMode : 'none', 'is_subject_timer' => $useGroupTimer ? 1 : 0]);

        $booleanFields = [
            'browser_tolerance',
            'random_question',
            'result_after_finish',
            'option_shuffle',
            'allow_answer_change',
            'is_subject_timer',
            'proctor',
            'calculator_allowed',
            'negative_marking',
        ];

        // Legacy exams can contain NULL for flags that are now required.
        // Preserve a stored value when the form omits it; otherwise use No.
        foreach ($booleanFields as $field) {
            if (! $request->has($field)) {
                $storedValue = $exam->getRawOriginal($field);
                $request->merge([$field => $storedValue === null ? 0 : (int) (bool) $storedValue]);
            }
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'instruction' => 'nullable|string',
            'syllabus' => 'nullable|string',
            'passing_percentage' => 'nullable|integer|min:0|max:100',
            'display_order' => 'nullable|integer|min:0',
            'test_type' => ['required', Rule::in(array_keys(Exam::testTypeLabels()))],
            'test_subject_id' => [
                Rule::requiredIf(fn () => in_array($request->input('test_type'), [Exam::TEST_TYPE_SUBJECT, Exam::TEST_TYPE_TOPIC, Exam::TEST_TYPE_SUBTOPIC], true)),
                'nullable',
                'integer',
                Rule::exists('subjects', 'id'),
            ],
            'test_topic_id' => [
                Rule::requiredIf(fn () => in_array($request->input('test_type'), [Exam::TEST_TYPE_TOPIC, Exam::TEST_TYPE_SUBTOPIC], true)),
                'nullable',
                'integer',
                Rule::exists('topics', 'id')->where(fn ($query) => $query->where('subject_id', $request->input('test_subject_id'))),
            ],
            'test_stopic_id' => [
                Rule::requiredIf(fn () => $request->input('test_type') === Exam::TEST_TYPE_SUBTOPIC),
                'nullable',
                'integer',
                Rule::exists('stopics', 'id')->where(fn ($query) => $query
                    ->where('subject_id', $request->input('test_subject_id'))
                    ->where('topic_id', $request->input('test_topic_id'))),
            ],
            'duration' => 'required|integer|min:0',
            'attempt_count' => 'required|integer|min:0',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'browser_tolerance' => 'required|boolean',
            'random_question' => 'required|boolean',
            'result_after_finish' => 'required|boolean',
            'option_shuffle' => 'required|boolean',
            'allow_answer_change' => 'required|boolean',
            'grouping_mode' => ['required', Rule::in(['none', 'subject', 'section'])],
            'timer_mode' => ['required', Rule::in(['none', 'subject', 'section'])],
            'is_subject_timer' => 'nullable|boolean',
            'proctor' => 'required|boolean',
            'calculator_allowed' => 'required|boolean',
            'negative_marking' => 'required|boolean',
            'groups' => 'nullable|array',
            'groups.*' => ['integer', Rule::exists('groups', 'id')->where(fn ($query) => $query->where('organization_id', \App\Support\Tenant::id()))],
            'packages' => 'nullable|array',
            'packages.*' => ['integer', Rule::exists('packages', 'id')->where(fn ($query) => $query->where('organization_id', \App\Support\Tenant::id()))],
            'language_ids' => 'nullable|array',
            'language_ids.*' => ['integer', Rule::exists('languages', 'id')->where(fn ($query) => $query->where('organization_id', \App\Support\Tenant::id())->where('is_enabled', true))],
            'tolerance_count' => 'nullable|integer|min:0',
            'source_question_pdf' => 'nullable|file|mimes:pdf|max:51200',
            'source_answer_pdf' => 'nullable|file|mimes:pdf|max:51200',
            'source_combined_pdf' => 'nullable|file|mimes:pdf|max:51200',
            'source_question_url' => 'nullable|url|max:2000',
            'source_answer_url' => 'nullable|url|max:2000',
            'source_combined_url' => 'nullable|url|max:2000',
            'remove_source_ids' => 'nullable|array',
            'remove_source_ids.*' => 'integer',
            'category_level_1' => 'sometimes|nullable|integer',
            'category_level_2' => 'sometimes|nullable|integer',
            'meta_title' => 'nullable|string|max:191',
            'meta_description' => 'nullable|string',
            'meta_keywords' => 'nullable|string',
            'canonical_url' => 'nullable|string|max:2048',
            'og_title' => 'nullable|string|max:191',
            'og_description' => 'nullable|string',
            'og_image' => 'nullable|string|max:2048',
            'robots_meta' => ['nullable', Rule::in(['index,follow', 'noindex,follow', 'index,nofollow', 'noindex,nofollow'])],
            'seo_schema' => 'nullable|string',
        ]);

        $scope = app(ExamScopeService::class)->resolve(
            (int) \App\Support\Tenant::id(),
            (array) ($validated['packages'] ?? []),
            (array) ($validated['groups'] ?? []),
            ! empty($validated['category_level_1']) ? (int) $validated['category_level_1'] : null,
            ! empty($validated['category_level_2']) ? (int) $validated['category_level_2'] : null,
        );
        $languageIds = app(ExamLanguageService::class)->normalizeIds(
            (int) \App\Support\Tenant::id(),
            (array) ($validated['language_ids'] ?? [])
        );
        app(CurriculumTaxonomyService::class)->validateSelection(
            (int) \App\Support\Tenant::id(), $scope['group_ids'],
            ! empty($validated['test_subject_id']) ? (int) $validated['test_subject_id'] : null,
            ! empty($validated['test_topic_id']) ? (int) $validated['test_topic_id'] : null,
            ! empty($validated['test_stopic_id']) ? (int) $validated['test_stopic_id'] : null
        );
        $data = collect($validated)->except(['groups', 'packages', 'language_ids', 'source_question_pdf', 'source_answer_pdf', 'source_combined_pdf', 'source_question_url', 'source_answer_url', 'source_combined_url', 'remove_source_ids'])->all();
        $data['category_level_1'] = $scope['category_level_1'];
        $data['category_level_2'] = $scope['category_level_2'];
        $data['slug'] = $this->uniqueExamSlug($request->input('slug') ?: $validated['name'], $exam->id);
        $data['passing_percentage'] = $request->filled('passing_percentage') ? (int) $validated['passing_percentage'] : 0;
        $data['tolerance_count'] = $request->filled('tolerance_count') ? (int) $validated['tolerance_count'] : 0;
        if (! in_array($data['test_type'], [Exam::TEST_TYPE_SUBJECT, Exam::TEST_TYPE_TOPIC, Exam::TEST_TYPE_SUBTOPIC], true)) {
            $data['test_subject_id'] = null;
        }
        if (! in_array($data['test_type'], [Exam::TEST_TYPE_TOPIC, Exam::TEST_TYPE_SUBTOPIC], true)) {
            $data['test_topic_id'] = null;
        }
        if ($data['test_type'] !== Exam::TEST_TYPE_SUBTOPIC) {
            $data['test_stopic_id'] = null;
        }


        foreach ($booleanFields as $field) {
            $data[$field] = $request->boolean($field);
        }
        $data['attempt_count'] = (int) $validated['attempt_count'];
        $data['duration'] = (int) $validated['duration'];

        DB::transaction(function () use ($exam, $data, $scope, $languageIds, $booleanFields) {
            // Persist the validated scalar payload directly and verify the raw
            // database values. This avoids a cast masking a failed legacy value.
            $data['updated_at'] = now();
            DB::table($exam->getTable())
                ->where($exam->getKeyName(), $exam->getKey())
                ->where('organization_id', \App\Support\Tenant::id())
                ->update($data);

            app(ExamScopeService::class)->sync($exam, $scope);
            app(ExamLanguageService::class)->sync($exam, $languageIds);

            $expectedIntegers = [
                'attempt_count' => $data['attempt_count'],
                'duration' => $data['duration'],
                'passing_percentage' => $data['passing_percentage'],
                'tolerance_count' => $data['tolerance_count'],
            ];
            foreach ($booleanFields as $field) {
                $expectedIntegers[$field] = $data[$field] ? 1 : 0;
            }

            $persisted = DB::table($exam->getTable())
                ->where($exam->getKeyName(), $exam->getKey())
                ->where('organization_id', \App\Support\Tenant::id())
                ->first(array_keys($expectedIntegers));

            if (! $persisted) {
                throw new \RuntimeException('The exam could not be found after saving.');
            }

            foreach ($expectedIntegers as $field => $expected) {
                if ((int) $persisted->{$field} !== (int) $expected) {
                    throw new \RuntimeException("The exam setting '{$field}' could not be persisted.");
                }
            }

        });
        $this->syncQualitySources($request, $exam);

        return redirect()->route('exams.index')->with('success', 'Exam updated successfully.');
    }
    public function updateAttemptLimit(Request $request, Exam $exam)
    {
        $this->ensureTenantOwns($exam);

        $currentUser = Auth::user();
        if ($currentUser && $currentUser->ugroup_id != 0) {
            $assignedGroupIds = getUserGroupIds();
            if (! empty($assignedGroupIds) && empty(array_intersect($assignedGroupIds, $this->effectiveExamGroupIds($exam)))) {
                abort(403, 'You do not have permission to edit this exam.');
            }
        }

        $validated = $request->validate([
            'attempt_count' => 'required|integer|min:0',
        ]);
        $attemptCount = (int) $validated['attempt_count'];

        DB::table($exam->getTable())
            ->where($exam->getKeyName(), $exam->getKey())
            ->where('organization_id', \App\Support\Tenant::id())
            ->update([
                'attempt_count' => $attemptCount,
                'updated_at' => now(),
            ]);

        $persistedAttemptCount = (int) DB::table($exam->getTable())
            ->where($exam->getKeyName(), $exam->getKey())
            ->value('attempt_count');

        abort_if($persistedAttemptCount !== $attemptCount, 500, 'The attempt limit could not be saved.');

        return response()->json([
            'success' => true,
            'attempt_count' => $persistedAttemptCount,
            'label' => $persistedAttemptCount > 0
                ? $persistedAttemptCount.' attempts/student'
                : 'Unlimited attempts',
        ]);
    }

    public function destroy(Exam $exam)
    {
        $this->ensureTenantOwns($exam);
        // ðŸ”’ Security Check: Staff apne group ka hi exam delete kare
        $currentUser = Auth::user();
        if ($currentUser && $currentUser->ugroup_id != 0) {
            $assignedGroupIds = getUserGroupIds();
            $examGroups = $exam->groups->pluck('id')->toArray();
            
            if (! empty($assignedGroupIds) && empty(array_intersect($assignedGroupIds, $examGroups))) {
                return redirect()->route('exams.index')->with('error', 'You do not have permission to delete this exam.');
            }
        }

        $exam->delete();
        return redirect()->route('exams.index')->with('success', 'Exam deleted successfully.');
    }

    public function toggleStatus(Exam $exam)
    {
        $this->ensureTenantOwns($exam);
        $exam->status = $exam->status == 'Active' ? 'Inactive' : 'Active';
        $exam->save();
        return response()->json(['success' => true]);
    }

    public function addQuestions(Request $request, Exam $exam)
    {
        $this->ensureTenantOwns($exam);
        $tenantId = \App\Support\Tenant::id();
        $groups = Cache::remember("exam-question-groups:{$tenantId}", now()->addMinutes(5), fn () =>
            Group::where('organization_id', $tenantId)->orderBy('group_name')->get(['id', 'group_name'])
        );
        $dependentFilterValues = $request->only([
            'group',
            'category',
            'subcategory',
            'package',
            'exam_filter',
            'subject',
            'topic',
        ]);
        $dependentFilterKey = hash('sha256', json_encode($dependentFilterValues));
        $dependentOptions = Cache::remember(
            "exam-question-options:{$tenantId}:{$dependentFilterKey}",
            now()->addMinutes(2),
            fn () => $this->dependentFilterOptionData($request, $tenantId)
        );
        $parentCategories = $dependentOptions['categories'];
        $childCategories = $dependentOptions['subcategories'];
        $packages = $dependentOptions['packages'];
        $exams = $dependentOptions['exams'];
        $subjects = $dependentOptions['subjects'];
        $topics = $dependentOptions['topics'];
        $stopics = $dependentOptions['stopics'];
        $qtypes = \App\Models\Qtype::displayOrdered();
        $diffs = \App\Models\Diff::orderBy('diff_level')->get();
        $questionTags = \App\Models\QuestionTag::where(function ($query) use ($tenantId) {
            $query->whereNull('organization_id')
                ->orWhere('organization_id', $tenantId);
        })
            ->where('status', true)
            ->orderBy('name')
            ->get();
        $languages = \App\Models\Language::enabledForOrganization($tenantId)->orderBy('name')->get();
        $statusOptions = [
            'Yes' => 'Active',
            'No' => 'Inactive',
        ];
    
        $query = Question::query()
            ->select([
                'questions.id',
                'questions.organization_id',
                'questions.subject_id',
                'questions.topic_id',
                'questions.stopic_id',
                'questions.qtype_id',
                'questions.diff_id',
                'questions.language_id',
                'questions.passage_id',
                'questions.question',
                'questions.marks',
                'questions.negative_marks',
                'questions.status',
                'questions.created_at',
            ])
            ->with([
                'subject:id,subject_name',
                'topic:id,name',
                'stopic:id,name',
                'qtype:id,question_type',
                'diff:id,diff_level',
            ])
            ->withExists(['exams as is_attached' => fn ($attachedQuery) => $attachedQuery->where('exams.id', $exam->id)])
            ->where('organization_id', $tenantId)
            ->when($request->filled('group'), function ($q) use ($request) {
                $q->whereHas('groups', fn($groupQuery) => $groupQuery->where('groups.id', $request->group));
            })
            ->when($request->filled('category'), function ($q) use ($request) {
                $q->whereHas('exams', function ($examQuery) use ($request) {
                    $examQuery->where('category_level_1', $request->category)
                        ->orWhereHas('packages', fn($packageQuery) => $packageQuery->where('category_level_1', $request->category));
                });
            })
            ->when($request->filled('subcategory'), function ($q) use ($request) {
                $q->whereHas('exams', function ($examQuery) use ($request) {
                    $examQuery->where('category_level_2', $request->subcategory)
                        ->orWhereHas('packages', fn($packageQuery) => $packageQuery->where('category_level_2', $request->subcategory));
                });
            })
            ->when($request->filled('package'), function ($q) use ($request) {
                $q->whereHas('exams.packages', fn($packageQuery) => $packageQuery->where('packages.id', $request->package));
            })
            ->when($request->filled('exam_filter'), function ($q) use ($request) {
                $q->whereHas('exams', fn($examQuery) => $examQuery->where('exams.id', $request->exam_filter));
            })
            ->when($request->filled('subject'), fn($q) => $q->where('subject_id', $request->subject))
            ->when($request->filled('topic'), fn($q) => $q->where('topic_id', $request->topic))
            ->when($request->filled('subtopic'), fn($q) => $q->where('stopic_id', $request->subtopic))
            ->when($request->filled('qtype'), fn($q) => $q->where('qtype_id', $request->qtype))
            ->when($request->filled('diff'), fn($q) => $q->where('diff_id', $request->diff))
            ->when($request->filled('tag'), fn($q) => $q->whereHas('tags', fn($tagQuery) => $tagQuery->where('question_tags.id', $request->tag)))
            ->when($request->filled('language'), fn($q) => $q->where('language_id', $request->language))
            ->when($request->filled('status'), fn($q) => $q->where('status', $request->status))
            ->when($request->filled('marks_min'), fn($q) => $q->where('marks', '>=', $request->marks_min))
            ->when($request->filled('marks_max'), fn($q) => $q->where('marks', '<=', $request->marks_max))
            ->when($request->filled('negative_marks_min'), fn($q) => $q->where('negative_marks', '>=', $request->negative_marks_min))
            ->when($request->filled('negative_marks_max'), fn($q) => $q->where('negative_marks', '<=', $request->negative_marks_max))
            ->when($request->filled('has_image'), function ($q) use ($request) {
                $imageMatch = function ($query) {
                    $query->where('question', 'like', '%<img%')
                        ->orWhere('question', 'like', '%.png%')
                        ->orWhere('question', 'like', '%.jpg%')
                        ->orWhere('question', 'like', '%.jpeg%')
                        ->orWhere('question', 'like', '%.webp%')
                        ->orWhere('question', 'like', '%.gif%');
                };

                if ($request->has_image === 'yes') {
                    $q->where($imageMatch);
                } elseif ($request->has_image === 'no') {
                    $q->where(function ($query) {
                        $query->where('question', 'not like', '%<img%')
                            ->where('question', 'not like', '%.png%')
                            ->where('question', 'not like', '%.jpg%')
                            ->where('question', 'not like', '%.jpeg%')
                            ->where('question', 'not like', '%.webp%')
                            ->where('question', 'not like', '%.gif%');
                    });
                }
            })
            ->when($request->filled('has_passage'), function ($q) use ($request) {
                if ($request->has_passage === 'yes') {
                    $q->whereNotNull('passage_id');
                } elseif ($request->has_passage === 'no') {
                    $q->whereNull('passage_id');
                }
            })
            ->when($request->filled('ai_generated') && \Illuminate\Support\Facades\Schema::hasColumn('questions', 'ai_generated'), function ($q) use ($request) {
                if ($request->ai_generated === 'yes') {
                    $q->whereNotNull('ai_generated')->where('ai_generated', '!=', '');
                } elseif ($request->ai_generated === 'no') {
                    $q->where(function ($query) {
                        $query->whereNull('ai_generated')->orWhere('ai_generated', '');
                    });
                }
            })
            ->when($request->filled('question'), fn($q) => $q->where('question', 'like', "%{$request->question}%"));
    
        $perPage = (int) $request->input('per_page', 50);
        $perPage = in_array($perPage, [50, 100, 500], true) ? $perPage : 50;
        $questions = $query
            ->orderByDesc('questions.created_at')
            ->orderByDesc('questions.id')
            ->simplePaginate($perPage)
            ->withQueryString();

        return view('exams.add_questions', compact('exam', 'questions', 'groups', 'parentCategories', 'childCategories', 'packages', 'exams', 'subjects', 'topics', 'stopics', 'qtypes', 'diffs', 'questionTags', 'languages', 'statusOptions', 'perPage'));
    }

    public function toggleQuestion(Request $request, Exam $exam)
    {
        $this->ensureTenantOwns($exam);
        $request->validate(['question_id' => 'required|exists:questions,id']);
        $question = Question::where('organization_id', \App\Support\Tenant::id())->with('questionSection')->findOrFail($request->question_id);
        $attached = $exam->questions()->where('questions.id', $question->id)->exists();
        if ($attached) {
            $exam->questions()->detach($question->id);
        } else {
            $section = $this->examSectionForDefinition($exam, $question->questionSection);
            $exam->questions()->attach($question->id, ['exam_section_id' => $section?->id]);
        }
        Cache::forget("exam_{$exam->id}");
        return response()->json(['success' => true, 'attached' => ! $attached]);
    }
    public function bulkAddQuestions(Request $request, Exam $exam)
    {
        $this->ensureTenantOwns($exam);
        $validated = $request->validate(['question_ids' => ['required', 'array', 'min:1'], 'question_ids.*' => ['integer', 'exists:questions,id']]);
        $questions = Question::where('organization_id', \App\Support\Tenant::id())->whereIn('id', $validated['question_ids'])->with('questionSection')->get();
        if ($questions->isEmpty()) return response()->json(['success' => false, 'message' => 'No valid questions were selected.'], 422);
        $existingIds = $exam->questions()->whereIn('questions.id', $questions->pluck('id'))->pluck('questions.id')->map(fn ($id) => (int) $id);
        $payload = [];
        foreach ($questions->whereNotIn('id', $existingIds) as $question) {
            $section = $this->examSectionForDefinition($exam, $question->questionSection);
            $payload[$question->id] = ['exam_section_id' => $section?->id];
        }
        if ($payload) $exam->questions()->attach($payload);
        Cache::forget("exam_{$exam->id}");
        $addedCount = count($payload);
        return response()->json(['success' => true, 'added_count' => $addedCount, 'message' => $addedCount.' question(s) added to the exam.']);
    }
    public function view(Exam $exam)
    {
        $this->ensureTenantOwns($exam);
        return view('exams.view', compact('exam'));
    }

    public function previewPaper(Exam $exam, ManualExamPaperService $manual)
    {
        $this->ensureTenantOwns($exam);
        $items = $manual->paperItems($exam, (int) auth()->id(), false);

        return response()->view('question-drafts.paper-preview', [
            'questions' => $items,
            'title' => (string) $exam->name,
            'contextLabel' => 'Complete exam paper',
            'backUrl' => route('exams.viewQuestions', $exam),
            'paperEditUrl' => (user_can_route_action('questions.edit', 'edit') || user_can_route_action('exams.edit', 'edit')) ? route('exams.paper.edit', $exam) : null,
        ])->header('Cache-Control', 'private, no-store, max-age=0');
    }

    public function editPaper(Exam $exam, ManualExamPaperService $manual, QuestionRepairService $repairs)
    {
        $this->ensureCanEditPaper();
        $this->ensureTenantOwns($exam);
        $items = $manual->prepare($exam, (int) auth()->id())->values()->map(function (QuestionRepairDraft $draft, int $index) use ($repairs) {
            $draft->loadMissing('question.qtype');
            $original = (array) ($draft->original_payload ?: $repairs->snapshot($draft->question));

            return [
                'draft' => $draft,
                'original' => $original,
                'payload' => array_replace($original, (array) $draft->proposed_payload),
                'label' => 'Paper Q. '.($index + 1).' - Question ID '.$draft->question_id,
                'type' => (string) ($draft->question?->qtype?->question_type ?: $draft->question?->qtype?->type ?: 'Question'),
                'crop_page' => $repairs->detectedSourcePage($draft),
            ];
        });
        $hasPdfSource = ExamQualitySource::where('organization_id', $exam->organization_id)
            ->where('exam_id', $exam->id)->where('is_active', true)
            ->whereIn('kind', ['file', 'url'])->whereIn('role', ['questions', 'combined', 'answers'])->exists();

        return response()->view('exams.paper-editor', compact('exam', 'items', 'hasPdfSource'))
            ->header('Cache-Control', 'private, no-store, max-age=0');
    }

    public function editPaperQuestion(Exam $exam, QuestionRepairDraft $draft, ManualExamPaperService $manual, QuestionRepairService $repairs)
    {
        $this->ensureCanEditPaper();
        $this->ensureTenantOwns($exam);
        $draft->loadMissing(['audit', 'question.qtype']);
        $manual->assertOwned($exam, $draft);
        $original = (array) ($draft->original_payload ?: $repairs->snapshot($draft->question));
        $payload = array_replace($original, (array) $draft->proposed_payload);
        $sharedExams = $draft->question->exams()->count();
        $cropDefaultPage = $repairs->detectedSourcePage($draft);
        $hasPdfSource = ExamQualitySource::where('organization_id', $exam->organization_id)
            ->where('exam_id', $exam->id)->where('is_active', true)
            ->whereIn('kind', ['file', 'url'])->whereIn('role', ['questions', 'combined', 'answers'])->exists();

        return view('exams.paper-question-draft', compact('exam', 'draft', 'original', 'payload', 'sharedExams', 'cropDefaultPage', 'hasPdfSource'));
    }

    public function paperDraftSourcePage(Request $request, Exam $exam, QuestionRepairDraft $draft, ManualExamPaperService $manual, QuestionRepairService $repairs)
    {
        $this->ensureCanEditPaper();
        $this->ensureTenantOwns($exam);
        $draft->loadMissing('audit');
        $manual->assertOwned($exam, $draft);
        $data = $request->validate([
            'page' => ['required', 'integer', 'min:1'],
            'source_role' => ['nullable', Rule::in(['questions', 'answers', 'combined'])],
        ]);
        try {
            return response()->file(
                $repairs->renderSourcePage($draft, (int) $data['page'], $data['source_role'] ?? 'questions'),
                ['Cache-Control' => 'private, no-store, max-age=0']
            )->deleteFileAfterSend(true);
        } catch (\Throwable $exception) {
            abort(422, $exception->getMessage());
        }
    }

    public function cropPaperQuestion(Request $request, Exam $exam, QuestionRepairDraft $draft, ManualExamPaperService $manual, QuestionRepairService $repairs)
    {
        $this->ensureCanEditPaper();
        $this->ensureTenantOwns($exam);
        $draft->loadMissing(['audit', 'question']);
        $manual->assertOwned($exam, $draft);
        $targets = ['question', 'option1', 'option2', 'option3', 'option4', 'option5', 'option6', 'explanation'];
        $data = $request->validate([
            'page' => ['required', 'integer', 'min:1'],
            'source_role' => ['required', Rule::in(['questions', 'answers', 'combined'])],
            'target_field' => ['required', Rule::in($targets)],
            'next_target' => ['nullable', Rule::in($targets)],
            'background_mode' => ['nullable', Rule::in(['white', 'transparent'])],
            'crop_mode' => ['nullable', Rule::in(['image', 'mathpix'])],
            'confirmed' => ['nullable', 'boolean'],
            'recognized_html' => ['nullable', 'string', 'max:100000'],
            'mathpix_request_id' => ['nullable', 'string', 'max:255'],
            'mathpix_confidence' => ['nullable', 'numeric', 'between:0,100'],
            'x0' => ['required', 'numeric', 'between:0,1'], 'y0' => ['required', 'numeric', 'between:0,1'],
            'x1' => ['required', 'numeric', 'between:0,1'], 'y1' => ['required', 'numeric', 'between:0,1'],
        ]);
        if ((float) $data['x0'] >= (float) $data['x1'] || (float) $data['y0'] >= (float) $data['y1']) {
            throw \Illuminate\Validation\ValidationException::withMessages(['crop' => 'Draw a valid crop region on the source page.']);
        }
        if (($data['crop_mode'] ?? 'image') === 'mathpix') {
            try {
                if (! empty($data['confirmed'])) {
                    $evidence = $repairs->applyManualOcrText($draft, $data['target_field'], (string) ($data['recognized_html'] ?? ''), [
                        'provider' => 'mathpix', 'request_id' => $data['mathpix_request_id'] ?? null,
                        'confidence' => isset($data['mathpix_confidence']) ? (float) $data['mathpix_confidence'] : null,
                        'source_page' => (int) $data['page'], 'source_role' => $data['source_role'],
                        'bbox_normalized' => [(float)$data['x0'],(float)$data['y0'],(float)$data['x1'],(float)$data['y1']],
                    ]);
                    return response()->json(['message' => ucfirst($data['target_field']).' Mathpix text inserted and saved to the draft.', 'target' => $data['target_field'], 'html' => data_get($draft->fresh()->proposed_payload, $data['target_field']), 'recognition' => $evidence]);
                }
                $image = $repairs->extractMathpixCrop($draft, (int) $data['page'], [(float)$data['x0'],(float)$data['y0'],(float)$data['x1'],(float)$data['y1']], $data['source_role']);
                try { $recognition = app(\App\Services\MathpixOcrService::class)->recognize($image, (int) $draft->organization_id); }
                finally { @unlink($image); }
                return response()->json(['message' => 'Mathpix recognition is ready for review.', 'target' => $data['target_field'], 'preview_required' => true, 'recognition' => $recognition]);
            } catch (\Throwable $exception) { return response()->json(['message' => $exception->getMessage()], 422); }
        }
        try {
            $crop = $repairs->applyManualImageCrop(
                $draft,
                (int) $data['page'],
                [(float) $data['x0'], (float) $data['y0'], (float) $data['x1'], (float) $data['y1']],
                $data['target_field'],
                $data['source_role'],
                $data['background_mode'] ?? 'white'
            );
            $fresh = $draft->fresh();
            $message = ucfirst($data['target_field']).' crop auto-saved to the manual draft.';
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => $message,
                    'target' => $data['target_field'],
                    'next_target' => $data['next_target'] ?? $data['target_field'],
                    'html' => data_get($fresh->proposed_payload, $data['target_field']),
                    'crop' => $crop,
                ]);
            }
            return redirect()->route('exams.paper.questions.edit', [
                'exam' => $exam, 'draft' => $draft, 'crop_page' => (int) $data['page'],
                'crop_target' => $data['next_target'] ?? $data['target_field'], 'source_role' => $data['source_role'],
            ])->with('success', $message);
        } catch (\Throwable $exception) {
            if ($request->expectsJson()) return response()->json(['message' => $exception->getMessage()], 422);
            return back()->withInput()->with('error', $exception->getMessage());
        }
    }

    public function removePaperQuestionImage(Request $request, Exam $exam, QuestionRepairDraft $draft, ManualExamPaperService $manual)
    {
        $this->ensureCanEditPaper();
        $this->ensureTenantOwns($exam);
        $draft->loadMissing(['audit', 'question']);
        $manual->assertOwned($exam, $draft);
        $data = $request->validate([
            'field' => ['required', Rule::in(['question', 'option1', 'option2', 'option3', 'option4', 'option5', 'option6', 'explanation'])],
        ]);
        $payload = array_replace((array) $draft->original_payload, (array) $draft->proposed_payload);
        $current = (string) ($payload[$data['field']] ?? '');
        $cleaned = preg_replace('/<img\b[^>]*>/i', '', $current) ?? $current;
        $updated = $manual->update($draft, [$data['field'] => $cleaned]);

        return response()->json([
            'message' => 'Image removed from '.str_replace('option', 'Option ', $data['field']).' and saved to the draft.',
            'field' => $data['field'],
            'html' => data_get($updated->proposed_payload, $data['field'], ''),
            'changed_fields' => (array) $updated->changed_fields,
        ]);
    }

    public function updatePaperQuestion(Request $request, Exam $exam, QuestionRepairDraft $draft, ManualExamPaperService $manual)
    {
        $this->ensureCanEditPaper();
        $this->ensureTenantOwns($exam);
        $draft->loadMissing(['audit', 'question']);
        $manual->assertOwned($exam, $draft);
        $data = $request->validate(['proposed' => ['required', 'array']]);
        $proposed = collect($data['proposed'])->only(QuestionRepairService::FIELDS)->map(function ($value, $field) {
            if ($field === 'correct_option_indices') {
                $decoded = is_array($value) ? $value : json_decode((string) $value, true);
                if (! is_array($decoded)) {
                    throw \Illuminate\Validation\ValidationException::withMessages(['proposed.correct_option_indices' => 'Enter valid JSON option numbers, for example [1, 3].']);
                }
                return collect($decoded)->map(fn ($index) => (int) $index)->filter(fn ($index) => $index >= 1 && $index <= 6)->unique()->sort()->values()->all();
            }
            return $value;
        })->all();
        $updated = $manual->update($draft, $proposed);
        $message = 'Manual changes saved as a draft. The live paper is unchanged until publication.';
        if ($request->expectsJson()) {
            return response()->json([
                'message' => $message,
                'draft_id' => $updated->id,
                'changed_fields' => (array) $updated->changed_fields,
            ]);
        }

        return back()->with('success', $message);
    }

    public function publishPaperQuestion(Exam $exam, QuestionRepairDraft $draft, ManualExamPaperService $manual, QuestionRepairService $repairs)
    {
        $this->ensureCanEditPaper();
        $this->ensureTenantOwns($exam);
        $draft->loadMissing(['audit', 'question']);
        $manual->assertOwned($exam, $draft);
        try {
            $repairs->publish($draft, (int) auth()->id(), true);
            return redirect()->route('exams.paper.edit', $exam)->with('success', 'Question published with a restorable version.');
        } catch (\Throwable $exception) {
            return back()->with('error', $exception->getMessage());
        }
    }

    public function publishPaper(Exam $exam, ManualExamPaperService $manual)
    {
        $this->ensureCanEditPaper();
        $this->ensureTenantOwns($exam);
        try {
            $count = $manual->publishChanged($exam, (int) auth()->id());
            return redirect()->route('exams.paper.edit', $exam)->with('success', $count.' changed question(s) published with version history.');
        } catch (\Throwable $exception) {
            return back()->with('error', $exception->getMessage());
        }
    }
    public function viewQuestions(Request $request, Exam $exam)
    {
        $this->ensureTenantOwns($exam);
        $subjects = $this->tenantSubjects()->orderBy('subject_name')->get();
        $topics = $this->tenantTopics()->orderBy('name')->get();
        $stopics = $this->tenantStopics()->orderBy('name')->get();
        $qtypes = \App\Models\Qtype::displayOrdered();
        $diffs = \App\Models\Diff::orderBy('diff_level')->get();
    
        $sections = $exam->sections()->get();
        $availableSections = $this->availableSectionDefinitions($exam);
        $questions = $exam->questions()
            ->with(['subject', 'topic', 'stopic', 'qtype', 'diff'])
            ->when($request->subject, fn($q, $subject) => $q->where('subject_id', $subject))
            ->when($request->topic, fn($q, $topic) => $q->where('topic_id', $topic))
            ->when($request->subtopic, fn($q, $subtopic) => $q->where('stopic_id', $subtopic))
            ->when($request->qtype, fn($q, $qtype) => $q->where('qtype_id', $qtype))
            ->when($request->diff, fn($q, $diff) => $q->where('diff_id', $diff))
            ->when($request->question, fn($q, $question) => $q->where('question', 'like', '%'.$question.'%'))
            ->when($request->filled('section'), function ($query) use ($request) {
                return $request->section === 'general'
                    ? $query->wherePivotNull('exam_section_id')
                    : $query->wherePivot('exam_section_id', $request->integer('section'));
            })
            ->get();

        return view('exams.view_questions', compact('exam', 'questions', 'subjects', 'topics', 'stopics', 'qtypes', 'diffs', 'sections', 'availableSections'));
    }

    public function storeSection(Request $request, Exam $exam)
    {
        $this->ensureTenantOwns($exam);
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:191', Rule::unique('exam_sections')->where('exam_id', $exam->id)],
            'display_order' => 'nullable|integer|min:0',
            'duration' => 'nullable|integer|min:1',
        ]);
        $this->validateSectionDurationTotal($exam, $validated['duration'] ?? null);
        $exam->sections()->create($validated);
        Cache::forget("exam_{$exam->id}");

        return back()->with('success', 'Section created successfully.');
    }

    public function updateSection(Request $request, Exam $exam, ExamSection $section)
    {
        $this->ensureTenantOwns($exam);
        abort_unless((int) $section->exam_id === (int) $exam->id, 404);
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:191', Rule::unique('exam_sections')->where('exam_id', $exam->id)->ignore($section->id)],
            'display_order' => 'nullable|integer|min:0',
            'duration' => 'nullable|integer|min:1',
        ]);
        $this->validateSectionDurationTotal($exam, $validated['duration'] ?? null, $section->id);
        $section->update($validated);
        Cache::forget("exam_{$exam->id}");

        return back()->with('success', 'Section updated successfully.');
    }

    public function destroySection(Exam $exam, ExamSection $section)
    {
        $this->ensureTenantOwns($exam);
        abort_unless((int) $section->exam_id === (int) $exam->id, 404);
        $section->delete();
        Cache::forget("exam_{$exam->id}");

        return back()->with('success', 'Section deleted. Its questions were moved to General.');
    }

    public function assignQuestionSections(Request $request, Exam $exam)
    {
        $this->ensureTenantOwns($exam);
        $validated = $request->validate(['question_ids' => 'required|array|min:1', 'question_ids.*' => 'integer', 'question_section_id' => 'nullable|integer']);
        $examSectionId = null;
        if (! empty($validated['question_section_id'])) {
            $definition = $this->availableSectionDefinitions($exam)->firstWhere('id', (int) $validated['question_section_id']);
            abort_unless($definition, 422, 'The selected section is not linked to this exam group.');
            $examSectionId = $this->examSectionForDefinition($exam, $definition)->id;
        }
        $updated = DB::table('exam_questions')->where('exam_id', $exam->id)->whereIn('question_id', $validated['question_ids'])
            ->update(['exam_section_id' => $examSectionId, 'updated_at' => now()]);
        Cache::forget("exam_{$exam->id}");
        return back()->with('success', $updated.' question(s) assigned to '.($examSectionId ? 'the selected section' : 'General').'.');
    }
    private function validateSectionDurationTotal(Exam $exam, ?int $duration, ?int $exceptSectionId = null): void
    {
        $savedTotal = (int) $exam->sections()
            ->when($exceptSectionId, fn ($query) => $query->where('id', '!=', $exceptSectionId))
            ->sum('duration');
        if ($exam->duration > 0 && $savedTotal + (int) $duration > (int) $exam->duration) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'duration' => 'The total of section durations cannot exceed the exam duration.',
            ]);
        }
    }
    public function getSubjects(Exam $exam)
    {
        $this->ensureTenantOwns($exam);
        $subjects = $exam->questions()
            ->with('subject')
            ->get()
            ->pluck('subject')
            ->whereNotNull('id') 
            ->unique('id')
            ->values(); 

        $savedDurations = $exam->subjectDurations->pluck('duration', 'subject_id');

        $totalDuration = $exam->duration;
        $subjectCount = $subjects->count();
        
        $defaultSplit = ($subjectCount > 0 && $totalDuration > 0) ? floor($totalDuration / $subjectCount) : 0;

        return response()->json([
            'subjects' => $subjects,
            'saved_durations' => $savedDurations,
            'default_split' => $defaultSplit 
        ]);
    }

    // âœ… MODIFIED: Preserved Cache Logic from your upload
    public function setSectionWiseTimer(Request $request, Exam $exam)
    {
        $this->ensureTenantOwns($exam);
        $request->validate([
            'subject_ids' => 'required|array',
            'durations' => 'required|array',
        ]);
        
        $totalDuration = array_sum($request->durations);
        if ($totalDuration > $exam->duration) {
            return redirect()->back()->with('error', 'Total duration of subjects cannot exceed the total exam duration.');
        }

        foreach ($request->subject_ids as $key => $subjectId) {
            $exam->subjectDurations()->updateOrCreate(
                ['subject_id' => $subjectId],
                ['duration' => $request->durations[$key]]
            );
        }

        // Feature: Force enable section timer flag
        $exam->update(['is_subject_timer' => true, 'timer_mode' => 'subject', 'grouping_mode' => 'subject']);

        // Feature: Clear cache immediately
        Cache::forget("exam_{$exam->id}");

        return redirect()->back()->with('success', 'Subject-wise timer set successfully.');
    }

    public function toggleResultStatus($id)
    {
        $exam = Exam::where('organization_id', \App\Support\Tenant::id())->findOrFail($id);
        $exam->result_after_finish = !$exam->result_after_finish;
        $exam->save();

        $status = $exam->result_after_finish ? 'Published (Visible to Students)' : 'Hidden (Awaited)';
        return back()->with('success', "Result status updated successfully: $status");
    }

    public function analytics(Exam $exam)
    {
        $this->ensureTenantOwns($exam);
        $tenantId = \App\Support\Tenant::id();
        $resultQuery = ExamResult::where('exam_id', $exam->id)
            ->where('organization_id', $tenantId);

        $totalAttempts = (clone $resultQuery)->count();
        
        if($totalAttempts == 0) {
            return back()->with('error', 'No attempts found for this exam yet.');
        }

        $passCount = (clone $resultQuery)->where('result', 'Pass')->count();
        $avgScore = (clone $resultQuery)->avg('percent');
        $maxScore = (clone $resultQuery)->max('percent');
        $minScore = (clone $resultQuery)->min('percent');
        $avgTime = (clone $resultQuery)->avg('total_test_time');

        $batchStats = [
            'total_attempts' => $totalAttempts,
            'pass_count'     => $passCount,
            'avg_score'      => $avgScore,
            'highest_score'  => $maxScore,
            'lowest_score'   => $minScore,
            'avg_time'       => round($avgTime / 60, 1),
        ];

        $killerQuestions = DB::table('exam_stats')
            ->join('questions', 'exam_stats.question_id', '=', 'questions.id')
            ->where('exam_stats.exam_id', $exam->id)
            ->where('exam_stats.organization_id', $tenantId)
            ->where('exam_stats.answered', 1)
            ->select(
                'questions.question',
                'questions.id',
                DB::raw('count(*) as total_attempts'),
                DB::raw('sum(case when exam_stats.ques_status = "R" then 1 else 0 end) as correct_count')
            )
            ->groupBy('questions.id', 'questions.question')
            ->havingRaw('(sum(case when exam_stats.ques_status = "R" then 1 else 0 end) / count(*)) < 0.40')
            ->orderBy('correct_count', 'asc')
            ->paginate(5, ['*'], 'killer_page');

        $killerQuestions->getCollection()->transform(function ($q) {
            $q->accuracy = $q->total_attempts > 0 ? round(($q->correct_count / $q->total_attempts) * 100) : 0;
            return $q;
        });

        $topicPerformance = DB::table('exam_stats')
            ->join('questions', 'exam_stats.question_id', '=', 'questions.id')
            ->join('topics', 'questions.topic_id', '=', 'topics.id')
            ->where('exam_stats.exam_id', $exam->id)
            ->where('exam_stats.organization_id', $tenantId)
            ->select(
                'topics.name',
                DB::raw('count(*) as total_q'),
                DB::raw('sum(case when exam_stats.ques_status = "R" then 1 else 0 end) as correct_q')
            )
            ->groupBy('topics.id', 'topics.name')
            ->orderByRaw('(sum(case when exam_stats.ques_status = "R" then 1 else 0 end) / count(*)) asc')
            ->limit(10) 
            ->get()
            ->map(function($t) {
                $t->accuracy = $t->total_q > 0 ? round(($t->correct_q / $t->total_q) * 100) : 0;
                return $t;
            });

        $atRiskStudents = (clone $resultQuery)
            ->with('student')
            ->orderBy('percent', 'asc') 
            ->paginate(10, ['*'], 'risk_page');

        $distribution = [
            '0-30%' => (clone $resultQuery)->whereBetween('percent', [0, 30])->count(),
            '31-60%' => (clone $resultQuery)->whereBetween('percent', [31, 60])->count(),
            '61-80%' => (clone $resultQuery)->whereBetween('percent', [61, 80])->count(),
            '81-100%' => (clone $resultQuery)->whereBetween('percent', [81, 100])->count(),
        ];

        return view('exams.analytics', compact(
            'exam', 'batchStats', 'killerQuestions', 'topicPerformance', 'atRiskStudents', 'distribution'
        ));
    }

    public function getExamPackages(Request $request) {
        $request->validate([
            'group_id' => 'required|integer|exists:groups,id',
        ]);

        Group::where('organization_id', \App\Support\Tenant::id())->findOrFail($request->group_id);

        $packages = Package::where('organization_id', \App\Support\Tenant::id())
        ->whereHas('groups', function ($query) use ($request) {
            $query->where('groups.id', $request->group_id);
        })
        ->select('id', 'name')
        ->orderBy('name')
        ->get();

        return response()->json([
            'packages' => $packages
        ]);
    }

    public function getExamExams(Request $request)
    {
        // Optional validation
        $request->validate([
            'group_id'   => 'required|integer|exists:groups,id',
            'package_id' => 'required|integer|exists:packages,id',
        ]);

        Group::where('organization_id', \App\Support\Tenant::id())->findOrFail($request->group_id);
        Package::where('organization_id', \App\Support\Tenant::id())->findOrFail($request->package_id);

        // Get exams that belong to the selected package
        // and (optionally) also belong to the selected group
        $exams = Exam::where('organization_id', \App\Support\Tenant::id())
            ->whereHas('packages', function ($query) use ($request) {
                $query->where('packages.id', $request->package_id);
            })
            ->whereHas('groups', function ($query) use ($request) {
                $query->where('groups.id', $request->group_id);
            })
            ->select('id', 'name')
            ->orderBy('name')
            ->get();

        return response()->json([
            'exams' => $exams
        ]);
    }

    public function dependentFilterOptions(Request $request)
    {
        return response()->json($this->dependentFilterOptionData($request, \App\Support\Tenant::id()));
    }

    private function dependentFilterOptionData(Request $request, int $organizationId): array
    {
        $groupId = $request->integer('group_id') ?: $request->integer('group') ?: null;
        $categoryId = $request->integer('category_id') ?: $request->integer('category') ?: null;
        $subcategoriesEnabled = subcategories_enabled();
        $subcategoryId = $subcategoriesEnabled ? ($request->integer('subcategory_id') ?: $request->integer('subcategory') ?: null) : null;
        $packageId = $request->integer('package_id') ?: $request->integer('package') ?: null;
        $examId = $request->integer('exam_id') ?: $request->integer('exam') ?: $request->integer('exam_filter') ?: null;
        $subjectId = $request->integer('subject_id') ?: $request->integer('subject') ?: null;
        $topicId = $request->integer('topic_id') ?: $request->integer('topic') ?: null;

        $subcategories = $subcategoriesEnabled ? Category::where('organization_id', $organizationId)
            ->whereNotNull('parent_id')
            ->where('status', 1)
            ->when($categoryId, fn ($query) => $query->where('parent_id', $categoryId))
            ->when($groupId, function ($query) use ($groupId, $categoryId, $organizationId) {
                $categoryIds = $this->categoryIdsForGroup($organizationId, $groupId);
                if ($categoryId) {
                    $categoryIds = $categoryIds->push($categoryId)->unique();
                }

                $query->whereIn('id', $categoryIds);
            })
            ->orderBy('title')
            ->get(['id', 'parent_id', 'title']) : collect();

        $categories = Category::where('organization_id', $organizationId)
            ->whereNull('parent_id')
            ->where('status', 1)
            ->when($groupId, function ($query) use ($organizationId, $groupId) {
                $query->whereIn('id', $this->categoryIdsForGroup($organizationId, $groupId));
            })
            ->orderBy('title')
            ->get(['id', 'title']);

        $packages = Package::where('organization_id', $organizationId)
            ->when($groupId, function ($query) use ($groupId) {
                $query->where(function ($groupQuery) use ($groupId) {
                    $groupQuery->whereHas('groups', fn ($packageGroupQuery) => $packageGroupQuery->where('groups.id', $groupId))
                        ->orWhereHas('exams.groups', fn ($examGroupQuery) => $examGroupQuery->where('groups.id', $groupId));
                });
            })
            ->when($categoryId, function ($query) use ($categoryId) {
                $query->where(function ($categoryQuery) use ($categoryId) {
                    $categoryQuery->where('category_level_1', $categoryId)
                        ->orWhereHas('exams', fn ($examQuery) => $examQuery->where('category_level_1', $categoryId));
                });
            })
            ->when($subcategoryId, function ($query) use ($subcategoryId) {
                $query->where(function ($subcategoryQuery) use ($subcategoryId) {
                    $subcategoryQuery->where('category_level_2', $subcategoryId)
                        ->orWhereHas('exams', fn ($examQuery) => $examQuery->where('category_level_2', $subcategoryId));
                });
            })
            ->orderBy('name')
            ->get(['id', 'name']);

        $exams = Exam::where('organization_id', $organizationId)
            ->when(Schema::hasColumn('exams', 'is_student_practice'), fn ($query) => $query->where('is_student_practice', false))
            ->when($groupId, function ($query) use ($groupId) {
                $query->where(function ($groupQuery) use ($groupId) {
                    $groupQuery->whereHas('groups', fn ($examGroupQuery) => $examGroupQuery->where('groups.id', $groupId))
                        ->orWhereHas('packages.groups', fn ($packageGroupQuery) => $packageGroupQuery->where('groups.id', $groupId));
                });
            })
            ->when($categoryId, function ($query) use ($categoryId) {
                $query->where(function ($categoryQuery) use ($categoryId) {
                    $categoryQuery->where('category_level_1', $categoryId)
                        ->orWhereHas('packages', fn ($packageQuery) => $packageQuery->where('category_level_1', $categoryId));
                });
            })
            ->when($subcategoryId, function ($query) use ($subcategoryId) {
                $query->where(function ($subcategoryQuery) use ($subcategoryId) {
                    $subcategoryQuery->where('category_level_2', $subcategoryId)
                        ->orWhereHas('packages', fn ($packageQuery) => $packageQuery->where('category_level_2', $subcategoryId));
                });
            })
            ->when($packageId, fn ($query) => $query->whereHas('packages', fn ($packageQuery) => $packageQuery->where('packages.id', $packageId)))
            ->orderBy('name')
            ->get(['id', 'name']);

        $questionScope = Question::where('organization_id', $organizationId)
            ->when($groupId, function ($query) use ($groupId) {
                $query->where(function ($groupQuery) use ($groupId) {
                    $groupQuery->whereHas('groups', fn ($questionGroupQuery) => $questionGroupQuery->where('groups.id', $groupId))
                        ->orWhereHas('exams.groups', fn ($examGroupQuery) => $examGroupQuery->where('groups.id', $groupId))
                        ->orWhereHas('exams.packages.groups', fn ($packageGroupQuery) => $packageGroupQuery->where('groups.id', $groupId));
                });
            })
            ->when($categoryId, function ($query) use ($categoryId) {
                $query->whereHas('exams', function ($examQuery) use ($categoryId) {
                    $examQuery->where('category_level_1', $categoryId)
                        ->orWhereHas('packages', fn ($packageQuery) => $packageQuery->where('category_level_1', $categoryId));
                });
            })
            ->when($subcategoryId, function ($query) use ($subcategoryId) {
                $query->whereHas('exams', function ($examQuery) use ($subcategoryId) {
                    $examQuery->where('category_level_2', $subcategoryId)
                        ->orWhereHas('packages', fn ($packageQuery) => $packageQuery->where('category_level_2', $subcategoryId));
                });
            })
            ->when($packageId, fn ($query) => $query->whereHas('exams.packages', fn ($packageQuery) => $packageQuery->where('packages.id', $packageId)))
            ->when($examId, fn ($query) => $query->whereHas('exams', fn ($examQuery) => $examQuery->where('exams.id', $examId)));

        $hasQuestionContext = $groupId || $categoryId || $subcategoryId || $packageId || $examId;
        $subjectIds = $hasQuestionContext ? (clone $questionScope)
            ->whereNotNull('subject_id')
            ->distinct()
            ->pluck('subject_id') : collect();
        $topicIds = $hasQuestionContext || $subjectId ? (clone $questionScope)
            ->when($subjectId, fn ($query) => $query->where('subject_id', $subjectId))
            ->whereNotNull('topic_id')
            ->distinct()
            ->pluck('topic_id') : collect();
        $stopicIds = $hasQuestionContext || $subjectId || $topicId ? (clone $questionScope)
            ->when($subjectId, fn ($query) => $query->where('subject_id', $subjectId))
            ->when($topicId, fn ($query) => $query->where('topic_id', $topicId))
            ->whereNotNull('stopic_id')
            ->distinct()
            ->pluck('stopic_id') : collect();

        $subjects = Subject::query()
            ->whereHas('groups', fn ($query) => $query->where('groups.organization_id', $organizationId))
            ->when($groupId, fn ($query) => $query->whereHas('groups', fn ($groupQuery) => $groupQuery->where('groups.id', $groupId)))
            ->when($hasQuestionContext, fn ($query) => $query->whereIn('id', $subjectIds))
            ->orderBy('subject_name')
            ->get(['id', 'subject_name']);

        $topics = \App\Models\Topic::query()
            ->whereHas('subject.groups', fn ($query) => $query->where('groups.organization_id', $organizationId))
            ->when($subjectId, fn ($query) => $query->where('subject_id', $subjectId))
            ->when($hasQuestionContext || $subjectId, fn ($query) => $query->whereIn('id', $topicIds))
            ->orderBy('name')
            ->get(['id', 'subject_id', 'name']);

        $stopics = \App\Models\Stopic::query()
            ->whereHas('subject.groups', fn ($query) => $query->where('groups.organization_id', $organizationId))
            ->when($subjectId, fn ($query) => $query->where('subject_id', $subjectId))
            ->when($topicId, fn ($query) => $query->where('topic_id', $topicId))
            ->when($hasQuestionContext || $subjectId || $topicId, fn ($query) => $query->whereIn('id', $stopicIds))
            ->orderBy('name')
            ->get(['id', 'subject_id', 'topic_id', 'name']);

        return [
            'categories' => $categories,
            'subcategories' => $subcategories,
            'packages' => $packages,
            'exams' => $exams,
            'subjects' => $subjects,
            'topics' => $topics,
            'stopics' => $stopics,
        ];
    }

    private function categoryIdsForGroup(int $organizationId, int $groupId)
    {
        $examCategoryIds = Exam::where('organization_id', $organizationId)
            ->where(function ($query) use ($groupId) {
                $query->whereHas('groups', fn ($groupQuery) => $groupQuery->where('groups.id', $groupId))
                    ->orWhereHas('packages.groups', fn ($packageGroupQuery) => $packageGroupQuery->where('groups.id', $groupId));
            })
            ->get(['category_level_1', 'category_level_2'])
            ->flatMap(fn ($exam) => [$exam->category_level_1, $exam->category_level_2]);

        $packageCategoryIds = Package::where('organization_id', $organizationId)
            ->where(function ($query) use ($groupId) {
                $query->whereHas('groups', fn ($groupQuery) => $groupQuery->where('groups.id', $groupId))
                    ->orWhereHas('exams.groups', fn ($examGroupQuery) => $examGroupQuery->where('groups.id', $groupId));
            })
            ->get(['category_level_1', 'category_level_2'])
            ->flatMap(fn ($package) => [$package->category_level_1, $package->category_level_2]);

        return $examCategoryIds->merge($packageCategoryIds)->filter()->unique()->values();
    }

    public function updateReportStatus(Request $request, QuestionsReport $report)
    {
        if ((int) ($report->organization_id ?? 0) !== (int) \App\Support\Tenant::id()) {
            abort(404);
        }

        $request->validate([
            'status' => [
                'required',
                Rule::in([
                    'Pending',
                    'In Progress',
                    'On Hold',
                    'Resolved',
                    'Closed',
                ]),
            ],
        ]);

        $report->update([
            'status' => $request->status,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Report status updated successfully.',
        ]);
    }

    private function uniqueExamSlug(string $value, ?int $ignoreId = null): string
    {
        $baseSlug = Str::slug($value) ?: 'exam';
        $slug = $baseSlug;
        $counter = 2;

        while (Exam::where('slug', $slug)
            ->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))
            ->exists()) {
            $slug = $baseSlug . '-' . $counter++;
        }

        return $slug;
    }
}

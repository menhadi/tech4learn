<?php

namespace App\Http\Controllers;

use App\Models\AiAnswerDraft;
use App\Models\AiAnswerRun;
use App\Models\AiCurriculumEvent;
use App\Models\Category;
use App\Models\Configuration;
use App\Models\Diff;
use App\Models\Exam;
use App\Models\Group;
use App\Models\Package;
use App\Models\Question;
use App\Models\QuestionVersion;
use App\Models\Stopic;
use App\Models\Subject;
use App\Models\Topic;
use App\Services\AiAnswerProcessLauncher;
use App\Services\AiAnswerService;
use App\Support\AiProvider;
use App\Support\SaasAccess;
use App\Support\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AiAnswerController extends Controller
{
    public function index()
    {
        $tenantId = Tenant::id();
        $groups = Group::where('organization_id', $tenantId)->displayOrdered()->get(['id', 'group_name']);
        $categories = Category::where('organization_id', $tenantId)
            ->with(['groups:id,group_name', 'packages.groups:id,group_name', 'subcategoryPackages.groups:id,group_name'])
            ->displayOrdered()->get(['id', 'parent_id', 'title']);
        $packages = Package::where('organization_id', $tenantId)
            ->with('groups:id,group_name')->displayOrdered()
            ->get(['id', 'name', 'category_level_1', 'category_level_2']);
        $categoryById = $categories->keyBy('id');
        $categoryOptions = $categories->map(function ($category) use ($categoryById) {
            $groupIds = $category->groups->pluck('id')
                ->merge($category->packages->flatMap(fn ($package) => $package->groups->pluck('id')))
                ->merge($category->subcategoryPackages->flatMap(fn ($package) => $package->groups->pluck('id')));
            if ($groupIds->isEmpty() && $category->parent_id) {
                $groupIds = $categoryById->get($category->parent_id)?->groups?->pluck('id') ?? collect();
            }
            return [
                'id' => (int) $category->id, 'parent_id' => $category->parent_id ? (int) $category->parent_id : null,
                'title' => $category->title, 'group_ids' => $groupIds->unique()->map(fn ($id) => (int) $id)->values(),
            ];
        })->values();
        $packageOptions = $packages->map(function ($package) use ($categoryById) {
            $groupIds = $package->groups->pluck('id')
                ->merge($categoryById->get($package->category_level_1)?->groups?->pluck('id') ?? collect())
                ->merge($categoryById->get($package->category_level_2)?->groups?->pluck('id') ?? collect());
            return [
                'id' => (int) $package->id, 'name' => $package->name,
                'category_id' => $package->category_level_1 ? (int) $package->category_level_1 : null,
                'subcategory_id' => $package->category_level_2 ? (int) $package->category_level_2 : null,
                'group_ids' => $groupIds->unique()->map(fn ($id) => (int) $id)->values(),
            ];
        })->values();
        $selectedExamIds = collect(old('exam_ids', []))->map(fn ($id) => (int) $id)->filter()->unique();
        $selectedExams = Exam::where('organization_id', $tenantId)->whereIn('id', $selectedExamIds)
            ->withCount('questions')->get(['id', 'name']);
        $configuration = Configuration::where('organization_id', $tenantId)->first();
        $providers = collect(AiProvider::available($configuration, false, 'answer_explanation'))
            ->map(fn ($provider) => ['value' => $provider['provider'], 'label' => ucfirst($provider['stored_name']).' - '.$provider['model']])
            ->unique('value')->values();
        $runs = AiAnswerRun::where('organization_id', $tenantId)->with('exam:id,name')
            ->withCount([
                'curriculumEvents as new_subjects_count' => fn ($query) => $query->where('entity_type', 'subject'),
                'curriculumEvents as new_topics_count' => fn ($query) => $query->where('entity_type', 'topic'),
                'curriculumEvents as new_subtopics_count' => fn ($query) => $query->where('entity_type', 'subtopic'),
            ])->latest()->simplePaginate(25, ['*'], 'run_page')->withQueryString();
        $recentCurriculumEvents = AiCurriculumEvent::where('organization_id', $tenantId)
            ->with(['creator:id,name', 'run.exam:id,name'])
            ->latest()->paginate(50, ['*'], 'curriculum_page')->withQueryString();

        return view('ai-answers.index', compact(
            'groups', 'categoryOptions', 'packageOptions', 'selectedExams', 'providers', 'runs', 'recentCurriculumEvents'
        ));
    }

    public function examSearch(Request $request)
    {
        $tenantId = Tenant::id();
        $data = $request->validate([
            'q' => ['nullable', 'string', 'max:120'], 'page' => ['nullable', 'integer', 'min:1'],
            'group_id' => ['nullable', 'integer'], 'category_id' => ['nullable', 'integer'], 'package_id' => ['nullable', 'integer'],
        ]);
        $page = max(1, (int) ($data['page'] ?? 1));
        $pageSize = 100;
        $query = Exam::where('organization_id', $tenantId)->whereHas('questions');
        $this->applyHierarchy($query, $data);
        $term = trim((string) ($data['q'] ?? ''));
        if ($term !== '') {
            $escaped = addcslashes($term, '%_\\');
            $query->where('name', 'like', '%'.$escaped.'%');
        }
        $total = (clone $query)->count();
        $exams = $query->withCount('questions')->latest('id')
            ->skip(($page - 1) * $pageSize)->take($pageSize)->get(['id', 'name']);

        return response()->json([
            'results' => $exams->take($pageSize)->map(fn ($exam) => [
                'id' => (int) $exam->id, 'text' => $exam->name,
                'name' => $exam->name, 'question_count' => (int) $exam->questions_count,
            ])->values(),
            'pagination' => ['more' => $page * $pageSize < $total],
            'total' => $total,
        ]);
    }
    public function store(Request $request, AiAnswerService $service, AiAnswerProcessLauncher $launcher)
    {
        SaasAccess::abortIfFeatureDisabled('exam_quality_ai');
        $tenantId = Tenant::id();
        $data = $request->validate([
            'group_id' => ['nullable', Rule::exists('groups', 'id')->where('organization_id', $tenantId)],
            'category_id' => ['nullable', Rule::exists('category', 'id')->where('organization_id', $tenantId)],
            'package_id' => ['nullable', Rule::exists('packages', 'id')->where('organization_id', $tenantId)],
            'exam_ids' => ['nullable', 'array'], 'exam_ids.*' => ['integer', 'distinct', Rule::exists('exams', 'id')->where('organization_id', $tenantId)],
            'question_identifiers' => ['nullable', 'string', 'max:5000'],
            'additional_instructions' => ['nullable', 'string', 'max:3000'],
        ]);
        if (empty($data['group_id']) && empty($data['category_id']) && empty($data['package_id']) && empty($data['exam_ids'])) {
            return back()->withInput()->with('error', 'Choose a group, category, package, or at least one exam.');
        }

        $query = Exam::where('organization_id', $tenantId)->whereHas('questions');
        $this->applyHierarchy($query, $data);
        if (! empty($data['exam_ids'])) $query->whereIn('id', $data['exam_ids']);
        $exams = $query->get(['id', 'name', 'organization_id']);
        if ($exams->isEmpty()) return back()->withInput()->with('error', 'No exams with questions match this selection.');

        $identifiers = collect(preg_split('/[\s,;]+/', trim((string) ($data['question_identifiers'] ?? '')), -1, PREG_SPLIT_NO_EMPTY));
        $numericIds = $identifiers->filter(fn ($value) => ctype_digit($value))->map(fn ($value) => (int) $value);
        $codes = $identifiers->reject(fn ($value) => ctype_digit($value))->values();
        $tasks = ['generate_answer_explanation', 'generate_explanation', 'validate_existing'];
        $batchToken = (string) Str::uuid();
        $runCount = 0;

        DB::transaction(function () use ($exams, $tenantId, $data, $numericIds, $codes, $tasks, $batchToken, $service, &$runCount) {
            foreach ($exams as $exam) {
                $questions = $exam->questions()->with('qtype')
                    ->when($numericIds->isNotEmpty() || $codes->isNotEmpty(), fn ($q) => $q->where(function ($filter) use ($numericIds, $codes) {
                        if ($numericIds->isNotEmpty()) $filter->whereIn('questions.id', $numericIds);
                        if ($codes->isNotEmpty()) $filter->orWhereIn('question_code', $codes);
                    }))->get();
                if ($questions->isEmpty()) continue;
                $verification = app(\App\Services\AiAnswerVerification::class)->forExam($exam, $questions);

                $run = AiAnswerRun::create([
                    'organization_id' => $tenantId, 'exam_id' => $exam->id, 'batch_token' => $batchToken,
                    'requested_by' => auth()->id(), 'status' => 'queued', 'provider' => 'auto',
                    'additional_instructions' => trim((string) ($data['additional_instructions'] ?? '')) ?: null,
                    'total_questions' => $questions->count(), 'options' => ['tasks' => $tasks],
                ]);
                foreach ($questions as $question) {
                    AiAnswerDraft::create([
                        'organization_id' => $tenantId, 'run_id' => $run->id, 'exam_id' => $exam->id,
                        'question_id' => $question->id, 'status' => 'queued', 'mode' => $service->mode($question, $verification[$question->id]),
                        'original_payload' => [...$service->snapshot($question), '_answer_verification' => $verification[$question->id]], 'question_updated_at' => $question->updated_at,
                    ]);
                }
                $runCount++;
            }
        });
        if ($runCount === 0) return back()->withInput()->with('error', 'No matching questions were found in the selected scope.');

        $started = $launcher->startBatch($batchToken, $runCount);
        $message = "{$runCount} paper job(s) queued with four-worker parallel processing.";
        if ($started > 0) $message .= " {$started} immediate worker(s) started.";
        return redirect()->route('ai-answers.index')->with('success', $message);
    }

    public function show(Request $request, AiAnswerRun $run, AiAnswerService $service)
    {
        $this->authorizeTenant($run);
        $status = trim((string) $request->input('status'));
        $drafts = $run->drafts()->with(['question' => fn ($query) => $query->with('qtype')->withCount([
            'versions as ai_answer_versions_count' => fn ($versions) => $versions->whereNotNull('ai_answer_draft_id'),
        ])])
            ->when($status !== '', fn ($q) => $q->where('status', $status))
            ->orderByRaw("FIELD(status, 'discrepancy','ready','failed','verified','published','queued','processing')")
            ->paginate(30)->withQueryString();
        $drafts->getCollection()->load('curriculumEvents.creator:id,name');
        $drafts->getCollection()->each(function ($draft) use ($service) {
            $payload = (array) $draft->proposed_payload;
            if (! empty($payload['explanation'])) {
                $payload['explanation'] = $service->studentFacingExplanation($payload['explanation']);
                $draft->setAttribute('proposed_payload', $payload);
            }
        });
        $curriculumEvents = $run->curriculumEvents()->with('creator:id,name')->latest()->get();
        $curriculumEventCounts = $curriculumEvents->countBy('entity_type');
        $difficultyNames = Diff::query()->pluck('diff_level', 'id');
        return view('ai-answers.show', compact(
            'run', 'drafts', 'status', 'difficultyNames', 'curriculumEvents', 'curriculumEventCounts'
        ));
    }

    public function versions(Question $question)
    {
        $this->authorizeTenant($question);
        $question->loadMissing(['qtype', 'subject', 'topic', 'stopic']);
        $versions = QuestionVersion::where('organization_id', Tenant::id())
            ->where('question_id', $question->id)
            ->whereNotNull('ai_answer_draft_id')
            ->with(['creator:id,name', 'aiAnswerDraft:id,run_id,published_at'])
            ->latest('id')
            ->paginate(25);

        $difficultyNames = Diff::query()->pluck('diff_level', 'id');
        $payloads = $versions->getCollection()->pluck('payload');
        $subjectNames = Subject::whereIn('id', $payloads->map(fn ($payload) => data_get($payload, 'subject_id'))->filter())->pluck('subject_name', 'id');
        $topicNames = Topic::whereIn('id', $payloads->map(fn ($payload) => data_get($payload, 'topic_id'))->filter())->pluck('name', 'id');
        $subtopicNames = Stopic::whereIn('id', $payloads->map(fn ($payload) => data_get($payload, 'stopic_id'))->filter())->pluck('name', 'id');
        return view('ai-answers.versions', compact(
            'question', 'versions', 'difficultyNames', 'subjectNames', 'topicNames', 'subtopicNames'
        ));
    }

    public function restoreVersion(QuestionVersion $version, AiAnswerService $service)
    {
        $this->authorizeTenant($version);
        abort_unless($version->ai_answer_draft_id, 404);

        try {
            $question = $service->restoreVersion($version, (int) auth()->id());
            return redirect()->route('ai-answers.questions.versions', $question)
                ->with('success', 'The saved answer, explanation, difficulty, and curriculum classification were restored. The replaced live content was saved as a new version.');
        } catch (\Throwable $exception) {
            return back()->with('error', $exception->getMessage());
        }
    }

    public function retry(AiAnswerRun $run, AiAnswerProcessLauncher $launcher)
    {
        $this->authorizeTenant($run);
        $failed = $run->drafts()->where('status', 'failed')->count();
        if ($failed === 0) return back()->with('error', 'This paper has no failed questions to retry.');

        DB::transaction(function () use ($run) {
            $run->drafts()->where('status', 'failed')->update([
                'status' => 'queued', 'failure_message' => null, 'provider' => null, 'model' => null,
            ]);
            $run->update([
                'status' => 'queued', 'failure_message' => null, 'completed_at' => null,
                'processed_questions' => max(0, (int) $run->total_questions - (int) $run->failed_count), 'failed_count' => 0,
            ]);
        });
        $started = $launcher->startBatch($run->batch_token, 1);
        $message = "{$failed} failed question(s) queued for retry.";
        if ($started > 0) $message .= ' Processing started immediately.';

        return back()->with('success', $message);
    }

    public function publishPapers(Request $request, AiAnswerService $service)
    {
        $tenantId = Tenant::id();
        $data = $request->validate([
            'run_ids' => ['required', 'array', 'min:1'],
            'run_ids.*' => ['integer', 'distinct', Rule::exists('ai_answer_runs', 'id')->where('organization_id', $tenantId)],
        ]);
        $drafts = AiAnswerDraft::where('organization_id', $tenantId)
            ->whereIn('run_id', $data['run_ids'])->whereIn('status', ['ready', 'discrepancy'])->get();
        if ($drafts->isEmpty()) return back()->with('error', 'The selected papers have no publishable drafts.');

        $published = 0;
        $errors = [];
        foreach ($drafts as $draft) {
            try {
                if ($draft->status === 'discrepancy') {
                    $service->approve($draft, (int) auth()->id());
                    $draft->refresh();
                }
                $service->publish($draft, (int) auth()->id());
                $published++;
            } catch (\Throwable $exception) {
                $errors[] = "Question {$draft->question_id}: ".$exception->getMessage();
            }
        }
        $message = "{$published} approved question draft(s) published from the selected papers.";
        if ($errors !== []) $message .= ' '.implode(' | ', array_slice($errors, 0, 5));

        return back()->with($published > 0 ? 'success' : 'error', $message);
    }

    public function approve(AiAnswerDraft $draft, AiAnswerService $service)
    {
        $this->authorizeTenant($draft);
        try {
            $service->approve($draft->load('run'), (int) auth()->id());
            return back()->with('success', 'Draft approved. It remains unpublished until selected for publication.');
        } catch (\Throwable $exception) {
            return back()->with('error', $exception->getMessage());
        }
    }

    public function publish(Request $request, AiAnswerService $service)
    {
        $tenantId = Tenant::id();
        $data = $request->validate([
            'scope' => ['required', 'in:selected,paper,batch'],
            'run_id' => ['required', Rule::exists('ai_answer_runs', 'id')->where('organization_id', $tenantId)],
            'draft_ids' => ['nullable', 'array'], 'draft_ids.*' => ['integer'],
        ]);
        $run = AiAnswerRun::where('organization_id', $tenantId)->findOrFail($data['run_id']);
        $query = AiAnswerDraft::where('organization_id', $tenantId)->where('status', 'ready');
        if ($data['scope'] === 'selected') {
            if (empty($data['draft_ids'])) return back()->with('error', 'Select at least one ready question.');
            $query->where('run_id', $run->id)->whereIn('id', $data['draft_ids']);
        } elseif ($data['scope'] === 'paper') {
            $query->where('run_id', $run->id);
        } else {
            $query->whereHas('run', fn ($q) => $q->where('batch_token', $run->batch_token));
        }
        $drafts = $query->get();
        if ($drafts->isEmpty()) return back()->with('error', 'No approved ready drafts were found for this publication scope.');

        $published = 0; $errors = [];
        foreach ($drafts as $draft) {
            try { $service->publish($draft, (int) auth()->id()); $published++; }
            catch (\Throwable $exception) { $errors[] = "Question {$draft->question_id}: ".$exception->getMessage(); }
        }
        $message = "{$published} question draft(s) published with previous versions recorded.";
        if ($errors !== []) $message .= ' '.implode(' | ', array_slice($errors, 0, 5));
        return back()->with($published ? 'success' : 'error', $message);
    }

    private function authorizeTenant(object $model): void
    {
        abort_unless((int) $model->organization_id === (int) Tenant::id(), 404);
    }
    private function applyHierarchy($query, array $data): void
    {
        if (! empty($data['group_id'])) {
            $groupId = (int) $data['group_id'];
            $query->where(fn ($q) => $q->whereHas('groups', fn ($g) => $g->whereKey($groupId))
                ->orWhereHas('packages.groups', fn ($g) => $g->whereKey($groupId)));
        }
        if (! empty($data['category_id'])) {
            $categoryId = (int) $data['category_id'];
            $query->where(fn ($q) => $q->where('category_level_1', $categoryId)->orWhere('category_level_2', $categoryId)
                ->orWhereHas('packages', fn ($p) => $p->where('category_level_1', $categoryId)->orWhere('category_level_2', $categoryId)));
        }
        if (! empty($data['package_id'])) {
            $query->whereHas('packages', fn ($q) => $q->whereKey((int) $data['package_id']));
        }
    }
}

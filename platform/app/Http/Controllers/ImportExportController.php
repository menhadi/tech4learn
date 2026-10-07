<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Group;
use App\Models\Subject;
use App\Models\Topic;
use App\Models\Stopic;
use App\Models\Qtype;
use App\Models\Diff;
use App\Models\Language;
use App\Models\Package;
use App\Models\Configuration;
use App\Support\AiProvider;
use App\Models\Question;
use App\Models\QuestionSection;
use App\Models\QuestionTag;
use App\Models\Exam;
use App\Support\SaasAccess;
use App\Imports\QuestionsImport;
use App\Exports\QuestionsExport;
use App\Services\CurriculumTaxonomyService;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

class ImportExportController extends Controller
{
    private function tenantId(): ?int
    {
        return class_exists(\App\Support\Tenant::class) ? \App\Support\Tenant::id() : null;
    }

    private function tenantGroupQuery()
    {
        return Group::query()
            ->when($this->tenantId(), function ($q, $tenantId) {
                $q->where('organization_id', $tenantId);
            });
    }

    private function scopeSubjectTenant($query)
    {
        return $query->when($this->tenantId(), fn ($q, $tenantId) => $q->where('organization_id', $tenantId));

    }

    private function scopeTopicTenant($query)
    {
        return $query->when($this->tenantId(), function ($q, $tenantId) {
            $q->whereHas('group', fn ($groupQuery) => $groupQuery->where('organization_id', $tenantId));
        });
    }

    private function scopeStopicTenant($query)
    {
        return $query->when($this->tenantId(), function ($q, $tenantId) {
            $q->whereHas('group', fn ($groupQuery) => $groupQuery->where('organization_id', $tenantId));
        });
    }

    private function ensureTenantOwnsGroups(array $groupIds): void
    {
        $tenantId = $this->tenantId();

        if (! $tenantId) {
            return;
        }

        $ownedCount = Group::where('organization_id', $tenantId)
            ->whereIn('id', $groupIds)
            ->count();

        if ($ownedCount !== count(array_unique($groupIds))) {
            abort(404);
        }
    }


    public function index()
    {
        $groups = $this->tenantGroupQuery()->orderBy('group_name')->get();
        $subjects = $this->scopeSubjectTenant(Subject::query()->with('groups:id'))->orderBy('subject_name')->get();
        $parentCategories = Category::query()
            ->with(['groups:id', 'children' => fn ($query) => $query->orderBy('title')])
            ->when($this->tenantId(), fn ($query, $tenantId) => $query->where('organization_id', $tenantId))
            ->whereNull('parent_id')->orderBy('title')->get();
        $packages = Package::query()
            ->with('groups:id')
            ->when($this->tenantId(), fn ($query, $tenantId) => $query->where('organization_id', $tenantId))
            ->orderBy('name')->get(['id', 'name', 'category_level_1', 'category_level_2']);

        $exams = Exam::query()
            ->with(['groups:id', 'packages.groups:id'])
            ->when($this->tenantId(), fn ($query, $tenantId) => $query->where('organization_id', $tenantId))
            ->orderBy('name')->get(['id', 'name', 'category_level_1', 'category_level_2']);
        $questionSections = QuestionSection::query()
            ->when($this->tenantId(), fn ($query, $tenantId) => $query->where('organization_id', $tenantId))
            ->orderBy('display_order')->orderBy('name')->get(['id', 'name']);
        $questionTypes = Qtype::displayOrdered(['id', 'question_type', 'type']);
        $difficulties = Diff::query()->orderBy('diff_level')->orderBy('type')->get(['id', 'diff_level', 'type']);
        $languages = Language::query()->forOrganization($this->tenantId())->orderBy('name')->get(['id', 'name']);
        $questionTags = QuestionTag::query()
            ->when($this->tenantId(), fn ($query, $tenantId) => $query->where('organization_id', $tenantId))
            ->orderBy('name')->get(['id', 'name']);

        // Prefer the flashed report so it survives tenant-context changes across the redirect.
        $importReport = session('import_report')
            ?: Cache::pull('question_import_report:'.($this->tenantId() ?: 0).':'.(auth()->id() ?: 'guest'));

        return view('import_export.index', compact(
            'groups', 'subjects', 'parentCategories', 'packages', 'exams', 'questionSections',
            'questionTypes', 'difficulties', 'languages', 'questionTags', 'importReport'
        ));

    }
    public function showAiGenerator()
    {
        SaasAccess::abortIfFeatureDisabled('ai_generator');

        $groups = $this->tenantGroupQuery()->get();
        $subjects = $this->scopeSubjectTenant(Subject::query())
            ->orderBy('subject_name')
            ->get();
        $topics = $this->scopeTopicTenant(Topic::query())
            ->orderBy('name')
            ->get();
        $stopics = $this->scopeStopicTenant(Stopic::query())
            ->orderBy('name')
            ->get();
        $qtypes = Qtype::displayOrdered();
        $diffs = Diff::orderBy('diff_level')->get();
        $languages = Language::enabledForOrganization($this->tenantId())->orderBy('name')->get();
        
        return view('import_export.ai-generator', compact('groups', 'subjects', 'topics', 'stopics', 'qtypes', 'diffs', 'languages'));
    }

    public function getSubjectsByGroup(Request $request)
    {
        $groupId = $request->integer('group_id');
        if (! $groupId) {
            return response()->json($this->scopeSubjectTenant(Subject::query())->orderBy('subject_name')->get());
        }

        $this->ensureTenantOwnsGroups([$groupId]);

        return response()->json(
            $this->scopeSubjectTenant(Subject::query())
                ->whereHas('groups', fn ($query) => $query->where('groups.id', $groupId))
                ->orderBy('subject_name')
                ->get()
        );
    }
    public function getSubjectsByGroups(Request $request)
    {
        $groupIds = $request->group_ids;
        
        if (empty($groupIds)) {
            return response()->json([]);
        }
        
        $this->ensureTenantOwnsGroups((array) $groupIds);

        $groupIds = collect((array) $groupIds)->map(fn ($id) => (int) $id)->filter()->unique()->values();
        $subjects = $this->scopeSubjectTenant(Subject::query())
            ->whereHas('groups', fn ($query) => $query->whereIn('groups.id', $groupIds), '=', $groupIds->count())
            ->orderBy('subject_name')
            ->get();
        
        return response()->json($subjects);
    }

    public function getTopicsBySubject(Request $request, $subjectId = null)
    {
        $subjectId = $request->subject_id ?: $subjectId;
        
        if (empty($subjectId)) {
            return response()->json([]);
        }
        
        $this->scopeSubjectTenant(Subject::query())->findOrFail($subjectId);

        $groupId = $request->integer('group_id') ?: collect((array) $request->input('group_ids', []))->map(fn ($id) => (int) $id)->filter()->first();
        if ($groupId) $this->ensureTenantOwnsGroups([$groupId]);

        $topics = $this->scopeTopicTenant(Topic::where('subject_id', $subjectId))
            ->when($groupId, fn ($query) => $query->where('group_id', $groupId))
            ->orderBy('name')
            ->get();
        
        return response()->json($topics);
    }

    public function getSubtopicsByTopic(Request $request, $topicId = null)
    {
        $topicId = $request->topic_id ?: $topicId;
        
        if (empty($topicId)) {
            return response()->json([]);
        }
        
        $topic = $this->scopeTopicTenant(Topic::query())->findOrFail($topicId);

        $stopics = $this->scopeStopicTenant(Stopic::where('topic_id', $topic->id))
            ->orderBy('name')
            ->get();
        
        return response()->json($stopics);
    }

    public function runAiGenerator(Request $request)
    {
        SaasAccess::abortIfFeatureDisabled('ai_generator');

        $request->validate([
            'group_ids' => 'required|array',
            'subject_id' => 'nullable|exists:subjects,id',
            'topic_id' => 'nullable|exists:topics,id',
            'stopic_id' => 'nullable|exists:stopics,id',
            'main_topic' => 'required|string|min:3',
            'qtype_id' => 'required|exists:qtypes,id',
            'diff_id' => 'nullable|exists:diffs,id',
            'marks' => 'required|numeric|min:0',
            'negative_marks' => 'nullable|numeric|min:0',
            'language_id' => 'required|exists:languages,id',
            'num_questions' => 'required|integer|min:1|max:10',
        ]);
        
        try {
            $this->ensureTenantOwnsGroups((array) $request->group_ids);

            $settings = getConfiguration();
            
            $ai = AiProvider::firstAvailable($settings, false, 'question_generation');
            $apiKey = $ai['key'] ?? null;
            $apiProvider = $ai['provider'] ?? null;
            $model = $ai['model'] ?? null;
            $isAiGenerated = $ai['stored_name'] ?? null;
            
            $subject = $request->subject_id
                ? $this->scopeSubjectTenant(Subject::query())->findOrFail($request->subject_id)
                : null;
            $topic = $request->topic_id
                ? $this->scopeTopicTenant(Topic::query())->findOrFail($request->topic_id)
                : null;
            $subtopic = $request->stopic_id
                ? $this->scopeStopicTenant(Stopic::query())->findOrFail($request->stopic_id)
                : null;

            if ($subject && $topic && (int) $topic->subject_id !== (int) $subject->id) {
                abort(422, 'Selected topic does not belong to the selected subject.');
            }

            if ($topic && $subtopic && (int) $subtopic->topic_id !== (int) $topic->id) {
                abort(422, 'Selected subtopic does not belong to the selected topic.');
            }
            app(CurriculumTaxonomyService::class)->validateSelection(
                (int) $this->tenantId(), (array) $request->group_ids,
                $subject?->id, $topic?->id, $subtopic?->id
            );

            $qtype = Qtype::find($request->qtype_id);
            $difficulty = Diff::find($request->diff_id);
            $language = Language::enabledForOrganization($this->tenantId())->findOrFail($request->language_id);
            
            $groupNames = $this->tenantGroupQuery()->whereIn('id', $request->group_ids)->pluck('group_name')->implode(', ');
            
            $questions = $this->getQuestions($request, $subject, $topic, $subtopic, $qtype, $difficulty, $language, $groupNames, $apiKey, $apiProvider, $model);
            
            $savedCount = 0;
            foreach ($questions as $q) {
                $question = new Question();
                $question->organization_id = $this->tenantId();
                $question->qtype_id = $request->qtype_id;
                $question->subject_id = $request->subject_id;
                $question->topic_id = $request->topic_id ?? null;
                $question->stopic_id = $request->stopic_id ?? null;
                $question->diff_id = $request->diff_id;
                $question->language_id = $request->language_id;
                $question->question = $q['question'];
                $question->marks = $request->marks;
                $question->negative_marks = $request->negative_marks ?? 0;
                $question->explanation = $q['explanation'] ?? '';
                $question->status = 'Yes';
                $question->ai_generated = $isAiGenerated;  // Now stores DEEPSEEK, CHATGPT, or GEMINI
                
                $type = $qtype->type;
                
                if ($type === 'M') {
                    $question->option1 = $q['option1'] ?? '';
                    $question->option2 = $q['option2'] ?? '';
                    $question->option3 = $q['option3'] ?? '';
                    $question->option4 = $q['option4'] ?? '';
                    $correctNum = (int) ($q['correct_option_number'] ?? 1);
                    $question->answer = (string) $correctNum;
                    $question->correct_option_indices = [$correctNum];
                }
                elseif ($type === 'F') {
                    $question->fill_blank = $q['fill_blank_answer'] ?? '';
                }
                elseif ($type === 'T') {
                    $trueFalseValue = strtolower($q['true_false_answer'] ?? 'true');
                    $question->true_false = $trueFalseValue;
                }
                elseif ($type === 'S') {
                    $question->si_answer1 = $q['subjective_answer'] ?? '';
                }
                
                $question->save();
                
                if (!empty($request->group_ids)) {
                    $question->groups()->sync($request->group_ids);
                    app(CurriculumTaxonomyService::class)->syncQuestion($question->fresh(['topic', 'stopic']), (array) $request->group_ids);
                }
                $savedCount++;
            }
            
            $aiStatus = $isAiGenerated ? 'AI generated' : 'Local fallback';
            return redirect()->back()->with('success', "Generated $savedCount {$qtype->question_type} questions using AI. ($aiStatus)");
            
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Error: ' . $e->getMessage());
        }
    }
    
    private function getQuestions($request, $subject, $topic, $subtopic, $qtype, $difficulty, $language, $groupNames, $apiKey, $apiProvider, $model)
    {
        $topicName = $topic ? $topic->name : $request->main_topic;
        $subtopicName = $subtopic ? $subtopic->name : '';
        $numQ = $request->num_questions;
        
        $subjectName = $subject?->subject_name ?? $request->main_topic;
        $difficultyName = $difficulty?->diff_level ?? "Not specified";

        $context = "Subject/Area: $subjectName, Topic: $topicName";
        if ($subtopicName) $context .= ", Sub-topic: $subtopicName";
        $context .= ", Difficulty: $difficultyName, For: $groupNames, Language: $language->name";
        $context .= ". Write every natural-language field, including explanations, in {$language->name} ({$language->code}). Keep JSON keys in English.";
        
        if ($qtype->type === 'M') {
            $prompt = "Create $numQ MCQ questions about: $request->main_topic. Context: $context. Randomize correct answers (1-4). Return ONLY valid JSON array. No markdown. Format: [{\"question\":\"text\",\"option1\":\"A\",\"option2\":\"B\",\"option3\":\"C\",\"option4\":\"D\",\"correct_option_number\":\"2\",\"explanation\":\"short reason\"}]";
        }
        elseif ($qtype->type === 'F') {
            $prompt = "Create $numQ fill in the blanks questions about: $request->main_topic. Context: $context. Return ONLY valid JSON array. No markdown. Format: [{\"question\":\"sentence with ______\",\"fill_blank_answer\":\"word\",\"explanation\":\"short explanation\"}]";
        }
        elseif ($qtype->type === 'T') {
            $prompt = "Create $numQ true/false questions about: $request->main_topic. Context: $context. Return ONLY valid JSON array. No markdown. Each true_false_answer must be exactly 'true' or 'false' (lowercase). Format: [{\"question\":\"statement\",\"true_false_answer\":\"true\",\"explanation\":\"short explanation\"}]";
        }
        elseif ($qtype->type === 'S') {
            $prompt = "Create $numQ subjective questions about: $request->main_topic. Context: $context. Return ONLY valid JSON array. No markdown. Format: [{\"question\":\"question text\",\"subjective_answer\":\"model answer key points\",\"explanation\":\"marking scheme\"}]";
        }
        else {
            return $this->fallback($request, $subject, $topicName, $qtype);
        }
        
        if (!empty($apiKey) && $apiProvider) {
            try {
                $content = AiProvider::generateText([
                    'provider' => $apiProvider,
                    'key' => $apiKey,
                    'model' => $model,
                ], $prompt, 'You are an expert exam question generator. Return only valid JSON. No markdown. For true/false, always use exactly "true" or "false" (lowercase).', 0.7, 8192, 60);

                if (is_string($content) && trim($content) !== '') {
                    $content = preg_replace('/```json\s*|\s*```/', '', $content);
                    $questions = json_decode(trim($content), true);
                    if (is_array($questions) && count($questions) > 0) {
                        return $questions;
                    }
                    Log::warning('AI question generator returned invalid JSON', [
                        'provider' => $apiProvider,
                        'model' => $model,
                        'response' => substr($content, 0, 500),
                    ]);
                }            } catch (\Exception $e) {
                Log::warning('AI question generator exception', [
                    'provider' => $apiProvider,
                    'model' => $model,
                    'message' => $e->getMessage(),
                ]);
            }
        }
        
        return $this->fallback($request, $subject, $topicName, $qtype);
    }
    
    private function fallback($request, $subject, $topicName, $qtype)
    {
        $subjectName = $subject?->subject_name ?? $request->main_topic;
        $questions = [];
        $correctOptions = ['1', '2', '3', '4'];
        
        for ($i = 1; $i <= $request->num_questions; $i++) {
            $correctNum = $correctOptions[array_rand($correctOptions)];
            
            if ($qtype->type === 'M') {
                $questions[] = [
                    'question' => "What is '$topicName' in {$subjectName}?",
                    'option1' => "Core concept and fundamental principle of $topicName",
                    'option2' => "Definition of $topicName without application",
                    'option3' => "Theoretical understanding of $topicName only",
                    'option4' => "Basic overview of $topicName",
                    'correct_option_number' => $correctNum,
                    'explanation' => "This is the correct answer based on standard curriculum."
                ];
            }
            elseif ($qtype->type === 'F') {
                $questions[] = [
                    'question' => "$topicName is a fundamental ______ in {$subjectName}.",
                    'fill_blank_answer' => 'concept',
                    'explanation' => 'Standard terminology used in academic context.'
                ];
            }
            elseif ($qtype->type === 'T') {
                $questions[] = [
                    'question' => "$topicName is an important topic in {$subjectName}.",
                    'true_false_answer' => 'true',
                    'explanation' => 'Based on standard curriculum guidelines.'
                ];
            }
            elseif ($qtype->type === 'S') {
                $questions[] = [
                    'question' => "Explain the concept of $topicName in {$subjectName}. Provide relevant examples.",
                    'subjective_answer' => "$topicName is a fundamental concept that forms the basis of understanding in {$subjectName}. It involves key principles and practical applications.",
                    'explanation' => 'Look for understanding of core concepts, ability to explain clearly, and relevant examples.'
                ];
            }
        }
        return $questions;
    }


    public function import(Request $request)
    {
        if (function_exists('set_time_limit')) @set_time_limit(0);
        $request->validate([
            'group_ids' => 'nullable|array',
            'group_ids.*' => 'integer',
            'category_level_1' => 'nullable|integer',
            'category_level_2' => 'nullable|integer',
            'subject_id' => 'nullable|exists:subjects,id',
            'topic_id' => 'nullable|exists:topics,id',
            'stopic_id' => 'nullable|exists:stopics,id',
            'google_sheet_url' => 'nullable|url|max:1000',
            'excel_file' => 'nullable|file|mimes:xlsx,csv,txt|max:5120',
            'import_mode' => 'required|in:create,update,upsert,source_patch,patch',
        ]);

        $file = $request->file('file')
            ?: $request->file('excel_file')
            ?: $request->file('import_file')
            ?: $request->file('questions_file');
        $googleSheetUrl = trim((string) $request->input('google_sheet_url'));

        if (! $file && $googleSheetUrl === '') {
            return back()->withErrors(['file' => 'Upload an Excel/CSV file or provide a Google Sheet URL.'])->withInput();
        }
        $groupIds = $request->input('group_ids', []);
        $this->ensureTenantOwnsGroups((array) $groupIds);

        $defaultCategory = $request->filled('category_level_1') ? Category::query()->when($this->tenantId(), fn ($q, $id) => $q->where('organization_id', $id))->whereNull('parent_id')->findOrFail($request->category_level_1) : null;
        if ($request->filled('category_level_2')) Category::query()->when($this->tenantId(), fn ($q, $id) => $q->where('organization_id', $id))->where('parent_id', $defaultCategory?->id)->findOrFail($request->category_level_2);

        if ($request->filled('subject_id')) {
            $this->scopeSubjectTenant(Subject::query())->findOrFail($request->subject_id);
        }

        if ($request->filled('topic_id')) {
            $topic = $this->scopeTopicTenant(Topic::query())->findOrFail($request->topic_id);
            if ($request->filled('subject_id') && (int) $topic->subject_id !== (int) $request->subject_id) {
                abort(422, 'Selected topic does not belong to the selected subject.');
            }
        }

        if ($request->filled('stopic_id')) {
            $stopic = $this->scopeStopicTenant(Stopic::query())->findOrFail($request->stopic_id);
            if ($request->filled('topic_id') && (int) $stopic->topic_id !== (int) $request->topic_id) {
                abort(422, 'Selected subtopic does not belong to the selected topic.');
            }
        }
        if ($groupIds !== [] || $request->filled('subject_id') || $request->filled('topic_id') || $request->filled('stopic_id')) {
            app(CurriculumTaxonomyService::class)->validateSelection(
                (int) $this->tenantId(), (array) $groupIds,
                $request->integer('subject_id') ?: null,
                $request->integer('topic_id') ?: null,
                $request->integer('stopic_id') ?: null,
            );
        }

        $temporaryPath = null;
        $importSource = $file;
        if (! $file) {
            $temporaryPath = $this->downloadGoogleSheetCsv($googleSheetUrl);
            $importSource = $temporaryPath;
        }

        $import = new QuestionsImport(
            $request->input('subject_id') ?: null,
            $request->input('topic_id') ?: null,
            $request->input('stopic_id') ?: null,
            (array) $groupIds,
            $this->tenantId(),
            $request->input('category_level_1') ?: null,
            $request->input('category_level_2') ?: null,
            $request->input('import_mode', 'create'),
        );

        $importException = null;
        try {
            DB::transaction(fn () => Excel::import($import, $importSource));
        } catch (ValidationException $exception) {
            $importException = $exception;
        } finally {
            if ($temporaryPath && is_file($temporaryPath)) @unlink($temporaryPath);
        }
        $report = [
            'file' => $file?->getClientOriginalName() ?: 'Google Sheet',
            'mode' => $request->input('import_mode', 'create'),
            'processed' => $import->importedCount,
            'updated' => $import->updatedCount,
            'duplicates' => $import->duplicateCount,
            'created_records' => $import->createdRecordCount,
            'warnings' => [],
            'errors' => [],
        ];


        if ($importException) {
            $report['errors'] = collect($importException->errors())->flatten()->values()->all();
        }

        Cache::put('question_import_report:'.($this->tenantId() ?: 0).':'.(auth()->id() ?: 'guest'), $report, now()->addMinutes(10));
        if ($importException) {
            return redirect()->route('questions.importExport')
                ->with('error', 'Import failed. Review the import report for row-level details.')
                ->with('import_report', $report)
                ->withErrors($importException->errors());
        }
        return redirect()->route('questions.importExport')
            ->with('success', "Import completed: {$report['processed']} rows processed.")
            ->with('import_report', $report);
    }
    public function export(Request $request)
    {
        if (function_exists('set_time_limit')) @set_time_limit(0);

        $filters = $request->validate([
            'format' => 'nullable|in:csv,xlsx',
            'group_id' => 'nullable|integer|min:1',
            'category_id' => 'nullable|integer|min:1',
            'subcategory_id' => 'nullable|integer|min:1',
            'package_id' => 'nullable|integer|min:1',
            'exam_id' => 'nullable|integer|min:1',
            'subject_id' => 'nullable|integer|min:1',
            'topic_id' => 'nullable|integer|min:1',
            'stopic_id' => 'nullable|integer|min:1',
            'question_section_id' => 'nullable|integer|min:1',
            'qtype_id' => 'nullable|integer|min:1',
            'diff_id' => 'nullable|integer|min:1',
            'language_id' => 'nullable|integer|min:1',
            'question_tag_id' => 'nullable|integer|min:1',
            'status' => 'nullable|in:Yes,No',
            'ai_generated' => 'nullable|in:yes,no',
            'has_passage' => 'nullable|in:yes,no',
            'has_explanation' => 'nullable|in:yes,no',
            'has_source' => 'nullable|in:yes,no',
            'question_code' => 'nullable|string|max:100',
            'search' => 'nullable|string|max:200',
            'marks_min' => 'nullable|numeric',
            'marks_max' => 'nullable|numeric',
            'created_from' => 'nullable|date',
            'created_to' => 'nullable|date',
        ]);

        if (isset($filters['marks_min'], $filters['marks_max']) && (float) $filters['marks_max'] < (float) $filters['marks_min']) {
            throw ValidationException::withMessages(['marks_max' => 'Maximum marks cannot be lower than minimum marks.']);
        }
        if (! empty($filters['created_from']) && ! empty($filters['created_to']) && strtotime($filters['created_to']) < strtotime($filters['created_from'])) {
            throw ValidationException::withMessages(['created_to' => 'The end date cannot be earlier than the start date.']);
        }
        $query = $this->questionExportQuery($filters);
        $groupId = isset($filters['group_id']) ? (int) $filters['group_id'] : null;
        $format = $filters['format'] ?? 'csv';

        if ($format === 'csv') {
            return (new \App\Exports\QuestionsCsvExport($query, $groupId))->download(
                'questions_' . now()->format('Y_m_d_His') . '.csv'
            );
        }

        $xlsxLimit = 25000;
        if ((clone $query)->toBase()->getCountForPagination() > $xlsxLimit) {
            return redirect()->route('questions.importExport', ['tab' => 'export'])->with(
                'error',
                'The filtered result exceeds '.number_format($xlsxLimit).' questions. Use Excel-compatible CSV or narrow the filters.'
            );
        }

        return Excel::download(
            new QuestionsExport($query->latest('id'), $groupId),
            'questions_' . now()->format('Y_m_d_His') . '.xlsx'
        );
    }

    private function questionExportQuery(array $filters): Builder
    {
        return Question::with(['groups', 'subject', 'questionSection', 'topic', 'stopic', 'taxonomies.subject', 'taxonomies.topic', 'taxonomies.stopic', 'diff', 'qtype', 'language', 'passage', 'tags', 'exams.packages.category', 'exams.packages.subcategory', 'exams.category', 'exams.subcategory', 'exams.qualitySources'])
            ->when($this->tenantId(), fn ($q, $tenantId) => $q->where('organization_id', $tenantId))
            ->when(! empty($filters['group_id']), function ($q) use ($filters) {
                $groupId = (int) $filters['group_id'];
                $q->where(function ($scope) use ($groupId) {
                    $scope->whereHas('groups', fn ($groupQuery) => $groupQuery->where('groups.id', $groupId))
                        ->orWhereHas('exams.groups', fn ($groupQuery) => $groupQuery->where('groups.id', $groupId))
                        ->orWhereHas('exams.packages.groups', fn ($groupQuery) => $groupQuery->where('groups.id', $groupId));
                });
            })
            ->when(! empty($filters['category_id']), function ($q) use ($filters) {
                $categoryId = (int) $filters['category_id'];
                $q->where(function ($scope) use ($categoryId) {
                    $scope->whereHas('exams', fn ($examQuery) => $examQuery->where('exams.category_level_1', $categoryId))
                        ->orWhereHas('exams.packages', fn ($packageQuery) => $packageQuery->where('packages.category_level_1', $categoryId));
                });
            })
            ->when(! empty($filters['subcategory_id']), function ($q) use ($filters) {
                $subcategoryId = (int) $filters['subcategory_id'];
                $q->where(function ($scope) use ($subcategoryId) {
                    $scope->whereHas('exams', fn ($examQuery) => $examQuery->where('exams.category_level_2', $subcategoryId))
                        ->orWhereHas('exams.packages', fn ($packageQuery) => $packageQuery->where('packages.category_level_2', $subcategoryId));
                });
            })
            ->when(! empty($filters['package_id']), fn ($q) => $q->whereHas('exams.packages', fn ($packages) => $packages->where('packages.id', (int) $filters['package_id'])))
            ->when(! empty($filters['exam_id']), fn ($q) => $q->whereHas('exams', fn ($exams) => $exams->where('exams.id', (int) $filters['exam_id'])))
            ->when(! empty($filters['subject_id']), fn ($q) => $q->where('subject_id', (int) $filters['subject_id']))
            ->when(! empty($filters['topic_id']), function ($q) use ($filters) {
                ! empty($filters['group_id'])
                    ? $q->whereHas('taxonomies', fn ($taxonomy) => $taxonomy->where('group_id', (int) $filters['group_id'])->where('topic_id', (int) $filters['topic_id']))
                    : $q->where('topic_id', (int) $filters['topic_id']);
            })
            ->when(! empty($filters['stopic_id']), function ($q) use ($filters) {
                ! empty($filters['group_id'])
                    ? $q->whereHas('taxonomies', fn ($taxonomy) => $taxonomy->where('group_id', (int) $filters['group_id'])->where('stopic_id', (int) $filters['stopic_id']))
                    : $q->where('stopic_id', (int) $filters['stopic_id']);
            })
            ->when(! empty($filters['question_section_id']), fn ($q) => $q->where('question_section_id', (int) $filters['question_section_id']))
            ->when(! empty($filters['qtype_id']), fn ($q) => $q->where('qtype_id', (int) $filters['qtype_id']))
            ->when(! empty($filters['diff_id']), fn ($q) => $q->where('diff_id', (int) $filters['diff_id']))
            ->when(! empty($filters['language_id']), fn ($q) => $q->where('language_id', (int) $filters['language_id']))
            ->when(! empty($filters['question_tag_id']), fn ($q) => $q->whereHas('tags', fn ($tags) => $tags->where('question_tags.id', (int) $filters['question_tag_id'])))
            ->when(! empty($filters['status']), fn ($q) => $q->where('status', $filters['status']))
            ->when(($filters['ai_generated'] ?? null) === 'yes', fn ($q) => $q->whereNotNull('ai_generated')->where('ai_generated', '<>', ''))
            ->when(($filters['ai_generated'] ?? null) === 'no', fn ($q) => $q->where(fn ($scope) => $scope->whereNull('ai_generated')->orWhere('ai_generated', '')))
            ->when(($filters['has_passage'] ?? null) === 'yes', fn ($q) => $q->whereNotNull('passage_id'))
            ->when(($filters['has_passage'] ?? null) === 'no', fn ($q) => $q->whereNull('passage_id'))
            ->when(($filters['has_explanation'] ?? null) === 'yes', fn ($q) => $q->whereNotNull('explanation')->where('explanation', '<>', ''))
            ->when(($filters['has_explanation'] ?? null) === 'no', fn ($q) => $q->where(fn ($scope) => $scope->whereNull('explanation')->orWhere('explanation', '')))
            ->when(($filters['has_source'] ?? null) === 'yes', fn ($q) => $q->where(fn ($scope) => $scope->whereNotNull('source_url')->where('source_url', '<>', '')->orWhere(fn ($nested) => $nested->whereNotNull('source_reference')->where('source_reference', '<>', ''))))
            ->when(($filters['has_source'] ?? null) === 'no', fn ($q) => $q->where(fn ($scope) => $scope->where(fn ($nested) => $nested->whereNull('source_url')->orWhere('source_url', ''))->where(fn ($nested) => $nested->whereNull('source_reference')->orWhere('source_reference', ''))))
            ->when(! empty($filters['question_code']), fn ($q) => $q->where('question_code', 'like', '%'.$this->escapeLike($filters['question_code']).'%'))
            ->when(! empty($filters['search']), function ($q) use ($filters) {
                $search = '%'.$this->escapeLike($filters['search']).'%';
                $q->where(fn ($scope) => $scope->where('question', 'like', $search)->orWhere('explanation', 'like', $search)->orWhere('hint', 'like', $search));
            })
            ->when(isset($filters['marks_min']), fn ($q) => $q->where('marks', '>=', $filters['marks_min']))
            ->when(isset($filters['marks_max']), fn ($q) => $q->where('marks', '<=', $filters['marks_max']))
            ->when(! empty($filters['created_from']), fn ($q) => $q->whereDate('created_at', '>=', $filters['created_from']))
            ->when(! empty($filters['created_to']), fn ($q) => $q->whereDate('created_at', '<=', $filters['created_to']));
    }

    private function escapeLike(string $value): string
    {
        return addcslashes(trim($value), '%_\\');
    }

    public function exportQuestions($subjectId = null, $topicId = null, $subtopicId = null)
    {
        return $this->export(new Request([
            'subject_id' => $subjectId,
            'topic_id' => $topicId,
            'stopic_id' => $subtopicId,
        ]));
    }

    public function downloadTemplate()
    {
        $headers = ['Content-Type' => 'text/csv', 'Content-Disposition' => 'attachment; filename="question-import-template.csv"'];
        $columns = [
            'question_code',
            'groups', 'category', 'subcategory', 'package', 'exams', 'subject', 'section', 'topic', 'subtopic',
            'question_type', 'difficulty_level', 'language', 'passage', 'question',
            'option1', 'option2', 'option3', 'option4', 'option5', 'option6',
            'answer', 'true_false', 'fill_blank', 'fill_blank_answers',
            'nat_mode', 'nat_value', 'nat_min', 'nat_max', 'nat_tolerance',
            'correct_option_indices', 'si_answer1',
            'marks', 'negative_marks', 'scoring_policy', 'hint', 'explanation', 'status', 'tags',
            'question_source_url', 'question_source_reference',
            'paper_question_source_url', 'paper_answer_source_url', 'paper_combined_source_url',
        ];

        $callback = function () use ($columns) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, $columns);

            $sample = array_fill_keys($columns, '');
            $sample = array_replace($sample, [
                'groups' => 'CAT & MBA',
                'category' => 'Previous Year Papers',
                'subcategory' => 'CAT PYP',
                'package' => 'CAT Previous Papers',
                'exams' => 'CAT 2024',
                'subject' => 'Quantitative Aptitude',
                'section' => 'Quantitative Aptitude Section',
                'topic' => 'Arithmetic',
                'subtopic' => 'Percentages',
                'question_type' => 'M',
                'difficulty_level' => 'Medium',
                'language' => 'English',
                'question' => 'What is 20% of 500?',
                'option1' => '50',
                'option2' => '100',
                'option3' => '150',
                'option4' => '200',
                'correct_option_indices' => '2',
                'marks' => '1',
                'negative_marks' => '0.25',
                'scoring_policy' => 'NORMAL',
                'hint' => 'Convert percentage to a fraction.',
                'explanation' => '20 x 500 / 100 = 100.',
                'status' => 'Yes',
                'tags' => 'Imported | PYP',
                'question_source_url' => 'https://official.example/questions/20-percent',
                'question_source_reference' => 'Page 12 / Question 18',
                'paper_question_source_url' => 'https://official.example/cat-2024-question-paper.pdf',
                'paper_answer_source_url' => 'https://official.example/cat-2024-answer-key.pdf',
            ]);
            fputcsv($handle, array_map(fn ($column) => $sample[$column], $columns));
            fclose($handle);
        };
        return response()->stream($callback, 200, $headers);
    }

    private function downloadGoogleSheetCsv(string $url): string
    {
        if (! preg_match('~^https://docs\.google\.com/spreadsheets/d/([a-zA-Z0-9_-]+)~', $url, $match)) {
            throw \Illuminate\Validation\ValidationException::withMessages(['google_sheet_url' => 'Use a valid docs.google.com/spreadsheets URL.']);
        }
        $gid = preg_match('/(?:[?#&]gid=)(\d+)/', $url, $gidMatch) ? $gidMatch[1] : '0';
        $csvUrl = "https://docs.google.com/spreadsheets/d/{$match[1]}/export?format=csv&gid={$gid}";
        $response = Http::timeout(45)->retry(2, 500)->get($csvUrl);
        if (! $response->successful() || trim($response->body()) === '') {
            throw \Illuminate\Validation\ValidationException::withMessages(['google_sheet_url' => 'The Google Sheet could not be downloaded. Share it as Anyone with the link (Viewer).']);
        }
        $temporaryBase = tempnam(sys_get_temp_dir(), 'question-sheet-');
        $path = $temporaryBase . '.csv';
        @unlink($temporaryBase);
        file_put_contents($path, $response->body());
        return $path;
    }

}

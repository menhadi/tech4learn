<?php

namespace App\Http\Controllers;

use App\Models\{Category, Diff, Exam, Group, Language, Package, Qtype, SourceExamImport, SourceExamQuestionDraft, Subject};
use App\Services\SourceExamImportService;
use App\Services\QuestionRepairService;
use App\Services\StructuredContentReviewService;
use App\Services\SourceExamImportProcessLauncher;
use App\Support\SaasAccess;
use App\Support\SourceExtractorRegistry;
use App\Support\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class SourceExamImportController extends Controller
{
    private function owned(SourceExamImport $import): void
    {
        abort_unless((int) $import->organization_id === (int) Tenant::id(), 404);
    }

    private function formData(?SourceExtractorRegistry $extractorRegistry = null): array
    {
        $tenant = (int) Tenant::id();
        return [
            'groups' => Group::where('organization_id', $tenant)->orderBy('group_name')->get(),
            'categories' => Category::where('organization_id', $tenant)->whereNull('parent_id')->where('status', 1)->with('groups:id')->orderBy('title')->get(),
            'subcategories' => Category::where('organization_id', $tenant)->whereNotNull('parent_id')->where('status', 1)->orderBy('title')->get(),
            'packages' => Package::where('organization_id', $tenant)->with('groups:id')->orderBy('name')->get(),
            'subjects' => Subject::whereHas('groups', fn ($q) => $q->where('groups.organization_id', $tenant))->orderBy('subject_name')->get(),
            'qtypes' => Qtype::displayOrdered(),
            'diffs' => Diff::orderBy('diff_level')->get(),
            'languages' => Language::enabledForOrganization($tenant)->orderBy('name')->get(),
            'emptyExams' => Exam::query()
                ->where('organization_id', $tenant)
                ->whereDoesntHave('questions')
                ->whereDoesntHave('results')
                ->whereHas('qualitySources', fn ($query) => $query
                    ->where('is_active', true)
                    ->whereIn('kind', ['file', 'url'])
                    ->whereIn('role', ['questions', 'combined']))
                ->whereNotIn('id', SourceExamImport::query()
                    ->whereNotNull('exam_id')
                    ->whereIn('status', ['draft', 'queued', 'processing', 'review'])
                    ->pluck('exam_id'))
                ->with(['groups:id,group_name', 'packages:id,name,category_level_1,category_level_2', 'packages.groups:id', 'qualitySources' => fn ($query) => $query->where('is_active', true)->whereIn('kind', ['file', 'url'])])
                ->orderBy('name')
                ->get(),
            'extractors' => ($extractorRegistry ?? app(SourceExtractorRegistry::class))->available(),
        ];
    }

    public function index(SourceExtractorRegistry $extractorRegistry)
    {
        $imports = SourceExamImport::where('organization_id', Tenant::id())
            ->select([
                'id', 'organization_id', 'exam_id', 'name', 'status', 'question_source_name', 'answer_source_name',
                'solution_source_name', 'settings', 'detected_questions', 'review_questions', 'failure_message', 'created_at',
            ])
            ->with(['exam' => fn ($query) => $query->select('id', 'name', 'status')->withExists('questions')])
            ->latest()
            ->simplePaginate(20);
        $extractors = $extractorRegistry->available();

        return view('source-exams.index', compact('imports', 'extractors'));
    }

    public function create(SourceExtractorRegistry $extractorRegistry)
    {
        return view('source-exams.create', $this->formData($extractorRegistry));
    }

    public function store(
        Request $request,
        SourceExtractorRegistry $extractorRegistry,
        SourceExamImportProcessLauncher $processLauncher
    )
    {
        $data = $request->validate([
            'target_exam_ids' => 'required|array|min:1',
            'target_exam_ids.*' => 'integer|exists:exams,id',
            'question_source' => 'nullable|file|mimes:pdf,docx|max:102400',
            'answer_source' => 'nullable|file|mimes:pdf,docx,txt|max:102400',
            'solution_source' => 'nullable|file|mimes:pdf,docx,txt|max:102400',
            'extractor_script' => 'required|string',
            'qtype_id' => 'nullable|integer|exists:qtypes,id',
            'processing_action' => 'nullable|in:draft,queue',
            'profile' => 'nullable|array',
        ]);
        $tenant = (int) Tenant::id();
        if (! array_key_exists($data['extractor_script'], $extractorRegistry->available())) {
            throw ValidationException::withMessages([
                'extractor_script' => 'The selected Python extractor is not available.',
            ]);
        }
        $targetIds = array_values(array_unique(array_map('intval', $data['target_exam_ids'])));
        $targetExams = Exam::query()
            ->where('organization_id', $tenant)->whereIn('id', $targetIds)
            ->whereDoesntHave('questions')->whereDoesntHave('results')
            ->whereHas('qualitySources', fn ($query) => $query->where('is_active', true)->whereIn('kind', ['file', 'url'])->whereIn('role', ['questions', 'combined']))
            ->whereNotIn('id', SourceExamImport::query()
                    ->whereNotNull('exam_id')
                    ->whereIn('status', ['draft', 'queued', 'processing', 'review'])
                    ->pluck('exam_id'))
            ->with(['groups:id,group_name', 'packages:id,name,category_level_1,category_level_2', 'packages.groups:id', 'qualitySources' => fn ($query) => $query->where('is_active', true)->whereIn('kind', ['file', 'url'])])
            ->get();
        if ($targetExams->count() !== count($targetIds)) throw ValidationException::withMessages(['target_exam_ids' => 'One or more exams are unavailable, already imported, or do not have a usable private PDF source.']);
        if ($targetExams->count() > 1 && ($request->hasFile('question_source') || $request->hasFile('answer_source') || $request->hasFile('solution_source'))) throw ValidationException::withMessages(['target_exam_ids' => 'File overrides can only be used with one selected exam. Multiple exams use their own attached sources.']);

        $processingAction = $data['processing_action'] ?? 'queue';
        $imports = collect();
        $processingBatchToken = $processingAction === 'queue' ? (string) Str::uuid() : null;
        foreach ($targetExams as $targetExam) {
            $sources = $targetExam->qualitySources;
            $sourceFor = static fn (array $roles) => $sources->sortBy(fn ($source) => array_search($source->role, $roles, true))->first(fn ($source) => in_array($source->role, $roles, true));
            $linked = ['question' => $sourceFor(['questions', 'combined']), 'answer' => $sourceFor(['answers', 'combined']), 'solution' => $sourceFor(['combined'])];
            $uuid = (string) Str::uuid();
            $stored = [];
            foreach (['question', 'answer', 'solution'] as $role) {
                if ($request->hasFile($role.'_source')) {
                    $file = $request->file($role.'_source');
                    $stored[$role] = ['name' => $file->getClientOriginalName(), 'path' => $file->storeAs("source-exam-imports/{$tenant}/{$uuid}/{$role}", Str::uuid().'.'.$file->getClientOriginalExtension(), 'local'), 'type' => strtolower($file->getClientOriginalExtension())];
                    continue;
                }
                $source = $linked[$role] ?? null;
                if (! $source) continue;
                [$localPath, $cleanup] = app(\App\Services\ExamQualitySourceStorage::class)->localPath($source);
                $relative = "source-exam-imports/{$tenant}/{$uuid}/{$role}/".Str::uuid().'.pdf';
                $stream = fopen($localPath, 'rb');
                try {
                    if (! is_resource($stream) || ! Storage::disk('local')->put($relative, $stream)) throw new \RuntimeException('Unable to copy the existing '.$role.' source.');
                } finally {
                    if (is_resource($stream)) fclose($stream);
                    if ($cleanup) $cleanup();
                }
                $sourceName = $source->file_path ?: (parse_url((string) $source->source_url, PHP_URL_PATH) ?: 'source.pdf');
                $stored[$role] = ['name' => $source->label ?: basename($sourceName), 'path' => $relative, 'type' => 'pdf'];
            }
            if (empty($stored['question'])) throw ValidationException::withMessages(['target_exam_ids' => $targetExam->name.' has no usable question source.']);
            $settings = [
                'group_ids' => $targetExam->groups->pluck('id')->map(fn ($id) => (int) $id)->all(),
                'package_ids' => $targetExam->packages->pluck('id')->map(fn ($id) => (int) $id)->all(),
                'category_id' => $targetExam->category_level_1, 'subcategory_id' => $targetExam->category_level_2,
                'subject_id' => null, 'qtype_id' => ! empty($data['qtype_id']) ? (int) $data['qtype_id'] : null,
                'diff_id' => (int) Diff::query()->orderBy('id')->value('id'),
                'language_id' => (int) Language::enabledForOrganization($tenant)->orderBy('id')->value('id'),
                'duration' => (int) ($targetExam->duration ?? 180), 'attempt_count' => (int) ($targetExam->attempt_count ?? 1),
                'passing_percentage' => (int) ($targetExam->passing_percentage ?? 0), 'marks' => 1, 'negative_marks' => 0,
                'extractor_script' => $data['extractor_script'],
                'processing_batch_token' => $processingBatchToken,
                'profile' => $data['profile'] ?? [],
            ];
            $imports->push(SourceExamImport::create([
                'organization_id' => $tenant, 'created_by' => auth()->id(), 'exam_id' => $targetExam->id, 'name' => $targetExam->name,
                'status' => $processingAction === 'draft' ? 'draft' : 'queued',
                'question_source_name' => $stored['question']['name'], 'question_source_path' => $stored['question']['path'], 'question_source_type' => $stored['question']['type'],
                'answer_source_name' => $stored['answer']['name'] ?? null, 'answer_source_path' => $stored['answer']['path'] ?? null, 'answer_source_type' => $stored['answer']['type'] ?? null,
                'solution_source_name' => $stored['solution']['name'] ?? null, 'solution_source_path' => $stored['solution']['path'] ?? null, 'solution_source_type' => $stored['solution']['type'] ?? null,
                'settings' => $settings,
            ]));
        }
        if ($processingAction === 'queue') {
            $started = $processLauncher->startBatch($processingBatchToken, $imports->count());
            $message = $imports->count().' source exam(s) submitted. '.$started.' independent extraction worker(s) started immediately.';
        } else {
            $message = $imports->count().' source exam(s) saved as drafts.';
        }
        return redirect()->route('source-exams.index')->with('success', $message);
    }
    public function show(SourceExamImport $sourceExam)
    {
        $this->owned($sourceExam);
        $status = request()->validate([
            'status' => 'nullable|in:ready,needs_review,published',
        ])['status'] ?? null;
        $drafts = $sourceExam->drafts()
            ->when($status, fn ($query) => $query->where('status', $status))
            ->orderBy('paper_question_number')
            ->paginate(20)
            ->withQueryString();

        return view('source-exams.show', [
            'import' => $sourceExam,
            'drafts' => $drafts,
            'statusFilter' => $status,
            'statusCounts' => $sourceExam->drafts()
                ->selectRaw('status, COUNT(*) as aggregate')
                ->groupBy('status')
                ->pluck('aggregate', 'status'),
        ]);
    }

    public function previewDraft(SourceExamImport $sourceExam, SourceExamQuestionDraft $draft)
    {
        $this->owned($sourceExam);
        abort_unless((int) $draft->source_exam_import_id === (int) $sourceExam->id, 404);
        $payload = (array) $draft->payload;
        $qtype = Qtype::find((int) ($payload['qtype_id'] ?? 0));

        return response()->view('question-drafts.preview', [
            'payload' => $payload,
            'title' => $sourceExam->name,
            'questionLabel' => 'Paper Q. '.($draft->printed_question_number ?: $draft->paper_question_number),
            'questionType' => (string) ($qtype?->question_type ?: $qtype?->type ?: 'Question'),
            'status' => (string) $draft->status,
            'contextLabel' => 'Extraction draft',
            'backUrl' => route('source-exams.drafts.edit', [$sourceExam, $draft]),
        ])->header('Cache-Control', 'private, no-store, max-age=0');
    }
    public function previewPaper(SourceExamImport $sourceExam)
    {
        $this->owned($sourceExam);
        $drafts = $sourceExam->drafts()
            ->with('question.qtype:id,question_type,type')
            ->orderBy('paper_question_number')
            ->orderBy('id')
            ->get();
        $qtypes = Qtype::whereIn('id', $drafts->pluck('payload')->map(
            fn ($payload) => (int) data_get($payload, 'qtype_id')
        )->filter()->unique())->get()->keyBy('id');
        $questions = $drafts->map(function (SourceExamQuestionDraft $draft) use ($qtypes) {
            $payload = (array) $draft->payload;
            if ($draft->status === 'published' && $draft->question) {
                $payload = array_replace($payload, $draft->question->only(QuestionRepairService::FIELDS));
            }
            $qtype = $draft->status === 'published' && $draft->question?->qtype
                ? $draft->question->qtype
                : $qtypes->get((int) ($payload['qtype_id'] ?? 0));

            return [
                'payload' => $payload,
                'label' => 'Paper Q. '.($draft->printed_question_number ?: $draft->paper_question_number),
                'type' => (string) ($qtype?->question_type ?: $qtype?->type ?: 'Question'),
                'status' => (string) $draft->status,
            ];
        })->values();

        return response()->view('question-drafts.paper-preview', [
            'questions' => $questions,
            'title' => $sourceExam->name,
            'contextLabel' => 'Full extraction draft',
            'backUrl' => route('source-exams.show', $sourceExam),
        ])->header('Cache-Control', 'private, no-store, max-age=0');
    }
    public function sourceDraftPage(Request $request, SourceExamImport $sourceExam, SourceExamQuestionDraft $draft, SourceExamImportService $service)
    {
        $this->owned($sourceExam);
        abort_unless((int) $draft->source_exam_import_id === (int) $sourceExam->id, 404);
        $data = $request->validate(['page' => 'required|integer|min:1']);
        try {
            return response()->file($service->renderDraftSourcePage($sourceExam, (int) $data['page']), [
                'Cache-Control' => 'private, no-store, max-age=0',
            ])->deleteFileAfterSend(true);
        } catch (\Throwable $e) {
            abort(422, $e->getMessage());
        }
    }
    public function cropDraftImage(Request $request, SourceExamImport $sourceExam, SourceExamQuestionDraft $draft, SourceExamImportService $service)
    {
        $this->owned($sourceExam);
        abort_unless((int) $draft->source_exam_import_id === (int) $sourceExam->id, 404);
        abort_if($sourceExam->status === 'published', 422, 'Published extraction drafts cannot be cropped. Edit the live question instead.');
        $targets = 'question,option1,option2,option3,option4,option5,option6,explanation';
        $data = $request->validate([
            'page' => 'required|integer|min:1', 'target_field' => 'required|in:'.$targets,
            'next_target' => 'nullable|in:'.$targets,
            'background_mode' => 'nullable|in:white,transparent',
            'crop_mode' => 'nullable|in:image,mathpix', 'confirmed' => 'nullable|boolean',
            'recognized_html' => 'nullable|string|max:100000', 'mathpix_request_id' => 'nullable|string|max:255', 'mathpix_confidence' => 'nullable|numeric|between:0,100',
            'x0' => 'required|numeric|between:0,1', 'y0' => 'required|numeric|between:0,1',
            'x1' => 'required|numeric|between:0,1', 'y1' => 'required|numeric|between:0,1',
        ]);
        if ((float) $data['x0'] >= (float) $data['x1'] || (float) $data['y0'] >= (float) $data['y1']) {
            throw ValidationException::withMessages(['crop' => 'Draw a valid crop region on the source page.']);
        }
        if (($data['crop_mode'] ?? 'image') === 'mathpix') {
            try {
                if (! empty($data['confirmed'])) {
                    $evidence = $service->applyManualOcrText($draft, $data['target_field'], (string) ($data['recognized_html'] ?? ''), [
                        'provider' => 'mathpix', 'request_id' => $data['mathpix_request_id'] ?? null,
                        'confidence' => isset($data['mathpix_confidence']) ? (float) $data['mathpix_confidence'] : null,
                        'source_page' => (int) $data['page'],
                        'bbox_normalized' => [(float)$data['x0'],(float)$data['y0'],(float)$data['x1'],(float)$data['y1']],
                    ]);
                    return response()->json(['message' => ucfirst($data['target_field']).' Mathpix text inserted and saved to the extraction draft.', 'target' => $data['target_field'], 'html' => data_get($draft->fresh()->payload, $data['target_field']), 'recognition' => $evidence]);
                }
                $image = $service->extractMathpixCrop($draft, (int) $data['page'], [(float)$data['x0'],(float)$data['y0'],(float)$data['x1'],(float)$data['y1']]);
                try { $recognition = app(\App\Services\MathpixOcrService::class)->recognize($image, (int) $draft->organization_id); }
                finally { @unlink($image); }
                return response()->json(['message' => 'Mathpix recognition is ready for review.', 'target' => $data['target_field'], 'preview_required' => true, 'recognition' => $recognition]);
            } catch (\Throwable $exception) { return response()->json(['message' => $exception->getMessage()], 422); }
        }
        try {
            $crop = $service->applyManualImageCrop($draft, (int) $data['page'], [
                (float) $data['x0'], (float) $data['y0'], (float) $data['x1'], (float) $data['y1'],
            ], $data['target_field'], $data['background_mode'] ?? 'white');
            $fresh = $draft->fresh();
            $message = ucfirst($data['target_field']).' crop auto-saved to this extraction draft.';
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => $message, 'target' => $data['target_field'],
                    'next_target' => $data['next_target'] ?? $data['target_field'],
                    'html' => data_get($fresh->payload, $data['target_field']), 'crop' => $crop,
                ]);
            }
            return redirect()->route('source-exams.drafts.edit', [
                'sourceExam' => $sourceExam, 'draft' => $draft, 'crop_page' => (int) $data['page'],
                'crop_target' => $data['next_target'] ?? $data['target_field'],
            ])->with('success', $message);
        } catch (\Throwable $e) {
            if ($request->expectsJson()) return response()->json(['message' => $e->getMessage()], 422);
            return back()->withInput()->with('error', $e->getMessage());
        }
    }
    public function editDraft(SourceExamImport $sourceExam, SourceExamQuestionDraft $draft)
    {
        $this->owned($sourceExam);
        abort_unless((int) $draft->source_exam_import_id === (int) $sourceExam->id, 404);

        return view('source-exams.edit-draft', [
            'import' => $sourceExam,
            'draft' => $draft,
            'qtypes' => Qtype::displayOrdered(),
        ]);
    }
    public function updateDraft(Request $request, SourceExamImport $sourceExam, SourceExamQuestionDraft $draft)
    {
        $this->owned($sourceExam); abort_unless((int) $draft->source_exam_import_id === (int) $sourceExam->id, 404);
        $validated = $request->validate([
            'question' => 'nullable|string', 'option1' => 'nullable|string', 'option2' => 'nullable|string', 'option3' => 'nullable|string',
            'option4' => 'nullable|string', 'option5' => 'nullable|string', 'option6' => 'nullable|string',
            'correct_answers' => 'nullable|array|max:6', 'correct_answers.*' => 'integer|min:1|max:6',
            'fill_blank_answers' => 'nullable|array|max:20', 'fill_blank_answers.*.accepted_answers' => 'nullable|string|max:2000',
            'nat_mode' => 'nullable|in:exact,range,tolerance', 'nat_value' => 'nullable|numeric', 'nat_tolerance' => 'nullable|numeric|min:0',
            'nat_min' => 'nullable|numeric', 'nat_max' => 'nullable|numeric', 'true_false' => 'nullable|in:true,false', 'si_answer1' => 'nullable|string',
            'explanation' => 'nullable|string', 'qtype_id' => 'required|integer|exists:qtypes,id', 'marks' => 'required|numeric|min:0',
            'negative_marks' => 'required|numeric|min:0', 'review_notes' => 'nullable|string',
        ]);
        $notes = $validated['review_notes'] ?? null;
        $type = strtoupper((string) Qtype::whereKey($validated['qtype_id'])->value('type'));
        $payload = array_merge($draft->payload, collect($validated)->except([
            'review_notes', 'correct_answers', 'fill_blank_answers', 'nat_mode', 'nat_value', 'nat_tolerance', 'nat_min', 'nat_max', 'true_false', 'si_answer1',
        ])->all());
        foreach (['correct_answers', 'fill_blank_config', 'nat_config', 'true_false', 'si_answer1'] as $field) unset($payload[$field]);
        $payload['correct_answer'] = null;

        if ($type === 'M') {
            $answers = collect($validated['correct_answers'] ?? [])->map(fn ($value) => (int) $value)->filter(fn ($value) => $value >= 1 && $value <= 6)->unique()->sort()->values()->all();
            $payload['correct_answers'] = $answers;
            $payload['correct_answer'] = $answers ? chr(64 + $answers[0]) : null;
        } elseif (in_array($type, ['F', 'B'], true)) {
            $blanks = collect($validated['fill_blank_answers'] ?? [])->map(function ($blank) {
                $answers = collect(explode('|', (string) ($blank['accepted_answers'] ?? '')))->map(fn ($answer) => trim(strip_tags($answer)))->filter()->unique()->values()->all();
                return $answers ? ['answers' => $answers] : null;
            })->filter()->values()->all();
            if (! $blanks) throw ValidationException::withMessages(['fill_blank_answers' => 'Add at least one blank and its accepted answer.']);
            $payload['fill_blank_config'] = ['version' => 1, 'blanks' => $blanks];
            $payload['correct_answer'] = $blanks[0]['answers'][0];
        } elseif ($type === 'NAT') {
            $mode = $validated['nat_mode'] ?? 'exact';
            if ($mode === 'range') {
                if (! isset($validated['nat_min'], $validated['nat_max']) || (float) $validated['nat_min'] > (float) $validated['nat_max']) throw ValidationException::withMessages(['nat_min' => 'Enter a valid minimum and maximum range.']);
                $payload['nat_config'] = ['version' => 1, 'mode' => 'range', 'min' => (float) $validated['nat_min'], 'max' => (float) $validated['nat_max']];
            } else {
                if (! isset($validated['nat_value'])) throw ValidationException::withMessages(['nat_value' => 'Enter the correct numerical value.']);
                $payload['nat_config'] = ['version' => 1, 'mode' => $mode, 'value' => (float) $validated['nat_value']];
                if ($mode === 'tolerance') $payload['nat_config']['tolerance'] = (float) ($validated['nat_tolerance'] ?? 0);
                $payload['correct_answer'] = (string) $validated['nat_value'];
            }
        } elseif ($type === 'T') {
            $payload['true_false'] = $validated['true_false'] ?? null;
            $payload['correct_answer'] = $payload['true_false'];
        } elseif ($type === 'S') {
            $payload['si_answer1'] = $validated['si_answer1'] ?? null;
            $payload['correct_answer'] = $payload['si_answer1'];
        }

        $sourceEvidence = app(StructuredContentReviewService::class)->reconcileEvidence((array) $draft->source_evidence, $payload);
        $hasPendingStructuredReview = collect((array) data_get($sourceEvidence, 'structured_content', []))
            ->contains(fn ($item) => ($item['status'] ?? null) !== 'reviewed');
        $draft->update([
            'payload' => $payload,
            'source_evidence' => $sourceEvidence,
            'review_notes' => $notes,
            'status' => $hasPendingStructuredReview ? 'needs_review' : 'ready',
        ]);
        return back()->with('success', $hasPendingStructuredReview
            ? 'Question draft saved and still marked Needs review. An administrator may review it or publish it as a final decision.'
            : 'Question draft saved and marked ready.');
    }
    public function publish(Request $request, SourceExamImport $sourceExam, SourceExamImportService $service)
    {
        $this->owned($sourceExam); $data = $request->validate(['draft_ids' => 'nullable|array', 'draft_ids.*' => 'integer']);
        try {
            if (! $sourceExam->exam_id) SaasAccess::abortIfLimitReached('exams');
            $exam = $service->publish($sourceExam, array_map('intval', $data['draft_ids'] ?? []));
            return redirect()->route('source-exams.show', $sourceExam)->with(
                'success',
                'Exam and selected questions published. The extracted questions remain available here for review and editing.'
            );
        } catch (\Throwable $e) { return back()->with('error', 'Publication failed: '.$e->getMessage()); }
    }

    public function processSelected(
        Request $request,
        SourceExtractorRegistry $extractorRegistry,
        SourceExamImportProcessLauncher $processLauncher
    )
    {
        $tenant = (int) Tenant::id();
        $data = $request->validate([
            'import_ids' => 'required|array|min:1',
            'import_ids.*' => 'integer',
            'extractors' => 'nullable|array',
            'extractors.*' => 'nullable|string',
        ]);
        $ids = array_unique(array_map('intval', $data['import_ids']));
        $imports = SourceExamImport::query()
            ->where('organization_id', $tenant)
            ->whereIn('id', $ids)
            ->whereIn('status', ['draft', 'failed'])
            ->get();
        $available = $extractorRegistry->available();

        $batchToken = (string) Str::uuid();
        foreach ($imports as $import) {
            $extractor = trim((string) data_get(
                $data,
                "extractors.{$import->id}",
                data_get($import->settings, 'extractor_script', SourceExtractorRegistry::BUILTIN)
            ));
            if (! array_key_exists($extractor, $available)) {
                throw ValidationException::withMessages([
                    "extractors.{$import->id}" => 'The selected Python extractor is not available.',
                ]);
            }

            $settings = (array) $import->settings;
            $settings['extractor_script'] = $extractor;
            $settings['processing_batch_token'] = $batchToken;

            $import->update([
                'status' => 'queued',
                'failure_message' => null,
                'processing_started_at' => null,
                'processing_completed_at' => null,
                'settings' => $settings,
            ]);
        }

        if ($imports->isEmpty()) return back()->with('error', 'Select at least one unprocessed source-exam draft.');
        $started = $processLauncher->startBatch($batchToken, $imports->count());
        return back()->with('success', $imports->count().' source exam(s) submitted with their selected extractor. '.$started.' independent worker(s) started immediately.');
    }

    public function publishSelected(Request $request, SourceExamImportService $service)
    {
        $tenant = (int) Tenant::id();
        $data = $request->validate([
            'import_ids' => 'required|array|min:1',
            'import_ids.*' => 'integer',
        ]);
        $imports = SourceExamImport::query()
            ->where('organization_id', $tenant)
            ->whereIn('id', array_unique(array_map('intval', $data['import_ids'])))
            ->withCount(['drafts as pending_review_count' => fn ($query) => $query->where('status', 'needs_review')])
            ->get();

        $published = 0;
        $questions = 0;
        $errors = [];
        foreach ($imports as $import) {
            if ($import->status !== 'review') {
                $errors[] = $import->name.': not ready for publication.';
                continue;
            }
            try {
                if (! $import->exam_id) SaasAccess::abortIfLimitReached('exams');
                $exam = $service->publish($import);
                $published++;
                $questions += $exam->questions()->count();
            } catch (\Throwable $e) {
                $errors[] = $import->name.': '.$e->getMessage();
            }
        }

        if ($published === 0) return back()->with('error', implode(' ', $errors) ?: 'No selected paper was ready to publish.');
        $message = "{$published} paper(s) and {$questions} question(s) published.";
        if ($errors) $message .= ' Some papers were skipped: '.implode(' ', $errors);
        return back()->with($errors ? 'warning' : 'success', $message);
    }

    public function auditSelected(Request $request)
    {
        $tenant = (int) Tenant::id();
        $data = $request->validate([
            'import_ids' => 'required|array|min:1',
            'import_ids.*' => 'integer',
        ]);
        $examIds = SourceExamImport::query()
            ->where('organization_id', $tenant)
            ->whereIn('id', array_unique(array_map('intval', $data['import_ids'])))
            ->whereNotNull('exam_id')
            ->whereHas('exam.questions')
            ->pluck('exam_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        if ($examIds->isEmpty()) {
            return back()->with('error', 'Select at least one published paper with questions to audit.');
        }

        return redirect()->route('exam-quality.index', ['exam_ids' => $examIds->all()]);
    }

    public function retry(SourceExamImport $sourceExam, SourceExamImportProcessLauncher $processLauncher)
    {
        $this->owned($sourceExam); abort_if($sourceExam->status === 'published', 422, 'A published import cannot be regenerated.');
        $sourceExam->update([
            'status' => 'queued', 'failure_message' => null,
            'processing_started_at' => null, 'processing_completed_at' => null,
        ]);
        $processLauncher->start($sourceExam->id);
        return back()->with('success', 'Extraction retry started immediately.');
    }
    public function reviewStructuredContent(Request $request, SourceExamImport $sourceExam, SourceExamQuestionDraft $draft, StructuredContentReviewService $service)
    {
        $this->owned($sourceExam);
        abort_unless((int) $draft->source_exam_import_id === (int) $sourceExam->id, 404);
        $data = $request->validate([
            'item_id' => 'nullable|string', 'item_ids' => 'nullable|array', 'item_ids.*' => 'string',
            'decision' => 'required|in:accept,keep_original', 'candidate_html' => 'nullable|string',
        ]);
        $ids = array_values(array_unique(array_filter(array_merge((array) ($data['item_ids'] ?? []), [$data['item_id'] ?? null]))));
        if ($ids === []) return back()->with('error', 'Select at least one structured-content item.');
        try {
            foreach ($ids as $id) $service->reviewSourceItem($draft->fresh(), $id, $data['decision'], count($ids) === 1 ? ($data['candidate_html'] ?? null) : null);
            return back()->with('success', count($ids).' structured-content item(s) reviewed.');
        } catch (\Throwable $e) { return back()->with('error', $e->getMessage()); }
    }

}

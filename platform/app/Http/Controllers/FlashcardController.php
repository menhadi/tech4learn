<?php

namespace App\Http\Controllers;

use App\Exports\RowsExport;
use App\Imports\RowsImport;
use App\Models\Flashcard;
use App\Models\FlashcardSet;
use App\Models\Category;
use App\Models\Diff;
use App\Models\Exam;
use App\Models\Group;
use App\Models\Language;
use App\Models\Package;
use App\Models\Question;
use App\Models\Qtype;
use App\Models\Stopic;
use App\Models\Subject;
use App\Models\Topic;
use App\Services\FlashcardAiGeneratorService;
use App\Services\CurriculumTaxonomyService;
use App\Support\SaasAccess;
use App\Support\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Excel as ExcelWriter;

class FlashcardController extends Controller
{
    private function tenantId(): int
    {
        return (int) Tenant::id();
    }

    private function ensureTenantSet(FlashcardSet $flashcardSet): void
    {
        if ((int) $flashcardSet->organization_id !== $this->tenantId()) {
            abort(404);
        }
    }

    private function formData(): array
    {
        $tenantId = $this->tenantId();

        return [
            'packages' => Package::query()
                ->where('organization_id', $tenantId)
                ->where('status', 1)
                ->with(['groups:id'])
                ->orderBy('name')
                ->get(['id', 'name', 'flashcards_enabled', 'category_level_1', 'category_level_2']),
            'categoryNames' => Category::query()
                ->where('organization_id', $tenantId)
                ->orderBy('title')
                ->pluck('title', 'id'),
            'categories' => Category::query()
                ->where('organization_id', $tenantId)
                ->whereNull('parent_id')
                ->orderBy('title')
                ->get(['id', 'title']),
            'subcategories' => Category::query()
                ->where('organization_id', $tenantId)
                ->whereNotNull('parent_id')
                ->orderBy('title')
                ->get(['id', 'title', 'parent_id']),
            'groups' => Group::query()
                ->where('organization_id', $tenantId)
                ->orderBy('group_name')
                ->get(['id', 'group_name']),
            'subjects' => Subject::query()
                ->where('organization_id', $tenantId)
                ->with(['groups:id'])
                ->orderBy('subject_name')
                ->get(['id', 'subject_name']),
            'topics' => Topic::query()
                ->whereHas('group', fn ($query) => $query->where('organization_id', $tenantId))
                ->orderBy('name')
                ->get(['id', 'name', 'subject_id', 'group_id']),
            'stopics' => Stopic::query()
                ->whereHas('group', fn ($query) => $query->where('organization_id', $tenantId))
                ->orderBy('name')
                ->get(['id', 'name', 'subject_id', 'topic_id', 'group_id']),
        ];
    }

    public function index(Request $request)
    {
        SaasAccess::abortIfFeatureDisabled('flashcards');
        $perPage = $this->perPage($request, 50);

        $query = FlashcardSet::query()
            ->with([
                'package:id,name',
                'group:id,group_name',
                'category:id,title',
                'subcategory:id,title',
                'subject:id,subject_name',
                'topic:id,name',
                'stopic:id,name',
            ])
            ->withCount('cards')
            ->where('organization_id', $this->tenantId());

        if ($request->filled('package_id')) {
            $query->where('package_id', $request->integer('package_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->boolean('status'));
        }

        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));
            $query->where('title', 'like', '%' . $search . '%');
        }

        $sets = $query->orderByRaw('CASE WHEN display_order IS NULL OR display_order = 0 THEN 1 ELSE 0 END')->orderBy('display_order')->orderBy('title')->paginate($perPage)->withQueryString();

        return view('flashcards.index', array_merge(
            compact('sets', 'perPage'),
            $this->formData()
        ));
    }

    public function store(Request $request)
    {
        SaasAccess::abortIfFeatureDisabled('flashcards');

        $data = $this->validateSet($request);
        $package = $this->flashcardPackage((int) $data['package_id']);

        FlashcardSet::create($data + [
            'organization_id' => $this->tenantId(),
            'package_id' => $package->id,
            'created_by' => Auth::id(),
            'source_type' => 'manual',
            'status' => $request->boolean('status', true),
        ]);

        return redirect()->route('flashcards.index')->with('success', 'Study card set created successfully.');
    }

    public function show(Request $request, FlashcardSet $flashcardSet)
    {
        SaasAccess::abortIfFeatureDisabled('flashcards');
        $this->ensureTenantSet($flashcardSet);

        $perPage = $this->perPage($request, 50);
        $flashcardSet->load([
            'package:id,name,ai_flashcard_generation_enabled',
            'group:id,group_name',
            'category:id,title',
            'subcategory:id,title',
            'subject:id,subject_name',
            'topic:id,name',
            'stopic:id,name',
        ]);

        $cards = $flashcardSet->cards()
            ->with([
                'sourceQuestion.qtype:id,type,question_type',
                'sourceQuestions.subject:id,subject_name',
                'sourceQuestions.topic:id,name',
                'sourceQuestions.qtype:id,type,question_type',
            ])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->paginate($perPage, ['*'], 'card_page')
            ->withQueryString();

        return view('flashcards.show', compact('flashcardSet', 'cards', 'perPage'));
    }

    public function downloadCardTemplate(Request $request, FlashcardSet $flashcardSet)
    {
        SaasAccess::abortIfFeatureDisabled('flashcards');
        $this->ensureTenantSet($flashcardSet);

        $rows = [
            $this->cardSheetHeadings(),
            [
                'Newton Laws Basics',
                'Newton Second Law',
                'Force equals mass multiplied by acceleration.',
                '1',
                'active',
                '101,102',
            ],
        ];

        return $this->downloadCardRows($rows, 'study-card-import-template', $request->query('format', 'csv'));
    }

    public function exportCards(Request $request, FlashcardSet $flashcardSet)
    {
        SaasAccess::abortIfFeatureDisabled('flashcards');
        $this->ensureTenantSet($flashcardSet);

        $rows = [$this->cardSheetHeadings()];

        $flashcardSet->cards()
            ->with('sourceQuestions:id')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->chunk(200, function ($cards) use (&$rows) {
                foreach ($cards as $card) {
                    $linkedQuestionIds = $card->sourceQuestions->pluck('id')->all();

                    if (empty($linkedQuestionIds) && $card->source_question_id) {
                        $linkedQuestionIds = [(int) $card->source_question_id];
                    }

                    $rows[] = [
                        $card->title,
                        $card->front,
                        $card->back,
                        $card->sort_order,
                        $card->status ? 'active' : 'inactive',
                        implode(',', array_unique($linkedQuestionIds)),
                    ];
                }
            });

        return $this->downloadCardRows($rows, 'study-cards-' . $flashcardSet->id, $request->query('format', 'xlsx'));
    }

    public function importCards(Request $request, FlashcardSet $flashcardSet)
    {
        SaasAccess::abortIfFeatureDisabled('flashcards');
        $this->ensureTenantSet($flashcardSet);

        $request->validate([
            'card_file' => ['required', 'file', 'mimes:csv,txt,xlsx,xls'],
        ]);

        $sheets = Excel::toArray(new RowsImport(), $request->file('card_file'));
        $sheetRows = $sheets[0] ?? [];

        if (count($sheetRows) < 2) {
            return back()->with('error', 'The import file must include a heading row and at least one study card row.');
        }

        $headings = collect(array_shift($sheetRows))
            ->map(fn ($heading) => $this->normaliseCardSheetHeading($heading))
            ->all();

        $nextOrder = (int) $flashcardSet->cards()->max('sort_order');
        $created = 0;
        $skipped = 0;

        DB::transaction(function () use ($sheetRows, $headings, $flashcardSet, &$nextOrder, &$created, &$skipped) {
            foreach ($sheetRows as $row) {
                $payload = [];

                foreach ($headings as $index => $heading) {
                    if ($heading) {
                        $payload[$heading] = $row[$index] ?? null;
                    }
                }

                $front = trim((string) ($payload['front'] ?? ''));
                $back = trim((string) ($payload['back'] ?? ''));
                $title = trim((string) ($payload['title'] ?? ''));

                if ($front === '' || $back === '') {
                    $skipped++;
                    continue;
                }

                $questionIds = $this->questionIdsFromSheet($payload['linked_question_ids'] ?? null);

                $card = $flashcardSet->cards()->create([
                    'title' => $title !== '' ? $title : null,
                    'front' => $front,
                    'back' => $back,
                    'card_type' => 'basic',
                    'options' => null,
                    'explanation' => null,
                    'hint' => null,
                    'difficulty' => null,
                    'source_label' => 'Imported sheet',
                    'source_question_id' => $questionIds[0] ?? null,
                    'ai_generated' => false,
                    'sort_order' => is_numeric($payload['sort_order'] ?? null) ? (int) $payload['sort_order'] : ++$nextOrder,
                    'status' => $this->sheetStatus($payload['status'] ?? true),
                ]);

                if (! empty($questionIds)) {
                    $this->syncFlashcardQuestions($card, $questionIds);
                }

                $created++;
            }
        });

        $message = $created . ' study card(s) imported successfully.';

        if ($skipped > 0) {
            $message .= ' ' . $skipped . ' row(s) were skipped because front or back was blank.';
        }

        return redirect()
            ->route('flashcards.show', [$flashcardSet, 'per_page' => $this->perPage($request, 50)])
            ->with('success', $message);
    }

    public function update(Request $request, FlashcardSet $flashcardSet)
    {
        SaasAccess::abortIfFeatureDisabled('flashcards');
        $this->ensureTenantSet($flashcardSet);

        $data = $this->validateSet($request);
        $this->flashcardPackage((int) $data['package_id']);
        $data['status'] = $request->boolean('status', true);

        $flashcardSet->update($data);

        return redirect()->route('flashcards.index')->with('success', 'Study card set updated successfully.');
    }

    public function destroy(FlashcardSet $flashcardSet)
    {
        SaasAccess::abortIfFeatureDisabled('flashcards');
        $this->ensureTenantSet($flashcardSet);
        $flashcardSet->delete();

        return redirect()->route('flashcards.index')->with('success', 'Study card set removed successfully.');
    }

    public function storeCard(Request $request, FlashcardSet $flashcardSet)
    {
        SaasAccess::abortIfFeatureDisabled('flashcards');
        $this->ensureTenantSet($flashcardSet);

        $data = $this->validateCard($request);
        $questionIds = $this->questionIdsFromRequest($request);
        $manualChecks = $data['manual_checks'] ?? [];
        unset($data['manual_checks']);
        $flashcard = $flashcardSet->cards()->create($data);
        $this->syncFlashcardQuestions($flashcard, $questionIds);
        $this->syncManualChecks($flashcard, $manualChecks);

        return redirect()
            ->route('flashcards.show', [$flashcardSet, 'per_page' => $this->perPage($request, 50)])
            ->with('success', 'Study card added successfully.');
    }

    public function createCard(Request $request, FlashcardSet $flashcardSet)
    {
        SaasAccess::abortIfFeatureDisabled('flashcards');
        $this->ensureTenantSet($flashcardSet);
        $flashcardSet->load([
            'package:id,name',
            'group:id,group_name',
            'category:id,title',
            'subcategory:id,title',
            'subject:id,subject_name',
            'topic:id,name',
            'stopic:id,name',
        ]);

        return view('flashcards.card-form', array_merge([
            'flashcardSet' => $flashcardSet,
            'card' => new Flashcard(['status' => true, 'card_type' => 'basic']),
            'mode' => 'create',
        ], $this->questionBankData($request, $flashcardSet)));
    }

    public function editCard(Request $request, FlashcardSet $flashcardSet, Flashcard $flashcard)
    {
        SaasAccess::abortIfFeatureDisabled('flashcards');
        $this->ensureTenantSet($flashcardSet);
        abort_if((int) $flashcard->flashcard_set_id !== (int) $flashcardSet->id, 404);
        $flashcardSet->load([
            'package:id,name',
            'group:id,group_name',
            'category:id,title',
            'subcategory:id,title',
            'subject:id,subject_name',
            'topic:id,name',
            'stopic:id,name',
        ]);
        $flashcard->load([
            'sourceQuestion.subject:id,subject_name',
            'sourceQuestion.topic:id,name',
            'sourceQuestion.qtype:id,question_type,type',
            'sourceQuestions.subject:id,subject_name',
            'sourceQuestions.topic:id,name',
            'sourceQuestions.qtype:id,question_type,type',
            'checks',
        ]);

        return view('flashcards.card-form', array_merge([
            'flashcardSet' => $flashcardSet,
            'card' => $flashcard,
            'mode' => 'edit',
        ], $this->questionBankData($request, $flashcardSet, $flashcard)));
    }

    public function createCardsFromQuestions(Request $request, FlashcardSet $flashcardSet)
    {
        SaasAccess::abortIfFeatureDisabled('flashcards');
        $this->ensureTenantSet($flashcardSet);

        $data = $request->validate([
            'question_count' => ['required', 'integer', 'in:50,100,500'],
            'publish_status' => ['nullable', 'in:draft,active'],
        ]);

        $status = ($data['publish_status'] ?? 'draft') === 'active';
        $existingQuestionIds = $flashcardSet->cards()
            ->whereNotNull('source_question_id')
            ->pluck('source_question_id')
            ->filter()
            ->values();

        $query = $this->baseObjectiveQuestionQuery()
            ->when($existingQuestionIds->isNotEmpty(), fn ($query) => $query->whereNotIn('questions.id', $existingQuestionIds))
            ->inRandomOrder()
            ->limit((int) $data['question_count']);

        $this->applyQuestionBankFilters($query, $request, $flashcardSet);

        $questions = $query->get();
        $nextOrder = (int) $flashcardSet->cards()->max('sort_order');
        $created = 0;

        foreach ($questions as $question) {
            $card = $this->questionToFlashcard($question, ++$nextOrder, $status);

            if (! $card) {
                continue;
            }

            $createdCard = $flashcardSet->cards()->create($card);
            $this->syncFlashcardQuestions($createdCard, [(int) $question->id]);
            $created++;
        }

        if ($created === 0) {
            return redirect()
                ->route('flashcards.show', $flashcardSet)
                ->with('error', 'No new MCQ, True/False, or Fill in the Blank questions matched this set scope.');
        }

        return redirect()
            ->route('flashcards.show', $flashcardSet)
            ->with('success', $created . ' flashcards created from existing questions.');
    }

    public function addSelectedQuestions(Request $request, FlashcardSet $flashcardSet)
    {
        SaasAccess::abortIfFeatureDisabled('flashcards');
        $this->ensureTenantSet($flashcardSet);

        $data = $request->validate([
            'question_ids' => ['required', 'array', 'min:1'],
            'question_ids.*' => ['integer'],
            'publish_status' => ['nullable', 'in:draft,active'],
        ]);

        if ($request->input('return_to') === 'edit' && $request->filled('card_id')) {
            $flashcard = $flashcardSet->cards()->findOrFail($request->integer('card_id'));
            $questions = $this->validObjectiveQuestions($data['question_ids']);

            if ($questions->isEmpty()) {
                return redirect()
                    ->route('flashcards.cards.edit', [$flashcardSet, $flashcard])
                    ->with('error', 'No selected objective question could be linked to this flashcard.');
            }

            $this->attachFlashcardQuestions($flashcard, $questions->pluck('id')->all());

            return redirect()
                ->route('flashcards.cards.edit', [$flashcardSet, $flashcard])
                ->with('success', $questions->count() . ' selected question(s) added successfully.');
        }

        $status = ($data['publish_status'] ?? 'draft') === 'active';
        $existingQuestionIds = $flashcardSet->cards()
            ->whereNotNull('source_question_id')
            ->pluck('source_question_id')
            ->filter()
            ->values();

        $questions = $this->baseObjectiveQuestionQuery()
            ->whereIn('questions.id', $data['question_ids'])
            ->when($existingQuestionIds->isNotEmpty(), fn ($query) => $query->whereNotIn('questions.id', $existingQuestionIds))
            ->get();

        $nextOrder = (int) $flashcardSet->cards()->max('sort_order');
        $created = 0;

        foreach ($questions as $question) {
            $card = $this->questionToFlashcard($question, ++$nextOrder, $status);

            if (! $card) {
                continue;
            }

            $createdCard = $flashcardSet->cards()->create($card);
            $this->syncFlashcardQuestions($createdCard, [(int) $question->id]);
            $created++;
        }

        if ($created === 0) {
            return back()->with('error', 'No selected questions could be added. They may already exist in this set or may not be objective questions.');
        }

        $redirect = match ($request->input('return_to')) {
            'create' => redirect()->route('flashcards.cards.create', $flashcardSet),
            'edit' => redirect()->route('flashcards.cards.edit', [$flashcardSet, (int) $request->input('card_id')]),
            default => redirect()->route('flashcards.show', $flashcardSet),
        };

        return $redirect->with('success', $created . ' selected questions added as flashcards.');
    }

    public function linkQuestionsByScope(Request $request, FlashcardSet $flashcardSet)
    {
        SaasAccess::abortIfFeatureDisabled('flashcards');
        $this->ensureTenantSet($flashcardSet);

        $data = $request->validate([
            'questions_per_card' => ['nullable', 'integer', 'min:1', 'max:5'],
            'only_empty_cards' => ['nullable', 'boolean'],
        ]);

        $questionsPerCard = (int) ($data['questions_per_card'] ?? 1);
        $onlyEmptyCards = $request->boolean('only_empty_cards', true);

        $cards = $flashcardSet->cards()
            ->withCount('sourceQuestions')
            ->when($onlyEmptyCards, function ($query) {
                $query->whereDoesntHave('sourceQuestions')->whereNull('source_question_id');
            })
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        if ($cards->isEmpty()) {
            return redirect()
                ->route('flashcards.show', $flashcardSet)
                ->with('error', 'No eligible flashcards found. Existing cards already have linked questions.');
        }

        $usedQuestionIds = DB::table('flashcard_question_links')
            ->join('flashcards', 'flashcards.id', '=', 'flashcard_question_links.flashcard_id')
            ->where('flashcards.flashcard_set_id', $flashcardSet->id)
            ->pluck('flashcard_question_links.question_id')
            ->merge(
                $flashcardSet->cards()
                    ->whereNotNull('source_question_id')
                    ->pluck('source_question_id')
            )
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        $needed = $cards->count() * $questionsPerCard;
        $freshQuestions = $this->questionScopeQuery($flashcardSet)
            ->when($usedQuestionIds->isNotEmpty(), fn ($query) => $query->whereNotIn('questions.id', $usedQuestionIds))
            ->inRandomOrder()
            ->limit($needed)
            ->get(['questions.*']);

        $usedRepeatedQuestions = false;
        if ($freshQuestions->count() < $needed) {
            $remaining = $needed - $freshQuestions->count();
            $selectedQuestionIds = $freshQuestions->pluck('id')->map(fn ($id) => (int) $id)->all();
            $reusedQuestions = $this->questionScopeQuery($flashcardSet)
                ->when(! empty($selectedQuestionIds), fn ($query) => $query->whereNotIn('questions.id', $selectedQuestionIds))
                ->inRandomOrder()
                ->limit($remaining)
                ->get(['questions.*']);

            if ($reusedQuestions->isNotEmpty()) {
                $freshQuestions = $freshQuestions->concat($reusedQuestions)->values();
                $usedRepeatedQuestions = true;
            }
        }

        if ($freshQuestions->isEmpty()) {
            return redirect()
                ->route('flashcards.show', $flashcardSet)
                ->with('error', 'No objective questions match this study card set scope.');
        }

        $queue = $freshQuestions->pluck('id')->values();
        $attached = 0;
        $updatedCards = 0;

        foreach ($cards as $card) {
            $ids = $queue->splice(0, $questionsPerCard)->all();
            if (empty($ids)) {
                break;
            }

            $before = $card->sourceQuestions()->count();
            $this->attachFlashcardQuestions($card, $ids);
            $after = $card->sourceQuestions()->count();

            if ($after > $before) {
                $updatedCards++;
                $attached += $after - $before;
            }
        }

        $message = $attached . ' question(s) linked to ' . $updatedCards . ' flashcard(s) based on scope.';
        if ($usedRepeatedQuestions) {
            $message .= ' Fresh scoped questions were exhausted, so existing scoped questions were reused.';
        }

        return redirect()
            ->route('flashcards.show', $flashcardSet)
            ->with($attached > 0 ? 'success' : 'error', $attached > 0 ? $message : 'No new questions could be linked.');
    }

    public function generateAiCards(Request $request, FlashcardSet $flashcardSet, FlashcardAiGeneratorService $generator)
    {
        SaasAccess::abortIfFeatureDisabled('flashcards');
        SaasAccess::abortIfFeatureDisabled('ai_flashcard_generation');
        $this->ensureTenantSet($flashcardSet);

        $flashcardSet->load(['package', 'subject', 'topic', 'stopic']);

        if (! $flashcardSet->package?->ai_flashcard_generation_enabled) {
            throw ValidationException::withMessages([
                'ai_flashcards' => 'AI study card generation is not enabled for this package.',
            ]);
        }

        $data = $request->validate([
            'source_text' => ['nullable', 'string', 'max:60000'],
            'source_urls' => ['nullable', 'string', 'max:10000'],
            'source_files' => ['nullable', 'array', 'max:5'],
            'source_files.*' => ['file', 'mimes:pdf,txt', 'max:10240'],
            'cards_per_source' => ['required', 'integer', 'min:3', 'max:30'],
            'publish_status' => ['nullable', 'in:draft,active'],
        ]);

        try {
            $sources = [];

            if (trim((string) ($data['source_text'] ?? '')) !== '') {
                $sources[] = $generator->sourceFromText((string) $data['source_text']);
            }

            foreach ($this->parseSourceUrls((string) ($data['source_urls'] ?? '')) as $url) {
                $sources[] = $generator->sourceFromUrl($url);
            }

            foreach ($request->file('source_files', []) as $file) {
                $sources[] = $generator->sourceFromUploadedFile($file);
            }

            if (empty($sources)) {
                throw ValidationException::withMessages([
                    'ai_flashcards' => 'Paste content, add at least one URL, or upload a PDF/TXT file.',
                ]);
            }

            $context = [
                'package' => $flashcardSet->package?->name ?? 'Package',
                'set' => $flashcardSet->title,
                'subject' => $flashcardSet->subject?->subject_name ?? 'General',
                'topic' => $flashcardSet->topic?->name ?? '',
                'subtopic' => $flashcardSet->stopic?->name ?? '',
            ];

            $created = 0;
            $status = ($data['publish_status'] ?? 'draft') === 'active';
            $nextOrder = (int) $flashcardSet->cards()->max('sort_order');
            $attachedQuestionIds = $flashcardSet->cards()
                ->whereNotNull('source_question_id')
                ->pluck('source_question_id')
                ->filter()
                ->map(fn ($id) => (int) $id)
                ->values();

            foreach ($sources as $source) {
                $cards = $generator->generate($source, $context, (int) $data['cards_per_source']);
                $questionPool = $this->questionScopeQuery($flashcardSet)
                    ->when($attachedQuestionIds->isNotEmpty(), fn ($query) => $query->whereNotIn('questions.id', $attachedQuestionIds))
                    ->inRandomOrder()
                    ->limit(count($cards))
                    ->get(['questions.*']);

                if ($questionPool->isEmpty()) {
                    throw ValidationException::withMessages([
                        'ai_flashcards' => 'No matching objective questions are available in this study card scope. Add questions first or widen the study card set scope.',
                    ]);
                }

                foreach ($cards as $index => $card) {
                    $question = $questionPool->get($index);

                    if (! $question) {
                        break;
                    }

                    $nextOrder++;
                    $createdCard = $flashcardSet->cards()->create($card + [
                        'source_label' => $source['label'] ?? null,
                        'source_url' => $source['url'] ?? null,
                        'source_question_id' => $question->id,
                        'ai_generated' => true,
                        'sort_order' => $nextOrder,
                        'status' => $status,
                    ]);
                    $this->syncFlashcardQuestions($createdCard, [(int) $question->id]);
                    $attachedQuestionIds->push((int) $question->id);
                    $created++;
                }
            }
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw ValidationException::withMessages([
                'ai_flashcards' => $e->getMessage(),
            ]);
        }

        $message = $status
            ? "Generated and published {$created} AI study cards."
            : "Generated {$created} AI study cards as drafts. Review and activate them before students see them.";

        return redirect()->route('flashcards.show', $flashcardSet)->with('success', $message);
    }

    public function bulkUpdateCards(Request $request, FlashcardSet $flashcardSet)
    {
        SaasAccess::abortIfFeatureDisabled('flashcards');
        $this->ensureTenantSet($flashcardSet);

        $data = $request->validate([
            'card_ids' => ['required', 'array'],
            'card_ids.*' => ['integer'],
            'bulk_action' => ['required', 'in:activate,deactivate'],
        ]);

        $status = $data['bulk_action'] === 'activate';
        $updated = $flashcardSet->cards()
            ->whereIn('id', $data['card_ids'])
            ->update(['status' => $status]);

        return redirect()
            ->route('flashcards.show', [$flashcardSet, 'per_page' => $this->perPage($request, 50)])
            ->with('success', $updated . ' flashcards updated successfully.');
    }

    public function updateCard(Request $request, FlashcardSet $flashcardSet, Flashcard $flashcard)
    {
        SaasAccess::abortIfFeatureDisabled('flashcards');
        $this->ensureTenantSet($flashcardSet);
        abort_if((int) $flashcard->flashcard_set_id !== (int) $flashcardSet->id, 404);

        $data = $this->validateCard($request);
        $manualChecks = $data['manual_checks'] ?? [];
        unset($data['manual_checks']);
        $questionIds = $request->boolean('remove_source_question')
            ? []
            : $this->questionIdsFromRequest($request);

        if ($request->boolean('remove_source_question')) {
            $data['source_question_id'] = null;
        }

        $flashcard->update($data);
        $this->syncFlashcardQuestions($flashcard, $questionIds);
        $this->syncManualChecks($flashcard, $manualChecks);

        return redirect()
            ->route('flashcards.show', [$flashcardSet, 'per_page' => $this->perPage($request, 50)])
            ->with('success', 'Study card updated successfully.');
    }

    public function destroyCard(Request $request, FlashcardSet $flashcardSet, Flashcard $flashcard)
    {
        SaasAccess::abortIfFeatureDisabled('flashcards');
        $this->ensureTenantSet($flashcardSet);
        abort_if((int) $flashcard->flashcard_set_id !== (int) $flashcardSet->id, 404);

        $flashcard->delete();

        return redirect()
            ->route('flashcards.show', [$flashcardSet, 'per_page' => $this->perPage($request, 50)])
            ->with('success', 'Study card removed successfully.');
    }

    private function cardSheetHeadings(): array
    {
        return [
            'title',
            'front',
            'back',
            'sort_order',
            'status',
            'linked_question_ids',
        ];
    }

    private function downloadCardRows(array $rows, string $baseName, string $format)
    {
        $format = strtolower($format) === 'xlsx' ? 'xlsx' : 'csv';
        $writerType = $format === 'xlsx' ? ExcelWriter::XLSX : ExcelWriter::CSV;

        return Excel::download(new RowsExport($rows), $baseName . '.' . $format, $writerType);
    }

    private function normaliseCardSheetHeading(mixed $heading): ?string
    {
        $key = strtolower(trim((string) $heading));
        $key = preg_replace('/[^a-z0-9]+/', '_', $key);
        $key = trim((string) $key, '_');

        return match ($key) {
            'title', 'card_title', 'heading', 'name' => 'title',
            'front', 'front_content', 'card_front', 'concept' => 'front',
            'back', 'back_content', 'card_back', 'answer' => 'back',
            'sort_order', 'order', 'display_order' => 'sort_order',
            'status', 'active' => 'status',
            'linked_question_ids', 'question_ids', 'source_question_ids' => 'linked_question_ids',
            default => null,
        };
    }

    private function sheetStatus(mixed $value): bool
    {
        $value = strtolower(trim((string) $value));

        if (in_array($value, ['0', 'false', 'inactive', 'draft', 'no'], true)) {
            return false;
        }

        return true;
    }

    private function questionIdsFromSheet(mixed $value): array
    {
        return collect(preg_split('/[,\s|]+/', (string) $value) ?: [])
            ->map(fn ($id) => trim($id))
            ->filter(fn ($id) => is_numeric($id) && (int) $id > 0)
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    private function validateSet(Request $request): array
    {
        $data = $request->validate([
            'package_id' => ['required', 'integer'],
            'group_id' => ['nullable', 'integer'],
            'category_level_1' => ['nullable', 'integer'],
            'category_level_2' => ['nullable', 'integer'],
            'subject_id' => ['nullable', 'integer'],
            'topic_id' => ['nullable', 'integer'],
            'stopic_id' => ['nullable', 'integer'],
            'title' => ['required', 'string', 'max:255'],
            'display_order' => ['nullable', 'integer', 'min:0'],
            'status' => ['nullable', 'boolean'],
            'meta_title' => ['nullable', 'string', 'max:191'],
            'canonical_url' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string'],
            'meta_keywords' => ['nullable', 'string'],
            'og_title' => ['nullable', 'string', 'max:191'],
            'og_image' => ['nullable', 'string', 'max:255'],
            'og_description' => ['nullable', 'string'],
            'robots_meta' => ['nullable', 'string', 'max:50'],
            'seo_schema' => ['nullable', 'string'],
        ]);

        if (! empty($data['subject_id']) && empty($data['group_id'])) {
            throw ValidationException::withMessages(['group_id' => 'Select a group before assigning curriculum.']);
        }
        if (! empty($data['group_id'])) {
            app(CurriculumTaxonomyService::class)->validateSelection(
                (int) $this->tenantId(), [(int) $data['group_id']],
                ! empty($data['subject_id']) ? (int) $data['subject_id'] : null,
                ! empty($data['topic_id']) ? (int) $data['topic_id'] : null,
                ! empty($data['stopic_id']) ? (int) $data['stopic_id'] : null,
            );
        }

        return $data;
    }

    private function validateCard(Request $request): array
    {
        $data = $request->validate([
            'title' => ['nullable', 'string', 'max:255'],
            'front' => ['required', 'string'],
            'back' => ['required', 'string'],
            'source_question_id' => ['nullable', 'integer'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'status' => ['nullable', 'boolean'],
            'manual_checks' => ['nullable', 'array'],
            'manual_checks.*.difficulty' => ['nullable', 'in:Easy,Medium,Hard'],
            'manual_checks.*.question' => ['nullable', 'string'],
            'manual_checks.*.options' => ['nullable', 'string'],
            'manual_checks.*.correct_answer' => ['nullable', 'string'],
            'manual_checks.*.explanation' => ['nullable', 'string'],
        ]);

        $data['title'] = trim((string) ($data['title'] ?? '')) ?: null;
        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);
        $data['status'] = $request->boolean('status', true);
        $data['card_type'] = 'basic';
        $data['options'] = null;
        $data['hint'] = null;
        $data['explanation'] = null;
        $data['difficulty'] = 'Medium';

        if (! empty($data['source_question_id'])) {
            $sourceQuestion = $this->baseObjectiveQuestionQuery()
                ->where('questions.id', (int) $data['source_question_id'])
                ->first();

            if (! $sourceQuestion) {
                throw ValidationException::withMessages([
                    'source_question_id' => 'Select a valid objective question from the question bank.',
                ]);
            }

            $questionCard = $this->questionToFlashcard($sourceQuestion, $data['sort_order'], $data['status']);

            if (! $questionCard) {
                throw ValidationException::withMessages([
                    'source_question_id' => 'The linked question could not be converted into a flashcard question.',
                ]);
            }

            $data['source_question_id'] = $sourceQuestion->id;
            $data['card_type'] = $questionCard['card_type'] ?? 'basic';
            $data['options'] = $questionCard['options'] ?? null;
            $data['hint'] = $questionCard['hint'] ?? null;
            $data['explanation'] = $questionCard['explanation'] ?? null;
            $data['difficulty'] = $questionCard['difficulty'] ?? 'Medium';
        } else {
            $data['source_question_id'] = null;
        }

        return $data;
    }

    private function questionIdsFromRequest(Request $request): array
    {
        return collect((array) $request->input('question_ids', []))
            ->push($request->input('source_question_id'))
            ->filter(fn ($id) => is_numeric($id) && (int) $id > 0)
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->reject(function ($id) use ($request) {
                return collect((array) $request->input('remove_question_ids', []))
                    ->map(fn ($removeId) => (int) $removeId)
                    ->contains((int) $id);
            })
            ->values()
            ->all();
    }

    private function validObjectiveQuestions(array $questionIds)
    {
        $questionIds = collect($questionIds)
            ->filter(fn ($id) => is_numeric($id) && (int) $id > 0)
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        if ($questionIds->isEmpty()) {
            return collect();
        }

        return $this->baseObjectiveQuestionQuery()
            ->whereIn('questions.id', $questionIds)
            ->get(['questions.*']);
    }

    private function attachFlashcardQuestions(Flashcard $flashcard, array $questionIds): void
    {
        $existingIds = $flashcard->sourceQuestions()
            ->pluck('questions.id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $newIds = $this->validObjectiveQuestions($questionIds)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->reject(fn ($id) => in_array($id, $existingIds, true))
            ->values();

        if ($newIds->isEmpty()) {
            return;
        }

        $nextOrder = (int) $flashcard->sourceQuestions()->max('flashcard_question_links.sort_order');
        $payload = $newIds->mapWithKeys(function ($id) use (&$nextOrder) {
            return [$id => ['sort_order' => ++$nextOrder]];
        })->all();

        $flashcard->sourceQuestions()->attach($payload);

        if (! $flashcard->source_question_id) {
            $flashcard->update(['source_question_id' => $flashcard->sourceQuestions()->value('questions.id')]);
        }
    }

    private function syncFlashcardQuestions(Flashcard $flashcard, array $questionIds): void
    {
        $validIds = $this->validObjectiveQuestions($questionIds)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values();

        $payload = $validIds->mapWithKeys(fn ($id, $index) => [$id => ['sort_order' => $index + 1]])->all();

        DB::transaction(function () use ($flashcard, $payload, $validIds) {
            $flashcard->sourceQuestions()->sync($payload);
            $flashcard->update(['source_question_id' => $validIds->first()]);
        });
    }

    private function syncManualChecks(Flashcard $flashcard, array $checks): void
    {
        $flashcard->checks()->whereNull('source_question_id')->delete();
        foreach (collect($checks)->filter(fn ($check) => filled($check['question'] ?? null))->values() as $index => $check) {
            $flashcard->checks()->create([
                'difficulty' => $check['difficulty'] ?? null,
                'question' => trim((string) $check['question']),
                'options' => collect(preg_split('/\R+/', (string) ($check['options'] ?? '')) ?: [])->map(fn ($value) => trim($value))->filter()->values()->all() ?: null,
                'correct_answer' => $check['correct_answer'] ?? null,
                'explanation' => $check['explanation'] ?? null,
                'sort_order' => $index + 1,
                'status' => true,
            ]);
        }
    }
    private function normalizeCardOptions(string $options, string $cardType): ?array
    {
        if (in_array($cardType, ['basic', 'fill_blank'], true)) {
            return null;
        }

        if ($cardType === 'true_false') {
            return ['True', 'False'];
        }

        $items = collect(preg_split('/\R+/', $options) ?: [])
            ->map(fn ($option) => trim($option))
            ->filter()
            ->unique()
            ->values()
            ->all();

        return empty($items) ? null : $items;
    }

    private function perPage(Request $request, int $default = 50): int
    {
        $perPage = (int) $request->input('per_page', $default);

        return in_array($perPage, [50, 100, 500], true) ? $perPage : $default;
    }

    private function questionBankData(Request $request, FlashcardSet $flashcardSet, ?Flashcard $currentCard = null): array
    {
        $tenantId = $this->tenantId();
        $questionPerPage = $this->perPage($request, 50);

        $currentQuestionIds = collect();

        if ($currentCard) {
            $currentQuestionIds = $currentCard->sourceQuestions()
                ->pluck('questions.id')
                ->push($currentCard->source_question_id)
                ->filter()
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values();
        }

        $legacyUsedQuestionIds = $flashcardSet->cards()
            ->whereNotNull('source_question_id')
            ->when($currentCard, fn ($query) => $query->where('id', '!=', $currentCard->id))
            ->pluck('source_question_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->values();

        $linkedUsedQuestionIds = DB::table('flashcard_question_links')
            ->join('flashcards', 'flashcards.id', '=', 'flashcard_question_links.flashcard_id')
            ->where('flashcards.flashcard_set_id', $flashcardSet->id)
            ->when($currentCard, fn ($query) => $query->where('flashcards.id', '!=', $currentCard->id))
            ->pluck('flashcard_question_links.question_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->values();

        $existingQuestionIds = $legacyUsedQuestionIds
            ->merge($linkedUsedQuestionIds)
            ->unique()
            ->values();

        $questionQuery = $this->baseObjectiveQuestionQuery()
            ->with([
                'subject:id,subject_name',
                'topic:id,name',
                'stopic:id,name',
                'qtype:id,type,question_type',
                'diff:id,diff_level',
                'groups:id,group_name',
                'exams:id,name',
            ])
            ->when($existingQuestionIds->isNotEmpty(), fn ($query) => $query->whereNotIn('questions.id', $existingQuestionIds));

        $this->applyQuestionBankFilters($questionQuery, $request, $flashcardSet);

        return [
            'questionPerPage' => $questionPerPage,
            'availableQuestions' => $questionQuery
                ->latest('questions.id')
                ->paginate($questionPerPage, ['questions.*'], 'question_page')
                ->withQueryString(),
            'linkedQuestionIds' => $currentQuestionIds,
            'filterData' => [
                'groups' => Group::where('organization_id', $tenantId)->orderBy('group_name')->get(['id', 'group_name']),
                'categories' => Category::where('organization_id', $tenantId)->whereNull('parent_id')->orderBy('title')->get(['id', 'title']),
                'subcategories' => Category::where('organization_id', $tenantId)->whereNotNull('parent_id')->orderBy('title')->get(['id', 'title', 'parent_id']),
                'packages' => Package::where('organization_id', $tenantId)->where('status', 1)->orderBy('name')->get(['id', 'name', 'category_level_1', 'category_level_2']),
                'exams' => Exam::where('organization_id', $tenantId)->orderBy('name')->get(['id', 'name', 'category_level_1', 'category_level_2']),
                'subjects' => Subject::whereHas('groups', fn ($query) => $query->where('groups.organization_id', $tenantId))->orderBy('subject_name')->get(['id', 'subject_name']),
                'topics' => Topic::whereHas('subject.groups', fn ($query) => $query->where('groups.organization_id', $tenantId))->orderBy('name')->get(['id', 'name', 'subject_id']),
                'stopics' => Stopic::whereHas('subject.groups', fn ($query) => $query->where('groups.organization_id', $tenantId))->orderBy('name')->get(['id', 'name', 'subject_id', 'topic_id']),
                'qtypes' => Qtype::displayOrdered(['id', 'question_type', 'type']),
                'diffs' => Diff::orderBy('diff_level')->get(['id', 'diff_level']),
                'languages' => Language::enabledForOrganization($tenantId)->orderBy('name')->get(['id', 'name', 'code']),
            ],
        ];
    }

    private function baseObjectiveQuestionQuery()
    {
        $tenantId = $this->tenantId();

        return Question::query()
            ->where('organization_id', $tenantId)
            ->where(function ($query) {
                $query->whereIn('status', ['Yes', 'yes', 'Active', 'active', '1', 1, true])
                    ->orWhere('status', 1)
                    ->orWhere('status', true)
                    ->orWhereNull('status');
            })
            ->whereHas('qtype', function ($query) {
                $query->whereIn('type', ['M', 'T', 'F', 'MCQ', 'TF', 'FB', 'multiple_choice', 'true_false', 'fill_blank'])
                    ->orWhere('question_type', 'like', '%Multiple%')
                    ->orWhere('question_type', 'like', '%True%')
                    ->orWhere('question_type', 'like', '%False%')
                    ->orWhere('question_type', 'like', '%Fill%');
            });
    }

    private function applyQuestionBankFilters($query, Request $request, FlashcardSet $flashcardSet): void
    {
        $groupId = $request->filled('group') ? $request->input('group') : $flashcardSet->group_id;
        $categoryId = $request->filled('category') ? $request->input('category') : $flashcardSet->category_level_1;
        $subcategoryId = $request->filled('subcategory') ? $request->input('subcategory') : $flashcardSet->category_level_2;
        $packageId = $request->filled('package') ? $request->input('package') : $flashcardSet->package_id;
        $subjectId = $request->filled('subject') ? $request->input('subject') : $flashcardSet->subject_id;
        $topicId = $request->filled('topic') ? $request->input('topic') : $flashcardSet->topic_id;
        $subtopicId = $request->filled('subtopic') ? $request->input('subtopic') : $flashcardSet->stopic_id;

        if ($groupId) {
            $query->where(function ($groupQuery) use ($groupId) {
                $groupQuery->whereHas('groups', fn ($questionGroupQuery) => $questionGroupQuery->where('groups.id', $groupId))
                    ->orWhereHas('exams.groups', fn ($examGroupQuery) => $examGroupQuery->where('groups.id', $groupId))
                    ->orWhereHas('exams.packages.groups', fn ($packageGroupQuery) => $packageGroupQuery->where('groups.id', $groupId));
            });
        }

        $query
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
            ->when($packageId, fn ($query) => $query->whereHas('exams.packages', fn ($item) => $item->where('packages.id', $packageId)))
            ->when($request->filled('exam'), fn ($query) => $query->whereHas('exams', fn ($item) => $item->where('exams.id', $request->input('exam'))))
            ->when($subjectId, fn ($query) => $query->where('subject_id', $subjectId))
            ->when($topicId, fn ($query) => $query->where('topic_id', $topicId))
            ->when($subtopicId, fn ($query) => $query->where('stopic_id', $subtopicId))
            ->when($request->filled('qtype'), fn ($query) => $query->where('qtype_id', $request->input('qtype')))
            ->when($request->filled('diff'), fn ($query) => $query->where('diff_id', $request->input('diff')))
            ->when($request->filled('question'), function ($query) use ($request) {
                $term = trim((string) $request->input('question'));
                $query->where(function ($searchQuery) use ($term) {
                    $searchQuery->where('question', 'like', '%' . $term . '%')
                        ->orWhereHas('subject', fn ($item) => $item->where('subject_name', 'like', '%' . $term . '%'))
                        ->orWhereHas('topic', fn ($item) => $item->where('name', 'like', '%' . $term . '%'))
                        ->orWhereHas('stopic', fn ($item) => $item->where('name', 'like', '%' . $term . '%'));

                    if (is_numeric($term)) {
                        $searchQuery->orWhere('questions.id', (int) $term);
                    }
                });
            });
    }

    private function parseSourceUrls(string $urls): array
    {
        return collect(preg_split('/\R+/', $urls) ?: [])
            ->map(fn ($url) => trim($url))
            ->filter()
            ->unique()
            ->map(function ($url) {
                $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

                if (! filter_var($url, FILTER_VALIDATE_URL) || ! in_array($scheme, ['http', 'https'], true)) {
                    throw ValidationException::withMessages([
                        'source_urls' => 'Invalid URL: ' . $url,
                    ]);
                }

                return $url;
            })
            ->values()
            ->all();
    }

    private function flashcardPackage(int $packageId): Package
    {
        $package = Package::query()
            ->where('organization_id', $this->tenantId())
            ->where('status', 1)
            ->find($packageId);

        if (! $package) {
            throw ValidationException::withMessages([
                'package_id' => 'Select a valid active package.',
            ]);
        }

        return $package;
    }

    private function questionScopeQuery(FlashcardSet $flashcardSet)
    {
        $tenantId = $this->tenantId();

        return Question::query()
            ->with(['qtype:id,type,question_type'])
            ->where('organization_id', $tenantId)
            ->where(function ($query) {
                $query->whereIn('status', ['Yes', 'yes', 'Active', 'active', '1', 1, true])
                    ->orWhere('status', 1)
                    ->orWhere('status', true)
                    ->orWhereNull('status');
            })
            ->whereHas('qtype', function ($query) {
                $query->whereIn('type', ['M', 'T', 'F', 'MCQ', 'TF', 'FB', 'multiple_choice', 'true_false', 'fill_blank'])
                    ->orWhere('question_type', 'like', '%Multiple%')
                    ->orWhere('question_type', 'like', '%True%')
                    ->orWhere('question_type', 'like', '%False%')
                    ->orWhere('question_type', 'like', '%Fill%');
            })
            ->when($flashcardSet->group_id, function ($query, $groupId) {
                $query->where(function ($groupQuery) use ($groupId) {
                    $groupQuery->whereHas('groups', fn ($item) => $item->where('groups.id', $groupId))
                        ->orWhereHas('exams.groups', fn ($item) => $item->where('groups.id', $groupId))
                        ->orWhereHas('exams.packages.groups', fn ($item) => $item->where('groups.id', $groupId));
                });
            })
            ->when($flashcardSet->category_level_1, function ($query, $categoryId) {
                $query->whereHas('exams', function ($examQuery) use ($categoryId) {
                    $examQuery->where('category_level_1', $categoryId)
                        ->orWhereHas('packages', fn ($packageQuery) => $packageQuery->where('category_level_1', $categoryId));
                });
            })
            ->when($flashcardSet->category_level_2, function ($query, $subcategoryId) {
                $query->whereHas('exams', function ($examQuery) use ($subcategoryId) {
                    $examQuery->where('category_level_2', $subcategoryId)
                        ->orWhereHas('packages', fn ($packageQuery) => $packageQuery->where('category_level_2', $subcategoryId));
                });
            })
            ->when($flashcardSet->subject_id, fn ($query, $subjectId) => $query->where('subject_id', $subjectId))
            ->when($flashcardSet->topic_id, fn ($query, $topicId) => $query->where('topic_id', $topicId))
            ->when($flashcardSet->stopic_id, fn ($query, $stopicId) => $query->where('stopic_id', $stopicId));
    }

    private function questionToFlashcard(Question $question, int $sortOrder, bool $status): ?array
    {
        $type = strtoupper(trim((string) $question->qtype?->type));
        $typeLabel = strtolower((string) $question->qtype?->question_type);
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
            'status' => $status,
        ];

        $isTrueFalse = $type === 'T'
            || $type === 'TF'
            || $type === 'TRUE_FALSE'
            || str_contains($typeLabel, 'true')
            || str_contains($typeLabel, 'false');

        if ($isTrueFalse) {
            $answer = trim((string) $question->true_false);

            return $answer === '' ? null : array_merge($payload, [
                'card_type' => 'true_false',
                'options' => ['True', 'False'],
                'back' => ucfirst(strtolower($answer)),
                'explanation' => $payload['explanation'] ?: 'Correct answer: ' . ucfirst(strtolower($answer)) . '.',
            ]);
        }

        $isFillBlank = $type === 'F'
            || $type === 'FB'
            || $type === 'FILL_BLANK'
            || str_contains($typeLabel, 'fill');

        if ($isFillBlank) {
            $answer = trim((string) $question->fill_blank);

            return $answer === '' ? null : array_merge($payload, [
                'card_type' => 'fill_blank',
                'options' => null,
                'back' => $answer,
                'explanation' => $payload['explanation'] ?: 'Correct answer: ' . $answer . '.',
            ]);
        }

        $isMcq = $type === 'M'
            || $type === 'MCQ'
            || $type === 'MULTIPLE_CHOICE'
            || str_contains($typeLabel, 'multiple')
            || str_contains($typeLabel, 'choice');

        if ($isMcq) {
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

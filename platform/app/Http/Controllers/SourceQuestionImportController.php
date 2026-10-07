<?php

namespace App\Http\Controllers;

use App\Models\Group;
use App\Models\Question;
use App\Models\SourceQuestionAdapterProfile;
use App\Models\SourceQuestionImportItem;
use App\Models\SourceQuestionImportRun;
use App\Services\SourceQuestionAdapterRegistry;
use App\Services\SourceQuestionAuditService;
use App\Services\SourceQuestionImportService;
use App\Support\Tenant;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SourceQuestionImportController extends Controller
{
    public function index(SourceQuestionAdapterRegistry $registry)
    {
        return view('source-question-import.index', [
            'runs' => SourceQuestionImportRun::where('organization_id', Tenant::id())->latest()->withCount('items')->limit(30)->get(),
            'adapters' => $registry->options(),
            'adapterProfiles' => SourceQuestionAdapterProfile::where('organization_id', Tenant::id())->orderBy('name')->get(),
            'groups' => Group::where('organization_id', Tenant::id())->orderBy('group_name')->get(['id', 'group_name']),
        ]);
    }

    public function store(Request $request, SourceQuestionImportService $service)
    {
        $data = $this->validateSourceInput($request);
        $options = ['default_group' => $data['default_group'] ?? null, 'adapter' => $data['adapter'] ?? 'auto'];
        $run = $request->file('source_file')
            ? $service->createRun($request->file('source_file'), $options)
            : $service->createRunFromUrls(preg_split('/\r?\n/', (string) ($data['source_urls'] ?? '')), $options);
        return redirect()->route('source-question-import.show', $run)->with('success', 'Source import queued. Previously imported URLs were skipped before extraction.');
    }

    public function auditStore(Request $request, SourceQuestionImportService $service)
    {
        $data = $this->validateSourceInput($request);
        $options = ['adapter' => $data['adapter'] ?? 'auto'];
        $run = $request->file('source_file')
            ? $service->createAuditRun($request->file('source_file'), $options)
            : $service->createAuditRunFromUrls(preg_split('/\r?\n/', (string) ($data['source_urls'] ?? '')), $options);
        return redirect()->route('source-question-import.show', $run)->with('success', 'Audit queued. It will compare existing questions only and cannot create questions.');
    }

    public function crawlStore(Request $request, SourceQuestionImportService $service, SourceQuestionAdapterRegistry $registry)
    {
        $tenantId = (int) Tenant::id();
        $adapterKeys = collect($registry->options($tenantId))->pluck('key')->reject(fn ($key) => $key === 'auto')->values()->all();
        $data = $request->validate([
            'base_url' => ['required', 'url:http,https', 'max:2048'],
            'adapter' => ['required', 'string', 'max:64', Rule::in($adapterKeys)],
            'question_pattern' => ['nullable', 'string', 'max:500'],
            'default_group_id' => ['required', 'integer', Rule::exists('groups', 'id')->where('organization_id', $tenantId)],
            'max_pages' => ['required', 'integer', 'min:1', 'max:500'],
            'max_questions' => ['required', 'integer', 'min:1', 'max:10000'],
        ]);
        if ($data['adapter'] !== 'examside' && trim((string) ($data['question_pattern'] ?? '')) === '') {
            return back()->withErrors(['question_pattern' => 'A question URL wildcard pattern is required unless the ExamSide adapter is selected.'])->withInput();
        }
        $group = Group::where('organization_id', $tenantId)->findOrFail($data['default_group_id']);
        $run = $service->createCrawlRun([
            'base_url' => $data['base_url'], 'adapter' => $data['adapter'],
            'question_pattern' => $data['question_pattern'] ?? '',
            'default_group' => $group->group_name, 'default_group_id' => $group->id,
            'max_pages' => $data['max_pages'], 'max_questions' => $data['max_questions'],
        ]);

        return redirect()->route('source-question-import.show', $run)->with('success', 'Base URL discovery queued. Existing question URLs will be skipped and new questions will be extracted in the background.');
    }

    public function show(SourceQuestionImportRun $run)
    {
        $this->authorizeRun($run);
        $run->load('items.question');
        $discovering = data_get($run->options, 'crawl') && ! data_get($run->options, 'crawl.discovery_complete', false);
        if (! $discovering && $run->items->every(fn ($item) => ! in_array($item->status, ['queued', 'processing'], true)) && $run->status !== 'completed') $run->update(['status' => 'completed']);
        return view('source-question-import.show', ['run' => $run]);
    }

    public function item(SourceQuestionImportItem $item)
    {
        $this->authorizeItem($item);
        return view('source-question-import.item', ['item' => $item->load('run', 'question')]);
    }

    public function stop(SourceQuestionImportRun $run)
    {
        $this->authorizeRun($run);
        $run->items()->whereIn('status', ['queued', 'processing'])->update(['status' => 'failed', 'error_message' => 'Stopped by administrator before completion.']);
        $run->update(['status' => 'failed', 'failure_message' => 'Stopped by administrator.']);
        return back()->with('success', ucfirst($run->mode ?: 'import').' processing stopped.');
    }

    public function retry(Request $request, SourceQuestionImportRun $run)
    {
        $this->authorizeRun($run);
        $ids = collect((array) $request->input('item_ids', []))->map(fn ($id) => (int) $id)->filter()->values();
        $allowed = ($run->mode ?: 'import') === 'audit' ? ['failed', 'audit_changes', 'audit_clean'] : ['failed', 'needs_review'];
        $items = $run->items()->whereIn('id', $ids)->whereIn('status', $allowed)->get();
        if ($items->isEmpty()) return back()->with('error', 'Select at least one retryable URL. Duplicate imports cannot be extracted again; use Audit instead.');
        foreach ($items as $item) {
            $item->update(['status' => 'queued', 'error_message' => null]);
            \App\Jobs\ProcessSourceQuestionImportItem::dispatch($item->id);
        }
        $run->update(['status' => 'processing']);
        return back()->with('success', $items->count().' URL(s) queued for retry.');
    }

    public function repair(Request $request, SourceQuestionImportItem $item, SourceQuestionAuditService $audits)
    {
        $this->authorizeItem($item);
        abort_unless(($item->run->mode ?: 'import') === 'audit', 404);
        if (! $item->question || ! is_array($item->payload)) return back()->with('error', 'This audit is not linked to an existing question.');
        $data = $request->validate(['fields' => ['required', 'array', 'min:1'], 'fields.*' => ['string'], 'confirm_overwrite' => ['nullable', 'boolean']]);
        $selected = array_values(array_intersect(SourceQuestionAuditService::FIELDS, $data['fields']));
        $result = $audits->apply($item->question, $item->payload, $selected, (bool) ($data['confirm_overwrite'] ?? false));
        $freshAudit = $audits->compare($item->question->fresh(), $item->payload);
        $item->update(['audit_result' => $freshAudit, 'status' => 'audited_repaired', 'repaired_at' => now()]);
        return back()->with($result === [] ? 'error' : 'success', $result === [] ? 'No values were changed. Enable overwrite confirmation for nonblank mismatches.' : 'Updated: '.implode(', ', $result).'.');
    }

    public function paperPreview(Request $request, SourceQuestionImportRun $run)
    {
        $this->authorizeRun($run);
        $items = $this->filteredItems($run, $request);
        $questions = $items->values()->map(function ($item, $index) {
            $payload = is_array($item->payload) ? $item->payload : [];
            $options = array_values((array) ($payload['options'] ?? []));
            return ['payload' => [
                'question' => (string) ($payload['question'] ?? $item->question?->question ?? ''),
                'option1' => $options[0] ?? $item->question?->option1, 'option2' => $options[1] ?? $item->question?->option2,
                'option3' => $options[2] ?? $item->question?->option3, 'option4' => $options[3] ?? $item->question?->option4,
                'option5' => $options[4] ?? $item->question?->option5, 'option6' => $options[5] ?? $item->question?->option6,
                'answer' => $payload['answer'] ?? $item->question?->answer, 'explanation' => $payload['explanation'] ?? $item->question?->explanation,
                'correct_option_indices' => $payload['correct_option_indices'] ?? $item->question?->correct_option_indices ?? [],
            ], 'label' => 'Question '.($index + 1).' · CSV row '.$item->row_number, 'type' => (string) ($payload['question_type'] ?? 'Question'), 'status' => $item->status, 'edit_url' => route('source-question-import.item', $item)];
        });
        return response()->view('question-drafts.paper-preview', [
            'questions' => $questions, 'title' => ucfirst($run->mode ?: 'import').' run #'.$run->id,
            'contextLabel' => ($run->mode ?: 'import') === 'audit' ? 'Audit extraction snapshot' : 'Complete extracted paper',
            'backUrl' => route('source-question-import.show', array_merge([$run], $request->query())),
            'paperEditUrl' => ($run->mode ?: 'import') === 'audit' ? null : route('source-question-import.paper.edit', array_merge([$run], $request->query())),
        ])->header('Cache-Control', 'private, no-store, max-age=0');
    }

    public function paperEdit(Request $request, SourceQuestionImportRun $run)
    {
        $this->authorizeRun($run);
        if (($run->mode ?: 'import') === 'audit') return redirect()->route('source-question-import.show', $run)->with('error', 'Audit runs cannot use the extraction editor.');
        return view('source-question-import.paper', ['run' => $run, 'items' => $this->filteredItems($run, $request), 'mode' => 'edit']);
    }

    public function updateItem(Request $request, SourceQuestionImportItem $item)
    {
        $this->authorizeItem($item);
        if (($item->run->mode ?: 'import') === 'audit') return back()->with('error', 'Audit payloads are read-only. Use field-level repair.');
        if (! is_array($item->payload) && ! $item->question_id) return back()->with('error', 'This extracted item has no editable payload.');
        $data = $request->validate(['question' => ['required', 'string'], 'option1' => ['nullable', 'string'], 'option2' => ['nullable', 'string'], 'option3' => ['nullable', 'string'], 'option4' => ['nullable', 'string'], 'option5' => ['nullable', 'string'], 'option6' => ['nullable', 'string'], 'answer' => ['nullable', 'string'], 'explanation' => ['nullable', 'string']]);
        $options = array_values(array_filter([$data['option1'] ?? null, $data['option2'] ?? null, $data['option3'] ?? null, $data['option4'] ?? null, $data['option5'] ?? null, $data['option6'] ?? null], fn ($value) => $value !== null && $value !== ''));
        $payload = is_array($item->payload) ? $item->payload : [];
        $payload = array_merge($payload, ['question' => $data['question'], 'options' => $options, 'answer' => $data['answer'] ?? null, 'explanation' => $data['explanation'] ?? null]);
        $item->update(['payload' => $payload, 'status' => $item->question_id ? 'duplicate' : 'needs_review', 'error_message' => null]);
        return back()->with('success', 'Extracted question draft saved.');
    }

    public function publish(Request $request, SourceQuestionImportItem $item)
    {
        $this->authorizeItem($item);
        if (($item->run->mode ?: 'import') === 'audit') return back()->with('error', 'Audit runs cannot create questions.');
        $data = $request->validate([
            'subject_id' => ['nullable', 'integer'], 'qtype_id' => ['nullable', 'integer'],
            'diff_id' => ['nullable', 'integer'], 'group_id' => ['nullable', 'integer'],
        ]);
        if (Question::where('organization_id', $item->run->organization_id)->where('source_url', $item->source_url)->exists()) return back()->with('error', 'This source URL is already attached to a question. Use Audit instead.');
        if ($item->status === 'duplicate' || ! is_array($item->payload) || empty($item->payload['question'])) return back()->with('error', 'This item is not publishable.');
        $payload = $item->payload;
        $assignment = (array) ($payload['assignment'] ?? []);
        $subjectId = (int) ($data['subject_id'] ?? data_get($assignment, 'subject.id', 0));
        $qtypeId = (int) ($data['qtype_id'] ?? data_get($assignment, 'question_type.id', 0));
        if ($qtypeId <= 0) return back()->with('error', 'Question type could not be resolved. Correct the adapter/CSV value and retry extraction.');
        $question = Question::create([
            'organization_id' => $item->run->organization_id,
            'source_url' => $item->source_url,
            'source_reference' => $payload['question_source_reference'] ?? $item->source_url,
            'subject_id' => $subjectId ?: null,
            'qtype_id' => $qtypeId,
            'question_section_id' => data_get($assignment, 'section.id'),
            'topic_id' => data_get($assignment, 'topic.id'),
            'stopic_id' => data_get($assignment, 'subtopic.id'),
            'diff_id' => $data['diff_id'] ?? data_get($assignment, 'difficulty_level.id'),
            'language_id' => data_get($assignment, 'language.id'),
            'question' => $payload['question'],
            'option1' => $payload['options'][0] ?? null, 'option2' => $payload['options'][1] ?? null,
            'option3' => $payload['options'][2] ?? null, 'option4' => $payload['options'][3] ?? null,
            'option5' => $payload['options'][4] ?? null, 'option6' => $payload['options'][5] ?? null,
            'marks' => $payload['marks'] ?? null, 'negative_marks' => $payload['negative_marks'] ?? null,
            'scoring_policy' => $payload['scoring_policy'] ?? 'NORMAL', 'hint' => $payload['hint'] ?? null,
            'answer' => $payload['answer'] ?? null, 'explanation' => $payload['explanation'] ?? null,
            'true_false' => $payload['true_false'] ?? null, 'fill_blank' => $payload['fill_blank'] ?? null,
            'si_answer1' => $payload['si_answer1'] ?? null,
            'correct_option_indices' => $payload['correct_option_indices'] ?? [],
            'status' => $payload['status'] ?? 'Yes',
        ]);
        $groupIds = ! empty($data['group_id']) ? [(int) $data['group_id']] : (array) data_get($assignment, 'group.ids', []);
        if ($groupIds !== []) $question->groups()->sync($groupIds);
        $examIds = (array) data_get($assignment, 'exam.ids', []);
        if ($examIds !== []) $question->exams()->sync($examIds);
        $item->update(['status' => 'published', 'question_id' => $question->id, 'published_at' => now()]);
        $item->run()->increment('published');
        return back()->with('success', 'Question published.');
    }

    public function status(SourceQuestionImportRun $run)
    {
        $this->authorizeRun($run);
        return response()->json($run->fresh()->load('items:id,run_id,row_number,source_url,adapter,status,error_message,payload,audit_result,question_id'));
    }

    public function destroy(SourceQuestionImportRun $run)
    {
        $this->authorizeRun($run);
        if ($run->status === 'processing') return back()->with('error', 'This run is currently processing and cannot be removed.');
        $this->deleteRunData($run);
        return redirect()->route('source-question-import.index')->with('success', ucfirst($run->mode ?: 'import').' run removed.');
    }

    public function destroyMany(Request $request)
    {
        $ids = collect((array) $request->input('run_ids', []))->map(fn ($id) => (int) $id)->filter()->values();
        $runs = SourceQuestionImportRun::where('organization_id', Tenant::id())->whereIn('id', $ids)->get(); $blocked = 0; $deleted = 0;
        foreach ($runs as $run) { if (in_array($run->status, ['queued', 'processing'], true)) { $blocked++; continue; } $this->deleteRunData($run); $deleted++; }
        return redirect()->route('source-question-import.index')->with('success', $deleted.' run(s) deleted'.($blocked ? '; '.$blocked.' active run(s) skipped.' : '.'));
    }

    private function filteredItems(SourceQuestionImportRun $run, Request $request)
    {
        $run->loadMissing('items.question'); $items = $run->items;
        $meta = fn ($item, $key) => trim((string) (($item->payload['metadata'][$key] ?? $item->metadata[$key] ?? '') ?: ''));
        foreach (['group', 'exam', 'category', 'subcategory', 'package', 'subject'] as $filter) { $value = trim((string) $request->query($filter)); if ($value !== '') $items = $items->filter(fn ($item) => strcasecmp($meta($item, $filter), $value) === 0); }
        if ($request->query('status')) $items = $items->where('status', $request->query('status'));
        if ((int) $request->query('focus') > 0) $items = $items->where('id', (int) $request->query('focus'));
        return $items->values();
    }

    private function validateSourceInput(Request $request): array
    {
        $data = $request->validate(['source_file' => ['nullable', 'file', 'mimes:csv,txt', 'max:512000', 'required_without:source_urls'], 'source_urls' => ['nullable', 'string', 'max:1000000', 'required_without:source_file'], 'default_group' => ['nullable', 'string', 'max:191'], 'adapter' => ['nullable', 'string', 'max:64']]);
        return $data;
    }

    private function authorizeRun(SourceQuestionImportRun $run): void { abort_unless((int) $run->organization_id === (int) Tenant::id(), 404); }
    private function authorizeItem(SourceQuestionImportItem $item): void { abort_unless((int) $item->run->organization_id === (int) Tenant::id(), 404); }
    private function deleteRunData(SourceQuestionImportRun $run): void
    {
        $run->loadMissing('items');
        if (($run->mode ?: 'import') === 'import') {
            $createdIds = $run->items->filter(fn ($item) => $item->question_id && $item->published_at)->pluck('question_id')->unique();
            foreach ($createdIds as $questionId) { $question = Question::where('organization_id', $run->organization_id)->find($questionId); if (! $question) continue; $question->exams()->detach(); $question->groups()->detach(); $question->tags()->detach(); $question->taxonomies()->delete(); $question->delete(); }
        }
        $run->items()->delete(); $run->delete();
    }
}

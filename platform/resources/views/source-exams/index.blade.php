@extends('layouts.master')
@section('title','Create Exam from Source')
@section('content')
<style>
.source-extractor-error-popup { width: min(34rem, calc(100vw - 2rem)) !important; }
.source-extractor-error-popup .swal2-html-container {
    max-height: 18rem;
    overflow-y: auto;
    text-align: left;
    white-space: pre-wrap;
    overflow-wrap: anywhere;
    font-size: .9rem;
    line-height: 1.45;
}
</style>
<div class="container-fluid">
    <div class="mb-4">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3"><div><h3 class="mb-1">Create Exam from Source</h3><p class="text-muted mb-0">Build reviewable exam drafts from PDF or DOCX, publish them, then audit one or many papers.</p></div><div class="d-flex gap-2"><a href="{{ route('source-exams.discover') }}" class="btn btn-outline-primary"><i class="ri-links-line me-1"></i>Discover PDFs</a><a href="{{ route('source-exams.create') }}" class="btn btn-primary"><i class="ri-add-line me-1"></i>New source exam</a></div></div>
        <div class="card mt-3 mb-0"><div class="card-body"><div class="row g-3 align-items-start"><div class="col-xl-8">
            <div style="min-width:290px">
                <label for="bulk-source-extractor" class="form-label small mb-1">Extractor for selected drafts</label>
                <select class="form-select" id="bulk-source-extractor">
                    @foreach($extractors as $script => $label)
                        <option value="{{ $script }}" @selected($script === \App\Support\SourceExtractorRegistry::BUILTIN)>{{ $label }} - {{ $script }}</option>
                    @endforeach
                </select>
                <div class="small text-muted mt-1">The selected script alone controls extraction and any API calls. Four independent workers start immediately.</div>
            </div>
            </div><div class="col-xl-4"><div class="border rounded bg-light p-3 h-100"><div class="fw-semibold mb-1"><i class="ri-checkbox-multiple-line me-1"></i>Selected papers</div><div class="text-muted" id="source-selection-summary">Select papers in the table below to process, publish, or audit them.</div></div></div></div><hr class="my-3"><div class="d-flex flex-wrap align-items-center justify-content-between gap-2"><div class="small text-muted">Actions apply only to selected papers that are eligible for that action.</div><div class="d-flex flex-wrap gap-2 justify-content-end">
            <button type="button" class="btn btn-outline-primary source-bulk-action" id="process-selected-papers" data-kind="process" data-action-url="{{ route('source-exams.process-selected') }}"><i class="ri-play-list-add-line me-1"></i>Start extraction</button>
            <button type="button" class="btn btn-success source-bulk-action" id="publish-selected-papers" data-kind="publish" data-action-url="{{ route('source-exams.publish-selected') }}"><i class="ri-check-double-line me-1"></i>Publish papers</button>
            <button type="button" class="btn btn-primary source-bulk-action" id="audit-selected-papers" data-kind="audit" data-action-url="{{ route('source-exams.audit-selected') }}"><i class="ri-shield-check-line me-1"></i>Audit papers</button>
        </div></div></div></div>
    </div>
    @include('exams.partials.pdf-publication')
    <form method="POST" action="{{ route('source-exams.publish-selected') }}" id="source-paper-bulk-form">@csrf
    <div class="card"><div class="card-body p-0"><div class="table-responsive"><table class="table align-middle mb-0">
        <thead class="table-light"><tr><th style="width:48px"><input class="form-check-input" type="checkbox" id="select-all-source-papers" aria-label="Select all available papers"></th><th>Exam draft</th><th>Sources</th><th style="min-width:280px">Python extractor</th><th>Status</th><th>Questions</th><th>Created</th><th></th></tr></thead><tbody>
        @forelse($imports as $import)<tr>
            <td>@if(in_array($import->status, ['draft', 'review', 'failed', 'published'], true))<input class="form-check-input source-paper-checkbox" type="checkbox" name="import_ids[]" value="{{ $import->id }}" data-status="{{ $import->status }}" data-auditable="{{ $import->exam?->questions_exists ? '1' : '0' }}" data-pending-review="{{ (int) $import->review_questions }}" aria-label="Select {{ $import->name }}">@endif</td>
            <td>@if($import->exam && user_can_route_action('exams.edit', 'edit'))<label class="d-block small"><input type="checkbox" class="form-check-input" form="pdf-publication" name="exam_ids[]" value="{{ $import->exam_id }}"> Publish PDF</label>@endif<strong>{{ $import->name }}</strong>@if($import->exam && $import->status === 'published')<div class="small text-success">Published as {{ $import->exam->name }}</div>@elseif($import->exam)<div class="small text-muted">Target exam: {{ $import->exam->name }} <span class="{{ $import->exam->status === 'Active' ? 'text-success' : 'text-warning' }}">{{ $import->exam->status === 'Active' ? '(PDF published; extraction separate)' : '(not published)' }}</span></div>@endif</td>
            <td><div>{{ $import->question_source_name }}</div>@if($import->answer_source_name)<div class="small text-muted">Answer: {{ $import->answer_source_name }}</div>@endif @if($import->solution_source_name)<div class="small text-muted">Solution: {{ $import->solution_source_name }}</div>@endif</td>
            <td>
                @if(in_array($import->status, ['draft', 'failed'], true))
                    <select class="form-select form-select-sm source-extractor-select" name="extractors[{{ $import->id }}]">
                        @foreach($extractors as $script => $label)
                            <option value="{{ $script }}" @selected(data_get($import->settings, 'extractor_script', \App\Support\SourceExtractorRegistry::BUILTIN) === $script)>{{ $label }} - {{ $script }}</option>
                        @endforeach
                    </select>
                    <div class="small text-muted mt-1">This exact script will run for this paper.</div>
                @else
                    <span class="small">{{ $extractors[data_get($import->settings, 'extractor_script', \App\Support\SourceExtractorRegistry::BUILTIN)] ?? data_get($import->settings, 'extractor_script', \App\Support\SourceExtractorRegistry::BUILTIN) }}</span>
                @endif
            </td>
            <td><span class="badge bg-{{ $import->status==='published'?'success':($import->status==='failed'?'danger':($import->status==='review'?'primary':'warning')) }}">{{ ucfirst($import->status) }}</span>@if($import->failure_message)<button type="button" class="btn btn-link btn-sm text-danger p-0 mt-1 source-error-details" data-import-id="{{ $import->id }}">View full error</button>@endif</td>
            <td>{{ $import->detected_questions }}@if($import->review_questions)<div class="small text-warning">{{ $import->review_questions }} need review</div>@endif</td>
            <td>{{ $import->created_at->format('d M Y, h:i A') }}</td><td class="text-end"><div class="d-flex gap-2 justify-content-end">
                @if($import->status === 'review')
                    <button type="button" class="btn btn-sm btn-success source-row-publish" data-import-id="{{ $import->id }}"><i class="ri-check-double-line me-1"></i>Publish</button>
                @endif
                @if($import->exam?->questions_exists)
                    <a href="{{ route('exam-quality.index', ['exam_id' => $import->exam->id]) }}" class="btn btn-sm btn-primary"><i class="ri-shield-check-line me-1"></i>Audit</a>
                @elseif($import->exam)
                    <button type="button" class="btn btn-sm btn-outline-secondary" disabled title="Publish the extracted questions before auditing">Publish first</button>
                @endif
                <a href="{{ route('source-exams.show',$import) }}" class="btn btn-sm btn-outline-primary">Open</a>
            </div></td>
        </tr>@empty<tr><td colspan="8" class="text-center py-5 text-muted">No source exam drafts yet.</td></tr>@endforelse
        </tbody></table></div></div></div></form>{{ $imports->links() }}
</div>
@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const master = document.getElementById('select-all-source-papers');
    const boxes = Array.from(document.querySelectorAll('.source-paper-checkbox'));
    const publishButton = document.getElementById('publish-selected-papers');
    const processButton = document.getElementById('process-selected-papers');
    const auditButton = document.getElementById('audit-selected-papers');
    const selectionSummary = document.getElementById('source-selection-summary');
    const bulkExtractor = document.getElementById('bulk-source-extractor');
    const bulkForm = document.getElementById('source-paper-bulk-form');
    const failureMessages = @json($imports->getCollection()->filter(fn ($import) => filled($import->failure_message))->mapWithKeys(fn ($import) => [(string) $import->id => $import->failure_message]));
    const actionCopy = {
        process: {
            empty: 'Select at least one Draft or Failed paper.', title: 'Start selected extractions?',
            text: 'The exact selected extractor will start immediately for each eligible paper, with up to four papers running independently.'
        },
        publish: {
            empty: 'Select at least one paper with Review status.', title: 'Publish selected papers?',
            text: 'Needs review is advisory. Selected review papers will be published by the administrator.'
        },
        audit: {
            empty: 'Select at least one published paper containing questions.', title: 'Open bulk audit setup?',
            text: 'The selected published papers will be preselected on the audit screen.'
        }
    };
    const showModal = options => window.Swal ? Swal.fire(Object.assign({ confirmButtonColor: '#087f73', cancelButtonColor: '#6c757d' }, options)) : Promise.resolve({ isConfirmed: false });
    const serverWarning = @json(session('error') ?: session('warning'));
    if (serverWarning) showModal({

        icon: @json(session('error') ? 'error' : 'warning'), title: @json(session('error') ? 'Action failed' : 'Action warning'),

        text: serverWarning, customClass: { popup: 'source-extractor-error-popup' }

    });
    if (!serverWarning) {
        const latestFailure = Object.entries(failureMessages)[0];
        if (latestFailure) {
            const failureKey = `source-extractor-failure:${latestFailure[0]}:${String(latestFailure[1]).slice(0, 80)}`;
            if (sessionStorage.getItem(failureKey) !== 'shown') {
                sessionStorage.setItem(failureKey, 'shown');
                showModal({ icon: 'error', title: 'Extractor failed', text: latestFailure[1], customClass: { popup: 'source-extractor-error-popup' } });
            }
        }
    }
    function sync() {
        const count = boxes.filter(box => box.checked).length;
        const publishCount = boxes.filter(box => box.checked && box.dataset.status === 'review').length;
        const processCount = boxes.filter(box => box.checked && ['draft', 'failed'].includes(box.dataset.status)).length;
        const auditCount = boxes.filter(box => box.checked && box.dataset.auditable === '1').length;
        if (publishButton) {
            publishButton.innerHTML = '<i class="ri-check-double-line me-1"></i>Publish papers' + (publishCount ? ' (' + publishCount + ')' : '');
        }
        if (processButton) {
            processButton.innerHTML = '<i class="ri-play-list-add-line me-1"></i>Start extraction' + (processCount ? ' (' + processCount + ')' : '');
        }
        if (auditButton) {
            auditButton.innerHTML = '<i class="ri-shield-check-line me-1"></i>Audit papers' + (auditCount ? ' (' + auditCount + ')' : '');
        }
        if (selectionSummary) {
            selectionSummary.textContent = count ? count + ' selected: ' + processCount + ' processable, ' + publishCount + ' publishable, ' + auditCount + ' auditable.' : 'Select papers in the table below to process, publish, or audit them.';
        }
        if (master) {
            master.checked = boxes.length > 0 && boxes.every(box => box.checked);
            master.indeterminate = count > 0 && count < boxes.length;
        }
    }
    master?.addEventListener('change', () => { boxes.forEach(box => box.checked = master.checked); sync(); });
    boxes.forEach(box => box.addEventListener('change', sync));
    const eligibleBoxes = kind => boxes.filter(box => box.checked && (
        kind === 'process' ? ['draft', 'failed'].includes(box.dataset.status) :
        kind === 'publish' ? box.dataset.status === 'review' :
        box.dataset.auditable === '1'
    ));
    document.querySelectorAll('.source-bulk-action').forEach(button => button.addEventListener('click', async function () {
        const kind = this.dataset.kind;
        const eligible = eligibleBoxes(kind);
        if (eligible.length === 0) {
            await showModal({ icon: 'warning', title: 'Select an eligible paper', text: actionCopy[kind].empty });
            return;
        }
        const result = await showModal({
            icon: kind === 'publish' ? 'warning' : 'question',
            title: actionCopy[kind].title,
            text: actionCopy[kind].text,
            showCancelButton: true,
            confirmButtonText: kind === 'publish' ? 'Yes, publish' : 'Continue'
        });
        if (!result.isConfirmed || !bulkForm) return;
        bulkForm.action = this.dataset.actionUrl;
        bulkForm.submit();
    }));
    document.querySelectorAll('.source-row-publish').forEach(button => button.addEventListener('click', function () {
        const box = boxes.find(item => item.value === this.dataset.importId);
        boxes.forEach(item => item.checked = item === box);
        sync();
        publishButton?.click();
    }));
    document.querySelectorAll('.source-error-details').forEach(button => button.addEventListener('click', function () {
        showModal({ icon: 'error', title: 'Extractor failed', text: failureMessages[this.dataset.importId] || 'No error details were recorded.', customClass: { popup: 'source-extractor-error-popup' } });
    }));
    bulkExtractor?.addEventListener('change', function () {
        boxes
            .filter(box => box.checked && ['draft', 'failed'].includes(box.dataset.status))
            .forEach(box => {
                const extractor = box.closest('tr')?.querySelector('.source-extractor-select');
                if (extractor) extractor.value = this.value;
            });
    });
    sync();
    @if($imports->contains(fn ($import) => in_array($import->status, ['queued', 'starting', 'processing'], true)))
    setTimeout(() => window.location.reload(), 5000);
    @endif
});
</script>
@endpush
@endsection

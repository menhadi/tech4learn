@extends('layouts.master')

@section('title', 'Edit Full Paper')

@php
    $coreLabels = [
        'question' => 'Question text / formula / image',
        'option1' => 'Option A', 'option2' => 'Option B', 'option3' => 'Option C',
        'option4' => 'Option D', 'option5' => 'Option E', 'option6' => 'Option F',
    ];
    $reviewLabels = [
        'answer' => 'Answer', 'true_false' => 'True / False answer',
        'fill_blank' => 'Fill-blank answer', 'correct_option_indices' => 'Correct option numbers (JSON)',
        'si_answer1' => 'Subjective answer', 'hint' => 'Hint', 'explanation' => 'Explanation',
    ];
    $firstItem = $items->first();
@endphp

@section('content')
@component('components.breadcrumb')
    @slot('li_1', 'Exams')
    @slot('title', 'Edit Full Paper')
@endcomponent

@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif

<div class="card mb-3">
    <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div>
            <h3 class="mb-1">{{ $exam->name }}</h3>
            <div class="text-muted">All {{ $items->count() }} questions are editable here. Saves remain drafts; no AI call is made and nothing changes live until publication.</div>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a class="btn btn-outline-secondary" href="{{ route('exams.viewQuestions', $exam) }}"><i class="ri-arrow-left-line me-1"></i>Back to questions</a>
            <a class="btn btn-outline-primary" href="{{ route('exams.paper.preview', $exam) }}" target="_blank"><i class="ri-file-paper-2-line me-1"></i>Clean paper preview</a>
            <form method="POST" action="{{ route('exams.paper.publish', $exam) }}" data-swal-confirm="Publish every saved draft to the live paper? Restorable versions will be created.">@csrf @method('PATCH')<button class="btn btn-success"><i class="ri-check-double-line me-1"></i>Publish all saved drafts</button></form>
        </div>
    </div>
</div>
<div class="alert alert-info py-2"><strong>Saving behavior:</strong> image crops save to the selected question/option draft immediately. The per-question save button is only for text or formula edits you type manually.</div>

@if($items->isEmpty())
    <div class="alert alert-info">This exam has no questions to edit.</div>
@else
<div class="row g-3 align-items-start full-paper-editor">
    <div class="col-xl-6">
        <div class="full-paper-source">
            @if($hasPdfSource && $firstItem)
                @include('question-drafts.manual-cropper', [
                    'cropAction' => route('exams.paper.questions.crop', [$exam, $firstItem['draft']]),
                    'cropPageUrl' => route('exams.paper.questions.source-page', [$exam, $firstItem['draft']]),
                    'cropEvidence' => (array) $firstItem['draft']->evidence,
                    'cropPayload' => $firstItem['payload'],
                    'cropDefaultPage' => $firstItem['crop_page'],
                    'cropHasSourceRoles' => true,
                    'cropOptionCount' => 6,
                    'cropContinuous' => true,
                    'cropExternalTargets' => true,
                ])
            @else
                <div class="alert alert-info"><strong>No attached source PDF.</strong> Add a question or combined PDF in Edit Exam to enable source-page cropping. Text editing remains available.</div>
            @endif
        </div>
    </div>

    <div class="col-xl-6 full-paper-question-pane">
        <div class="card mb-3">
            <div class="card-body">
                <div class="row g-2">
                    <div class="col-md-8"><label class="form-label">Find a question</label><input type="search" class="form-control" id="paper-question-search" placeholder="Type paper number, question ID, code or text"></div>
                    <div class="col-md-4 d-flex align-items-end"><div class="form-check form-switch mb-2"><input class="form-check-input" type="checkbox" id="paper-changed-only"><label class="form-check-label" for="paper-changed-only">Show changed drafts only</label></div></div>
                </div>
                <div class="small text-muted mt-2"><span id="paper-visible-count">{{ $items->count() }}</span> questions shown</div>
            </div>
        </div>

        <div id="paper-question-list">
        @foreach($items as $item)
            @php
                $draft = $item['draft'];
                $original = $item['original'];
                $payload = $item['payload'];
                $changedFields = (array) $draft->changed_fields;
                $searchText = Str::lower($item['label'].' '.$item['type'].' '.$draft->question?->question_code.' '.strip_tags((string) ($payload['question'] ?? '')));
            @endphp
            <article class="card paper-question-editor" id="paper-question-{{ $draft->id }}" data-search="{{ e($searchText) }}" data-changed="{{ empty($changedFields) ? '0' : '1' }}">
                <div class="card-header d-flex flex-wrap justify-content-between align-items-start gap-2">
                    <div><h4 class="mb-1">{{ $item['label'] }}</h4><div class="text-muted small">{{ $draft->question?->question_code }} &middot; {{ $item['type'] }} &middot; detected PDF page {{ $item['crop_page'] }}</div></div>
                    <span class="badge {{ empty($changedFields) ? 'bg-light text-muted border' : 'bg-warning-subtle text-warning' }} paper-draft-badge">{{ empty($changedFields) ? 'No draft changes' : count($changedFields).' draft changes' }}</span>
                </div>
                <form method="POST" action="{{ route('exams.paper.questions.update', [$exam, $draft]) }}" class="paper-question-form" data-draft-id="{{ $draft->id }}" data-disable-ai-inline>
                    @csrf @method('PATCH')
                    <div class="card-body">
                        @foreach($coreLabels as $field => $label)
                            @include('exams.partials.paper-editor-field', compact('exam', 'draft', 'field', 'label', 'original', 'payload', 'hasPdfSource', 'item'))
                        @endforeach
                        <details class="border rounded p-3 mt-3">
                            <summary class="fw-semibold text-primary" style="cursor:pointer">Answer key, hint and explanation</summary>
                            <div class="pt-3">
                                @foreach($reviewLabels as $field => $label)
                                    @include('exams.partials.paper-editor-field', compact('exam', 'draft', 'field', 'label', 'original', 'payload', 'hasPdfSource', 'item'))
                                @endforeach
                            </div>
                        </details>
                    </div>
                    <div class="card-footer d-flex flex-wrap justify-content-between align-items-center gap-2">
                        <span class="small text-muted paper-save-status">Crops auto-save immediately; save is only needed for typed edits.</span>
                        <div class="d-flex gap-2"><a class="btn btn-sm btn-outline-secondary" href="{{ route('exams.paper.questions.edit', [$exam, $draft]) }}">Open individual editor</a><button class="btn btn-sm btn-primary paper-save-button"><i class="ri-save-line me-1"></i>Save typed edits now</button></div>
                    </div>
                </form>
            </article>
        @endforeach
        </div>
    </div>
</div>
@endif

<style>
.paper-question-editor{scroll-margin-top:12px}.paper-field-preview img,.paper-live-value img{display:block;max-width:100%;height:auto;object-fit:contain;margin:10px auto}.paper-field-preview{min-height:48px;max-height:240px;overflow:auto}.paper-live-value{max-height:220px;overflow:auto}.paper-question-editor.is-saving{opacity:.72;pointer-events:none}.crop-field-trigger.active-crop-target{background:#7c3aed!important;border-color:#7c3aed!important;color:#fff!important;box-shadow:0 0 0 .2rem rgba(124,58,237,.2)}
@media (min-width:1200px){.full-paper-editor{overflow:hidden}.full-paper-editor>div{height:100%}.full-paper-question-pane{overflow-y:auto;padding-right:.65rem}.full-paper-source{height:100%;overflow-y:auto;padding-right:.65rem}.full-paper-source #manual-pdf-cropper{height:auto;margin-bottom:0!important;display:block}.full-paper-source #manual-pdf-cropper>.card-body,.full-paper-source #manual-crop-form{overflow:visible;display:block}.full-paper-source #source-crop-stage{height:clamp(520px,68vh,850px)!important;min-height:520px!important}}
</style>
@endsection

@section('script')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const workspace = document.querySelector('.full-paper-editor');
    const fitWorkspace = () => { if (!workspace || window.innerWidth < 1200) { if (workspace) workspace.style.height = ''; return; } workspace.style.height = Math.max(520, window.innerHeight - workspace.getBoundingClientRect().top - 14) + 'px'; };
    fitWorkspace(); window.addEventListener('resize', fitWorkspace);
    const render = textarea => {
        const preview = document.getElementById(textarea.dataset.paperPreview || '');
        if (!preview) return;
        preview.innerHTML = textarea.value || '<span class="text-muted">Empty</span>';
        if (window.MathJax?.typesetPromise) window.MathJax.typesetPromise([preview]).catch(() => {});
    };
    document.querySelectorAll('[data-paper-preview]').forEach(textarea => textarea.addEventListener('input', () => render(textarea)));
    document.querySelectorAll('[data-crop-into]').forEach(button => button.addEventListener('click', () => {
        document.querySelectorAll('[data-crop-into]').forEach(candidate => { candidate.classList.toggle('active-crop-target', candidate === button); candidate.setAttribute('aria-pressed', candidate === button ? 'true' : 'false'); });
        document.dispatchEvent(new CustomEvent('manual-crop-target', {detail:{
            target:button.dataset.cropInto, cropAction:button.dataset.cropAction,
            pageUrl:button.dataset.cropPageUrl, page:button.dataset.cropPage,
            editorSelector:button.dataset.editorSelector,
        }}));
    }));
    document.querySelectorAll('[data-remove-image]').forEach(button => button.addEventListener('click', async () => {
        const editor = document.querySelector(button.dataset.editorSelector), card = editor?.closest('.paper-question-editor'), status = card?.querySelector('.paper-save-status');
        if (!editor || !card || !status) return;
        button.disabled = true; status.classList.remove('text-danger'); status.textContent = 'Removing image and saving draft...';
        const body = new FormData(); body.append('_token', document.querySelector('meta[name="csrf-token"]')?.content || ''); body.append('_method', 'PATCH'); body.append('field', button.dataset.removeField);
        try {
            const response = await fetch(button.dataset.removeUrl, {method:'POST', body, headers:{'Accept':'application/json','X-Requested-With':'XMLHttpRequest'}, credentials:'same-origin'});
            const result = await response.json().catch(() => ({}));
            if (!response.ok) throw new Error(result.message || Object.values(result.errors || {})[0]?.[0] || 'Image could not be removed.');
            editor.value = result.html || ''; editor.dispatchEvent(new Event('input', {bubbles:true})); button.classList.add('d-none');
            const changed = result.changed_fields || [], badge = card.querySelector('.paper-draft-badge'); card.dataset.changed = changed.length ? '1' : '0';
            badge.className = 'badge paper-draft-badge ' + (changed.length ? 'bg-warning-subtle text-warning' : 'bg-light text-muted border'); badge.textContent = changed.length ? changed.length + ' draft changes' : 'No draft changes'; status.textContent = result.message || 'Image removed and draft saved.';
        } catch (error) { status.textContent = error.message; status.classList.add('text-danger'); }
        finally { button.disabled = false; }
    }));
    document.querySelectorAll('.paper-question-form').forEach(form => form.addEventListener('submit', async event => {
        event.preventDefault();
        const card = form.closest('.paper-question-editor'), button = form.querySelector('.paper-save-button'), status = form.querySelector('.paper-save-status');
        card.classList.add('is-saving'); button.disabled = true; status.textContent = 'Saving draft...';
        try {
            const response = await fetch(form.action, {method:'POST', body:new FormData(form), headers:{'Accept':'application/json','X-Requested-With':'XMLHttpRequest'}, credentials:'same-origin'});
            const result = await response.json().catch(() => ({}));
            if (!response.ok) throw new Error(result.message || Object.values(result.errors || {})[0]?.[0] || 'Draft could not be saved.');
            const changed = result.changed_fields || [], badge = card.querySelector('.paper-draft-badge');
            card.dataset.changed = changed.length ? '1' : '0';
            badge.className = 'badge paper-draft-badge ' + (changed.length ? 'bg-warning-subtle text-warning' : 'bg-light text-muted border');
            badge.textContent = changed.length ? changed.length + ' draft changes' : 'No draft changes';
            status.textContent = result.message || 'Draft saved.';
        } catch (error) { status.textContent = error.message; status.classList.add('text-danger'); }
        finally { card.classList.remove('is-saving'); button.disabled = false; }
    }));
    document.addEventListener('manual-crop-saved', event => {
        const editor = event.detail?.editorSelector ? document.querySelector(event.detail.editorSelector) : null;
        const card = editor?.closest('.paper-question-editor');
        if (!card) return;
        card.dataset.changed = '1';
        const badge = card.querySelector('.paper-draft-badge'), status = card.querySelector('.paper-save-status');
        badge.className = 'badge paper-draft-badge bg-warning-subtle text-warning';
        const isMathpix = event.detail?.mode === 'mathpix';
        badge.textContent = isMathpix ? 'Mathpix text saved' : 'Crop auto-saved';
        status.textContent = isMathpix ? 'Reviewed Mathpix text inserted and saved to this draft.' : 'Image cropped and saved directly to this draft.';
        if (!isMathpix) editor.closest('.border')?.querySelector('[data-remove-image]')?.classList.remove('d-none');
    });
    const search = document.getElementById('paper-question-search'), changedOnly = document.getElementById('paper-changed-only'), cards = Array.from(document.querySelectorAll('.paper-question-editor')), count = document.getElementById('paper-visible-count');
    const filter = () => { const term=(search.value||'').trim().toLowerCase(); let visible=0; cards.forEach(card=>{const show=(!term||card.dataset.search.includes(term))&&(!changedOnly.checked||card.dataset.changed==='1');card.classList.toggle('d-none',!show);if(show)visible++;});count.textContent=visible; };
    search?.addEventListener('input', filter); changedOnly?.addEventListener('change', filter);
});
</script>
@endsection

@extends('layouts.master')

@section('title', 'Manual Paper Draft')

@php
    $labels = [
        'question' => 'Question text / formula / image',
        'option1' => 'Option A', 'option2' => 'Option B', 'option3' => 'Option C',
        'option4' => 'Option D', 'option5' => 'Option E', 'option6' => 'Option F',
        'answer' => 'Answer', 'true_false' => 'True / False answer',
        'fill_blank' => 'Fill-blank answer', 'correct_option_indices' => 'Correct option numbers (JSON)',
        'si_answer1' => 'Subjective answer', 'hint' => 'Hint', 'explanation' => 'Explanation',
    ];
@endphp

@section('content')
@component('components.breadcrumb')
    @slot('li_1', 'Exams')
    @slot('title', 'Manual Paper Draft')
@endcomponent

@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
@if($sharedExams > 1)<div class="alert alert-warning"><strong>Shared question:</strong> this question is used in {{ $sharedExams }} exams. Publishing changes its live content everywhere it is shared; keeping it as a draft changes nothing.</div>@endif

<div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
    <div>
        <h3 class="mb-1">{{ $exam->name }}</h3>
        <div class="text-muted">Question ID {{ $draft->question_id }} - changes remain draft-only until you publish.</div>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <a class="btn btn-outline-secondary" href="{{ route('exams.paper.edit', $exam) }}"><i class="ri-arrow-left-line me-1"></i>Full paper editor</a>
        <a class="btn btn-outline-primary" href="{{ route('exams.paper.preview', $exam) }}" target="_blank"><i class="ri-file-paper-2-line me-1"></i>Rendered paper</a>
    </div>
</div>

<div class="row g-3 align-items-start manual-paper-workspace">
    <div class="col-xl-7"><div class="manual-source-workspace">
@if($hasPdfSource)
    @include('question-drafts.manual-cropper', [
        'cropAction' => route('exams.paper.questions.crop', [$exam, $draft]),
        'cropPageUrl' => route('exams.paper.questions.source-page', [$exam, $draft]),
        'cropEvidence' => (array) $draft->evidence,
        'cropPayload' => $payload,
        'cropDefaultPage' => $cropDefaultPage,
        'cropHasSourceRoles' => true,
    ])
@else
    <div class="alert alert-info"><strong>No attached source PDF.</strong> Add a question or combined PDF from the exam settings to enable page viewing and cropping. Text editing below remains available.</div>
@endif
    </div></div>
    <div class="col-xl-5">
<form method="POST" action="{{ route('exams.paper.questions.update', [$exam, $draft]) }}" data-disable-ai-inline>
    @csrf
    @method('PATCH')
    <div class="card">
        <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div><h4 class="mb-1">Edit saved draft</h4><div class="text-muted">No AI request is made. Save stores the draft; Publish applies it to the live paper with version history.</div></div>
            <span class="badge bg-primary-subtle text-primary" id="manual-draft-status">{{ count((array) $draft->changed_fields) }} changed fields</span>
        </div>
        <div class="card-body">
            @foreach($labels as $field => $label)
                @php
                    $current = $original[$field] ?? null;
                    $value = array_key_exists($field, $payload) ? $payload[$field] : $current;
                    $isJson = $field === 'correct_option_indices';
                    if ($isJson) {
                        $current = $current ? json_encode($current, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE) : '[]';
                        $value = $value ? json_encode($value, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE) : '[]';
                    }
                    $changed = in_array($field, (array) $draft->changed_fields, true);
                @endphp
                <div class="border rounded p-3 mb-3 {{ $changed ? 'border-warning' : '' }}">
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
                        <strong>{{ $label }}</strong>
                        <div class="d-flex align-items-center gap-2">
                            @if(in_array($field, ['question','option1','option2','option3','option4','option5','option6','explanation'], true) && $hasPdfSource)
                                <button type="button" class="btn btn-sm btn-outline-primary" data-crop-into="{{ $field }}"><i class="ri-crop-line me-1"></i>Crop into this field</button>
                            @endif
                            @if($changed)<span class="badge bg-warning-subtle text-warning">Draft change</span>@endif
                        </div>
                    </div>
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label small text-muted">Current live value</label>
                            <div class="form-control bg-light overflow-auto" style="min-height:80px;max-height:280px;white-space:pre-wrap">{!! $isJson ? e($current) : ($current ?: '<span class="text-muted">Empty</span>') !!}</div>
                        </div>
                        <div class="col-12">
                            <label class="form-label small text-muted">Manual draft</label>
                            <textarea class="form-control" rows="{{ in_array($field, ['question','explanation'], true) ? 6 : 3 }}" name="proposed[{{ $field }}]" data-manual-draft-field="{{ $field }}" @if(!$isJson) data-manual-preview="manual-preview-{{ $field }}" @endif>{{ $value }}</textarea>
                            @if(!$isJson)
                                <div class="small text-muted mt-2">Rendered preview</div>
                                <div class="border rounded p-2 mt-1" id="manual-preview-{{ $field }}" style="min-height:44px">{!! $value ?: '<span class="text-muted">Empty</span>' !!}</div>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
        <div class="card-footer d-flex flex-wrap justify-content-end gap-2">
            <button class="btn btn-primary" type="submit"><i class="ri-save-line me-1"></i>Save draft</button>
        </div>
    </div>
</form>

<form method="POST" action="{{ route('exams.paper.questions.publish', [$exam, $draft]) }}" class="d-flex justify-content-end mb-4">
    @csrf
    @method('PATCH')
    <button class="btn btn-success" type="submit" onclick="return confirm('Publish this saved draft to the live question? A restorable version will be created.')"><i class="ri-check-double-line me-1"></i>Publish this question</button>
</form>
    </div>
</div>
<style>@media (min-width:1200px){.manual-source-workspace{position:sticky;top:80px}.manual-source-workspace #source-crop-stage{height:calc(100vh - 330px)!important;min-height:420px}}</style>
@endsection

@section('script')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const render = textarea => {
        const preview = document.getElementById(textarea.dataset.manualPreview || '');
        if (!preview) return;
        preview.innerHTML = textarea.value || '<span class="text-muted">Empty</span>';
        if (window.MathJax?.typesetPromise) window.MathJax.typesetPromise([preview]).catch(() => {});
    };
    document.querySelectorAll('[data-manual-preview]').forEach(textarea => {
        textarea.addEventListener('input', () => render(textarea));
    });
    document.querySelectorAll('[data-crop-into]').forEach(button => button.addEventListener('click', () => {
        document.dispatchEvent(new CustomEvent('manual-crop-target', {detail:{target:button.dataset.cropInto}}));
    }));
    document.addEventListener('manual-crop-saved', event => {
        const field = event.detail?.target;
        const textarea = document.querySelector(`[data-manual-draft-field="${field}"]`);
        if (!textarea || typeof event.detail?.html !== 'string') return;
        textarea.value = event.detail.html;
        textarea.dispatchEvent(new Event('input', {bubbles: true}));
        document.getElementById('manual-draft-status').textContent = 'Crop auto-saved';
    });
});
</script>
@endsection

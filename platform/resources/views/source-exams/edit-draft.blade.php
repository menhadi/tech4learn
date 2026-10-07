@extends('layouts.master')
@section('rich-editor', true)
@section('title', 'Review question - '.$import->name)
@section('content')
<?php
$p = $draft->payload;
$currentQtype = $qtypes->firstWhere('id', (int) ($p['qtype_id'] ?? 0));
$currentType = strtoupper((string) ($currentQtype->type ?? ''));
$currentCorrectAnswers = collect($p['correct_answers'] ?? [])->map(fn ($value) => (int) $value)->filter()->values()->all();
if (! $currentCorrectAnswers && preg_match('/^[A-F]$/i', (string) ($p['correct_answer'] ?? ''))) $currentCorrectAnswers = [ord(strtoupper($p['correct_answer'])) - 64];
$fillBlanks = data_get($p, 'fill_blank_config.blanks');
if (! is_array($fillBlanks) || ! $fillBlanks) $fillBlanks = [['answers' => array_filter([(string) ($p['correct_answer'] ?? '')])]];
$natConfig = is_array($p['nat_config'] ?? null) ? $p['nat_config'] : ['mode' => 'exact', 'value' => is_numeric($p['correct_answer'] ?? null) ? $p['correct_answer'] : null];
?>
<div class="container-fluid">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
            <a href="{{ route('source-exams.show', $import) }}" class="text-muted">
                <i class="ri-arrow-left-line"></i> Back to {{ $import->name }}
            </a>
            <h3 class="mt-2 mb-0">Review Paper Q. {{ $draft->printed_question_number ?: $draft->paper_question_number }}</h3>
        </div>
        <div class="d-flex flex-wrap align-items-center gap-2">
            <a class="btn btn-outline-primary" href="{{ route('source-exams.drafts.preview', [$import, $draft]) }}" target="_blank"><i class="ri-file-paper-2-line me-1"></i>Rendered question</a>
            <a class="btn btn-outline-primary" href="{{ route('source-exams.preview', $import) }}" target="_blank"><i class="ri-book-open-line me-1"></i>Full paper</a>
            <span class="badge bg-{{ $draft->status === 'needs_review' ? 'warning' : 'success' }} px-3 py-2">
                {{ str_replace('_', ' ', ucfirst($draft->status)) }}
            </span>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header"><strong>Rendered preview</strong></div>
        <div class="card-body source-preview">
            <div class="mb-3">{!! $p['question'] ?? '' !!}</div>
            <?php if ($currentType === 'M'): for ($i = 1; $i <= 6; $i++):
                $optionKey = 'option'.$i;
                $optionValue = $p[$optionKey] ?? null;
            ?>
                <?php if (! empty($optionValue)): ?>
                    <div class="border rounded p-2 mb-2">
                        {!! $optionValue !!}
                    </div>
                <?php endif; ?>
            <?php endfor; endif; ?>
        </div>
    </div>

    @include('partials.structured-content-review', [
        'structuredItems' => data_get($draft->source_evidence, 'structured_content', []),
        'structuredAction' => route('source-exams.drafts.structured-content', [$import, $draft]),
        'structuredSourceBase' => null,
    ])

    @if(str_contains(strtolower((string) $import->question_source_type), 'pdf') || strtolower(pathinfo((string) $import->question_source_name, PATHINFO_EXTENSION)) === 'pdf')
        @include('question-drafts.manual-cropper', [
            'cropAction' => route('source-exams.drafts.crop', [$import, $draft]),
            'cropPageUrl' => route('source-exams.drafts.source-page', [$import, $draft]),
            'cropEvidence' => (array) $draft->source_evidence,
            'cropPayload' => (array) $draft->payload,
            'cropDefaultPage' => collect((array) data_get($draft->source_evidence, 'pages', []))->first(),
            'cropHasSourceRoles' => false,
        ])
    @else
        <div class="alert alert-secondary">Inline manual cropping is available when the authoritative question source is a PDF.</div>
    @endif

    <div class="card">
        <div class="card-header"><strong>Edit draft</strong></div>
        <div class="card-body">
            <form method="POST" action="{{ route('source-exams.drafts.update', [$import, $draft]) }}" data-disable-ai-inline>
                @csrf
                @method('PATCH')
                <div class="row g-3">
                    <div class="col-12">
                        <div class="d-flex justify-content-between align-items-center gap-2"><label class="form-label">Question (HTML + MathJax)</label><button type="button" class="btn btn-sm btn-outline-primary" data-crop-into="question" data-editor-selector="#source-question-editor"><i class="ri-crop-line me-1"></i>Crop into this field</button></div>
                        <textarea id="source-question-editor" name="question" rows="6" data-editor="true" data-height="300" class="form-control editor">{{ $p['question'] ?? '' }}</textarea>
                    </div>

                    <?php for ($i = 1; $i <= 6; $i++): $optionKey = 'option'.$i; ?>
                        <div class="col-md-6 source-answer-panel source-mcq-panel">
                            <div class="d-flex align-items-center justify-content-between gap-2">
                                <label class="form-label">Option {{ $i }}</label><button type="button" class="btn btn-sm btn-outline-primary ms-auto me-2" data-crop-into="option{{ $i }}" data-editor-selector="#source-option{{ $i }}-editor"><i class="ri-crop-line me-1"></i>Crop into this field</button>
                                <label class="form-check-label small text-muted">
                                    <input class="form-check-input me-1 source-correct-option" type="checkbox" name="correct_answers[]" value="{{ $i }}" {{ in_array($i, $currentCorrectAnswers, true) ? 'checked' : '' }}>
                                    Correct answer
                                </label>
                            </div>
                            <textarea id="source-option{{ $i }}-editor" name="option{{ $i }}" rows="3" data-editor="true" data-height="220" class="form-control editor">{{ $p[$optionKey] ?? '' }}</textarea>
                        </div>
                    <?php endfor; ?>

                    <div class="col-md-3">
                        <label class="form-label">Question type</label>
                        <select name="qtype_id" class="form-select" required>
                            <?php foreach ($qtypes as $qtype): ?>
                                <option value="{{ $qtype->id }}" data-type="{{ strtoupper($qtype->type) }}" {{ (int) ($p['qtype_id'] ?? 0) === (int) $qtype->id ? 'selected' : '' }}>
                                    {{ $qtype->question_type }}
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12 source-answer-panel source-fill-panel">
                        <div class="d-flex justify-content-between align-items-start mb-2"><div><label class="form-label mb-1">Blank answers</label><div class="small text-muted">Follow the order of blanks in the question. Separate accepted alternatives with <strong>|</strong>.</div></div><button type="button" class="btn btn-sm btn-outline-primary" id="add-source-blank"><i class="ri-add-line me-1"></i>Add Blank</button></div>
                        <div id="source-fill-rows">
                            @foreach($fillBlanks as $blankIndex => $blank)<div class="d-flex gap-2 align-items-start mb-2 source-fill-row"><span class="badge bg-light text-dark mt-2 source-blank-number">{{ $blankIndex + 1 }}</span><div class="flex-grow-1"><input class="form-control" name="fill_blank_answers[{{ $blankIndex }}][accepted_answers]" value="{{ implode(' | ', $blank['answers'] ?? []) }}" placeholder="Example: New Delhi | Delhi"></div><button type="button" class="btn btn-sm btn-outline-danger mt-1 remove-source-blank"><i class="ri-delete-bin-line"></i></button></div>@endforeach
                        </div>
                    </div>
                    <div class="col-12 source-answer-panel source-nat-panel">
                        <div class="alert alert-info py-2">NAT accepts an exact number, inclusive range, or value with tolerance.</div><div class="row g-3"><div class="col-md-3"><label class="form-label">Evaluation method</label><select class="form-select" name="nat_mode" id="source-nat-mode">@foreach(['exact'=>'Exact value','range'=>'Inclusive range','tolerance'=>'Value ± tolerance'] as $mode=>$label)<option value="{{ $mode }}" @selected(($natConfig['mode'] ?? 'exact') === $mode)>{{ $label }}</option>@endforeach</select></div><div class="col-md-3 source-nat-value"><label class="form-label">Correct numerical value</label><input type="number" step="any" class="form-control" name="nat_value" value="{{ $natConfig['value'] ?? '' }}"></div><div class="col-md-3 source-nat-tolerance"><label class="form-label">Tolerance (±)</label><input type="number" step="any" min="0" class="form-control" name="nat_tolerance" value="{{ $natConfig['tolerance'] ?? '' }}"></div><div class="col-md-3 source-nat-range"><label class="form-label">Minimum</label><input type="number" step="any" class="form-control" name="nat_min" value="{{ $natConfig['min'] ?? '' }}"></div><div class="col-md-3 source-nat-range"><label class="form-label">Maximum</label><input type="number" step="any" class="form-control" name="nat_max" value="{{ $natConfig['max'] ?? '' }}"></div></div>
                    </div>
                    <div class="col-12 source-answer-panel source-true-false-panel"><label class="form-label d-block">Correct answer</label><div class="form-check form-check-inline"><input class="form-check-input" type="radio" name="true_false" value="true" @checked(($p['true_false'] ?? $p['correct_answer'] ?? '') === 'true')><label class="form-check-label">True</label></div><div class="form-check form-check-inline"><input class="form-check-input" type="radio" name="true_false" value="false" @checked(($p['true_false'] ?? $p['correct_answer'] ?? '') === 'false')><label class="form-check-label">False</label></div></div>
                    <div class="col-12 source-answer-panel source-subjective-panel"><label class="form-label">Answer</label><textarea id="source-subjective-editor" name="si_answer1" rows="5" data-editor="true" data-height="260" class="form-control editor">{{ $p['si_answer1'] ?? $p['correct_answer'] ?? '' }}</textarea></div>
                    <div class="col-md-2">
                        <label class="form-label">Marks</label>
                        <input type="number" step=".01" min="0" name="marks" class="form-control" value="{{ $p['marks'] ?? 1 }}" required>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Negative</label>
                        <input type="number" step=".01" min="0" name="negative_marks" class="form-control" value="{{ $p['negative_marks'] ?? 0 }}" required>
                    </div>
                    <div class="col-12">
                        <div class="d-flex justify-content-between align-items-center gap-2"><label class="form-label">Explanation (HTML + MathJax)</label><button type="button" class="btn btn-sm btn-outline-primary" data-crop-into="explanation" data-editor-selector="#source-explanation-editor"><i class="ri-crop-line me-1"></i>Crop into this field</button></div>
                        <textarea id="source-explanation-editor" name="explanation" rows="6" data-editor="true" data-height="300" class="form-control editor">{{ $p['explanation'] ?? '' }}</textarea>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Reviewer notes</label>
                        <input name="review_notes" class="form-control" value="{{ $draft->review_notes }}">
                    </div>
                </div>
                <div class="d-flex justify-content-end gap-2 mt-3">
                    <a href="{{ route('source-exams.show', $import) }}" class="btn btn-outline-secondary">Cancel</a>
                    <button class="btn btn-primary"><i class="ri-save-line me-1"></i>Save & mark ready</button>
                </div>
            </form>
        </div>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-crop-into][data-editor-selector]').forEach(button => button.addEventListener('click', () => {
        document.dispatchEvent(new CustomEvent('manual-crop-target', {detail:{target:button.dataset.cropInto, editorSelector:button.dataset.editorSelector}}));
    }));
});
</script>
@push('styles')
<style>
.source-preview {
    font-size: 1rem;
    line-height: 1.65;
}
.source-preview mjx-container {
    font-size: 1em !important;
}
.source-preview mjx-container[display="true"] {
    margin: .65rem 0 !important;
    text-align: left !important;
}
</style>
@endpush

@push('scripts')
<script>
window.MathJax = { loader: { load: ['[tex]/mhchem'] }, tex: { packages: {'[+]': ['mhchem']}, inlineMath: [['\\(','\\)'],['$','$']], displayMath: [['\\[','\\]'],['$$','$$']], processEscapes: true }, options: { skipHtmlTags: ['script','noscript','style','textarea','pre','code'] } };
</script>
<script defer src="https://cdn.jsdelivr.net/npm/mathjax@3/es5/tex-mml-chtml.js" onload="MathJax.typesetPromise([document.querySelector('.source-preview')]).catch(()=>{})"></script>
<script>
$(document).ready(function () {
    const typeSelect = document.querySelector('select[name="qtype_id"]');
    const fillRows = document.getElementById('source-fill-rows');
    const addBlank = document.getElementById('add-source-blank');
    const natMode = document.getElementById('source-nat-mode');

    function selectedType() {
        return (typeSelect?.selectedOptions[0]?.dataset.type || '').toUpperCase();
    }

    function syncQuestionType() {
        const type = selectedType();
        document.querySelectorAll('.source-answer-panel').forEach(panel => panel.classList.add('d-none'));
        const selector = type === 'M' ? '.source-mcq-panel'
            : ['F', 'B'].includes(type) ? '.source-fill-panel'
            : type === 'NAT' ? '.source-nat-panel'
            : type === 'T' ? '.source-true-false-panel'
            : type === 'S' ? '.source-subjective-panel' : null;
        if (selector) document.querySelectorAll(selector).forEach(panel => panel.classList.remove('d-none'));
    }

    function renumberBlanks() {
        const rows = fillRows?.querySelectorAll('.source-fill-row') || [];
        rows.forEach((row, index) => {
            row.querySelector('.source-blank-number').textContent = index + 1;
            row.querySelector('input').name = `fill_blank_answers[${index}][accepted_answers]`;
            row.querySelector('.remove-source-blank').disabled = rows.length === 1;
        });
    }

    function addBlankRow() {
        const index = fillRows.querySelectorAll('.source-fill-row').length;
        const row = document.createElement('div');
        row.className = 'd-flex gap-2 align-items-start mb-2 source-fill-row';
        row.innerHTML = `<span class="badge bg-light text-dark mt-2 source-blank-number">${index + 1}</span><div class="flex-grow-1"><input class="form-control" name="fill_blank_answers[${index}][accepted_answers]" placeholder="Example: New Delhi | Delhi"></div><button type="button" class="btn btn-sm btn-outline-danger mt-1 remove-source-blank"><i class="ri-delete-bin-line"></i></button>`;
        fillRows.appendChild(row);
        renumberBlanks();
    }

    function syncNatMode() {
        const mode = natMode?.value || 'exact';
        document.querySelectorAll('.source-nat-value').forEach(el => el.classList.toggle('d-none', mode === 'range'));
        document.querySelectorAll('.source-nat-range').forEach(el => el.classList.toggle('d-none', mode !== 'range'));
        document.querySelectorAll('.source-nat-tolerance').forEach(el => el.classList.toggle('d-none', mode !== 'tolerance'));
    }

    if (typeof createCkeditor === 'function') createCkeditor();
    document.querySelector('form[data-disable-ai-inline]')?.addEventListener('submit', function () {
        if (typeof initCkeditor === 'function') initCkeditor();
        if (typeof tinymce !== 'undefined') tinymce.triggerSave();
    });
    typeSelect?.addEventListener('change', syncQuestionType);
    natMode?.addEventListener('change', syncNatMode);
    addBlank?.addEventListener('click', addBlankRow);
    fillRows?.addEventListener('click', function (event) {
        const button = event.target.closest('.remove-source-blank');
        if (!button || fillRows.querySelectorAll('.source-fill-row').length <= 1) return;
        button.closest('.source-fill-row').remove();
        renumberBlanks();
    });
    syncQuestionType();
    syncNatMode();
    renumberBlanks();
    if (window.MathJax && MathJax.typesetPromise) MathJax.typesetPromise([document.querySelector('.source-preview')]);
});
</script>
@endpush
@endsection
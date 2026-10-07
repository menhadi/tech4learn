@extends('layouts.master')

@section('title', $mode === 'edit' ? 'Edit Study Card' : 'Add Study Card')

@section('content')
@component('components.breadcrumb')
    @slot('li_1', 'Study Cards')
    @slot('title', $mode === 'edit' ? 'Edit Study Card' : 'Add Study Card')
@endcomponent

@php
    $isEdit = $mode === 'edit';
    $action = $isEdit ? route('flashcards.cards.update', [$flashcardSet, $card]) : route('flashcards.cards.store', $flashcardSet);
    $questionFilterAction = $isEdit
        ? route('flashcards.cards.edit', [$flashcardSet, $card])
        : route('flashcards.cards.create', $flashcardSet);
    $inlineObjectiveQtypes = collect($filterData['qtypes'] ?? [])->filter(function ($qtype) {
        $typeCode = strtoupper((string) $qtype->type);
        $typeName = strtolower((string) $qtype->question_type);

        return in_array($typeCode, ['M', 'T', 'F', 'MCQ', 'TF', 'FB'], true)
            || str_contains($typeName, 'multiple')
            || str_contains($typeName, 'true')
            || str_contains($typeName, 'false')
            || str_contains($typeName, 'fill');
    })->sortBy(function ($qtype) {
        $typeCode = strtoupper((string) $qtype->type);
        $typeName = strtolower((string) $qtype->question_type);

        if (str_contains($typeName, 'multiple') || in_array($typeCode, ['M', 'MCQ'], true)) {
            return 1;
        }

        if (str_contains($typeName, 'fill') || in_array($typeCode, ['FB'], true)) {
            return 2;
        }

        if (str_contains($typeName, 'true') || str_contains($typeName, 'false') || in_array($typeCode, ['T', 'TF', 'F'], true)) {
            return 3;
        }

        return 9;
    })->values();

    $inlineDefaultQtypeId = old('qtype_id');

    if (! $inlineDefaultQtypeId) {
        $inlineDefaultQtypeId = optional($inlineObjectiveQtypes->first())->id;
    }

    $advancedQuestionFiltersActive = request()->filled('category')
        || request()->filled('subcategory')
        || request()->filled('package')
        || request()->filled('exam')
        || request()->filled('subject')
        || request()->filled('topic')
        || request()->filled('subtopic')
        || request()->filled('qtype')
        || request()->filled('diff');
    $questionFilterValue = fn ($key, $default = null) => request()->has($key) ? request($key) : $default;
    $linkedQuestions = collect();

    if ($isEdit) {
        $linkedQuestions = $card->sourceQuestions ?? collect();

        if ($linkedQuestions->isEmpty() && $card->sourceQuestion) {
            $linkedQuestions = collect([$card->sourceQuestion]);
        }
    }

    $inlineGroupIds = collect(old('group_ids', $flashcardSet->group_id ? [$flashcardSet->group_id] : []))
        ->filter()
        ->map(fn ($id) => (string) $id)
        ->all();
    $inlineSubjectId = old('subject_id', $flashcardSet->subject_id);
    $inlineTopicId = old('topic_id', $flashcardSet->topic_id);
    $inlineStopicId = old('stopic_id', $flashcardSet->stopic_id);
@endphp

<style>
    .flashcard-page-panel { background:#fff; border:1px solid var(--el-border); border-radius:8px; box-shadow:0 8px 20px rgba(15,23,42,.05); overflow:hidden; }
    .flashcard-page-header { padding:20px 22px; background:color-mix(in srgb, var(--el-primary) 8%, #fff); border-bottom:1px solid var(--el-border); }
    .flashcard-page-header h4 { color:var(--el-heading); font-weight:800; margin:0 0 6px; font-size:24px; }
    .flashcard-page-header .text-muted,
    .flashcard-section-note,
    .flashcard-question-meta { color:var(--el-muted) !important; }
    .flashcard-section { padding:22px; border-bottom:1px solid var(--el-border); }
    .flashcard-section:last-child { border-bottom:0; }
    .flashcard-section-title { display:flex; align-items:center; gap:8px; color:var(--el-heading); font-weight:800; font-size:20px; margin-bottom:6px; }
    .flashcard-section-title i { color:var(--el-primary); font-size:20px; }
    .flashcard-form-body .form-label,
    .flashcard-question-bank .form-label { color:var(--el-heading); font-weight:700; }
    .flashcard-form-body textarea.form-control { min-height:260px; line-height:1.55; }
    .flashcard-page-panel .cke,
    .flashcard-page-panel .tox-tinymce { border-color:var(--el-border) !important; border-radius:8px !important; overflow:hidden; }
    .flashcard-page-panel .btn { border-radius:6px; box-shadow:none !important; font-weight:700; min-height:42px; min-width:118px; padding:10px 18px; display:inline-flex; align-items:center; justify-content:center; gap:6px; }
    .flashcard-page-panel .flashcard-outline-btn { background:#fff !important; border:1px solid var(--el-primary) !important; color:var(--el-primary) !important; }
    .flashcard-page-panel .flashcard-outline-btn:hover,
    .flashcard-page-panel .flashcard-outline-btn:focus { background:var(--el-primary-soft) !important; border-color:var(--el-primary) !important; color:var(--el-primary) !important; }
    .flashcard-manual-actions { display:flex; justify-content:space-between; align-items:center; gap:10px; flex-wrap:wrap; margin-top:18px; }
    .flashcard-option-strip { display:flex; flex-wrap:wrap; gap:10px; align-items:center; margin-top:16px; }
    .flashcard-inline-question { display:none; margin-top:16px; border:1px solid var(--el-border); border-radius:8px; background:#fff; overflow:hidden; }
    .flashcard-inline-question.is-open { display:block; }
    .flashcard-inline-question__head { padding:14px 16px; background:var(--el-primary-soft); border-bottom:1px solid var(--el-border); }
    .flashcard-inline-question__body { padding:16px; }
    .flashcard-inline-question__section { border:1px solid var(--el-border); border-radius:8px; padding:16px; margin-bottom:16px; background:#fff; }
    .flashcard-inline-question__section-title { color:var(--el-heading); font-size:17px; font-weight:800; margin:0 0 14px; }
    .flashcard-inline-question textarea.form-control { min-height:170px; line-height:1.55; }
    .flashcard-qtype-row { display:flex; flex-wrap:wrap; gap:10px; }
    .flashcard-qtype-row .form-check { margin:0; padding:0; position:relative; }
    .flashcard-qtype-row .form-check-input { height:1px; opacity:0; position:absolute; width:1px; }
    .flashcard-qtype-row .form-check-label { align-items:center; border:1px solid var(--el-border); border-radius:6px; color:var(--el-heading); cursor:pointer; display:inline-flex; font-weight:700; gap:8px; min-height:40px; padding:8px 12px; transition:.18s ease; }
    .flashcard-qtype-row .form-check-label i { color:var(--el-primary); display:none; font-size:17px; }
    .flashcard-qtype-row .form-check-input:checked + .form-check-label { background:var(--el-primary-soft); border-color:var(--el-primary); color:var(--el-primary); }
    .flashcard-qtype-row .form-check-input:checked + .form-check-label i { display:inline-flex; }
    .flashcard-status-check { display:flex; gap:10px; align-items:center; margin-bottom:8px; }
    .flashcard-mini-check { align-items:center; cursor:pointer; display:inline-flex; gap:10px; margin:0; }
    .flashcard-mini-check input { height:1px; opacity:0; position:absolute; width:1px; }
    .flashcard-mini-check__box { align-items:center; background:#fff; border:1px solid var(--el-primary); border-radius:4px; color:#fff; display:inline-flex; height:22px; justify-content:center; width:22px; }
    .flashcard-mini-check input:checked + .flashcard-mini-check__box { background:var(--el-primary); border-color:var(--el-primary); }
    .flashcard-mini-check__box i { font-size:15px; line-height:1; }
    .flashcard-page-panel input[type="checkbox"].form-check-input { accent-color:var(--el-primary); border:1px solid var(--el-primary); border-radius:4px; height:20px; width:20px; }
    .flashcard-page-panel input[type="checkbox"].form-check-input:checked { background-color:var(--el-primary); border-color:var(--el-primary); }
    .flashcard-answer-check { cursor:pointer; display:inline-flex; flex:0 0 24px; height:24px; margin:8px 0 0; position:relative; width:24px; }
    .flashcard-answer-check input { cursor:pointer; height:100%; inset:0; opacity:0; position:absolute; width:100%; z-index:2; }
    .flashcard-answer-check__box { align-items:center; background:#fff; border:1px solid var(--el-primary); border-radius:4px; color:#fff; display:inline-flex; height:24px; justify-content:center; width:24px; }
    .flashcard-answer-check__box i { font-size:17px; opacity:0; }
    .flashcard-answer-check input:checked + .flashcard-answer-check__box { background:var(--el-primary); border-color:var(--el-primary); }
    .flashcard-answer-check input:checked + .flashcard-answer-check__box i { opacity:1; }
    .flashcard-editor-wrap { flex:1 1 auto; min-width:0; position:relative; width:100%; }
    .flashcard-editor-wrap > .d-flex.justify-content-end.mb-1 { margin-bottom:6px !important; width:100%; }
    .flashcard-editor-wrap .ai-inline-content-btn { background:var(--el-primary) !important; border-color:var(--el-primary) !important; border-radius:6px !important; color:#fff !important; margin-left:auto; min-height:36px; min-width:116px; padding:7px 12px; }
    .flashcard-editor-wrap .ai-inline-content-btn:hover,
    .flashcard-editor-wrap .ai-inline-content-btn:focus { background:var(--el-primary-dark, var(--el-primary)) !important; border-color:var(--el-primary-dark, var(--el-primary)) !important; color:#fff !important; }
    .flashcard-option-editor-row { align-items:flex-start; display:flex; gap:12px; width:100%; }
    .flashcard-option-editor-row .flashcard-editor-wrap textarea,
    .flashcard-option-editor-row .flashcard-editor-wrap .cke { width:100% !important; }
    .flashcard-question-bank { margin-top:18px; border:1px solid var(--el-border); border-radius:8px; overflow:hidden; background:#fff; }
    .flashcard-question-bank__head { padding:14px 16px; background:var(--el-primary-soft); border-bottom:1px solid var(--el-border); }
    .flashcard-question-bank__filters { padding:16px; border-bottom:1px solid var(--el-border); }
    .flashcard-filter-row { align-items:flex-end; display:flex; flex-wrap:nowrap; gap:10px; }
    .flashcard-filter-row__group { flex:0 0 260px; max-width:260px; min-width:0; }
    .flashcard-filter-row__action,
    .flashcard-filter-row__buttons { flex:0 0 auto; }
    .flashcard-filter-row__buttons { display:flex; gap:10px; }
    .flashcard-advanced-grid { display:grid; grid-template-columns:repeat(auto-fit, minmax(190px, 1fr)); gap:12px; padding-top:16px; margin-top:16px; border-top:1px solid var(--el-border); }
    .flashcard-question-bank__filter-actions { display:flex; flex-wrap:wrap; gap:10px; align-items:center; justify-content:space-between; padding:14px 16px; border-bottom:1px solid var(--el-border); }
    .flashcard-question-bank__filter-actions .btn { min-width:120px; }
    .flashcard-filter-loading { align-items:center; color:var(--el-primary); display:none; font-weight:700; gap:8px; }
    .flashcard-filter-loading::before { animation:flashcard-filter-pulse .75s ease-in-out infinite alternate; background:var(--el-primary); border-radius:999px; content:""; height:8px; width:8px; }
    .flashcard-question-bank.is-updating .flashcard-filter-loading { display:inline-flex; }
    .flashcard-question-bank.is-updating .flashcard-question-count { display:none; }
    .flashcard-table-toolbar { display:flex; flex-wrap:wrap; gap:10px; align-items:center; justify-content:flex-end; padding:14px 16px; border-bottom:1px solid var(--el-border); background:#fff; }
    .flashcard-table-toolbar .flashcard-search { width:min(360px, 100%); }
    .flashcard-table-toolbar .flashcard-per-page { width:150px; }
    .flashcard-question-table thead th { background:var(--el-table-header-bg, color-mix(in srgb, var(--el-primary) 10%, #fff)); color:var(--el-heading); font-weight:700; }
    .flashcard-question-preview { max-width:520px; white-space:normal; color:var(--el-heading); }
    .flashcard-question-meta { font-size:13px; margin-top:4px; }
    .flashcard-question-add-row { display:flex; flex-wrap:wrap; gap:10px; align-items:center; justify-content:space-between; padding:14px 16px; border-bottom:1px solid var(--el-border); }
    .flashcard-source-question { border:1px solid var(--el-border); border-radius:8px; padding:14px 16px; background:color-mix(in srgb, var(--el-primary) 6%, #fff); margin-top:14px; }
    .flashcard-source-question__title { color:var(--el-heading); font-weight:800; margin-bottom:6px; }
    .flashcard-source-question__meta { color:var(--el-muted); font-size:13px; }
    .flashcard-linked-question-list { display:grid; gap:10px; margin-top:10px; }
    .flashcard-linked-question-item { background:#fff; border:1px solid var(--el-border); border-radius:8px; padding:12px; }
    .flashcard-linked-question-head { display:flex; align-items:flex-start; justify-content:space-between; gap:12px; }
    .flashcard-linked-question-actions { align-items:center; display:flex; flex-wrap:wrap; gap:8px; justify-content:flex-end; }
    .flashcard-linked-question-review { margin-top:12px; padding:12px; border:1px solid var(--el-border); border-radius:8px; background:var(--el-primary-soft); display:none; }
    .flashcard-linked-question-review.is-open { display:block; }
    .flashcard-linked-question-review__label { color:var(--el-muted); font-size:12px; font-weight:800; text-transform:uppercase; letter-spacing:.02em; margin-bottom:4px; }
    .flashcard-linked-question-review__box { background:#fff; border:1px solid var(--el-border); border-radius:8px; padding:10px 12px; margin-bottom:10px; }
    .flashcard-linked-question-review__options { display:grid; gap:8px; margin:0 0 10px; padding:0; list-style:none; }
    .flashcard-linked-question-review__options li { background:#fff; border:1px solid var(--el-border); border-radius:8px; padding:8px 10px; }
    .flashcard-link-status { display:inline-flex; align-items:center; justify-content:center; border-radius:6px; font-size:13px; font-weight:700; min-height:34px; padding:7px 10px; }
    .flashcard-link-status--used { background:var(--el-primary-soft); color:var(--el-primary); }
    @media (max-width: 767.98px) {
        .flashcard-page-header,
        .flashcard-section { padding:16px; }
        .flashcard-page-header h4 { font-size:22px; }
        .flashcard-table-toolbar { justify-content:stretch; }
        .flashcard-table-toolbar .flashcard-search,
        .flashcard-table-toolbar .flashcard-per-page { width:100%; }
        .flashcard-filter-row { flex-wrap:wrap; }
        .flashcard-filter-row__group,
        .flashcard-filter-row__action,
        .flashcard-filter-row__buttons { flex:1 1 100%; max-width:100%; }
        .flashcard-filter-row__buttons .btn { flex:1 1 0; }
    }
    @keyframes flashcard-filter-pulse {
        from { opacity:.45; transform:scale(.85); }
        to { opacity:1; transform:scale(1.15); }
    }
</style>

<div class="flashcard-page-panel">
    <div class="flashcard-page-header">
        <h4>{{ $isEdit ? 'Edit Study Card' : 'Create Manual Study Card' }}</h4>
        <div class="text-muted">
            {{ $flashcardSet->title }} | {{ $flashcardSet->package?->name ?? 'Package' }}
            @if($flashcardSet->subject) | {{ $flashcardSet->subject->subject_name }} @endif
            @if($flashcardSet->topic) | {{ $flashcardSet->topic->name }} @endif
            @if($flashcardSet->stopic) | {{ $flashcardSet->stopic->name }} @endif
        </div>
    </div>

    @if($errors->any())
        <div class="alert alert-danger m-3 mb-0">
            {{ $errors->first() }}
        </div>
    @endif

    <form action="{{ $action }}" method="POST" id="flashcardManualForm" class="flashcard-section flashcard-form-body">
        @csrf
        @if($isEdit)
            @method('PUT')
        @endif
        <input type="hidden" name="source_question_id" id="flashcardSourceQuestionId" value="{{ old('source_question_id', request('source_question_id', $linkedQuestions->first()?->id ?? $card->source_question_id)) }}">
        <div id="flashcardLinkedQuestionInputs">
            @foreach(collect(old('question_ids', $linkedQuestions->pluck('id')->all()))->filter()->unique() as $linkedQuestionId)
                <input type="hidden" name="question_ids[]" value="{{ (int) $linkedQuestionId }}" data-question-input="{{ (int) $linkedQuestionId }}">
            @endforeach
        </div>
        <div id="flashcardRemovedQuestionInputs"></div>

        <div class="flashcard-section-title">
            <i class="ri-file-edit-line"></i>
            <span>{{ $isEdit ? 'Edit Front and Back' : 'Manual Study Card' }}</span>
        </div>
        <div class="flashcard-section-note mb-4">
            Add the main concept, formula, or prompt on the front and the answer or explanation on the back. The editor supports rich text and MathJax/LaTeX.
        </div>

        <div class="row g-4">
            <div class="col-12">
                <label class="form-label" for="title">Card Title <span class="text-muted fw-normal">(optional)</span></label>
                <input type="text" id="title" name="title" class="form-control" value="{{ old('title', $card->title ?? '') }}" maxlength="255" placeholder="Example: Newton's laws quick revision">
                <div class="form-text">Shown in the study card list. If empty, the set name and card number will be used.</div>
            </div>
            <div class="col-12">
                <label class="form-label" for="front">Front</label>
                <div class="flashcard-editor-wrap">
                    <textarea data-editor="true" data-height="250" id="front" name="front" class="form-control editor" required>{{ old('front', $card->front) }}</textarea>
                </div>
                <div class="form-text">Concept, formula, prompt, or study point.</div>
            </div>
            <div class="col-12">
                <label class="form-label" for="back">Back</label>
                <div class="flashcard-editor-wrap">
                    <textarea data-editor="true" data-height="250" id="back" name="back" class="form-control editor" required>{{ old('back', $card->back) }}</textarea>
                </div>
                <div class="form-text">Answer, explanation, derivation, or key points.</div>
            </div>
                        <div class="col-12">
                <div class="flashcard-section-title mt-2"><i class="ri-question-line"></i><span>Manual Knowledge Checks <small class="text-muted">(optional; any number)</small></span></div>
                <div id="manualCheckRows">
                    @php $manualChecks = collect(old('manual_checks', $card->checks ?? collect()))->values(); @endphp
                    @foreach($manualChecks as $checkIndex => $check)
                        <div class="row g-2 border rounded p-2 mb-2 manual-check-row">
                            <div class="col-md-2"><select name="manual_checks[{{ $checkIndex }}][difficulty]" class="form-select"><option value="">Any level</option>@foreach(['Easy','Medium','Hard'] as $level)<option value="{{ $level }}" {{ data_get($check, 'difficulty') === $level ? 'selected' : '' }}>{{ $level }}</option>@endforeach</select></div>
                            <div class="col-md-10"><input name="manual_checks[{{ $checkIndex }}][question]" class="form-control" value="{{ data_get($check, 'question') }}" placeholder="Question"></div>
                            <div class="col-md-4"><textarea name="manual_checks[{{ $checkIndex }}][options]" class="form-control" rows="2" placeholder="Options, one per line">{{ collect(data_get($check, 'options', []))->join("\n") }}</textarea></div>
                            <div class="col-md-3"><input name="manual_checks[{{ $checkIndex }}][correct_answer]" class="form-control" value="{{ data_get($check, 'correct_answer') }}" placeholder="Correct answer"></div>
                            <div class="col-md-4"><textarea name="manual_checks[{{ $checkIndex }}][explanation]" class="form-control" rows="2" placeholder="Explanation">{{ data_get($check, 'explanation') }}</textarea></div>
                            <div class="col-md-1"><button type="button" class="btn btn-outline-danger js-remove-manual-check" aria-label="Remove check">&times;</button></div>
                        </div>
                    @endforeach
                </div>
                <button type="button" class="btn flashcard-outline-btn" id="addManualCheck"><i class="ri-add-line"></i> Add Manual Check</button>
                <small class="d-block text-muted mt-2">Database-linked questions remain below. Cards may have zero, one, three, or more checks.</small>
            </div><div class="col-md-2">
                <label class="form-label" for="sort_order">Order</label>
                <input type="number" id="sort_order" name="sort_order" class="form-control" value="{{ old('sort_order', $card->sort_order ?? 0) }}" min="0">
            </div>
            <div class="col-md-3 d-flex align-items-end">
                <div class="flashcard-status-check">
                    <input type="hidden" name="status" value="0">
                    <label class="flashcard-mini-check fw-bold" for="status">
                        <input type="checkbox" id="status" name="status" value="1" {{ old('status', $card->status ?? true) ? 'checked' : '' }}>
                        <span class="flashcard-mini-check__box"><i class="ri-check-line"></i></span>
                        Active
                    </label>
                </div>
            </div>
        </div>

        <div class="flashcard-manual-actions">
            <a href="{{ route('flashcards.show', $flashcardSet) }}" class="btn flashcard-outline-btn">
                <i class="ri-arrow-left-line me-1"></i> Back
            </a>
            <div class="d-flex gap-2 flex-wrap">
                <button type="reset" class="btn flashcard-outline-btn">Reset</button>
                <button type="submit" class="btn el-btn-primary">
                    <i class="ri-save-line me-1"></i> {{ $isEdit ? 'Update Study Card' : 'Save Manual Study Card' }}
                </button>
            </div>
        </div>
    </form>

    <div class="flashcard-section">
            <div class="flashcard-section-title">
                <i class="ri-question-answer-line"></i>
                <span>Add Questions To This Study Card Set</span>
            </div>
            <div class="flashcard-section-note">
                Use the normal question editor to create a new question, or filter the existing objective question bank and add selected questions as cards.
            </div>

            <div class="flashcard-source-question {{ $linkedQuestions->isEmpty() ? 'd-none' : '' }}" id="flashcardSelectedQuestionBox">
                <div class="flashcard-source-question__title">Linked Questions</div>
                <div class="flashcard-linked-question-list" id="flashcardLinkedQuestionList">
                    @foreach($linkedQuestions as $linkedQuestion)
                        @php
                            $linkedQuestionText = trim(strip_tags((string) $linkedQuestion->question));
                            if ($linkedQuestionText === '') {
                                $linkedQuestionText = trim((string) $linkedQuestion->question);
                            }
                            $linkedOptions = collect(range(1, 6))
                                ->map(fn ($index) => trim((string) data_get($linkedQuestion, 'option'.$index)))
                                ->filter()
                                ->values();
                            $linkedAnswers = collect([$linkedQuestion->answer, $linkedQuestion->true_false, $linkedQuestion->fill_blank, $linkedQuestion->si_answer1])
                                ->merge($linkedQuestion->correctOptionValues())
@section('rich-editor', true)
                                ->filter(fn ($value) => filled($value))
                                ->unique()
                                ->values();
                        @endphp
                        <div class="flashcard-linked-question-item" data-linked-question="{{ $linkedQuestion->id }}">
                            <div class="flashcard-linked-question-head">
                                <div>
                                    <div class="fw-bold">{{ \Illuminate\Support\Str::limit($linkedQuestionText ?: 'Question content is attached.', 180) }}</div>
                                    <div class="flashcard-source-question__meta mt-1">
                                        ID {{ $linkedQuestion->id }}
                                        @if($linkedQuestion->subject) | {{ $linkedQuestion->subject->subject_name }} @endif
                                        @if($linkedQuestion->topic) | {{ $linkedQuestion->topic->name }} @endif
                                        @if($linkedQuestion->qtype) | {{ $linkedQuestion->qtype->question_type }} @endif
                                    </div>
                                </div>
                                <div class="flashcard-linked-question-actions">
                                    <button type="button" class="btn flashcard-outline-btn btn-sm js-review-linked-question">
                                        <i class="ri-eye-line me-1"></i> Review
                                    </button>
                                    <a href="{{ route('questions.edit', $linkedQuestion->id) }}" target="_blank" class="btn el-btn-primary btn-sm">
                                        <i class="ri-edit-2-line me-1"></i> Edit Question
                                    </a>
                                </div>
                            </div>
                            <div class="flashcard-linked-question-review">
                                <div class="flashcard-linked-question-review__label">Question</div>
                                <div class="flashcard-linked-question-review__box">{!! $linkedQuestion->question ?: 'Question content is attached.' !!}</div>

                                @if($linkedOptions->isNotEmpty())
                                    <div class="flashcard-linked-question-review__label">Options</div>
                                    <ul class="flashcard-linked-question-review__options">
                                        @foreach($linkedOptions as $option)
                                            <li>{!! $option !!}</li>
                                        @endforeach
                                    </ul>
                                @endif

                                <div class="flashcard-linked-question-review__label">Correct Answer</div>
                                <div class="flashcard-linked-question-review__box">
                                    @if($linkedAnswers->isNotEmpty())
                                        {!! $linkedAnswers->implode('<br>') !!}
                                    @else
                                        -
                                    @endif
                                </div>

                                @if(filled($linkedQuestion->explanation))
                                    <div class="flashcard-linked-question-review__label">Explanation</div>
                                    <div class="flashcard-linked-question-review__box mb-0">{!! $linkedQuestion->explanation !!}</div>
                                @endif
                            </div>
                            <label class="form-check mt-2 mb-0">
                                <input class="form-check-input js-remove-linked-question" type="checkbox" name="remove_question_ids[]" value="{{ $linkedQuestion->id }}" form="flashcardManualForm">
                                <span class="form-check-label">Remove this question from the card on save</span>
                            </label>
                        </div>
                    @endforeach
                </div>
                <button type="button" class="btn flashcard-outline-btn mt-3" id="clearFlashcardSourceQuestion">
                    <i class="ri-close-line me-1"></i> Remove All Linked Questions
                </button>
            </div>

            <div class="flashcard-option-strip">
                <button type="button" class="btn el-btn-primary" id="toggleInlineQuestionForm">
                    <i class="ri-add-line me-1"></i> Create New Question
                </button>
                <a href="#flashcardQuestionBank" class="btn flashcard-outline-btn">
                    <i class="ri-database-2-line me-1"></i> Add Existing Questions
                </a>
            </div>

            <div class="flashcard-inline-question {{ old('flashcard_inline_question') || request()->boolean('open_question') ? 'is-open' : '' }}" id="flashcardInlineQuestionForm">
                <div class="flashcard-inline-question__head">
                    <h5 class="mb-1">Create New Question</h5>
                    <div class="text-muted small">This question will be saved to the question bank and added to this study card set.</div>
                </div>
                <form action="{{ route('questions.store') }}" method="POST" class="flashcard-inline-question__body" id="flashcardQuestionCreateForm">
                    @csrf
                    <input type="hidden" name="flashcard_set_id" value="{{ $flashcardSet->id }}">
                    <input type="hidden" name="return_to" value="{{ $isEdit ? 'edit' : 'create' }}">
                    @if($isEdit)
                        <input type="hidden" name="card_id" value="{{ $card->id }}">
                    @endif
                    <input type="hidden" name="flashcard_inline_question" value="1">

                    <div class="flashcard-inline-question__section">
                        <h6 class="flashcard-inline-question__section-title">Question Details</h6>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Group</label>
                                <select name="group_ids[]" class="form-control" required>
                                    <option value="">Select Group</option>
                                    @foreach($filterData['groups'] as $group)
                                        <option value="{{ $group->id }}" @selected(in_array((string) $group->id, $inlineGroupIds, true))>{{ $group->group_name }}</option>
                                    @endforeach
                                </select>
                                @if($flashcardSet->group_id)
                                    <div class="form-text">Prefilled from this study card set scope. Change only if this question belongs elsewhere.</div>
                                @endif
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Subject</label>
                                <select name="subject_id" class="form-control">
                                    <option value="">Select Subject</option>
                                    @foreach($filterData['subjects'] as $subject)
                                        <option value="{{ $subject->id }}" @selected((string) $inlineSubjectId === (string) $subject->id)>{{ $subject->subject_name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Topic</label>
                                <select name="topic_id" class="form-control">
                                    <option value="">Select Topic</option>
                                    @foreach($filterData['topics'] as $topic)
                                        <option value="{{ $topic->id }}" @selected((string) $inlineTopicId === (string) $topic->id)>{{ $topic->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Sub Topic</label>
                                <select name="stopic_id" class="form-control">
                                    <option value="">Select Sub Topic</option>
                                    @foreach($filterData['stopics'] as $stopic)
                                        <option value="{{ $stopic->id }}" @selected((string) $inlineStopicId === (string) $stopic->id)>{{ $stopic->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Question</label>
                                <div class="flashcard-editor-wrap">
                                    <textarea data-editor="true" data-height="220" name="question" class="form-control editor" placeholder="Write the question">{{ old('question') }}</textarea>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="flashcard-inline-question__section">
                        <h6 class="flashcard-inline-question__section-title">Answer and Options</h6>
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label">Question Type</label>
                                <div class="flashcard-qtype-row">
                                    @foreach($inlineObjectiveQtypes as $qtype)
                                        @php
                                            $typeCode = strtoupper((string) $qtype->type);
                                        @endphp
                                        <div class="form-check">
                                            <input class="form-check-input flashcard-inline-qtype" type="radio" name="qtype_id" id="inline_qtype_{{ $qtype->id }}" value="{{ $qtype->id }}" data-type="{{ $typeCode ?: $qtype->question_type }}" @checked((string) $inlineDefaultQtypeId === (string) $qtype->id)>
                                            <label class="form-check-label" for="inline_qtype_{{ $qtype->id }}">
                                                <i class="ri-check-line"></i>
                                                {{ $qtype->question_type }}
                                            </label>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                            <div class="col-12 flashcard-inline-options" data-card-options="mcq">
                                <label class="form-label">Options</label>
                                <div class="row g-3">
                                    @for($i = 1; $i <= 4; $i++)
                                        <div class="col-12">
                                            <div class="flashcard-option-editor-row">
                                                <label class="flashcard-answer-check" title="Correct answer option">
                                                    <input type="checkbox" name="correct_answers[]" value="{{ $i }}" @checked(in_array((string) $i, (array) old('correct_answers', []), true))>
                                                    <span class="flashcard-answer-check__box"><i class="ri-check-line"></i></span>
                                                </label>
                                                <div class="flashcard-editor-wrap">
                                                    <textarea data-editor="true" data-height="120" name="option{{ $i }}" class="form-control editor" placeholder="Option {{ $i }}">{{ old('option' . $i) }}</textarea>
                                                </div>
                                            </div>
                                        </div>
                                    @endfor
                                </div>
                            </div>
                            <div class="col-12 flashcard-inline-options" data-card-options="truefalse">
                                <label class="form-label">Correct Answer</label>
                                <select name="true_false" class="form-control">
                                    <option value="">Select Answer</option>
                                    <option value="true" @selected(old('true_false') === 'true')>True</option>
                                    <option value="false" @selected(old('true_false') === 'false')>False</option>
                                </select>
                            </div>
                            <div class="col-12 flashcard-inline-options" data-card-options="fillblank">
                                <label class="form-label">Correct Answer</label>
                                <div class="flashcard-editor-wrap">
                                    <textarea data-editor="true" data-height="120" name="fill_blank" class="form-control editor" placeholder="Fill in the blank answer">{{ old('fill_blank') }}</textarea>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="flashcard-inline-question__section">
                        <h6 class="flashcard-inline-question__section-title">Scoring and Explanation</h6>
                        <div class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label">Difficulty</label>
                                <select name="diff_id" class="form-control">
                                    <option value="">Select Difficulty</option>
                                    @foreach($filterData['diffs'] as $diff)
                                        <option value="{{ $diff->id }}" @selected((string) old('diff_id') === (string) $diff->id)>{{ $diff->diff_level }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Language</label>
                                <select name="language_id" class="form-control" required>
                                    @foreach($filterData['languages'] as $language)
                                        <option value="{{ $language->id }}" @selected((string) old('language_id') === (string) $language->id)>{{ $language->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Positive Marks</label>
                                <input type="number" step="0.01" name="marks" class="form-control" value="{{ old('marks', '1') }}">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Negative Marks</label>
                                <input type="number" step="0.01" name="negative_marks" class="form-control" value="{{ old('negative_marks', '0') }}">
                            </div>
                            <div class="col-12">
                                <label class="form-label">Hint</label>
                                <div class="flashcard-editor-wrap">
                                    <textarea data-editor="true" data-height="150" name="hint" class="form-control editor" placeholder="Optional hint">{{ old('hint') }}</textarea>
                                </div>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Explanation</label>
                                <div class="flashcard-editor-wrap">
                                    <textarea data-editor="true" data-height="150" name="explanation" class="form-control editor" placeholder="Explanation shown on the back of the card">{{ old('explanation') }}</textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="d-flex flex-wrap justify-content-end gap-2 mt-4">
                                <button type="button" class="btn flashcard-outline-btn" id="closeInlineQuestionForm">Cancel</button>
                                <button type="submit" class="btn el-btn-primary">
                                    <i class="ri-save-line me-1"></i> Save Question and Add Study Card
                                </button>
                    </div>
                </form>
            </div>

            <div class="flashcard-question-bank" id="flashcardQuestionBank">
                <div class="flashcard-question-bank__head">
                    <h5 class="mb-1">Existing Question Bank</h5>
                    <div class="text-muted small">Already-added questions are hidden. Only objective questions can be used as interactive cards.</div>
                </div>

                <form id="flashcardCardFilterForm" method="GET" action="{{ $questionFilterAction }}">
                    <div class="flashcard-question-bank__filters">
                        <div class="flashcard-filter-row">
                            <div class="flashcard-filter-row__group">
                                <label class="form-label">Group</label>
                                <select name="group" class="form-control">
                                    <option value="">All Groups</option>
                                    @foreach($filterData['groups'] as $group)
                                        <option value="{{ $group->id }}" @selected((string) $questionFilterValue('group', $flashcardSet->group_id) === (string) $group->id)>{{ $group->group_name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="flashcard-filter-row__action">
                                <button class="btn el-btn-primary" type="button" data-bs-toggle="collapse" data-bs-target="#flashcardAdvancedQuestionFilters" aria-expanded="{{ $advancedQuestionFiltersActive ? 'true' : 'false' }}" aria-controls="flashcardAdvancedQuestionFilters">
                                    <i class="ri-equalizer-line"></i> Advanced Filters
                                </button>
                            </div>
                            <div class="flashcard-filter-row__buttons">
                                <button class="btn el-btn-primary" type="submit"><i class="ri-search-line"></i> Search</button>
                                <a class="btn flashcard-outline-btn" href="{{ $questionFilterAction }}">Reset</a>
                            </div>
                        </div>
                        <div id="flashcardAdvancedQuestionFilters" class="collapse {{ $advancedQuestionFiltersActive ? 'show' : '' }}">
                            <div class="flashcard-advanced-grid">
                                <div>
                                    <label class="form-label">Category</label>
                                    <select name="category" class="form-control">
                                        <option value="">All Categories</option>
                                        @foreach($filterData['categories'] as $category)
                                            <option value="{{ $category->id }}" @selected((string) request('category') === (string) $category->id)>{{ $category->title }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div data-subcategory-ui>
                                    <label class="form-label">Subcategory</label>
                                    <select name="subcategory" class="form-control">
                                        <option value="">All Subcategories</option>
                                        @foreach($filterData['subcategories'] as $subcategory)
                                            <option value="{{ $subcategory->id }}" @selected((string) request('subcategory') === (string) $subcategory->id)>{{ $subcategory->title }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label class="form-label">Package</label>
                                    <select name="package" class="form-control">
                                        <option value="">All Packages</option>
                                        @foreach($filterData['packages'] as $package)
                                            <option value="{{ $package->id }}" @selected((string) $questionFilterValue('package', $flashcardSet->package_id) === (string) $package->id)>{{ $package->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label class="form-label">Exam</label>
                                    <select name="exam" class="form-control">
                                        <option value="">All Exams</option>
                                        @foreach($filterData['exams'] as $exam)
                                            <option value="{{ $exam->id }}" @selected((string) request('exam') === (string) $exam->id)>{{ $exam->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label class="form-label">Subject</label>
                                    <select name="subject" class="form-control">
                                        <option value="">All Subjects</option>
                                        @foreach($filterData['subjects'] as $subject)
                                            <option value="{{ $subject->id }}" @selected((string) $questionFilterValue('subject', $flashcardSet->subject_id) === (string) $subject->id)>{{ $subject->subject_name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label class="form-label">Topic</label>
                                    <select name="topic" class="form-control">
                                        <option value="">All Topics</option>
                                        @foreach($filterData['topics'] as $topic)
                                            <option value="{{ $topic->id }}" @selected((string) $questionFilterValue('topic', $flashcardSet->topic_id) === (string) $topic->id)>{{ $topic->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label class="form-label">Sub Topic</label>
                                    <select name="subtopic" class="form-control">
                                        <option value="">All Sub Topics</option>
                                        @foreach($filterData['stopics'] as $stopic)
                                            <option value="{{ $stopic->id }}" @selected((string) $questionFilterValue('subtopic', $flashcardSet->stopic_id) === (string) $stopic->id)>{{ $stopic->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label class="form-label">Question Type</label>
                                    <select name="qtype" class="form-control">
                                        <option value="">All Types</option>
                                        @foreach($filterData['qtypes'] as $qtype)
                                            <option value="{{ $qtype->id }}" @selected((string) request('qtype') === (string) $qtype->id)>{{ $qtype->question_type }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label class="form-label">Difficulty</label>
                                    <select name="diff" class="form-control">
                                        <option value="">All Difficulty</option>
                                        @foreach($filterData['diffs'] as $diff)
                                            <option value="{{ $diff->id }}" @selected((string) request('diff') === (string) $diff->id)>{{ $diff->diff_level }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="flashcard-question-bank__filter-actions">
                        <div class="text-muted flashcard-question-count">Showing {{ $availableQuestions->count() }} of {{ $availableQuestions->total() }} available questions</div>
                        <div class="flashcard-filter-loading">Updating questions...</div>
                    </div>
                </form>

                <form action="{{ route('flashcards.cards.add-selected', $flashcardSet) }}" method="POST" id="flashcardQuestionAddForm">
                    @csrf
                    <input type="hidden" name="return_to" value="{{ $isEdit ? 'edit' : 'create' }}">
                    @if($isEdit)
                        <input type="hidden" name="card_id" value="{{ $card->id }}">
                    @endif
                    <div class="flashcard-table-toolbar">
                        <input type="text" name="question" value="{{ request('question') }}" class="form-control flashcard-search" placeholder="Search question text or keyword" form="flashcardCardFilterForm">
                        <select name="per_page" class="form-control flashcard-per-page" form="flashcardCardFilterForm">
                            @foreach([50, 100, 500] as $size)
                                <option value="{{ $size }}" @selected((int) ($questionPerPage ?? 50) === $size)>{{ $size }} questions</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="flashcard-question-add-row">
                        <div class="d-flex flex-wrap gap-2 align-items-center">
                            <button type="submit" class="btn el-btn-primary"><i class="ri-add-line me-1"></i> Add Selected</button>
                            <select name="publish_status" class="form-control" style="width:150px;">
                                <option value="draft">Draft</option>
                                <option value="active">Active</option>
                            </select>
                        </div>
                        <div class="text-muted small">Question cards keep the original question as their source.</div>
                    </div>
                    <div class="table-responsive">
                        <table class="table align-middle mb-0 flashcard-question-table">
                            <thead>
                                <tr>
                                    <th style="width:44px;"><input type="checkbox" class="form-check-input" id="selectAllQuestionBank"></th>
                                    <th>Question</th>
                                    <th>Group</th>
                                    <th>Subject</th>
                                    <th>Type</th>
                                    <th>Difficulty</th>
                                    <th style="width:130px;">Card Link</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($availableQuestions as $question)
                                    @php
                                        $previewText = trim(strip_tags((string) $question->question));
                                        if ($previewText === '') {
                                            $previewText = trim((string) $question->question);
                                        }
                                        $isLinkedToCard = collect($linkedQuestionIds ?? [])->contains((int) $question->id);
                                    @endphp
                                    <tr>
                                        <td><input type="checkbox" name="question_ids[]" value="{{ $question->id }}" class="form-check-input flashcard-question-check"></td>
                                        <td>
                                            <div class="flashcard-question-preview">{{ \Illuminate\Support\Str::limit($previewText, 150) }}</div>
                                            <div class="flashcard-question-meta">ID {{ $question->id }}</div>
                                        </td>
                                        <td>{{ $question->groups->pluck('group_name')->take(2)->join(', ') ?: '-' }}</td>
                                        <td>{{ $question->subject?->subject_name ?? '-' }}</td>
                                        <td>{{ $question->qtype?->question_type ?? '-' }}</td>
                                        <td>{{ $question->diff?->diff_level ?? '-' }}</td>
                                        <td>
                                            @if($isLinkedToCard)
                                                <span class="flashcard-link-status flashcard-link-status--used">Used in Card</span>
                                            @else
                                                <button type="button"
                                                    class="btn flashcard-outline-btn btn-sm js-attach-question-to-card"
                                                    data-question-id="{{ $question->id }}"
                                                    data-question-text="{{ e(\Illuminate\Support\Str::limit($previewText ?: 'Question content is attached.', 160)) }}"
                                                    data-question-meta="ID {{ $question->id }}{{ $question->subject ? ' | '.$question->subject->subject_name : '' }}{{ $question->qtype ? ' | '.$question->qtype->question_type : '' }}">
                                                    Available
                                                </button>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="7" class="text-center text-muted py-4">No matching unused objective questions found.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div class="p-3">{{ $availableQuestions->links() }}</div>
                </form>
            </div>
    </div>
</div>

@endsection

@section('script')
<script>
    $(document).ready(function () {
        if (typeof createCkeditor === 'function') {
            createCkeditor();
        }

        $('#toggleInlineQuestionForm').on('click', function () {
            $('#flashcardInlineQuestionForm').addClass('is-open');
            document.getElementById('flashcardInlineQuestionForm')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
        });

        $('#closeInlineQuestionForm').on('click', function () {
            $('#flashcardInlineQuestionForm').removeClass('is-open');
        });

        function syncEditors() {
            if (typeof CKEDITOR !== 'undefined' && CKEDITOR.instances) {
                Object.keys(CKEDITOR.instances).forEach(function (instance) {
                    CKEDITOR.instances[instance].updateElement();
                });
            }
            if (typeof tinymce !== 'undefined') {
                tinymce.triggerSave();
            }
        }

        var flashcardIsEdit = @json($isEdit);

        $('#flashcardManualForm, #flashcardQuestionCreateForm, #flashcardQuestionAddForm').on('submit', function () {
            syncEditors();
        });

        function replaceQuestionBank(html, targetUrl) {
            var parser = new DOMParser();
            var doc = parser.parseFromString(html, 'text/html');
            var nextBank = doc.querySelector('#flashcardQuestionBank');
            var currentBank = document.querySelector('#flashcardQuestionBank');

            if (nextBank && currentBank) {
                currentBank.innerHTML = nextBank.innerHTML;
                window.history.replaceState({}, '', targetUrl);
            } else {
                window.location.href = targetUrl;
            }
        }

        function loadQuestionBank(url) {
            var bank = $('#flashcardQuestionBank');
            bank.addClass('is-updating');

            fetch(url, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'text/html'
                }
            })
                .then(function (response) { return response.text(); })
                .then(function (html) { replaceQuestionBank(html, url); })
                .catch(function () { window.location.href = url; })
                .finally(function () { $('#flashcardQuestionBank').removeClass('is-updating'); });
        }

        $(document).on('submit', '#flashcardCardFilterForm', function (event) {
            event.preventDefault();
            var params = new URLSearchParams(new FormData(this));
            document.querySelectorAll('[form="' + this.id + '"]').forEach(function (control) {
                if (!control.name || control.disabled) {
                    return;
                }
                params.set(control.name, control.value);
            });
            var url = this.action + '?' + params.toString();
            loadQuestionBank(url);
        });

        $(document).on('change', '#flashcardCardFilterForm select, [form="flashcardCardFilterForm"]', function () {
            $('#flashcardCardFilterForm').trigger('submit');
        });

        $(document).on('input', '[form="flashcardCardFilterForm"][name="question"]', function () {
            clearTimeout(window.flashcardQuestionSearchTimer);
            window.flashcardQuestionSearchTimer = setTimeout(function () {
                $('#flashcardCardFilterForm').trigger('submit');
            }, 450);
        });

        $(document).on('click', '#flashcardQuestionBank .pagination a', function (event) {
            event.preventDefault();
            loadQuestionBank(this.href);
        });

        function currentInlineType() {
            var checked = $('.flashcard-inline-qtype:checked');
            return String(checked.data('type') || checked.next('label').text() || '').toLowerCase();
        }

        function updateInlineQuestionFields() {
            var type = currentInlineType();
            var showTrueFalse = type.includes('t') && (type.includes('true') || type === 't' || type === 'tf');
            var showFillBlank = type.includes('f') && (type.includes('fill') || type === 'f' || type === 'fb');

            $('[data-card-options]').hide();
            if (showTrueFalse) {
                $('[data-card-options="truefalse"]').show();
            } else if (showFillBlank) {
                $('[data-card-options="fillblank"]').show();
            } else {
                $('[data-card-options="mcq"]').show();
            }
        }

        $('.flashcard-inline-qtype').on('change', updateInlineQuestionFields);
        updateInlineQuestionFields();

        $(document).on('change', '#selectAllQuestionBank', function () {
            $('.flashcard-question-check').prop('checked', this.checked);
        });

        function linkedQuestionIds() {
            return $('#flashcardLinkedQuestionInputs input[name="question_ids[]"]').map(function () {
                return String(this.value);
            }).get();
        }

        function refreshPrimaryQuestionId() {
            $('#flashcardSourceQuestionId').val(linkedQuestionIds()[0] || '');
        }

        function addLinkedQuestion(questionId, questionText, questionMeta) {
            questionId = String(questionId || '');

            if (!questionId || linkedQuestionIds().includes(questionId)) {
                return false;
            }

            $('#flashcardLinkedQuestionInputs').append(
                $('<input>', {
                    type: 'hidden',
                    name: 'question_ids[]',
                    value: questionId,
                    'data-question-input': questionId
                })
            );

            $('#flashcardLinkedQuestionList').append(
                $('<div>', {
                    class: 'flashcard-linked-question-item',
                    'data-linked-question': questionId
                }).append(
                    $('<div>', { class: 'fw-bold' }).text(questionText || 'Question content is attached.'),
                    $('<div>', { class: 'flashcard-source-question__meta mt-1' }).text(questionMeta || ('ID ' + questionId)),
                    $('<div>', { class: 'flashcard-source-question__meta mt-2' }).text('This question will be linked when you save the card.')
                )
            );

            $('#flashcardSelectedQuestionBox').removeClass('d-none');
            refreshPrimaryQuestionId();
            return true;
        }

        function removeLinkedQuestion(questionId) {
            questionId = String(questionId || '');
            $('#flashcardLinkedQuestionInputs input[data-question-input="' + questionId + '"]').remove();
            refreshPrimaryQuestionId();
        }

        $(document).on('click', '.js-attach-question-to-card', function () {
            var questionId = this.dataset.questionId || '';
            var questionText = this.dataset.questionText || 'Question content is attached.';
            var questionMeta = this.dataset.questionMeta || '';

            if (addLinkedQuestion(questionId, questionText, questionMeta)) {
                $(this).replaceWith('<span class="flashcard-link-status flashcard-link-status--used">Used in Card</span>');
            }

            document.getElementById('flashcardManualForm')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
        });

        $(document).on('click', '#clearFlashcardSourceQuestion', function () {
            $('.js-remove-linked-question').each(function () {
                $('#flashcardRemovedQuestionInputs').append(
                    $('<input>', {
                        type: 'hidden',
                        name: 'remove_question_ids[]',
                        value: this.value
                    })
                );
            });

            $('#flashcardSourceQuestionId').val('');
            $('#flashcardLinkedQuestionInputs').empty();
            $('#flashcardLinkedQuestionList').empty();
            $('#flashcardSelectedQuestionBox').addClass('d-none');
        });

        $(document).on('change', '.js-remove-linked-question', function () {
            if (this.checked) {
                removeLinkedQuestion(this.value);
            } else if (!linkedQuestionIds().includes(String(this.value))) {
                addLinkedQuestion(this.value, $(this).closest('.flashcard-linked-question-item').find('.fw-bold').text(), $(this).closest('.flashcard-linked-question-item').find('.flashcard-source-question__meta').first().text());
            }
        });

        $(document).on('click', '.js-review-linked-question', function () {
            const $item = $(this).closest('.flashcard-linked-question-item');
            const $panel = $item.find('.flashcard-linked-question-review').first();
            $panel.toggleClass('is-open');
            $(this).html($panel.hasClass('is-open')
                ? '<i class="ri-eye-off-line me-1"></i> Hide'
                : '<i class="ri-eye-line me-1"></i> Review');
        });

        $(document).on('submit', '#flashcardQuestionAddForm', function (event) {
            if (!$('.flashcard-question-check:checked').length) {
                event.preventDefault();
                alert('Select at least one question first.');
                return;
            }

            if (!flashcardIsEdit) {
                event.preventDefault();
                var added = 0;

                $('.flashcard-question-check:checked').each(function () {
                    var selectedRow = $(this).closest('tr');
                    var attachButton = selectedRow.find('.js-attach-question-to-card');

                    if (!attachButton.length) {
                        return;
                    }

                    if (addLinkedQuestion(
                        attachButton.data('question-id'),
                        attachButton.data('question-text'),
                        attachButton.data('question-meta')
                    )) {
                        attachButton.replaceWith('<span class="flashcard-link-status flashcard-link-status--used">Used in Card</span>');
                        added++;
                    }
                });

                if (added) {
                    alert(added + ' selected question(s) added successfully. Save the card to keep them.');
                } else {
                    alert('Selected questions are already linked to this card.');
                }
            }
        });
    });
</script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const rows = document.getElementById('manualCheckRows'), add = document.getElementById('addManualCheck');
    if (!rows || !add) return;
    let index = rows.querySelectorAll('.manual-check-row').length;
    add.addEventListener('click', function () {
        rows.insertAdjacentHTML('beforeend', `<div class="row g-2 border rounded p-2 mb-2 manual-check-row"><div class="col-md-2"><select name="manual_checks[${index}][difficulty]" class="form-select"><option value="">Any level</option><option>Easy</option><option>Medium</option><option>Hard</option></select></div><div class="col-md-10"><input name="manual_checks[${index}][question]" class="form-control" placeholder="Question"></div><div class="col-md-4"><textarea name="manual_checks[${index}][options]" class="form-control" rows="2" placeholder="Options, one per line"></textarea></div><div class="col-md-3"><input name="manual_checks[${index}][correct_answer]" class="form-control" placeholder="Correct answer"></div><div class="col-md-4"><textarea name="manual_checks[${index}][explanation]" class="form-control" rows="2" placeholder="Explanation"></textarea></div><div class="col-md-1"><button type="button" class="btn btn-outline-danger js-remove-manual-check" aria-label="Remove check">&times;</button></div></div>`); index++;
    });
    rows.addEventListener('click', function (event) { const button = event.target.closest('.js-remove-manual-check'); if (button) button.closest('.manual-check-row').remove(); });
});
</script>
@endsection

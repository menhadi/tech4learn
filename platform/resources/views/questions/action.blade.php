@extends('layouts.master')
@section('title', isset($question) ? 'Edit Question' : 'Add Question')

@section('css')
<style>
    .tox-edit-area__iframe:focus { border: 2px solid #405189; }
    .load-editor-btn-hide { display: none !important; }
    .answer-rule-row { border: 1px solid var(--el-border, #dce5e5); border-radius: 10px; padding: 14px; background: var(--el-soft, #f7fbfb); }
    .answer-rule-number { width: 34px; height: 34px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; background: var(--theme-primary, #0f7b70); color: #fff; font-weight: 700; flex: 0 0 auto; }
    .student-preview-shell { border: 1px solid var(--el-border, #dce5e5); border-radius: 12px; background: var(--el-surface, #fff); overflow: hidden; }
    .student-preview-question { padding: 18px; color: var(--el-heading, #1f2937); font-size: 1.05rem; line-height: 1.7; }
    .student-preview-options { display: grid; gap: 10px; padding: 0 18px 18px; }
    .student-preview-option { display: flex; gap: 10px; align-items: flex-start; border: 1px solid var(--el-border, #dce5e5); border-radius: 10px; padding: 10px 12px; }
    .student-preview-option-label { width: 28px; height: 28px; flex: 0 0 28px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; background: var(--el-primary-soft, #e8f4f2); color: var(--el-primary, #0f766e); font-weight: 700; }
    .student-preview-option-content { min-width: 0; flex: 1; }
    .student-preview-question img,
    .student-preview-option-content img { max-width: 100%; height: auto; }
</style>
@endsection

@section('content')
@component('components.breadcrumb')
@slot('li_1', '')
@slot('title', isset($question) ? 'Edit Question' : 'Add Question')
@endcomponent

<div class="row">
    <div class="col-lg-12">
        <form id="questionForm" method="POST" action="{{ isset($question) ? route('questions.update', $question->id) : route('questions.store') }}">
            @csrf
            @if(isset($question)) @method('PUT') @endif
            @if(old('return_url', request('return_url')))
                <input type="hidden" name="return_url" value="{{ old('return_url', request('return_url')) }}">
            @endif
            @if(!isset($question) && request('flashcard_set_id'))
                <input type="hidden" name="flashcard_set_id" value="{{ request('flashcard_set_id') }}">
            @endif

            {{-- 1. Question Details --}}
            <div class="card">
                <div class="card-header"><h5 class="card-title mb-0">1. Question Details</h5></div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="passage_id" class="form-label">Passage (optional)</label>
                            <select id="passage_id" name="passage_id" class="form-control select2">
                                <option value="">Select Passage</option>
                                @foreach($passages as $passage)
                                <option value="{{ $passage->id }}" {{ isset($question) && $question->passage_id == $passage->id ? 'selected' : '' }}>{{ $passage->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Question Type</label>
                        <div>
                            @foreach($qtypes as $qtype)
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="qtype_id" id="qtype_{{ $qtype->id }}" value="{{ $qtype->id }}" {{ (string) old('qtype_id', isset($question) ? $question->qtype_id : '') === (string) $qtype->id ? 'checked' : '' }} data-type="{{ $qtype->type }}">
                                <label class="form-check-label" for="qtype_{{ $qtype->id }}">{{ $qtype->question_type }}</label>
                            </div>
                            @endforeach
                        </div>
                    </div>

                    <div class="mb-3">
                        <!-- <x-textarea-editor id="question" name="question" label="Question" placeholder="Enter question here" :value="old('question', isset($question) ? $question->question : '')" /> -->

                        <textarea data-editor="true" data-height="300" id="question" name="question" label="Question" class="form-control form-control-lg form-control-solid mb-3 mb-lg-0 editor" placeholder="Enter question here">{{ old('question', isset($question) ? $question->question : '') }}</textarea>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-lg-8">
                            <label for="source_url" class="form-label">Original source URL <span class="text-muted">(private, optional)</span></label>
                            @php $savedQuestionSourceUrl = old('source_url', $question->source_url ?? ''); @endphp
                            <div class="input-group">
                                <input type="url" id="source_url" name="source_url" class="form-control" value="{{ $savedQuestionSourceUrl }}" placeholder="https://official-source.example/question-paper">
                                @if($savedQuestionSourceUrl)
                                    <a class="btn btn-outline-secondary" href="{{ $savedQuestionSourceUrl }}" target="_blank" rel="noopener" title="Open source in a new tab"><i class="ri-external-link-line"></i> Open</a>
                                @endif
                            </div>
                            <small class="text-muted">Used only by the quality audit bot; never displayed to students.</small>
                        </div>
                        <div class="col-lg-4">
                            <label for="source_reference" class="form-label">Source reference <span class="text-muted">(optional)</span></label>
                            <input type="text" id="source_reference" name="source_reference" class="form-control" value="{{ old('source_reference', $question->source_reference ?? '') }}" placeholder="Page 12 / Question 18">
                        </div>
                    </div>
                </div>
            </div>

            {{-- 2. Options --}}
            <div class="card">
                <div class="card-header"><h5 class="card-title mb-0">2. Answer & Options</h5></div>
                <div class="card-body">
                    <div id="si-answer-option" style="display: none;">
                        <x-textarea-editor id="si_answer1" name="si_answer1" label="Answer" placeholder="Answer" :value="old('si_answer1', isset($question) ? $question->si_answer1 : '')" />
                    </div>
                    <div id="multiple-choice-options" style="display: none;">
                        <div class="accordion" id="optionsAccordion">
                            @for ($i = 1; $i <= 6; $i++)
                            <div class="accordion-item">
                                <h2 class="accordion-header" id="headingOption{{ $i }}">
                                    <button class="accordion-button {{ $i > 1 ? 'collapsed' : '' }}" type="button" data-bs-toggle="collapse" data-bs-target="#collapseOption{{ $i }}">
                                        <span class="flex-grow-1">Option {{ $i }}</span>
                                        <div class="form-check form-check-inline me-3" onclick="event.stopPropagation();">
                                            <input class="form-check-input" type="checkbox" name="correct_answers[]" value="{{ $i }}" {{ isset($question) && in_array($i, $question->correctOptionIndices(), true) ? 'checked' : '' }}>
                                            <label class="form-check-label">Correct</label>
                                        </div>
                                    </button>
                                </h2>
                                <div id="collapseOption{{ $i }}" class="accordion-collapse collapse {{ $i == 1 ? 'show' : '' }}" data-bs-parent="#optionsAccordion">
                                    <div class="accordion-body">
                                        <!-- <x-textarea-editor id="option{{ $i }}" name="option{{ $i }}" label="" placeholder="Option {{ $i }}" :value="old('option' . $i, isset($question) ? $question->{'option' . $i} : '')" /> -->

                                        <textarea id="option{{ $i }}" data-editor="true" data-height="300" name="option{{ $i }}" label="Option {{ $i }}" class="form-control form-control-lg form-control-solid mb-3 mb-lg-0 editor" placeholder="Option {{ $i }}">{{ old('option' . $i, isset($question) ? $question->{'option' . $i} : '') }}</textarea>
                                    </div>
                                </div>
                            </div>
                            @endfor
                        </div>
                    </div>
                    
                    <div id="true-false-options" style="display: none;">
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="true_false" value="true" {{ isset($question) && $question->true_false == 'true' ? 'checked' : '' }}><label class="form-check-label">True</label>
                        </div>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="true_false" value="false" {{ isset($question) && $question->true_false == 'false' ? 'checked' : '' }}><label class="form-check-label">False</label>
                        </div>
                    </div>

                    @php
                        $savedFillBlanks = old('fill_blank_answers');
                        if ($savedFillBlanks === null) {
                            $configuredBlanks = isset($question) ? (($question->fill_blank_config['blanks'] ?? null)) : null;
                            $savedFillBlanks = $configuredBlanks
                                ? collect($configuredBlanks)->map(fn ($blank) => ['accepted_answers' => implode(' | ', $blank['answers'] ?? [])])->all()
                                : [['accepted_answers' => isset($question) ? trim(strip_tags((string) $question->fill_blank)) : '']];
                        }
                        $savedNat = isset($question) && is_array($question->nat_config) ? $question->nat_config : [];
                    @endphp
                    <div id="fill-blank-options" style="display: none;">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <div><label class="form-label mb-1">Blank answers</label><div class="text-muted small">Answers follow the order of the blanks in the question. Separate acceptable alternatives with <strong>|</strong>.</div></div>
                            <button type="button" class="btn el-btn-soft btn-sm" id="add-fill-blank"><i class="ri-add-line me-1"></i>Add Blank</button>
                        </div>
                        <div id="fill-blank-rows">
                            @foreach(($savedFillBlanks ?? [['accepted_answers' => '']]) as $blankIndex => $blank)
@section('rich-editor', true)
                                <div class="answer-rule-row d-flex gap-3 align-items-start mb-2" data-fill-blank-row>
                                    <span class="answer-rule-number">{{ $blankIndex + 1 }}</span>
                                    <div class="flex-grow-1"><label class="form-label">Accepted fixed answer(s)</label><input type="text" class="form-control" name="fill_blank_answers[{{ $blankIndex }}][accepted_answers]" value="{{ data_get($blank, 'accepted_answers') }}" placeholder="Example: New Delhi | Delhi"><small class="text-muted">Text ignores capitalization and extra spaces. Numeric values such as 1 and 1.0 are treated equally.</small></div>
                                    <button type="button" class="btn btn-outline-danger btn-sm mt-4 remove-fill-blank" title="Remove blank"><i class="ri-delete-bin-line"></i></button>
                                </div>
                            @endforeach
                        </div>
                        @error('fill_blank_answers')<div class="text-danger small mt-2">{{ $message }}</div>@enderror
                    </div>
                    <div id="nat-options" style="display: none;">
                        <div class="alert alert-info py-2">NAT accepts one numerical response. Choose an exact value, an inclusive range, or a value with tolerance.</div>
                        <div class="row g-3">
                            <div class="col-md-4"><label class="form-label">Evaluation method</label><select class="form-select" name="nat_mode" id="nat-mode">@foreach(['exact' => 'Exact value', 'range' => 'Inclusive range', 'tolerance' => 'Value ± tolerance'] as $mode => $label)<option value="{{ $mode }}" @selected(old('nat_mode', $savedNat['mode'] ?? 'exact') === $mode)>{{ $label }}</option>@endforeach</select></div>
                            <div class="col-md-4 nat-value-field"><label class="form-label">Correct numerical value</label><input type="number" step="any" class="form-control" name="nat_value" value="{{ old('nat_value', $savedNat['value'] ?? '') }}"></div>
                            <div class="col-md-4 nat-tolerance-field"><label class="form-label">Tolerance (±)</label><input type="number" step="any" min="0" class="form-control" name="nat_tolerance" value="{{ old('nat_tolerance', $savedNat['tolerance'] ?? '') }}"></div>
                            <div class="col-md-4 nat-range-field"><label class="form-label">Minimum</label><input type="number" step="any" class="form-control" name="nat_min" value="{{ old('nat_min', $savedNat['min'] ?? '') }}"></div>
                            <div class="col-md-4 nat-range-field"><label class="form-label">Maximum</label><input type="number" step="any" class="form-control" name="nat_max" value="{{ old('nat_max', $savedNat['max'] ?? '') }}"></div>
                        </div>
                        @error('nat_value')<div class="text-danger small mt-2">{{ $message }}</div>@enderror
                        @error('nat_min')<div class="text-danger small mt-2">{{ $message }}</div>@enderror
                    </div>
                </div>
            </div>

            {{-- 3. Categorization --}}
            <div class="card">
                <div class="card-header"><h5 class="card-title mb-0">3. Categorization</h5></div>
                <div class="card-body">
                    <div class="mb-3"><label class="form-label">Groups</label><select id="group_ids" name="group_ids[]" class="form-control select2" multiple>
                        @foreach($groups as $group)
                        <option value="{{ $group->id }}" {{ isset($question) && in_array($group->id, $question->groups->pluck('id')->toArray()) ? 'selected' : '' }}>{{ $group->group_name }}</option>
                        @endforeach
                    </select></div>
                    @php
                        $selectedQuestionTags = collect(old('tag_ids', isset($question) ? $question->tags->pluck('id')->all() : []))
                            ->map(fn ($id) => (int) $id)
                            ->all();
                    @endphp
                    <div class="mb-3">
                        <label class="form-label">Question Tags</label>
                        <select id="tag_ids" name="tag_ids[]" class="form-control select2" multiple>
                            @foreach($questionTags as $tag)
                                <option value="{{ $tag->id }}" {{ in_array((int) $tag->id, $selectedQuestionTags, true) ? 'selected' : '' }}>{{ $tag->name }}</option>
                            @endforeach
                        </select>
                        <small class="text-muted">Use tags for source, AI/manual, sharing, review status, or organization usage.</small>
                    </div>
                    <div class="row">
                        <div class="col-md-3 mb-3"><label class="form-label">Subject</label><select id="subject_id" name="subject_id" class="form-control select2" disabled><option value="">Select (Optional)</option></select></div>
                        <div class="col-md-3 mb-3"><label class="form-label">Section</label><select id="question_section_id" name="question_section_id" class="form-control select2" disabled><option value="">Select (Optional)</option></select><small class="text-muted">Independent of subject; filtered by group.</small></div>
                        
                        {{-- ✅ Yahan topic aur stopic ka 'required' hata diya gaya hai --}}
                        <div class="col-md-3 mb-3"><label class="form-label">Topic</label><select id="topic_id" name="topic_id" class="form-control select2" disabled><option value="">Select (Optional)</option></select></div>
                        <div class="col-md-3 mb-3"><label class="form-label">Sub Topic</label><select id="stopic_id" name="stopic_id" class="form-control select2" disabled><option value="">Select (Optional)</option></select></div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3"><label class="form-label">Difficulty</label><select id="diff_id" name="diff_id" class="form-control select2">
                            <option value="">Select (Optional)</option>
                            @foreach($diffs as $diff) <option value="{{ $diff->id }}" {{ isset($question) && $question->diff_id == $diff->id ? 'selected' : '' }}>{{ $diff->diff_level }}</option> @endforeach
                        </select></div>
                        <div class="col-md-6 mb-3"><label class="form-label">Language</label><select id="language_id" name="language_id" class="form-control select2">
                            @foreach($languages as $language) <option value="{{ $language->id }}" {{ isset($question) && $language->id == $question->language_id ? 'selected' : '' }}>{{ $language->name }}</option> @endforeach
                        </select></div>
                    </div>
                </div>
            </div>

            {{-- 4. Scoring --}}
            <div class="card">
                <div class="card-header"><h5 class="card-title mb-0">4. Scoring</h5></div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4"><label class="form-label">Marks</label><input type="number" step="0.01" name="marks" class="form-control" value="{{ old('marks', $question->marks ?? '') }}"></div>
                        <div class="col-md-4"><label class="form-label">Negative Marks</label><input type="number" step="0.01" name="negative_marks" class="form-control" value="{{ old('negative_marks', $question->negative_marks ?? '') }}"></div>
                        <div class="col-md-4"><label class="form-label">Scoring policy</label><select name="scoring_policy" class="form-control"><option value="NORMAL" @selected(old('scoring_policy', $question->scoring_policy ?? 'NORMAL') === 'NORMAL')>Normal evaluation</option><option value="MTA" @selected(old('scoring_policy', $question->scoring_policy ?? 'NORMAL') === 'MTA')>Marks to all (MTA)</option></select><small class="text-muted">MTA awards positive marks even when left blank.</small></div>
                    </div>
                    <div class="mb-3 mt-3">
                        <!-- <x-textarea-editor id="hint" name="hint" label="Hint" placeholder="Hint" :value="old('hint', $question->hint ?? '')" /> -->
                        <textarea data-editor="true" data-height="300" id="hint" name="hint" class="form-control form-control-lg form-control-solid mb-3 mb-lg-0 editor" placeholder="Hint">{{ old('hint', $question->hint ?? '') }}</textarea>
                    </div>
                    <div class="mb-3">
                        <!-- <x-textarea-editor id="explanation" name="explanation" label="Explanation" placeholder="Explanation" :value="old('explanation', $question->explanation ?? '')" /> -->
                        <textarea data-editor="true" data-height="300" id="explanation" name="explanation" class="form-control form-control-lg form-control-solid mb-3 mb-lg-0 editor" placeholder="Explanation">{{ old('explanation', $question->explanation ?? '') }}</textarea>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header d-flex flex-wrap gap-3 justify-content-between align-items-center">
                    <div>
                        <h5 class="card-title mb-1">Student Preview</h5>
                        <div class="text-muted small">This uses the same MathJax configuration as the student exam page.</div>
                    </div>
                    <button type="button" class="btn el-btn-soft" id="refresh-student-preview">
                        <i class="ri-eye-line me-1"></i> Preview as Student
                    </button>
                </div>
                <div class="card-body">
                    <div class="student-preview-shell" id="student-preview-shell">
                        <div class="student-preview-question text-muted" id="student-preview-question">
                            Click <strong>Preview as Student</strong> to verify text, images and equations before saving.
                        </div>
                        <div class="student-preview-options d-none" id="student-preview-options"></div>
                    </div>
                    <div class="small text-muted mt-2" id="student-preview-status" role="status" aria-live="polite">
                        The preview does not change the saved question.
                    </div>
                </div>
            </div>

            <div class="d-flex justify-content-end mb-4">
                <button type="submit" class="btn el-btn-primary el-btn-icon">
                    <i class="ri-save-line"></i> Save Question
                </button>
            </div>
        </form>
    </div>
</div>
@endsection

@section('script')
@include('partials.student-mathjax')
<script>
    $(document).ready(function() {

        createCkeditor();
        
        // 1. Hide duplicate Load Editor Buttons
        setTimeout(function() {
            $("button:contains('Load Editor')").hide();
        }, 500);

        const previewQuestion = document.getElementById('student-preview-question');
        const previewOptions = document.getElementById('student-preview-options');
        const previewShell = document.getElementById('student-preview-shell');
        const previewStatus = document.getElementById('student-preview-status');

        function editorHtml(id) {
            if (typeof CKEDITOR !== 'undefined' && CKEDITOR.instances[id]) {
                return CKEDITOR.instances[id].getData();
            }

            const field = document.getElementById(id);
            return field ? field.value : '';
        }

        function previewSafeHtml(html) {
            const template = document.createElement('template');
            template.innerHTML = html || '';
            template.content.querySelectorAll('script, iframe, object, embed').forEach(element => element.remove());
            template.content.querySelectorAll('*').forEach(element => {
                Array.from(element.attributes).forEach(attribute => {
                    if (/^on/i.test(attribute.name)) {
                        element.removeAttribute(attribute.name);
                    }
                });
            });

            return template.innerHTML;
        }

        function hasPreviewContent(html) {
            const template = document.createElement('template');
            template.innerHTML = html || '';
            return template.content.textContent.trim() !== ''
                || template.content.querySelector('img, math, mjx-container, svg, table') !== null;
        }

        async function refreshStudentPreview() {
            const mathJax = window.MathJax;
            if (mathJax && typeof mathJax.typesetClear === 'function') {
                mathJax.typesetClear([previewShell]);
            }

            const questionHtml = editorHtml('question');
            previewQuestion.classList.toggle('text-muted', !hasPreviewContent(questionHtml));
            previewQuestion.innerHTML = hasPreviewContent(questionHtml)
                ? previewSafeHtml(questionHtml)
                : 'No question content has been entered.';

            previewOptions.replaceChildren();
            for (let optionNumber = 1; optionNumber <= 6; optionNumber++) {
                const optionHtml = editorHtml('option' + optionNumber);
                if (!hasPreviewContent(optionHtml)) continue;

                const option = document.createElement('div');
                option.className = 'student-preview-option';
                option.innerHTML = '<span class="student-preview-option-label">'
                    + String.fromCharCode(64 + optionNumber)
                    + '</span><div class="student-preview-option-content">'
                    + previewSafeHtml(optionHtml)
                    + '</div>';
                previewOptions.appendChild(option);
            }
            previewOptions.classList.toggle('d-none', previewOptions.childElementCount === 0);

            if (!mathJax || typeof mathJax.typesetPromise !== 'function') {
                previewStatus.textContent = 'Math renderer is still loading. Wait a moment and preview again.';
                return;
            }

            previewStatus.textContent = 'Rendering equations...';
            try {
                await mathJax.typesetPromise([previewShell]);
                previewStatus.textContent = 'Preview rendered with the student MathJax configuration.';
            } catch (error) {
                previewStatus.textContent = 'An equation could not be rendered. Check its LaTeX source before saving.';
                console.error('Student preview MathJax error', error);
            }
        }

        $('#refresh-student-preview').on('click', refreshStudentPreview);



        // Form Submit
        $('#questionForm').on('submit', function() {
            if(typeof tinymce !== 'undefined') {
                tinymce.triggerSave();
            }
        });

        // Dropdowns Logic
        
        if ($.fn.select2) {
            $('.select2').select2({ placeholder: "Select", allowClear: true });
        }

        $('#subject_id').on('select2:clear change', function() {
            if (!$(this).val()) {
                window.__questionSubjectInitialized = true;
                window.__questionTopicInitialized = true;
                window.__questionStopicInitialized = true;
                $('#topic_id').val(null).trigger('change').prop('disabled', true).empty().append('<option value="">Select (Optional)</option>');
                $('#stopic_id').val(null).trigger('change').prop('disabled', true).empty().append('<option value="">Select (Optional)</option>');
            }
        });

        $('#diff_id').on('select2:clear change', function() {
            if (!$(this).val()) {
                $(this).val(null).trigger('change.select2');
            }
        });

        const topics = @json($topics);
        const stopics = @json($stopics);

        function populateSections(groups) {
            const select = $('#question_section_id');
            select.prop('disabled', !groups || groups.length === 0).empty().append('<option value="">Select (Optional)</option>');
            if (!groups || groups.length === 0) return;
            $.get("{{ route('questions.get-sections-by-group') }}", { group_ids: groups }, function(data) {
                data.forEach(section => select.append(`<option value="${section.id}">${section.name}</option>`));
                @if(isset($question) && !empty($question->question_section_id))
                    if (!select.data('initialized')) { select.val('{{ $question->question_section_id }}').trigger('change'); select.data('initialized', true); }
                @endif
            });
        }
        function populateSubjects(groups) {
            if(!groups || groups.length === 0) return;
            $.get("{{ route('questions.get-subjects-by-group') }}", { group_ids: groups }, function(data) {
                $('#subject_id').prop('disabled', false).empty().append('<option value="">Select (Optional)</option>');
                data.forEach(s => $('#subject_id').append(`<option value="${s.id}">${s.subject_name}</option>`));
                @if(isset($question) && !empty($question->subject_id)) if (!$('#subject_id').data('initialized')) { $('#subject_id').val('{{ $question->subject_id }}').trigger('change'); $('#subject_id').data('initialized', true); } @endif
            });
        }
        
        $('#group_ids').change(function() { const groups = $(this).val(); populateSubjects(groups); populateSections(groups); });
        $('#subject_id').change(function() {
            let sid = $(this).val();
            $('#topic_id').prop('disabled', !sid).empty().append('<option value="">Select (Optional)</option>');
            const primaryGroup = ($('#group_ids').val() || [])[0];
            if(sid) topics.filter(t => t.subject_id == sid && String(t.group_id) === String(primaryGroup)).forEach(t => $('#topic_id').append(`<option value="${t.id}">${t.name}</option>`));
            @if(isset($question) && !empty($question->topic_id)) if (!window.__questionTopicInitialized) { $('#topic_id').val('{{ $question->topic_id }}').trigger('change'); window.__questionTopicInitialized = true; } @endif
        });
        $('#topic_id').change(function() {
            let tid = $(this).val();
            $('#stopic_id').prop('disabled', !tid).empty().append('<option value="">Select (Optional)</option>');
            if(tid) stopics.filter(s => s.topic_id == tid).forEach(s => $('#stopic_id').append(`<option value="${s.id}">${s.name}</option>`));
             @if(isset($question) && !empty($question->stopic_id)) if (!window.__questionStopicInitialized) { $('#stopic_id').val('{{ $question->stopic_id }}').trigger('change'); window.__questionStopicInitialized = true; } @endif
        });

        @if(isset($question)) $('#group_ids').trigger('change'); @endif

        // 5. Question Type Logic (Show/Hide Options)
        $('input[name="qtype_id"]').change(function() {
            const type = $(this).data('type');
            $('#multiple-choice-options, #true-false-options, #fill-blank-options, #nat-options, #si-answer-option').hide();
            if (type === 'M') $('#multiple-choice-options').show();
            else if (type === 'T') $('#true-false-options').show();
            else if (type === 'F' || type === 'B') $('#fill-blank-options').show();
            else if (type === 'NAT') $('#nat-options').show();
            else if (type === 'S') $('#si-answer-option').show();
        });


        function refreshFillBlankRows() {
            $('#fill-blank-rows [data-fill-blank-row]').each(function(index) {
                $(this).find('.answer-rule-number').text(index + 1);
                $(this).find('input').attr('name', `fill_blank_answers[${index}][accepted_answers]`);
            });
            $('.remove-fill-blank').prop('disabled', $('#fill-blank-rows [data-fill-blank-row]').length <= 1);
        }
        $('#add-fill-blank').on('click', function() {
            const index = $('#fill-blank-rows [data-fill-blank-row]').length;
            $('#fill-blank-rows').append(`<div class="answer-rule-row d-flex gap-3 align-items-start mb-2" data-fill-blank-row><span class="answer-rule-number">${index + 1}</span><div class="flex-grow-1"><label class="form-label">Accepted fixed answer(s)</label><input type="text" class="form-control" name="fill_blank_answers[${index}][accepted_answers]" placeholder="Example: New Delhi | Delhi"><small class="text-muted">Separate acceptable alternatives with |.</small></div><button type="button" class="btn btn-outline-danger btn-sm mt-4 remove-fill-blank" title="Remove blank"><i class="ri-delete-bin-line"></i></button></div>`);
            refreshFillBlankRows();
        });
        $(document).on('click', '.remove-fill-blank', function() {
            if ($('#fill-blank-rows [data-fill-blank-row]').length > 1) { $(this).closest('[data-fill-blank-row]').remove(); refreshFillBlankRows(); }
        });
        function refreshNatFields() {
            const mode = $('#nat-mode').val();
            $('.nat-range-field').toggle(mode === 'range');
            $('.nat-value-field').toggle(mode !== 'range');
            $('.nat-tolerance-field').toggle(mode === 'tolerance');
        }
        $('#nat-mode').on('change', refreshNatFields);
        refreshFillBlankRows();
        refreshNatFields();
        if ($('input[name="qtype_id"]:checked').length) { 
            $('input[name="qtype_id"]:checked').trigger('change'); 
        } else {
            $('input[name="qtype_id"]').first().prop('checked', true).trigger('change');
        }
    });
</script>
@endsection

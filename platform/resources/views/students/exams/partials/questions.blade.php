{{-- ✅✅✅ MATHS FIX: Using SVG Engine to fix hidden element rendering ✅✅✅ --}}
@include('partials.student-mathjax')

{{-- ✅ MODIFIED: Removed border classes from main div, added flex structure --}}
<style>
    .subject-btn.btn-primary,
    #prevButton.btn-primary,
    #nextButton.btn-primary {
        background: var(--el-primary);
        border-color: var(--el-primary);
        color: var(--theme-button-text, #fff);
    }
    .subject-btn.btn-outline-primary {
        background: #fff;
        border-color: var(--el-primary);
        color: var(--el-primary);
    }
    .subject-btn.btn-primary:hover,
    #prevButton.btn-primary:hover,
    #nextButton.btn-primary:hover {
        background: var(--el-primary-dark, var(--el-primary));
        border-color: var(--el-primary-dark, var(--el-primary));
        color: var(--theme-button-text, #fff);
    }
    .bookmark-button.btn-info,
    .bookmark-button.btn-outline-info {
        background: var(--el-primary-soft);
        border-color: var(--el-primary);
        color: var(--el-primary);
    }
    .exam-question-shell {
        background: var(--el-surface, #fff);
        border: 1px solid var(--el-border);
        border-radius: 12px;
        overflow: hidden;
        box-shadow: 0 10px 24px rgba(15, 23, 42, .05);
    }
    .exam-question-titlebar {
        background: var(--el-primary-soft);
        border-bottom: 1px solid var(--el-border);
    }
    .exam-question-toolbar {
        border-top: 1px solid var(--el-border);
        background: var(--el-surface);
    }
    .exam-meta-badge-primary { background: var(--el-primary); color: var(--theme-button-text, #fff); }
    .exam-meta-badge-secondary { background: var(--el-secondary); color: var(--theme-button-text, #fff); }
    .exam-meta-badge-soft { background: var(--el-surface-muted); color: var(--el-heading); border: 1px solid var(--el-border); }
    .exam-question-row-head { background: var(--el-surface-muted) !important; border-bottom: 1px solid var(--el-border) !important; }
    .exam-passage-box { background: var(--el-surface-muted) !important; border-color: var(--el-border) !important; }
    .exam-explanation-box { background: var(--el-secondary-soft) !important; border-color: color-mix(in srgb, var(--el-secondary) 35%, #fff) !important; }
    .exam-tool-btn {
        background: var(--el-surface);
        border-color: var(--el-border);
        color: var(--el-heading);
        font-weight: 700;
    }
    .exam-tool-btn:hover,
    .exam-tool-btn:focus {
        background: var(--el-primary-soft);
        border-color: var(--el-primary);
        color: var(--el-primary);
    }
    .options-container .form-check {
        background: var(--el-surface);
        border: 1px solid var(--el-border);
        border-radius: 10px;
        padding: 10px 12px 10px 38px;
        cursor: pointer;
        transition: border-color .16s ease, background-color .16s ease, box-shadow .16s ease;
    }
    .options-container .form-check:hover {
        border-color: color-mix(in srgb, var(--el-primary) 38%, var(--el-border));
        background: var(--el-primary-soft);
    }
    .options-container .form-check:has(.form-check-input:checked) {
        border-color: var(--el-primary);
        background: var(--el-primary-soft);
        box-shadow: inset 4px 0 0 var(--el-primary);
    }
    .options-container .form-check-label {
        display: block;
        width: 100%;
        cursor: pointer;
        color: var(--el-heading);
    }
    .options-container .form-check-input:checked {
        background-color: var(--el-primary);
        border-color: var(--el-primary);
    }
    .question-body-content {
        background: color-mix(in srgb, var(--el-primary) 3%, var(--el-surface, #fff));
    }
    .question-body-content img,
    .question-box img,
    .options-container img,
    .passage-box img,
    .hint-box img,
    .explanation-box img {
        display: block;
        max-width: 100% !important;
        width: auto !important;
        height: auto !important;
        object-fit: contain;
        margin: 10px auto;
    }
    .question-box {
        background: var(--el-surface);
        border: 1px solid var(--el-border);
        border-radius: 10px;
        color: var(--el-heading);
        padding: 14px;
    }
    .exam-nav-footer {
        background: var(--el-surface);
        border-top: 1px solid var(--el-border);
    }
    .exam-nav-primary,
    .exam-nav-primary:hover,
    .exam-nav-primary:focus {
        background: var(--el-primary);
        border-color: var(--el-primary);
        color: var(--theme-button-text, #fff);
        font-weight: 800;
    }
    .bookmark-button,
    .bookmark-button:hover,
    .bookmark-button:focus {
        background: var(--el-primary-soft);
        border-color: color-mix(in srgb, var(--el-primary) 32%, var(--el-border));
        color: var(--el-primary);
        font-weight: 700;
    }
    .review-button,
    .review-button:hover,
    .review-button:focus {
        background: var(--el-secondary-soft);
        border-color: color-mix(in srgb, var(--el-secondary) 35%, var(--el-border));
        color: var(--el-secondary);
        font-weight: 700;
    }
    .clear-button:not(.report-btn),
    .clear-button:not(.report-btn):hover,
    .clear-button:not(.report-btn):focus {
        background: var(--el-surface);
        border-color: var(--el-border);
        color: var(--el-heading);
        font-weight: 700;
    }
    .answer-lock-notice {
        margin: 12px 16px 0;
        padding: 10px 12px;
        border: 1px solid color-mix(in srgb, var(--el-secondary) 40%, var(--el-border));
        border-radius: 10px;
        background: var(--el-secondary-soft);
        color: var(--el-heading);
        font-weight: 700;
    }
    .answer-lock-notice i { color: var(--el-secondary); margin-right: 5px; }
    .question[data-answer-locked="1"] .options-container { opacity: .78; }
    .exam-action-danger {
        background: color-mix(in srgb, #ef4444 10%, #fff);
        border-color: color-mix(in srgb, #ef4444 42%, #fff);
        color: #b91c1c;
    }
    .exam-action-danger:hover,
    .exam-action-danger:focus {
        background: #ef4444;
        border-color: #ef4444;
        color: #fff;
    }
    @media (max-width: 767.98px) {
        .exam-question-row-head {
            align-items: stretch !important;
            flex-direction: column;
            gap: 10px;
            position: static !important;
        }
        .exam-question-row-head > .d-flex {
            align-items: stretch !important;
            flex-wrap: wrap;
            gap: 8px;
            width: 100%;
        }
        .exam-question-row-head .me-2.d-flex {
            flex: 1 1 100%;
            margin-right: 0 !important;
        }
        .exam-question-row-head .view-in-select {
            min-width: 150px;
        }
        .exam-question-row-head .badge {
            align-items: center;
            display: inline-flex;
            min-height: 32px;
        }
        .exam-question-row-head .bookmark-button,
        .exam-question-row-head .review-button,
        .exam-question-row-head .clear-button,
        .exam-question-row-head .report-btn {
            flex: 1 1 calc(50% - 8px);
            margin-right: 0 !important;
            min-height: 36px;
            white-space: nowrap;
        }
    }
</style>

<div class="col-md-8 d-flex flex-column pe-md-2"> 

    {{-- Header for Exam Name and Subject Buttons --}}
    <div class="exam-question-shell mb-2" style="flex-shrink: 0;">
        <div class="p-3">
             <h2 class="h5 mb-0">{{ $exam->name }}</h2>
        </div>
         
         <div class="exam-question-toolbar p-2 d-flex justify-content-between align-items-center">
            <div class="flex-grow-1"> 
                @if ($exam->questions && $exam->questions->isNotEmpty())
                    @foreach ($exam->questions->unique('exam_group_key') as $groupQuestion)
                        <button type="button"
                            class="btn btn-sm d-inline-flex align-items-center justify-content-center subject-btn {{ $loop->first ? 'btn-primary' : 'btn-outline-primary' }}"
                            data-subject-id="{{ $groupQuestion->exam_group_key }}" style="margin-right: 5px; margin-bottom: 5px;">
                            {{ $groupQuestion->exam_group_name }}
                        </button>
                    @endforeach
                @else
                    <p class="mb-0 text-muted small">@lang('messages.exam_subject_none')</p>
                @endif
            </div>

            <div class="btn-group btn-group-sm ms-2" role="group" aria-label="Tools" style="flex-shrink: 0;">
                {{-- ✅ CALCULATOR BUTTON (Only if allowed) --}}
                @if($exam->calculator_allowed)
                    <button type="button" class="btn exam-tool-btn" id="btn-calculator" title="Open Scientific Calculator" style="padding: 0.1rem 0.5rem; margin-right: 5px;">
                        <i class="mdi mdi-calculator"></i> Calc
                    </button>
                @endif

                <button type="button" class="btn exam-tool-btn" id="font-decrease-btn" title="{{ __('ui.decrease_font') }}" style="padding: 0.1rem 0.5rem;">A-</button>
                <button type="button" class="btn exam-tool-btn" id="font-increase-btn" title="{{ __('ui.increase_font') }}" style="padding: 0.1rem 0.5rem;">A+</button>
            </div>
        </div>
    </div>

    {{-- Question Display Area --}}
    <div id="question-container" class="exam-question-shell flex-grow-1" data-exam-result-id="{{ $examResult->id ?? '' }}" style="overflow-y: auto; position: relative;">
        
        {{-- ✅✅✅ START: NUMBERING FIX LOGIC ✅✅✅ --}}
        @php
            $subjectQuestionNumbers = [];
            $subjectCounters = [];
            foreach ($exam->questions as $index => $question) {
                $sId = $question->exam_group_key ?? 'all';
                if (!isset($subjectCounters[$sId])) {
                    $subjectCounters[$sId] = 0;
                }
                $subjectCounters[$sId]++;
                $subjectQuestionNumbers[$index] = $subjectCounters[$sId];
            }
        @endphp
        {{-- ✅✅✅ END LOGIC ✅✅✅ --}}

        @forelse($exam->questions as $index => $question)
            @php
                $currentLanguageId = (int) $selectedLanguageId;
                $passage = $question->passage;
                // Passages assess source-language knowledge and intentionally never follow the UI language.
                $passageLang = $passage
                    ? ($passage->langs()->where('language_id', (int) $question->language_id)->first() ?? $passage->langs()->first())
                    : null;
                $passageContent = $passageLang?->passage;
                $passageName = $passage?->name;
                $questionLang = $question->langs->firstWhere('language_id', $currentLanguageId);

                $repairMath = app(\App\Services\MathContentNormalizer::class);
                $displayQuestion = $repairMath->repairForDisplay($questionLang?->question ?? $question->question);
                $displayHint = $repairMath->repairForDisplay($questionLang?->hint ?? $question->hint);
                $displayOption1 = $repairMath->repairForDisplay($questionLang?->option1 ?? $question->option1);
                $displayOption2 = $repairMath->repairForDisplay($questionLang?->option2 ?? $question->option2);
                $displayOption3 = $repairMath->repairForDisplay($questionLang?->option3 ?? $question->option3);
                $displayOption4 = $repairMath->repairForDisplay($questionLang?->option4 ?? $question->option4);
                $displayOption5 = $repairMath->repairForDisplay($questionLang?->option5 ?? $question->option5);
                $displayOption6 = $repairMath->repairForDisplay($questionLang?->option6 ?? $question->option6);
                
                 $questionType = $question->question_type ?? 'text';
            @endphp

            <div class="question" data-index="{{ $index }}"
                data-subject-id="{{ $question->exam_group_key ?? 'all' }}" data-academic-subject-id="{{ $question->subject_id }}" data-question-id="{{ $question->id }}"
                data-question-type="{{ $questionType }}" data-answer-locked="{{ ($question->answer_locked ?? false) ? '1' : '0' }}" style="display: none; {{ !$loop->last ? 'border-bottom: 1px solid var(--el-border);' : '' }}">
                <div class="answer-lock-notice" hidden><i class="mdi mdi-lock-outline"></i> {{ __('ui.answer_locked') }}</div>

                {{-- Question Header --}}
                <div class="p-3 d-flex justify-content-between align-items-center exam-question-row-head sticky-top" style="top: 0; z-index: 10;">
                    <h5 class="mb-0 fs-6 fw-semibold">
                        {{-- ✅ FIX: Using the new counter array --}}
                        @lang('messages.exam_question_no') {{ $subjectQuestionNumbers[$index] }}
                    </h5>
                    <div class="d-flex align-items-center">
                         <span class="badge exam-meta-badge-primary me-1">{{ $question->marks ?? 'N/A' }}</span>
                         @if ($exam->negative_marking && isset($question->negative_marks) && $question->negative_marks > 0)
                            <span class="badge exam-meta-badge-secondary me-2">-{{ number_format($question->negative_marks, 2) }}</span>
                         @else
                             <span class="badge exam-meta-badge-soft me-2">0.00</span>
                         @endif
                         
                         @php
                            $isBookmarked = $question->prefilled_bookmark ?? false;
                         @endphp
                         <button class="btn btn-sm {{ $isBookmarked ? 'btn-info' : 'btn-outline-info' }} bookmark-button me-1 px-2 py-1" 
                                 data-index="{{ $index }}" 
                                 title="{{ $isBookmarked ? __('messages.exam_unbookmark_button_title') : __('messages.exam_bookmark_button_title') }}">
                            <i class="mdi mdi-bookmark"></i> @lang('messages.exam_bookmark_button')
                         </button>

                         <button class="btn btn-sm btn-outline-warning review-button me-1 px-2 py-1" data-index="{{ $index }}" title="@lang('messages.exam_review_button_title')">
                            <i class="mdi mdi-flag"></i> @lang('messages.exam_review_button')
                         </button>
                         <button class="btn btn-sm btn-outline-secondary clear-button me-1 px-2 py-1" data-index="{{ $index }}" title="@lang('messages.exam_clear_button_title')">
                            <i class="mdi mdi-recycle"></i> @lang('messages.exam_clear_button')
                         </button>

                         <button class="btn btn-sm exam-action-danger clear-button px-2 py-1 report-btn" data-bs-toggle="modal" data-bs-target="#reportModal">
                            <i class="mdi mdi-alert-circle-outline"></i> @lang('messages.exam_sidebar_btn_report')
                        </button>
                    </div>
                </div>

                {{-- Question Body --}}
                <div class="p-3 question-body-content">
                    {{-- Passage Box --}}
                    @if ($passageContent)
                        <div id="passageBox_{{ $index }}" class="mb-3 border rounded p-2 passage-box exam-passage-box" style="max-height:150px; overflow-y: auto; font-size: 0.9em;">
                            @if($passageName)<p class="fw-semibold text-center small mb-1">{!! $passageName !!}</p>@endif
                            {!! $passageContent !!}
                        </div>
                    @else
                        <div id="passageBox_{{ $index }}" class="passage-box" style="display: none;"></div>
                    @endif

                    {{-- Question Box --}}
                    <div id="questionBox_{{ $index }}" class="mb-3 question-text question-box">
                        {!! $displayQuestion !!}
                    </div>

                    {{-- Hint Box --}}
                     @if ($displayHint)
                        <div id="hintBox_{{ $index }}" class="text-muted small mb-3 hint-box">
                            <em><strong>@lang('messages.exam_hint_prefix')</strong> {!! $displayHint !!}</em>
                        </div>
                    @else
                        <div id="hintBox_{{ $index }}" class="text-muted small mb-3 hint-box" style="display: none;"></div>
                    @endif
                    
                    <div id="explanationBox_{{ $index }}" class="text-muted small mb-3 explanation-box exam-explanation-box" style="font-size: 0.9em; padding: 10px; border-radius: 5px; display: none;">
                    </div>

                    {{-- Answer Options --}}
                    <div class="options-container">
                        @if ($questionType == 'true_false')
                            <div class="form-check mb-2">
                                <input class="form-check-input answer-input" type="radio" name="answer_{{ $index }}" id="q_{{ $index }}_true" value="true"
                                    {{ ($question->prefilled_answer ?? null) === 'true' ? 'checked' : '' }}>
                                <label class="form-check-label" for="q_{{ $index }}_true">@lang('messages.exam_option_true')</label>
                            </div>
                            <div class="form-check mb-2">
                                <input class="form-check-input answer-input" type="radio" name="answer_{{ $index }}" id="q_{{ $index }}_false" value="false"
                                    {{ ($question->prefilled_answer ?? null) === 'false' ? 'checked' : '' }}>
                                <label class="form-check-label" for="q_{{ $index }}_false">@lang('messages.exam_option_false')</label>
                            </div>
                        @elseif($questionType == 'multiple_choice_checkbox' || $questionType == 'multiple_choice_radio')
                            @php
                                $options = collect([
                                    ['index' => 1, 'content' => $displayOption1], ['index' => 2, 'content' => $displayOption2],
                                    ['index' => 3, 'content' => $displayOption3], ['index' => 4, 'content' => $displayOption4],
                                    ['index' => 5, 'content' => $displayOption5], ['index' => 6, 'content' => $displayOption6],
                                ])->filter(fn ($item) => $item['content'] !== null && $item['content'] !== '');

                                if ($exam->option_shuffle) {
                                    $options = $options->shuffle();
                                }
                                $inputType = $questionType == 'multiple_choice_checkbox' ? 'checkbox' : 'radio';
                                $inputName = $inputType == 'checkbox' ? "answer_{$index}[]" : "answer_{$index}";
                                $prefilled = is_array($question->prefilled_answer) ? $question->prefilled_answer : (($question->prefilled_answer !== null) ? [$question->prefilled_answer] : []);
                            @endphp
                            @forelse ($options as $displayIndex => $option)
                                <div class="form-check mb-2">
                                    <input class="form-check-input answer-input" type="{{ $inputType }}" name="{{ $inputName }}" id="q_{{ $index }}_opt_{{ $option['index'] }}" value="{{ $option['index'] }}"
                                        {{ in_array((int) $option['index'], array_map('intval', $prefilled), true) ? 'checked' : '' }}>
                                    <label class="form-check-label" for="q_{{ $index }}_opt_{{ $option['index'] }}">
                                        {!! $option['content'] !!}
                                    </label>
                                </div>
                            @empty
                                <p class="text-danger small">@lang('messages.exam_no_options_error')</p>
                            @endforelse
                        @elseif($questionType === 'fill_blank')
                            @php $blankAnswers = is_array($question->prefilled_answer ?? null) ? $question->prefilled_answer : []; @endphp
                            <div class="row g-3">
                                @for($blankIndex = 0; $blankIndex < max(1, (int) ($question->fill_blank_count ?? 1)); $blankIndex++)
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold">Blank {{ $blankIndex + 1 }}</label>
                                        <input type="text" class="form-control answer-input fill-blank-input" name="answer_{{ $index }}[]" value="{{ data_get($blankAnswers, $blankIndex, '') }}" autocomplete="off" placeholder="Type your answer here">
                                    </div>
                                @endfor
                            </div>
                        @elseif($questionType === 'nat')
                            <label class="form-label fw-semibold">{{ __('ui.numerical_answer') }}</label>
                            <input type="number" step="any" inputmode="decimal" class="form-control answer-input nat-answer-input" name="answer_{{ $index }}" value="{{ $question->prefilled_answer ?? '' }}" autocomplete="off" placeholder="Enter a numerical value">
                        @else
                            <textarea class="form-control answer-input" name="answer_{{ $index }}" rows="4" placeholder="@lang('messages.exam_text_placeholder')">{{ $question->prefilled_answer ?? '' }}</textarea>
                        @endif
                    </div>
                </div>
            </div>
        @empty
            <div class="p-5 text-center text-muted">
                <p>@lang('messages.exam_no_questions_loaded')</p>
            </div>
        @endforelse
    </div>

    {{-- Navigation Buttons - Now fixed at the bottom --}}
    <div class="d-flex justify-content-between p-2 exam-nav-footer" style="flex-shrink: 0; margin-top: auto;">
        <button class="btn exam-nav-primary w-50 me-2" id="prevButton" disabled>
            <i class="mdi mdi-arrow-left"></i> @lang('messages.exam_prev_button')
        </button>
        <button class="btn exam-nav-primary w-50 ms-2" id="nextButton">
            @lang('messages.exam_next_button') <i class="mdi mdi-arrow-right"></i>
        </button>
    </div>
</div>

{{-- ✅ ADDED: Calculator Modal --}}
@if($exam->calculator_allowed)
<div id="scientific-calculator" class="calc-modal" style="display: none;">
    <div class="calc-header">
        <span>{{ __('ui.scientific_calculator') }}</span>
        <button type="button" class="calc-close" onclick="toggleCalculator()">×</button>
    </div>
    <div class="calc-body">
        <input type="text" id="calc-display" readonly>
        <div class="calc-keys">
            <button onclick="calcCmd('sin')">sin</button> <button onclick="calcCmd('cos')">cos</button> <button onclick="calcCmd('tan')">tan</button> <button onclick="calcCmd('C')" class="calc-danger">C</button>
            <button onclick="calcCmd('log')">log</button> <button onclick="calcCmd('ln')">ln</button> <button onclick="calcCmd('sqrt')">√</button> <button onclick="calcCmd('/')">÷</button>
            <button onclick="calcCmd('7')">7</button> <button onclick="calcCmd('8')">8</button> <button onclick="calcCmd('9')">9</button> <button onclick="calcCmd('*')">×</button>
            <button onclick="calcCmd('4')">4</button> <button onclick="calcCmd('5')">5</button> <button onclick="calcCmd('6')">6</button> <button onclick="calcCmd('-')">-</button>
            <button onclick="calcCmd('1')">1</button> <button onclick="calcCmd('2')">2</button> <button onclick="calcCmd('3')">3</button> <button onclick="calcCmd('+')">+</button>
            <button onclick="calcCmd('(')">(</button> <button onclick="calcCmd('0')">0</button> <button onclick="calcCmd(')')">)</button> <button onclick="calcResult()" class="calc-primary">=</button>
        </div>
    </div>
</div>

<style>
    /* Calculator Styles */
    .calc-modal {
        position: fixed; top: 100px; right: 20px; width: 280px;
        background: var(--el-surface, #fff); border: 1px solid var(--el-border); border-radius: 12px;
        box-shadow: 0 16px 35px rgba(15, 23, 42, .18); z-index: 9999;
        font-family: sans-serif;
    }
    .calc-header {
        background: var(--el-primary); color: var(--theme-button-text, #fff); padding: 8px 12px;
        border-radius: 12px 12px 0 0; display: flex; justify-content: space-between; cursor: move;
    }
    .calc-close { background: none; border: none; color: #fff; font-size: 20px; cursor: pointer; }
    .calc-body { padding: 10px; background: var(--el-surface-muted); border-radius: 0 0 12px 12px; }
    #calc-display {
        width: 100%; height: 40px; margin-bottom: 10px; font-size: 18px;
        text-align: right; padding: 5px; border: 1px solid var(--el-border);
        border-radius: 8px;
    }
    .calc-keys { display: grid; grid-template-columns: repeat(4, 1fr); gap: 5px; }
    .calc-keys button {
        padding: 10px; font-size: 14px; border: 1px solid var(--el-border); background: var(--el-surface);
        cursor: pointer; border-radius: 4px;
    }
    .calc-keys button:hover { background: var(--el-primary-soft); color: var(--el-primary); }
    .calc-danger { background: color-mix(in srgb, #ef4444 14%, #fff) !important; color: #b91c1c; }
    .calc-primary { background: var(--el-primary) !important; color: var(--theme-button-text, #fff); }
</style>
@endif


<!-- Subjective answer OCR/upload: augments the original editable textarea. -->
<style>
.upload-single-wrap {
    position: relative;
    width: 100%;
}
.upload-single-wrap textarea {
    width: 100%;
    padding-bottom: 52px;
    padding-right: 170px;
}
.upload-single-btn {
    position: absolute;
    right: 12px;
    bottom: 10px;
    z-index: 10;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 7px 16px;
    border: 0;
    border-radius: 24px;
    color: #fff;
    background: var(--el-primary);
    font-size: 13px;
    cursor: pointer;
}
.upload-single-btn:hover { filter: brightness(.92); }
.upload-single-btn:disabled { cursor: not-allowed; opacity: .55; }
.upload-single-status {
    position: absolute;
    left: 14px;
    bottom: 13px;
    z-index: 10;
    max-width: calc(100% - 190px);
    padding: 2px 10px;
    border-radius: 14px;
    background: rgba(255,255,255,.94);
    font-size: 12px;
}
.upload-single-status.success,
.upload-single-status.uploading { color: var(--el-primary); background: var(--el-primary-soft); }
.upload-single-status.error { color: #b42318; background: #fef3f2; }
@media (max-width: 575.98px) {
    .upload-single-wrap textarea { padding-right: 12px; padding-bottom: 86px; }
    .upload-single-btn { left: 12px; right: auto; }
    .upload-single-status { bottom: 48px; max-width: calc(100% - 28px); }
}
</style>

<script>
(function () {
    function setStatus(status, message, state) {
        status.textContent = message || '';
        status.className = 'upload-single-status' + (state ? ' ' + state : '');
    }

    function clearStatusLater(status, delay) {
        window.setTimeout(function () { setStatus(status, '', ''); }, delay || 3500);
    }

    window.setTimeout(function () {
        if (!@json(\App\Support\SaasAccess::featureEnabled('ai_subjective_analysis'))) {
            return;
        }

        var extractUrl = @json(asset('extract_file.php'));
        var uploadUrl = @json(route('student.subjective-upload'));
        var csrfToken = @json(csrf_token());
        var examResultElement = document.querySelector('[data-exam-result-id]');
        var examResultId = examResultElement ? examResultElement.getAttribute('data-exam-result-id') : '';

        document.querySelectorAll('.question[data-question-type="subjective"] textarea.answer-input').forEach(function (textarea) {
            if (textarea.closest('.upload-single-wrap')) return;
            if (textarea.closest('.question')?.dataset.answerLocked === '1') return;

            var question = textarea.closest('[data-question-id]');
            var questionId = question ? question.getAttribute('data-question-id') : '';
            var wrapper = document.createElement('div');
            var status = document.createElement('span');
            var button = document.createElement('button');

            wrapper.className = 'upload-single-wrap';
            status.className = 'upload-single-status';
            button.type = 'button';
            button.className = 'upload-single-btn';
            button.innerHTML = '<i class="ri-upload-2-line" aria-hidden="true"></i><span>{{ __('ui.upload_answer') }}</span>';

            textarea.parentNode.insertBefore(wrapper, textarea);
            wrapper.appendChild(textarea);
            wrapper.appendChild(status);
            wrapper.appendChild(button);

            button.addEventListener('click', function () {
                var input = document.createElement('input');
                input.type = 'file';
                input.accept = '.jpg,.jpeg,.png,.pdf,.doc,.docx,.txt';
                if (/Mobi|Android|iPhone|iPad/i.test(navigator.userAgent)) {
                    input.setAttribute('capture', 'environment');
                }

                input.addEventListener('change', function () {
                    var file = input.files && input.files[0];
                    if (!file) return;

                    button.disabled = true;
                    setStatus(status, 'Extracting text...', 'uploading');

                    var extractData = new FormData();
                    extractData.append('file', file);

                    fetch(extractUrl, { method: 'POST', body: extractData })
                        .then(function (response) { return response.json(); })
                        .then(function (result) {
                            if (!result.success || !result.text) {
                                throw new Error(result.error || 'Text extraction failed.');
                            }

                            textarea.value = textarea.value
                                ? textarea.value + '\n\n' + result.text
                                : result.text;
                            textarea.dispatchEvent(new Event('input', { bubbles: true }));
                            textarea.dispatchEvent(new Event('change', { bubbles: true }));

                            var uploadData = new FormData();
                            uploadData.append('answer_file', file);
                            uploadData.append('question_id', questionId);
                            uploadData.append('exam_result_id', examResultId);
                            uploadData.append('_token', csrfToken);

                            return fetch(uploadUrl, { method: 'POST', body: uploadData })
                                .then(function (response) { return response.json(); })
                                .then(function (uploadResult) {
                                    if (!uploadResult.success) {
                                        throw new Error(uploadResult.message || 'Answer upload failed.');
                                    }
                                    setStatus(status, 'Text extracted and answer uploaded.', 'success');
                                    button.querySelector('span').textContent = 'Upload more';
                                    clearStatusLater(status, 4500);
                                });
                        })
                        .catch(function (error) {
                            setStatus(status, error.message || 'Unable to process this file.', 'error');
                            clearStatusLater(status);
                        })
                        .finally(function () {
                            button.disabled = false;
                        });
                });

                input.click();
            });
        });
    }, 500);
})();
</script>

{{-- ✅✅✅ MATHS FIX: Using SVG Engine to fix hidden element rendering ✅✅✅ --}}
<script>
    window.MathJax = { tex: { inlineMath: [['$', '$'], ['\\(', '\\)']] }, svg: { fontCache: 'global' } };
</script>
<script id="MathJax-script" async src="https://cdn.jsdelivr.net/npm/mathjax@3/es5/tex-mml-svg.js"></script>

{{-- ✅ MODIFIED: Removed border classes from main div, added flex structure --}}
<div class="col-md-8 d-flex flex-column pe-md-2"> 

    {{-- Header for Exam Name and Subject Buttons --}}
    <div class="border border-dark mb-2" style="background-color: lightblue; flex-shrink: 0;"> 
        <div class="p-3">
             <h2 class="h5 mb-0">{{ $exam->name }}</h2>
        </div>
         
         <div class="border-top border-dark p-2 d-flex justify-content-between align-items-center">
            <div class="flex-grow-1"> 
                @if ($exam->questions && $exam->questions->isNotEmpty())
                    @foreach ($exam->questions->pluck('subject')->unique()->filter() as $subject)
                        <span
                            class="btn btn-sm d-inline-flex align-items-center justify-content-center subject-btn {{ $loop->first ? 'btn-primary' : 'btn-outline-primary' }}"
                            data-subject-id="{{ $subject->id }}" style="margin-right: 5px; margin-bottom: 5px;"> 
                            {{ $subject->subject_name }}
                        </span>
                    @endforeach
                @else
                    <p class="mb-0 text-muted small">@lang('messages.exam_subject_none')</p>
                @endif
            </div>

            <div class="btn-group btn-group-sm ms-2" role="group" aria-label="Tools" style="flex-shrink: 0;">
                {{-- ✅ CALCULATOR BUTTON (Only if allowed) --}}
                @if($exam->calculator_allowed)
                    <button type="button" class="btn btn-outline-dark" id="btn-calculator" title="Open Scientific Calculator" style="font-weight: bold; padding: 0.1rem 0.5rem; margin-right: 5px;">
                        <i class="mdi mdi-calculator"></i> Calc
                    </button>
                @endif

                <button type="button" class="btn btn-outline-dark" id="font-decrease-btn" title="Decrease Font Size" style="font-weight: bold; padding: 0.1rem 0.5rem;">A-</button>
                <button type="button" class="btn btn-outline-dark" id="font-increase-btn" title="Increase Font Size" style="font-weight: bold; padding: 0.1rem 0.5rem;">A+</button>
            </div>
        </div>
    </div>

    {{-- Question Display Area --}}
    <div id="question-container" class="border border-dark flex-grow-1" style="overflow-y: auto; position: relative;">
        
        {{-- ✅✅✅ START: NUMBERING FIX LOGIC ✅✅✅ --}}
        @php
            $subjectQuestionNumbers = [];
            $subjectCounters = [];
            foreach ($exam->questions as $index => $question) {
                $sId = $question->subject_id ?? 'default';
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
                $currentLanguageId = session('locale_id', 1);
                $passage = $question->passage; 
                $passageLang = null;
                if ($passage) { 
                    $passageLang = $passage->langs()->where('language_id', $currentLanguageId)->first() 
                                                   ?? $passage->langs()->first(); 
                }
                $passageContent = $passageLang?->passage;
                $passageName = $passage?->name; 

                $questionLang = $question->langs()->where('language_id', $currentLanguageId)->first();

                $displayQuestion = $questionLang?->question ?? $question->question;
                $displayHint = $questionLang?->hint ?? $question->hint;
                $displayOption1 = $questionLang?->option1 ?? $question->option1;
                $displayOption2 = $questionLang?->option2 ?? $question->option2;
                $displayOption3 = $questionLang?->option3 ?? $question->option3;
                $displayOption4 = $questionLang?->option4 ?? $question->option4;
                $displayOption5 = $questionLang?->option5 ?? $question->option5;
                $displayOption6 = $questionLang?->option6 ?? $question->option6;
                
                 $questionType = $question->question_type ?? 'text';
            @endphp

            <div class="question" data-index="{{ $index }}"
                data-subject-id="{{ $question->subject_id ?? 'unknown' }}" data-question-id="{{ $question->id }}"
                data-question-type="{{ $questionType }}" style="display: none; {{ !$loop->last ? 'border-bottom: 1px solid #dee2e6;' : '' }}"> 

                {{-- Question Header --}}
                <div class="p-3 d-flex justify-content-between align-items-center bg-light sticky-top border-bottom" style="top: 0; z-index: 10;">
                    <h5 class="mb-0 fs-6 fw-semibold">
                        {{-- ✅ FIX: Using the new counter array --}}
                        @lang('messages.exam_question_no') {{ $subjectQuestionNumbers[$index] }}
                    </h5>
                    <div class="d-flex align-items-center">
                         <div class="me-2 d-flex align-items-center">
                            <label for="viewInSelect_{{ $index }}" class="me-1 small text-nowrap mb-0">@lang('messages.exam_view_in')</label>
                            <select class="form-select form-select-sm view-in-select" name="language_id" id="viewInSelect_{{ $index }}">
                                @foreach ($languages as $language)
                                    <option value="{{ $language->id }}" data-question="{{ $question->id }}" {{ $currentLanguageId == $language->id ? 'selected' : '' }}>
                                        {{ $language->name }}</option>
                                @endforeach
                            </select>
                        </div>
                         <span class="badge bg-success me-1">{{ $question->marks ?? 'N/A' }}</span>
                         @if ($exam->negative_marking && isset($question->negative_marks) && $question->negative_marks > 0)
                            <span class="badge bg-danger me-2">-{{ number_format($question->negative_marks, 2) }}</span>
                         @else
                             <span class="badge bg-secondary me-2">0.00</span>
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
                         <button class="btn btn-sm btn-outline-secondary clear-button px-2 py-1" data-index="{{ $index }}" title="@lang('messages.exam_clear_button_title')">
                            <i class="mdi mdi-recycle"></i> @lang('messages.exam_clear_button')
                         </button>
                    </div>
                </div>

                {{-- Question Body --}}
                <div class="p-3 question-body-content">
                    {{-- Passage Box --}}
                    @if ($passageContent)
                        <div id="passageBox_{{ $index }}" class="mb-3 border rounded p-2 passage-box" style="max-height:150px; overflow-y: auto; background-color: #f8f9fa; font-size: 0.9em;">
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
                    
                    <div id="explanationBox_{{ $index }}" class="text-muted small mb-3 explanation-box" style="font-size: 0.9em; background: #fdfbe9; border: 1px solid #fff5c2; padding: 10px; border-radius: 5px; display: none;">
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
                                    $displayOption1, $displayOption2, $displayOption3,
                                    $displayOption4, $displayOption5, $displayOption6,
                                ])->filter(function($value) {
                                    return $value !== null && $value !== '';
                                });

                                if ($exam->option_shuffle) {
                                    $options = $options->shuffle();
                                }
                                $inputType = $questionType == 'multiple_choice_checkbox' ? 'checkbox' : 'radio';
                                $inputName = $inputType == 'checkbox' ? "answer_{$index}[]" : "answer_{$index}";
                                $prefilled = is_array($question->prefilled_answer) ? $question->prefilled_answer : (($question->prefilled_answer !== null) ? [$question->prefilled_answer] : []);
                            @endphp
                            @forelse ($options as $optIndex => $option)
                                <div class="form-check mb-2">
                                    <input class="form-check-input answer-input" type="{{ $inputType }}" name="{{ $inputName }}" id="q_{{ $index }}_opt_{{ $optIndex }}" value="{{ $option }}"
                                        {{ in_array($option, $prefilled) ? 'checked' : '' }}>
                                    <label class="form-check-label" for="q_{{ $index }}_opt_{{ $optIndex }}">
                                        {!! $option !!}
                                    </label>
                                </div>
                            @empty
                                <p class="text-danger small">@lang('messages.exam_no_options_error')</p>
                            @endforelse
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
    <div class="d-flex justify-content-between p-2 border-top bg-light" style="flex-shrink: 0; margin-top: auto;">
        <button class="btn btn-primary w-50 me-2" id="prevButton" disabled>
            <i class="mdi mdi-arrow-left"></i> @lang('messages.exam_prev_button')
        </button>
        <button class="btn btn-primary w-50 ms-2" id="nextButton">
            @lang('messages.exam_next_button') <i class="mdi mdi-arrow-right"></i>
        </button>
    </div>
</div>

{{-- ✅ ADDED: Calculator Modal --}}
@if($exam->calculator_allowed)
<div id="scientific-calculator" class="calc-modal" style="display: none;">
    <div class="calc-header">
        <span>Scientific Calculator</span>
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
        background: #fff; border: 1px solid #ccc; border-radius: 8px;
        box-shadow: 0 4px 15px rgba(0,0,0,0.2); z-index: 9999;
        font-family: sans-serif;
    }
    .calc-header {
        background: #333; color: #fff; padding: 8px 12px;
        border-radius: 8px 8px 0 0; display: flex; justify-content: space-between; cursor: move;
    }
    .calc-close { background: none; border: none; color: #fff; font-size: 20px; cursor: pointer; }
    .calc-body { padding: 10px; background: #f1f1f1; border-radius: 0 0 8px 8px; }
    #calc-display {
        width: 100%; height: 40px; margin-bottom: 10px; font-size: 18px;
        text-align: right; padding: 5px; border: 1px solid #ddd;
    }
    .calc-keys { display: grid; grid-template-columns: repeat(4, 1fr); gap: 5px; }
    .calc-keys button {
        padding: 10px; font-size: 14px; border: 1px solid #ccc; background: #fff;
        cursor: pointer; border-radius: 4px;
    }
    .calc-keys button:hover { background: #e0e0e0; }
    .calc-danger { background: #ffcccc !important; }
    .calc-primary { background: #4f46e5 !important; color: white; }
</style>
@endif
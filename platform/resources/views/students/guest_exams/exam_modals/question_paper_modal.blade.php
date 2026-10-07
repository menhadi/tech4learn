{{-- resources/views/students/exams/partials/question_paper_modal.blade.php --}}

<div class="modal fade" id="questionPaperModal" tabindex="-1" aria-labelledby="questionPaperModalLabel"
    aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable"> {{-- Added modal-dialog-scrollable --}}
        <div class="modal-content exam-theme-modal">
            <div class="modal-header">
                <h5 class="modal-title" id="questionPaperModalLabel">@lang('messages.exam_modal_q_paper_title')</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('messages.my_exams_modal_close_button') }}"></button>
            </div>
            <div class="modal-body">
                
                {{-- ✅✅✅ START: FULL LOGIC FIX ✅✅✅ --}}
                @php
                    // Get current language ID from session, default to 1 (English)
                    $currentLanguageId = session('locale_id', 1);
                @endphp

                @foreach($exam->questions as $index => $question)
                    @php
                        // --- Replicate the language logic from questions.blade.php ---

                        // --- Passage Handling ---
                        $passage = $question->passage;
                        $passageLang = null;
                        if ($passage) {
                            // ✅ FIX: Use 'langs' relationship from Question model
                            $passageLang = $passage->langs()->where('language_id', $currentLanguageId)->first()
                                           ?? $passage->langs()->first();
                        }
                        $passageContent = $passageLang?->passage;
                        $passageName = $passage?->name;

                        // --- Question Language Handling ---
                        // ✅ FIX: Use 'langs' relationship from Question model
                        $questionLang = $question->langs()->where('language_id', $currentLanguageId)->first();

                        // --- Fallback Logic ---
                        $displayQuestion = $questionLang?->question ?? $question->question;
                        $displayHint = $questionLang?->hint ?? $question->hint;
                        $displayOption1 = $questionLang?->option1 ?? $question->option1;
                        $displayOption2 = $questionLang?->option2 ?? $question->option2;
                        $displayOption3 = $questionLang?->option3 ?? $question->option3;
                        $displayOption4 = $questionLang?->option4 ?? $question->option4;
                        $displayOption5 = $questionLang?->option5 ?? $question->option5;
                        $displayOption6 = $questionLang?->option6 ?? $question->option6;
                        
                        // Get the type set by the controller
                        $questionType = $question->question_type ?? 'text'; 
                    @endphp

                    <div class="mb-3 border-bottom pb-3"> {{-- Added border-bottom for separation --}}
                        <strong>@lang('messages.exam_modal_q_paper_q_prefix') {{ $index + 1 }}:</strong>
                        <div class="p-3">
                            {{-- Passage (if any) --}}
                            @if ($passageContent)
                                <div class="mb-3 border rounded p-2" style="max-height:150px; overflow-y: auto; background-color: var(--el-primary-soft); font-size: 0.9em;">
                                    @if($passageName)<p class="fw-semibold text-center small mb-1">{!! $passageName !!}</p>@endif
                                    {!! $passageContent !!}
                                </div>
                            @endif

                            {{-- Question Text --}}
                            <p>{!! strip_tags($displayQuestion) !!}</p>
                            @if($displayHint)
                            <p><strong>@lang('messages.exam_modal_q_paper_hint')</strong> {!! $displayHint !!}</p>
                            @endif
                            <hr>
                            
                            {{-- Answer Section --}}
                            @if($questionType == 'true_false')
                            <div>
                                <input type="radio" name="modal_answer_{{ $index }}" value="true" disabled> @lang('messages.exam_modal_q_paper_true')
                                <input type="radio" name="modal_answer_{{ $index }}" value="false" disabled> @lang('messages.exam_modal_q_paper_false')
                            </div>
                            
                            {{-- ✅ FIX: Changed check from 'multiple_choice' to the two specific types --}}
                            @elseif($questionType == 'multiple_choice_radio' || $questionType == 'multiple_choice_checkbox')
                            @php
                            $options = collect([
                                $displayOption1, $displayOption2, $displayOption3,
                                $displayOption4, $displayOption5, $displayOption6
                            ])->filter(function($value) {
                                return $value !== null && $value !== '';
                            });
                            @endphp
                            
                            {{-- ✅ FIX: Check type again to decide radio/checkbox --}}
                            @if($questionType == 'multiple_choice_checkbox')
                                @foreach($options as $option)
                                <div>
                                    <input type="checkbox" name="modal_answer_{{ $index }}[]" value="{{ $option }}" disabled> {!! $option !!}
                                </div>
                                @endforeach
                            @else {{-- Must be multiple_choice_radio --}}
                                @foreach($options as $option)
                                <div>
                                    <input type="radio" name="modal_answer_{{ $index }}" value="{{ $option }}" disabled> {!! $option !!}
                                </div>
                                @endforeach
                            @endif

                            @else {{-- This is for 'text' or 'fill_blank' --}}
                            <textarea class="form-control" rows="3" readonly></textarea> {{-- Made textarea smaller --}}
                            @endif
                        </div>
                    </div>
                @endforeach
                {{-- ✅✅✅ END: FULL LOGIC FIX ✅✅✅ --}}
            </div>
            <div class="modal-footer">
                <button type="button" class="btn exam-modal-secondary" data-bs-dismiss="modal">@lang('messages.exam_modal_q_paper_close')</button>
            </div>
        </div>
    </div>
</div>

@extends('students.layouts.simple')
@section('title') @lang('messages.feedback_title') @endsection
@section('body-class', 'bg-light')

@section('content')
<style>
    .feedback-card {
        border: 1px solid var(--el-border);
        border-radius: 14px;
        overflow: hidden;
    }
    .feedback-icon {
        align-items: center;
        background: var(--el-primary-soft);
        border-radius: 18px;
        color: var(--el-primary);
        display: inline-flex;
        font-size: 42px;
        height: 82px;
        justify-content: center;
        width: 82px;
    }
    .feedback-card .form-select,
    .feedback-card .form-control { border-color: var(--el-border); }
    .feedback-card .form-select:focus,
    .feedback-card .form-control:focus {
        border-color: var(--el-primary);
        box-shadow: 0 0 0 .2rem rgba(var(--el-primary-rgb), .12);
    }
    .feedback-primary-btn,
    .feedback-primary-btn:hover,
    .feedback-primary-btn:focus {
        background: var(--el-primary);
        border-color: var(--el-primary);
        color: var(--theme-button-text, #fff);
    }
    .feedback-secondary-btn,
    .feedback-secondary-btn:hover,
    .feedback-secondary-btn:focus {
        background: var(--el-secondary);
        border-color: var(--el-secondary);
        color: var(--theme-button-text, #fff);
    }
    .feedback-outline-btn,
    .feedback-outline-btn:hover,
    .feedback-outline-btn:focus {
        background: var(--el-secondary);
        border-color: var(--el-secondary);
        color: var(--theme-button-text, #fff);
    }
    .feedback-actions {
        display: flex;
        flex-wrap: wrap;
        gap: 12px;
        justify-content: center;
    }
    .feedback-actions .btn {
        margin-left: 0 !important;
        min-width: 150px;
    }
    @media (max-width: 575.98px) {
        .feedback-actions .btn {
            width: 100%;
        }
    }
</style>
<div class="d-flex align-items-center justify-content-center min-vh-100">
    <div class="container text-center">
        <div class="card shadow-sm feedback-card" style="max-width: 500px; margin: auto;">
            <div class="card-body p-5">
                <span class="feedback-icon">
                    <i class="ri-checkbox-circle-line"></i>
                </span>

                <h2 class="mt-3">@lang('messages.feedback_heading')</h2>
                <p class="text-muted">@lang('messages.feedback_subheading')</p>

                <div id="feedbackSection" style="display: none;">
                    <hr>
                    <h5 class="mb-3">@lang('messages.feedback_form_title')</h5>
                    <form method="POST" action="{{ route('student.submitExamFeedback') }}">
                        @csrf
                        <input type="hidden" name="exam_result_id" value="{{ $examResultId }}">

                        {{-- Test Instructions Dropdown --}}
                        <div class="mb-3 text-start">
                            <label for="instructionsClear" class="form-label small fw-bold">1. @lang('messages.feedback_label_instructions')</label>
                            <select class="form-select" id="instructionsClear" name="test_instructions">
                                <option value="Very Clear">{{ __('ui.very_clear') }}</option>
                                <option value="Somewhat Clear">{{ __('ui.somewhat_clear') }}</option>
                                <option value="Not Clear">{{ __('messages.feedback_option_instr_not_clear') }}</option>
                            </select>
                        </div>

                        {{-- Language Dropdown --}}
                        <div class="mb-3 text-start">
                            <label for="languageClear" class="form-label small fw-bold">2. @lang('messages.feedback_label_language')</label>
                            <select class="form-select" id="languageClear" name="language_of_questions">
                                <option value="Easy to Understand">{{ __('ui.easy_understand') }}</option>
                                <option value="Moderate">{{ __('ui.moderate') }}</option>
                                <option value="Difficult">{{ __('ui.difficult') }}</option>
                            </select>
                        </div>

                        {{-- Experience Dropdown --}}
                        <div class="mb-3 text-start">
                            <label for="experience" class="form-label small fw-bold">3. @lang('messages.feedback_label_experience')</label>
                            <select class="form-select" id="experience" name="text_experience">
                                <option value="Excellent">{{ __('ui.excellent') }}</option>
                                <option value="Good">{{ __('messages.feedback_option_exp_good') }}</option>
                                <option value="Average">{{ __('messages.feedback_option_exp_avg') }}</option>
                                <option value="Poor">{{ __('messages.feedback_option_exp_poor') }}</option>
                            </select>
                        </div>

                        {{-- Feedback Textarea --}}
                        <div class="mb-3 text-start">
                            <label for="feedback" class="form-label small fw-bold">4. @lang('messages.feedback_label_your_feedback') <span class="text-muted fw-normal">(Optional)</span></label>
                            <textarea class="form-control" id="feedback" name="feedback" rows="3"></textarea>
                        </div>

                        <button type="submit" class="btn feedback-primary-btn w-100">@lang('messages.feedback_submit_button')</button>
                    </form>
                </div>

                {{-- ✅ LOGIC TO DETERMINE REDIRECT URL --}}
                @php
                    // Agar result dikhana allowed hai (result_after_finish == 1)
                    if(isset($resultAfterFinish) && $resultAfterFinish == 1) {
                        $redirectUrl = route('student.results.view', ['id' => $examResultId]);
                        $btnText = "View Result";
                    } else {
                        // Agar result allowed nahi hai, Dashboard par bhejo
                        $redirectUrl = route('student.dashboard');
                        $btnText = "Go to Dashboard";
                    }
                @endphp

                <div class="mt-4 feedback-actions" id="actionButtons">
                    <button type="button" class="btn feedback-outline-btn" onclick="showFeedbackForm()">@lang('messages.feedback_provide_button')</button>

                    {{-- ✅ FIXED: Simple Link Button (No JavaScript Logic needed for redirection) --}}
                    <a href="{{ $redirectUrl }}" class="btn {{ $btnText === 'View Result' ? 'feedback-primary-btn' : 'feedback-secondary-btn' }} ms-2">
                        {{ $btnText }}
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    function showFeedbackForm() {
        document.getElementById('feedbackSection').style.display = 'block';
        document.getElementById('actionButtons').style.display = 'none';
    }
</script>
@endsection

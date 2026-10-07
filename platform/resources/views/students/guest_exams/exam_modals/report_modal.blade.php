<div class="modal fade" id="reportModal" tabindex="-1" aria-labelledby="reportModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-md">
        <div class="modal-content exam-theme-modal">
            <div class="modal-header">
                <h5 class="modal-title" id="reportModalLabel">@lang('messages.exam_modal_question_report_title')</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('messages.my_exams_modal_close_button') }}"></button>
            </div>
            <div class="modal-body">
                <p>Hi, {{ Auth::guard('student')->user()->name ?? 'Guest' }}</p>
                <p>{{ __('ui.report_issue_copy') }}</p>

                <div class="form-group">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">{{ __('ui.report_question') }}</label>

                        <select class="form-select" name="report_type">
                            <option selected disabled value="">{{ __('ui.select_error_type') }}</option>
                            <option value="Typo Error">{{ __('ui.typo_option') }}</option>
                            <option value="Answer Error">{{ __('ui.answer_error_option') }}</option>
                            <option value="Classification Error">{{ __('ui.classification_error_option') }}</option>
                            <option value="Translation Error">{{ __('ui.translation_error_option') }}</option>
                            <option value="Other Error">{{ __('ui.other') }}</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">{{ __('messages.my_exams_details_button') }} <span class="text-muted fw-normal">(optional)</span></label>
                        <textarea class="form-control" name="message" rows="4" placeholder="Add details if needed"></textarea>
                    </div>
                </div>

                <input type="hidden" id="report_question_id" value="question_id">
                <input type="hidden" id="report_subject_id" value="subject_id">
                <input type="hidden" id="report_question_type" value="question_type">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn report-submit-btn exam-modal-primary">{{ __('ui.submit') }}</button>
                <button type="button" class="btn exam-modal-secondary" data-bs-dismiss="modal">@lang('messages.exam_modal_q_paper_close')</button>
            </div>
        </div>
    </div>
</div>

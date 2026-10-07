<div class="modal fade" id="browserToleranceModal" tabindex="-1" aria-labelledby="browserToleranceModalLabel"
    aria-hidden="true">
    @include('students.exams.exam_modals.shared_browser_tolerance_styles')
    <div class="modal-dialog modal-lg">
        <div class="modal-content exam-theme-modal exam-warning-modal">
            <div class="modal-header">
                <h5 class="modal-title" id="browserToleranceModalLabel">
                    @lang('messages.exam_modal_warning_title')
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('messages.my_exams_modal_close_button') }}"></button>
            </div>
            <div class="modal-body">
                <p class="mb-0">
                    @lang('messages.exam_modal_warning_desc')
                </p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn exam-modal-secondary" data-bs-dismiss="modal">@lang('messages.exam_modal_q_paper_close')</button>
            </div>
        </div>
    </div>
</div>

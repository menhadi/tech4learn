<div class="modal fade" id="finalizeExamModal" tabindex="-1" aria-labelledby="finalizeExamModalLabel"
    aria-hidden="true">
    @include('students.exams.exam_modals.shared_finalize_styles')
    <div class="modal-dialog exam-finalize-dialog modal-dialog-centered">
        <div class="modal-content exam-theme-modal exam-finalize-modal">
            <div class="modal-header">
                <h5 class="modal-title" id="finalizeExamModalLabel">@lang('messages.exam_modal_finalize_title')</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('messages.my_exams_modal_close_button') }}"></button>
            </div>
            <div class="modal-body">
                <p>@lang('messages.exam_modal_finalize_warning_1')</p>
                <p>@lang('messages.exam_modal_finalize_warning_2')</p>
                <div class="exam-finalize-summary">
                    <strong>@lang('messages.exam_sidebar_legend_title')</strong>
                    <div class="row g-3">
                        <div class="col-md-6 d-flex align-items-center exam-finalize-stat">
                            <span class="badge" id="finalize-legend-answered">0</span>
                            <img src="{{ asset('assets/images/exam-status/answered.svg') }}" alt="@lang('messages.exam_sidebar_legend_answered')">
                            <span>@lang('messages.exam_sidebar_legend_answered')</span>
                        </div>
                        <div class="col-md-6 d-flex align-items-center exam-finalize-stat">
                            <span class="badge" id="finalize-legend-not_answered">0</span>
                            <img src="{{ asset('assets/images/exam-status/not_answered.svg') }}" alt="@lang('messages.exam_sidebar_legend_not_answered')">
                            <span>@lang('messages.exam_sidebar_legend_not_answered')</span>
                        </div>
                        <div class="col-md-6 d-flex align-items-center exam-finalize-stat">
                            <span class="badge" id="finalize-legend-not_visited">0</span>
                            <img src="{{ asset('assets/images/exam-status/not_visited.svg') }}" alt="@lang('messages.exam_sidebar_legend_not_visited')">
                            <span>@lang('messages.exam_sidebar_legend_not_visited')</span>
                        </div>
                        <div class="col-md-6 d-flex align-items-center exam-finalize-stat">
                            <span class="badge" id="finalize-legend-review">0</span>
                            <img src="{{ asset('assets/images/exam-status/review.svg') }}" alt="@lang('messages.exam_sidebar_legend_review')">
                            <span>@lang('messages.exam_sidebar_legend_review')</span>
                        </div>
                        <div class="col-12 d-flex align-items-center exam-finalize-stat">
                            <span class="badge" id="finalize-legend-review_answer">0</span>
                            <img src="{{ asset('assets/images/exam-status/review_answer.svg') }}" alt="@lang('messages.exam_modal_finalize_review_ans')">
                            <span>@lang('messages.exam_modal_finalize_review_ans')</span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer d-flex justify-content-between exam-finalize-actions">
                <button type="button" class="btn exam-modal-primary" id="finishExamButton">
                    <i class="mdi mdi-check"></i> @lang('messages.exam_modal_finalize_btn_finish')
                </button>
                <button type="button" class="btn exam-modal-secondary" data-bs-dismiss="modal">
                    <i class="mdi mdi-cancel"></i> @lang('messages.exam_modal_finalize_btn_cancel')
                </button>
                <button type="button" class="btn exam-modal-primary" id="returnToFirstQuestion">
                    <i class="mdi mdi-arrow-left"></i> @lang('messages.exam_modal_finalize_btn_return')
                </button>
            </div>
        </div>
    </div>
</div>

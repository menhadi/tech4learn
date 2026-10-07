<div class="modal fade" id="instructionsModal" tabindex="-1" aria-labelledby="instructionsModalLabel"
    aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content exam-theme-modal">
            <div class="modal-header">
                {{-- Reusing existing key from sidebar --}}
                <h5 class="modal-title" id="instructionsModalLabel">@lang('messages.exam_sidebar_btn_instructions')</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('messages.my_exams_modal_close_button') }}"></button>
            </div>
            <div class="modal-body">
                {{-- Exam instructions are pulled from the database (already dynamic) --}}
                <p>{!! $exam->instruction !!}</p>
            </div>
            <div class="modal-footer">
                {{-- Reusing existing key from question paper modal --}}
                <button type="button" class="btn exam-modal-secondary" data-bs-dismiss="modal">@lang('messages.exam_modal_q_paper_close')</button>
            </div>
        </div>
    </div>
</div>

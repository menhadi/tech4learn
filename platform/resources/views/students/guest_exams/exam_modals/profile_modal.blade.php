<div class="modal fade" id="profileModal" tabindex="-1" aria-labelledby="profileModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content exam-theme-modal">
            <div class="modal-header">
                {{-- Reusing existing key --}}
                <h5 class="modal-title" id="profileModalLabel">@lang('messages.exam_modal_profile_title')</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('messages.my_exams_modal_close_button') }}"></button>
            </div>
            <div class="modal-body">
                @if (Auth::guard('student')->check())
                <p><strong>@lang('messages.exam_modal_profile_name')</strong> {{ Auth::guard('student')->user()->name }}</p>
                <p><strong>@lang('messages.exam_modal_profile_email')</strong> {{ Auth::guard('student')->user()->email }}</p>
                @else
                <p>@lang('messages.exam_modal_profile_not_auth')</p>
                @endif
            </div>
            <div class="modal-footer">
                {{-- Reusing existing key --}}
                <button type="button" class="btn exam-modal-secondary" data-bs-dismiss="modal">@lang('messages.exam_modal_q_paper_close')</button>
            </div>
        </div>
    </div>
</div>

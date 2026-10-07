@php
    $displayMarks = is_numeric($exam->questions_sum_marks)
        ? number_format((float) $exam->questions_sum_marks, 0)
        : __('messages.my_exams_js_na');
@endphp
<tr>
    <td>
        <div class="d-flex align-items-start gap-3">
            <span class="student-paper-index">{{ $examIndex }}</span>
            <div class="student-exam-info">
                <div class="student-exam-name">{{ $exam->name }}</div>
                @if($exam->offline_enabled)
                    <span class="badge bg-success-subtle text-success border border-success-subtle mt-1"><i class="ri-wifi-off-line me-1"></i>{{ __('ui.offline_exam_available') }}</span>
                @endif
                <div class="student-paper-details">
                    <span class="student-metric-chip"><i class="ri-time-line"></i> {{ $exam->duration == 0 ? __('messages.my_exams_js_unlimited') : $exam->duration . ' ' . __('messages.my_exams_mins') }}</span>
                    <span class="student-metric-chip"><i class="ri-file-text-line"></i> {{ $displayMarks }} @lang('messages.my_exams_marks')</span>
                    <span class="student-attempt-box"><i class="ri-repeat-line"></i> {{ $exam->attempts_left }} @lang('messages.my_exams_attempts_label')</span>
                </div>
            </div>
        </div>
    </td>
    <td class="text-end">
        <x-exam-action-buttons>
            <x-slot:primary>
            @if($exam->canAttemptOnline())
                @if($exam->exam_status == 'Live' && ($exam->attempts_left > 0 || $exam->attempts_left == 'Unlimited'))
                    <button type="button" class="exam-action-control exam-action-control--primary" onclick="attemptExam({{ $exam->id }}, {{ $exam->attempt_count }})">@lang('messages.my_exams_attempt_button') <i class="ri-arrow-right-line"></i></button>
                @elseif($exam->latest_result_id)
                    <a href="{{ route('student.results.view', $exam->latest_result_id) }}" class="exam-action-control exam-action-control--primary">@lang('messages.my_exams_result_button')</a>
                @endif
            @else<span class="badge bg-light text-dark">PDF only</span>@endif
        </x-slot:primary>
            <x-slot:paper>
                @if($package->show_pdf_download ?? true)
                    <a class="exam-action-control exam-action-control--download" href="{{ route('exam.print.download', ['id' => $exam->slug ?: $exam->id, 'package' => $package->slug ?: $package->id]) }}" title="{{ __('ui.download_question_paper') }}"><i class="ri-download-2-line"></i> {{ __('ui.paper_pdf') }}</a>
                @endif
            </x-slot:paper>
            <x-slot:solution>
                @if($exam->canAttemptOnline() && ($package->show_solution_pdf_download ?? true))
                    <a class="exam-action-control exam-action-control--download" href="{{ route('student.exam.solution.download', ['id' => $exam->slug ?: $exam->id, 'package' => $package->slug ?: $package->id]) }}" title="{{ __('ui.download_solutions') }}"><i class="ri-file-check-line"></i> {{ __('ui.solution') }}</a>
                @endif
            </x-slot:solution>
            <x-slot:details>
                @if($exam->canAttemptOnline())
                <button type="button" class="exam-action-control exam-action-control--download exam-action-control--icon" onclick="showExamDetails({{ $exam->id }})" title="@lang('messages.my_exams_details_button')" aria-label="@lang('messages.my_exams_details_button')"><i class="ri-information-line"></i></button>
            @endif
            </x-slot:details>
        </x-exam-action-buttons>
    </td>
</tr>
@php
    $paperLanguages = app(App\Services\ExamLanguageService::class)->available($exam)->map(fn ($language) => [
        'id' => $language->id, 'code' => $language->code, 'name' => $language->name,
    ])->values();
    $preferredPaperLanguage = auth('student')->user()?->language ?: session('preferred_exam_language', 'en');
@endphp

<li class="course-exam-row" data-exam-subject-id="{{ $exam->test_subject_id ?: 'general' }}" data-exam-topic-id="{{ $exam->test_topic_id ?: 'general-topic' }}" data-exam-subtopic-id="{{ $exam->test_stopic_id ?: 'general-subtopic' }}">
    <span class="course-exam-number">{{ $examIndex }}</span>
    <div>
        <h3 class="course-exam-title">{{ $examDisplayName }}</h3>
        <div class="course-exam-meta">
            <span><i class="ri-question-line"></i> {{ __('ui.questions_count', ['count' => $exam->questions_count ?? countExamQuestions($exam->id)]) }}</span>
            <span><i class="ri-time-line"></i> {{ __('ui.minutes_count', ['count' => $exam->duration ?? 180]) }}</span>
            @if(!empty($exam->marks))
                <span><i class="ri-file-text-line"></i> {{ __('ui.marks_count', ['count' => (int) $exam->marks]) }}</span>
            @endif
        </div>
    </div>
    <x-exam-action-buttons>
        <x-slot:primary>
            @if($exam->canAttemptOnline())
            @if($package->package_type == 'free' && !$allowGuestExamAttempts)
                <a class="exam-action-control exam-action-control--primary" href="{{ route('student.signin', ['action' => 'start_exam', 'package_id' => $package->id, 'exam_id' => $exam->id, 'group_id' => $groupId]) }}">
                    Attempt <i class="ri-arrow-right-line"></i>
                </a>
            @else
                <button type="button" class="exam-action-control exam-action-control--primary startExamBtn" data-groupid="{{ $groupId }}" data-id="{{ $package->id }}" data-exam="{{ $exam->slug ?: $exam->id }}">
                    Attempt <i class="ri-arrow-right-line"></i>
                </button>
            @endif
        @else<span class="badge bg-light text-dark">PDF only</span>@endif
        </x-slot:primary>
        <x-slot:paper>
            @if($package->show_pdf_download ?? true)
                <button type="button" class="exam-action-control exam-action-control--download downloadPdfBtn" data-pdf-intent-url="{{ route('exam.print.intent', ['id' => $exam->slug ?: $exam->id]) }}" data-package="{{ $package->slug ?: $package->id }}" data-exam-id="{{ $exam->slug ?: $exam->id }}" data-exam-name="{{ $examDisplayName }}"
                    data-pdf-languages='@json($paperLanguages)' data-preferred-language="{{ $preferredPaperLanguage }}">
                    <i class="ri-download-2-line"></i> {{ __('ui.paper_pdf') }}
                </button>
            @endif
        </x-slot:paper>
        <x-slot:solution>
            @if($exam->canAttemptOnline() && ($package->show_solution_pdf_download ?? true))
                <a class="exam-action-control exam-action-control--download protectedSolutionPdfBtn" data-login-required="{{ auth('student')->check() ? '0' : '1' }}" data-solution-activity-url="{{ route('exam.solution.activity', ['id' => $exam->slug ?: $exam->id]) }}" data-package="{{ $package->slug ?: $package->id }}" href="{{ route('student.exam.solution.download', ['id' => $exam->slug ?: $exam->id, 'package' => $package->slug ?: $package->id]) }}" title="{{ __('ui.download_solutions') }}">
                    <i class="ri-file-check-line"></i> {{ __('ui.solution') }}
                </a>
            @endif
        </x-slot:solution>
    </x-exam-action-buttons>
</li>
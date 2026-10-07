@php
    $subjectKey = static fn ($exam) => (string) ($exam->test_subject_id ?: 'general');
    $topicKey = static fn ($exam) => (string) ($exam->test_topic_id ?: 'general-topic');
    $subtopicKey = static fn ($exam) => (string) ($exam->test_stopic_id ?: 'general-subtopic');
    $subjectName = static fn ($exam) => $exam->testSubject?->subject_name ?: 'General';
    $topicName = static fn ($exam) => $exam->testTopic?->name ?: 'General Topic';
    $subtopicName = static fn ($exam) => $exam->testSubtopic?->name ?: 'General Subtopic';
    $groupBySubject = in_array($type, [
        \App\Models\Exam::TEST_TYPE_SUBJECT,
        \App\Models\Exam::TEST_TYPE_TOPIC,
        \App\Models\Exam::TEST_TYPE_SUBTOPIC,
    ], true);
    $subjects = $groupBySubject ? $exams->groupBy($subjectKey) : collect();
@endphp

<section class="exam-type-section" data-exam-type-section="{{ $type }}">
    @if(!$groupBySubject)
        <ul class="course-exam-list">
            @foreach($exams as $exam)
                @include('website.partials.course-exam-row', [
                    'exam' => $exam,
                    'examIndex' => $examNumberById[$exam->id] ?? $loop->iteration,
                    'examDisplayName' => $displayText($exam->name),
                    'package' => $package,
                    'groupId' => $groupId,
                    'allowGuestExamAttempts' => $allowGuestExamAttempts,
                ])
            @endforeach
        </ul>
    @else
        <nav class="exam-hierarchy-nav"
             data-exam-hierarchy
             data-hierarchy-level="{{ $type }}"
             data-type-label="{{ $examTypeLabels[$type] ?? 'Tests' }}"
             aria-label="{{ $examTypeLabels[$type] ?? 'Test' }} filters">
            <div class="exam-filter-selection" aria-live="polite">
                <div class="exam-filter-selection__path">
                    <span>{{ __('website.course_showing') }}</span>
                    <strong data-selected-path>{{ $examTypeLabels[$type] ?? 'Tests' }}</strong>
                </div>
                <span class="exam-filter-selection__count" data-visible-count>{{ $exams->count() }} {{ Str::plural('test', $exams->count()) }}</span>
            </div>

            <div class="exam-filter-row" data-filter-row="subject">
                <div class="exam-filter-label">{{ __('ui.subjects') }}</div>
                <div class="exam-filter-options">
                    <button type="button" class="exam-filter-chip is-active" data-filter-level="subject" data-filter-value="" data-filter-label="{{ __('ui.all_subjects') }}" aria-pressed="true">
                        {{ __('website.course_all') }} <span>{{ $exams->count() }}</span>
                    </button>
                    @foreach($subjects as $subjectId => $subjectExams)
                        <button type="button"
                                class="exam-filter-chip"
                                data-filter-level="subject"
                                data-filter-value="{{ $subjectId }}"
                                data-filter-label="{{ $subjectName($subjectExams->first()) }}"
                                aria-pressed="false">
                            {{ $subjectName($subjectExams->first()) }} <span>{{ $subjectExams->count() }}</span>
                        </button>
                    @endforeach
                </div>
            </div>

            @if(in_array($type, [\App\Models\Exam::TEST_TYPE_TOPIC, \App\Models\Exam::TEST_TYPE_SUBTOPIC], true))
                <div class="exam-filter-row" data-filter-row="topic" hidden>
                    <div class="exam-filter-label">{{ __('ui.topics') }}</div>
                    <div class="exam-filter-options">
                        <button type="button" class="exam-filter-chip is-active" data-filter-level="topic" data-filter-value="" data-filter-label="{{ __('ui.all_topics') }}" aria-pressed="true">{{ __('ui.all_topics') }}</button>
                        @foreach($subjects as $subjectId => $subjectExams)
                            @foreach($subjectExams->groupBy($topicKey) as $topicId => $topicExams)
                                <button type="button"
                                        class="exam-filter-chip"
                                        data-filter-level="topic"
                                        data-filter-value="{{ $topicId }}"
                                        data-filter-label="{{ $topicName($topicExams->first()) }}"
                                        data-parent-subject="{{ $subjectId }}"
                                        aria-pressed="false"
                                        hidden>
                                    {{ $topicName($topicExams->first()) }} <span>{{ $topicExams->count() }}</span>
                                </button>
                            @endforeach
                        @endforeach
                    </div>
                </div>
            @endif

            @if($type === \App\Models\Exam::TEST_TYPE_SUBTOPIC)
                <div class="exam-filter-row" data-filter-row="subtopic" hidden>
                    <div class="exam-filter-label">{{ __('ui.subtopics_label') }}</div>
                    <div class="exam-filter-options">
                        <button type="button" class="exam-filter-chip is-active" data-filter-level="subtopic" data-filter-value="" data-filter-label="{{ __('ui.all_subtopics_filter') }}" aria-pressed="true">{{ __('ui.all_subtopics_filter') }}</button>
                        @foreach($subjects as $subjectId => $subjectExams)
                            @foreach($subjectExams->groupBy($topicKey) as $topicId => $topicExams)
                                @foreach($topicExams->groupBy($subtopicKey) as $subtopicId => $subtopicExams)
                                    <button type="button"
                                            class="exam-filter-chip"
                                            data-filter-level="subtopic"
                                            data-filter-value="{{ $subtopicId }}"
                                            data-filter-label="{{ $subtopicName($subtopicExams->first()) }}"
                                            data-parent-subject="{{ $subjectId }}"
                                            data-parent-topic="{{ $topicId }}"
                                            aria-pressed="false"
                                            hidden>
                                        {{ $subtopicName($subtopicExams->first()) }} <span>{{ $subtopicExams->count() }}</span>
                                    </button>
                                @endforeach
                            @endforeach
                        @endforeach
                    </div>
                </div>
            @endif
        </nav>

        <ul class="course-exam-list" data-exam-filter-list>
            @foreach($exams as $exam)
                @include('website.partials.course-exam-row', [
                    'exam' => $exam,
                    'examIndex' => $examNumberById[$exam->id] ?? $loop->iteration,
                    'examDisplayName' => $displayText($exam->name),
                    'package' => $package,
                    'groupId' => $groupId,
                    'allowGuestExamAttempts' => $allowGuestExamAttempts,
                ])
            @endforeach
        </ul>
        <div class="exam-filter-empty" data-exam-filter-empty hidden>{{ __('ui.no_tests_selection') }}</div>
    @endif
</section>

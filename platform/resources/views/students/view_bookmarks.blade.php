@extends('students.layouts.app')

@section('title') @lang('messages.view_bookmarks_title') - {{ $examName ?? 'Review' }} @endsection

@push('styles')
<style>
    .question-card {
        border: 1px solid var(--el-border);
        border-radius: 8px;
        box-shadow: 0 10px 30px rgba(0,0,0,0.05);
        overflow: hidden;
    }
    .question-header {
        background: var(--el-table-head-bg, var(--el-primary-soft));
        padding: 15px 25px;
        border-bottom: 1px solid #eef2f7;
    }
    .option-box {
        position: relative;
        padding: 15px 20px;
        margin-bottom: 12px;
        border: 1px solid var(--el-border);
        border-radius: 8px;
        background: #fff;
    }
    /* Correct Answer Style (Green) */
    .option-box.correct-answer {
        border-color: var(--el-primary);
        background-color: var(--el-primary-soft);
    }
    /* Student's Wrong Selection (Red) */
    .option-box.wrong-selection {
        border-color: var(--el-secondary);
        background-color: var(--el-secondary-soft);
    }
    .badge-status {
        font-size: 0.75rem;
        padding: 4px 8px;
        border-radius: 6px;
        font-weight: 600;
        text-transform: uppercase;
        margin-left: 10px;
    }
    /* Static Info Boxes */
    .info-box {
        background: var(--el-primary-soft);
        border-left: 4px solid;
        border-radius: 6px;
        margin-top: 20px;
        padding: 15px;
    }
    .info-box.hint { 
        border-color: var(--el-secondary);
        background-color: var(--el-secondary-soft);
    }
    .info-box.explanation { 
        border-color: var(--el-primary);
        background-color: var(--el-primary-soft);
    }
    .info-label {
        font-weight: 700;
        margin-bottom: 5px;
        display: block;
        text-transform: uppercase;
        font-size: 0.8rem;
        letter-spacing: 0.5px;
    }
    .student-badge-primary { background: var(--el-primary); color: var(--theme-button-text, #fff); }
    .student-badge-secondary { background: var(--el-secondary); color: var(--theme-button-text, #fff); }
    .student-badge-soft { background: var(--el-primary-soft); color: var(--el-primary); border: 1px solid var(--el-border); }
    .student-badge-danger { background: var(--el-secondary); color: var(--theme-button-text, #fff); }
    .student-text-primary { color: var(--el-primary) !important; }
    .student-text-secondary { color: var(--el-secondary) !important; }
    .student-remove-btn { color: var(--el-secondary); border: 1px solid var(--el-secondary); background: #fff; }
    .student-remove-btn:hover { background: var(--el-secondary); color: var(--theme-button-text, #fff); }
</style>
@endpush

@section('content')

@component('components.breadcrumb')
    @slot('li_1') <a href="{{ route('student.bookmarks') }}">{{ __('messages.sidebar_my_bookmark') }}</a> @endslot
    @slot('title') {{ $examName ?? 'Question Review' }} @endslot
@endcomponent

<div class="row justify-content-center">
    <div class="col-lg-9">
        
        {{-- Navigation Header --}}
        <div class="d-flex justify-content-between align-items-center mb-4">
            <button class="btn btn-white shadow-sm border" id="prevBtn"><i class="ri-arrow-left-line align-middle"></i> {{ __('ui.prev') }}</button>
            <div class="text-center">
                <h5 class="mb-0 fw-bold" style="color: var(--el-primary);">{{ __('messages.view_bookmarks_question_prefix') }} <span id="current-q-num">1</span></h5>
                <small class="text-muted">Total {{ count($bookmarkedQuestions) }} Questions</small>
            </div>
            <button class="btn btn-white shadow-sm border" id="nextBtn">{{ __('messages.view_bookmarks_js_next') }} <i class="ri-arrow-right-line align-middle"></i></button>
        </div>

        <div id="question-container">
            @forelse($bookmarkedQuestions as $index => $report)
                @php
                    $question = $report->question;
                    $answerEvaluator = app(\App\Services\QuestionAnswerEvaluator::class);
                    $selectedOptionIndices = $answerEvaluator->selectedOptionIndices($question, $report);
                    $correctOptionIndices = $answerEvaluator->correctOptionIndices($question);
                    
                    // Non-option answer values remain available for True/False, blanks, NAT and subjective questions.
                    $studentAns = $report->true_false ?? $report->answer;
                    $correctAns = $question->true_false ?? $question->fill_blank ?? $answerEvaluator->correctAnswerSnapshot($question);
                @endphp

            <div class="card question-item question-card" 
                 data-index="{{ $index }}" 
                 style="display: {{ $index === 0 ? 'block' : 'none' }};">
                
                {{-- Question Header --}}
                <div class="question-header d-flex justify-content-between align-items-center">
                    <div>
                        <span class="badge student-badge-primary me-1">{{ $question->qtype->qtype ?? 'MCQ' }}</span>
                        <span class="badge student-badge-soft">{{ $question->subject->subject_name ?? 'General' }}</span>
                        <span class="badge student-badge-secondary ms-1">Marks: {{ $question->marks }}</span>
                    </div>
                    <button class="btn btn-sm student-remove-btn bookmark-btn" data-question-id="{{ $question->id }}" data-exam-stat-id="{{ $report->id }}" data-bookmarked="true">
                        <i class="ri-bookmark-fill align-middle"></i> {{ __('ui.remove') }}
                    </button>
                </div>

                <div class="card-body p-4">
                    {{-- Question Text --}}
                    <div class="mb-4">
                        <h5 class="lh-base text-dark fw-semibold" style="font-size: 1.1rem;">
                            <span class="text-muted me-2">Q{{ $index + 1 }}.</span> 
                            {!! $question->question !!}
                        </h5>
                    </div>

                    {{-- OPTIONS RENDER LOGIC --}}
                    <div class="mb-4">
                        
                        {{-- CASE 1: True / False --}}
                        @if($question->question_type == 'T' || $question->true_false)
                            @foreach(['True', 'False'] as $opt)
                                @php
                                    $isCorrect = ($correctAns == $opt);
                                    $isSelected = ($studentAns == $opt);
                                    $boxClass = '';
                                    if ($isCorrect) $boxClass = 'correct-answer';
                                    elseif ($isSelected && !$isCorrect) $boxClass = 'wrong-selection';
                                @endphp
                                <div class="option-box {{ $boxClass }} d-flex justify-content-between align-items-center">
                                    <span><i class="ri-checkbox-blank-circle-line text-muted me-2"></i> {{ $opt }}</span>
                                    <div>
                                        @if($isSelected) <span class="badge bg-dark">{{ __('ui.your_choice') }}</span> @endif
                                        @if($isCorrect) <span class="badge student-badge-primary"><i class="ri-check-line"></i> {{ __('messages.results_view_table_correct') }}</span> @endif
                                        @if($isSelected && !$isCorrect) <span class="badge student-badge-danger"><i class="ri-close-line"></i> {{ __('ui.wrong') }}</span> @endif
                                    </div>
                                </div>
                            @endforeach

                        {{-- CASE 2: MCQ (Option 1 - 6) --}}
                        @elseif($question->option1)
                            @foreach(['option1', 'option2', 'option3', 'option4', 'option5', 'option6'] as $key)
                                @if(!empty($question->$key))
                                    @php
                                        $optionIndex = $loop->iteration;
                                        $optText = $question->$key;
                                        $isCorrect = in_array($optionIndex, $correctOptionIndices, true);
                                        $isSelected = in_array($optionIndex, $selectedOptionIndices, true);

                                        $boxClass = '';
                                        if ($isCorrect) $boxClass = 'correct-answer';
                                        elseif ($isSelected && !$isCorrect) $boxClass = 'wrong-selection';
                                    @endphp

                                    <div class="option-box {{ $boxClass }} d-flex align-items-center justify-content-between">
                                        <div class="d-flex align-items-center">
                                            <span class="fw-bold text-muted me-3" style="min-width:20px;">{{ chr(65 + $loop->index) }}</span>
                                            <div class="text-break">{!! $optText !!}</div>
                                        </div>
                                        <div style="min-width: 100px; text-align: right;">
                                            @if($isSelected) <span class="badge bg-dark badge-status">{{ __('ui.you') }}</span> @endif
                                            @if($isCorrect) <span class="badge student-badge-primary badge-status"><i class="ri-check-double-line"></i> {{ __('ui.ans') }}</span> @endif
                                            @if($isSelected && !$isCorrect) <span class="badge student-badge-danger badge-status"><i class="ri-close-line"></i></span> @endif
                                        </div>
                                    </div>
                                @endif
                            @endforeach

                        {{-- CASE 3: Fill in Blanks / Text --}}
                        @else
                            <div class="alert alert-light border">
                                <p class="mb-1 text-muted small">{{ __('ui.your_answer') }}:</p>
                                <h6 class="text-dark">{{ $studentAns ?? 'Not Attempted' }}</h6>
                                <hr>
                                <p class="mb-1 student-text-primary small fw-bold">{{ __('ui.correct_answer') }}:</p>
                                <h6 class="student-text-primary">{{ $correctAns }}</h6>
                            </div>
                        @endif
                    </div>

                    {{-- ✅ HINT SECTION (Always Visible if exists) --}}
                    @if(!empty($question->hint))
                        <div class="info-box hint">
                            <span class="info-label student-text-secondary"><i class="ri-lightbulb-flash-line"></i> {{ __('ui.hint') }}</span>
                            <div class="text-dark">{!! $question->hint !!}</div>
                        </div>
                    @endif

                    {{-- ✅ EXPLANATION SECTION (Always Visible if exists) --}}
                    @if(!empty($question->explanation))
                        <div class="info-box explanation">
                            <span class="info-label" style="color: var(--el-primary);"><i class="ri-book-open-line"></i> {{ __('ui.explanation') }}</span>
                            <div class="text-dark">{!! $question->explanation !!}</div>
                        </div>
                    @endif

                </div>
            </div>
            @empty
                <div class="text-center py-5">
                    <i class="ri-question-line d-block mb-3 opacity-50" style="font-size: 72px; color: var(--el-primary);"></i>
                    <h4 class="text-muted">{{ __('ui.no_questions_found') }}</h4>
                </div>
            @endforelse
        </div>

    </div>
</div>
@endsection

@push('scripts')
{{-- Include MathJax --}}
<script>
    window.MathJax = { loader: { load: ['[tex]/mhchem'] }, tex: { packages: {'[+]': ['mhchem']}, inlineMath: [['$', '$'], ['\\(', '\\)']], displayMath: [['\\[', '\\]'], ['$$', '$$']], processEscapes: true }, svg: { fontCache: 'global' } };
</script>
<script id="MathJax-script" async src="https://cdn.jsdelivr.net/npm/mathjax@3/es5/tex-mml-chtml.js"></script>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const questions = document.querySelectorAll('.question-item');
        const prevBtn = document.getElementById('prevBtn');
        const nextBtn = document.getElementById('nextBtn');
        const qNumSpan = document.getElementById('current-q-num');
        let currentIndex = 0;

        function updateUI(index) {
            questions.forEach((q, i) => {
                q.style.display = i === index ? 'block' : 'none';
            });
            qNumSpan.innerText = index + 1;
            prevBtn.disabled = index === 0;
            nextBtn.disabled = index === questions.length - 1;
            
            // Re-render MathJax if content changed
            if(window.MathJax && MathJax.typesetPromise) {
                MathJax.typesetPromise();
            }
        }

        prevBtn.addEventListener('click', () => {
            if (currentIndex > 0) { currentIndex--; updateUI(currentIndex); }
        });

        nextBtn.addEventListener('click', () => {
            if (currentIndex < questions.length - 1) { currentIndex++; updateUI(currentIndex); }
        });

        // Bookmark Remove Logic
        document.querySelectorAll('.bookmark-btn').forEach(button => {
            button.addEventListener('click', async function () {
                const confirmation = await Swal.fire({ icon: 'warning', title: 'Remove bookmark?', text: 'Remove this question from bookmarks?', showCancelButton: true, confirmButtonText: 'Remove' });
                if (!confirmation.isConfirmed) return;
                const questionId = this.getAttribute('data-question-id');
                const examStatId = this.getAttribute('data-exam-stat-id');

                fetch('{{ route("student.bookmarkQuestion") }}', { 
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                    body: JSON.stringify({ question_id: questionId, exam_stat_id: examStatId, bookmark: false })
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        alert('Removed!');
                        window.location.reload(); 
                    }
                });
            });
        });

        updateUI(currentIndex);
    });
</script>
@endpush

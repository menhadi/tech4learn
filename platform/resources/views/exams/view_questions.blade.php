@extends('layouts.master')

@section('title', 'Manage Exam Questions')

@php
    $canEditQuestion = user_can_route_action('questions.edit', 'edit');
    $groupingMode = $exam->grouping_mode ?: ($exam->timer_mode === 'section' ? 'section' : 'subject');
    $timerMode = $exam->timer_mode ?: ($exam->is_subject_timer ? $groupingMode : 'none');
@endphp

@section('content')
@component('components.breadcrumb')
@slot('li_1', 'Exams')
@slot('title', 'Manage Exam Questions')
@endcomponent

<div class="row">
    <div class="col-12">
        @if($groupingMode === 'section')
            <div class="card mb-3">
                <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <div><h4 class="card-title mb-1">Exam Sections</h4><p class="text-muted mb-0">Questions inherit reusable Sections when added. Assignments can be overridden for this exam below.</p></div>
                    <a href="{{ route('sections.index') }}" class="btn btn-soft-primary"><i class="ri-layout-grid-line me-1"></i>Manage Reusable Sections</a>
                </div>
                <div class="card-body">
                    @if($sections->isEmpty())
                        <div class="alert alert-info mb-0">No Section has been copied into this exam yet. Assign a reusable Section below, or add a question that already has a Section.</div>
                    @else
                        <div class="row g-3">
                            @foreach($sections as $section)
                                <div class="col-lg-4">
                                    <form method="POST" action="{{ route('exams.sections.update', [$exam, $section]) }}" class="border rounded p-3 h-100">@csrf @method('PUT')
                                        <input type="hidden" name="name" value="{{ $section->name }}"><input type="hidden" name="display_order" value="{{ $section->display_order }}">
                                        <strong>{{ $section->name }}</strong>
                                        <label class="form-label small mt-2">Duration for this exam (minutes)</label>
                                        <div class="input-group"><input name="duration" value="{{ $section->duration }}" type="number" min="1" class="form-control" placeholder="Automatic"><button class="btn btn-soft-primary" title="Save duration"><i class="ri-save-line"></i></button></div>
                                        @if($timerMode !== 'section')<small class="text-muted">Used only when separate Section timers are enabled.</small>@endif
                                    </form>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        @elseif($groupingMode === 'subject')
            <div class="alert alert-info">Questions are displayed by <strong>Subject</strong>. {{ $timerMode === 'subject' ? 'Each subject has its own timer.' : 'The exam uses one overall timer.' }}</div>
        @else
            <div class="alert alert-light border">Questions are displayed together without Subject or Section tabs, using one overall timer.</div>
        @endif
        <div class="card">
            <div class="card-header">
                <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
                    <div><h4 class="card-title mb-1">{{ $exam->name }}</h4><p class="text-muted mb-0">{{ $questions->count() }} questions attached</p></div>
                    <div class="d-flex flex-wrap gap-2"><a class="btn btn-outline-primary" href="{{ route('exams.paper.preview', $exam) }}" target="_blank"><i class="ri-file-paper-2-line me-1"></i>View complete paper</a>@if($canEditQuestion)<a class="btn btn-primary" href="{{ route('exams.paper.edit', $exam) }}"><i class="ri-edit-box-line me-1"></i>Edit complete paper</a>@endif</div>
                </div>
                <div class="row g-2">
                    @if($groupingMode === 'section')
                        <div class="col-md-2">
                            <select id="section-filter" class="form-select select2">
                                <option value="">All Sections</option>
                                <option value="general" @selected(request('section') === 'general')>General / Unassigned</option>
                                @foreach($sections as $section)
                                    <option value="{{ $section->id }}" @selected((string) request('section') === (string) $section->id)>{{ $section->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif
                    <div class="col-md-2"><select id="subject-filter" class="form-select select2"><option value="">All Subjects</option>@foreach($subjects as $subject)<option value="{{ $subject->id }}" @selected(request('subject') == $subject->id)>{{ $subject->subject_name }}</option>@endforeach</select></div>
                    <div class="col-md-2"><select id="topic-filter" class="form-select select2"><option value="">All Topics</option>@foreach($topics as $topic)<option value="{{ $topic->id }}" @selected(request('topic') == $topic->id)>{{ $topic->name }}</option>@endforeach</select></div>
                    <div class="col-md-2"><select id="subtopic-filter" class="form-select select2"><option value="">All Sub Topics</option>@foreach($stopics as $stopic)<option value="{{ $stopic->id }}" @selected(request('subtopic') == $stopic->id)>{{ $stopic->name }}</option>@endforeach</select></div>
                    <div class="col-md-2"><select id="qtype-filter" class="form-select select2"><option value="">All Question Types</option>@foreach($qtypes as $qtype)<option value="{{ $qtype->id }}" @selected(request('qtype') == $qtype->id)>{{ $qtype->question_type }}</option>@endforeach</select></div>
                    <div class="col-md-2"><select id="diff-filter" class="form-select select2"><option value="">All Difficulty Levels</option>@foreach($diffs as $diff)<option value="{{ $diff->id }}" @selected(request('diff') == $diff->id)>{{ $diff->diff_level }}</option>@endforeach</select></div>
                    <div class="col-md-4"><input id="question-search" class="form-control" placeholder="Search question text" value="{{ request('question') }}"></div>
                    <div class="col-md-auto"><button id="search-btn" class="btn btn-primary">Search</button></div>
                    <div class="col-md-auto"><button id="reset-btn" class="btn btn-secondary">Reset</button></div>
                </div>
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('exams.sections.assign', $exam) }}" id="sectionAssignmentForm">
                    @csrf
                    @if($groupingMode === 'section')
                        <div class="d-flex flex-wrap align-items-center gap-2 mb-3 p-3 bg-light rounded">
                            <strong>Assign selected questions:</strong>
                            <select name="question_section_id" class="form-select" style="max-width: 280px;">
                                <option value="">General / Unassigned</option>
                                @foreach($availableSections as $section)<option value="{{ $section->id }}">{{ $section->name }}</option>@endforeach
                            </select>
                            <button class="btn btn-primary" type="submit">Apply Section</button>
                            <span class="text-muted small">Select one or more questions below.</span>
                        </div>
                    @endif
                    <div class="table-responsive table-card mt-3 mb-1">
                        <table class="table align-middle">
                            <thead class="table-light"><tr>
                                @if($groupingMode === 'section')<th style="width:42px"><input type="checkbox" class="form-check-input" id="selectAllQuestions"></th><th>Section</th>@endif
                                <th>No</th><th>Marks</th><th>Subject</th><th>Topic</th><th>Sub Topic</th><th>Type</th><th>Body of Question</th><th>Level</th>@if($canEditQuestion)<th class="text-end">Action</th>@endif
                            </tr></thead>
                            <tbody>
                            @forelse($questions as $index => $question)
                                <tr>
                                    @if($groupingMode === 'section')
                                        <td><input type="checkbox" class="form-check-input js-question-checkbox" name="question_ids[]" value="{{ $question->id }}"></td>
                                        <td><span class="badge bg-primary-subtle text-primary">{{ $sections->firstWhere('id', $question->pivot->exam_section_id)?->name ?? 'General' }}</span></td>
                                    @endif
                                    <td>{{ $index + 1 }}</td><td>{{ $question->marks }}</td><td>{{ $question->subject?->subject_name ?? '-' }}</td><td>{{ $question->topic?->name ?? '-' }}</td><td>{{ $question->stopic?->name ?? '-' }}</td><td>{{ $question->qtype?->question_type ?? '-' }}</td><td>{{ Str::limit(strip_tags($question->question), 70) }}</td><td>{{ $question->diff?->diff_level ?? '-' }}</td>
                                    @if($canEditQuestion)<td class="text-end"><a href="{{ route('questions.edit', ['question' => $question->id, 'return_url' => request()->getRequestUri()]) }}" class="btn btn-sm btn-soft-primary"><i class="ri-pencil-line me-1"></i>Edit</a></td>@endif
                                </tr>
                            @empty
                                <tr><td colspan="{{ $timerMode === 'section' ? 11 : 9 }}" class="text-center text-muted py-4">No questions found.</td></tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@section('script')
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/js/select2.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    if (window.jQuery && jQuery.fn.select2) jQuery('.select2').select2({ width: '100%' });
    const keys = ['section', 'subject', 'topic', 'subtopic', 'qtype', 'diff'];
    document.getElementById('search-btn')?.addEventListener('click', function () {
        const url = new URL(window.location.href);
        keys.forEach(key => {
            const value = document.getElementById(key + '-filter')?.value || '';
            value ? url.searchParams.set(key, value) : url.searchParams.delete(key);
        });
        const question = document.getElementById('question-search')?.value.trim() || '';
        question ? url.searchParams.set('question', question) : url.searchParams.delete('question');
        window.location.href = url.toString();
    });
    document.getElementById('reset-btn')?.addEventListener('click', function () {
        const url = new URL(window.location.href);
        [...keys, 'question'].forEach(key => url.searchParams.delete(key));
        window.location.href = url.toString();
    });
    document.getElementById('selectAllQuestions')?.addEventListener('change', function () {
        document.querySelectorAll('.js-question-checkbox').forEach(box => box.checked = this.checked);
    });
    document.getElementById('sectionAssignmentForm')?.addEventListener('submit', function (event) {
        if (!document.querySelector('.js-question-checkbox:checked')) {
            event.preventDefault();
            alert('Select at least one question.');
        }
    });
});
</script>
@endsection

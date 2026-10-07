@extends('layouts.master')
@section('title', 'AI Answer Version History')
@section('content')
@component('components.breadcrumb')
@slot('li_1', 'AI Answers & Explanations')
@slot('title', 'Version history')
@endcomponent

@php
    $answerFields = [
        'correct_option_indices' => 'Correct option positions',
        'true_false' => 'True / false answer',
        'fill_blank' => 'Fill-in-the-blank answer',
        'fill_blank_config' => 'Fill-in-the-blank configuration',
        'nat_config' => 'Numerical answer configuration',
        'si_answer1' => 'Subjective reference answer',
        'diff_id' => 'Difficulty',
        'subject_id' => 'Subject',
        'topic_id' => 'Topic',
        'stopic_id' => 'Subtopic',
    ];
@endphp

<div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
    <div>
        <a href="{{ url()->previous() }}" class="text-muted"><i class="ri-arrow-left-line me-1"></i>Back</a>
        <h3 class="mt-2 mb-1">Question {{ $question->question_code ?: $question->id }}</h3>
        <div class="text-muted">
            {{ $question->qtype?->question_type }}
            &middot; {{ $question->subject?->subject_name ?: 'No subject' }}
            <i class="ri-arrow-right-s-line"></i> {{ $question->topic?->name ?: 'No topic' }}
            <i class="ri-arrow-right-s-line"></i> {{ $question->stopic?->name ?: 'No subtopic' }}
            &middot; Current difficulty: {{ $difficultyNames[$question->diff_id] ?? 'Not set' }}
        </div>
    </div>
    <a href="{{ route('questions.edit', $question) }}" target="_blank" rel="noopener" class="btn btn-primary">
        <i class="ri-external-link-line me-1"></i>View current published question
    </a>
</div>

<div class="alert alert-info">
    Each entry is the live answer and explanation saved immediately before an AI draft was published or a version was restored.
    Restoring never deletes the current content: it is saved first as another version.
</div>

<div class="card mb-3">
    <div class="card-header"><h4 class="mb-0">Current question</h4></div>
    <div class="card-body">
        <div class="mb-3">{!! $question->question !!}</div>
        @if($question->explanation)
            <div class="border rounded p-3"><strong>Current explanation</strong><div class="mt-2">{!! $question->explanation !!}</div></div>
        @endif
    </div>
</div>

<div class="card">
    <div class="card-header"><h4 class="mb-0">Saved versions</h4></div>
    <div class="card-body">
        @forelse($versions as $version)
            <div class="border rounded p-3 mb-3">
                <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
                    <div>
                        <h5 class="mb-1">Version #{{ $version->id }}</h5>
                        <div class="text-muted small">
                            Saved {{ $version->created_at->format('d M Y, h:i A') }}
                            &middot; by {{ $version->creator?->name ?: 'System' }}
                        </div>
                    </div>
                    <form method="POST" action="{{ route('ai-answers.versions.restore', $version) }}">
                        @csrf @method('PATCH')
                        <button class="btn btn-sm btn-outline-warning"
                            data-swal-title="Restore saved version"
                            data-swal-button="Restore"
                            data-swal-confirm="Restore this saved answer and explanation? The current live content will be saved first, so this action can also be reversed.">
                            <i class="ri-history-line me-1"></i>Restore this version
                        </button>
                    </form>
                </div>

                <div class="row g-3 mt-1">
                    <div class="col-lg-5">
                        <div class="border rounded p-3 h-100">
                            <strong>Saved answer data</strong>
                            @php $hasAnswer = false; @endphp
                            <dl class="row small mt-2 mb-0">
                                @foreach($answerFields as $field => $label)
                                    @if(array_key_exists($field, (array) $version->payload))
                                        @php $hasAnswer = true; @endphp
                                        <dt class="col-sm-5">{{ $label }}</dt>
                                        <dd class="col-sm-7 text-break">
                                            @if($field === 'diff_id')
                                                {{ $difficultyNames[data_get($version->payload, $field)] ?? 'Not set' }}
                                            @elseif($field === 'subject_id')
                                                {{ $subjectNames[data_get($version->payload, $field)] ?? 'Not set' }}
                                            @elseif($field === 'topic_id')
                                                {{ $topicNames[data_get($version->payload, $field)] ?? 'Not set' }}
                                            @elseif($field === 'stopic_id')
                                                {{ $subtopicNames[data_get($version->payload, $field)] ?? 'Not set' }}
                                            @elseif(is_array(data_get($version->payload, $field)))
                                                {{ json_encode(data_get($version->payload, $field), JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) }}
                                            @else
                                                {!! data_get($version->payload, $field) ?: '<span class="text-muted">Empty</span>' !!}
                                            @endif
                                        </dd>
                                    @endif
                                @endforeach
                            </dl>
                            @unless($hasAnswer)<span class="text-muted small">No saved answer fields.</span>@endunless
                        </div>
                    </div>
                    <div class="col-lg-7">
                        <div class="border rounded p-3 h-100">
                            <strong>Saved explanation</strong>
                            <div class="mt-2">{!! data_get($version->payload, 'explanation') ?: '<span class="text-muted">No explanation</span>' !!}</div>
                        </div>
                    </div>
                </div>
            </div>
        @empty
            <div class="text-center text-muted py-5">No AI answer versions have been saved for this question yet.</div>
        @endforelse
    </div>
    @if($versions->hasPages())<div class="card-footer">{{ $versions->links() }}</div>@endif
</div>
@endsection
@push('scripts')
@include('partials.student-mathjax')
<script>
if (window.MathJax?.typesetPromise) window.MathJax.typesetPromise();
@if(session('error')) Swal.fire({icon:'error', title:'Unable to restore', text:@json(session('error')), width:520}); @endif
@if(session('success')) Swal.fire({icon:'success', title:'Version restored', text:@json(session('success')), width:520}); @endif
</script>
@endpush

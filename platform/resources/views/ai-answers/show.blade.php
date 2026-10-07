@extends('layouts.master')
@section('title', 'Review AI Answer Drafts')
@section('content')
@component('components.breadcrumb')
@slot('li_1', 'AI Answers & Explanations')
@slot('title', $run->exam?->name)
@endcomponent
<style>
.ai-answer-status {
    display: inline-flex; align-items: center; gap: .35rem; flex: 0 0 auto;
    padding: .42rem .72rem; border-radius: 999px; font-size: .75rem;
    font-weight: 700; line-height: 1; white-space: nowrap;
}
.ai-answer-status--failed { color: #b42318; background: #fff1f0; border: 1px solid #f3b5b0; }
.ai-answer-status--ready { color: #067647; background: #ecfdf3; border: 1px solid #a6f4c5; }
.ai-answer-status--discrepancy { color: #b54708; background: #fffaeb; border: 1px solid #fedf89; }
.ai-answer-status--default { color: #344054; background: #f2f4f7; border: 1px solid #d0d5dd; }
.ai-answer-failure { border-left: 4px solid #d92d20; }
.ai-answer-failure code { color: #912018; white-space: normal; overflow-wrap: anywhere; }
</style>

<div class="card">
    <div class="card-body">
        <div class="d-flex flex-wrap justify-content-between gap-3 align-items-center">
            <div>
                <h4 class="mb-1">{{ $run->exam?->name }}</h4>
                <div class="text-muted">{{ ucfirst($run->status) }} · {{ $run->processed_questions }}/{{ $run->total_questions }} processed · Provider: {{ $run->provider === 'auto' ? 'Admin priority/fallback' : ucfirst($run->provider).' first, then fallback' }}</div>
            </div>
            <div class="d-flex flex-wrap gap-2">
                @if($run->failed_count > 0)
                    <form method="POST" action="{{ route('ai-answers.retry', $run) }}">@csrf
                        <button class="btn btn-outline-danger" data-swal-confirm="Retry every failed question in this paper using the configured provider fallback order?" data-swal-title="Retry failed questions" data-swal-button="Retry now">
                            <i class="ri-refresh-line me-1"></i> Retry {{ $run->failed_count }} failed
                        </button>
                    </form>
                @endif
                <a href="{{ route('ai-answers.index') }}" class="btn btn-light">Back to jobs</a>
            </div>
        </div>
        @if($run->additional_instructions)<div class="alert alert-light border mt-3 mb-0"><strong>Additional instructions:</strong> {{ $run->additional_instructions }}</div>@endif
        <div class="alert alert-light border mt-3 mb-0">
            <strong>Curriculum created by this paper:</strong>
            {{ $curriculumEventCounts->get('subject', 0) }} subjects
            &middot; {{ $curriculumEventCounts->get('topic', 0) }} topics
            &middot; {{ $curriculumEventCounts->get('subtopic', 0) }} subtopics
        </div>
    </div>
</div>

<form method="GET" class="card card-body">
    <div class="row g-2 align-items-end">
        <div class="col-md-4"><label class="form-label">Status</label><select name="status" class="form-select">
            <option value="">All statuses</option>
            @foreach(['discrepancy','ready','verified','failed','published','queued','processing'] as $value)<option value="{{ $value }}" @selected($status === $value)>{{ ucfirst($value) }}</option>@endforeach
        </select></div>
        <div class="col-auto"><button class="btn btn-primary">Filter</button></div>
        <div class="col-auto"><a href="{{ route('ai-answers.show', $run) }}" class="btn btn-secondary">Reset</a></div>
    </div>
</form>

<form method="POST" action="{{ route('ai-answers.publish') }}" id="publish-ai-drafts">
    @csrf
    <input type="hidden" name="run_id" value="{{ $run->id }}">
    <div class="card">
        <div class="card-header d-flex flex-wrap justify-content-between gap-2 align-items-center">
            <label class="mb-0"><input type="checkbox" id="select-ready" class="form-check-input me-2">Select all ready on this page</label>
            <div class="d-flex flex-wrap gap-2">
                <button name="scope" value="selected" class="btn btn-primary" data-swal-confirm="Publish only the selected ready questions? A restorable previous version is recorded for every question.">Publish selected questions</button>
                <button name="scope" value="paper" class="btn btn-primary" data-swal-confirm="Publish every approved ready draft in this paper? Discrepancies are excluded.">Publish ready paper</button>
                <button name="scope" value="batch" class="btn btn-warning" data-swal-confirm="Publish every approved ready draft across all papers created in this batch? Discrepancies are excluded.">Publish ready batch</button>
            </div>
        </div>
        <div class="card-body">
            @forelse($drafts as $draft)
                @php $question = $draft->question; @endphp
                <div class="border rounded p-3 mb-3">
                    <div class="d-flex justify-content-between gap-3">
                        <div class="d-flex gap-2">
                            @if($draft->status === 'ready')<input class="form-check-input ready-checkbox" type="checkbox" name="draft_ids[]" value="{{ $draft->id }}">@endif
                            <div><h5 class="mb-1">Question {{ $question->question_code ?: $question->id }}</h5>
                                <div class="text-muted small">{{ $question->qtype?->question_type }} · {{ str_replace('_', ' ', ucfirst($draft->mode)) }} · {{ $draft->provider ? ucfirst($draft->provider).' / '.$draft->model : 'Waiting' }}</div>
                            </div>
                        </div>
                        @php $statusClass = match($draft->status) {
                            'failed' => 'failed', 'ready' => 'ready',
                            'discrepancy' => 'discrepancy', default => 'default'
                        }; @endphp
                        <span class="ai-answer-status ai-answer-status--{{ $statusClass }}">
                            @if($draft->status === 'failed')<i class="ri-close-circle-line"></i>@endif {{ ucfirst($draft->status) }}
                        </span>
                    </div>
                    <div class="mt-3">{!! $question->question !!}</div>
                    @php $answerVerification = (array) data_get($draft->original_payload, '_answer_verification', []); @endphp
                    <div class="alert alert-light border mt-2 mb-2">
                        <strong>{{ ($answerVerification['status'] ?? '') === 'verified' ? 'Official answer preserved' : (($answerVerification['status'] ?? '') === 'review_required' ? 'Answer-key review required' : 'Unverified answer — independently solved') }}</strong>
                        <div class="small">{{ $answerVerification['reason'] ?? 'Older draft: regenerate to use the exam verification status.' }}</div>
                    </div>
                    @if(collect(range(1,6))->contains(fn($i) => filled($question->{'option'.$i})))
                        <ol type="A" class="mt-2">@foreach(range(1,6) as $i) @if(filled($question->{'option'.$i}))<li>{!! $question->{'option'.$i} !!}</li>@endif @endforeach</ol>
                    @endif
                    @if($draft->failure_message)
                        <div class="alert alert-danger ai-answer-failure mt-3 mb-0">
                            <div class="d-flex align-items-center gap-2 fw-semibold mb-1">
                                <i class="ri-error-warning-line"></i> Answer generation failed
                            </div>
                            <code>{{ $draft->failure_message }}</code>
                        </div>
                    @endif
                    @if($draft->discrepancies)
                        <div class="alert alert-warning"><strong>Independent-check discrepancy</strong><ul class="mb-0">@foreach($draft->discrepancies as $item)<li>{{ $item }}</li>@endforeach</ul></div>
                    @endif
                    @if($draft->proposed_payload)
                        <div class="row g-3">
                            <div class="col-lg-5"><div class="border rounded p-3 h-100">
                                <div class="d-flex flex-wrap justify-content-between gap-2 align-items-center">
                                    <strong>Proposed answer data</strong>
                                    @if(data_get($draft->proposed_payload, 'diff_id'))
                                        <span class="badge bg-info text-dark">AI difficulty: {{ $difficultyNames[data_get($draft->proposed_payload, 'diff_id')] ?? 'Unknown' }}</span>
                                    @endif
                                </div>
                                @if(data_get($draft->proposed_payload, '_curriculum'))
                                    <div class="alert alert-info py-2 px-3 mt-2 mb-2">
                                        <strong class="d-block mb-2">AI curriculum</strong>
                                        @foreach(['subject' => 'Subject', 'topic' => 'Topic', 'subtopic' => 'Subtopic'] as $key => $label)
                                            @php $curriculumStatus = data_get($draft->proposed_payload, '_curriculum_status.'.$key.'.status'); @endphp
                                            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-1">
                                                <span><strong>{{ $label }}:</strong> {{ data_get($draft->proposed_payload, '_curriculum.'.$key) }}</span>
                                                @if($curriculumStatus)
                                                    <span class="badge bg-{{ $curriculumStatus === 'new' ? 'warning text-dark' : 'success' }}">
                                                        {{ $curriculumStatus === 'new' ? 'Will create' : 'Existing' }}
                                                    </span>
                                                @endif
                                            </div>
                                        @endforeach
                                    </div>
                                @endif
                                <pre class="small text-wrap mb-0">{{ json_encode(collect($draft->proposed_payload)->except(['explanation','diff_id','_curriculum','_curriculum_status'])->all(), JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) }}</pre>
                                @if(($answerVerification['status'] ?? '') !== 'verified')
                                    <strong class="d-block mt-3">Previous unverified answer</strong>
                                    <pre class="small text-wrap mb-0">{{ json_encode(collect((array) $draft->original_payload)->only(['correct_option_indices','nat_config','true_false','fill_blank','fill_blank_config','si_answer1'])->filter(fn ($value) => $value !== null && $value !== '' && $value !== [])->all(), JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) }}</pre>
                                @endif
                            </div></div>
                            <div class="col-lg-7"><div class="border rounded p-3 h-100"><strong>Proposed explanation</strong><div class="mt-2">{!! data_get($draft->proposed_payload, 'explanation') ?: '<span class="text-muted">No explanation change</span>' !!}</div></div></div>
                        </div>
                    @endif
                    @if($draft->status === 'discrepancy')
                        <button type="submit" form="approve-{{ $draft->id }}" class="btn btn-sm btn-warning mt-3" data-swal-confirm="Approve this AI proposal after reviewing the discrepancy? It will still remain unpublished.">Approve proposal for publishing</button>
                    @endif
                    @if($draft->status === 'published')
                        @if($draft->curriculumEvents->isNotEmpty())
                            <div class="alert alert-success mt-3 mb-0">
                                <strong class="d-block mb-1">Curriculum created with this publication</strong>
                                @foreach($draft->curriculumEvents as $event)
                                    <div>
                                        {{ ucfirst($event->entity_type) }}: <strong>{{ $event->name }}</strong>
                                        <span class="text-muted">&middot; {{ $event->creator?->name ?: 'Deleted administrator' }} &middot; {{ $event->created_at?->format('d M Y H:i') }}</span>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                        <div class="d-flex flex-wrap gap-2 mt-3">
                            <a href="{{ route('questions.edit', $question) }}" target="_blank" rel="noopener" class="btn btn-sm btn-primary">
                                <i class="ri-external-link-line me-1"></i> View published question
                            </a>
                            <a href="{{ route('ai-answers.questions.versions', $question) }}" class="btn btn-sm btn-outline-primary">
                                <i class="ri-history-line me-1"></i> Version history ({{ $question->ai_answer_versions_count }})
                            </a>
                        </div>
                    @endif
                </div>
            @empty<div class="text-center text-muted py-5">No drafts match this filter.</div>@endforelse
        </div>
        @if($drafts->hasPages())<div class="card-footer">{{ $drafts->links() }}</div>@endif
    </div>
</form>
@foreach($drafts as $draft)
    @if($draft->status === 'discrepancy')<form id="approve-{{ $draft->id }}" method="POST" action="{{ route('ai-answers.drafts.approve', $draft) }}">@csrf @method('PATCH')</form>@endif
@endforeach
@endsection
@push('scripts')
@include('partials.student-mathjax')
<script>
document.getElementById('select-ready')?.addEventListener('change', e => document.querySelectorAll('.ready-checkbox').forEach(box => box.checked = e.target.checked));
@if(session('error')) Swal.fire({icon:'error', title:'Unable to continue', text:@json(session('error')), width:520}); @endif
@if(session('success')) Swal.fire({icon:'success', title:'Completed', text:@json(session('success')), width:520}); @endif
if (window.MathJax?.typesetPromise) window.MathJax.typesetPromise();
@if(in_array($run->status, ['queued','starting','running'])) setTimeout(() => location.reload(), 10000); @endif
</script>
@endpush

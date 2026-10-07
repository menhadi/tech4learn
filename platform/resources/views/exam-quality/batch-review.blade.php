@extends('layouts.master')
@section('title','Review Batch Repair Release')
@section('content')
<div class="d-flex flex-wrap justify-content-between gap-3 mb-3">
    <div><a href="{{ route('exam-quality.show',$audit) }}" class="text-muted"><i class="ri-arrow-left-line"></i> Back to audit</a><h3 class="mt-2 mb-1">Review paper repair release</h3><div class="text-muted">{{ $audit->exam?->name }} &middot; {{ $drafts->count() }} selected questions</div></div>
    <span class="badge bg-warning text-dark align-self-start">Nothing published yet</span>
</div>
<div class="alert alert-info"><strong>Atomic publication:</strong> all selected questions publish together. If any draft is stale or invalid, none are changed.</div>
<form method="POST" action="{{ route('exam-quality.repairs.batch-publish',$audit) }}" id="batch-publish-form">@csrf
    @foreach($drafts as $draft)<input type="hidden" name="draft_ids[]" value="{{ $draft->id }}">@endforeach
    <div class="card mb-3"><div class="card-body"><label class="form-label fw-semibold">Release name</label><input class="form-control" name="release_name" value="{{ old('release_name',$audit->exam?->name.' reviewed repair '.now()->format('Y-m-d')) }}"><div class="form-text">This name identifies the paper-level rollback point.</div></div></div>
    @foreach($drafts as $draft)
        <div class="card mb-3">
            <div class="card-header d-flex flex-wrap justify-content-between gap-2"><div><h4 class="mb-1">Question ID {{ $draft->question_id }}</h4><div class="text-muted">{{ $draft->question?->question_code }} &middot; {{ $draft->question?->qtype?->question_type }}</div></div><a href="{{ route('exam-quality.repairs.show',$draft) }}" target="_blank" class="btn btn-sm btn-outline-primary align-self-start">Open full draft</a></div>
            <div class="card-body">
                @forelse((array)$draft->changed_fields as $field)
                    @php $current=data_get($draft->original_payload,$field); @endphp
                    @php $proposed=data_get($draft->proposed_payload,$field); @endphp
                    <div class="border rounded p-3 mb-3"><div class="fw-semibold text-capitalize mb-2">{{ str_replace('_',' ',$field) }}</div><div class="row g-3"><div class="col-lg-6"><div class="small text-muted">Current</div><div class="repair-math-preview bg-light rounded p-2">{!! is_array($current)?e(json_encode($current,JSON_UNESCAPED_UNICODE)):$current !!}</div></div><div class="col-lg-6"><div class="small text-muted">Will publish</div><div class="repair-math-preview border rounded p-2">{!! is_array($proposed)?e(json_encode($proposed,JSON_UNESCAPED_UNICODE)):$proposed !!}</div></div></div></div>
                @empty<div class="alert alert-warning mb-0">This draft has no detected changed fields. Open it and review before publishing.</div>@endforelse
            </div>
        </div>
    @endforeach
    <div class="d-flex justify-content-end gap-2 mb-4"><a href="{{ route('exam-quality.show',$audit) }}" class="btn btn-outline-secondary">Cancel</a><button type="submit" class="btn btn-success" id="batch-publish-button"><i class="ri-check-double-line me-1"></i>Publish {{ $drafts->count() }} questions</button></div>
</form>
<script>
document.getElementById('batch-publish-form')?.addEventListener('submit', async function (event) {
    event.preventDefault();
    const form = this;
    const button = document.getElementById('batch-publish-button');
    const total = form.querySelectorAll('input[name="draft_ids[]"]').length;

    const confirmation = await Swal.fire({ icon: 'warning', title: 'Publish selected repairs?', text: `Publish all ${total} selected question repairs as one reversible release?`, showCancelButton: true, confirmButtonText: 'Publish' });
    if (!confirmation.isConfirmed) return;

    const original = button.innerHTML;
    button.disabled = true;
    button.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Publishing...';

    try {
        const response = await fetch(form.action, {
            method: 'POST',
            body: new FormData(form),
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        });
        const payload = await response.json().catch(() => ({}));
        if (!response.ok || !payload.redirect) {
            throw new Error(payload.message || 'The repair release could not be published. Please try again.');
        }
        window.location.assign(payload.redirect);
    } catch (error) {
        button.disabled = false;
        button.innerHTML = original;
        window.Swal
            ? Swal.fire({ icon: 'error', title: 'Publication failed', text: error.message, confirmButtonColor: '#087f73' })
            : alert(error.message);
    }
});
</script>
<script>window.MathJax={loader:{load:['[tex]/mhchem']},tex:{packages:{'[+]':['mhchem']},inlineMath:[['\\(','\\)'],['$','$']],displayMath:[['\\[','\\]'],['$$','$$']],processEscapes:true},options:{skipHtmlTags:['script','noscript','style','textarea','pre','code']}};</script>
<script defer src="https://cdn.jsdelivr.net/npm/mathjax@3/es5/tex-mml-chtml.js" onload="MathJax.typesetPromise(Array.from(document.querySelectorAll('.repair-math-preview'))).catch(()=>{})"></script>
@endsection

@extends('layouts.master')
@section('title',$import->name)
@section('content')
@php $statusColor=$import->status==='published'?'success':($import->status==='failed'?'danger':($import->status==='review'?'primary':'warning')); @endphp
<div class="container-fluid">
<div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3"><div><a href="{{ route('source-exams.index') }}" class="text-muted"><i class="ri-arrow-left-line"></i> Source exams</a><h3 class="mt-2 mb-1">{{ $import->name }}</h3><div class="text-muted">{{ $import->question_source_name }}</div></div><div class="d-flex flex-wrap align-items-center gap-2"><a class="btn btn-outline-primary" href="{{ route('source-exams.preview', $import) }}" target="_blank"><i class="ri-file-paper-2-line me-1"></i>Full paper preview</a><span class="badge bg-{{ $statusColor }} px-3 py-2">{{ ucfirst($import->status) }}</span></div></div>
<div class="row g-3 mb-3">
<div class="col-md-3"><div class="card h-100"><div class="card-body"><div class="text-muted">Detected</div><div class="fs-2 fw-semibold text-primary">{{ $import->detected_questions }}</div></div></div></div>
<div class="col-md-3"><div class="card h-100"><div class="card-body"><div class="text-muted">Ready</div><div class="fs-2 fw-semibold text-success">{{ (int) ($statusCounts['ready'] ?? 0) }}</div></div></div></div>
<div class="col-md-3"><div class="card h-100"><div class="card-body"><div class="text-muted">Needs review</div><div class="fs-2 fw-semibold text-warning">{{ (int) ($statusCounts['needs_review'] ?? 0) }}</div></div></div></div>
<div class="col-md-3"><div class="card h-100"><div class="card-body"><div class="text-muted">Published</div><div class="fs-2 fw-semibold text-dark">{{ (int) ($statusCounts['published'] ?? 0) }}</div></div></div></div>
</div>
@if($import->status==='draft')<div class="alert alert-secondary"><strong>Saved as an unprocessed draft.</strong><div>Select this paper from the Source Exams list and click <strong>Process selected drafts</strong> when ready.</div></div>@endif
@if(in_array($import->status,['queued','starting','processing']))<div class="alert alert-info d-flex align-items-center gap-3"><span class="spinner-border spinner-border-sm"></span><div><strong>Preparing the exam draft...</strong><div>This page updates automatically when processing finishes. Large or scanned sources can take longer.</div></div></div>@endif
@if($import->status==='failed')<div class="alert alert-danger"><strong>Extraction failed</strong><div>{{ $import->failure_message }}</div><form method="POST" action="{{ route('source-exams.retry',$import) }}" class="mt-2">@csrf<button class="btn btn-danger btn-sm"><i class="ri-refresh-line me-1"></i>Retry extraction</button></form></div>@endif
@if(in_array($import->status,['review','published'],true))
<div class="card mb-3"><div class="card-body d-flex flex-wrap align-items-center gap-2"><span class="fw-semibold me-2"><i class="ri-filter-3-line me-1"></i>Filter questions</span><a href="{{ route('source-exams.show', $import) }}" class="btn btn-sm {{ $statusFilter === null ? 'btn-primary' : 'btn-outline-primary' }}">All ({{ (int) $statusCounts->sum() }})</a><a href="{{ route('source-exams.show', ['sourceExam' => $import, 'status' => 'ready']) }}" class="btn btn-sm {{ $statusFilter === 'ready' ? 'btn-success' : 'btn-outline-success' }}">Ready ({{ (int) ($statusCounts['ready'] ?? 0) }})</a><a href="{{ route('source-exams.show', ['sourceExam' => $import, 'status' => 'needs_review']) }}" class="btn btn-sm {{ $statusFilter === 'needs_review' ? 'btn-warning' : 'btn-outline-warning' }}">Needs review ({{ (int) ($statusCounts['needs_review'] ?? 0) }})</a><a href="{{ route('source-exams.show', ['sourceExam' => $import, 'status' => 'published']) }}" class="btn btn-sm {{ $statusFilter === 'published' ? 'btn-dark' : 'btn-outline-dark' }}">Published ({{ (int) ($statusCounts['published'] ?? 0) }})</a></div></div>
@if($import->status==='published')
<div class="alert alert-success d-flex flex-wrap align-items-center justify-content-between gap-2">
    <div><strong>Published successfully.</strong> The extracted questions remain available below for review and editing.</div>
    <a class="btn btn-sm btn-outline-success" href="{{ route('exams.edit',$import->exam_id) }}"><i class="ri-settings-3-line me-1"></i>Exam settings</a>
</div>
@endif
@if($import->status==='review')
<form method="POST" action="{{ route('source-exams.publish',$import) }}" id="publish-source-exam-form">@csrf</form>
<div class="card mb-3"><div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-3"><div><div class="d-flex align-items-center gap-2"><input class="form-check-input" type="checkbox" id="select-all-drafts"><label class="fw-semibold" for="select-all-drafts">Select all visible</label><span class="text-muted small" id="selected-count">0 selected</span></div><div class="small text-muted mt-1">Needs review is advisory; administrators may still publish. If nothing is selected, all questions are published.</div></div><button class="btn btn-success" type="submit" form="publish-source-exam-form"><i class="ri-check-double-line me-1"></i>Publish exam</button></div></div>
@endif
@if($drafts->isEmpty())<div class="alert alert-light border text-muted">No questions match this filter.</div>@endif
<?php foreach ($drafts as $draft):
$payload = $draft->payload;
$plain = preg_replace('/\s+/u', ' ', trim(strip_tags($payload['question'] ?? '')));
$hasImage = str_contains(strtolower($payload['question'] ?? ''), '<img');
?>
<div class="card mb-3 source-question-card"><div class="card-header d-flex flex-wrap justify-content-between gap-2"><div class="d-flex gap-3 align-items-start">@if($import->status==='review')<input class="form-check-input draft-checkbox mt-1" type="checkbox" name="draft_ids[]" value="{{ $draft->id }}" form="publish-source-exam-form">@endif<div><h5 class="mb-1">Paper Q. {{ $draft->printed_question_number ?: $draft->paper_question_number }}</h5><span class="badge bg-{{ $draft->status==='needs_review'?'warning':'success' }}">{{ str_replace('_',' ',ucfirst($draft->status)) }}</span>@if($draft->ai_fields)<span class="badge bg-light text-dark ms-1">AI proposal - review required</span>@endif @if($hasImage)<span class="badge bg-light text-primary ms-1"><i class="ri-image-line"></i> Image</span>@endif</div></div><div class="d-flex flex-wrap gap-2"><a class="btn btn-sm btn-outline-secondary" href="{{ route('source-exams.drafts.preview',[$import,$draft]) }}" target="_blank"><i class="ri-file-paper-2-line me-1"></i>Preview</a>@if($import->status==='published' && $draft->question_id)<a class="btn btn-sm btn-outline-primary" href="{{ route('questions.edit',['question'=>$draft->question_id,'return_url'=>request()->getRequestUri()]) }}"><i class="ri-edit-line me-1"></i>Edit live question</a>@else<a class="btn btn-sm btn-outline-primary" href="{{ route('source-exams.drafts.edit',[$import,$draft]) }}"><i class="ri-crop-line me-1"></i>Review / edit / crop</a>@endif</div></div>
<div class="card-body"><p class="mb-0">{{ \Illuminate\Support\Str::limit($plain,500) }}</p></div></div>
<?php endforeach; ?>
{{ $drafts->links() }}
@endif
</div>
@push('scripts')<script>
document.addEventListener('DOMContentLoaded', function () {
    @if(in_array($import->status,['queued','starting','processing'])) setTimeout(() => window.location.reload(), 5000); @endif
    const serverWarning = @json(session('error') ?: session('warning'));
    if (serverWarning && window.Swal) Swal.fire({
        icon: @json(session('error') ? 'error' : 'warning'), title: @json(session('error') ? 'Publication failed' : 'Publication warning'),
        text: serverWarning, width: 760, confirmButtonColor: '#087f73'
    });
    const all = document.getElementById('select-all-drafts');
    const boxes = [...document.querySelectorAll('.draft-checkbox')];
    const count = document.getElementById('selected-count');
    const form = document.getElementById('publish-source-exam-form');
    function sync(){ if(count) count.textContent=boxes.filter(x=>x.checked).length+' selected'; if(all) all.checked=boxes.length>0&&boxes.every(x=>x.checked); }
    if(all) all.addEventListener('change',()=>{boxes.forEach(x=>x.checked=all.checked);sync()});
    boxes.forEach(x=>x.addEventListener('change',sync));
    form?.addEventListener('submit', async event => {
        event.preventDefault();
        if (!window.Swal) return;
        const selected = boxes.filter(box => box.checked).length;
        const result = await Swal.fire({icon:'warning',title:'Publish this exam?',text:selected ? selected+' selected questions will be published, including Needs review items.' : 'All questions will be published, including Needs review items.',showCancelButton:true,confirmButtonText:'Yes, publish',confirmButtonColor:'#087f73',cancelButtonColor:'#6c757d'});
        if(result.isConfirmed) form.submit();
    });
    sync();
});
</script>@endpush
@endsection

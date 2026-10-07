@extends('layouts.master')
@section('title', 'Question Repair Draft')
@section('content')
<style>
    .repair-math-preview, .repair-current-value { overflow: hidden; }
    .repair-math-preview img, .repair-current-value img { display:block; max-width:100%; height:auto; object-fit:contain; margin:12px auto; }
</style>
@php
    $labels = [
        'question'=>'Question text / formula / image','option1'=>'Option 1','option2'=>'Option 2','option3'=>'Option 3','option4'=>'Option 4','option5'=>'Option 5','option6'=>'Option 6',
        'answer'=>'Answer','true_false'=>'True/False answer','fill_blank'=>'Fill blank answer','fill_blank_config'=>'Multiple blank configuration','nat_config'=>'NAT configuration','correct_option_indices'=>'Correct option positions',
        'si_answer1'=>'Subjective answer','hint'=>'Hint','explanation'=>'Explanation',
    ];
    $original = (array) $draft->original_payload;
    $proposed = (array) $draft->proposed_payload;
    $legacyImageDraft = !data_get($draft->evidence, 'extracted_image.pipeline_version')
        && collect($proposed)->contains(fn ($value) => is_string($value) && stripos($value, '<img') !== false);
@endphp
<div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
    <div>
        <a href="{{ route('exam-quality.show', $draft->audit_id) }}" class="text-muted"><i class="ri-arrow-left-line"></i> Back to audit</a>
        <h3 class="mt-2 mb-1">Repair draft: Question ID {{ $draft->question_id }}</h3>
        <div class="text-muted">{{ $draft->exam?->name }} &middot; {{ $draft->question?->question_code }}</div>
    </div>
    <div class="d-flex flex-wrap align-items-center gap-2"><a class="btn btn-outline-primary" href="{{ route('exam-quality.repairs.preview', $draft) }}" target="_blank"><i class="ri-file-paper-2-line me-1"></i>Rendered question</a><a class="btn btn-outline-primary" href="{{ route('exam-quality.preview', $draft->audit_id) }}" target="_blank"><i class="ri-book-open-line me-1"></i>Full paper</a><a class="btn btn-primary" href="{{ route('exams.paper.edit', $draft->exam) }}"><i class="ri-edit-box-line me-1"></i>Edit full paper</a><span class="badge fs-6 bg-{{ $draft->status === 'published' ? 'success' : ($draft->status === 'needs_review' ? 'warning text-dark' : (in_array($draft->status,['failed','rejected']) ? 'danger' : 'primary')) }}">{{ ucfirst(str_replace('_',' ',$draft->status)) }}</span></div>
</div>

@if($sharedExams > 1)
<div class="alert alert-warning"><strong>Shared question:</strong> this question is used in {{ $sharedExams }} exams. Publishing updates the same global question in every linked exam. The previous version will be saved.</div>
@endif
@if($draft->failure_message)
<div class="alert alert-danger d-flex flex-wrap align-items-center justify-content-between gap-2">
    <div><strong>Repair preparation failed:</strong> {{ $draft->failure_message }}</div>
    @if($draft->status === 'failed')
    <form method="POST" action="{{ route('exam-quality.repairs.retry',$draft) }}">@csrf @method('PATCH')<button class="btn btn-danger"><i class="ri-refresh-line me-1"></i>Retry with current extractor</button></form>
    @endif
</div>
@endif
@if($draft->status === 'needs_review')<div class="alert alert-warning"><strong>Manual review required:</strong> {{ data_get($draft->evidence,'manual_review_reason','The audit found an issue, but no safe field-level change was produced. Edit the draft or regenerate it with the authoritative source.') }}</div>@endif
@if($legacyImageDraft && in_array($draft->status,['ready','needs_review','rejected']))
<div class="alert alert-warning d-flex flex-wrap align-items-center justify-content-between gap-2"><div><strong>Older image draft:</strong> this draft predates source-based question/option image placement. Regenerate it before publishing.</div><form method="POST" action="{{ route('exam-quality.repairs.retry',$draft) }}">@csrf @method('PATCH')<button class="btn btn-warning"><i class="ri-refresh-line me-1"></i>Regenerate with current extractor</button></form></div>
@endif
@if(in_array($draft->status,['queued','starting','processing']))
<div class="alert alert-info"><span class="spinner-border spinner-border-sm me-2"></span>The source PDFs/URLs are being compared. Refresh shortly; the live question remains unchanged.</div>
@endif

<div class="row g-3 mb-3">
    <div class="col-md-4"><div class="card h-100 mb-0"><div class="card-body"><div class="text-muted small">Provider</div><strong>{{ ucfirst(data_get($draft->evidence,'provider','Pending')) }}</strong></div></div></div>
    <div class="col-md-4"><div class="card h-100 mb-0"><div class="card-body"><div class="text-muted small">Confidence</div><strong>{{ $draft->confidence !== null ? rtrim(rtrim(number_format($draft->confidence,2),'0'),'.').'%' : 'Pending' }}</strong></div></div></div>
    <div class="col-md-4"><div class="card h-100 mb-0"><div class="card-body"><div class="text-muted small">Source reference</div><strong>{{ data_get($draft->evidence,'source_reference') ?: 'Not supplied' }}</strong></div></div></div>
</div>

@if(!empty(data_get($draft->evidence,'conflicts',[])))
<div class="alert alert-warning">
    <h5><i class="ri-error-warning-line me-1"></i>Source and academic proposals conflict</h5>
    <p class="mb-2">Nothing will be merged automatically. Review each field and save the value you approve.</p>
    @foreach(data_get($draft->evidence,'conflicts',[]) as $conflict)
        <div class="border-top pt-2 mt-2"><strong>{{ ucfirst(str_replace('_',' ',data_get($conflict,'field','field'))) }}</strong>
            <div class="row g-2 mt-1"><div class="col-md-6"><small class="text-muted">Source proposal</small><div class="bg-light rounded p-2">{{ is_scalar(data_get($conflict,'source_value')) ? data_get($conflict,'source_value') : json_encode(data_get($conflict,'source_value'),JSON_UNESCAPED_UNICODE) }}</div></div>
            <div class="col-md-6"><small class="text-muted">Academic proposal</small><div class="bg-light rounded p-2">{{ is_scalar(data_get($conflict,'academic_value')) ? data_get($conflict,'academic_value') : json_encode(data_get($conflict,'academic_value'),JSON_UNESCAPED_UNICODE) }}</div></div></div>
        </div>
    @endforeach
</div>
@endif

@if(data_get($draft->evidence,'summary') || data_get($draft->evidence,'source_excerpt') || data_get($draft->evidence,'image_instruction') || data_get($draft->evidence,'image_instructions') || data_get($draft->evidence,'extracted_image.status'))
<div class="card mb-3"><div class="card-body">
    @if(data_get($draft->evidence,'summary'))<p><strong>Repair summary:</strong> {{ data_get($draft->evidence,'summary') }}</p>@endif
    @if(data_get($draft->evidence,'source_excerpt'))<p><strong>Source excerpt:</strong> {{ data_get($draft->evidence,'source_excerpt') }}</p>@endif
    @php
        $pdfImageInstructions = (array) data_get($draft->evidence,'image_instructions',[]);
        if ($pdfImageInstructions === [] && data_get($draft->evidence,'image_instruction')) $pdfImageInstructions[] = data_get($draft->evidence,'image_instruction');
    @endphp
    @if($pdfImageInstructions !== [])
        <div class="alert alert-warning mb-0"><strong>Authoritative PDF visuals:</strong> {{ count($pdfImageInstructions) }} crop(s) will rebuild the stored image placement.
            @foreach($pdfImageInstructions as $instruction)
                <div class="small mt-1">Page {{ data_get($instruction,'source_page','?') }} to {{ ucfirst(data_get($instruction,'target_field','question')) }}: {{ data_get($instruction,'description','PDF visual') }}</div>
            @endforeach
        </div>
    @endif
    @php
        $imageStatus = data_get($draft->evidence,'extracted_image.status');
        $imageResolved = in_array($imageStatus, ['canonical_source_visuals_applied','source_visuals_synchronized','source_visual_synchronized','manually_cropped'], true);
    @endphp
    @if($imageStatus)
        <div class="alert alert-{{ $imageResolved ? 'success' : 'danger' }} mt-2 mb-0">
            <strong>Automatic image extraction:</strong> {{ ucfirst(str_replace('_',' ',$imageStatus)) }}.
            @if(data_get($draft->evidence,'extracted_image.message'))
                <div class="mt-1">{{ data_get($draft->evidence,'extracted_image.message') }}</div>
            @endif
        </div>
    @endif
</div></div>
@endif

@include('partials.structured-content-review', [
    'structuredItems' => data_get($draft->evidence, 'structured_content', []),
    'structuredAction' => route('exam-quality.repairs.structured-content', $draft),
    'structuredSourceBase' => url('exam-quality/repairs/'.$draft->id.'/structured-content'),
])

<div class="row g-3 align-items-start audit-repair-workspace">
    <div class="col-xl-7"><div class="audit-source-workspace">
@include('question-drafts.manual-cropper', [
    'cropAction' => route('exam-quality.repairs.crop', $draft),
    'cropPageUrl' => route('exam-quality.repairs.source-page', $draft),
    'cropEvidence' => (array) $draft->evidence,
    'cropPayload' => array_replace((array) $draft->original_payload, (array) $draft->proposed_payload),
    'cropDefaultPage' => $cropDefaultPage ?? null,
    'cropHasSourceRoles' => true,
])
    </div></div>
    <div class="col-xl-5">

@if(in_array($draft->status,['ready','needs_review','rejected']))
<form method="POST" action="{{ route('exam-quality.repairs.update',$draft) }}" data-disable-ai-inline>@csrf @method('PATCH')
    <div class="card">
        <div class="card-header"><h4 class="mb-1">Compare and edit draft</h4><div class="text-muted">Only publishing applies these values to the live question. Use MathJax source delimiters for every formula or chemistry expression; pre-rendered formula markup is never stored.</div></div>
        <div class="card-body">
            @foreach($labels as $field=>$label)
                @php
                    $oldValue = $original[$field] ?? null;
                    $newValue = array_key_exists($field,$proposed) ? $proposed[$field] : $oldValue;
                    $isJson = in_array($field,['fill_blank_config','nat_config','correct_option_indices']);
                    if($isJson) { $oldValue = $oldValue ? json_encode($oldValue, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE) : ''; $newValue = $newValue ? json_encode($newValue, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE) : ''; }
                    $changed = in_array($field,(array)$draft->changed_fields,true);
                @endphp
                <div class="border rounded p-3 mb-3 {{ $changed ? 'border-warning' : '' }}">
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2"><strong>{{ $label }}</strong><div class="d-flex align-items-center gap-2">@if(in_array($field,['question','option1','option2','option3','option4','option5','option6','explanation'],true))<button type="button" class="btn btn-sm btn-outline-primary" data-crop-into="{{ $field }}"><i class="ri-crop-line me-1"></i>Crop into this field</button>@endif @if($changed)<span class="badge bg-warning text-dark">Proposed change</span>@endif</div></div>
                    <div class="row g-3">
                        <div class="col-12"><label class="form-label text-muted small">Current live value</label><div class="form-control bg-light repair-current-value" style="min-height:72px;white-space:pre-wrap">{!! $isJson ? e($oldValue) : ($oldValue ?: '<span class="text-muted">Empty</span>') !!}</div></div>
                        <div class="col-12">
                            <label class="form-label text-muted small">Repair draft</label>
                            <textarea class="form-control" rows="3" name="proposed[{{ $field }}]" @if(!$isJson && in_array($field,['question','option1','option2','option3','option4','option5','option6','explanation'])) data-repair-preview="repair-preview-{{ $field }}" @endif>{{ $newValue }}</textarea>
                            @if(!$isJson && in_array($field,['question','option1','option2','option3','option4','option5','option6','explanation']))
                                <div class="small text-muted mt-2">Live MathJax preview</div>
                                <div id="repair-preview-{{ $field }}" class="border rounded p-2 mt-1 repair-math-preview">{!! $newValue ?: '<span class="text-muted">Empty</span>' !!}</div>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
        <div class="card-footer d-flex flex-wrap gap-2 justify-content-end">
            <button class="btn btn-primary"><i class="ri-save-line me-1"></i>Save draft</button>
        </div>
    </div>
</form>
<div class="d-flex flex-wrap gap-2 justify-content-end mt-3">
    <form method="POST" action="{{ route('exam-quality.repairs.retry',$draft) }}">@csrf @method('PATCH')<button class="btn btn-outline-primary"><i class="ri-refresh-line me-1"></i>Regenerate from source</button></form>
    <form method="POST" action="{{ route('exam-quality.repairs.reject',$draft) }}">@csrf @method('PATCH')<button class="btn btn-outline-danger"><i class="ri-close-line me-1"></i>Reject draft</button></form>
    <form method="POST" action="{{ route('exam-quality.repairs.publish',$draft) }}" data-swal-confirm="{{ $draft->status === 'needs_review' ? 'Admin override: publish these changes while audit warnings or errors remain?' : 'Publish these approved changes to the live question?' }} The previous version will be saved.">@csrf @method('PATCH') @if($draft->status === 'needs_review')<input type="hidden" name="force_publish" value="1">@endif<button class="btn btn-{{ $draft->status === 'needs_review' ? 'warning' : 'success' }}" {{ empty($draft->changed_fields) ? 'disabled' : '' }} title="{{ empty($draft->changed_fields) ? 'This draft has no changes to publish' : 'Publish reviewed changes and save the previous version' }}"><i class="ri-check-double-line me-1"></i>{{ $draft->status === 'needs_review' ? 'Publish anyway' : 'Approve & publish' }}</button></form>
</div>
@elseif(in_array($draft->status,['failed']))
<form method="POST" action="{{ route('exam-quality.repairs.retry',$draft) }}">@csrf @method('PATCH')<button class="btn btn-primary"><i class="ri-refresh-line me-1"></i>Retry from source</button></form>
@elseif($draft->status === 'published')
<div class="alert alert-success"><i class="ri-checkbox-circle-line me-1"></i>Published {{ optional($draft->published_at)->diffForHumans() }}. The previous question content is stored in version history and related findings were resolved.</div>
@endif

    </div>
</div>
<style>@media (min-width:1200px){.audit-source-workspace{position:sticky;top:80px}.audit-source-workspace #source-crop-stage{height:calc(100vh - 330px)!important;min-height:420px}}</style>

@if($versions->isNotEmpty())
<div class="card mt-4">
    <div class="card-header"><h4 class="mb-1">Version history</h4><div class="text-muted">Every publish or restore keeps the content it replaces, so rollback is reversible.</div></div>
    <div class="card-body p-0"><div class="table-responsive"><table class="table mb-0"><thead><tr><th>Saved</th><th>Saved by</th><th>Previous question preview</th><th></th></tr></thead><tbody>
    @foreach($versions as $version)<tr><td>{{ $version->created_at->format('d M Y, h:i A') }}</td><td>{{ $version->creator?->name ?: 'System' }}</td><td><div class="text-truncate" style="max-width:520px">{{ strip_tags((string)data_get($version->payload,'question')) }}</div></td><td class="text-end"><form method="POST" action="{{ route('exam-quality.versions.restore',$version) }}" data-swal-confirm="Restore this saved version? The current content will be saved first.">@csrf @method('PATCH')<button class="btn btn-sm btn-outline-primary"><i class="ri-history-line me-1"></i>Restore</button></form></td></tr>@endforeach
    </tbody></table></div></div>
</div>
@endif

<script>
window.MathJax = { loader: { load: ['[tex]/mhchem'] }, tex: { packages: {'[+]': ['mhchem']}, inlineMath: [['\\(','\\)'],['$','$']], displayMath: [['\\[','\\]'],['$$','$$']], processEscapes: true }, options: { skipHtmlTags: ['script','noscript','style','textarea','pre','code'] } };
</script>
<script defer src="https://cdn.jsdelivr.net/npm/mathjax@3/es5/tex-mml-chtml.js" onload="MathJax.typesetPromise(Array.from(document.querySelectorAll('.repair-math-preview'))).catch(()=>{})"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-crop-into]').forEach(button => button.addEventListener('click', () => {
        document.dispatchEvent(new CustomEvent('manual-crop-target', {detail:{target:button.dataset.cropInto}}));
    }));
    document.querySelectorAll('[data-repair-preview]').forEach(textarea => {
        let timer;
        textarea.addEventListener('input', () => {
            clearTimeout(timer);
            timer = setTimeout(async () => {
                const preview = document.getElementById(textarea.dataset.repairPreview);
                if (!preview) return;
                preview.innerHTML = textarea.value || '<span class="text-muted">Empty</span>';
                if (window.MathJax?.typesetPromise) {
                    window.MathJax.typesetClear?.([preview]);
                    await window.MathJax.typesetPromise([preview]).catch(() => {});
                }
            }, 180);
        });
    });


});
</script>

@if(in_array($draft->status,['queued','starting','processing']))<script>setTimeout(()=>location.reload(),10000)</script>@endif
@endsection
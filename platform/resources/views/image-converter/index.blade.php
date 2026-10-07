@extends('layouts.admin')
@section('title', 'Image Format Converter')
@section('content')
@component('components.breadcrumb') @slot('li_1', 'Assessments') @slot('title', 'Image Format Converter') @endcomponent

@if(!$capabilities['svg'] || !$capabilities['webp'])
<div class="alert alert-warning"><strong>Server capability:</strong>
    SVG {{ $capabilities['svg'] ? 'ready' : 'unavailable' }} · WebP {{ $capabilities['webp'] ? 'ready' : 'unavailable' }}.
    Install Imagick with SVG/WebP delegates to support both formats.
</div>
@endif

<div class="card mb-4">
    <div class="card-header"><h5 class="mb-1">Find SVG and WebP question images</h5><div class="small text-muted">Choose the hierarchy from left to right. Each level supports multiple selections.</div></div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-lg-4"><label class="form-label" for="converter-groups">1. Groups</label><select id="converter-groups" class="form-select" multiple size="6">@foreach($groups as $group)<option value="{{ $group->id }}">{{ $group->group_name }}</option>@endforeach</select></div>
            <div class="col-lg-4"><label class="form-label" for="converter-categories">2. Categories</label><select id="converter-categories" class="form-select" multiple size="6"></select></div>
            <div class="col-lg-4"><label class="form-label" for="converter-exams">3. Exams</label><select id="converter-exams" class="form-select" multiple size="6"></select></div>
        </div>
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mt-3">
            <span class="small text-muted" id="converter-hierarchy-note">Select one or more exams to inspect.</span>
            <button type="button" class="btn btn-primary" id="converter-load"><i class="ri-search-eye-line me-1"></i>Show images requiring conversion</button>
        </div>
    </div>
</div>

<div class="card mb-4 d-none" id="converter-preview-card">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2"><div><h5 class="mb-1">Images requiring conversion</h5><div class="small text-muted"><span id="converter-count">0</span> local SVG/WebP references found</div></div><div class="d-flex gap-2"><input type="search" id="converter-search" class="form-control form-control-sm" placeholder="Search rows"><button type="button" class="btn btn-sm btn-outline-primary" id="converter-select-visible">Select visible</button><button type="button" class="btn btn-sm btn-outline-secondary" id="converter-clear">Clear</button></div></div>
    <form method="POST" action="{{ route('image-converter.store') }}" id="converter-form">@csrf
        <div id="converter-exam-inputs"></div>
        <div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th style="width:45px"></th><th>Existing image</th><th>Exam / Question</th><th>Location</th><th>Format</th><th>Ready</th></tr></thead><tbody id="converter-preview-body"></tbody></table></div>
        <div class="card-footer d-flex flex-wrap justify-content-between align-items-center gap-2"><strong><span id="converter-selected-count">0</span> image(s) selected</strong><button class="btn btn-success" id="converter-submit" disabled data-swal-confirm="Back up and convert the selected server images to PNG, then update their question references?"><i class="ri-file-transfer-line me-1"></i>Convert selected to PNG</button></div>
    </form>
</div>

<div class="card">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2"><div><h5 class="mb-1">Conversion results</h5><div class="small text-muted">Original and new URLs remain together for quick checking.</div></div><div class="btn-group btn-group-sm">
        <a class="btn btn-{{ !$status ? 'primary' : 'outline-primary' }}" href="{{ route('image-converter.index', request()->except(['status','page'])) }}">All</a>
        @foreach(['success'=>'Success','failed'=>'Failure','queued'=>'Queued','processing'=>'Processing'] as $value=>$label)<a class="btn btn-{{ $status===$value ? 'primary' : 'outline-primary' }}" href="{{ route('image-converter.index', array_merge(request()->except(['status','page']), ['status'=>$value])) }}">{{ $label }}</a>@endforeach
    </div></div>
    <div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Status</th><th>Original image URL</th><th>New PNG URL</th><th>Exam / Question</th><th>Server files</th><th></th></tr></thead><tbody>
        @forelse($items as $item)
        <tr>
            <td><span class="badge bg-{{ $item->status==='success'?'success':($item->status==='failed'?'danger':'warning') }}">{{ ucfirst($item->status) }}</span>@if($item->failure_message)<div class="small text-danger mt-1" style="max-width:260px">{{ $item->failure_message }}</div>@endif</td>
            <td class="text-break" style="min-width:230px"><a href="{{ $item->original_src }}" target="_blank" rel="noopener">{{ $item->original_src }}</a></td>
            <td class="text-break" style="min-width:230px">@if($item->new_src)<a href="{{ $item->new_src }}" target="_blank" rel="noopener">{{ $item->new_src }}</a>@else<span class="text-muted">Pending</span>@endif</td>
            <td><strong>{{ $item->exam?->name }}</strong><div class="small text-muted">{{ $item->question?->question_code ?: '#'.$item->question_id }} · {{ IlluminateSupportStr::headline($item->field) }} · image {{ $item->image_index + 1 }}</div></td>
            <td class="small text-break" style="min-width:220px">@if($item->backup_path)<div><strong>Backup:</strong> {{ $item->backup_path }}</div>@endif @if($item->png_path)<div><strong>PNG:</strong> {{ $item->png_path }}</div>@endif</td>
            <td>@if($item->status==='failed')<form method="POST" action="{{ route('image-converter.items.retry',$item) }}">@csrf<button class="btn btn-sm btn-outline-danger">Retry</button></form>@endif</td>
        </tr>
        @empty<tr><td colspan="6" class="text-center text-muted py-5">No conversion rows match this status.</td></tr>@endforelse
    </tbody></table></div><div class="card-footer">{{ $items->links() }}</div>
</div>
@endsection

@section('script')
<script>
document.addEventListener('DOMContentLoaded',()=>{
 const categories=@json($categoryOptions),exams=@json($examOptions),previewUrl=@json(route('image-converter.preview'));
 const groups=document.getElementById('converter-groups'),category=document.getElementById('converter-categories'),exam=document.getElementById('converter-exams'),load=document.getElementById('converter-load'),card=document.getElementById('converter-preview-card'),body=document.getElementById('converter-preview-body'),search=document.getElementById('converter-search'),submit=document.getElementById('converter-submit');
 const selected=el=>[...el.selectedOptions].map(o=>Number(o.value)),escape=s=>String(s??'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#039;');let images=[];
 const refillCategories=()=>{const gids=selected(groups),old=selected(category);category.innerHTML=categories.filter(c=>!gids.length||c.group_ids.some(id=>gids.includes(id))).map(c=>`<option value="${c.id}" ${old.includes(c.id)?'selected':''}>${escape(c.title)}</option>`).join('');refillExams();};
 const refillExams=()=>{const gids=selected(groups),cids=selected(category),old=selected(exam);exam.innerHTML=exams.filter(e=>(!gids.length||e.group_ids.some(id=>gids.includes(id)))&&(!cids.length||cids.includes(e.category_id)||cids.includes(e.subcategory_id))).map(e=>`<option value="${e.id}" ${old.includes(e.id)?'selected':''}>${escape(e.name)}</option>`).join('');document.getElementById('converter-hierarchy-note').textContent=`${exam.options.length} exam(s) available; ${selected(exam).length} selected.`;};
 const sync=()=>{const count=body.querySelectorAll('.converter-check:checked').length;document.getElementById('converter-selected-count').textContent=count;submit.disabled=count===0;};
 const render=()=>{const term=search.value.trim().toLowerCase();body.innerHTML=images.map(i=>{const visible=!term||`${i.exam_name} ${i.question_code} ${i.field_label} ${i.question_text} ${i.src}`.toLowerCase().includes(term);return `<tr class="converter-row ${visible?'':'d-none'}"><td><input class="form-check-input converter-check" type="checkbox" name="selected_images[]" value="${escape(i.token)}" ${i.local?'':'disabled'}></td><td><img src="${escape(i.src)}" alt="Existing image" loading="lazy" style="width:110px;height:70px;object-fit:contain;background:#f1f5f9;border-radius:6px"></td><td><strong>${escape(i.exam_name)}</strong><div class="small text-muted">${escape(i.question_code)} · ${escape(i.field_label)} · image ${i.image_index+1}</div><div class="small">${escape(i.question_text)}</div></td><td class="text-break" style="min-width:260px"><a href="${escape(i.src)}" target="_blank" rel="noopener">${escape(i.src)}</a></td><td><span class="badge bg-secondary">${escape(i.format.toUpperCase())}</span></td><td>${i.local?'<span class="badge bg-success">Local</span>':'<span class="badge bg-danger">Remote</span>'}</td></tr>`}).join('')||'<tr><td colspan="6" class="text-center text-muted py-5">No SVG/WebP images were found in the selected exams.</td></tr>';document.getElementById('converter-count').textContent=images.length;sync();};
 groups.addEventListener('change',refillCategories);category.addEventListener('change',refillExams);exam.addEventListener('change',refillExams);search.addEventListener('input',render);body.addEventListener('change',sync);
 load.addEventListener('click',async()=>{const ids=selected(exam);if(!ids.length){alert('Select at least one exam.');return;}load.disabled=true;load.innerHTML='<span class="spinner-border spinner-border-sm me-1"></span>Scanning server images...';try{const response=await fetch(previewUrl,{method:'POST',headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':@json(csrf_token())},body:JSON.stringify({exam_ids:ids})});if(!response.ok)throw new Error('Scan failed');images=(await response.json()).images||[];document.getElementById('converter-exam-inputs').innerHTML=ids.map(id=>`<input type="hidden" name="exam_ids[]" value="${id}">`).join('');card.classList.remove('d-none');render();card.scrollIntoView({behavior:'smooth',block:'start'});}catch(e){alert('The server could not scan the selected exams. Please try again.');}finally{load.disabled=false;load.innerHTML='<i class="ri-search-eye-line me-1"></i>Show images requiring conversion';}});
 document.getElementById('converter-select-visible').addEventListener('click',()=>{body.querySelectorAll('.converter-row:not(.d-none) .converter-check:not(:disabled)').forEach(c=>c.checked=true);sync();});document.getElementById('converter-clear').addEventListener('click',()=>{body.querySelectorAll('.converter-check').forEach(c=>c.checked=false);sync();});
 refillCategories();
 @if($active) setTimeout(()=>location.reload(),8000); @endif
});
</script>
@endsection

@extends('layouts.master')
@section('title', 'Math Content Cleanup')

@section('content')
@component('components.breadcrumb') @slot('li_1', 'AI Tools') @slot('title', 'Math Content Cleanup') @endcomponent

<div id="normalizationAlert"></div>
<div class="card mb-4">
    <div class="card-header">
        <h4 class="mb-1">Normalize stored mathematical content</h4>
        <div class="text-muted">Choose papers with the same Group, Category, Package and search flow used by Image Cleanup Bot. Preview first; Apply creates a restorable backup for every changed question.</div>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-4"><label class="form-label" for="normalization-group">Group</label><select id="normalization-group" class="form-select"><option value="">All groups</option>@foreach($groups as $group)<option value="{{ $group->id }}">{{ $group->group_name }}</option>@endforeach</select></div>
            <div class="col-md-4"><label class="form-label" for="normalization-category">Category</label><select id="normalization-category" class="form-select"><option value="">All categories</option></select></div>
            <div class="col-md-4"><label class="form-label" for="normalization-package">Package</label><select id="normalization-package" class="form-select"><option value="">All packages</option></select></div>
            <div class="col-12">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
                    <label class="form-label mb-0" for="normalization-exam-search">Search and select papers</label>
                    <div class="d-flex flex-wrap align-items-center gap-2"><span class="badge bg-light text-dark border">First 30 matching papers shown</span><button type="button" class="btn btn-sm btn-primary" id="normalization-all-filtered" disabled><i class="ri-checkbox-multiple-line me-1"></i>Select all matching papers</button></div>
                </div>
                <input type="search" id="normalization-exam-search" class="form-control" placeholder="Type exam name; results respect Group, Category and Package">
                <div id="normalization-exam-results" class="list-group mt-2"></div>
                <div class="small text-muted mt-1" id="normalization-exam-note">Tick one or more papers, or select every matching paper.</div>
            </div>
        </div>

        <div class="border rounded mt-4 d-none" id="normalization-selection-panel">
            <div class="p-3 border-bottom d-flex flex-wrap justify-content-between align-items-center gap-2">
                <div><h5 class="mb-1" id="normalization-selection-title">No papers selected</h5><div class="small text-muted" id="normalization-selection-note"></div></div>
                <button type="button" class="btn btn-sm btn-outline-secondary" id="normalization-clear-papers">Clear papers</button>
            </div>
            <div class="p-3 d-flex flex-wrap gap-2" id="normalization-selected-papers"></div>
        </div>

        <div class="row g-3 mt-1">
            <div class="col-md-6"><label class="form-label" for="normalizationFromId">Question ID from <span class="text-muted">(optional)</span></label><input id="normalizationFromId" type="number" min="1" class="form-control"></div>
            <div class="col-md-6"><label class="form-label" for="normalizationToId">Question ID to <span class="text-muted">(optional)</span></label><input id="normalizationToId" type="number" min="1" class="form-control"></div>
            <div class="col-12">
                <label class="form-label" for="normalizationContentScope">Cleanup scope</label>
                <select id="normalizationContentScope" class="form-select">
                    <option value="all_content" selected>All selected question content (recommended)</option>
                    <option value="mathml_only">MathML fields only</option>
                </select>
                <div class="form-text">Recommended mode converts stored MathML, keeps semantic HTML such as &lt;p&gt;, &lt;sup&gt; and &lt;sub&gt;, preserves existing LaTeX, and removes unsupported source-site wrappers and classes. MathML-only mode skips fields that contain no MathML.</div>
            </div>
        </div>
        <div class="alert alert-info mt-3 mb-3">Preview does not change data. Apply backs up every changed record, and Restore skips records edited later.</div>
        <div class="form-check mb-3"><input class="form-check-input" type="checkbox" id="confirmNormalizationApply"><label class="form-check-label" for="confirmNormalizationApply">I understand Apply changes this organization’s database content and creates restorable backups.</label></div>
        <div class="d-flex flex-wrap gap-2"><button type="button" class="btn btn-outline-primary" data-start-mode="preview"><i class="ri-search-eye-line me-1"></i>Run Preview</button><button type="button" class="btn btn-primary" data-start-mode="apply"><i class="ri-play-circle-line me-1"></i>Apply Cleanup</button></div>
    </div>
</div>

<div class="card mb-4 d-none" id="activeRunPanel"><div class="card-header"><h5 class="mb-0" id="activeRunTitle">Processing</h5></div><div class="card-body"><div class="progress mb-3" style="height:22px"><div id="activeRunProgress" class="progress-bar" style="width:0%">0%</div></div><div id="activeRunSummary" class="small text-muted"></div></div></div>

<div class="card">
    <div class="card-header">
        <h5 class="mb-1">Recent cleanup runs and papers</h5>
        <div class="small text-muted">Expand a run to see its papers. Restore one paper, selected papers, or all remaining papers. Shared questions or passages are restored everywhere they are used.</div>
    </div>
    <div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead><tr><th>Started</th><th>Mode</th><th>Papers</th><th>Result</th><th>Status</th><th class="text-end">Action</th></tr></thead><tbody>
    @forelse($runs as $run)
        @php $stats = $run->stats ?: []; @endphp
        @php $papers = $runPaperOptions[$run->id] ?? []; @endphp
        @php $remainingPapers = $run->mode === 'apply' ? collect($papers)->where('available', true)->where('restored', false) : collect(); @endphp
        <tr data-cleanup-run-row="{{ $run->id }}">
            <td>{{ $run->created_at?->format('Y-m-d H:i') }}<div class="small text-muted">{{ $run->requester?->name ?: 'System' }}</div></td>
            <td>{{ ucfirst($run->mode) }}</td>
            <td style="min-width:320px">
                <details>
                    <summary class="text-primary cursor-pointer">{{ count($papers) }} paper(s) @if($run->mode === 'apply')&middot; {{ $remainingPapers->count() }} restorable @endif</summary>
                    <div class="border rounded p-2 mt-2" style="max-height:260px;overflow:auto">
                        @if($run->mode === 'apply' && $remainingPapers->isNotEmpty())
                            <label class="form-check mb-2 border-bottom pb-2"><input class="form-check-input cleanup-run-select-all" type="checkbox" data-run="{{ $run->id }}"> <span class="form-check-label fw-semibold">Select all remaining</span></label>
                        @endif
                        @forelse($papers as $paper)
                            <div class="d-flex align-items-center gap-2 py-1">
                                @if($run->mode === 'apply' && !$paper['restored'] && $paper['available'] && in_array($run->status, ['completed','restore_partial']))
                                    <input class="form-check-input cleanup-paper-restore-check" type="checkbox" data-run="{{ $run->id }}" value="{{ $paper['id'] }}" aria-label="Select {{ $paper['name'] }}">
                                @else<span style="width:1rem"></span>@endif
                                <span class="flex-grow-1">{{ $paper['name'] }} <small class="text-muted">({{ number_format($paper['question_count']) }} questions)</small></span>
                                @if($paper['restored'])<span class="badge bg-secondary">Restored</span>
                                @elseif(!$paper['available'])<span class="badge bg-light text-dark border">Deleted</span>
                                @elseif($run->mode === 'apply' && in_array($run->status, ['completed','restore_partial']))<button type="button" class="btn btn-sm btn-link text-danger p-0 cleanup-restore-one" data-run="{{ $run->id }}" data-exam="{{ $paper['id'] }}">Restore</button>@endif
                            </div>
                        @empty<div class="text-muted small">No papers recorded for this run.</div>@endforelse
                    </div>
                </details>
            </td>
            <td>{{ number_format($run->processed_questions) }} / {{ number_format($run->total_questions) }}<div class="small text-muted">Converted {{ number_format($stats['converted'] ?? 0) }} &middot; Review {{ number_format($stats['needs_review'] ?? 0) }} &middot; Applied {{ number_format($stats['applied_records'] ?? 0) }}</div></td>
            <td><span class="badge bg-{{ in_array($run->status, ['completed','restored']) ? 'success' : (in_array($run->status, ['failed','restore_partial']) ? 'danger' : 'warning') }}">{{ str_replace('_', ' ', ucfirst($run->status)) }}</span>@if($run->error)<div class="text-danger small">{{ $run->error }}</div>@endif</td>
            <td class="text-end">
                @if(in_array($run->status, ['pending','running']))<button class="btn btn-sm btn-outline-primary" data-resume-run="{{ $run->id }}">Resume</button>
                @elseif($run->status === 'restoring')<button class="btn btn-sm btn-outline-primary" data-resume-restore="{{ $run->id }}">Resume restore</button>
                @elseif($run->mode === 'apply' && in_array($run->status, ['completed','restore_partial']) && $remainingPapers->isNotEmpty())<button class="btn btn-sm btn-outline-danger cleanup-restore-selected" data-run="{{ $run->id }}" disabled><i class="ri-arrow-go-back-line"></i> Restore selected</button>
                @else<span class="text-muted">&mdash;</span>@endif
            </td>
        </tr>
    @empty<tr><td colspan="6" class="text-center text-muted py-5">No cleanup runs yet.</td></tr>@endforelse
    </tbody></table></div>
</div>
@endsection

@section('script')
<script>
document.addEventListener('DOMContentLoaded',()=>{
 const csrf=@json(csrf_token()),categories=@json($categoryOptions),packages=@json($packageOptions),routes={search:@json(route('content-normalization.exams.search')),summary:@json(route('content-normalization.exams.selection-summary')),store:@json(route('content-normalization.runs.store')),step:@json(route('content-normalization.runs.step',['run'=>'__RUN__'])),restore:@json(route('content-normalization.runs.restore',['run'=>'__RUN__'])),restoreStep:@json(route('content-normalization.runs.restore-step',['run'=>'__RUN__']))};
 const group=document.getElementById('normalization-group'),category=document.getElementById('normalization-category'),pkg=document.getElementById('normalization-package'),search=document.getElementById('normalization-exam-search'),results=document.getElementById('normalization-exam-results'),note=document.getElementById('normalization-exam-note'),panel=document.getElementById('normalization-selection-panel'),paperList=document.getElementById('normalization-selected-papers'),allFiltered=document.getElementById('normalization-all-filtered');
 const selectedPapers=new Map(),searchExams=new Map();let selectionMode='selected',matchingTotal=0,filteredSummary=null,timer=null,controller=null;
 const escape=s=>String(s??'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#039;');
 const params=()=>new URLSearchParams({q:search.value,group_id:group.value,category_id:category.value,package_id:pkg.value});
 const refill=()=>{const gid=Number(group.value||0),cid=Number(category.value||0);category.innerHTML='<option value="">All categories</option>'+categories.filter(c=>!gid||c.group_ids.includes(gid)).map(c=>`<option value="${c.id}">${escape(c.title)}</option>`).join('');if(cid&&[...category.options].some(o=>Number(o.value)===cid))category.value=cid;const active=Number(category.value||0);pkg.innerHTML='<option value="">All packages</option>'+packages.filter(p=>(!gid||p.group_ids.includes(gid))&&(!active||p.category_id===active||p.subcategory_id===active)).map(p=>`<option value="${p.id}">${escape(p.name)}</option>`).join('');};
 const renderSelection=()=>{const papers=[...selectedPapers.values()],all=selectionMode==='all_filtered'&&filteredSummary;panel.classList.toggle('d-none',!all&&!papers.length);document.getElementById('normalization-selection-title').textContent=all?`All ${filteredSummary.paper_count} matching papers selected`:`${papers.length} paper(s) selected`;document.getElementById('normalization-selection-note').textContent=all?`${Number(filteredSummary.question_count).toLocaleString()} linked question(s) before optional ID limits.`:'Only the checked papers will be processed.';paperList.innerHTML=all?'<span class="badge bg-primary fs-6">All current filter matches</span>':papers.map(p=>`<span class="badge bg-light text-dark border fs-6 d-inline-flex align-items-center gap-2">${escape(p.name)} <button type="button" class="btn-close normalization-remove-paper" data-id="${p.id}" aria-label="Remove"></button></span>`).join('');};
 const query=async()=>{controller?.abort();controller=new AbortController();results.innerHTML='<div class="list-group-item text-muted"><span class="spinner-border spinner-border-sm me-2"></span>Searching...</div>';try{const response=await fetch(routes.search+'?'+params(),{headers:{Accept:'application/json'},signal:controller.signal}),data=await response.json();if(!response.ok)throw Error(data.message||'Search failed');searchExams.clear();data.results.forEach(e=>searchExams.set(Number(e.id),e));matchingTotal=Number(data.total||0);allFiltered.disabled=!matchingTotal;allFiltered.innerHTML=`<i class="ri-checkbox-multiple-line me-1"></i>Select all ${matchingTotal} matching paper(s)`;results.innerHTML=data.results.length?data.results.map(e=>`<label class="list-group-item d-flex align-items-center gap-2"><input class="form-check-input normalization-paper-check" type="checkbox" data-id="${e.id}" ${selectionMode==='selected'&&selectedPapers.has(Number(e.id))?'checked':''}><span><strong>${escape(e.text)}</strong><span class="small text-muted ms-2">${e.question_count} questions</span></span></label>`).join(''):'<div class="list-group-item text-muted">No matching papers.</div>';note.textContent=`${matchingTotal} matching paper(s); first 30 shown. Search again to find another paper.`;}catch(e){if(e.name!=='AbortError')results.innerHTML='<div class="list-group-item text-danger">Paper search failed.</div>';}};
 const schedule=()=>{clearTimeout(timer);timer=setTimeout(query,250);};
 [group,category,pkg].forEach(el=>el.addEventListener('change',()=>{if(el===group||el===category)refill();selectionMode='selected';filteredSummary=null;schedule();renderSelection();}));search.addEventListener('input',()=>{selectionMode='selected';filteredSummary=null;schedule();renderSelection();});
 results.addEventListener('change',e=>{const check=e.target.closest('.normalization-paper-check');if(!check)return;selectionMode='selected';filteredSummary=null;const id=Number(check.dataset.id),item=searchExams.get(id);if(check.checked)selectedPapers.set(id,{id,name:item?.text||'Paper'});else selectedPapers.delete(id);renderSelection();});
 allFiltered.addEventListener('click',async()=>{const original=allFiltered.innerHTML;allFiltered.disabled=true;allFiltered.innerHTML='<span class="spinner-border spinner-border-sm me-1"></span>Counting questions...';try{const response=await fetch(routes.summary+'?'+params(),{headers:{Accept:'application/json'}}),data=await response.json();if(!response.ok)throw Error(data.message||'Summary failed');selectionMode='all_filtered';filteredSummary=data;selectedPapers.clear();results.querySelectorAll('.normalization-paper-check').forEach(c=>c.checked=false);renderSelection();}catch(e){alertBox(e.message,'danger')}finally{allFiltered.disabled=!matchingTotal;allFiltered.innerHTML=original;}});
 paperList.addEventListener('click',e=>{const button=e.target.closest('.normalization-remove-paper');if(!button)return;selectedPapers.delete(Number(button.dataset.id));renderSelection();const check=results.querySelector(`.normalization-paper-check[data-id="${button.dataset.id}"]`);if(check)check.checked=false;});
 document.getElementById('normalization-clear-papers').addEventListener('click',()=>{selectionMode='selected';filteredSummary=null;selectedPapers.clear();results.querySelectorAll('.normalization-paper-check').forEach(c=>c.checked=false);renderSelection();});
 document.querySelectorAll('[data-start-mode]').forEach(button=>button.addEventListener('click',()=>start(button.dataset.startMode)));
 document.querySelectorAll('[data-resume-run]').forEach(b=>b.onclick=()=>run(b.dataset.resumeRun));
 document.querySelectorAll('[data-resume-restore]').forEach(b=>b.onclick=()=>restoreLoop(b.dataset.resumeRestore));
 document.querySelectorAll('.cleanup-restore-selected').forEach(b=>b.onclick=()=>restorePapers(b.dataset.run,selectedRestoreIds(b.dataset.run)));
 document.querySelectorAll('.cleanup-restore-one').forEach(b=>b.onclick=()=>restorePapers(b.dataset.run,[Number(b.dataset.exam)]));
 document.addEventListener('change',e=>{const all=e.target.closest('.cleanup-run-select-all'),paper=e.target.closest('.cleanup-paper-restore-check');if(!all&&!paper)return;const run=(all||paper).dataset.run,checks=[...document.querySelectorAll(`.cleanup-paper-restore-check[data-run="${run}"]`)];if(all)checks.forEach(check=>check.checked=all.checked);const selected=checks.filter(check=>check.checked).length,button=document.querySelector(`.cleanup-restore-selected[data-run="${run}"]`),selectAll=document.querySelector(`.cleanup-run-select-all[data-run="${run}"]`);if(button)button.disabled=selected===0;if(selectAll&&!all){selectAll.checked=selected===checks.length;selectAll.indeterminate=selected>0&&selected<checks.length;}});
 const selectedRestoreIds=run=>[...document.querySelectorAll(`.cleanup-paper-restore-check[data-run="${run}"]:checked`)].map(check=>Number(check.value));
 async function restorePapers(run,examIds){if(!examIds.length)return alertBox('Select at least one paper to restore.','danger');if(!confirm(`Restore ${examIds.length} selected paper(s)? Shared questions and passages will be restored everywhere they are used. Later manual edits remain protected.`))return;try{const data=await post(routes.restore.replace('__RUN__',run),{exam_ids:examIds});await restoreLoop(data.run.id)}catch(e){alertBox(e.message,'danger')}}
 async function start(mode){if(selectionMode==='selected'&&!selectedPapers.size)return alertBox('Select at least one paper, or choose all matching papers.','danger');if(selectionMode==='all_filtered'&&!filteredSummary)return alertBox('Choose all matching papers again.','danger');const confirmed=document.getElementById('confirmNormalizationApply').checked;if(mode==='apply'&&!confirmed)return alertBox('Confirm the Apply action first.','danger');try{const data=await post(routes.store,{selection_mode:selectionMode,mode,content_scope:document.getElementById('normalizationContentScope').value,exam_ids:[...selectedPapers.keys()],q:search.value||null,group_id:group.value||null,category_id:category.value||null,package_id:pkg.value||null,from_id:document.getElementById('normalizationFromId').value||null,to_id:document.getElementById('normalizationToId').value||null,confirm_apply:confirmed?1:0});await run(data.run.id)}catch(e){alertBox(e.message,'danger')}}
 async function run(id){try{while(true){const data=await post(routes.step.replace('__RUN__',id),{});show(data.run,data.run.mode==='apply'?'Applying cleanup':'Running preview');if(data.done||!['pending','running','processing'].includes(data.run.status))break}alertBox('Run finished. Reloading...','success');setTimeout(()=>location.reload(),600)}catch(e){alertBox(e.message,'danger')}}
 async function restoreLoop(id){try{while(true){const data=await post(routes.restoreStep.replace('__RUN__',id),{});show(data.run,'Restoring backup');if(data.done||!['restoring','restore_processing'].includes(data.run.status))break}alertBox('Restore finished. Reloading...','success');setTimeout(()=>location.reload(),600)}catch(e){alertBox(e.message,'danger')}}
 function show(run,title){document.getElementById('activeRunPanel').classList.remove('d-none');document.getElementById('activeRunTitle').textContent=title;const progress=Math.max(0,Math.min(100,run.progress||0)),stats=run.stats||{},bar=document.getElementById('activeRunProgress');bar.style.width=progress+'%';bar.textContent=progress+'%';document.getElementById('activeRunSummary').textContent=`${Number(run.processed_questions).toLocaleString()} / ${Number(run.total_questions).toLocaleString()} questions · converted ${Number(stats.converted||0).toLocaleString()} · review ${Number(stats.needs_review||0).toLocaleString()} · applied ${Number(stats.applied_records||0).toLocaleString()}`}
 async function post(url,payload){const response=await fetch(url,{method:'POST',headers:{'Content-Type':'application/json',Accept:'application/json','X-CSRF-TOKEN':csrf},body:JSON.stringify(payload)}),data=await response.json().catch(()=>({}));if(!response.ok)throw Error(data.errors?Object.values(data.errors).flat().join(' '):(data.message||'Request failed'));return data}
 function alertBox(message,type='info'){const node=document.getElementById('normalizationAlert');node.textContent='';if(message){const box=document.createElement('div');box.className='alert alert-'+type;box.textContent=message;node.appendChild(box);window.scrollTo({top:0,behavior:'smooth'})}}
 refill();query();
});
</script>
@endsection
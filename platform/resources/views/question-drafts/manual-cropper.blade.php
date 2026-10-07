@php
    $cropEvidence = (array) ($cropEvidence ?? []);
    $cropPayload = (array) ($cropPayload ?? []);
    $targetCandidates = ['question','option1','option2','option3','option4','option5','option6','explanation'];
    $initialTarget = (string) request('crop_target', old('target_field', data_get($cropEvidence, 'image_instruction.target_field', 'question')));
    if (! in_array($initialTarget, $targetCandidates, true)) $initialTarget = 'question';
    $sourcePages = collect((array) data_get($cropEvidence, 'pages', []))->map(fn ($page) => (int) $page)->filter(fn ($page) => $page > 0);
    $pageCandidates = collect([
        request('crop_page'), old('page'), data_get($cropEvidence, 'manual_crops.'.$initialTarget.'.source_page'),
        $cropDefaultPage ?? null, data_get($cropEvidence, 'extracted_image.source_page'), data_get($cropEvidence, 'image_instruction.source_page'),
        data_get($cropEvidence, 'image_instructions.0.source_page'), data_get($cropEvidence, 'extracted_image.source_pages.0'),
        $sourcePages->first(),
    ])->map(fn ($page) => (int) $page)->filter(fn ($page) => $page > 0);
    $initialPage = max(1, (int) ($pageCandidates->first() ?: 1));
    $highestOption = collect(range(1, 6))->filter(function ($index) use ($cropPayload) {
        $value = (string) ($cropPayload['option'.$index] ?? '');
        return trim(strip_tags($value)) !== '' || stripos($value, '<img') !== false;
    })->max() ?: 0;
    $answerOption = collect((array) ($cropPayload['correct_option_indices'] ?? $cropPayload['correct_answers'] ?? []))->map(fn ($value) => (int) $value)->max() ?: 0;
    $optionCount = max(4, min(6, (int) ($cropOptionCount ?? max($highestOption, $answerOption))));
    $manualCrops = (array) data_get($cropEvidence, 'manual_crops', []);
    $initialBackground = (string) request('background_mode', old('background_mode', data_get($manualCrops, $initialTarget.'.background_mode', 'white')));
    if (! in_array($initialBackground, ['white', 'transparent'], true)) $initialBackground = 'white';
    $cropContinuous = (bool) ($cropContinuous ?? false);
    $cropExternalTargets = (bool) ($cropExternalTargets ?? false);
@endphp
<div class="card mb-3" id="manual-pdf-cropper">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-start gap-2">
        <div><h4 class="mb-1">Crop from the authoritative PDF</h4><div class="text-muted">@if($cropContinuous) Scroll the complete PDF below, select Crop into this field on the right, then draw the required image. @else This question's detected PDF page opens automatically. Crop once for the question, or save Option A through Option {{ chr(64 + $optionCount) }} sequentially. @endif</div></div>
        <span class="badge bg-light text-primary border" id="crop-current-target">{{ $cropExternalTargets ? 'Select a field on the right' : 'Question image' }}</span>
    </div>
    <div class="card-body">
        <form method="POST" action="{{ $cropAction }}" id="manual-crop-form">@csrf @method('PATCH')
            <input type="hidden" name="page" id="crop-page" value="{{ $initialPage }}">
            <input type="hidden" name="target_field" id="crop-target-field" value="{{ $initialTarget }}">
            <input type="hidden" name="next_target" id="crop-next-target" value="{{ $initialTarget }}">
            <input type="hidden" name="crop_mode" id="crop-operation-mode" value="image">
            @foreach(['x0','y0','x1','y1'] as $coordinate)<input type="hidden" name="{{ $coordinate }}" id="crop-{{ $coordinate }}" value="{{ old($coordinate) }}">@endforeach
            @if($cropHasSourceRoles ?? false)
                <input type="hidden" name="source_role" id="crop-source-role" value="{{ request('source_role', old('source_role', 'questions')) }}">
            @endif
            <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
                <div class="crop-operation-picker" role="group" aria-label="Crop operation">
                    <button type="button" class="btn btn-primary crop-operation-choice active" data-crop-mode="image"><i class="ri-image-line me-1"></i>Image crop</button>
                    <button type="button" class="btn btn-outline-primary crop-operation-choice" data-crop-mode="mathpix"><i class="ri-functions me-1"></i>Equation / text (Mathpix)</button>
                </div>
                <div class="crop-destination-picker {{ $cropExternalTargets ? 'd-none' : '' }}" role="group" aria-label="Crop destination mode">
                    <button type="button" class="crop-destination-card" id="crop-question-mode"><span class="crop-destination-icon"><i class="ri-image-line"></i></span><span><strong>Question image</strong><small>Insert one diagram into the question</small></span></button>
                    <button type="button" class="crop-destination-card" id="crop-options-mode"><span class="crop-destination-icon"><i class="ri-gallery-line"></i></span><span><strong>Option images A-{{ chr(64 + $optionCount) }}</strong><small>Crop each option separately in sequence</small></span></button>
                </div>
                <div class="btn-group ms-md-2 {{ $cropContinuous ? 'd-none' : '' }}" role="group" aria-label="PDF page navigation">
                    <button type="button" class="btn btn-outline-secondary" id="crop-prev-page" title="Previous PDF page"><i class="ri-arrow-left-s-line"></i></button>
                    <div class="input-group" style="width:150px" title="Jump directly to the detected or known PDF page"><span class="input-group-text">Page</span><input type="number" min="1" class="form-control text-center" id="crop-page-jump" value="{{ $initialPage }}" aria-label="PDF page number"><button type="button" class="btn btn-outline-secondary" id="crop-page-go">Go</button></div>
                    <span class="visually-hidden" id="crop-page-label">{{ $initialPage }}</span>
                    <button type="button" class="btn btn-outline-secondary" id="crop-next-page-button" title="Next PDF page"><i class="ri-arrow-right-s-line"></i></button>
                </div>
                @if($cropHasSourceRoles ?? false)
                    <div class="dropdown">
                        <button class="btn btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">Question PDF</button>
                        <ul class="dropdown-menu"><li><button class="dropdown-item crop-source-choice" type="button" data-role="questions">Question PDF</button></li><li><button class="dropdown-item crop-source-choice" type="button" data-role="answers">Answer PDF</button></li><li><button class="dropdown-item crop-source-choice" type="button" data-role="combined">Combined PDF</button></li></ul>
                    </div>
                @endif
                <label class="d-flex align-items-center gap-2 ms-md-auto"><span class="small text-muted text-nowrap">Image background</span><select class="form-select" name="background_mode" id="crop-background-mode" style="width:auto"><option value="white" @selected($initialBackground === 'white')>Clean white</option><option value="transparent" @selected($initialBackground === 'transparent')>Transparent</option></select></label>
                <div class="btn-group" role="group" aria-label="Zoom controls"><button type="button" class="btn btn-outline-secondary" id="crop-zoom-out"><i class="ri-zoom-out-line"></i></button><button type="button" class="btn btn-outline-secondary" id="crop-zoom-reset">100%</button><button type="button" class="btn btn-outline-secondary" id="crop-zoom-in"><i class="ri-zoom-in-line"></i></button></div>
            </div>
            <div class="d-flex flex-wrap gap-2 mb-3 {{ $cropExternalTargets || !str_starts_with($initialTarget, 'option') ? 'd-none' : '' }}" id="crop-option-progress">
                @foreach(range(1, $optionCount) as $index)
                    @php $field = 'option'.$index; @endphp
                    <button type="button" class="btn btn-sm {{ data_get($manualCrops, $field) ? 'btn-success' : 'btn-outline-secondary' }} crop-option-step" data-target="{{ $field }}">{{ chr(64 + $index) }} @if(data_get($manualCrops, $field))<i class="ri-check-line ms-1"></i>@endif</button>
                @endforeach
            </div>
            @error('crop')<div class="alert alert-danger">{{ $message }}</div>@enderror
            <div class="alert alert-info py-2 mb-2" id="crop-help"><span class="spinner-border spinner-border-sm me-2"></span>{{ $cropContinuous ? 'Loading the continuous PDF...' : 'Loading the detected source page...' }}</div>
            <div class="card border-primary mb-3 d-none" id="mathpix-review-panel">
                <div class="card-header d-flex justify-content-between align-items-center gap-2"><div><strong>Review recognized equation / text</strong><div class="small text-muted">Edit the MathJax source if needed. It is not inserted until you confirm.</div></div><span class="badge bg-light text-dark" id="mathpix-confidence"></span></div>
                <div class="card-body">
                    <div class="row g-3"><div class="col-lg-6"><label class="form-label" for="mathpix-recognized-html">Recognized HTML + MathJax source</label><textarea class="form-control font-monospace" rows="7" id="mathpix-recognized-html"></textarea></div><div class="col-lg-6"><div class="form-label">Rendered preview <span class="small text-muted">(how it will appear)</span></div><div class="form-control bg-white overflow-auto mathpix-rendered-preview" style="min-height:180px;max-height:300px" id="mathpix-rendered-preview"></div></div></div>
                    <div class="alert alert-warning py-2 mt-3 mb-0 d-none" id="mathpix-confidence-warning">Low recognition confidence. Compare every symbol with the authoritative PDF before inserting.</div>
                    <div class="d-flex justify-content-end gap-2 mt-3"><button type="button" class="btn btn-outline-secondary" id="mathpix-cancel">Cancel</button><button type="button" class="btn btn-primary" id="mathpix-insert"><i class="ri-check-line me-1"></i>Insert &amp; save draft</button></div>
                </div>
            </div>
            <div id="source-crop-stage" class="position-relative border rounded bg-light overflow-auto" style="height:min(72vh,850px);min-height:420px;user-select:none">
                @if($cropContinuous)
                    <div id="continuous-source-pages" style="width:100%"></div>
                @else
                    <div class="position-relative" id="source-image-wrap" style="width:100%">
                        <img id="source-page-image" alt="Private PDF source page" draggable="false" style="display:block;width:100%;height:auto">
                        <div id="crop-selection" class="position-absolute d-none" style="pointer-events:none;border:3px solid #f59e0b;background:transparent;box-shadow:0 0 0 1px rgba(255,255,255,.95),0 2px 8px rgba(15,23,42,.35)"></div>
                    </div>
                @endif
            </div>
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mt-3">
                <div class="small text-muted">{{ $cropContinuous ? 'Release the rectangle to crop and save immediately to the selected draft field.' : 'Draw a new rectangle to redo the current crop. Every crop is auto-saved to the draft immediately. Nothing changes live until publication.' }}</div>
                <div class="d-flex gap-2"><button type="button" class="btn btn-outline-secondary d-none" id="crop-skip-option">Skip this option</button><button class="btn btn-primary {{ $cropContinuous ? 'd-none' : '' }}" id="save-crop" disabled><i class="ri-crop-line me-1"></i><span id="save-crop-label">{{ $cropExternalTargets ? 'Select a field, crop & auto-save' : 'Crop & auto-save question' }}</span></button></div>
            </div>
        </form>
    </div>
</div>
<style>
.crop-operation-picker{display:flex;flex-wrap:wrap;gap:.65rem}.crop-operation-picker .crop-operation-choice{border-radius:.55rem!important;box-shadow:0 2px 6px rgba(15,23,42,.08)}.crop-operation-picker .crop-operation-choice.active{box-shadow:0 0 0 .18rem rgba(13,110,253,.16),0 3px 10px rgba(15,23,42,.12)}
.mathpix-rendered-preview{font-size:1.05rem;line-height:1.65}.mathpix-rendered-preview mjx-container{max-width:100%;overflow-x:auto;overflow-y:hidden;padding:.2rem 0}.mathpix-rendered-preview.is-rendering{color:#64748b}
.crop-destination-picker{display:flex;flex-wrap:wrap;gap:.65rem}.crop-destination-card{display:flex;align-items:center;gap:.7rem;min-width:220px;padding:.7rem .9rem;border:1px solid #b8c8c5;border-radius:.65rem;background:#fff;color:#334155;text-align:left;transition:.15s ease}.crop-destination-card:hover{border-color:#0f766e;box-shadow:0 3px 12px rgba(15,118,110,.12)}.crop-destination-card.active{border-color:#0f766e;background:#eaf7f5;color:#0f5f59;box-shadow:inset 0 0 0 1px #0f766e}.crop-destination-card small{display:block;margin-top:.1rem;color:#64748b;font-size:.72rem}.crop-destination-icon{display:inline-flex;align-items:center;justify-content:center;width:38px;height:38px;border-radius:50%;background:#edf2f7;font-size:1.15rem}.crop-destination-card.active .crop-destination-icon{background:#0f766e;color:#fff}
</style>
<script>
window.ExamEliteMathPreview = window.ExamEliteMathPreview || (() => {
    let loaderPromise = null;
    const ensure = () => {
        if (window.MathJax?.typesetPromise) return Promise.resolve(window.MathJax);
        if (loaderPromise) return loaderPromise;
        loaderPromise = new Promise((resolve, reject) => {
            if (!window.MathJax) window.MathJax = {loader:{load:['[tex]/mhchem']},tex:{packages:{'[+]':['mhchem']},inlineMath:[['\\(','\\)'],['$','$']],displayMath:[['\\[','\\]'],['$$','$$']],processEscapes:true},options:{skipHtmlTags:['script','noscript','style','textarea','pre','code']}};
            let script = document.querySelector('script[src*="mathjax@3"],script[src*="tex-mml-chtml"],script[src*="tex-svg"]');
            if (!script) {
                script = document.createElement('script');
                script.src = 'https://cdn.jsdelivr.net/npm/mathjax@3/es5/tex-mml-chtml.js';
                script.defer = true;
                script.dataset.examEliteMathPreview = '1';
                document.head.appendChild(script);
            }
            let checks = 0;
            const timer = window.setInterval(() => {
                if (window.MathJax?.typesetPromise) { window.clearInterval(timer); resolve(window.MathJax); }
                else if (++checks >= 100) { window.clearInterval(timer); loaderPromise = null; reject(new Error('MathJax preview could not load. The recognized source is still editable and can be inserted.')); }
            }, 100);
            script.addEventListener('error', () => { window.clearInterval(timer); loaderPromise = null; reject(new Error('MathJax preview could not load. Check the internet connection and try again.')); }, {once:true});
        });
        return loaderPromise;
    };
    const render = async (container, html) => {
        container.classList.add('is-rendering');
        container.innerHTML = html || '<span class="text-muted">Nothing recognized.</span>';
        try {
            const mathJax = await ensure();
            mathJax.typesetClear?.([container]);
            await mathJax.typesetPromise([container]);
            container.classList.remove('is-rendering');
        } catch (error) {
            container.classList.remove('is-rendering');
            const notice = document.createElement('div');
            notice.className = 'alert alert-warning py-2 mt-2 mb-0 small';
            notice.textContent = error.message;
            container.appendChild(notice);
        }
    };
    return {render};
})();
</script>
@if($cropContinuous)
    @include('question-drafts.continuous-cropper-script')
@else
<script>
document.addEventListener('DOMContentLoaded', () => {
    const root=document.getElementById('manual-pdf-cropper');if(!root)return;
    let pageUrl=@json($cropPageUrl),activeEditorSelector=null,start=null,zoom=1,turningPage=false,saving=false,selectionStart=0,selectionEnd=0,recognition=null;
    const targets=@json($targetCandidates),optionCount=@json($optionCount),form=document.getElementById('manual-crop-form'),pageInput=document.getElementById('crop-page'),pageLabel=document.getElementById('crop-page-label'),pageJump=document.getElementById('crop-page-jump'),targetInput=document.getElementById('crop-target-field'),nextTarget=document.getElementById('crop-next-target'),targetLabel=document.getElementById('crop-current-target'),modeInput=document.getElementById('crop-operation-mode');
    const stage=document.getElementById('source-crop-stage'),wrap=document.getElementById('source-image-wrap'),img=document.getElementById('source-page-image'),box=document.getElementById('crop-selection'),save=document.getElementById('save-crop'),saveLabel=document.getElementById('save-crop-label'),skip=document.getElementById('crop-skip-option'),help=document.getElementById('crop-help'),optionProgress=document.getElementById('crop-option-progress'),roleInput=document.getElementById('crop-source-role');
    const review=document.getElementById('mathpix-review-panel'),recognized=document.getElementById('mathpix-recognized-html'),rendered=document.getElementById('mathpix-rendered-preview'),confidence=document.getElementById('mathpix-confidence'),warning=document.getElementById('mathpix-confidence-warning'),insert=document.getElementById('mathpix-insert');
    const optionNumber=target=>target.startsWith('option')?Number(target.slice(6)):0;
    const editor=()=>activeEditorSelector?document.querySelector(activeEditorSelector):document.querySelector(`[name="proposed[${targetInput.value}]"], [name="${targetInput.value}"], [data-manual-draft-field="${targetInput.value}"]`);
    const readEditor=field=>field?.id&&window.CKEDITOR?.instances?.[field.id]?window.CKEDITOR.instances[field.id].getData():(field?.value||'');
    const writeEditor=(field,value)=>{if(!field)return;if(field.id&&window.CKEDITOR?.instances?.[field.id])window.CKEDITOR.instances[field.id].setData(value);field.value=value;field.dispatchEvent(new Event('input',{bubbles:true}));};
    const renderMathpix=()=>window.ExamEliteMathPreview.render(rendered,recognized.value);
    const hideReview=()=>{review.classList.add('d-none');recognition=null;};
    const clearSelection=()=>{box.classList.add('d-none');save.disabled=true;['x0','y0','x1','y1'].forEach(name=>document.getElementById('crop-'+name).value='');};
    const setMode=mode=>{modeInput.value=mode;document.querySelectorAll('.crop-operation-choice').forEach(button=>{const active=button.dataset.cropMode===mode;button.classList.toggle('active',active);button.classList.toggle('btn-primary',active);button.classList.toggle('btn-outline-primary',!active);});hideReview();saveLabel.textContent=mode==='mathpix'?'Recognize & preview':'Crop & auto-save';help.className='alert alert-info py-2 mb-2';help.textContent=mode==='mathpix'?'Draw tightly around an equation or text. Mathpix output will be previewed before insertion.':'Draw the required image; it will save to the selected draft field.';};
    const setTarget=target=>{const index=optionNumber(target),optionMode=index>0;targetInput.value=optionMode?`option${Math.min(optionCount,Math.max(1,index))}`:(target==='explanation'?'explanation':'question');const activeIndex=optionNumber(targetInput.value);targetLabel.textContent=activeIndex?`Option ${String.fromCharCode(64+activeIndex)} field`:(targetInput.value==='explanation'?'Explanation field':'Question field');skip.classList.toggle('d-none',!activeIndex||activeIndex>=optionCount);optionProgress.classList.toggle('d-none',!activeIndex);document.getElementById('crop-question-mode').classList.toggle('active',!activeIndex);document.getElementById('crop-options-mode').classList.toggle('active',!!activeIndex);document.querySelectorAll('.crop-option-step').forEach(button=>button.classList.toggle('active',button.dataset.target===targetInput.value));nextTarget.value = activeIndex && activeIndex < optionCount ? 'option'+(activeIndex+1) : targetInput.value;saveLabel.textContent = modeInput.value === 'mathpix' ? 'Recognize & preview' : (activeIndex ? `Crop & auto-save Option ${String.fromCharCode(64 + activeIndex)}` : 'Crop & auto-save question');const field=editor();const rawStart=field?.selectionStart??0,rawEnd=field?.selectionEnd??rawStart,fieldEnd=readEditor(field).length;selectionStart=rawEnd>rawStart?rawStart:fieldEnd;selectionEnd=rawEnd>rawStart?rawEnd:fieldEnd;clearSelection();hideReview();};
    const loadPage=()=>{const page=Math.max(1,Number(pageInput.value||1));pageInput.value=page;pageLabel.textContent=page;pageJump.value=page;clearSelection();hideReview();help.className='alert alert-info py-2 mb-2';help.innerHTML='<span class="spinner-border spinner-border-sm me-2"></span>Loading source page '+page+'...';const role=roleInput?'&source_role='+encodeURIComponent(roleInput.value):'';img.onload=()=>{turningPage=false;help.className='alert alert-success py-2 mb-2';help.textContent='Source page '+page+' loaded. Select a crop mode and draw the required region.';stage.scrollTop=0;};img.onerror=()=>{turningPage=false;help.className='alert alert-danger py-2 mb-2';help.textContent='This source page could not be loaded.';};img.src=pageUrl+(pageUrl.includes('?')?'&':'?')+'page='+page+role+'&t='+Date.now();};
    const point=event=>{const rect=img.getBoundingClientRect();return{x:Math.max(0,Math.min(rect.width,event.clientX-rect.left)),y:Math.max(0,Math.min(rect.height,event.clientY-rect.top)),w:rect.width,h:rect.height};};wrap.addEventListener('pointerdown',event=>{if(!img.complete||!img.naturalWidth||saving)return;start=point(event);box.classList.remove('d-none');hideReview();wrap.setPointerCapture(event.pointerId);event.preventDefault();});wrap.addEventListener('pointermove',event=>{if(!start)return;const p=point(event),x=Math.min(start.x,p.x),y=Math.min(start.y,p.y);Object.assign(box.style,{left:x+'px',top:y+'px',width:Math.abs(p.x-start.x)+'px',height:Math.abs(p.y-start.y)+'px'});});wrap.addEventListener('pointerup',event=>{if(!start)return;const p=point(event),x0=Math.min(start.x,p.x)/p.w,y0=Math.min(start.y,p.y)/p.h,x1=Math.max(start.x,p.x)/p.w,y1=Math.max(start.y,p.y)/p.h;start=null;if(x1-x0<.01||y1-y0<.01){clearSelection();return;}[['x0',x0],['y0',y0],['x1',x1],['y1',y1]].forEach(([name,value])=>document.getElementById('crop-'+name).value=value.toFixed(6));save.disabled=false;saveLabel.textContent=modeInput.value==='mathpix'?'Recognize & preview':'Crop & auto-save';});
    document.getElementById('crop-question-mode').addEventListener('click',()=>setTarget('question'));document.getElementById('crop-options-mode').addEventListener('click',()=>setTarget(optionNumber(targetInput.value)?targetInput.value:'option1'));document.querySelectorAll('.crop-option-step').forEach(button=>button.addEventListener('click',()=>setTarget(button.dataset.target)));document.querySelectorAll('.crop-operation-choice').forEach(button=>button.addEventListener('click',()=>setMode(button.dataset.cropMode)));
    document.addEventListener('manual-crop-target',event=>{const target=event.detail?.target;if(!targets.includes(target))return;if(event.detail?.cropAction)form.action=event.detail.cropAction;if(event.detail?.pageUrl)pageUrl=event.detail.pageUrl;activeEditorSelector=event.detail?.editorSelector||null;setTarget(target);if(event.detail?.page&&Number(pageInput.value)!==Number(event.detail.page)){pageInput.value=Math.max(1,Number(event.detail.page));loadPage();}root.scrollIntoView({behavior:'smooth',block:'start'});help.className='alert alert-info py-2 mb-2';help.textContent=modeInput.value==='mathpix'?'Draw tightly around the equation/text for this field; preview is required before saving.':'Draw the source image for this field; it will auto-save.';});
    skip.addEventListener('click',()=>{const index=optionNumber(targetInput.value);if(index&&index<optionCount)setTarget('option'+(index+1));});document.getElementById('crop-prev-page').addEventListener('click',()=>{pageInput.value=Math.max(1,Number(pageInput.value)-1);loadPage();});document.getElementById('crop-next-page-button').addEventListener('click',()=>{pageInput.value=Number(pageInput.value)+1;loadPage();});stage.addEventListener('wheel',event=>{if(turningPage)return;const atTop=stage.scrollTop<=2,atBottom=stage.scrollTop+stage.clientHeight>=stage.scrollHeight-3,direction=event.deltaY>0&&atBottom?1:(event.deltaY<0&&atTop&&Number(pageInput.value)>1?-1:0);if(!direction)return;event.preventDefault();turningPage=true;pageInput.value=Math.max(1,Number(pageInput.value)+direction);loadPage();},{passive:false});const jumpToPage=()=>{pageInput.value=Math.max(1,Number(pageJump.value||1));loadPage();};document.getElementById('crop-page-go').addEventListener('click',jumpToPage);pageJump.addEventListener('keydown',event=>{if(event.key==='Enter'){event.preventDefault();jumpToPage();}});document.querySelectorAll('.crop-source-choice').forEach(button=>button.addEventListener('click',()=>{if(roleInput){roleInput.value=button.dataset.role;button.closest('.dropdown').querySelector('.dropdown-toggle').textContent=button.textContent;}loadPage();}));
    const applyZoom=()=>{wrap.style.width=(zoom*100)+'%';document.getElementById('crop-zoom-reset').textContent=Math.round(zoom*100)+'%';clearSelection();hideReview();};document.getElementById('crop-zoom-in').addEventListener('click',()=>{zoom=Math.min(2.5,zoom+.25);applyZoom();});document.getElementById('crop-zoom-out').addEventListener('click',()=>{zoom=Math.max(.75,zoom-.25);applyZoom();});document.getElementById('crop-zoom-reset').addEventListener('click',()=>{zoom=1;applyZoom();});
    form.addEventListener('submit',async event=>{event.preventDefault();if(saving)return;saving=true;const savedTarget=targetInput.value,originalLabel=saveLabel.textContent;save.disabled=true;saveLabel.textContent=modeInput.value==='mathpix'?'Recognizing...':'Auto-saving...';try{const body=new FormData(form);body.set('crop_mode',modeInput.value);const response=await fetch(form.action,{method:'POST',body,headers:{'Accept':'application/json','X-Requested-With':'XMLHttpRequest'},credentials:'same-origin'});const result=await response.json().catch(()=>({}));if(!response.ok)throw new Error(result.message||Object.values(result.errors||{})[0]?.[0]||'The crop could not be processed.');if(result.preview_required){recognition=result.recognition;recognized.value=recognition.html||'';confidence.textContent=`Confidence ${recognition.confidence}%`;warning.classList.toggle('d-none',!recognition.low_confidence);review.classList.remove('d-none');await renderMathpix();help.className='alert alert-warning py-2 mb-2';help.textContent='Review every symbol, then insert and save the draft.';return;}const field=editor();if(field&&typeof result.html==='string')writeEditor(field,result.html);document.dispatchEvent(new CustomEvent('manual-crop-saved',{detail:{mode:'image',target:savedTarget,html:result.html,crop:result.crop,editorSelector:activeEditorSelector}}));setTarget(result.next_target||nextTarget.value);help.className='alert alert-success py-2 mb-2';help.textContent=result.message||'Crop auto-saved.';}catch(error){help.className='alert alert-danger py-2 mb-2';help.textContent=error.message;save.disabled=false;}finally{saving=false;saveLabel.textContent=originalLabel;}});
    let previewTimer;recognized.addEventListener('input',()=>{clearTimeout(previewTimer);previewTimer=setTimeout(renderMathpix,180);});document.getElementById('mathpix-cancel').addEventListener('click',()=>{hideReview();save.disabled=false;help.className='alert alert-info py-2 mb-2';help.textContent='Recognition cancelled. Adjust the crop and try again.';});insert.addEventListener('click',async()=>{if(!recognition||saving)return;const field=editor();if(!field){help.className='alert alert-danger py-2 mb-2';help.textContent='The selected draft field could not be found.';return;}const insertion=recognized.value,current=readEditor(field),startAt=Math.max(0,Math.min(selectionStart,current.length)),endAt=Math.max(startAt,Math.min(selectionEnd,current.length)),spacer=startAt===endAt&&startAt===current.length&&current.trim()!==''?'\n':'';const merged=current.slice(0,startAt)+spacer+insertion+current.slice(endAt);saving=true;insert.disabled=true;try{const body=new FormData(form);body.set('crop_mode','mathpix');body.set('confirmed','1');body.set('recognized_html',merged);body.set('mathpix_request_id',recognition.request_id||'');body.set('mathpix_confidence',recognition.confidence??0);const response=await fetch(form.action,{method:'POST',body,headers:{'Accept':'application/json','X-Requested-With':'XMLHttpRequest'},credentials:'same-origin'});const result=await response.json().catch(()=>({}));if(!response.ok)throw new Error(result.message||'The recognized text could not be saved.');writeEditor(field,result.html);document.dispatchEvent(new CustomEvent('manual-crop-saved',{detail:{mode:'mathpix',target:targetInput.value,html:result.html,editorSelector:activeEditorSelector}}));hideReview();clearSelection();help.className='alert alert-success py-2 mb-2';help.textContent=result.message;}catch(error){help.className='alert alert-danger py-2 mb-2';help.textContent=error.message;}finally{saving=false;insert.disabled=false;}});
    setTarget(@json($initialTarget));setMode('image');loadPage();
});
</script>
@endif

@php
$structuredItems = collect($structuredItems ?? []);
$pendingStructured = $structuredItems->filter(fn ($item) => ($item['status'] ?? null) !== 'reviewed');
$fieldLabels = ['question'=>'Question','option1'=>'Option 1','option2'=>'Option 2','option3'=>'Option 3','option4'=>'Option 4','option5'=>'Option 5','option6'=>'Option 6','explanation'=>'Explanation'];
@endphp
@if($structuredItems->isNotEmpty())
<div class="card mb-3 structured-review-card">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div><h4 class="mb-1">Structured table &amp; formula review</h4><div class="text-muted">Compare the source crop and reconstructed HTML/MathJax. Nothing changes live until the question or paper is published.</div></div>
        <span class="badge bg-{{ $pendingStructured->isEmpty() ? 'success' : 'warning text-dark' }}">{{ $pendingStructured->count() }} awaiting review</span>
    </div>
    <div class="card-body">
        @if($pendingStructured->isNotEmpty())
        <form method="POST" action="{{ $structuredAction }}" id="structured-bulk-live" class="d-flex flex-wrap align-items-center gap-2 mb-3 structured-bulk-form">
            @csrf @method('PATCH')
            <label class="form-check mb-0"><input type="checkbox" class="form-check-input structured-select-all"> <span class="form-check-label">Select all pending</span></label>
            <button class="btn btn-primary btn-sm" name="decision" value="accept"><i class="ri-check-double-line me-1"></i>Accept selected reconstructions</button>
            <button class="btn btn-outline-secondary btn-sm" name="decision" value="keep_original"><i class="ri-arrow-go-back-line me-1"></i>Keep current content</button>
        </form>
        @endif
        @foreach($structuredItems as $item)
        @php $reviewed = ($item['status'] ?? null) === 'reviewed'; $itemId = $item['id'] ?? ''; @endphp
        <div class="border rounded p-3 mb-3 {{ $reviewed ? 'border-success' : 'border-warning' }}">
            <div class="d-flex flex-wrap justify-content-between gap-2 mb-3">
                <div><strong>{{ $fieldLabels[$item['field'] ?? ''] ?? ucfirst($item['field'] ?? 'Content') }} &middot; {{ ucfirst($item['type'] ?? 'structured content') }}</strong><div class="small text-muted">{{ ucfirst($item['provider'] ?? 'deterministic') }} candidate &middot; confidence {{ number_format((float)($item['confidence'] ?? 0),0) }}%</div></div>
                <div class="d-flex gap-2 align-items-center">@if(!$reviewed)<label class="form-check mb-0"><input class="form-check-input structured-item-check" form="structured-bulk-live" type="checkbox" name="item_ids[]" value="{{ $itemId }}"></label>@endif<span class="badge bg-{{ $reviewed ? 'success' : (($item['validation']['valid'] ?? false) ? 'primary' : 'warning text-dark') }}">{{ $reviewed ? ucfirst(str_replace('_',' ',$item['decision'] ?? 'reviewed')) : (($item['validation']['valid'] ?? false) ? 'Validated; review required' : 'Needs correction') }}</span></div>
            </div>
            <div class="row g-3">
                <div class="col-lg-6"><div class="small text-muted mb-1">Original source / current value</div>
                    @if($structuredSourceBase && data_get($item,'source.private_path'))<img class="img-fluid border rounded mb-2 structured-source-crop" src="{{ $structuredSourceBase.'/'.$itemId.'/source' }}" alt="Private source crop">@endif
                    <div class="border rounded p-3 bg-light structured-original">{!! $item['original_html'] ?: '<span class="text-muted">Empty current value</span>' !!}</div>
                </div>
                <div class="col-lg-6"><div class="small text-muted mb-1">Reconstructed candidate</div>
                    <form method="POST" action="{{ $structuredAction }}">@csrf @method('PATCH')<input type="hidden" name="item_id" value="{{ $itemId }}">
                        <textarea name="candidate_html" class="form-control structured-candidate-editor" rows="6" {{ $reviewed ? 'readonly' : '' }}>{{ $item['candidate_html'] ?? '' }}</textarea>
                        <div class="border rounded p-3 mt-2 structured-candidate-preview">{!! $item['candidate_html'] ?? '' !!}</div>
                        @if(!$reviewed)<div class="d-flex flex-wrap gap-2 mt-2"><button class="btn btn-primary btn-sm" name="decision" value="accept"><i class="ri-check-line me-1"></i>Accept candidate</button><button class="btn btn-outline-secondary btn-sm" name="decision" value="keep_original"><i class="ri-arrow-go-back-line me-1"></i>Keep current</button></div>@endif
                    </form>
                </div>
            </div>
            @if(!($item['validation']['valid'] ?? false))<div class="alert alert-warning py-2 mt-3 mb-0"><strong>Validation:</strong> {{ implode(' ', (array)data_get($item,'validation.messages',[])) }}</div>@endif
        </div>
        @endforeach
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded',()=>{
 const bulk=document.querySelector('.structured-bulk-form'); if(!bulk)return;
 const checks=[...document.querySelectorAll('.structured-item-check')];
 bulk.querySelector('.structured-select-all')?.addEventListener('change',e=>checks.forEach(c=>c.checked=e.target.checked));
 document.querySelectorAll('.structured-candidate-editor').forEach(t=>t.addEventListener('input',()=>{const p=t.closest('form')?.querySelector('.structured-candidate-preview');if(p){p.innerHTML=t.value;if(window.MathJax?.typesetPromise){window.MathJax.typesetClear?.([p]);window.MathJax.typesetPromise([p]).catch(()=>{});}}}));
});
</script>
@endif

@extends('layouts.master')
@section('title', 'Homepage Content')

@section('css')
<style>
    .content-hero{background:linear-gradient(135deg,var(--el-primary,#0f766e),#115e59);color:#fff;border-radius:18px;padding:28px}
    .content-hero p{color:rgba(255,255,255,.78);max-width:720px}
    .section-switcher{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px;margin:22px 0}
    .section-switcher .nav-link{border:1px solid var(--el-border,#d7e2df);background:#fff;color:#0f172a;text-align:left;border-radius:16px;padding:18px;box-shadow:0 10px 26px rgba(15,23,42,.05)}
    .section-switcher .nav-link.active{background:var(--el-primary-soft,#e6f3f1);border-color:var(--el-primary,#0f766e);color:#0f172a}
    .section-switcher i{display:inline-grid;place-items:center;width:42px;height:42px;border-radius:12px;background:var(--el-primary,#0f766e);color:#fff;font-size:22px;margin-right:10px}
    .content-panel{border:1px solid var(--el-border,#d7e2df);border-radius:18px;overflow:hidden}
    .content-panel__head{padding:20px 22px;background:#f7faf9;border-bottom:1px solid var(--el-border,#d7e2df)}
    .item-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:16px;padding:20px}
    .content-item{border:1px solid var(--el-border,#d7e2df);border-radius:15px;padding:17px;background:#fff;min-width:0}
    .content-item__media{width:48px;height:48px;border-radius:13px;background:var(--el-primary-soft,#e6f3f1);display:grid;place-items:center;color:var(--el-primary,#0f766e);font-size:23px;overflow:hidden}
    .content-item__media img{width:100%;height:100%;object-fit:cover}
    .content-item p{color:#64748b;display:-webkit-box;-webkit-line-clamp:3;-webkit-box-orient:vertical;overflow:hidden}
    .empty-state{grid-column:1/-1;text-align:center;padding:38px;color:#64748b}
    .modal .language-box{border:1px solid var(--el-border,#d7e2df);border-radius:12px;padding:14px;margin-bottom:12px}
    @media(max-width:991px){.item-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
    @media(max-width:767px){.section-switcher,.item-grid{grid-template-columns:1fr}.content-hero{padding:22px}.content-panel__head{align-items:flex-start!important;gap:12px}}
</style>
@endsection

@section('content')
@component('components.breadcrumb')
@slot('li_1', 'Website Settings')
@slot('title', 'Homepage Content')
@endcomponent

@php
    $activeSection = in_array(request('section'), ['features', 'counters', 'testimonials'], true)
        ? request('section')
        : 'features';
@endphp

<div class="content-hero">

    <span class="badge bg-white text-success mb-3">FLEXIBLE HOMEPAGE BUILDER</span>
    <h2 class="text-white mb-2">Create homepage content in one place</h2>
    <div class="d-flex flex-wrap justify-content-between align-items-end gap-3"><p class="mb-0">Add, edit or remove benefit cards, live statistics and student reviews. Each content type has only the fields it needs.</p><a href="{{ route('configurations.website') }}" class="btn btn-light"><i class="ri-eye-settings-line me-1"></i>Visibility & hero settings</a></div>
</div>

<ul class="nav section-switcher" role="tablist">
    <li class="nav-item"><button class="nav-link {{ $activeSection === 'features' ? 'active' : '' }} w-100" data-bs-toggle="tab" data-bs-target="#features-pane" data-section="features" aria-selected="{{ $activeSection === 'features' ? 'true' : 'false' }}"><i class="ri-layout-grid-line"></i><strong>Benefits</strong><small class="d-block ms-5 text-muted">{{ $features->count() }} cards</small></button></li>
    <li class="nav-item"><button class="nav-link {{ $activeSection === 'counters' ? 'active' : '' }} w-100" data-bs-toggle="tab" data-bs-target="#counters-pane" data-section="counters" aria-selected="{{ $activeSection === 'counters' ? 'true' : 'false' }}"><i class="ri-bar-chart-box-line"></i><strong>Statistics</strong><small class="d-block ms-5 text-muted">{{ $counters->count() }} numbers</small></button></li>
    <li class="nav-item"><button class="nav-link {{ $activeSection === 'testimonials' ? 'active' : '' }} w-100" data-bs-toggle="tab" data-bs-target="#testimonials-pane" data-section="testimonials" aria-selected="{{ $activeSection === 'testimonials' ? 'true' : 'false' }}"><i class="ri-chat-quote-line"></i><strong>Student Reviews</strong><small class="d-block ms-5 text-muted">{{ $testimonials->count() }} reviews</small></button></li>
</ul>

<div class="tab-content">
    <div class="tab-pane fade {{ $activeSection === 'features' ? 'show active' : '' }}" id="features-pane">
        <div class="content-panel">
            <div class="content-panel__head d-flex justify-content-between align-items-center">
                <div><h4 class="mb-1">Benefits and highlights</h4><p class="text-muted mb-0">Explain why students should choose your platform.</p></div>
                <div class="d-flex flex-wrap gap-2"><button class="btn btn-outline-success edit-heading" data-bs-toggle="modal" data-bs-target="#headingModal" data-section="1" data-section-key="features" data-label="Benefits section" data-title="{{ json_encode($featureTitle?->getTranslations('title') ?? []) }}" data-subtitle="{{ json_encode($featureTitle?->getTranslations('sub_title') ?? []) }}"><i class="ri-edit-line me-1"></i>Edit heading</button><button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#featureModal" data-mode="create"><i class="ri-add-line me-1"></i>Add benefit</button></div>
            </div>
            <div class="item-grid">
                @forelse($features as $item)
                <article class="content-item">
                    <div class="d-flex justify-content-between gap-2 mb-3">
                        <div class="content-item__media">@if($item->image_url)<img src="{{ asset($item->image_url) }}" alt="">@else<i class="{{ $item->icon ?: 'ri-star-smile-line' }}"></i>@endif</div>
                        <div class="d-flex gap-1">
                            <button class="btn btn-sm btn-soft-success edit-content" data-bs-toggle="modal" data-bs-target="#featureModal" data-mode="edit" data-id="{{ $item->id }}" data-icon="{{ $item->icon }}" data-title="{{ json_encode($item->getTranslations("title")) }}" data-description="{{ json_encode($item->getTranslations("description")) }}"><i class="ri-pencil-line"></i></button>
                            <form method="POST" action="{{ route('features.destroy',$item->id) }}" data-swal-confirm="Remove this benefit card?">@csrf @method('DELETE')<button class="btn btn-sm btn-soft-danger"><i class="ri-delete-bin-line"></i></button></form>
                        </div>
                    </div>
                    <h5>{{ $item->title }}</h5><p class="mb-0">{{ $item->description }}</p>
                </article>
                @empty <div class="empty-state"><i class="ri-layout-grid-line fs-1"></i><h5 class="mt-2">No benefit cards yet</h5><p>Add the first reason students should choose you.</p></div>
                @endforelse
            </div>
        </div>
    </div>

    <div class="tab-pane fade {{ $activeSection === 'counters' ? 'show active' : '' }}" id="counters-pane">
        <div class="content-panel">
            <div class="content-panel__head d-flex justify-content-between align-items-center">
                <div><h4 class="mb-1">Trust-building statistics</h4><p class="text-muted mb-0">Show real numbers such as students, exams or attempts.</p></div>
                <div class="d-flex flex-wrap gap-2"><button class="btn btn-outline-success edit-heading" data-bs-toggle="modal" data-bs-target="#headingModal" data-section="6" data-section-key="counters" data-label="Statistics section" data-title="{{ json_encode($counterTitle?->getTranslations('title') ?? []) }}" data-subtitle="{{ json_encode($counterTitle?->getTranslations('sub_title') ?? []) }}"><i class="ri-edit-line me-1"></i>Edit heading</button><button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#counterModal" data-mode="create"><i class="ri-add-line me-1"></i>Add statistic</button></div>
            </div>
            <div class="item-grid">
                @forelse($counters as $item)
                <article class="content-item">
                    <div class="d-flex justify-content-between gap-2 mb-3">
                        <div class="content-item__media">@if($item->image_url)<img src="{{ asset($item->image_url) }}" alt="">@else<i class="{{ $item->icon ?: 'ri-line-chart-line' }}"></i>@endif</div>
                        <div class="d-flex gap-1">
                            <button class="btn btn-sm btn-soft-success edit-content" data-bs-toggle="modal" data-bs-target="#counterModal" data-mode="edit" data-id="{{ $item->id }}" data-icon="{{ $item->icon }}" data-number="{{ $item->number }}" data-title="{{ json_encode($item->getTranslations("title")) }}"><i class="ri-pencil-line"></i></button>
                            <form method="POST" action="{{ route('counters.destroy',$item->id) }}" data-swal-confirm="Remove this statistic?">@csrf @method('DELETE')<button class="btn btn-sm btn-soft-danger"><i class="ri-delete-bin-line"></i></button></form>
                        </div>
                    </div>
                    <div class="fs-2 fw-bold text-success">{{ $item->number }}</div><h5 class="mb-0">{{ $item->title }}</h5>
                </article>
                @empty <div class="empty-state"><i class="ri-bar-chart-box-line fs-1"></i><h5 class="mt-2">No statistics yet</h5><p>Add verified numbers that build confidence.</p></div>
                @endforelse
            </div>
        </div>
    </div>

    <div class="tab-pane fade {{ $activeSection === 'testimonials' ? 'show active' : '' }}" id="testimonials-pane">
        <div class="content-panel">
            <div class="content-panel__head d-flex justify-content-between align-items-center">
                <div><h4 class="mb-1">Student reviews</h4><p class="text-muted mb-0">Publish authentic feedback and student success stories.</p></div>
                <div class="d-flex flex-wrap gap-2"><button class="btn btn-outline-success edit-heading" data-bs-toggle="modal" data-bs-target="#headingModal" data-section="2" data-section-key="testimonials" data-label="Reviews section" data-title="{{ json_encode($testimonialTitle?->getTranslations('title') ?? []) }}" data-subtitle="{{ json_encode($testimonialTitle?->getTranslations('sub_title') ?? []) }}"><i class="ri-edit-line me-1"></i>Edit heading</button><button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#testimonialModal" data-mode="create"><i class="ri-add-line me-1"></i>Add review</button></div>
            </div>
            <div class="item-grid">
                @forelse($testimonials as $item)
                <article class="content-item">
                    <div class="d-flex justify-content-between gap-2 mb-3">
                        <div class="content-item__media">@if($item->image_url)<img src="{{ asset($item->image_url) }}" alt="">@else<i class="ri-user-smile-line"></i>@endif</div>
                        <div class="d-flex gap-1">
                            <button class="btn btn-sm btn-soft-success edit-content" data-bs-toggle="modal" data-bs-target="#testimonialModal" data-mode="edit" data-id="{{ $item->id }}" data-name="{{ json_encode($item->getTranslations("name")) }}" data-feedback="{{ json_encode($item->getTranslations("feedback")) }}"><i class="ri-pencil-line"></i></button>
                            <form method="POST" action="{{ route('testimonial.destroy',$item->id) }}" data-swal-confirm="Remove this review?">@csrf @method('DELETE')<button class="btn btn-sm btn-soft-danger"><i class="ri-delete-bin-line"></i></button></form>
                        </div>
                    </div>
                    <h5>{{ $item->name }}</h5><p class="mb-0">�{{ $item->feedback }}�</p>
                </article>
                @empty <div class="empty-state"><i class="ri-chat-quote-line fs-1"></i><h5 class="mt-2">No reviews yet</h5><p>Add your first student success story.</p></div>
                @endforelse
            </div>
        </div>
    </div>
</div>

@php $iconOptions=['ri-shield-check-line'=>'Shield','ri-smartphone-line'=>'Mobile','ri-bar-chart-box-line'=>'Analytics','ri-file-list-3-line'=>'Tests','ri-question-answer-line'=>'Questions','ri-timer-flash-line'=>'Speed','ri-award-line'=>'Achievement','ri-graduation-cap-line'=>'Education','ri-line-chart-line'=>'Growth']; @endphp

<div class="modal fade" id="headingModal" tabindex="-1"><div class="modal-dialog modal-lg modal-dialog-centered"><div class="modal-content">
<form method="POST" action="{{ route('website.title.update') }}">@csrf <input type="hidden" name="id" value="1"><input type="hidden" name="section" value="features">
<div class="modal-header"><h5 class="modal-title">Edit section heading</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
<div class="modal-body">@foreach($languages as $code=>$language)<div class="language-box"><strong>{{ $language }}</strong><div class="row mt-2"><div class="col-md-6"><label class="form-label">Heading</label><input class="form-control" name="title[{{ $code }}]" data-heading-field="title" data-lang="{{ $code }}" {{ $code==='en'?'required':'' }}></div><div class="col-md-6"><label class="form-label">Supporting text</label><input class="form-control" name="sub_title[{{ $code }}]" data-heading-field="subtitle" data-lang="{{ $code }}" {{ $code==='en'?'required':'' }}></div></div></div>@endforeach</div>
<div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button class="btn btn-success">Save section heading</button></div>
</form></div></div></div>
<div class="modal fade content-editor" id="featureModal" tabindex="-1"><div class="modal-dialog modal-lg modal-dialog-centered"><div class="modal-content">
<form method="POST" action="{{ route('features.store') }}" enctype="multipart/form-data" data-store="{{ route('features.store') }}" data-update="{{ url('/features') }}">
@csrf <input type="hidden" name="_method" value="POST"><input type="hidden" name="section" value="features">
<div class="modal-header"><h5 class="modal-title">Add benefit</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
<div class="modal-body"><div class="row"><div class="col-md-6 mb-3"><label class="form-label">Icon</label><select name="icon" class="form-select"><option value="">Automatic</option>@foreach($iconOptions as $icon=>$label)<option value="{{ $icon }}">{{ $label }}</option>@endforeach</select></div><div class="col-md-6 mb-3"><label class="form-label">Optional image</label><input type="file" name="image_url" class="form-control" accept="image/*"></div></div>
@foreach($languages as $code=>$language)<div class="language-box"><strong>{{ $language }}</strong><div class="row mt-2"><div class="col-md-5"><input class="form-control" name="title[{{ $code }}]" data-field="title" data-lang="{{ $code }}" placeholder="Benefit title" {{ $code==='en'?'required':'' }}></div><div class="col-md-7"><input class="form-control" name="description[{{ $code }}]" data-field="description" data-lang="{{ $code }}" placeholder="Short description" {{ $code==='en'?'required':'' }}></div></div></div>@endforeach
</div><div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button class="btn btn-success">Save benefit</button></div>
</form></div></div></div>

<div class="modal fade content-editor" id="counterModal" tabindex="-1"><div class="modal-dialog modal-lg modal-dialog-centered"><div class="modal-content">
<form method="POST" action="{{ route('counters.store') }}" enctype="multipart/form-data" data-store="{{ route('counters.store') }}" data-update="{{ url('/counters') }}">
@csrf <input type="hidden" name="_method" value="POST"><input type="hidden" name="section" value="counters">
<div class="modal-header"><h5 class="modal-title">Add statistic</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
<div class="modal-body"><div class="row"><div class="col-md-4 mb-3"><label class="form-label">Number</label><input type="number" step="any" name="number" class="form-control" required></div><div class="col-md-4 mb-3"><label class="form-label">Icon</label><select name="icon" class="form-select"><option value="">Automatic</option>@foreach($iconOptions as $icon=>$label)<option value="{{ $icon }}">{{ $label }}</option>@endforeach</select></div><div class="col-md-4 mb-3"><label class="form-label">Optional image</label><input type="file" name="image_url" class="form-control" accept="image/*"></div></div>
@foreach($languages as $code=>$language)<div class="language-box"><strong>{{ $language }}</strong><input class="form-control mt-2" name="title[{{ $code }}]" data-field="title" data-lang="{{ $code }}" placeholder="Statistic label" {{ $code==='en'?'required':'' }}></div>@endforeach
</div><div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button class="btn btn-success">Save statistic</button></div>
</form></div></div></div>

<div class="modal fade content-editor" id="testimonialModal" tabindex="-1"><div class="modal-dialog modal-lg modal-dialog-centered"><div class="modal-content">
<form method="POST" action="{{ route('testimonial.store') }}" enctype="multipart/form-data" data-store="{{ route('testimonial.store') }}" data-update="{{ url('/testimonial') }}">
@csrf <input type="hidden" name="_method" value="POST"><input type="hidden" name="section" value="testimonials">
<div class="modal-header"><h5 class="modal-title">Add student review</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
<div class="modal-body"><div class="mb-3"><label class="form-label">Optional student photo</label><input type="file" name="image_url" class="form-control" accept="image/*"></div>
@foreach($languages as $code=>$language)<div class="language-box"><strong>{{ $language }}</strong><div class="row mt-2"><div class="col-md-4"><input class="form-control" name="name[{{ $code }}]" data-field="name" data-lang="{{ $code }}" placeholder="Student name" {{ $code==='en'?'required':'' }}></div><div class="col-md-8"><input class="form-control" name="feedback[{{ $code }}]" data-field="feedback" data-lang="{{ $code }}" placeholder="Student feedback" {{ $code==='en'?'required':'' }}></div></div></div>@endforeach
</div><div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button class="btn btn-success">Save review</button></div>
</form></div></div></div>
@endsection

@section('script')
<script>
document.getElementById('headingModal').addEventListener('show.bs.modal',function(event){
    const button=event.relatedTarget, form=this.querySelector('form');
    form.querySelector('[name="id"]').value=button.dataset.section;
    form.querySelector('[name="section"]').value=button.dataset.sectionKey;
    this.querySelector('.modal-title').textContent='Edit '+button.dataset.label;
    ['title','subtitle'].forEach(function(field){
        let values={}; try{values=JSON.parse(button.dataset[field]||'{}')}catch(e){}
        form.querySelectorAll('[data-heading-field="'+field+'"]').forEach(function(input){input.value=values[input.dataset.lang]||'';});
    });
});
document.querySelectorAll('.section-switcher [data-bs-toggle="tab"]').forEach(function(tab){
    tab.addEventListener('shown.bs.tab',function(){
        const url=new URL(window.location.href);
        url.searchParams.set('section',tab.dataset.section);
        window.history.replaceState({},'',url);
    });
});
document.querySelectorAll('.content-editor').forEach(function(modal){
    modal.addEventListener('show.bs.modal',function(event){
        const button=event.relatedTarget, form=modal.querySelector('form'), edit=button.dataset.mode==='edit';
        form.reset(); form.action=edit ? form.dataset.update+'/'+button.dataset.id : form.dataset.store;
        form.querySelector('[name="_method"]').value=edit?'PUT':'POST';
        modal.querySelector('.modal-title').textContent=(edit?'Edit ':'Add ')+(modal.id==='featureModal'?'benefit':modal.id==='counterModal'?'statistic':'student review');
        ['icon','number'].forEach(function(field){const input=form.querySelector('[name="'+field+'"]');if(input)input.value=button.dataset[field]||'';});
        ['title','description','name','feedback'].forEach(function(field){
            let values={}; try{values=JSON.parse(button.dataset[field]||'{}')}catch(e){}
            form.querySelectorAll('[data-field="'+field+'"]').forEach(function(input){input.value=values[input.dataset.lang]||'';});
        });
    });
});
</script>
@endsection
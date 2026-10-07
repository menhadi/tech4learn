@extends('layouts.master')
@section('title', 'Update Logo and Favicon')

@section('content')
@component('components.breadcrumb')
@slot('li_1', 'Configurations')
@slot('title', 'Update Logo and Favicon')
@endcomponent

<div class="row">
    <div class="col-lg-12">
        <div class="card">
            <div class="card-header">
                <h4 class="card-title mb-0">Update Logo and Favicon</h4>
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('configurations.updateLogoFavicon') }}"
                    enctype="multipart/form-data">
                    @csrf
                    <div class="mb-3">
                        <label for="logo" class="form-label">Logo (Recommended size: 182x32 pixels)</label>
                        <input type="file" id="logo" name="logo" class="form-control" />
                        <div class="invalid-feedback">Please upload a valid logo.</div>
                        @if(optional($configuration)->logo)
                            <div class="mt-3 d-flex align-items-center gap-3 flex-wrap">
                                <img src="{{ asset('storage/' . $configuration->logo) }}" alt="Logo"
                                    style="max-width: 100px;">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" value="1" id="remove_logo" name="remove_logo">
                                    <label class="form-check-label" for="remove_logo">
                                        Remove saved logo and use website name
                                    </label>
                                </div>
                            </div>
                        @endif
                    </div>
                    <div class="border rounded p-3 mb-4 bg-light">
                        <h5 class="mb-1">Fallback Brand (when no logo is uploaded)</h5>
                        <p class="text-muted small">All fields are optional. Empty fields use the current website defaults.</p>
                        <div class="row g-3">
                            <div class="col-md-5"><label class="form-label" for="brand_fallback_name">Website Name</label><input class="form-control" id="brand_fallback_name" name="brand_fallback_name" value="{{ old('brand_fallback_name', $configuration->brand_fallback_name) }}" placeholder="{{ $configuration->name ?: 'ExamElite' }}"></div>
                            <div class="col-md-4"><label class="form-label" for="brand_fallback_tagline">Tagline</label><input class="form-control" id="brand_fallback_tagline" name="brand_fallback_tagline" value="{{ old('brand_fallback_tagline', $configuration->brand_fallback_tagline) }}" placeholder="Online Exams Platform"></div>
                            <div class="col-md-3"><label class="form-label" for="brand_fallback_icon">Icon</label><select class="form-select" id="brand_fallback_icon" name="brand_fallback_icon"><option value="">Default graduation cap</option>@foreach(['ri-graduation-cap-line'=>'Graduation cap','ri-book-open-line'=>'Open book','ri-stack-line'=>'Study stack','ri-award-line'=>'Award','ri-lightbulb-line'=>'Learning bulb','ri-pencil-ruler-2-line'=>'Education tools'] as $value=>$label)<option value="{{ $value }}" {{ old('brand_fallback_icon', $configuration->brand_fallback_icon) === $value ? 'selected' : '' }}>{{ $label }}</option>@endforeach</select></div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="favicon" class="form-label">Favicon (32x32 pixels)</label>
                        <input type="file" id="favicon" name="favicon" class="form-control" />
                        <div class="invalid-feedback">Please upload a valid favicon.</div>
                        @if(optional($configuration)->favicon)
                            <div class="mt-3 d-flex align-items-center gap-3 flex-wrap">
                                <img src="{{ asset('storage/' . $configuration->favicon) }}" alt="Favicon"
                                    style="max-width: 50px;">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" value="1" id="remove_favicon" name="remove_favicon">
                                    <label class="form-check-label" for="remove_favicon">
                                        Remove saved favicon and use default favicon
                                    </label>
                                </div>
                            </div>
                        @endif
                    </div>
                    <button type="submit" class="btn btn-success">Update</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@section('script')
<script>
    document.getElementById('logo').addEventListener('change', function(event) {
        const [file] = event.target.files;
        if (file) {
            let preview = event.target.parentNode.querySelector('img.preview-logo');
            if (!preview) {
                preview = document.createElement('img');
                preview.classList.add('preview-logo');
                event.target.parentNode.appendChild(preview);
            }
            preview.src = URL.createObjectURL(file);
            preview.style.maxWidth = '100px';
            preview.style.marginTop = '10px';
        }
    });

    document.getElementById('favicon').addEventListener('change', function(event) {
        const [file] = event.target.files;
        if (file) {
            const img = new Image();
            img.onload = function() {
                if (img.width !== 32 || img.height !== 32) {
                    alert('Favicon must be 32x32 pixels.');
                    event.target.value = '';
                } else {
                    let preview = event.target.parentNode.querySelector('img.preview-favicon');
                    if (!preview) {
                        preview = document.createElement('img');
                        preview.classList.add('preview-favicon');
                        event.target.parentNode.appendChild(preview);
                    }
                    preview.src = URL.createObjectURL(file);
                    preview.style.maxWidth = '50px';
                    preview.style.marginTop = '10px';
                }
            };
            img.src = URL.createObjectURL(file);
        }
    });
</script>
@endsection

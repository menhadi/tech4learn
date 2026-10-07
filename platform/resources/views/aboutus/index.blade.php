@extends('layouts.master')
@section('rich-editor', true)
@section('title', 'About Us')

@section('css')
<link href="{{ URL::asset('build/libs/sweetalert2/sweetalert2.min.css') }}" rel="stylesheet" type="text/css" />
{{-- Language Tabs ke liye CSS --}}
<style>
    .nav-tabs .nav-link.active {
        background-color: #f0f3f6;
        border-bottom: 2px solid var(--vz-primary);
    }
    .tab-content {
        padding-top: 20px;
    }
</style>
@endsection

@section('content')
@component('components.breadcrumb')
@slot('li_1', '')
@slot('title', 'About Us')
@endcomponent

{{-- Hum yahan language list define karenge --}}
@php
    $languages = [
        'en' => 'English',
        'hi' => 'Hindi',
    ];
@endphp

<div class="row">
    <div class="col-lg-12">
        <div class="card">
            <div class="card-header">
                <h4 class="card-title mb-0">Edit About Us Page</h4>
            </div>
            
            <form method="POST" action="{{ route('aboutus.update', $data->id ?? 1) }}" enctype="multipart/form-data">
                @csrf
                @method('PUT')

                <div class="card-body">
                    <div class="mb-3">
                        <label for="image-field" class="form-label">Image</label>
                        <input type="file" id="image-field" name="image_url" class="form-control" accept="image/*" />
                        <div class="invalid-feedback">Please upload an image.</div>

                        @if (!empty($data->image_url))
                            <div id="image-preview" class="mt-2">
                                <label>Current Image:</label><br>
                                <img src="{{ asset($data->image_url) }}" id="preview-img" alt="Current Image" style="max-height: 100px;">
                            </div>
                        @endif
                    </div>

                    {{-- ========================================================== --}}
                    {{-- YAHAN CHANGE KIYA HAI: Title aur Description ke liye tabs --}}
                    {{-- ========================================================== --}}

                    <ul class="nav nav-tabs" id="aboutUsTabs" role="tablist">
                        @foreach($languages as $langCode => $langName)
                            <li class="nav-item" role="presentation">
                                <button class="nav-link {{ $loop->first ? 'active' : '' }}" id="aboutus-tab-{{ $langCode }}" data-bs-toggle="tab" data-bs-target="#aboutus-content-{{ $langCode }}" type="button" role="tab" aria-controls="aboutus-content-{{ $langCode }}" aria-selected="{{ $loop->first ? 'true' : 'false' }}">{{ $langName }}</button>
                            </li>
                        @endforeach
                    </ul>

                    <div class="tab-content" id="aboutUsTabsContent">
                        @foreach($languages as $langCode => $langName)
                            <div class="tab-pane fade {{ $loop->first ? 'show active' : '' }}" id="aboutus-content-{{ $langCode }}" role="tabpanel" aria-labelledby="aboutus-tab-{{ $langCode }}">
                                
                                <div class="mb-3">
                                    <label for="title-field-{{ $langCode }}" class="form-label">Title ({{ strtoupper($langCode) }})</label>
                                    <input type="text" 
                                           id="title-field-{{ $langCode }}" 
                                           name="title[{{ $langCode }}]" 
                                           class="form-control"
                                           value="{{ old('title.'.$langCode, $data->getTranslation('title', $langCode, false) ?? '') }}"
                                           placeholder="Enter About Us Title in {{ $langName }}" 
                                           {{ $langCode == 'en' ? 'required' : '' }} />
                                    <div class="invalid-feedback">Please enter a title.</div>
                                </div>

                                <div class="mb-3">
                                    <label for="description-{{ $langCode }}" class="form-label">Description ({{ strtoupper($langCode) }})</label>
                                    {{-- Hum component ko unique ID denge --}}
                                    <x-textarea-editor 
                                        id="description-{{ $langCode }}" 
                                        name="description[{{ $langCode }}]" 
                                        label=""
                                        placeholder="Enter Description here in {{ $langName }}"
                                        :value="old('description.'.$langCode, isset($data) ? $data->getTranslation('description', $langCode, false) : '')" />
                                    
                                    @if ($errors->has('description.' . $langCode))
                                        <div class="invalid-feedback d-block">{{ $errors->first('description.' . $langCode) }}</div>
                                    @endif
                                </div>

                            </div>
                        @endforeach
                    </div>
                
                    @include('partials.seo-fields', ['seoModel' => $data ?? null])

                </div>

                <div class="card-footer">
                    <div class="hstack gap-2 justify-content-end">
                        <a href="{{ route('dashboard') }}" class="btn btn-light">Close</a>
                        <button type="submit" class="btn btn-success">Update About Us</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@section('script')
<script src="{{ URL::asset('build/libs/prismjs/prism.js') }}"></script>
<script src="{{ URL::asset('build/libs/list.pagination.js/list.pagination.min.js') }}"></script>
<script src="{{ URL::asset('build/libs/sweetalert2/sweetalert2.min.js') }}"></script>



<script>
    document.addEventListener('DOMContentLoaded', function() {
        // ... (SweetAlert wala code waise hi rehne do) ...
        @if(session('success'))
        Swal.fire({
            icon: 'success'
            , title: 'Success'
            , text: '{{ session('success') }}'
            , timer: 3000
            , showConfirmButton: false
        });
        @endif

        @if(session('error'))
        Swal.fire({
            icon: 'error'
            , title: 'Error'
            , text: '{{ session('error') }}'
            , timer: 3000
            , showConfirmButton: false
        });
        @endif
        
        @if($errors->any())
            var errorText = '<ul>';
            @foreach ($errors->all() as $error)
                errorText += '<li>{{ $error }}</li>';
            @endforeach
            errorText += '</ul>';

            Swal.fire({
                icon: 'error'
                , title: 'Validation Error'
                , html: errorText
                , showConfirmButton: true
            });
        @endif
    });
</script>
@endsection
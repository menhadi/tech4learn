@extends('layouts.master')
@section('title', 'Testimonial')

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
@slot('title', 'Testimonial')
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
                <h4 class="card-title mb-0">Testimonial Title</h4>
            </div>
            <div class="card-body">
                {{-- ========================================================== --}}
                {{-- YAHAN CHANGE KIYA HAI: Title form ko tabs mein daala hai --}}
                {{-- ========================================================== --}}
                <form action="{{ route('website.title.update') }}" method="post">
                    @csrf
                    <input type="hidden" name="id" value="2"> {{-- Ye Testimonials (ID: 2) titles ko update karega --}}

                    <ul class="nav nav-tabs" id="titleTabs" role="tablist">
                        @foreach($languages as $langCode => $langName)
                            <li class="nav-item" role="presentation">
                                <button class="nav-link {{ $loop->first ? 'active' : '' }}" id="title-tab-{{ $langCode }}" data-bs-toggle="tab" data-bs-target="#title-content-{{ $langCode }}" type="button" role="tab" aria-controls="title-content-{{ $langCode }}" aria-selected="{{ $loop->first ? 'true' : 'false' }}">{{ $langName }}</button>
                            </li>
                        @endforeach
                    </ul>

                    <div class="tab-content" id="titleTabsContent">
                        @foreach($languages as $langCode => $langName)
                            <div class="tab-pane fade {{ $loop->first ? 'show active' : '' }}" id="title-content-{{ $langCode }}" role="tabpanel" aria-labelledby="title-tab-{{ $langCode }}">
                                <div class="row">
                                    <div class="col-lg-6 mb-3">
                                        <label for="title-{{ $langCode }}" class="form-label">Title ({{ strtoupper($langCode) }})</label>
                                        <input type="text" 
                                               name="title[{{ $langCode }}]" 
                                               value="{{ old('title.'.$langCode, $titles->getTranslation('title', $langCode, false)) }}" 
                                               class="form-control"
                                               placeholder="Enter Title in {{ $langName }}">
                                    </div>
                                    <div class="col-lg-6 mb-3">
                                        <label for="sub_title-{{ $langCode }}" class="form-label">Sub Title ({{ strtoupper($langCode) }})</label>
                                        <input type="text" 
                                               name="sub_title[{{ $langCode }}]" 
                                               value="{{ old('sub_title.'.$langCode, $titles->getTranslation('sub_title', $langCode, false)) }}" 
                                               class="form-control"
                                               placeholder="Enter Sub Title in {{ $langName }}">
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                    
                    <div class="row mt-2">
                        <div class="col-lg-12">
                            <button class="btn btn-success">Submit</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>


<div class="row">
    <div class="col-lg-12">
        <div class="card">
            <div class="card-header">
                <h4 class="card-title mb-0">Add, Edit & Remove Testimonial</h4>
            </div>
            {{-- ... (search bar etc. waise hi) ... --}}
            <div class="card-body">
                <div class="listjs-table" id="groupList">
                    <div class="row g-4 mb-3">
                        <div class="col-sm-auto">
                            <div>
                                <button type="button" class="btn btn-success add-btn" data-bs-toggle="modal"
                                    id="create-btn" data-bs-target="#showModal"><i
                                        class="ri-add-line align-bottom me-1"></i> Add</button>
                            </div>
                        </div>
                        <div class="col-sm">
                            <div class="d-flex justify-content-sm-end">
                                <div class="search-box ms-2">
                                    <input type="text" id="search-input" class="form-control search"
                                        placeholder="Search..." value="{{ request('search') }}">
                                    <i class="ri-search-line search-icon"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="table-responsive table-card mt-3 mb-1">
                        <table class="table align-middle table-nowrap" id="table">
                            <thead class="table-light">
                                <tr>
                                    <th class="sort" data-sort="Image">Image</th>
                                    <th class="sort" data-sort="title">Name</th>
                                    <th class="sort" data-sort="created_at">Created At</th>
                                    <th class="sort" data-sort="updated_at">Updated At</th>
                                    <th class="sort" data-sort="action">Action</th>
                                </tr>
                            </thead>
                            <tbody class="list form-check-all">
                                @foreach ($data as $val)
                                <tr>
                                    <td class="image_url">
                                        @if($val->image_url)
                                            <img src="{{ asset($val->image_url) }}" alt="Hero Image" style="max-height: 60px;">
                                        @else
                                            N/A
                                        @endif
                                    </td>
                                    {{-- Model update hone ke baad, ye automatic current language ka name dikhayega --}}
                                    <td class="name">{{ $val->name }}</td>
                                    <td class="created_at">@formatDate($val->created_at)</td>
                                    <td class="updated_at">@formatDate($val->updated_at)</td>
                                    <td>
                                        <div class="d-flex gap-2">
                                            <div class="edit">
                                                {{-- ========================================================== --}}
                                                {{-- YAHAN CHANGE KIYA HAI: data attributes ko JSON kiya --}}
                                                {{-- ========================================================== --}}
                                                <button class="btn btn-sm btn-success edit-item-btn"
                                                    data-bs-toggle="modal" data-bs-target="#showModal"
                                                    data-id="{{ $val->id }}"
                                                    data-image_url="{{ $val->image_url }}"
                                                    data-name-translations="{{ json_encode($val->getTranslations('name')) }}"
                                                    data-feedback-translations="{{ json_encode($val->getTranslations('feedback')) }}">Edit</button>
                                            </div>
                                            <div class="remove">
                                                <button class="btn btn-sm btn-danger remove-item-btn"
                                                    data-bs-toggle="modal" data-bs-target="#deleteRecordModal"
                                                    data-id="{{ $val->id }}">Remove</button>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                        {{-- ... (no result div) ... --}}
                    </div>

                    <div class="d-flex justify-content-end">
                        {{ $data->links('vendor.pagination.custom') }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="showModal" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-light p-3">
                <h5 class="modal-title" id="exampleModalLabel"></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"
                    id="close-modal"></button>
            </div>
            <form class="tablelist-form" autocomplete="off" method="POST" action="{{ route('testimonial.store') }}" enctype="multipart/form-data">
                @csrf
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="image-field" class="form-label">Image</label>
                        <input type="file" id="image-field" name="image_url" class="form-control" accept="image/*" />
                        <div class="invalid-feedback">Please upload an image.</div>

                        <div id="image-preview" class="mt-2" style="display: none;">
                            <label>Current Image:</label><br>
                            <img src="" id="preview-img" alt="Current Image" style="max-height: 100px;">
                        </div>
                    </div>

                    {{-- ========================================================== --}}
                    {{-- YAHAN CHANGE KIYA HAI: name aur feedback ke liye tabs --}}
                    {{-- ========================================================== --}}
                    <ul class="nav nav-tabs" id="testimonialTabs" role="tablist">
                        @foreach($languages as $langCode => $langName)
                            <li class="nav-item" role="presentation">
                                <button class="nav-link {{ $loop->first ? 'active' : '' }}" id="testimonial-tab-{{ $langCode }}" data-bs-toggle="tab" data-bs-target="#testimonial-content-{{ $langCode }}" type="button" role="tab" aria-controls="testimonial-content-{{ $langCode }}" aria-selected="{{ $loop->first ? 'true' : 'false' }}">{{ $langName }}</button>
                            </li>
                        @endforeach
                    </ul>

                    <div class="tab-content" id="testimonialTabsContent">
                        @foreach($languages as $langCode => $langName)
                            <div class="tab-pane fade {{ $loop->first ? 'show active' : '' }}" id="testimonial-content-{{ $langCode }}" role="tabpanel" aria-labelledby="testimonial-tab-{{ $langCode }}">
                                
                                <div class="mb-3">
                                    <label for="name-field-{{ $langCode }}" class="form-label">Name ({{ strtoupper($langCode) }})</label>
                                    <input type="text" id="name-field-{{ $langCode }}" name="name[{{ $langCode }}]" class="form-control"
                                        placeholder="Enter Name in {{ $langName }}" {{ $langCode == 'en' ? 'required' : '' }} />
                                    <div class="invalid-feedback">Please enter a Name.</div>
                                </div>

                                <div class="mb-3">
                                    <label for="feedback-field-{{ $langCode }}" class="form-label">Feedback ({{ strtoupper($langCode) }})</label>
                                    <input type="text" id="feedback-field-{{ $langCode }}" name="feedback[{{ $langCode }}]" class="form-control"
                                        placeholder="Enter Feedback in {{ $langName }}" {{ $langCode == 'en' ? 'required' : '' }} />
                                    <div class="invalid-feedback">Please enter Feedback.</div>
                                </div>

                            </div>
                        @endforeach
                    </div>
                </div>
                <div class="modal-footer">
                    <div class="hstack gap-2 justify-content-end">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-success" id="add-btn">Add Testimonial</button>
                        <button type="submit" class="btn btn-success" id="edit-btn" style="display: none;">Update
                            Testimonial</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade zoomIn" id="deleteRecordModal" tabindex="-1" aria-hidden="true">
    {{-- ... (is modal mein koi change nahi) ... --}}
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"
                    id="btn-close"></button>
            </div>
            <div class="modal-body">
                <div class="mt-2 text-center">
                    <lord-icon src="https://cdn.lordicon.com/gsqxdxog.json" trigger="loop"
                        colors="primary:#f7b84b,secondary:#f06548" style="width:100px;height:100px"></lord-icon>
                    <div class="mt-4 pt-2 fs-15 mx-4 mx-sm-5">
                        <h4>Are you Sure ?</h4>
                        <p class="text-muted mx-4 mb-0">Are you Sure You want to Remove this Record ?</p>
                    </div>
                </div>
                <div class="d-flex gap-2 justify-content-center mt-4 mb-2">
                    <button type="button" class="btn w-sm btn-light" data-bs-dismiss="modal">Close</button>
                    <form id="delete-form" method="POST" action="">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn w-sm btn-danger">Yes, Delete It!</button>
                    </form>
                </div>
            </div>
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
        // Language list (JS mein bhi use hoga)
        const languages = @json($languages);
        const langCodes = Object.keys(languages);

        // ... (search wala code waise hi) ...

        // Add/Edit Modal
        document.getElementById('showModal').addEventListener('show.bs.modal', function(event) {
            var button = event.relatedTarget;
            var id = button.getAttribute('data-id');
            var image = button.getAttribute('data-image_url');
            
            // YAHAN CHANGE KIYA HAI: JSON data read kar rahe hain
            var nameTranslations = JSON.parse(button.getAttribute('data-name-translations') || '{}');
            var feedbackTranslations = JSON.parse(button.getAttribute('data-feedback-translations') || '{}');
            
            var modal = this;

            // Reset image preview
            modal.querySelector('#image-preview').style.display = 'none';
            modal.querySelector('#preview-img').src = '';
            
            // Clear all old language fields
            langCodes.forEach(function(lang) {
                modal.querySelector(`#name-field-${lang}`).value = '';
                modal.querySelector(`#feedback-field-${lang}`).value = '';
            });

            if (id) { // Edit Mode
                modal.querySelector('.modal-title').textContent = 'Edit Testimonial';
                modal.querySelector('#add-btn').style.display = 'none';
                modal.querySelector('#edit-btn').style.display = 'block';

                // Populate all language fields
                langCodes.forEach(function(lang) {
                    modal.querySelector(`#name-field-${lang}`).value = nameTranslations[lang] || '';
                    modal.querySelector(`#feedback-field-${lang}`).value = feedbackTranslations[lang] || '';
                });

                // Show current image
                if (image) {
                    modal.querySelector('#image-preview').style.display = 'block';
                    modal.querySelector('#preview-img').src = '/' + image;
                }

                // Update action URL and method
                modal.querySelector('form').setAttribute('action', '{{ route("testimonial.update", ":id") }}'.replace(':id', id));
                if (!modal.querySelector('input[name="_method"]')) {
                    modal.querySelector('form').insertAdjacentHTML('beforeend', '<input type="hidden" name="_method" value="PUT">');
                }
                
            } else { // Add Mode
                modal.querySelector('.modal-title').textContent = 'Add Testimonial';
                modal.querySelector('#add-btn').style.display = 'block';
                modal.querySelector('#edit-btn').style.display = 'none';

                modal.querySelector('form').setAttribute('action', '{{ route("testimonial.store") }}');
                var methodInput = modal.querySelector('input[name="_method"]');
                if (methodInput) {
                    methodInput.remove();
                }
            }

            // Reset file input
            modal.querySelector('#image-field').value = '';
        });


        // Delete Modal
        document.getElementById('deleteRecordModal').addEventListener('show.bs.modal', function(event) {
            var button = event.relatedTarget;
            var id = button.getAttribute('data-id');
            var action = "{{ route('testimonial.destroy', ':id') }}";
            action = action.replace(':id', id);
            document.getElementById('delete-form').setAttribute('action', action);
        });

        // ... (sweetalert wala code waise hi) ...
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
        Swal.fire({
            icon: 'error'
            , title: 'Validation Error'
            , text: '{{ implode(", ", $errors->all()) }}'
            , timer: 5000
            , showConfirmButton: true
        });
        @endif
    });
</script>
@endsection
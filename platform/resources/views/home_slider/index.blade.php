@extends('layouts.master')
@section('title', 'Hero Slider')

@section('css')
<link href="{{ URL::asset('build/libs/sweetalert2/sweetalert2.min.css') }}" rel="stylesheet" type="text/css" />
@endsection

@section('content')
@component('components.breadcrumb')
@slot('li_1', '')
@slot('title', 'Hero Slider')
@endcomponent

<div class="row">
    <div class="col-lg-12">
        <div class="card">
            <div class="card-header">
                <h4 class="card-title mb-0">Add, Edit & Remove Hero Slider / Banners</h4> {{-- Title Updated --}}
            </div>
            <div class="card-body">
                <div class="listjs-table" id="groupList">
                    <div class="row g-4 mb-3">
                        <div class="col-sm-auto">
                            <div>
                                <button type="button" class="btn btn-success add-btn" data-bs-toggle="modal"
                                    id="create-btn" data-bs-target="#showModal"><i
                                        class="ri-add-line align-bottom me-1"></i> Add Banner</button> {{-- Text Updated --}}
                            </div>
                        </div>
                        <div class="col-sm">
                            <form method="GET" action="{{ route('heroslider.index') }}">
                                <div class="d-flex justify-content-sm-end">
                                    <div class="search-box ms-2">
                                        <input type="text" name="search" id="search-input" class="form-control search" placeholder="Search Banners..." value="{{ request('search') }}"> {{-- Placeholder Updated --}}
                                        <i class="ri-search-line search-icon"></i>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                    <div class="table-responsive table-card mt-3 mb-1">
                        <table class="table align-middle table-nowrap" id="table">
                            <thead class="table-light">
                                <tr>
                                    <th class="sort" data-sort="Image">Banner Image</th> {{-- Header Updated --}}
                                    <th class="sort" data-sort="title">Title (Used for Alt Text)</th> {{-- Header Updated --}}
                                    <th class="sort" data-sort="updated_at">Updated At</th>
                                    <th class="sort" data-sort="action">Action</th>
                                </tr>
                            </thead>
                            <tbody class="list form-check-all">
                                @forelse ($data as $val)
                                <tr>
                                    <td class="image_url">
                                        @if($val->image_url)
                                            <img src="{{ asset($val->image_url) }}" alt="Banner Image" style="max-height: 50px; border-radius: 4px;">
                                        @else
                                            N/A
                                        @endif
                                    </td>
                                    <td class="title">{{ $val->title }}</td>
                                    <td class="updated_at">@formatDate($val->updated_at)</td>
                                    <td>
                                        <div class="d-flex gap-2">
                                            <div class="edit">
                                                <button class="btn btn-sm btn-success edit-item-btn"
                                                    data-bs-toggle="modal" data-bs-target="#showModal"
                                                    data-id="{{ $val->id }}"
                                                    data-image_url="{{ $val->image_url }}"
                                                    data-title="{{ $val->title }}"
                                                    data-description="{{ $val->description }}">Edit</button>
                                            </div>
                                            <div class="remove">
                                                <button class="btn btn-sm btn-danger remove-item-btn"
                                                    data-bs-toggle="modal" data-bs-target="#deleteRecordModal"
                                                    data-id="{{ $val->id }}">Remove</button>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="4" class="text-center">No banners found.</td> {{-- Colspan Updated --}}
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div class="d-flex justify-content-end mt-3">
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
                <h5 class="modal-title" id="exampleModalLabel">Add/Edit Banner</h5> {{-- Title Updated --}}
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" id="close-modal"></button>
            </div>
            <form class="tablelist-form needs-validation" autocomplete="off" method="POST" action="{{ route('heroslider.store') }}" enctype="multipart/form-data" novalidate>
                @csrf
                <div class="modal-body">
                    <input type="hidden" name="id" id="slider-id">
                    <div class="mb-3">
                        <label for="image-field" class="form-label">Banner Image</label> {{-- Label Updated --}}
                        <input type="file" id="image-field" name="image_url" class="form-control" accept="image/png, image/jpeg, image/jpg, image/gif, image/svg+xml">
                        {{-- ✅ RECOMMENDED SIZE TEXT UPDATED HERE --}}
                        <small class="form-text text-muted">Recommended size: **1600x300 pixels** (approx 5:1 ratio) or similar wide banner format.</small>
                        <div class="invalid-feedback">Please upload a valid image (jpg, png, gif, svg). Max 2MB.</div>
                        <div id="image-preview" class="mt-2" style="display: none;">
                            <label class="form-label">Current Image:</label><br>
                            <img src="" id="preview-img" alt="Current Image" style="max-height: 100px; border-radius: 4px;">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="title-field" class="form-label">Title (for reference / alt text)</label> {{-- Label Updated --}}
                        <input type="text" id="title-field" name="title" class="form-control" placeholder="Enter Banner Title" required />
                        <div class="invalid-feedback">Please enter a Title.</div>
                    </div>
                    <div class="mb-3">
                        <label for="description-field" class="form-label">Description (Optional - Not displayed on banner)</label> {{-- Label Updated --}}
                        <textarea id="description-field" name="description" class="form-control" placeholder="Enter Banner Description (for internal reference)" rows="3"></textarea> {{-- Removed required --}}
                        {{-- <div class="invalid-feedback">Please enter a Description.</div> --}}
                    </div>
                </div>
                <div class="modal-footer">
                    <div class="hstack gap-2 justify-content-end">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-success" id="save-btn">Save Banner</button> {{-- Text Updated --}}
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade zoomIn" id="deleteRecordModal" tabindex="-1" aria-hidden="true">
    {{-- Delete Modal Content (Same as before) --}}
    <div class="modal-dialog modal-dialog-centered"> <div class="modal-content"> <div class="modal-header"> <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" id="btn-close"></button> </div> <div class="modal-body"> <div class="mt-2 text-center"> <lord-icon src="https://cdn.lordicon.com/gsqxdxog.json" trigger="loop" colors="primary:#f7b84b,secondary:#f06548" style="width:100px;height:100px"></lord-icon> <div class="mt-4 pt-2 fs-15 mx-4 mx-sm-5"> <h4>Are you Sure ?</h4> <p class="text-muted mx-4 mb-0">Are you Sure You want to Remove this Record ?</p> </div> </div> <div class="d-flex gap-2 justify-content-center mt-4 mb-2"> <button type="button" class="btn w-sm btn-light" data-bs-dismiss="modal">Close</button> <form id="delete-form" method="POST" action=""> @csrf @method('DELETE') <button type="submit" class="btn w-sm btn-danger">Yes, Delete It!</button> </form> </div> </div> </div> </div>
</div>
@endsection

@section('script')
{{-- Scripts (Mostly same as before, List.js removed, SweetAlert kept) --}}
<script src="{{ URL::asset('build/libs/prismjs/prism.js') }}"></script>
<script src="{{ URL::asset('build/libs/sweetalert2/sweetalert2.min.js') }}"></script>
<script src="{{ URL::asset('build/js/app.js') }}"></script>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // --- Add/Edit Modal Logic (Updated for single save button) ---
        const showModal = document.getElementById('showModal');
        const modalForm = showModal.querySelector('form');
        const modalTitle = showModal.querySelector('.modal-title');
        const saveBtn = showModal.querySelector('#save-btn'); // Single save button
        const imagePreviewDiv = showModal.querySelector('#image-preview');
        const previewImg = showModal.querySelector('#preview-img');
        const imageInput = showModal.querySelector('#image-field');
        const sliderIdInput = showModal.querySelector('#slider-id');
        const titleInput = showModal.querySelector('#title-field');
        const descriptionInput = showModal.querySelector('#description-field');
        let currentMethodInput = null;

        showModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            const isEdit = button.classList.contains('edit-item-btn');
            modalForm.classList.remove('was-validated');
            if (currentMethodInput) { currentMethodInput.remove(); currentMethodInput = null; }
            imageInput.value = ''; imagePreviewDiv.style.display = 'none'; previewImg.src = '';
            imageInput.removeAttribute('required'); // Remove required initially

            if (isEdit) {
                const id = button.getAttribute('data-id');
                const image = button.getAttribute('data-image_url');
                const title = button.getAttribute('data-title');
                const description = button.getAttribute('data-description');
                modalTitle.textContent = 'Edit Banner'; // Updated Text
                saveBtn.textContent = 'Update Banner'; // Updated Text
                sliderIdInput.value = id; titleInput.value = title; descriptionInput.value = description;
                modalForm.action = '{{ route("heroslider.index") }}/' + id;
                currentMethodInput = document.createElement('input'); currentMethodInput.type = 'hidden'; currentMethodInput.name = '_method'; currentMethodInput.value = 'PUT'; modalForm.appendChild(currentMethodInput);
                if (image && image !== 'null' && image !== '') {
                    previewImg.src = `{{ asset('') }}${image.startsWith('/') ? image.substring(1) : image}`; // Handle leading slash if present
                    imagePreviewDiv.style.display = 'block';
                } else {
                     imageInput.setAttribute('required', ''); // Make image required only if editing AND no current image exists
                }
            } else {
                modalTitle.textContent = 'Add Banner'; // Updated Text
                saveBtn.textContent = 'Add Banner'; // Updated Text
                sliderIdInput.value = ''; titleInput.value = ''; descriptionInput.value = '';
                modalForm.action = '{{ route("heroslider.store") }}';
                 imageInput.setAttribute('required', ''); // Image is required for new sliders
            }
             // Make description not required
            descriptionInput.removeAttribute('required');
        });

        imageInput.addEventListener('change', function(event) { /* Image preview logic (same) */ const file = event.target.files[0]; if (file) { const reader = new FileReader(); reader.onload = function(e) { previewImg.src = e.target.result; imagePreviewDiv.style.display = 'block'; }; reader.readAsDataURL(file); } else { if (sliderIdInput.value === '' || previewImg.src === '') { imagePreviewDiv.style.display = 'none'; previewImg.src = ''; } } });
        modalForm.addEventListener('submit', function (event) { /* Bootstrap validation (same) */ if (!modalForm.checkValidity()) { event.preventDefault(); event.stopPropagation(); } modalForm.classList.add('was-validated'); }, false);

        // --- Delete Modal Logic (Same as before) ---
        const deleteModal = document.getElementById('deleteRecordModal');
        deleteModal.addEventListener('show.bs.modal', function(event) { /* Delete modal logic (same) */ const button = event.relatedTarget; const id = button.getAttribute('data-id'); const action = "{{ route('heroslider.destroy', ':id') }}".replace(':id', id); const deleteForm = document.getElementById('delete-form'); deleteForm.action = action; });

        // --- SweetAlert Notifications (Same as before) ---
        @if(session('success')) Swal.fire({ icon: 'success', title: 'Success', text: '{{ session('success') }}', timer: 2000, showConfirmButton: false }); @endif
        @if(session('error')) Swal.fire({ icon: 'error', title: 'Error', text: '{{ session('error') }}', timer: 3000, showConfirmButton: false }); @endif
        @if($errors->any()) Swal.fire({ icon: 'error', title: 'Validation Error', html: '{!! implode("<br>", $errors->all()) !!}', showConfirmButton: true }); @endif
    });
</script>
@endsection
@extends('layouts.master')
@section('rich-editor', true)
@section('title', 'Website Pages')

@section('css')
<link href="{{ URL::asset('build/libs/sweetalert2/sweetalert2.min.css') }}" rel="stylesheet" type="text/css" />
<style>
    .nav-tabs .nav-link.active {
        background-color: #f0f3f6;
        border-bottom: 2px solid var(--vz-primary);
        color: var(--vz-primary);
        font-weight: 600;
    }
    .tab-content { padding-top: 20px; }
    /* TinyMCE source-code dialog must stay editable above Bootstrap/admin overlays. */
    .tox-tinymce-aux,
    .tox-dialog-wrap,
    .tox-dialog,
    .tox-dialog__body,
    .tox-dialog__body-content {
        pointer-events: auto !important;
    }
    .tox-tinymce-aux { z-index: 9999 !important; }
    .tox-dialog__body-content {
        max-height: 70vh !important;
        overflow: auto !important;
    }
    .tox-dialog textarea,
    .tox-dialog__body-content textarea,
    .tox-textarea,
    .tox-textarea-wrap textarea {
        display: block !important;
        width: 100% !important;
        min-height: 420px !important;
        height: 60vh !important;
        color: var(--el-text, #0f172a) !important;
        background: #ffffff !important;
        border: 1px solid var(--el-border, #cbd5e1) !important;
        opacity: 1 !important;
        pointer-events: auto !important;
        resize: vertical !important;
        user-select: text !important;
    }
    .tox-dialog__footer .tox-button {
        background: var(--el-primary, #0f766e) !important;
        border-color: var(--el-primary, #0f766e) !important;
        color: #ffffff !important;
    }
    .tox-dialog__footer .tox-button--secondary {
        background: var(--el-secondary, #f59e0b) !important;
        border-color: var(--el-secondary, #f59e0b) !important;
        color: #111827 !important;
    }
</style>
@endsection

@section('content')
@component('components.breadcrumb')
@slot('li_1', '')
@slot('title', 'Website Pages')
@endcomponent

@php
    $languages = [
        'en' => 'English',
        'hi' => 'Hindi',
    ];
@endphp

<div class="row">
    <div class="col-lg-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h4 class="card-title mb-0">Website Pages Management</h4>
                <button type="button" class="btn btn-success add-btn" data-bs-toggle="modal" id="create-btn" data-bs-target="#showModal">
                    <i class="ri-add-line align-bottom me-1"></i> Add Page
                </button>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table align-middle table-nowrap" id="table">
                        <thead class="table-light">
                            <tr>
                                <th>Short Title</th>
                                <th>Title</th>
                                <th>Show in Menu?</th>
                                <th>Created At</th>
                                <th>Updated At</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($data as $val)
                            <tr>
                                <td>{{ $val->short_title }}</td>
                                <td>{{ $val->title }}</td>
                                <td>
                                    @if($val->show_in_menu)
                                        <span class="badge bg-success">Yes</span>
                                    @else
                                        <span class="badge bg-danger">No</span>
                                    @endif
                                </td>
                                <td>@formatDate($val->created_at)</td>
                                <td>@formatDate($val->updated_at)</td>
                                <td>
                                    <div class="d-flex gap-2">
                                        <button class="btn btn-sm btn-success edit-item-btn"
                                            data-bs-toggle="modal" data-bs-target="#showModal"
                                            data-id="{{ $val->id }}"
                                            data-short_title-translations="{{ json_encode($val->getTranslations('short_title')) }}"
                                            data-title-translations="{{ json_encode($val->getTranslations('title')) }}"
                                            data-description-translations="{{ json_encode($val->getTranslations('description')) }}"
                                            data-show_in_menu="{{ $val->show_in_menu ? '1' : '0' }}"
                                            data-show_in_footer="{{ ($val->show_in_footer ?? false) ? '1' : '0' }}">Edit</button>
                                        
                                        <button class="btn btn-sm btn-danger remove-item-btn"
                                            data-bs-toggle="modal" data-bs-target="#deleteRecordModal"
                                            data-id="{{ $val->id }}">Remove</button>
                                    </div>
                                </td>
                            </tr>
                            @empty
                            <tr><td colspan="6" class="text-center">No pages found.</td></tr>
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

{{-- Add/Edit Modal --}}
<div class="modal fade" id="showModal" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true" data-bs-focus="false">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-light p-3">
                <h5 class="modal-title" id="exampleModalLabel">Add Page</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" id="close-modal"></button>
            </div>
            
            {{-- Form start --}}
            <form id="pageForm" autocomplete="off" method="POST" action="{{ route('websitepages.store') }}">
                @csrf
                <input type="hidden" name="id" id="page-id">
                <input type="hidden" name="_method" id="form-method" value="POST">

                <div class="modal-body">
                    <ul class="nav nav-tabs" id="pageTabs" role="tablist">
                        @foreach($languages as $langCode => $langName)
                            <li class="nav-item" role="presentation">
                                <button class="nav-link {{ $loop->first ? 'active' : '' }}" id="page-tab-{{ $langCode }}" data-bs-toggle="tab" data-bs-target="#page-content-{{ $langCode }}" type="button" role="tab">{{ $langName }}</button>
                            </li>
                        @endforeach
                    </ul>

                    <div class="tab-content">
                        @foreach($languages as $langCode => $langName)
                            <div class="tab-pane fade {{ $loop->first ? 'show active' : '' }}" id="page-content-{{ $langCode }}" role="tabpanel">
                                <div class="mb-3 mt-3">
                                    <label class="form-label">Short Title (URL Slug) ({{ strtoupper($langCode) }})</label>
                                    <input type="text" id="short_title-field-{{ $langCode }}" name="short_title[{{ $langCode }}]" class="form-control" placeholder="e.g. refund-policy" maxlength="50" {{ $langCode == 'en' ? 'required' : '' }} />
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Page Title ({{ strtoupper($langCode) }})</label>
                                    <input type="text" id="title-field-{{ $langCode }}" name="title[{{ $langCode }}]" class="form-control" placeholder="Enter page title" {{ $langCode == 'en' ? 'required' : '' }} />
                                </div>
                                <div class="mb-3">
                                    {{-- Use Component but handle ID uniquely --}}
                                    @component('components.textarea-editor', [
                                        'id' => 'description-field-'.$langCode,
                                        'name' => 'description['.$langCode.']',
                                        'label' => 'Page Content ('.strtoupper($langCode).')',
                                        'placeholder' => 'Enter page content...',
                                        'value' => ''
                                    ])
                                    @endcomponent
                                </div>
                            </div>
                        @endforeach
                    </div>
                    

                    <div class="border-top pt-3 mt-3">
                        <button class="btn btn-warning btn-sm d-inline-flex align-items-center gap-2 px-3 mb-3"
                                type="button"
                                data-bs-toggle="collapse"
                                data-bs-target="#websitePageSeoSettings"
                                aria-expanded="false"
                                aria-controls="websitePageSeoSettings">
                            <i class="ri-search-eye-line"></i>
                            <span>SEO Settings</span>
                            <span class="badge bg-light text-dark">Optional</span>
                            <i class="ri-arrow-down-s-line"></i>
                        </button>

                        <div class="collapse" id="websitePageSeoSettings">

                        <div class="mb-3">
                            <label class="form-label">Meta Title</label>
                            <input type="text" name="meta_title" id="meta_title-field" class="form-control">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Meta Description</label>
                            <textarea name="meta_description" id="meta_description-field" class="form-control" rows="2"></textarea>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Meta Keywords</label>
                            <textarea name="meta_keywords" id="meta_keywords-field" class="form-control" rows="2"></textarea>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Canonical URL</label>
                            <input type="url" name="canonical_url" id="canonical_url-field" class="form-control">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">OG Title</label>
                            <input type="text" name="og_title" id="og_title-field" class="form-control">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">OG Description</label>
                            <textarea name="og_description" id="og_description-field" class="form-control" rows="2"></textarea>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">OG Image URL / Path</label>
                            <input type="text" name="og_image" id="og_image-field" class="form-control">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Robots Meta</label>
                            <select name="robots_meta" id="robots_meta-field" class="form-select">
                                <option value="index,follow">index,follow</option>
                                <option value="noindex,follow">noindex,follow</option>
                                <option value="index,nofollow">index,nofollow</option>
                                <option value="noindex,nofollow">noindex,nofollow</option>
                            </select>
                        </div>

                            <div class="mb-3">
                                <label class="form-label">SEO Schema JSON</label>
                                <textarea name="seo_schema" id="seo_schema-field" class="form-control font-monospace" rows="4"></textarea>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3 border-top pt-3">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="show_in_menu-field" name="show_in_menu" value="1" checked> 
                            <label class="form-check-label" for="show_in_menu-field">Show in Header / More menu</label>
                        </div>

                            <div class="form-check form-switch mt-2">
                                <input class="form-check-input" type="checkbox" id="show_in_footer-field" name="show_in_footer" value="1">
                                <label class="form-check-label" for="show_in_footer-field">Show in Footer</label>
                            </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-success" id="submit-btn">Save Page</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Delete Modal --}}
<div class="modal fade zoomIn" id="deleteRecordModal" tabindex="-1" aria-hidden="true">
     <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mt-2 text-center">
                    <lord-icon src="https://cdn.lordicon.com/gsqxdxog.json" trigger="loop" colors="primary:#f7b84b,secondary:#f06548" style="width:100px;height:100px"></lord-icon>
                    <div class="mt-4 pt-2 fs-15 mx-4 mx-sm-5">
                        <h4>Are you Sure?</h4>
                        <p class="text-muted mx-4 mb-0">Are you Sure You want to Remove this Page?</p>
                    </div>
                </div>
                <div class="d-flex gap-2 justify-content-center mt-4 mb-2">
                    <button type="button" class="btn w-sm btn-light" data-bs-dismiss="modal">Close</button>
                    <form id="delete-form" method="POST" action="">
                        @csrf @method('DELETE')
                        <button type="submit" class="btn w-sm btn-danger">Yes, Delete It!</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('script')
<script src="{{ URL::asset('build/libs/sweetalert2/sweetalert2.min.js') }}"></script>
{{-- TinyMCE Library --}}

<script>
    // Bootstrap modal focus trapping can block TinyMCE's Source Code dialog.
    // Let TinyMCE auxiliary dialogs receive keyboard and mouse focus normally.
    document.addEventListener('focusin', function(event) {
        if (event.target.closest('.tox-tinymce-aux, .tox-dialog, .tox-dialog__body, .tox-dialog__body-content, .tox-textarea, .tox-textarea-wrap')) {
            event.stopImmediatePropagation();
        }
    }, true);

    document.addEventListener('DOMContentLoaded', function() {
        const languages = @json($languages);
        const langCodes = Object.keys(languages);
        
        // --- 1. INITIALIZE TINYMCE (With Source Code & Image Upload) ---
        function initEditors() {
            // Destroy existing instances first to avoid duplication issues
            langCodes.forEach(lang => {
                if(tinymce.get(`description-field-${lang}`)) {
                    tinymce.get(`description-field-${lang}`).remove();
                }
            });

            tinymce.init({
                selector: 'textarea.myeditorinstance',
                height: 400,
                menubar: false,
                // ✅ ADDED 'code' to plugins
                plugins: [
                    "advlist", "anchor", "autolink", "charmap", "code", "codesample", "fullscreen",
                    "help", "image", "insertdatetime", "link", "lists", "media",
                    "preview", "searchreplace", "table", "visualblocks",
                ],
                // ✅ ADDED 'code' to toolbar
                toolbar: "code | undo redo | styleselect | bold italic underline | alignleft aligncenter alignright | bullist numlist | link image | removeformat",
                branding: false,
                setup: function(editor) {
                    editor.on('OpenWindow', function() {
                        setTimeout(function() {
                            document.querySelectorAll('.tox-dialog textarea, .tox-dialog .tox-textarea').forEach(function(field) {
                                field.removeAttribute('readonly');
                                field.removeAttribute('disabled');
                                field.style.pointerEvents = 'auto';
                            });
                        }, 50);
                    });
                },
                images_upload_url: '{{ route("upload-image") }}',
                automatic_uploads: true,
                images_upload_handler: function (blobInfo, success, failure) {
                    var xhr, formData;
                    xhr = new XMLHttpRequest();
                    xhr.withCredentials = false;
                    xhr.open('POST', '{{ route("upload-image") }}');
                    xhr.setRequestHeader('X-CSRF-TOKEN', '{{ csrf_token() }}');
                    xhr.onload = function() {
                        var json;
                        if (xhr.status != 200) { failure('HTTP Error: ' + xhr.status); return; }
                        json = JSON.parse(xhr.responseText);
                        if (!json || typeof json.location != 'string') { failure('Invalid JSON: ' + xhr.responseText); return; }
                        success(json.location);
                    };
                    formData = new FormData();
                    formData.append('file', blobInfo.blob(), blobInfo.filename());
                    xhr.send(formData);
                }
            });
        }

        // --- 2. MODAL OPEN LOGIC (Add vs Edit) ---
        const showModal = document.getElementById('showModal');
        const pageForm = document.getElementById('pageForm');
        const modalTitle = document.getElementById('exampleModalLabel');
        const submitBtn = document.getElementById('submit-btn');
        const formMethod = document.getElementById('form-method');
        const pageIdInput = document.getElementById('page-id');

        showModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            const isEdit = button && button.classList.contains('edit-item-btn');

            // Initialize Editors when modal opens
            setTimeout(initEditors, 200);

            if (isEdit) {
                // EDIT MODE
                modalTitle.textContent = 'Edit Page';
                submitBtn.textContent = 'Update Page';
                
                const id = button.getAttribute('data-id');
                const showInMenu = button.getAttribute('data-show_in_menu') === '1';
                const showInFooter = button.getAttribute('data-show_in_footer') === '1';
                
                // Parse Translations
                const shortTitles = JSON.parse(button.getAttribute('data-short_title-translations') || '{}');
                const titles = JSON.parse(button.getAttribute('data-title-translations') || '{}');
                const descriptions = JSON.parse(button.getAttribute('data-description-translations') || '{}');

                pageIdInput.value = id;
                pageForm.action = "{{ route('websitepages.update', ':id') }}".replace(':id', id);
                formMethod.value = "PUT";
                document.getElementById('show_in_menu-field').checked = showInMenu;
                document.getElementById('show_in_footer-field').checked = showInFooter;

                // Fill Fields & Editors
                langCodes.forEach(lang => {
                    document.getElementById(`short_title-field-${lang}`).value = shortTitles[lang] || '';
                    document.getElementById(`title-field-${lang}`).value = titles[lang] || '';
                    // We set content directly to textarea AND wait for editor to load to set content there
                    const content = descriptions[lang] || '';
                    document.getElementById(`description-field-${lang}`).value = content;
                    
                    // If editor is already active (re-opening modal), set content
                    if(tinymce.get(`description-field-${lang}`)) {
                        tinymce.get(`description-field-${lang}`).setContent(content);
                    }
                });

            } else {
                // ADD MODE
                modalTitle.textContent = 'Add Page';
                submitBtn.textContent = 'Add Page';
                pageForm.action = "{{ route('websitepages.store') }}";
                formMethod.value = "POST";
                pageIdInput.value = '';
                pageForm.reset();
                
                // Clear editors
                langCodes.forEach(lang => {
                    if(tinymce.get(`description-field-${lang}`)) {
                        tinymce.get(`description-field-${lang}`).setContent('');
                    }
                });
            }
        });

        // --- 3. FORM SUBMIT FIX ---
        // Manually handle form submit to ensure TinyMCE saves data
        pageForm.addEventListener('submit', function(e) {
            // Trigger save on all editors
            tinymce.triggerSave();
            
            // Allow default submission to proceed
            // Validation errors will be handled by Laravel redirect back
        });

        // Delete Modal Logic
        document.getElementById('deleteRecordModal').addEventListener('show.bs.modal', function(event) {
            var button = event.relatedTarget;
            var id = button.getAttribute('data-id');
            var action = "{{ route('websitepages.destroy', ':id') }}".replace(':id', id);
            document.getElementById('delete-form').setAttribute('action', action);
        });

        // SweetAlerts
        @if(session('success'))
            Swal.fire({ icon: 'success', title: 'Success', text: '{{ session('success') }}', timer: 3000, showConfirmButton: false });
        @endif
        @if(session('error'))
            Swal.fire({ icon: 'error', title: 'Error', text: '{{ session('error') }}', timer: 3000, showConfirmButton: false });
        @endif
    });
</script>
@endsection

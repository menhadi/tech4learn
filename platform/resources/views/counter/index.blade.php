@extends('layouts.master')
@section('title', 'Counter')

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
@slot('title', 'Counter')
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
                <h4 class="card-title mb-0">Add, Edit & Remove Counter</h4>
            </div>
            
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
                                    <th class="sort" data-sort="title">Title</th>
                                    <th class="sort" data-sort="number">Number</th>
                                    <th class="sort" data-sort="updated_at">Updated At</th>
                                    <th class="sort" data-sort="action">Action</th>
                                </tr>
                            </thead>
                            <tbody class="list form-check-all">
                                @foreach ($data as $val)
                                <tr>
                                    <td class="image_url">
                                        @if($val->image_url)
                                            <img src="{{ asset($val->image_url) }}" alt="Image" style="max-height: 60px;">
                                        @else
                                            N/A
                                        @endif
                                    </td>
                                    {{-- Model update hone ke baad, ye automatic current language ka title dikhayega --}}
                                    <td class="title">{{ $val->title }}</td>
                                    <td class="number">{{ $val->number }}</td>
                                    <td class="updated_at">@formatDate($val->updated_at)</td>
                                    <td>
                                        <div class="d-flex gap-2">
                                            <div class="edit">
                                                {{-- YAHAN CHANGE KIYA HAI: 'data-title' ko JSON data se update kiya --}}
                                                <button class="btn btn-sm btn-success edit-item-btn"
                                                    data-bs-toggle="modal" data-bs-target="#showModal"
                                                    data-id="{{ $val->id }}"
                                                    data-image_url="{{ $val->image_url }}"
                                                    data-icon="{{ $val->icon ?? '' }}"
                                                    data-title-translations="{{ json_encode($val->getTranslations('title')) }}"
                                                    data-number="{{ $val->number }}">Edit</button>
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
            <form class="tablelist-form" autocomplete="off" method="POST" action="{{ route('counters.store') }}" enctype="multipart/form-data">
                @csrf
                <div class="modal-body">
                    
                    <div class="mb-3">
                        <label for="icon-field" class="form-label">Icon</label>
                        <select id="icon-field" name="icon" class="form-select">
                            <option value="">Auto icon</option>
<option value="ri-shield-check-line">Shield / Secure</option>
<option value="ri-smartphone-line">Mobile</option>
<option value="ri-bar-chart-box-line">Analytics</option>
<option value="ri-file-list-3-line">Tests / Papers</option>
<option value="ri-question-answer-line">Questions</option>
<option value="ri-timer-flash-line">Speed / Timer</option>
<option value="ri-award-line">Achievement</option>
<option value="ri-graduation-cap-line">Education</option>
<option value="ri-line-chart-line">Growth</option>
<option value="ri-lock-password-line">Privacy</option>
                        </select>
                        <div class="form-text">Recommended. If no image is uploaded, this icon will be used on homepage.</div>
                    </div>

                    <div class="mb-3">
                        <label for="image-field" class="form-label">Image</label>
                        <input type="file" id="image-field" name="image_url" class="form-control" accept="image/*" />
                        <div class="invalid-feedback">Please upload an image.</div>

                        <div id="image-preview" class="mt-2" style="display: none;">
                            <label>Current Image / Legacy Image:</label><br>
                            <img src="" id="preview-img" alt="Current Image" style="max-height: 100px;">
                        </div>
                    </div>

                    {{-- YAHAN CHANGE KIYA HAI: 'Title' ke liye Language tabs add kiye hain --}}
                    <ul class="nav nav-tabs" id="counterTabs" role="tablist">
                        @foreach($languages as $langCode => $langName)
                            <li class="nav-item" role="presentation">
                                <button class="nav-link {{ $loop->first ? 'active' : '' }}" id="counter-tab-{{ $langCode }}" data-bs-toggle="tab" data-bs-target="#counter-content-{{ $langCode }}" type="button" role="tab" aria-controls="counter-content-{{ $langCode }}" aria-selected="{{ $loop->first ? 'true' : 'false' }}">{{ $langName }}</button>
                            </li>
                        @endforeach
                    </ul>

                    <div class="tab-content" id="counterTabsContent">
                        @foreach($languages as $langCode => $langName)
                            <div class="tab-pane fade {{ $loop->first ? 'show active' : '' }}" id="counter-content-{{ $langCode }}" role="tabpanel" aria-labelledby="counter-tab-{{ $langCode }}">
                                <div class="mb-3">
                                    <label for="title-field-{{ $langCode }}" class="form-label">Title ({{ strtoupper($langCode) }})</label>
                                    <input type="text" id="title-field-{{ $langCode }}" name="title[{{ $langCode }}]" class="form-control"
                                        placeholder="Enter Counter Title in {{ $langName }}" {{ $langCode == 'en' ? 'required' : '' }} />
                                    <div class="invalid-feedback">Please enter a Title.</div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                    
                    {{-- 'Number' field translatable nahi hai, isliye ye tabs ke bahar hai --}}
                    <div class="mb-3">
                        <label for="number-field" class="form-label">Number</label>
                        <input type="number" id="number-field" name="number" class="form-control"
                            placeholder="Enter Counter number" required />
                        <div class="invalid-feedback">Please enter a number.</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <div class="hstack gap-2 justify-content-end">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-success" id="add-btn">Add Counter</button>
                        <button type="submit" class="btn btn-success" id="edit-btn" style="display: none;">Update
                            Counter</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade zoomIn" id="deleteRecordModal" tabindex="-1" aria-hidden="true">
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
            var icon = button.getAttribute('data-icon') || '';
            
            // YAHAN CHANGE KIYA HAI: JSON data read kar rahe hain
            var titleTranslations = JSON.parse(button.getAttribute('data-title-translations') || '{}');
            var number = button.getAttribute('data-number'); // Number field alag se
            
            var modal = this;
            modal.querySelector('#icon-field').value = icon;

            // Reset image preview
            modal.querySelector('#image-preview').style.display = 'none';
            modal.querySelector('#preview-img').src = '';

            // Clear all old language fields
            langCodes.forEach(function(lang) {
                modal.querySelector(`#title-field-${lang}`).value = '';
            });

            if (id) { // Edit Mode
                modal.querySelector('.modal-title').textContent = 'Edit Counter';
                modal.querySelector('#add-btn').style.display = 'none';
                modal.querySelector('#edit-btn').style.display = 'block';

                // Populate all language fields
                langCodes.forEach(function(lang) {
                    modal.querySelector(`#title-field-${lang}`).value = titleTranslations[lang] || '';
                });

                // Number field (non-translatable)
                modal.querySelector('#number-field').value = number;

                // Show current image
                if (image) {
                    modal.querySelector('#image-preview').style.display = 'block';
                    modal.querySelector('#preview-img').src = '/' + image;
                }

                // Update action URL and method
                modal.querySelector('form').setAttribute('action', '{{ route("counters.update", ":id") }}'.replace(':id', id));
                if (!modal.querySelector('input[name="_method"]')) {
                    modal.querySelector('form').insertAdjacentHTML('beforeend', '<input type="hidden" name="_method" value="PUT">');
                }
            } else { // Add Mode
                modal.querySelector('.modal-title').textContent = 'Add Counter';
                modal.querySelector('#add-btn').style.display = 'block';
                modal.querySelector('#edit-btn').style.display = 'none';

                // Clear all language fields
                langCodes.forEach(function(lang) {
                    modal.querySelector(`#title-field-${lang}`).value = '';
                });
                // Number field (non-translatable)
                modal.querySelector('#number-field').value = '';

                modal.querySelector('form').setAttribute('action', '{{ route("counters.store") }}');
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
            var action = "{{ route('counters.destroy', ':id') }}";
            action = action.replace(':id', id);
            document.getElementById('delete-form').setAttribute('action', action);
        });

        // ... (sweetalert wala code waise hi) ...
    });
</script>
@endsection
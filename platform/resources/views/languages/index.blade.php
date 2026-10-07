@extends('layouts.master')
@section('title', 'Languages')

@section('css')
<link href="{{ URL::asset('build/libs/sweetalert2/sweetalert2.min.css') }}" rel="stylesheet" type="text/css" />
@endsection

@section('content')
@component('components.breadcrumb')
@slot('li_1', '')
@slot('title', 'Languages')
@endcomponent

<div class="row">
    <div class="col-lg-12">
        <div class="card">
            <div class="card-header">
                <h4 class="card-title mb-0">Add, Edit & Remove Languages</h4>
            </div>
            <div class="two-column-menu">
                <p></p>
            </div>
            <div class="card-body">
                <div class="listjs-table" id="languageList">
                    <div class="row g-4 mb-3">
                        <div class="col-sm-auto">
                            <div>
                                <button type="button" class="btn btn-success add-btn" data-bs-toggle="modal"
                                    id="create-btn" data-bs-target="#showModal"><i
                                        class="ri-add-line align-bottom me-1"></i> {{ $isPlatformLanguageAdmin ? 'Add' : 'Enable Language' }}</button>
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
                        <table class="table align-middle table-nowrap" id="languageTable">
                            <thead class="table-light">
                                <tr>
                                    <th class="sort" data-sort="name">Name</th>
                                    <th class="sort" data-sort="code">Code</th>
                                    <th class="sort" data-sort="action">Action</th>
                                </tr>
                            </thead>
                            <tbody class="list form-check-all">
                                @foreach ($languages as $language)
                                <tr>
                                    <td class="name">{{ $language->name }}</td>
                                    <td class="code">{{ $language->code }}</td>
                                    <td>
                                        <div class="d-flex gap-2">
                                            @if($isPlatformLanguageAdmin)
                                            <div class="edit">
                                                <button class="btn btn-sm btn-success edit-item-btn"
                                                    data-bs-toggle="modal" data-bs-target="#showModal"
                                                    data-id="{{ $language->id }}" data-name="{{ $language->name }}" data-code="{{ $language->code }}"
                                                    data-value1="{{ $language->value1 }}"
                                                    data-value2="{{ $language->value2 }}">Edit</button>
                                            </div>
                                            @endif
                                            <div class="remove">
                                                <button class="btn btn-sm btn-danger remove-item-btn"
                                                    data-bs-toggle="modal" data-bs-target="#deleteRecordModal"
                                                    data-id="{{ $language->id }}">{{ $isPlatformLanguageAdmin ? 'Remove' : 'Disable' }}</button>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                        <div class="noresult" style="display: none">
                            <div class="text-center">
                                <lord-icon src="https://cdn.lordicon.com/msoeawqm.json" trigger="loop"
                                    colors="primary:#121331,secondary:#08a88a" style="width:75px;height:75px">
                                </lord-icon>
                                <h5 class="mt-2">Sorry! No Result Found</h5>
                                <p class="text-muted mb-0">We've searched more than 150+ Orders We did not find any
                                    orders for you search.</p>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-end">
                        {{ $languages->links('vendor.pagination.custom') }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add/Edit Modal -->
<div class="modal fade" id="showModal" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-light p-3">
                <h5 class="modal-title" id="exampleModalLabel"></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"
                    id="close-modal"></button>
            </div>
            <form class="tablelist-form" autocomplete="off" method="POST" action="{{ route('languages.store') }}">
                @csrf
                <div class="modal-body">
                    @if(!$isPlatformLanguageAdmin)
                    <div class="mb-3" id="master-language-wrapper">
                        <label for="master-language-field" class="form-label">Select Language</label>
                        <select id="master-language-field" name="master_language_id" class="form-select">
                            <option value="">Select language</option>
                            @foreach($platformLanguages as $platformLanguage)
                                <option value="{{ $platformLanguage->id }}">{{ $platformLanguage->name }} ({{ $platformLanguage->code }})</option>
                            @endforeach
                        </select>
                        @if($platformLanguages->isEmpty())
                            <div class="form-text">All available platform languages are already enabled.</div>
                        @endif
                    </div>
                    @endif
                    <div class="mb-3" id="name-wrapper">
                        <label for="name-field" class="form-label">Name</label>
                        <input type="text" id="name-field" name="name" class="form-control" placeholder="Enter Name"
                            {{ $isPlatformLanguageAdmin ? 'required' : 'readonly' }} />
                        <div class="invalid-feedback">Please enter a name.</div>
                    </div>
                    <div class="mb-3" id="code-wrapper">
                        <label for="code-field" class="form-label">Language Code</label>
                        <input type="text" id="code-field" name="code" class="form-control" placeholder="en, hi, bn" {{ $isPlatformLanguageAdmin ? 'required' : 'readonly' }} />
                    </div>
                    <div class="mb-3">
                        <label for="value1-field" class="form-label">True label</label>
                        <input type="text" id="value1-field" name="value1" class="form-control"
                            placeholder="True label, e.g. True / सही" />
                    </div>
                    <div class="mb-3">
                        <label for="value2-field" class="form-label">False label</label>
                        <input type="text" id="value2-field" name="value2" class="form-control"
                            placeholder="False label, e.g. False / गलत" />
                    </div>
                </div>
                <div class="modal-footer">
                    <div class="hstack gap-2 justify-content-end">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-success" id="add-btn">Add Language</button>
                        <button type="submit" class="btn btn-success" id="edit-btn" style="display: none;">Update
                            Language</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Modal -->
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
                        <p class="text-muted mx-4 mb-0">Are you Sure You want to {{ $isPlatformLanguageAdmin ? 'Remove' : 'Disable' }} this Record ?</p>
                    </div>
                </div>
                <div class="d-flex gap-2 justify-content-center mt-4 mb-2">
                    <button type="button" class="btn w-sm btn-light" data-bs-dismiss="modal">Close</button>
                    <form id="delete-form" method="POST" action="">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn w-sm btn-danger">Yes, {{ $isPlatformLanguageAdmin ? 'Delete' : 'Disable' }} It!</button>
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
        const isPlatformLanguageAdmin = @json($isPlatformLanguageAdmin);
        let debounceTimeout;
        const searchInput = document.getElementById('search-input');

        searchInput.addEventListener('input', function() {
            clearTimeout(debounceTimeout);
            debounceTimeout = setTimeout(() => {
                const searchQuery = searchInput.value;
                fetchLanguages(searchQuery);
            }, 300);
        });

        function fetchLanguages(query) {
            const url = new URL(window.location.href);
            url.searchParams.set('search', query);

            fetch(url)
                .then(response => response.text())
                .then(html => {
                    const parser = new DOMParser();
                    const doc = parser.parseFromString(html, 'text/html');
                    const newTableBody = doc.querySelector('#languageTable tbody');
                    document.querySelector('#languageTable tbody').innerHTML = newTableBody.innerHTML;
                })
                .catch(error => console.error('Error fetching languages:', error));
        }

        // Add/Edit Modal
        document.getElementById('showModal').addEventListener('show.bs.modal', function(event) {
            var button = event.relatedTarget;
            var id = button.getAttribute('data-id');
            var name = button.getAttribute('data-name');
            var code = button.getAttribute('data-code');
            var value1 = button.getAttribute('data-value1');
            var value2 = button.getAttribute('data-value2');
            var modal = this;
            var masterWrapper = modal.querySelector('#master-language-wrapper');
            var masterField = modal.querySelector('#master-language-field');
            var nameWrapper = modal.querySelector('#name-wrapper');
            var codeWrapper = modal.querySelector('#code-wrapper');
            var value1Wrapper = modal.querySelector('#value1-field').closest('.mb-3');
            var value2Wrapper = modal.querySelector('#value2-field').closest('.mb-3');

            if (id) {
                modal.querySelector('.modal-title').textContent = 'Edit Language';
                if (masterWrapper) {
                    masterWrapper.style.display = 'none';
                    masterField.removeAttribute('required');
                    masterField.value = '';
                }
                nameWrapper.style.display = 'block';
                codeWrapper.style.display = 'block';
                value1Wrapper.style.display = 'block';
                value2Wrapper.style.display = 'block';
                modal.querySelector('#name-field').value = name;
                modal.querySelector('#code-field').value = code;
                modal.querySelector('#value1-field').value = value1;
                modal.querySelector('#value2-field').value = value2;
                modal.querySelector('#add-btn').style.display = 'none';
                modal.querySelector('#edit-btn').style.display = 'block';

                // Set form action and method for update
                modal.querySelector('form').setAttribute('action', '{{ route("languages.update", ":id") }}'.replace(':id', id));
                var existingMethodInput = modal.querySelector('input[name="_method"]');
                if (existingMethodInput) {
                    existingMethodInput.remove();
                }
                modal.querySelector('form').insertAdjacentHTML('beforeend', '<input type="hidden" name="_method" value="PUT">');
            } else {
                modal.querySelector('.modal-title').textContent = isPlatformLanguageAdmin ? 'Add Language' : 'Enable Language';
                if (masterWrapper) {
                    masterWrapper.style.display = 'block';
                    masterField.setAttribute('required', 'required');
                    masterField.value = '';
                }
                nameWrapper.style.display = isPlatformLanguageAdmin ? 'block' : 'none';
                codeWrapper.style.display = isPlatformLanguageAdmin ? 'block' : 'none';
                value1Wrapper.style.display = isPlatformLanguageAdmin ? 'block' : 'none';
                value2Wrapper.style.display = isPlatformLanguageAdmin ? 'block' : 'none';
                modal.querySelector('#name-field').value = '';
                modal.querySelector('#code-field').value = '';
                modal.querySelector('#value1-field').value = '';
                modal.querySelector('#value2-field').value = '';
                modal.querySelector('#add-btn').style.display = 'block';
                modal.querySelector('#edit-btn').style.display = 'none';

                // Reset form action and method for create
                modal.querySelector('form').setAttribute('action', '{{ route("languages.store") }}');
                var methodInput = modal.querySelector('input[name="_method"]');
                if (methodInput) {
                    methodInput.remove();
                }
            }
        });

        // Delete Modal
        document.getElementById('deleteRecordModal').addEventListener('show.bs.modal', function(event) {
            var button = event.relatedTarget;
            var id = button.getAttribute('data-id');
            var action = "{{ route('languages.destroy', ':id') }}";
            action = action.replace(':id', id);
            document.getElementById('delete-form').setAttribute('action', action);
        });

        // Display success or error messages
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

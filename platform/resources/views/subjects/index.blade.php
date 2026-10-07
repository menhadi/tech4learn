@extends('layouts.master')
@section('title', 'Subjects')

@section('content')
@component('components.breadcrumb')
@slot('li_1', '')
@slot('title', 'Subjects')
@endcomponent

<div class="row">
    <div class="col-lg-12">
        <div class="card">
            <div class="card-header">
                <h4 class="card-title mb-0">Add, Edit & Remove Subjects</h4>
                <div class="el-filter-bar mt-3">
                    <div class="el-filter-field">
                        <select id="groupFilter" class="form-control select2">
                            <option value="">All Groups</option>
                            @foreach($groups as $examGroup)
                                <option
                                    value="{{ $examGroup['id'] }}"
                                    {{ request()->get('group') == $examGroup['id'] ? 'selected' : '' }}
                                >
                                    {{ $examGroup['group_name'] }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="el-filter-field el-filter-action-field">
                        <button id="search-btn" class="btn el-btn-primary">Search</button>
                    </div>
                    <div class="el-filter-field el-filter-action-field">
                        <button id="reset-btn" class="btn el-btn-secondary">Reset</button>
                    </div>
                </div>
            </div>

            <div class="card-body">
                <div id="subjectListContainer"> {{-- ID aur Class yahan se hata diye gaye the pichle code me, aage bhi hataye rakhein --}}
                    <div class="el-table-toolbar">
                        <div class="el-table-toolbar-actions">
                            <button type="button" class="btn el-btn-primary add-btn" data-bs-toggle="modal" id="create-btn" data-bs-target="#showModal">
                                <i class="ri-add-line align-bottom me-1"></i> Add
                            </button>
                            <a href="{{ route('admin.bulk-editor.index', 'subjects') }}" class="btn el-btn-secondary"><i class="ri-edit-box-line align-bottom me-1"></i> Bulk Edit</a>
                            <x-google-sheets-button resource="subjects" :filters="request()->query()" />
                        </div>
                        <div class="el-table-toolbar-controls">
                            <div class="el-filter-search">
                                <div class="search-box">
                                    <input type="text" id="search-input" class="form-control search"
                                        placeholder="Search..." value="{{ request('search') }}">
                                    <i class="ri-search-line search-icon"></i>
                                </div>
                            </div>
                            <div class="el-page-size">
                                <select id="per-page-select" class="form-select">
                                    <option value="50" {{ $perPage == 50 ? 'selected' : '' }}>50 per page</option>
                                    <option value="100" {{ $perPage == 100 ? 'selected' : '' }}>100 per page</option>
                                    <option value="500" {{ $perPage == 500 ? 'selected' : '' }}>500 per page</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="el-result-count">Showing {{ $subjects->count() }} of {{ $subjects->total() }}</div>
                    <div class="table-responsive table-card mt-3 mb-1">
                        <table class="table align-middle table-nowrap" id="subjectTable">
                            <thead class="table-light">
                                <tr>
                                    <th class="sort" data-sort="subject_name">Subject Name</th>
                                    <th class="sort" data-sort="groups">Groups</th>
                                    <th class="sort" data-sort="action">Action</th>
                                </tr>
                            </thead>
                            <tbody class="list">
                                @forelse($subjects as $subject)
                                <tr>
                                    <td class="subject_name">{{ $subject->subject_name }}</td>
                                    <td class="groups">
                                        @foreach ($subject->groups as $group)
                                        {{ $group->group_name }}{{ !$loop->last ? ' | ' : '' }}
                                        @endforeach
                                    </td>
                                    <td>
                                        <div class="d-flex gap-2">
                                            <div class="edit">
                                                <button class="btn btn-sm el-btn-primary edit-item-btn"
                                                    data-bs-toggle="modal" data-bs-target="#showModal"
                                                    data-id="{{ $subject->id }}"
                                                    data-subject_name="{{ $subject->subject_name }}"
                                                    data-ordering="{{ $subject->ordering }}"
                                                    data-group_ids="{{ $subject->groups->pluck('id')->implode(',') }}">Edit</button>
                                            </div>
                                            <div class="remove">
                                                <button class="btn btn-sm el-btn-danger remove-item-btn"
                                                    data-bs-toggle="modal" data-bs-target="#deleteRecordModal"
                                                    data-id="{{ $subject->id }}">Remove</button>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="3" class="text-center">No subjects found.</td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <div class="d-flex justify-content-end mt-3">
                        {{-- ======================================================= --}}
                        {{-- FINAL FIX YAHAN HAI: Humne Bootstrap 5 pagination view specify kiya hai --}}
                        {{-- ======================================================= --}}
                        {{ $subjects->appends(request()->query())->links('pagination::bootstrap-5') }}
                    </div>
                    
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="showModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add Subject</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="subject-form" method="POST" action="{{ route('subjects.store') }}">
                    @csrf
                    <div class="mb-3">
                        <label for="subject_name-field" class="form-label">Subject Name</label>
                        <input type="text" id="subject_name-field" name="subject_name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label for="group_ids-field" class="form-label">Groups</label>
                        <select id="group_ids-field" name="group_ids[]" class="form-control" multiple required>
                            @foreach($groups as $group)
                            <option value="{{ $group->id }}">{{ $group->group_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
                        <button type="submit" class="btn el-btn-primary">Save</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="deleteRecordModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mt-2 text-center">
                    <lord-icon src="https://cdn.lordicon.com/gsqxdxog.json" trigger="loop"
                        colors="primary:#f7b84b,secondary:#f06548" style="width:100px;height:100px"></lord-icon>
                    <div class="mt-4 pt-2 fs-15 mx-4 mx-sm-5">
                        <h4>Are you Sure ?</h4>
                        <p class="text-muted mx-4 mb-0">Do you really want to remove this subject? Its topics and subtopics will also be removed. Questions, exam papers, attempts and results will be preserved, but their academic classification will be cleared.</p>
                    </div>
                </div>
                <div class="d-flex gap-2 justify-content-center mt-4 mb-2">
                    <button type="button" class="btn w-sm btn-light" data-bs-dismiss="modal">Close</button>
                    <form id="delete-form" method="POST" action="">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn w-sm el-btn-danger">Yes, Delete It!</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>


{{-- Yeh CSS ab zaroori nahi hai, but aap ise rakh sakte hain, isse koi nuksan nahi hoga. --}}
<style>
    .pagination .page-link svg {
        display: none !important;
    }
    .pagination li:first-child .page-link::before {
        content: '«';
        font-weight: 600;
    }
    .pagination li:last-child .page-link::before {
        content: '»';
        font-weight: 600;
    }
</style>


@endsection

@section('script')
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="{{ URL::asset('build/libs/sweetalert2/sweetalert2.min.js') }}"></script>

<script>
    document.addEventListener('DOMContentLoaded', function() {

        $('.select2').select2({ width: '100%' });

        let filterTimer;

        function showFilterWorking() {
            $('.el-filter-bar').addClass('el-filter-working');
        }

        function applyFilters() {
            showFilterWorking();
            const group = $('#groupFilter').val();
            const search = $('#search-input').val();
            const perPage = $('#per-page-select').val();

            const url = new URL(window.location.href.split('?')[0]); // Base URL without old params
            if (group) url.searchParams.set('group', group);
            if (search) url.searchParams.set('search', search);
            if (perPage) url.searchParams.set('per_page', perPage);
            
            if (window.ExamLiteAjaxFilter) {
                window.ExamLiteAjaxFilter.loadUrl(url, ['#subjectListContainer'], document.querySelector('#subjectListContainer'));
            } else {
                window.location.href = url.toString();
            }
        }

        function scheduleFilters(delay = 450) {
            clearTimeout(filterTimer);
            filterTimer = setTimeout(applyFilters, delay);
        }

        // Get query params
        const urlParams = new URLSearchParams(window.location.search);
        const selectedGroup = urlParams.get('group');
        const selectedSearch = urlParams.get('search');

        // Search button click
        $(document).on('click', '#search-btn', function() {
            applyFilters();
        });

        $('#groupFilter').on('change', function() {
            scheduleFilters(120);
        });

        $("#filterBtn").click(function(event) {
            applyFilters();
        });

        // Search on Enter key press
        $(document).on('keyup', '#search-input', function(e) {
            if (e.key === 'Enter') {
                applyFilters();
            }
        });

        $(document).on('input', '#search-input', function() {
            scheduleFilters(650);
        });
        
        // Per-page select change
        $(document).on('change', '#per-page-select', function() {
            applyFilters();
        });

        // Reset button click
        $(document).on('click', '#reset-btn', function() {
            const url = new URL(window.location.href.split('?')[0]);
            if (window.ExamLiteAjaxFilter) {
                window.ExamLiteAjaxFilter.loadUrl(url, ['#subjectListContainer'], document.querySelector('#subjectListContainer'));
            } else {
                window.location.href = url.toString();
            }
        });

        // Initialize Select2
        $(document).ready(function() {
            $('#group_ids-field').select2({
                placeholder: "Select Groups",
                allowClear: true,
                closeOnSelect: false,
                dropdownParent: $('#showModal')
            });
        });

        // Add/Edit Modal Logic
        var showModal = document.getElementById('showModal');
        if (showModal) {
            showModal.addEventListener('show.bs.modal', function(event) {
                var button = event.relatedTarget;
                var id = button.getAttribute('data-id');
                var subject_name = button.getAttribute('data-subject_name');
                var modal = this;
                var form = modal.querySelector('#subject-form');
                var methodInput = form.querySelector('input[name="_method"]');

                if (methodInput) {
                    methodInput.remove();
                }

                if (id) { // This is for Edit
                    modal.querySelector('.modal-title').textContent = 'Edit Subject';
                    form.querySelector('#subject_name-field').value = subject_name;
                    form.setAttribute('action', '{{ url("subjects") }}/' + id);
                    form.insertAdjacentHTML('beforeend', '<input type="hidden" name="_method" value="PUT">');

                    var group_ids = button.getAttribute('data-group_ids') ? button.getAttribute('data-group_ids').split(',') : [];
                    $('#group_ids-field').val(group_ids).trigger('change');
                } else { // This is for Add
                    modal.querySelector('.modal-title').textContent = 'Add Subject';
                    form.reset();
                    form.setAttribute('action', '{{ route("subjects.store") }}');
                    $('#group_ids-field').val(null).trigger('change');
                }
            });
        }

        // Delete Modal Logic
        var deleteModal = document.getElementById('deleteRecordModal');
        if(deleteModal) {
            deleteModal.addEventListener('show.bs.modal', function(event) {
                var button = event.relatedTarget;
                var id = button.getAttribute('data-id');
                var form = document.getElementById('delete-form');
                var action = "{{ route('subjects.destroy', ':id') }}";
                action = action.replace(':id', id);
                form.setAttribute('action', action);
            });
        }

        // Display success or error messages using SweetAlert2
        @if(session('success'))
        Swal.fire({
            icon: 'success',
            title: 'Success',
            text: '{{ session('success') }}',
            timer: 2000,
            showConfirmButton: false
        });
        @endif

        @if(session('error'))
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: '{{ session('error') }}',
            timer: 3000,
            showConfirmButton: false
        });
        @endif

        @if($errors->any())
        var errorText = '<ul class="text-start">';
        @foreach($errors->all() as $error)
        errorText += '<li>{{ $error }}</li>';
        @endforeach
        errorText += '</ul>';

        Swal.fire({
            icon: 'error',
            title: 'Validation Error',
            html: errorText,
            showConfirmButton: true
        });
        @endif
    });
</script>
@endsection

@extends('layouts.master')
@section('title', 'Topics')

@section('content')
@component('components.breadcrumb')
@slot('li_1', '')
@slot('title', 'Topics')
@endcomponent

<div class="row">
    <div class="col-lg-12">
        <div class="card">
            <div class="card-header">
                <h4 class="card-title mb-0">Add, Edit & Remove Topics</h4>
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
                    <div class="el-filter-field">
                        <select id="subjectFilter" class="form-control select2">
                            <option value="">All Subjects</option>
                            @foreach($subjects as $subject)
                                <option
                                    value="{{ $subject['id'] }}"
                                    {{ request()->get('subject') == $subject['id'] ? 'selected' : '' }}
                                >
                                    {{ $subject['subject_name'] }}
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
                <div class="listjs-table" id="topicList">
                    <div class="el-table-toolbar">
                        <div class="el-table-toolbar-actions">
                            <button type="button" class="btn el-btn-primary add-btn" data-bs-toggle="modal" id="create-btn" data-bs-target="#showModal">
                                <i class="ri-add-line align-bottom me-1"></i> Add
                            </button>
                            <button type="button" class="btn el-btn-danger" id="bulk-delete-topics" disabled>
                                <i class="ri-delete-bin-line align-bottom me-1"></i> Bulk Delete
                            </button>
                            <a href="{{ route('admin.bulk-editor.index', 'topics') }}" class="btn el-btn-secondary">
                                <i class="ri-edit-box-line align-bottom me-1"></i> Bulk Edit
                            </a>
                            <x-google-sheets-button resource="topics" :filters="request()->query()" />
                            <form id="bulk-delete-form" method="POST" action="{{ route('topics.bulk-destroy') }}" class="d-none">@csrf</form>
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
                    <div class="el-result-count">Showing {{ $topics->count() }} of {{ $topics->total() }}</div>
                    <div class="table-responsive table-card mt-3 mb-1">
                        <table class="table align-middle table-nowrap" id="topicTable">
                            <thead class="table-light">
                                <tr>
                                    <th style="width:44px"><input class="form-check-input" type="checkbox" id="select-all-topics" aria-label="Select all topics on this page"></th>
                                    <th class="sort" data-sort="group_name">Group</th>
                                    <th class="sort" data-sort="subject_name">Subject Name</th>
                                    <th class="sort" data-sort="topic_name">Topic Name</th>
                                    <th class="sort" data-sort="action">Action</th>
                                </tr>
                            </thead>
                            <tbody class="list">
                                @forelse($topics as $topic)
                                <tr>
                                    <td><input class="form-check-input topic-select" type="checkbox" value="{{ $topic->id }}" data-question-count="{{ $topic->questions_count }}" data-subtopic-count="{{ $topic->stopics_count }}" aria-label="Select {{ $topic->name }}"></td>
                                    <td class="group_name">{{ $topic->group?->group_name ?? 'Unassigned' }}</td>
                                    <td class="subject_name">{{ $topic->subject->subject_name }}</td>
                                    <td class="topic_name">{{ $topic->name }}</td>
                                    <td>
                                        <div class="d-flex gap-2">
                                            <div class="edit">
                                                <button class="btn btn-sm el-btn-primary edit-item-btn"
                                                    data-bs-toggle="modal" data-bs-target="#showModal"
                                                    data-id="{{ $topic->id }}"
                                                    data-group_id="{{ $topic->group_id }}"
                                                    data-subject_id="{{ $topic->subject_id }}"
                                                    data-name="{{ $topic->name }}" data-display-order="{{ $topic->display_order }}">Edit</button>
                                            </div>
                                            <div class="remove">
                                                <button class="btn btn-sm el-btn-danger remove-item-btn"
                                                    data-bs-toggle="modal" data-bs-target="#deleteRecordModal"
                                                    data-id="{{ $topic->id }}" data-name="{{ $topic->name }}"
                                                    data-question-count="{{ $topic->questions_count }}"
                                                    data-subtopic-count="{{ $topic->stopics_count }}">Remove</button>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="5" class="text-center">No topics found.</td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div class="d-flex justify-content-end">
                        {{-- $topics->links('vendor.pagination.custom') --}}
                        {{ $topics->appends(request()->query())->links('pagination::bootstrap-5') }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add/Edit Modal -->
<div class="modal fade" id="showModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add Topic</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="topic-form" method="POST" action="{{ route('topics.store') }}">
                    @csrf
                    <div class="mb-3">
                        <label for="group_id-field" class="form-label">Group / Exam Context</label>
                        <select id="group_id-field" name="group_id" class="form-control" required>
                            <option value="">Select group</option>
                            @foreach($groups as $examGroup)
                            <option value="{{ $examGroup->id }}">{{ $examGroup->group_name }}</option>
                            @endforeach
                        </select>
                        <div class="form-text">Topics are independent per group, even when the subject name is the same.</div>
                    </div>
                    <div class="mb-3">
                        <label for="subject_id-field" class="form-label">Subject</label>
                        <select id="subject_id-field" name="subject_id" class="form-control" required>
                            @foreach($subjects as $subject)
                            <option value="{{ $subject->id }}">{{ $subject->subject_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="name-field" class="form-label">Topic Name</label>
                        <input type="text" id="name-field" name="name" class="form-control" required><label class="form-label mt-3">Curriculum Order</label><input type="number" id="display-order-field" name="display_order" min="0" class="form-control" placeholder="Use name order">
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

<!-- Delete Modal -->
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
                        <p class="text-muted mx-4 mb-0" id="delete-impact-message">The topic and its subtopics will be deleted. Linked questions, exam papers and results will be kept.</p>
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
            const subject = $('#subjectFilter').val();
            const search = $('#search-input').val();
            const perPage = $('#per-page-select').val();

            const url = new URL(window.location.href.split('?')[0]); // Base URL without old params
            if (group) url.searchParams.set('group', group);
            if (subject) url.searchParams.set('subject', subject);
            if (search) url.searchParams.set('search', search);
            if (perPage) url.searchParams.set('per_page', perPage);
            
            if (window.ExamLiteAjaxFilter) {
                window.ExamLiteAjaxFilter.loadUrl(url, ['#topicList'], document.querySelector('#topicList'));
            } else {
                window.location.href = url.toString();
            }
        }

        const urlParams = new URLSearchParams(window.location.search);
        const selectedGroup = urlParams.get('group');
        const selectedSubject = urlParams.get('subject');
        const selectedSearch = urlParams.get('search');
        const allSubjects = @json($subjects);
        let bootstrappingFilters = Boolean(selectedGroup);

        function scheduleFilters(delay = 450) {
            if (bootstrappingFilters) {
                return;
            }

            clearTimeout(filterTimer);
            filterTimer = setTimeout(applyFilters, delay);
        }

        function fillSubjectOptions(subjects, selectedId = null) {
            const subjectDropdown = document.getElementById("subjectFilter");
            subjectDropdown.innerHTML = '<option value="">All Subjects</option>';

            subjects.forEach(subject => {
                const option = document.createElement("option");
                option.value = subject.id;
                option.textContent = subject.subject_name ?? 'Unnamed Subject';
                if (selectedId && selectedId == subject.id) {
                    option.selected = true;
                }
                subjectDropdown.appendChild(option);
            });

            $('#subjectFilter').trigger('change.select2');
        }

        // Search button click
        $(document).on('click', '#search-btn', function() {
            applyFilters();
        });

        $('#subjectFilter').on('change', function() {
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
                window.ExamLiteAjaxFilter.loadUrl(url, ['#topicList'], document.querySelector('#topicList'));
            } else {
                window.location.href = url.toString();
            }
        });

        $('#groupFilter').on('change', function () {

            const groupId = $(this).val();
            const subjectDropdown = document.getElementById("subjectFilter");

            subjectDropdown.innerHTML = '<option value="">All Subjects</option>';

            if (!groupId) {
                fillSubjectOptions(allSubjects, selectedSubject);
                scheduleFilters(120);
                return;
            }

            fetch(`{{ route('topics.getSubjectsByGroup') }}?group_id=${groupId}`)
                .then(response => response.json())
                .then(data => {

                    const subjects = data.subjects || data;

                    subjects.forEach(subject => {

                        const option = document.createElement("option");

                        option.value = subject.id;
                        option.textContent = subject.subject_name ?? 'Unnamed Subject';

                        if (selectedSubject && selectedSubject == subject.id) {
                            option.selected = true;
                        }

                        subjectDropdown.appendChild(option);
                    });

                    $('#subjectFilter').trigger('change.select2');
                    if (bootstrappingFilters) {
                        bootstrappingFilters = false;
                        return;
                    }
                    scheduleFilters(120);

                })
                .catch(error => console.error('Error:', error));
        });

        if (selectedGroup) {
            $('#groupFilter').val(selectedGroup).trigger('change');
        }

        $(document).ready(function() {
            $('#subject_id-field').select2({
                placeholder: "Select Subject",
                allowClear: true,
                closeOnSelect: true
            });
        });

        // Add/Edit Modal
        document.getElementById('showModal').addEventListener('show.bs.modal', function(event) {
            var button = event.relatedTarget;
            var id = button.getAttribute('data-id');
            var group_id = button.getAttribute('data-group_id');
            var subject_id = button.getAttribute('data-subject_id');
            var name = button.getAttribute('data-name');
            var modal = this;

            if (id) {
                modal.querySelector('.modal-title').textContent = 'Edit Topic';
                $('#group_id-field').val(group_id).trigger('change');
                $('#subject_id-field').val(subject_id).trigger('change');
                modal.querySelector('#name-field').value = name;
                modal.querySelector('#display-order-field').value = button.getAttribute('data-display-order') || '';
                modal.querySelector('form').setAttribute('action', '{{ route("topics.update", ":id") }}'.replace(':id', id));
                modal.querySelector('form').insertAdjacentHTML('beforeend', '<input type="hidden" name="_method" value="PUT">');
            } else {
                modal.querySelector('.modal-title').textContent = 'Add Topic';
                $('#group_id-field').val(null).trigger('change');
                $('#group_id-field').val(null).trigger('change');
            $('#subject_id-field').val(null).trigger('change');
                modal.querySelector('#name-field').value = '';
                modal.querySelector('#display-order-field').value = '';
                modal.querySelector('form').setAttribute('action', '{{ route("topics.store") }}');
                var methodInput = modal.querySelector('input[name="_method"]');
                if (methodInput) {
                    methodInput.remove();
                }
            }
        });

        // Clear modal on close
        document.getElementById('showModal').addEventListener('hidden.bs.modal', function() {
            var modal = this;
            $('#group_id-field').val(null).trigger('change');
            $('#subject_id-field').val(null).trigger('change');
            modal.querySelector('#name-field').value = '';
                modal.querySelector('#display-order-field').value = '';
            var methodInput = modal.querySelector('input[name="_method"]');
            if (methodInput) {
                methodInput.remove();
            }
        });

        // Delete Modal
        document.getElementById('deleteRecordModal').addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            const id = button.getAttribute('data-id');
            const name = button.getAttribute('data-name') || 'this topic';
            const questionCount = Number(button.getAttribute('data-question-count') || 0);
            const subtopicCount = Number(button.getAttribute('data-subtopic-count') || 0);
            const action = "{{ route('topics.destroy', ':id') }}".replace(':id', id);
            document.getElementById('delete-form').setAttribute('action', action);

            const impacts = [];
            if (questionCount) {
                impacts.push(questionCount + ' linked question(s) will be kept in their exam papers and results, but their topic/subtopic classification will be removed');
            }
            if (subtopicCount) {
                impacts.push(subtopicCount + ' subtopic(s) will also be deleted');
            }

            document.getElementById('delete-impact-message').textContent = impacts.length
                ? '"' + name + '": ' + impacts.join('; ') + '.'
                : '"' + name + '" is not linked to questions or subtopics and can be safely deleted.';
        });

        function selectedTopics() {
            return Array.from(document.querySelectorAll('.topic-select:checked'));
        }

        function refreshBulkDeleteState() {
            const selected = selectedTopics();
            document.getElementById('bulk-delete-topics').disabled = selected.length === 0;
            const all = Array.from(document.querySelectorAll('.topic-select'));
            const selectAll = document.getElementById('select-all-topics');
            if (selectAll) {
                selectAll.checked = all.length > 0 && selected.length === all.length;
                selectAll.indeterminate = selected.length > 0 && selected.length < all.length;
            }
        }

        $(document).on('change', '#select-all-topics', function () {
            document.querySelectorAll('.topic-select').forEach(checkbox => checkbox.checked = this.checked);
            refreshBulkDeleteState();
        });
        $(document).on('change', '.topic-select', refreshBulkDeleteState);
        $(document).on('click', '#bulk-delete-topics', function () {
            const selected = selectedTopics();
            const questionCount = selected.reduce((total, checkbox) => total + Number(checkbox.dataset.questionCount || 0), 0);
            const subtopicCount = selected.reduce((total, checkbox) => total + Number(checkbox.dataset.subtopicCount || 0), 0);

            Swal.fire({
                icon: 'warning',
                title: 'Delete ' + selected.length + ' topic(s)?',
                html: '<strong>' + questionCount + ' linked question(s)</strong> will be kept in exam papers and results, with their topic/subtopic classification removed.<br><strong>' + subtopicCount + ' subtopic(s)</strong> will be deleted.',
                showCancelButton: true,
                confirmButtonText: 'Yes, delete and unassign',
                cancelButtonText: 'Cancel'
            }).then(result => {
                if (!result.isConfirmed) return;
                const form = document.getElementById('bulk-delete-form');
                form.querySelectorAll('input[name="ids[]"]').forEach(input => input.remove());
                selected.forEach(checkbox => {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'ids[]';
                    input.value = checkbox.value;
                    form.appendChild(input);
                });

                Swal.fire({
                    title: 'Deleting selected topics...',
                    text: 'Questions, exam papers and results are being preserved. Please keep this page open.',
                    allowOutsideClick: false,
                    allowEscapeKey: false,
                    showConfirmButton: false,
                    didOpen: () => Swal.showLoading()
                });
                window.setTimeout(() => form.submit(), 50);
            });
        });

        // Display success or error messages
        @if(session('success'))
        Swal.fire({
            icon: 'success',
            title: 'Success',
            text: '{{ session('success') }}',
            timer: 3000,
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
        Swal.fire({
            icon: 'error',
            title: 'Validation Error',
            text: '{{ implode(", ", $errors->all()) }}',
            timer: 5000,
            showConfirmButton: true
        });
        @endif
    });
</script>
@endsection

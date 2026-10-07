@extends('layouts.master')
@section('title', 'Sub topics')

{{-- ✅ Select2 CSS Add kiya hai --}}
@section('css')
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
@endsection

@section('content')
@component('components.breadcrumb')
@slot('li_1', '')
@slot('title', 'Sub topics')
@endcomponent

<div class="row">
    <div class="col-lg-12">
        <div class="card">
            <!-- <div class="card-header">
                <h4 class="card-title mb-0">Add, Edit & Remove Sub topics</h4>
            </div> -->

            <div class="card-header">
                <h4 class="card-title mb-0">Add, Edit & Remove Sub topics</h4>
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
                    <div class="el-filter-field">
                        <select id="topicFilter" class="form-control select2">
                            <option value="">All Topics</option>
                            @foreach($topics as $topic)
                                <option
                                    value="{{ $topic['id'] }}"
                                    {{ request()->get('topic') == $topic['id'] ? 'selected' : '' }}
                                >
                                    {{ $topic['name'] }}
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
                <div class="listjs-table" id="stopicList">
                    <div class="el-table-toolbar">
                        <div class="el-table-toolbar-actions">
                            <button type="button" class="btn el-btn-primary add-btn" data-bs-toggle="modal" id="create-btn" data-bs-target="#showModal">
                                <i class="ri-add-line align-bottom me-1"></i> Add
                            </button>
                            <button type="button" class="btn el-btn-danger" id="bulk-delete-stopics" disabled>
                                <i class="ri-delete-bin-line align-bottom me-1"></i> Bulk Delete
                            </button>
                            <a href="{{ route('admin.bulk-editor.index', 'subtopics') }}" class="btn el-btn-secondary">
                                <i class="ri-edit-box-line align-bottom me-1"></i> Bulk Edit
                            </a>
                            <x-google-sheets-button resource="subtopics" :filters="request()->query()" />
                            <form id="bulk-delete-form" method="POST" action="{{ route('stopics.bulk-destroy') }}" class="d-none">@csrf</form>
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
                    <div class="el-result-count">Showing {{ $stopics->count() }} of {{ $stopics->total() }}</div>
                    <div class="table-responsive table-card mt-3 mb-1">
                        <table class="table align-middle table-nowrap" id="stopicTable">
                            <thead class="table-light">
                                <tr>
                                    <th style="width:44px"><input class="form-check-input" type="checkbox" id="select-all-stopics" aria-label="Select all subtopics on this page"></th>
                                    <th class="sort" data-sort="group_name">Group</th>
                                    <th class="sort" data-sort="subject_name">Subject Name</th>
                                    <th class="sort" data-sort="topic_name">Topic Name</th>
                                    <th class="sort" data-sort="stopic_name">Sub topic Name</th>
                                    <th class="sort" data-sort="action">Action</th>
                                </tr>
                            </thead>
                            <tbody class="list">
                                @forelse($stopics as $stopic)
                                <tr>
                                    <td><input class="form-check-input stopic-select" type="checkbox" value="{{ $stopic->id }}" data-question-count="{{ $stopic->questions_count }}" aria-label="Select {{ $stopic->name }}"></td>
                                    <td class="group_name">{{ $stopic->group?->group_name ?? 'Unassigned' }}</td>
                                    <td class="subject_name">{{ $stopic->subject->subject_name }}</td>
                                    <td class="topic_name">{{ $stopic->topic->name }}</td>
                                    <td class="stopic_name">{{ $stopic->name }}</td>
                                    <td>
                                        <div class="d-flex gap-2">
                                            <div class="edit">
                                                <button class="btn btn-sm el-btn-primary edit-item-btn"
                                                    data-bs-toggle="modal" data-bs-target="#showModal"
                                                    data-id="{{ $stopic->id }}"
                                                    data-group_id="{{ $stopic->group_id }}"
                                                    data-subject_id="{{ $stopic->subject_id }}"
                                                    data-topic_id="{{ $stopic->topic_id }}"
                                                    data-name="{{ $stopic->name }}" data-display-order="{{ $stopic->display_order }}">Edit</button>
                                            </div>
                                            <div class="remove">
                                                <button class="btn btn-sm el-btn-danger remove-item-btn"
                                                    data-bs-toggle="modal" data-bs-target="#deleteRecordModal"
                                                    data-id="{{ $stopic->id }}" data-name="{{ $stopic->name }}" data-question-count="{{ $stopic->questions_count }}">Remove</button>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="6" class="text-center">No Sub topics found.</td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    
                    <div class="d-flex justify-content-end mt-3">
                        {{ $stopics->appends(request()->query())->links('pagination::bootstrap-5') }}
                    </div>

                </div>
            </div>
        </div>
    </div>
</div>

{{-- Add/Edit Modal --}}
<div class="modal fade" id="showModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add Sub topic</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="stopic-form" method="POST" action="{{ route('stopics.store') }}">
                    @csrf
                    <div class="mb-3">
                        <label for="group_id-field" class="form-label">Group / Exam Context</label>
                        <select id="group_id-field" name="group_id" class="form-control" style="width: 100%;" required>
                            <option value="" selected disabled>Select Group</option>
                            @foreach($groups as $examGroup)
                            <option value="{{ $examGroup->id }}">{{ $examGroup->group_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="subject_id-field" class="form-label">Subject</label>
                        <select id="subject_id-field" name="subject_id" class="form-control" style="width: 100%;" required>
                            <option value="" selected disabled>Select Subject</option>
                            @foreach($subjects as $subject)
                            <option value="{{ $subject->id }}">{{ $subject->subject_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="topic_id-field" class="form-label">Topic</label>
                        <select id="topic_id-field" name="topic_id" class="form-control" style="width: 100%;" required disabled>
                            <option value="" selected disabled>Select Topic</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="name-field" class="form-label">Sub topic Name</label>
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

{{-- Delete Modal --}}
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
                        <p class="text-muted mx-4 mb-0" id="delete-impact-message">The subtopic will be deleted. Linked questions, exams and results will be kept.</p>
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
{{-- ✅ JQUERY & SELECT2 JS ADDED --}}
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        $('.select2').select2({ width: '100%' });

        function applyFilters() {
            showFilterWorking();
            const group = $('#groupFilter').val();
            const subject = $('#subjectFilter').val();
            const topic = $('#topicFilter').val();
            const search = $('#search-input').val();
            const perPage = $('#per-page-select').val();

            const url = new URL(window.location.href.split('?')[0]); // Base URL without old params
            if (group) url.searchParams.set('group', group);
            if (subject) url.searchParams.set('subject', subject);
            if (topic) url.searchParams.set('topic', topic);
            if (search) url.searchParams.set('search', search);
            if (perPage) url.searchParams.set('per_page', perPage);
            
            if (window.ExamLiteAjaxFilter) {
                window.ExamLiteAjaxFilter.loadUrl(url, ['#stopicList'], document.querySelector('#stopicList'));
            } else {
                window.location.href = url.toString();
            }
        }

        const urlParams = new URLSearchParams(window.location.search);
        const selectedGroup = urlParams.get('group');
        const selectedSubject = urlParams.get('subject');
        const selectedTopic = urlParams.get('topic');
        const selectedSearch = urlParams.get('search');
        const allSubjects = @json($subjects);
        const allTopicsForFilter = @json($topics);
        let filterTimer;
        let bootstrappingFilters = Boolean(selectedGroup);
        let currentGroupSubjectIds = allSubjects.map(subject => String(subject.id));

        function showFilterWorking() {
            $('.el-filter-bar').addClass('el-filter-working');
        }

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

        function fillTopicOptions(topics, selectedId = null) {
            const topicDropdown = document.getElementById("topicFilter");
            topicDropdown.innerHTML = '<option value="">All Topics</option>';

            topics.forEach(topic => {
                const option = document.createElement("option");
                option.value = topic.id;
                option.textContent = topic.name ?? 'Unnamed Topic';
                if (selectedId && selectedId == topic.id) {
                    option.selected = true;
                }
                topicDropdown.appendChild(option);
            });

            $('#topicFilter').trigger('change.select2');
        }

        function topicsForCurrentGroup() {
            return allTopicsForFilter.filter(topic => currentGroupSubjectIds.includes(String(topic.subject_id)));
        }

        // if (selectedSubject) {
        //     $('#subjectFilter').val(selectedSubject).trigger('change');
        // }

        // Search button click
        $(document).on('click', '#search-btn', function() {
            applyFilters();
        });

        $('#topicFilter').on('change', function() {
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
                window.ExamLiteAjaxFilter.loadUrl(url, ['#stopicList'], document.querySelector('#stopicList'));
            } else {
                window.location.href = url.toString();
            }
        });

        $('#groupFilter').on('change', function () {

            const groupId = $(this).val();
            const subjectDropdown = document.getElementById("subjectFilter");

            subjectDropdown.innerHTML = '<option value="">All Subjects</option>';

            if (!groupId) {
                currentGroupSubjectIds = allSubjects.map(subject => String(subject.id));
                fillSubjectOptions(allSubjects, selectedSubject);
                fillTopicOptions(allTopicsForFilter, selectedTopic);
                scheduleFilters(120);
                return;
            }

            fetch(`{{ route('stopics.get-subjects-by-group') }}?group_id=${groupId}`)
                .then(response => response.json())
                .then(data => {

                    currentGroupSubjectIds = data.map(subject => String(subject.id));

                    data.forEach(subject => {

                        const option = document.createElement("option");

                        option.value = subject.id;
                        option.textContent = subject.subject_name ?? 'Unnamed Subject';

                        if (selectedSubject && selectedSubject == subject.id) {
                            option.selected = true;
                        }

                        subjectDropdown.appendChild(option);
                    });

                    if (selectedSubject) {
                        $('#subjectFilter').val(selectedSubject);
                    }

                    fillTopicOptions(topicsForCurrentGroup(), selectedTopic);
                    if (selectedSubject) {
                        $('#subjectFilter').trigger('change');
                    } else {
                        $('#subjectFilter').trigger('change.select2');
                        scheduleFilters(120);
                    }

                })
                .catch(error => console.error('Error:', error));
        });

        $('#subjectFilter').on('change', function () {

            const groupId = $("#groupFilter").val();
            const subjectId = $(this).val();

            const topicDropdown = document.getElementById("topicFilter");

            topicDropdown.innerHTML = '<option value="">All Topics</option>';

            if (!subjectId) {
                fillTopicOptions(topicsForCurrentGroup(), selectedTopic);
                if (bootstrappingFilters) {
                    bootstrappingFilters = false;
                    return;
                }
                scheduleFilters(120);
                return;
            }

            fetch(`{{ route('stopics.get-topics-by-subject') }}?group_id=${groupId}&subject_id=${subjectId}`)
                .then(response => response.json())
                .then(data => {

                    data.forEach(topic => {

                        const option = document.createElement("option");

                        option.value = topic.id;
                        option.textContent = topic.name ?? 'Unnamed Topic';

                        if (selectedTopic && selectedTopic == topic.id) {
                            option.selected = true;
                        }

                        topicDropdown.appendChild(option);
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
            $('#groupFilter')
                .val(selectedGroup)
                .trigger('change');
        } else {
            bootstrappingFilters = false;
        }
        
        // 1. Data load from Controller (Fastest method)
        const allTopics = @json($topics);

        // 2. Initialize Select2
        $(document).ready(function() {
            // Apply select2 to dropdowns
            $('#group_id-field').select2({ placeholder: 'Select Group', allowClear: true, dropdownParent: $('#showModal') });

            $('#subject_id-field').select2({
                placeholder: "Select Subject",
                allowClear: true,
                dropdownParent: $('#showModal') // Important for Modal
            });

            $('#topic_id-field').select2({
                placeholder: "Select Topic",
                allowClear: true,
                dropdownParent: $('#showModal') // Important for Modal
            });

            $('#group_id-field').on('change', function() { $('#subject_id-field').trigger('change'); });

            // 3. Filter Logic (Subject Change -> Topic Update)
            $('#subject_id-field').on('change', function() {
                var subjectId = $(this).val();
                var topicSelect = $('#topic_id-field');
                
                // Clear old options
                topicSelect.empty().append('<option value="" selected disabled>Select Topic</option>');

                if (subjectId) {
                    // Filter matching topics
                    var groupId = $('#group_id-field').val();
                    var filteredTopics = allTopics.filter(function(topic) {
                        return topic.subject_id == subjectId && topic.group_id == groupId;
                    });

                    // Add new options
                    filteredTopics.forEach(function(topic) {
                        var newOption = new Option(topic.name, topic.id, false, false);
                        topicSelect.append(newOption);
                    });

                    topicSelect.prop('disabled', false);
                } else {
                    topicSelect.prop('disabled', true);
                }
                
                // Refresh Select2 UI after updating options
                topicSelect.trigger('change.select2');

                // Edit Mode: Auto Select Logic
                var preSelectedTopic = topicSelect.attr('data-selected');
                if(preSelectedTopic) {
                    if (topicSelect.find("option[value='" + preSelectedTopic + "']").length) {
                        topicSelect.val(preSelectedTopic).trigger('change');
                    }
                    topicSelect.removeAttr('data-selected');
                }
            });
        });

        // Modal Logic (Add/Edit)
        document.getElementById('showModal').addEventListener('show.bs.modal', function(event) {
            var button = event.relatedTarget;
            var id = button.getAttribute('data-id');
            var form = document.getElementById('stopic-form');
            var modalTitle = this.querySelector('.modal-title');
            
            // Clean method input
            var methodInput = form.querySelector('input[name="_method"]');
            if (methodInput) methodInput.remove();

            if (id) { 
                // EDIT MODE
                modalTitle.textContent = 'Edit Sub topic';
                form.action = '{{ url("stopics") }}/' + id;
                form.insertAdjacentHTML('beforeend', '<input type="hidden" name="_method" value="PUT">');

                var groupId = button.getAttribute('data-group_id');
                var subjectId = button.getAttribute('data-subject_id');
                var topicId = button.getAttribute('data-topic_id');
                var name = button.getAttribute('data-name');
                
                document.getElementById('name-field').value = name;
                document.getElementById('display-order-field').value = button.getAttribute('data-display-order') || '';
                
                $('#group_id-field').val(groupId).trigger('change');

                // Store topic ID for later selection
                $('#topic_id-field').attr('data-selected', topicId);
                
                // Trigger subject change to populate topics
                $('#subject_id-field').val(subjectId).trigger('change');

            } else { 
                // ADD MODE
                modalTitle.textContent = 'Add Sub topic';
                form.action = '{{ route("stopics.store") }}';
                form.reset();
                $('#group_id-field').val(null).trigger('change');
                $('#subject_id-field').val(null).trigger('change');
                $('#topic_id-field').val(null).trigger('change').prop('disabled', true);
            }
        });

        // Delete Modal Logic
        document.getElementById('deleteRecordModal').addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            const id = button.getAttribute('data-id');
            const name = button.getAttribute('data-name') || 'this subtopic';
            const questionCount = Number(button.getAttribute('data-question-count') || 0);
            const action = "{{ route('stopics.destroy', ':id') }}".replace(':id', id);
            document.getElementById('delete-form').setAttribute('action', action);
            document.getElementById('delete-impact-message').textContent = questionCount
                ? `"${name}" is linked to ${questionCount} question(s). The questions, exam papers and results will be kept; only their subtopic classification will be removed.`
                : `"${name}" is not linked to any questions and can be safely deleted.`;
        });

        function selectedSubtopics() {
            return Array.from(document.querySelectorAll('.stopic-select:checked'));
        }

        function refreshBulkDeleteState() {
            const selected = selectedSubtopics();
            document.getElementById('bulk-delete-stopics').disabled = selected.length === 0;
            const all = Array.from(document.querySelectorAll('.stopic-select'));
            const selectAll = document.getElementById('select-all-stopics');
            if (selectAll) {
                selectAll.checked = all.length > 0 && selected.length === all.length;
                selectAll.indeterminate = selected.length > 0 && selected.length < all.length;
            }
        }

        $(document).on('change', '#select-all-stopics', function () {
            document.querySelectorAll('.stopic-select').forEach(checkbox => checkbox.checked = this.checked);
            refreshBulkDeleteState();
        });
        $(document).on('change', '.stopic-select', refreshBulkDeleteState);
        $(document).on('click', '#bulk-delete-stopics', function () {
            const selected = selectedSubtopics();
            const questionCount = selected.reduce((total, checkbox) => total + Number(checkbox.dataset.questionCount || 0), 0);
            Swal.fire({
                icon: 'warning',
                title: `Delete ${selected.length} subtopic(s)?`,
                html: questionCount
                    ? `<strong>${questionCount} linked question(s)</strong> will be kept in their exam papers and results, but their subtopic classification will be removed.`
                    : 'The selected subtopics are not linked to questions.',
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
                    title: 'Deleting selected subtopics...',
                    text: 'Questions, exam papers and results are being preserved. Please keep this page open.',
                    allowOutsideClick: false,
                    allowEscapeKey: false,
                    showConfirmButton: false,
                    didOpen: () => Swal.showLoading()
                });
                window.setTimeout(() => form.submit(), 50);
            });
        });
        // Alerts
        @if(session('success'))
        Swal.fire({ icon: 'success', title: 'Success', text: '{{ session('success') }}', timer: 2000, showConfirmButton: false });
        @endif
        @if(session('error'))
        Swal.fire({ icon: 'error', title: 'Error', text: '{{ session('error') }}', timer: 3000, showConfirmButton: false });
        @endif
    });
</script>
@endsection

@extends('layouts.master')
@section('title', 'Questions')

@section('css')
<link href="{{ URL::asset('build/libs/sweetalert2/sweetalert2.min.css') }}" rel="stylesheet" type="text/css" />
<link href="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/css/select2.min.css" rel="stylesheet" />
@endsection

@section('content')
@component('components.breadcrumb')
@slot('li_1', '')
@slot('title', 'Questions')
@endcomponent

@php
    $canAddQuestion = user_can_route_action('questions.create', 'add');
    $canEditQuestion = user_can_route_action('questions.edit', 'edit');
    $canDeleteQuestion = user_can_route_action('questions.destroy', 'delete');
    $statusOptions = ['Yes' => 'Active', 'No' => 'Inactive'];
@endphp

<div class="row">
    <div class="col-lg-12">
        <div class="card">
            <div class="card-header">
                <h4 class="card-title mb-0">Add, Edit & Remove Questions</h4>
                <div class="row g-2 mt-3 align-items-stretch">
                    <div class="col-lg-3 col-md-4">
                        <select id="examGroupFilter" class="form-control select2">
                            <option value="">All Groups</option>
                            @foreach($allExamGroups as $examGroups)
                                <option {{ $examGroups['id'] == $selectedGroup ? 'selected' : '' }} value="{{ $examGroups['id'] }}">{{ $examGroups['group_name'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-auto d-flex gap-2">
                        <button class="btn el-btn-primary el-btn-icon" type="button" data-bs-toggle="collapse" data-bs-target="#advancedQuestionFilters" aria-expanded="{{ $advancedFiltersActive ? 'true' : 'false' }}" aria-controls="advancedQuestionFilters">
                            <i class="ri-equalizer-line"></i> Advanced Filters
                        </button>
                        <button id="search-btn" class="btn el-btn-primary el-btn-icon">Search</button>
                        <button id="reset-btn" class="btn el-btn-secondary el-btn-icon">Reset</button>
                    </div>
                </div>
                <div class="small mt-2 text-muted">
                    <span id="questionFilterCount">Showing {{ $questions->count() }} questions{{ $questions->hasMorePages() ? ' (more available)' : '' }}</span>
                    <span id="questionFilterLoading" class="d-none text-primary fw-semibold">Updating questions...</span>
                </div>
                <div id="advancedQuestionFilters" class="collapse {{ $advancedFiltersActive ? 'show' : '' }}">
                    <div class="border-top mt-3 pt-3">
                        <div class="row g-2 align-items-stretch">
                            <div class="col-xl-2 col-lg-3 col-md-4">
                                <label class="form-label">Category</label>
                                <select id="category-filter" class="form-control select2">
                                    <option value="">All Categories</option>
                                    @foreach($parentCategories as $category)
                                        <option value="{{ $category->id }}" {{ (string) request('category') === (string) $category->id ? 'selected' : '' }}>{{ $category->title }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-xl-2 col-lg-3 col-md-4" data-subcategory-ui>
                                <label class="form-label">Subcategory</label>
                                <select id="subcategory-filter" class="form-control select2">
                                    <option value="">All Subcategories</option>
                                    @foreach($childCategories as $category)
                                        <option value="{{ $category->id }}" data-parent="{{ $category->parent_id }}" {{ (string) request('subcategory') === (string) $category->id ? 'selected' : '' }}>{{ $category->title }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-xl-2 col-lg-3 col-md-4">
                                <label class="form-label">Package</label>
                                <select id="examPackageFilter" class="form-control select2">
                                    <option value="">All Packages</option>
                                    @foreach($allExamPackages as $examPackages)
                                        <option {{ $examPackages['id'] == $selectedPackage ? 'selected' : '' }} value="{{ $examPackages['id'] }}">{{ $examPackages['name'] }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-xl-2 col-lg-3 col-md-4">
                                <label class="form-label">Exam</label>
                                <select id="exam-filter" class="form-control select2">
                                    <option value="">All Exams</option>
                                    @foreach($examsQuestions as $examOption)
                                        <option value="{{ $examOption->id }}" {{ (string) request('exam') === (string) $examOption->id ? 'selected' : '' }}>
                                            {{ $examOption->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-xl-2 col-lg-3 col-md-4">
                                <label class="form-label">Exam Assignment</label>
                                <select id="exam-assignment-filter" class="form-control select2">
                                    <option value="">All Questions</option>
                                    <option value="assigned" {{ request('exam_assignment') === 'assigned' ? 'selected' : '' }}>Used in an Exam</option>
                                    <option value="unassigned" {{ request('exam_assignment') === 'unassigned' ? 'selected' : '' }}>Not Used in Any Exam</option>
                                </select>
                            </div>
                            <div class="col-xl-2 col-lg-3 col-md-4">
                                <label class="form-label">Subject</label>
                                <select id="subject-filter" class="form-control select2">
                                    <option value="">All Subjects</option>
                                    @foreach($subjects as $subject)
                                    <option value="{{ $subject->id }}" {{ request('subject')==$subject->id ? 'selected' : '' }}>
                                        {{ $subject->subject_name }}
                                    </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-xl-2 col-lg-3 col-md-4">
                                <label class="form-label">Topic</label>
                                <select id="topic-filter" class="form-control select2">
                                    <option value="">All Topics</option>
                                    @foreach($topics as $topic)
                                    <option value="{{ $topic->id }}" {{ request('topic')==$topic->id ? 'selected' : '' }}>
                                        {{ $topic->name }}
                                    </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-xl-2 col-lg-3 col-md-4">
                                <label class="form-label">Subtopic</label>
                                <select id="subtopic-filter" class="form-control select2">
                                    <option value="">All Subtopics</option>
                                    @foreach($stopics as $stopic)
                                    <option value="{{ $stopic->id }}" {{ request('subtopic')==$stopic->id ? 'selected' : '' }}>
                                        {{ $stopic->name }}
                                    </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-xl-2 col-lg-3 col-md-4">
                                <label class="form-label">Question Type</label>
                                <select id="qtype-filter" class="form-control select2">
                                    <option value="">All Question Types</option>
                                    @foreach($qtypes as $qtype)
                                    <option value="{{ $qtype->id }}" {{ request('qtype')==$qtype->id ? 'selected' : '' }}>
                                        {{ $qtype->question_type }}
                                    </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-xl-2 col-lg-3 col-md-4">
                                <label class="form-label">Difficulty</label>
                                <select id="diff-filter" class="form-control select2">
                                    <option value="">All Difficulty Levels</option>
                                    @foreach($diffs as $diff)
                                    <option value="{{ $diff->id }}" {{ request('diff')==$diff->id ? 'selected' : '' }}>
                                        {{ $diff->diff_level }}
                                    </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-xl-2 col-lg-3 col-md-4">
                                <label class="form-label">Language</label>
                                <select id="language-filter" class="form-control select2">
                                    <option value="">All Languages</option>
                                    @foreach($languages as $language)
                                    <option value="{{ $language->id }}" {{ request('language')==$language->id ? 'selected' : '' }}>
                                        {{ $language->name }}
                                    </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-xl-2 col-lg-3 col-md-4">
                                <label class="form-label">Status</label>
                                <select id="status-filter" class="form-control select2">
                                    <option value="">All Status</option>
                                    @foreach($statusOptions as $value => $label)
                                        <option value="{{ $value }}" {{ request('status') === $value ? 'selected' : '' }}>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-xl-2 col-lg-3 col-md-4">
                                <label class="form-label">Question Tags</label>
                                <select id="tag-filter" class="form-control select2">
                                    <option value="">All Tags</option>
                                    @foreach($questionTags as $tag)
                                        <option value="{{ $tag->id }}" {{ (string) request('tag') === (string) $tag->id ? 'selected' : '' }}>{{ $tag->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-xl-2 col-lg-3 col-md-4">
                                <label class="form-label">Positive Marks</label>
                                <div class="input-group">
                                    <input type="number" step="0.01" id="marks-min-filter" class="form-control" placeholder="Min" value="{{ request('marks_min') }}">
                                    <input type="number" step="0.01" id="marks-max-filter" class="form-control" placeholder="Max" value="{{ request('marks_max') }}">
                                </div>
                            </div>
                            <div class="col-xl-2 col-lg-3 col-md-4">
                                <label class="form-label">Negative Marks</label>
                                <div class="input-group">
                                    <input type="number" step="0.01" id="negative-marks-min-filter" class="form-control" placeholder="Min" value="{{ request('negative_marks_min') }}">
                                    <input type="number" step="0.01" id="negative-marks-max-filter" class="form-control" placeholder="Max" value="{{ request('negative_marks_max') }}">
                                </div>
                            </div>
                            <div class="col-xl-2 col-lg-3 col-md-4">
                                <label class="form-label">Image</label>
                                <select id="has-image-filter" class="form-control select2">
                                    <option value="">Any</option>
                                    <option value="yes" {{ request('has_image') === 'yes' ? 'selected' : '' }}>Has Image</option>
                                    <option value="no" {{ request('has_image') === 'no' ? 'selected' : '' }}>No Image</option>
                                </select>
                            </div>
                            <div class="col-xl-2 col-lg-3 col-md-4">
                                <label class="form-label">Passage</label>
                                <select id="has-passage-filter" class="form-control select2">
                                    <option value="">Any</option>
                                    <option value="yes" {{ request('has_passage') === 'yes' ? 'selected' : '' }}>Has Passage</option>
                                    <option value="no" {{ request('has_passage') === 'no' ? 'selected' : '' }}>No Passage</option>
                                </select>
                            </div>
                            <div class="col-xl-2 col-lg-3 col-md-4">
                                <label class="form-label">AI</label>
                                <select id="ai-generated-filter" class="form-control select2">
                                    <option value="">Any</option>
                                    <option value="yes" {{ request('ai_generated') === 'yes' ? 'selected' : '' }}>AI Generated</option>
                                    <option value="no" {{ request('ai_generated') === 'no' ? 'selected' : '' }}>Manual</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="card-body">
                @include('questions.partials.list')
            </div>
        </div>
    </div>
</div>

{{-- ================================================================= --}}
{{-- START: DELETE CONFIRMATION MODAL (YAHAN FIX KIYA GAYA HAI) --}}
{{-- ================================================================= --}}
@if($canDeleteQuestion)
<div class="modal fade zoomIn" id="deleteRecordModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" id="btn-close"></button>
            </div>
            <div class="modal-body">
                <div class="mt-2 text-center">
                    <i class="ri-delete-bin-line display-4 text-danger"></i>
                    <div class="mt-4 pt-2 fs-15 mx-4">
                        <h4>Are you sure?</h4>
                        <p class="text-muted mx-4 mb-0">Are you sure you want to remove this question? This action cannot be undone.</p>
                    </div>
                </div>
                <div class="d-flex gap-2 justify-content-center mt-4 mb-2">
                    <button type="button" class="btn w-sm btn-light" data-bs-dismiss="modal">Close</button>
                    
                    {{-- YEH HAI SABSE IMPORTANT FORM --}}
                    <form id="delete-form" action="" method="POST" class="d-inline">
                        @csrf
                        @method('DELETE') {{-- YAHI LINE MAIN FIX HAI --}}
                        <button type="submit" class="btn w-sm el-btn-danger" id="delete-record">Yes, Delete It!</button>
                    </form>
                    {{-- FORM YAHAN KHATAM HOTA HAI --}}
                    
                </div>
            </div>
        </div>
    </div>
</div>
@endif
@include('questions.partials.exam-assignment-modal')
{{-- ================================================================= --}}
{{-- END: DELETE CONFIRMATION MODAL --}}
{{-- ================================================================= --}}
@endsection

@section('script')
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="{{ URL::asset('build/libs/sweetalert2/sweetalert2.min.js') }}"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/js/select2.min.js"></script>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        $('.select2').select2();
        let filterTimer;
        let activeQuestionRequest = null;

        function showFilterWorking() {
            $('#questionList').addClass('el-filter-working');
            $('#questionFilterCount').addClass('d-none');
            $('#questionFilterLoading').removeClass('d-none');
        }

        function hideFilterWorking() {
            $('#questionList').removeClass('el-filter-working');
            $('#questionFilterCount').removeClass('d-none');
            $('#questionFilterLoading').addClass('d-none');
        }

        function filterUrl() {
            const url = new URL(window.location.href.split('?')[0]);
            const filters = {
                group: $("#examGroupFilter").val(),
                category: $('#category-filter').val(),
                subcategory: $('#subcategory-filter').val(),
                package: $("#examPackageFilter").val(),
                exam: $('#exam-filter').val(),
                exam_assignment: $('#exam-assignment-filter').val(),
                subject: $('#subject-filter').val(),
                topic: $('#topic-filter').val(),
                subtopic: $('#subtopic-filter').val(),
                qtype: $('#qtype-filter').val(),
                diff: $('#diff-filter').val(),
                language: $('#language-filter').val(),
                status: $('#status-filter').val(),
                marks_min: $('#marks-min-filter').val(),
                marks_max: $('#marks-max-filter').val(),
                negative_marks_min: $('#negative-marks-min-filter').val(),
                negative_marks_max: $('#negative-marks-max-filter').val(),
                has_image: $('#has-image-filter').val(),
                has_passage: $('#has-passage-filter').val(),
                ai_generated: $('#ai-generated-filter').val(),
                tag: $('#tag-filter').val(),
                per_page: $('#per-page-select').val(),
                search: $('#search-input').val()
            };

            Object.entries(filters).forEach(function ([key, value]) {
                if (value) {
                    url.searchParams.set(key, value);
                }
            });

            return url;
        }

        function replaceQuestionList(html) {
            const doc = new DOMParser().parseFromString(html, 'text/html');
            const incoming = doc.querySelector('#questionList');
            const current = document.querySelector('#questionList');

            if (incoming && current) {
                current.replaceWith(incoming);
                $('#questionFilterCount').text(incoming.dataset.resultCount || '');
                return true;
            }

            return false;
        }

        function loadQuestionList(url) {
            showFilterWorking();

            if (activeQuestionRequest) {
                activeQuestionRequest.abort();
            }

            const requestController = new AbortController();
            activeQuestionRequest = requestController;

            fetch(url.toString(), {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'text/html'
                },
                credentials: 'same-origin',
                signal: requestController.signal
            })
                .then(function (response) {
                    if (!response.ok) {
                        throw new Error('Filter request failed');
                    }

                    return response.text();
                })
                .then(function (html) {
                    if (!replaceQuestionList(html)) {
                        throw new Error('Question list partial was missing from response.');
                    }

                    window.history.replaceState({}, '', url.toString());
                })
                .catch(function (error) {
                    if (error.name !== 'AbortError') {
                        console.error('Question filter error:', error);
                        if (window.Swal) {
                            Swal.fire({
                                icon: 'error',
                                title: 'Filter failed',
                                text: 'Could not update the question list. Please refresh and try again.'
                            });
                        }
                    }
                })
                .finally(function () {
                    if (activeQuestionRequest === requestController) {
                        activeQuestionRequest = null;
                        hideFilterWorking();
                    }
                });
        }

        window.refreshQuestionList = function (url) {
            loadQuestionList(url ? new URL(url, window.location.origin) : new URL(window.location.href));
        };

        function applyFilters() {
            loadQuestionList(filterUrl());
        }

        function scheduleFilters(delay = 450) {
            clearTimeout(filterTimer);
            filterTimer = setTimeout(applyFilters, delay);
        }

        const urlParams = new URLSearchParams(window.location.search);
        const selectedGroup = urlParams.get('group');
        const selectedPackage = urlParams.get('package');
        const selectedExam = urlParams.get('exam');
        const dependentOptionsUrl = "{{ route('filters.dependent-options') }}";

        function optionLabel(value) {
            if (!value) {
                return '-';
            }

            if (typeof value === 'object') {
                return value[document.documentElement.lang] || value.en || Object.values(value)[0] || '-';
            }

            try {
                const parsed = JSON.parse(value);
                if (parsed && typeof parsed === 'object') {
                    return parsed[document.documentElement.lang] || parsed.en || Object.values(parsed)[0] || '-';
                }
            } catch (error) {}

            return value;
        }

        function updateSelectOptions(selector, placeholder, items, selectedValue, labelKey) {
            const select = document.querySelector(selector);
            if (!select) {
                return;
            }

            select.innerHTML = `<option value="">${placeholder}</option>`;

            items.forEach(function (item) {
                const option = document.createElement('option');
                option.value = item.id;
                option.textContent = optionLabel(item[labelKey]);
                if (selectedValue && String(selectedValue) === String(item.id)) {
                    option.selected = true;
                }
                select.appendChild(option);
            });

            if (window.jQuery && jQuery.fn.select2) {
                jQuery(select).trigger('change.select2');
            }
        }

        function refreshDependentOptions(changedLevel) {
            if (changedLevel === 'group') {
                $('#category-filter, #subcategory-filter, #examPackageFilter, #exam-filter, #subject-filter, #topic-filter, #subtopic-filter').val('').trigger('change.select2');
            } else if (changedLevel === 'category') {
                $('#subcategory-filter, #examPackageFilter, #exam-filter, #subject-filter, #topic-filter, #subtopic-filter').val('').trigger('change.select2');
            } else if (changedLevel === 'subcategory') {
                $('#examPackageFilter, #exam-filter, #subject-filter, #topic-filter, #subtopic-filter').val('').trigger('change.select2');
            } else if (changedLevel === 'package') {
                $('#exam-filter, #subject-filter, #topic-filter, #subtopic-filter').val('').trigger('change.select2');
            } else if (changedLevel === 'exam') {
                $('#subject-filter, #topic-filter, #subtopic-filter').val('').trigger('change.select2');
            } else if (changedLevel === 'subject') {
                $('#topic-filter, #subtopic-filter').val('').trigger('change.select2');
            } else if (changedLevel === 'topic') {
                $('#subtopic-filter').val('').trigger('change.select2');
            }

            const params = new URLSearchParams({
                group_id: $('#examGroupFilter').val() || '',
                category_id: $('#category-filter').val() || '',
                subcategory_id: $('#subcategory-filter').val() || '',
                package_id: $('#examPackageFilter').val() || '',
                exam_id: $('#exam-filter').val() || '',
                subject_id: $('#subject-filter').val() || '',
                topic_id: $('#topic-filter').val() || ''
            });

            fetch(`${dependentOptionsUrl}?${params.toString()}`, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
                .then(response => response.json())
                .then(function (data) {
                    updateSelectOptions('#category-filter', 'All Categories', data.categories || [], $('#category-filter').val(), 'title');
                    updateSelectOptions('#subcategory-filter', 'All Subcategories', data.subcategories || [], $('#subcategory-filter').val(), 'title');
                    updateSelectOptions('#examPackageFilter', 'All Packages', data.packages || [], $('#examPackageFilter').val(), 'name');
                    updateSelectOptions('#exam-filter', 'All Exams', data.exams || [], $('#exam-filter').val(), 'name');
                    updateSelectOptions('#subject-filter', 'All Subjects', data.subjects || [], $('#subject-filter').val(), 'subject_name');
                    updateSelectOptions('#topic-filter', 'All Topics', data.topics || [], $('#topic-filter').val(), 'name');
                    updateSelectOptions('#subtopic-filter', 'All Subtopics', data.stopics || [], $('#subtopic-filter').val(), 'name');
                    scheduleFilters(120);
                })
                .catch(error => console.error('Dependent filter error:', error));
        }

        // Search button click
        $('#search-btn').on('click', function() {
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

        $(document).on('change', '#category-filter', function() {
            refreshDependentOptions('category');
        });

        $(document).on('change', '#subcategory-filter', function() {
            refreshDependentOptions('subcategory');
        });

        $(document).on('change', '#examPackageFilter', function() {
            refreshDependentOptions('package');
        });

        $(document).on('change', '#exam-filter', function() {
            refreshDependentOptions('exam');
        });

        $(document).on('change', '#subject-filter', function() {
            refreshDependentOptions('subject');
        });

        $(document).on('change', '#topic-filter', function() {
            refreshDependentOptions('topic');
        });

        $(document).on('change', '#subtopic-filter, #qtype-filter, #diff-filter, #language-filter, #status-filter, #tag-filter, #has-image-filter, #has-passage-filter, #ai-generated-filter, #exam-assignment-filter', function() {
            scheduleFilters(120);
        });

        $(document).on('input', '#marks-min-filter, #marks-max-filter, #negative-marks-min-filter, #negative-marks-max-filter', function() {
            scheduleFilters(650);
        });
        
        // Per-page select change
        $(document).on('change', '#per-page-select', function() {
            applyFilters();
        });

        $(document).on('click', '#questionList .pagination a', function(e) {
            e.preventDefault();
            loadQuestionList(new URL(this.href));
        });

        // Reset button click
        $('#reset-btn').on('click', function() {
            const url = new URL(window.location.href.split('?')[0]);
            $('#examGroupFilter, #category-filter, #subcategory-filter, #examPackageFilter, #exam-filter, #subject-filter, #topic-filter, #subtopic-filter, #qtype-filter, #diff-filter, #language-filter, #status-filter, #tag-filter, #has-image-filter, #has-passage-filter, #ai-generated-filter, #exam-assignment-filter').val('').trigger('change.select2');
            $('#marks-min-filter, #marks-max-filter, #negative-marks-min-filter, #negative-marks-max-filter').val('');
            loadQuestionList(url);
        });

        $('#examGroupFilter').on('change', function () {
            refreshDependentOptions('group');
        });

        // ===============================================================
        // YEH SCRIPT FORM KA ACTION URL SET KARTI HAI - YEH SAHI HAI
        // ===============================================================
        $('#deleteRecordModal').on('show.bs.modal', function(event) {
            var button = event.relatedTarget;
            var id = button.getAttribute('data-id');
            var action = "{{ route('questions.destroy', ':id') }}";
            action = action.replace(':id', id);
            $('#delete-form').attr('action', action); // Yahan 'delete-form' ko target kar rahe hain
        });

        // SweetAlert messages (no change needed)
        @if(session('success'))
        Swal.fire({ icon: 'success', title: 'Success', text: '{{ session('success') }}', timer: 2000, showConfirmButton: false });
        @endif
        @if(session('import_report'))
        const importReport = @json(session('import_report'));
        const reportRows = [
            ['File', importReport.file], ['Mode', importReport.mode], ['Rows processed', importReport.processed],
            ['Questions updated', importReport.updated], ['Duplicates tagged', importReport.duplicates],
            ['Hierarchy records created', importReport.created_records], ['Warnings', (importReport.warnings || []).length],
            ['Errors', (importReport.errors || []).length]
        ].map(row => `<tr><th class="text-start pe-4">${row[0]}</th><td class="text-start">${row[1] ?? ''}</td></tr>`).join('');
        const reportDetails = [...(importReport.warnings || []), ...(importReport.errors || [])];
        const details = reportDetails.length ? `<hr><div class="text-start"><strong>Details</strong><ul class="mb-0">${reportDetails.map(item => `<li>${item}</li>`).join('')}</ul></div>` : '<div class="text-success mt-2">No warnings or errors.</div>';
        Swal.fire({ icon: importReport.errors?.length ? 'warning' : 'success', title: 'Import report', html: `<table class="table table-sm mb-0"><tbody>${reportRows}</tbody></table>${details}`, confirmButtonText: 'Close', width: 620 });
        @endif
        @if(session('error'))
        Swal.fire({ icon: 'error', title: 'Error', text: '{{ session('error') }}', timer: 3000, showConfirmButton: false });
        @endif
    });

    // Bulk delete script (no change needed)
    $(document).on('change', '#select-all', function () {
        $('.question-checkbox').prop('checked', this.checked);
    });

    $(document).on('change', '.question-checkbox', function () {
        $('#select-all').prop('checked', $('.question-checkbox:checked').length === $('.question-checkbox').length);
    });

    $(document).on('click', '#bulk-delete-btn', function () {
        let ids = $('.question-checkbox:checked').map(function() { return $(this).val(); }).get();

        if (ids.length === 0) {
            Swal.fire('Warning', 'Please select at least one question.', 'warning');
            return;
        }

        Swal.fire({
            title: 'Are you sure?',
            text: "Selected questions will be deleted permanently!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Yes, delete',
            cancelButtonText: 'Cancel'
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.fire({
                    title: 'Deleting selected questions...',
                    text: 'Checking exam links and processing the batch. Please keep this page open.',
                    allowOutsideClick: false,
                    allowEscapeKey: false,
                    showConfirmButton: false,
                    didOpen: () => Swal.showLoading()
                });

                $.ajax({
                    url: "{{ route('questions.remove') }}",
                    type: 'post',
                    data: { ids: ids, _token: "{{ csrf_token() }}" },
                    success: function (response) {
                        Swal.fire('Deleted!', response.message, 'success').then(() => {
                            if (window.refreshQuestionList) {
                                window.refreshQuestionList();
                            } else {
                                location.reload();
                            }
                        });
                    },
                    error: function (xhr) {
                        const message = xhr.responseJSON?.message || 'Failed to delete questions.';
                        Swal.fire('Error!', message, 'error');
                    }
                });
            }
        });
    });
</script>
@include('questions.partials.exam-assignment-script')
@endsection
<script>
// =============================================================================
// AI REGENERATE BUTTON - WORKING VERSION
// =============================================================================
(function() {
    function showPlanMessage() {
        Swal.fire({
            icon: 'info',
            title: 'Not included in your plan',
            text: 'Please contact the platform administrator to enable this feature for your organization.',
            confirmButtonText: 'OK'
        });
    }

    document.addEventListener('click', function(e) {
        var locked = e.target.closest('.js-plan-feature-locked');
        if (locked && !locked.matches('#aiRegenerateBtn')) {
            e.preventDefault();
            showPlanMessage();
        }
    });

    function handleRegenerate(btn, e) {
        e.preventDefault();

        if (!btn) {
            return;
        }

        if (btn.classList.contains('js-plan-feature-locked')) {
            showPlanMessage();
            return;
        }

        var selectedIds = [];
        document.querySelectorAll('.question-checkbox:checked').forEach(function(cb) {
            selectedIds.push(cb.value);
        });

        if (selectedIds.length === 0) {
            Swal.fire('No Selection', 'Please select at least one question to regenerate.', 'warning');
            return;
        }

        var styles = getComputedStyle(document.documentElement);
        var primary = styles.getPropertyValue('--el-primary') || styles.getPropertyValue('--vz-primary') || '#0f766e';
        var secondary = styles.getPropertyValue('--el-secondary') || styles.getPropertyValue('--vz-warning') || '#f59e0b';

        Swal.fire({
            title: 'Confirm Regeneration',
            html: 'Generate new AI versions for <strong>' + selectedIds.length + '</strong> question(s)?<br><br>Original questions will remain unchanged.',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: primary.trim(),
            cancelButtonColor: secondary.trim(),
            confirmButtonText: 'Yes, Generate with AI!',
            cancelButtonText: 'Cancel'
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.fire({
                    title: 'Generating...',
                    text: 'AI is creating new questions. Please wait.',
                    allowOutsideClick: false,
                    didOpen: () => { Swal.showLoading(); }
                });

                fetch('/ai-regenerator/regenerate', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    },
                    body: JSON.stringify({ question_ids: selectedIds })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        var successCount = data.results.filter(function(r) { return r.success; }).length;
                        Swal.fire({
                            title: 'Complete!',
                            html: '<strong>' + successCount + '</strong> new questions generated using <strong>AI</strong>.',
                            icon: 'success',
                            confirmButtonText: 'OK'
                        }).then(() => {
                            if (window.refreshQuestionList) {
                                window.refreshQuestionList();
                            } else {
                                location.reload();
                            }
                        });
                    } else {
                        Swal.fire('Error', data.message || 'Failed to generate questions', 'error');
                    }
                })
                .catch(function(error) {
                    console.error('Error:', error);
                    Swal.fire('Error', 'An error occurred. Please try again.', 'error');
                });
            }
        });
    }

    document.addEventListener('click', function(e) {
        var btn = e.target.closest('#aiRegenerateBtn');
        if (btn) {
            handleRegenerate(btn, e);
        }
    });
})();
</script>

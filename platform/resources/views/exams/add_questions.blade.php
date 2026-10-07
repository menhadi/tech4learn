@extends('layouts.master')

@section('title', 'Add Questions to Exam')

@section('css')
<link href="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/css/select2.min.css" rel="stylesheet" />
<link href="{{ URL::asset('build/libs/sweetalert2/sweetalert2.min.css') }}" rel="stylesheet" type="text/css" />
<style>
    .exam-add-loading {
        align-items: center;
        color: var(--el-primary, var(--vz-primary, #0f766e));
        display: none;
        font-weight: 600;
        gap: 8px;
    }

    .exam-add-loading::before {
        animation: exam-add-pulse 0.75s ease-in-out infinite alternate;
        background: var(--el-primary, var(--vz-primary, #0f766e));
        border-radius: 999px;
        content: "";
        height: 8px;
        width: 8px;
    }

    .exam-add-updating .exam-add-loading {
        display: inline-flex;
    }

    .exam-add-updating .js-exam-add-count {
        display: none;
    }

    .exam-add-filter-row {
        align-items: flex-end;
        display: flex;
        flex-wrap: nowrap;
        gap: 10px;
    }

    .exam-add-group-filter {
        flex: 0 0 260px;
        max-width: 260px;
        min-width: 0;
    }

    .exam-add-filter-action,
    .exam-add-filter-buttons {
        flex: 0 0 auto;
    }

    .exam-add-filter-buttons {
        display: flex;
        gap: 10px;
    }

    @media (max-width: 767.98px) {
        .exam-add-filter-row {
            flex-wrap: wrap;
        }

        .exam-add-group-filter,
        .exam-add-filter-action,
        .exam-add-filter-buttons {
            flex: 1 1 100%;
            max-width: 100%;
        }

        .exam-add-filter-buttons .btn {
            flex: 1 1 0;
        }
    }

    @keyframes exam-add-pulse {
        from {
            opacity: 0.45;
            transform: scale(0.85);
        }
        to {
            opacity: 1;
            transform: scale(1.15);
        }
    }
</style>
@endsection

@section('content')
@component('components.breadcrumb')
@slot('li_1')
<a href="{{ route('exams.index') }}">Exams</a>
@endslot
@slot('title', 'Add Questions to Exam')
@endcomponent

@php
    $advancedExamQuestionFiltersActive = request()->filled('package')
        || request()->filled('category')
        || request()->filled('subcategory')
        || request()->filled('exam_filter')
        || request()->filled('subject')
        || request()->filled('topic')
        || request()->filled('subtopic')
        || request()->filled('qtype')
        || request()->filled('diff')
        || request()->filled('tag')
        || request()->filled('language')
        || request()->filled('status')
        || request()->filled('marks_min')
        || request()->filled('marks_max')
        || request()->filled('negative_marks_min')
        || request()->filled('negative_marks_max')
        || request()->filled('has_image')
        || request()->filled('has_passage')
        || request()->filled('ai_generated');
@endphp

<div id="examAddQuestionsContent">
    <div class="row">
        <div class="col-lg-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white">
                    <div class="d-flex flex-wrap align-items-start justify-content-between gap-3">
                        <div>
                            <h4 class="card-title mb-1">Add Questions to {{ $exam->name }}</h4>
                            <div class="small">
                                <span class="text-muted js-exam-add-count">Showing {{ $questions->count() }} questions{{ $questions->hasMorePages() ? ' (more available)' : '' }}</span>
                                <span class="exam-add-loading">Updating questions...</span>
                            </div>
                        </div>
                        <a href="{{ route('exams.index') }}" class="btn el-btn-secondary">Back to Exams</a>
                    </div>
                </div>

                <div class="card-body">
                    <form id="examAddQuestionFilterForm" method="GET" action="{{ route('exams.addQuestions', $exam->id) }}" class="el-filter-bar mb-3">
                        <div class="exam-add-filter-row">
                            <div class="exam-add-group-filter">
                                <label class="form-label">Group</label>
                                <select id="examGroupFilter" name="group" class="form-select select2">
                                    <option value="">All Groups</option>
                                    @foreach($groups as $group)
                                        <option value="{{ $group->id }}" {{ request('group') == $group->id ? 'selected' : '' }}>{{ $group->group_name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="exam-add-filter-action">
                                <button class="btn el-btn-primary el-btn-icon" type="button" data-bs-toggle="collapse" data-bs-target="#advancedExamQuestionFilters" aria-expanded="{{ $advancedExamQuestionFiltersActive ? 'true' : 'false' }}" aria-controls="advancedExamQuestionFilters">
                                    <i class="ri-equalizer-line"></i> Advanced Filters
                                </button>
                            </div>
                            <div class="exam-add-filter-buttons">
                                <button type="submit" id="searchBtn" class="btn el-btn-primary">Search</button>
                                <button type="button" id="resetBtn" class="btn el-btn-secondary">Reset</button>
                            </div>
                        </div>
                        <div id="advancedExamQuestionFilters" class="collapse {{ $advancedExamQuestionFiltersActive ? 'show' : '' }}">
                        <div class="row g-2 align-items-end mt-2 border-top pt-3">
                            <div class="col-xl-2 col-md-4">
                                <label class="form-label">Category</label>
                                <select id="categoryFilter" name="category" class="form-select select2">
                                    <option value="">All Categories</option>
                                    @foreach($parentCategories as $category)
                                        <option value="{{ $category->id }}" {{ request('category') == $category->id ? 'selected' : '' }}>{{ $category->title }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-xl-2 col-md-4" data-subcategory-ui>
                                <label class="form-label">Subcategory</label>
                                <select id="subcategoryFilter" name="subcategory" class="form-select select2">
                                    <option value="">All Subcategories</option>
                                    @foreach($childCategories as $category)
                                        <option value="{{ $category->id }}" {{ request('subcategory') == $category->id ? 'selected' : '' }}>{{ $category->title }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-xl-2 col-md-4">
                                <label class="form-label">Package</label>
                                <select id="examPackageFilter" name="package" class="form-select select2">
                                    <option value="">All Packages</option>
                                    @foreach($packages as $package)
                                        <option value="{{ $package->id }}" {{ request('package') == $package->id ? 'selected' : '' }}>{{ $package->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-xl-2 col-md-4">
                                <label class="form-label">Exam</label>
                                <select id="examFilter" name="exam_filter" class="form-select select2">
                                    <option value="">All Exams</option>
                                    @foreach($exams as $filterExam)
                                        <option value="{{ $filterExam->id }}" {{ request('exam_filter') == $filterExam->id ? 'selected' : '' }}>{{ $filterExam->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-xl-2 col-md-4">
                                <label class="form-label">Subject</label>
                                <select id="subjectFilter" name="subject" class="form-select select2">
                                    <option value="">All Subjects</option>
                                    @foreach($subjects as $subject)
                                        <option value="{{ $subject->id }}" {{ request('subject') == $subject->id ? 'selected' : '' }}>{{ $subject->subject_name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-xl-2 col-md-4">
                                <label class="form-label">Topic</label>
                                <select id="topicFilter" name="topic" class="form-select select2">
                                    <option value="">All Topics</option>
                                    @foreach($topics as $topic)
                                        <option value="{{ $topic->id }}" {{ request('topic') == $topic->id ? 'selected' : '' }}>{{ $topic->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-xl-2 col-md-4">
                                <label class="form-label">Sub Topic</label>
                                <select id="subtopicFilter" name="subtopic" class="form-select select2">
                                    <option value="">All Sub Topics</option>
                                    @foreach($stopics as $stopic)
                                        <option value="{{ $stopic->id }}" {{ request('subtopic') == $stopic->id ? 'selected' : '' }}>{{ $stopic->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-xl-2 col-md-4">
                                <label class="form-label">Question Type</label>
                                <select id="qtypeFilter" name="qtype" class="form-select select2">
                                    <option value="">All Question Types</option>
                                    @foreach($qtypes as $qtype)
                                        <option value="{{ $qtype->id }}" {{ request('qtype') == $qtype->id ? 'selected' : '' }}>{{ $qtype->question_type }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-xl-2 col-md-4">
                                <label class="form-label">Difficulty</label>
                                <select id="diffFilter" name="diff" class="form-select select2">
                                    <option value="">All Difficulty Levels</option>
                                    @foreach($diffs as $diff)
                                        <option value="{{ $diff->id }}" {{ request('diff') == $diff->id ? 'selected' : '' }}>{{ $diff->diff_level }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-xl-2 col-md-4">
                                <label class="form-label">Question Tags</label>
                                <select id="tagFilter" name="tag" class="form-select select2">
                                    <option value="">All Tags</option>
                                    @foreach($questionTags as $tag)
                                        <option value="{{ $tag->id }}" {{ request('tag') == $tag->id ? 'selected' : '' }}>{{ $tag->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-xl-2 col-md-4">
                                <label class="form-label">Language</label>
                                <select id="languageFilter" name="language" class="form-select select2">
                                    <option value="">All Languages</option>
                                    @foreach($languages as $language)
                                        <option value="{{ $language->id }}" {{ request('language') == $language->id ? 'selected' : '' }}>{{ $language->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-xl-2 col-md-4">
                                <label class="form-label">Status</label>
                                <select id="statusFilter" name="status" class="form-select select2">
                                    <option value="">All Status</option>
                                    @foreach($statusOptions as $value => $label)
                                        <option value="{{ $value }}" {{ request('status') === $value ? 'selected' : '' }}>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-xl-2 col-md-4">
                                <label class="form-label">Positive Marks</label>
                                <div class="input-group">
                                    <input type="number" step="0.01" id="marksMinFilter" name="marks_min" class="form-control" placeholder="Min" value="{{ request('marks_min') }}">
                                    <input type="number" step="0.01" id="marksMaxFilter" name="marks_max" class="form-control" placeholder="Max" value="{{ request('marks_max') }}">
                                </div>
                            </div>
                            <div class="col-xl-2 col-md-4">
                                <label class="form-label">Negative Marks</label>
                                <div class="input-group">
                                    <input type="number" step="0.01" id="negativeMarksMinFilter" name="negative_marks_min" class="form-control" placeholder="Min" value="{{ request('negative_marks_min') }}">
                                    <input type="number" step="0.01" id="negativeMarksMaxFilter" name="negative_marks_max" class="form-control" placeholder="Max" value="{{ request('negative_marks_max') }}">
                                </div>
                            </div>
                            <div class="col-xl-2 col-md-4">
                                <label class="form-label">Image</label>
                                <select id="hasImageFilter" name="has_image" class="form-select select2">
                                    <option value="">Any</option>
                                    <option value="yes" {{ request('has_image') === 'yes' ? 'selected' : '' }}>Has Image</option>
                                    <option value="no" {{ request('has_image') === 'no' ? 'selected' : '' }}>No Image</option>
                                </select>
                            </div>
                            <div class="col-xl-2 col-md-4">
                                <label class="form-label">Passage</label>
                                <select id="hasPassageFilter" name="has_passage" class="form-select select2">
                                    <option value="">Any</option>
                                    <option value="yes" {{ request('has_passage') === 'yes' ? 'selected' : '' }}>Has Passage</option>
                                    <option value="no" {{ request('has_passage') === 'no' ? 'selected' : '' }}>No Passage</option>
                                </select>
                            </div>
                            <div class="col-xl-2 col-md-4">
                                <label class="form-label">AI</label>
                                <select id="aiGeneratedFilter" name="ai_generated" class="form-select select2">
                                    <option value="">Any</option>
                                    <option value="yes" {{ request('ai_generated') === 'yes' ? 'selected' : '' }}>AI Generated</option>
                                    <option value="no" {{ request('ai_generated') === 'no' ? 'selected' : '' }}>Manual</option>
                                </select>
                            </div>
                        </div>
                        </div>
                    </form>

                    <form id="bulkAddQuestionForm">
                        @csrf
                        <div class="el-table-toolbar">
                            <div class="el-table-toolbar-actions">
                                <button type="submit" class="btn el-btn-primary" id="bulkAddBtn">
                                    <i class="ri-add-line align-bottom me-1"></i> Add Selected
                                </button>
                            </div>
                            <div class="el-table-toolbar-controls">
                                <div class="el-filter-search">
                                    <div class="search-box">
                                        <input type="text" id="questionSearch" name="question" form="examAddQuestionFilterForm" class="form-control search" placeholder="Search question body" value="{{ request('question') }}">
                                        <i class="ri-search-line search-icon"></i>
                                    </div>
                                </div>
                                <div class="el-page-size">
                                    <select id="perPageSelect" name="per_page" form="examAddQuestionFilterForm" class="form-select">
                                        <option value="50" {{ $perPage == 50 ? 'selected' : '' }}>50 per page</option>
                                        <option value="100" {{ $perPage == 100 ? 'selected' : '' }}>100 per page</option>
                                        <option value="500" {{ $perPage == 500 ? 'selected' : '' }}>500 per page</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="table-responsive table-card mt-3 mb-1">
                            <table class="table align-middle el-table">
                                <thead>
                                    <tr>
                                        <th style="width: 42px;">
                                            <input type="checkbox" class="form-check-input" id="selectAllQuestions">
                                        </th>
                                        <th>No</th>
                                        <th>Marks</th>
                                        <th>Subject</th>
                                        <th>Topic</th>
                                        <th>Sub Topic</th>
                                        <th>Type</th>
                                        <th>Body of Question</th>
                                        <th>Level</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($questions as $index => $question)
                                        @php
                                            $isAttached = (bool) $question->is_attached;
                                            $rawQuestion = (string) ($question->question ?? '');
                                            $questionPreview = trim(strip_tags($rawQuestion));

                                            if ($questionPreview === '') {
                                                preg_match('/<img[^>]+src=["\']([^"\']+)["\']/i', $rawQuestion, $imageMatch);
                                                $questionPreview = $imageMatch[1] ?? trim($rawQuestion);
                                            }

                                            $questionPreview = preg_replace('/\s+/', ' ', $questionPreview ?? '') ?: 'No question text';
                                        @endphp
                                        <tr>
                                            <td>
                                                <input type="checkbox"
                                                    class="form-check-input js-question-checkbox"
                                                    name="question_ids[]"
                                                    value="{{ $question->id }}"
                                                    {{ $isAttached ? 'checked disabled' : '' }}>
                                            </td>
                                            <td>{{ $questions->firstItem() + $index }}</td>
                                            <td>{{ $question->marks }}</td>
                                            <td>{{ $question->subject?->subject_name ?? '-' }}</td>
                                            <td>{{ $question->topic?->name ?? '-' }}</td>
                                            <td>{{ $question->stopic?->name ?? '-' }}</td>
                                            <td>{{ $question->qtype?->question_type ?? '-' }}</td>
                                            <td>
                                                <span title="{{ $questionPreview }}">{{ Str::limit($questionPreview, 80) }}</span>
                                            </td>
                                            <td>{{ $question->diff?->diff_level ?? '-' }}</td>
                                            <td>
                                                @if($isAttached)
                                                    <span class="badge el-badge-primary">Added</span>
                                                @else
                                                    <span class="text-muted">Available</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="10" class="text-center text-muted py-4">No questions found matching your criteria.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </form>

                    <div class="d-flex justify-content-end mt-3">
                        {{ $questions->appends(request()->query())->links('pagination::simple-bootstrap-5') }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('script')
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/js/select2.min.js"></script>
<script src="{{ URL::asset('build/libs/sweetalert2/sweetalert2.min.js') }}"></script>

<script>
document.addEventListener('DOMContentLoaded', function () {
    let filterTimer;
    let activeRequest;
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

    function initExamAddQuestions(root) {
        root = root || document;

        if (window.jQuery && jQuery.fn.select2) {
            jQuery(root).find('.select2').each(function () {
                const $select = jQuery(this);
                if ($select.data('select2')) {
                    $select.select2('destroy');
                }
                $select.select2({ width: '100%' });
            });
        }

        const selectAll = root.querySelector('#selectAllQuestions');
        if (selectAll) {
            selectAll.addEventListener('change', function () {
                root.querySelectorAll('.js-question-checkbox:not(:disabled)').forEach(function (checkbox) {
                    checkbox.checked = selectAll.checked;
                });
            });
        }
    }

    function currentContainer() {
        return document.querySelector('#examAddQuestionsContent');
    }

    function showUpdating() {
        const container = currentContainer();
        if (container) {
            container.classList.add('exam-add-updating');
        }
    }

    function hideUpdating() {
        const container = currentContainer();
        if (container) {
            container.classList.remove('exam-add-updating');
        }
    }

    function filterUrl() {
        const form = document.querySelector('#examAddQuestionFilterForm');
        const url = new URL(form.action, window.location.origin);
        const formData = new FormData(form);
        const associatedControls = document.querySelectorAll('[form="examAddQuestionFilterForm"]');

        associatedControls.forEach(function (control) {
            if (control.name && control.value !== null && String(control.value) !== '') {
                formData.set(control.name, control.value);
            }
        });

        formData.forEach(function (value, key) {
            if (value !== null && String(value) !== '') {
                url.searchParams.set(key, value);
            }
        });

        return url;
    }

    function replaceContent(html) {
        const doc = new DOMParser().parseFromString(html, 'text/html');
        const incoming = doc.querySelector('#examAddQuestionsContent');
        const current = currentContainer();

        if (!incoming || !current) {
            return false;
        }

        current.replaceWith(incoming);
        initExamAddQuestions(incoming);
        return true;
    }

    function loadExamAddQuestions(url) {
        showUpdating();

        if (activeRequest) {
            activeRequest.abort();
        }

        activeRequest = new AbortController();

        fetch(url.toString(), {
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            signal: activeRequest.signal
        })
            .then(function (response) {
                if (!response.ok) {
                    throw new Error('Unable to refresh questions.');
                }
                return response.text();
            })
            .then(function (html) {
                if (!replaceContent(html)) {
                    window.location.href = url.toString();
                    return;
                }
                window.history.replaceState({}, '', url.toString());
            })
            .catch(function (error) {
                if (error.name !== 'AbortError') {
                    window.location.href = url.toString();
                }
            })
            .finally(function () {
                activeRequest = null;
                hideUpdating();
            });
    }

    function scheduleFilter(delay) {
        clearTimeout(filterTimer);
        filterTimer = setTimeout(function () {
            loadExamAddQuestions(filterUrl());
        }, delay);
    }

    function refreshDependentOptions(changedLevel) {
        if (changedLevel === 'group') {
            jQuery('#categoryFilter, #subcategoryFilter, #examPackageFilter, #examFilter, #subjectFilter, #topicFilter, #subtopicFilter').val('').trigger('change.select2');
        } else if (changedLevel === 'category') {
            jQuery('#subcategoryFilter, #examPackageFilter, #examFilter, #subjectFilter, #topicFilter, #subtopicFilter').val('').trigger('change.select2');
        } else if (changedLevel === 'subcategory') {
            jQuery('#examPackageFilter, #examFilter, #subjectFilter, #topicFilter, #subtopicFilter').val('').trigger('change.select2');
        } else if (changedLevel === 'package') {
            jQuery('#examFilter, #subjectFilter, #topicFilter, #subtopicFilter').val('').trigger('change.select2');
        } else if (changedLevel === 'exam') {
            jQuery('#subjectFilter, #topicFilter, #subtopicFilter').val('').trigger('change.select2');
        } else if (changedLevel === 'subject') {
            jQuery('#topicFilter, #subtopicFilter').val('').trigger('change.select2');
        } else if (changedLevel === 'topic') {
            jQuery('#subtopicFilter').val('').trigger('change.select2');
        }

        const params = new URLSearchParams({
            group_id: jQuery('#examGroupFilter').val() || '',
            category_id: jQuery('#categoryFilter').val() || '',
            subcategory_id: jQuery('#subcategoryFilter').val() || '',
            package_id: jQuery('#examPackageFilter').val() || '',
            exam_id: jQuery('#examFilter').val() || '',
            subject_id: jQuery('#subjectFilter').val() || '',
            topic_id: jQuery('#topicFilter').val() || ''
        });

        fetch(`${dependentOptionsUrl}?${params.toString()}`, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
            .then(response => response.json())
            .then(function (data) {
                updateSelectOptions('#categoryFilter', 'All Categories', data.categories || [], jQuery('#categoryFilter').val(), 'title');
                updateSelectOptions('#subcategoryFilter', 'All Subcategories', data.subcategories || [], jQuery('#subcategoryFilter').val(), 'title');
                updateSelectOptions('#examPackageFilter', 'All Packages', data.packages || [], jQuery('#examPackageFilter').val(), 'name');
                updateSelectOptions('#examFilter', 'All Exams', data.exams || [], jQuery('#examFilter').val(), 'name');
                updateSelectOptions('#subjectFilter', 'All Subjects', data.subjects || [], jQuery('#subjectFilter').val(), 'subject_name');
                updateSelectOptions('#topicFilter', 'All Topics', data.topics || [], jQuery('#topicFilter').val(), 'name');
                updateSelectOptions('#subtopicFilter', 'All Sub Topics', data.stopics || [], jQuery('#subtopicFilter').val(), 'name');
                scheduleFilter(150);
            })
            .catch(error => console.error('Dependent filter error:', error));
    }

    function handleFilterChange(target) {
        if (target.matches('#examGroupFilter')) {
            refreshDependentOptions('group');
            return;
        }

        if (target.matches('#categoryFilter')) {
            refreshDependentOptions('category');
            return;
        }

        if (target.matches('#subcategoryFilter')) {
            refreshDependentOptions('subcategory');
            return;
        }

        if (target.matches('#examPackageFilter')) {
            refreshDependentOptions('package');
            return;
        }

        if (target.matches('#examFilter')) {
            refreshDependentOptions('exam');
            return;
        }

        if (target.matches('#subjectFilter')) {
            refreshDependentOptions('subject');
            return;
        }

        if (target.matches('#topicFilter')) {
            refreshDependentOptions('topic');
            return;
        }

        scheduleFilter(150);
    }

    initExamAddQuestions(document);

    document.addEventListener('submit', function (event) {
        if (event.target.matches('#examAddQuestionFilterForm')) {
            event.preventDefault();
            loadExamAddQuestions(filterUrl());
        }
    });

    if (window.jQuery) {
        jQuery(document).on('change.examAddFilters', '#examAddQuestionFilterForm select', function () {
            handleFilterChange(this);
        });
    } else {
        document.addEventListener('change', function (event) {
            if (event.target.closest('#examAddQuestionFilterForm') && event.target.matches('select')) {
                handleFilterChange(event.target);
            }
        });
    }

    document.addEventListener('input', function (event) {
        if (event.target.matches('#questionSearch')) {
            scheduleFilter(600);
        }

        if (event.target.matches('#marksMinFilter, #marksMaxFilter, #negativeMarksMinFilter, #negativeMarksMaxFilter')) {
            scheduleFilter(600);
        }
    });

    document.addEventListener('change', function (event) {
        if (event.target.matches('#perPageSelect')) {
            scheduleFilter(150);
        }
    });

    document.addEventListener('click', function (event) {
        const resetButton = event.target.closest('#resetBtn');
        if (resetButton) {
            event.preventDefault();
            loadExamAddQuestions(new URL(document.querySelector('#examAddQuestionFilterForm').action, window.location.origin));
            return;
        }

        const pageLink = event.target.closest('#examAddQuestionsContent .pagination a');
        if (pageLink) {
            event.preventDefault();
            loadExamAddQuestions(new URL(pageLink.href));
        }
    });

    document.addEventListener('submit', function (event) {
        if (!event.target.matches('#bulkAddQuestionForm')) {
            return;
        }

        event.preventDefault();
        const selected = Array.from(document.querySelectorAll('.js-question-checkbox:not(:disabled):checked')).map(function (checkbox) {
            return checkbox.value;
        });

        if (!selected.length) {
            Swal.fire('Select questions', 'Please select at least one available question.', 'info');
            return;
        }

        const button = document.querySelector('#bulkAddBtn');
        if (button) {
            button.disabled = true;
        }

        fetch("{{ route('exams.bulkAddQuestions', $exam->id) }}", {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify({ question_ids: selected })
        })
            .then(function (response) {
                return response.json().then(function (data) {
                    if (!response.ok) {
                        throw new Error(data.message || 'Unable to add selected questions.');
                    }
                    return data;
                });
            })
            .then(function (data) {
                Swal.fire('Done', data.message || 'Questions added to the exam.', 'success');
                loadExamAddQuestions(new URL(window.location.href));
            })
            .catch(function (error) {
                Swal.fire('Error', error.message || 'Unable to add selected questions.', 'error');
            })
            .finally(function () {
                if (button) {
                    button.disabled = false;
                }
            });
    });
});
</script>
@endsection

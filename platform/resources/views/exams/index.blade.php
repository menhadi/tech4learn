@extends('layouts.master')

@section('title', 'Exams Management')

@section('content')
@component('components.breadcrumb')
@slot('li_1', 'Academic')
@slot('title', 'Exams Overview')
@endcomponent

@php
    $canAddExam = user_can_route_action('exams.create', 'add');
    $canEditExam = user_can_route_action('exams.edit', 'edit');
    $canDeleteExam = user_can_route_action('exams.destroy', 'delete');
    $advancedExamFiltersActive = request()->filled('category')
        || request()->filled('subcategory')
        || request()->filled('package')
        || request()->filled('exam')
        || request()->filled('filter');
@endphp

<style>
    .stat-card { transition: all 0.2s; border-left: 4px solid; }
    .stat-card:hover { transform: translateY(-3px); box-shadow: 0 5px 15px rgba(0,0,0,0.1); }
    .border-blue,
    .border-green { border-color: var(--vz-primary); }
    .border-red,
    .border-yellow { border-color: var(--vz-warning); }
    
    .progress-sm { height: 6px; border-radius: 3px; background-color: #e9ecef; }
    .nav-pills .nav-link.active { background-color: var(--vz-primary); }
    .exams-page {
        max-width: 100%;
        overflow-x: clip;
    }
    .exams-page #examList,
    .exams-page .table-card,
    .exams-page #examTable {
        width: 100%;
        max-width: 100%;
    }
    .exams-page #examTable {
        table-layout: auto;
    }
    .exams-page #examTable th,
    .exams-page #examTable td {
        white-space: normal;
        vertical-align: middle;
    }
    .exams-page #examTable .admin-select-column {
        width: 46px;
    }
    .exams-page #examTable th:last-child,
    .exams-page #examTable td:last-child {
        width: 110px;
    }
    .exams-page #examTable .dropdown-menu {
        max-width: min(280px, 90vw);
    }
</style>

@include('exams.partials.pdf-publication')

{{-- 1. INSIGHT CARDS (Coaching Owner View) --}}
<div class="row mb-4">
    <div class="col-xl-3 col-md-6">
        <div class="card stat-card border-blue h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <p class="fw-medium text-muted mb-0">Total Exams</p>
                        <h2 class="mt-2 ff-secondary fw-semibold">{{ $stats['total'] }}</h2>
                    </div>
                    <div class="avatar-sm flex-shrink-0">
                        <span class="avatar-title bg-primary-subtle rounded-circle fs-3 text-primary"><i class="ri-file-list-3-line"></i></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="card stat-card border-green h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <p class="fw-medium text-muted mb-0">Active Exams</p>
                        <h2 class="mt-2 ff-secondary fw-semibold">{{ $stats['active'] }}</h2>
                    </div>
                    <div class="avatar-sm flex-shrink-0">
                        <span class="avatar-title bg-success-subtle rounded-circle fs-3 text-success"><i class="ri-checkbox-circle-line"></i></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="card stat-card border-yellow h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <p class="fw-medium text-muted mb-0">Total Attempts</p>
                        <h2 class="mt-2 ff-secondary fw-semibold">{{ $stats['attempts'] }}</h2>
                    </div>
                    <div class="avatar-sm flex-shrink-0">
                        <span class="avatar-title bg-warning-subtle rounded-circle fs-3 text-warning"><i class="ri-group-line"></i></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="card stat-card border-red h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <p class="fw-medium text-muted mb-0">Global Pass Rate</p>
                        <h2 class="mt-2 ff-secondary fw-semibold">{{ $stats['pass_rate'] }}%</h2>
                    </div>
                    <div class="avatar-sm flex-shrink-0">
                        <span class="avatar-title bg-danger-subtle rounded-circle fs-3 text-danger"><i class="ri-pie-chart-line"></i></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row exams-page">
    <div class="col-lg-12">
        <div class="card">
            <div class="card-header">
                <h4 class="card-title mb-0">Exams List</h4>
                <div class="small mt-2 text-muted">
                    <span id="examFilterCount">Showing {{ $exams->count() }} of {{ $exams->total() }}</span>
                    <span id="examFilterLoading" class="d-none text-primary fw-semibold">Updating exams...</span>
                </div>
                <div class="el-filter-bar mt-3">
                    <div class="el-filter-field">
                        <select id="examGroupFilter" class="form-control select2">
                            <option value="">All Groups</option>
                            @foreach($allExamGroups as $examGroups)
                                <option value="{{ $examGroups['id'] }}" {{ (string) request('group') === (string) $examGroups['id'] ? 'selected' : '' }}>{{ $examGroups['group_name'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="el-filter-field">
                        <select id="examSortFilter" class="form-select" aria-label="Sort exams">
                            <option value="newest" {{ request('sort', 'newest') === 'newest' ? 'selected' : '' }}>Newest first</option>
                            <option value="display" {{ request('sort') === 'display' ? 'selected' : '' }}>Display order</option>
                            <option value="name" {{ request('sort') === 'name' ? 'selected' : '' }}>Name A-Z</option>
                            <option value="oldest" {{ request('sort') === 'oldest' ? 'selected' : '' }}>Oldest first</option>
                        </select>
                    </div>
                    <div class="el-filter-field el-filter-action-field">
                        <button class="btn el-btn-primary el-btn-icon" type="button" data-bs-toggle="collapse" data-bs-target="#advancedExamFilters" aria-expanded="{{ $advancedExamFiltersActive ? 'true' : 'false' }}" aria-controls="advancedExamFilters">
                            <i class="ri-equalizer-line"></i> Advanced Filters
                        </button>
                    </div>
                    <div class="el-filter-field el-filter-action-field">
                        <button id="search-btn" type="button" class="btn el-btn-primary">Search</button>
                    </div>
                    <div class="el-filter-field el-filter-action-field">
                        <button id="reset-btn" type="button" class="btn el-btn-secondary">Reset</button>
                    </div>
                </div>
                <div id="advancedExamFilters" class="collapse {{ $advancedExamFiltersActive ? 'show' : '' }}">
                    <div class="el-filter-bar mt-3 border-top pt-3">
                    <div class="el-filter-field">
                        <select id="categoryFilter" class="form-control select2">
                            <option value="">All Categories</option>
                            @foreach($parentCategories as $category)
                                <option value="{{ $category->id }}" {{ (string) $selectedCategory === (string) $category->id ? 'selected' : '' }}>{{ $category->title }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="el-filter-field" data-subcategory-ui>
                        <select id="subcategoryFilter" class="form-control select2">
                            <option value="">All Subcategories</option>
                            @foreach($childCategories as $category)
                                <option value="{{ $category->id }}" {{ (string) $selectedSubcategory === (string) $category->id ? 'selected' : '' }}>{{ $category->title }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="el-filter-field">
                        <select id="examPackageFilter" class="form-control select2">
                            <option value="">All Packages</option>
                            @foreach($allExamPackages as $examPackages)
                                <option {{ $examPackages['id'] == $selectedPackage ? 'selected' : '' }} value="{{ $examPackages['id'] }}">{{ $examPackages['name'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="el-filter-field">
                        <select id="examFilter" class="form-control select2">
                            <option value="">All Exams</option>
                            @foreach($allExamList as $examL)
                                <option {{ $examL['id'] == $selectedExam ? 'selected' : '' }} value="{{ $examL['id'] }}">{{ $examL['name'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="el-filter-field">
                        <select id="statusFilter" class="form-select">
                            <option value="">All Status</option>
                            <option value="unpublished_pdfs" {{ request('filter') === 'unpublished_pdfs' ? 'selected' : '' }}>Unpublished PDF papers</option>
                            <option value="active" {{ request('filter') === 'active' ? 'selected' : '' }}>Active</option>
                            <option value="upcoming" {{ request('filter') === 'upcoming' ? 'selected' : '' }}>Upcoming</option>
                        </select>
                    </div>
                    </div>
                </div>
            </div>

            <div class="card-body">
                <div id="examList" data-result-count="Showing {{ $exams->count() }} of {{ $exams->total() }}">
                    <div class="el-table-toolbar">
                        <div class="el-table-toolbar-actions">
                            @if($canAddExam)
                                <a href="{{ route('exams.create') }}" class="btn el-btn-primary add-btn"><i class="ri-add-line align-bottom me-1"></i> Add</a>
                            @endif
                            @if($canEditExam)
                                <a href="{{ route('exams.bulk-editor') }}" class="btn el-btn-secondary"><i class="ri-edit-line align-bottom me-1"></i> Bulk Edit</a>
                                <x-google-sheets-button resource="exams" :filters="request()->query()" />
                            @endif

                            <a href="{{ route('exams.import.index') }}#export-exams" class="btn el-btn-secondary"><i class="ri-file-excel-2-line me-1"></i> Export Exams</a>
                            <a href="{{ route('exams.import.index') }}#import-exams" class="btn el-btn-primary"><i class="ri-upload-2-line me-1"></i> Import Exams</a>
                        </div>
                        <div class="el-table-toolbar-controls">
                            <div class="el-filter-search">
                                <div class="search-box">
                                    <input type="text" id="search-input" class="form-control search" placeholder="Search exam name..." value="{{ request('search') }}">
                                    <i class="ri-search-line search-icon"></i>
                                </div>
                            </div>
                            <div class="el-page-size">
                                <select id="per-page-select" class="form-select" aria-label="Rows per page">
                                    <option value="50" {{ $perPage == 50 ? 'selected' : '' }}>50 per page</option>
                                    <option value="100" {{ $perPage == 100 ? 'selected' : '' }}>100 per page</option>
                                    <option value="500" {{ $perPage == 500 ? 'selected' : '' }}>500 per page</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="table-responsive table-card mb-1">
                        <table class="table align-middle el-table" id="examTable">
                        <thead class="table-light text-muted">
                            <tr>
                                <th>Exam Name</th>
                                <th>Groups</th>
                                <th>Display Order</th>
                                <th>Success Rate</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($exams as $exam)
                            <tr data-record-id="{{ $exam->id }}"
                                data-delete-protected="{{ (!$canDeleteExam || $exam->results_count > 0) ? '1' : '0' }}"
                                data-delete-protected-reason="{{ !$canDeleteExam ? 'You do not have permission to delete exams.' : 'Cannot delete this exam because results already exist.' }}">
                                <td>
                                    @if($canEditExam && $exam->qualitySources()->where('is_active', true)->whereIn('role', ['questions', 'combined'])->exists())
                                    <input type="checkbox" class="form-check-input me-2" form="pdf-publication" name="exam_ids[]" value="{{ $exam->id }}" aria-label="Select {{ $exam->name }} for PDF publication">
                                    @endif
                                    <h5 class="fs-14 mb-1"><a href="{{ route('exams.view', $exam->id) }}" class="text-dark">{{ $exam->name }}</a></h5>
                                    <p class="text-muted mb-0 fs-12">
                                        {{ $exam->questions_count ?? 0 }} Questions
                                        <span class="mx-1">•</span>
                                        {{ (int) $exam->duration > 0 ? $exam->duration.' min' : 'Unlimited time' }}
                                        <span class="mx-1">•</span>
                                        <span id="attempt-limit-{{ $exam->id }}">{{ (int) $exam->attempt_count > 0 ? $exam->attempt_count.' attempts/student' : 'Unlimited attempts' }}</span>
                                    </p>
                                </td>
                                <td>
                                    @if($exam->groups->isNotEmpty())
                                        <span class="badge bg-light text-body border">{{ Str::limit($exam->groups->pluck('group_name')->join(', '), 20) }}</span>
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>
                                <td>{{ $exam->display_order ?: '-' }}</td>
                                <td style="width: 150px;">
                                    @php
                                        $passRate = $exam->results_count > 0 ? round(($exam->passed_count / $exam->results_count) * 100) : 0;
                                        $barColor = $passRate >= 70 ? 'bg-success' : ($passRate >= 40 ? 'bg-warning' : 'bg-danger');
                                    @endphp
                                    <div class="d-flex align-items-center">
                                        <div class="flex-grow-1 progress progress-sm animated-progess">
                                            <div class="progress-bar {{ $barColor }}" role="progressbar" style="width: {{ $passRate }}%"></div>
                                        </div>
                                        <span class="flex-shrink-0 ms-2 fs-12 fw-medium">{{ $passRate }}%</span>
                                    </div>
                                </td>
                                
                                {{-- Status --}}
                                <td>
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" role="switch" id="switch{{ $exam->id }}" 
                                            {{ $canEditExam ? '' : 'disabled' }}
                                            onclick="toggleStatus({{ $exam->id }})" {{ $exam->status == 'Active' ? 'checked' : '' }}>
                                        <label class="form-check-label" for="switch{{ $exam->id }}">{{ $exam->status }}</label>
                                    </div>
                                </td>

                                <td>
                                    <div class="dropdown">
                                        <button class="btn btn-soft-secondary btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                            <i class="ri-more-fill"></i>
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-end">
                                            {{-- ✅ ANALYTICS BUTTON ADDED HERE --}}
                                            <li><a class="dropdown-item" href="{{ route('exams.analytics', $exam->id) }}"><i class="ri-bar-chart-grouped-line align-bottom me-2 text-warning"></i> Batch Analytics</a></li>
                                            
                                            <li><a class="dropdown-item" href="{{ route('exams.view', $exam->id) }}"><i class="ri-eye-fill align-bottom me-2 text-muted"></i> View Details</a></li>
                                            <li><a class="dropdown-item" href="{{ route('exams.viewQuestions', $exam->id) }}"><i class="ri-question-answer-line align-bottom me-2 text-muted"></i> View Questions</a></li>
                                            <li><a class="dropdown-item" href="{{ route('exams.paper.preview', $exam->id) }}" target="_blank"><i class="ri-file-paper-2-line align-bottom me-2 text-muted"></i> View complete paper</a></li>
                                            @if($canEditExam)<li><a class="dropdown-item" href="{{ route('exams.paper.edit', $exam->id) }}"><i class="ri-edit-box-line align-bottom me-2 text-muted"></i> Edit complete paper</a></li>@endif
                                            <li><a class="dropdown-item adminDownloadPdfBtn" href="{{ route('exam.print.download', ['id' => $exam->slug ?: $exam->id, 'package' => $exam->packages->first()?->slug ?: $exam->packages->first()?->id]) }}" data-pdf-url="{{ route('exam.print.download', ['id' => $exam->slug ?: $exam->id, 'package' => $exam->packages->first()?->slug ?: $exam->packages->first()?->id]) }}" data-exam-name="{{ $exam->name }}"><i class="ri-download-2-line align-bottom me-2 text-secondary"></i> Paper PDF</a></li>
                                            <li><a class="dropdown-item adminDownloadPdfBtn" href="{{ route('exams.solutionPdf', ['exam' => $exam->slug ?: $exam->id, 'package' => $exam->packages->first()?->slug ?: $exam->packages->first()?->id]) }}" data-pdf-url="{{ route('exams.solutionPdf', ['exam' => $exam->slug ?: $exam->id, 'package' => $exam->packages->first()?->slug ?: $exam->packages->first()?->id]) }}" data-exam-name="{{ $exam->name }}-solutions"><i class="ri-download-2-line align-bottom me-2 text-secondary"></i> Solution PDF</a></li>
                                            
                                            {{-- Publish/Hide Result --}}
                                            @if($canEditExam)
                                                <li>
                                                    <form action="{{ route('exams.toggleResult', $exam->id) }}" method="POST">
                                                        @csrf
                                                        <button type="submit" class="dropdown-item">
                                                            @if($exam->result_after_finish == 0)
                                                                <i class="ri-checkbox-circle-line align-bottom me-2 text-success"></i> Publish Result
                                                            @else
                                                                <i class="ri-close-circle-line align-bottom me-2 text-warning"></i> Hide Result
                                                            @endif
                                                        </button>
                                                    </form>
                                                </li>
                                            @endif
                                            
                                            @if($canEditExam || $canDeleteExam)
                                                <li class="dropdown-divider"></li>
                                            @endif
                                            
                                            @if($canEditExam)
                                                <li><button type="button" class="dropdown-item setAttemptLimitBtn" data-url="{{ route('exams.updateAttemptLimit', $exam->id) }}" data-current="{{ (int) $exam->attempt_count }}" data-target="#attempt-limit-{{ $exam->id }}"><i class="ri-repeat-line align-bottom me-2 text-muted"></i> Set Attempt Limit</button></li>
                                                <li><a class="dropdown-item" href="{{ route('exams.edit', $exam->id) }}"><i class="ri-pencil-fill align-bottom me-2 text-muted"></i> Edit</a></li>
                                                <li><a class="dropdown-item" href="{{ route('exams.addQuestions', $exam->id) }}"><i class="ri-add-box-line align-bottom me-2 text-muted"></i> Manage Questions</a></li>
                                                @if(($exam->timer_mode === 'subject' || (!$exam->timer_mode && $exam->is_subject_timer)) && $exam->duration > 0)
                                                    <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#setSectionWiseTimerModal" data-id="{{ $exam->id }}"><i class="ri-timer-flash-line align-bottom me-2 text-muted"></i> Subject Timer</a></li>
                                                @endif
                                            @endif

                                            @if($canEditExam && $canDeleteExam)
                                                <li class="dropdown-divider"></li>
                                            @endif

                                            @if($canDeleteExam)
                                                @if($exam->status != 'Active' || $exam->results_count == 0)
                                                    <li><button class="dropdown-item text-danger" data-bs-toggle="modal" data-bs-target="#deleteRecordModal" data-id="{{ $exam->id }}"><i class="ri-delete-bin-fill align-bottom me-2"></i> Delete</button></li>
                                                @else
                                                    <li><span class="dropdown-item text-muted small" title="Deactivate the exam or ensure it has no results before deletion"><i class="ri-shield-lock-line align-bottom me-2"></i> Delete Protected</span></li>
                                                @endif
                                            @endif
                                        </ul>
                                    </div>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                        </table>
                        <div class="d-flex justify-content-end mt-3">
                            {{-- $exams->links('pagination::bootstrap-5') --}}
                            {{ $exams->appends(request()->query())->links('pagination::bootstrap-5') }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@if($canEditExam)
<div class="modal fade" id="setSectionWiseTimerModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Set Subject-wise Timer</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="sectionWiseTimerForm" action="" method="POST">
                    @csrf
                    <input type="hidden" id="examId" name="exam_id" value="">
                    <div class="row"><div class="col-md-12"><div id="subjectDurations" class="row"></div></div></div>
                    <div class="d-flex justify-content-end"><button type="button" class="btn el-btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn el-btn-primary ms-2">Save</button></div>
                </form>
            </div>
        </div>
    </div>
</div>
@endif

@if($canDeleteExam)
<div class="modal fade zoomIn" id="deleteRecordModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header"><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" id="btn-close"></button></div>
            <div class="modal-body">
                <div class="mt-2 text-center">
                    <lord-icon src="https://cdn.lordicon.com/gsqxdxog.json" trigger="loop" style="width:100px;height:100px"></lord-icon>
                    <div class="mt-4 pt-2 fs-15 mx-4 mx-sm-5"><h4>Are you Sure ?</h4><p class="text-muted mx-4 mb-0">Are you Sure You want to Remove this Record ?</p></div>
                </div>
                <div class="d-flex gap-2 justify-content-center mt-4 mb-2"><button type="button" class="btn w-sm btn-light" data-bs-dismiss="modal">Close</button><form id="delete-form" method="POST" action="">@csrf @method('DELETE')<button type="submit" class="btn w-sm el-btn-danger">Yes, Delete It!</button></form></div>
            </div>
        </div>
    </div>
</div>
@endif

@endsection

@section('script')
<script>
    $(document).on('click', '.setAttemptLimitBtn', async function (event) {
        event.preventDefault();
        event.stopPropagation();

        const button = this;
        const result = await Swal.fire({
            title: 'Set attempt limit',
            text: 'Maximum attempts allowed per student. Enter 0 for unlimited attempts.',
            input: 'number',
            inputValue: Number(button.dataset.current || 0),
            inputAttributes: { min: 0, step: 1 },
            showCancelButton: true,
            confirmButtonText: 'Save limit',
            confirmButtonColor: '#0f7f75',
            inputValidator: function (value) {
                if (value === '' || Number(value) < 0 || !Number.isInteger(Number(value))) {
                    return 'Enter a whole number of 0 or greater.';
                }
            }
        });

        if (!result.isConfirmed) {
            return false;
        }

        try {
            const response = await fetch(button.dataset.url, {
                method: 'PATCH',
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                },
                body: JSON.stringify({ attempt_count: Number(result.value) })
            });
            const payload = await response.json();

            if (!response.ok || !payload.success) {
                throw new Error(payload.message || 'The attempt limit could not be saved.');
            }

            button.dataset.current = payload.attempt_count;
            document.querySelector(button.dataset.target).textContent = payload.label;

            Swal.fire({
                icon: 'success',
                title: 'Attempt limit saved',
                text: payload.label,
                timer: 1600,
                showConfirmButton: false
            });
        } catch (error) {
            Swal.fire({
                icon: 'error',
                title: 'Save failed',
                text: error.message || 'The attempt limit could not be saved.'
            });
        }

        return false;
    });
    $(document).on('click', '.adminDownloadPdfBtn', async function (event) {
        event.preventDefault();
        event.stopPropagation();

        const $button = $(this);
        if ($button.data('busy')) {
            return false;
        }

        const pdfUrl = $button.data('pdf-url');
        const originalHtml = $button.html();
        $button.data('busy', true).addClass('disabled')
            .html('<span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>Preparing PDF');

        Swal.fire({
            title: 'Downloading your PDF',
            html: 'Please wait while we are downloading your PDF.<br><small class="text-muted">Large question papers may take a little longer.</small>',
            allowOutsideClick: false,
            allowEscapeKey: false,
            showConfirmButton: false,
            didOpen: function () {
                Swal.showLoading();
            }
        });

        try {
            const response = await fetch(pdfUrl, {
                method: 'GET',
                credentials: 'same-origin',
                headers: { 'Accept': 'application/pdf' }
            });
            const contentType = response.headers.get('content-type') || '';

            if (!response.ok || !contentType.includes('application/pdf')) {
                throw new Error('The server did not return a PDF file.');
            }

            const blob = await response.blob();
            const objectUrl = URL.createObjectURL(blob);
            const disposition = response.headers.get('content-disposition') || '';
            const filenameMatch = disposition.match(/filename\*?=(?:UTF-8''|["'])?([^"';]+)/i);
            const fallbackName = (($button.data('exam-name') || 'exam-paper') + '.pdf')
                .replace(/[^a-z0-9._-]+/gi, '-');
            const filename = filenameMatch ? decodeURIComponent(filenameMatch[1].trim()) : fallbackName;
            const link = document.createElement('a');

            link.href = objectUrl;
            link.download = filename;
            document.body.appendChild(link);
            link.click();
            link.remove();
            setTimeout(function () { URL.revokeObjectURL(objectUrl); }, 30000);

            Swal.fire({
                icon: 'success',
                title: 'PDF downloaded',
                text: 'Your PDF has been downloaded successfully.',
                timer: 1800,
                showConfirmButton: false
            });
        } catch (error) {
            Swal.fire({
                icon: 'error',
                title: 'Download failed',
                text: 'The PDF could not be prepared. Please try again.'
            });
        } finally {
            $button.data('busy', false).removeClass('disabled').html(originalHtml);
        }

        return false;
    });

    document.addEventListener('DOMContentLoaded', function() {

        $('.select2').select2();
        let filterTimer;
        let bootstrappingFilters = true;

        function scheduleFilters() {
            if (bootstrappingFilters) {
                return;
            }

            clearTimeout(filterTimer);
            filterTimer = setTimeout(applyFilters, 500);
        }

        function applyFilters() {
            const group = $('#examGroupFilter').val();
            const category = $('#categoryFilter').val();
            const subcategory = $('#subcategoryFilter').val();
            const packageId = $('#examPackageFilter').val();
            const exam = $('#examFilter').val();
            const status = $('#statusFilter').val();
            const sort = $('#examSortFilter').val();
            const search = $('#search-input').val();
            const perPage = $('#per-page-select').val();

            const url = new URL(window.location.href.split('?')[0]); // Base URL without old params
            if (group) url.searchParams.set('group', group);
            if (category) url.searchParams.set('category', category);
            if (subcategory) url.searchParams.set('subcategory', subcategory);
            if (packageId) url.searchParams.set('package', packageId);
            if (exam) url.searchParams.set('exam', exam);
            if (status) url.searchParams.set('filter', status);
            if (sort && sort !== 'newest') url.searchParams.set('sort', sort);
            if (search) url.searchParams.set('search', search);
            if (perPage) url.searchParams.set('per_page', perPage);
            
            $('#examFilterCount').addClass('d-none');
            $('#examFilterLoading').removeClass('d-none');
            window.ExamLiteAjaxFilter.loadUrl(url, ['#examList'], document.querySelector('#examList'));
        }

        // Get query params
        const urlParams = new URLSearchParams(window.location.search);

        const selectedGroup = urlParams.get('group');
        const selectedCategory = urlParams.get('category');
        const selectedSubcategory = urlParams.get('subcategory');
        const selectedPackage = urlParams.get('package');
        const selectedExam = urlParams.get('exam');
        const selectedStatus = urlParams.get('filter');
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
                $('#categoryFilter, #subcategoryFilter, #examPackageFilter, #examFilter').val('').trigger('change.select2');
            } else if (changedLevel === 'category') {
                $('#subcategoryFilter, #examPackageFilter, #examFilter').val('').trigger('change.select2');
            } else if (changedLevel === 'subcategory') {
                $('#examPackageFilter, #examFilter').val('').trigger('change.select2');
            } else if (changedLevel === 'package') {
                $('#examFilter').val('').trigger('change.select2');
            }

            const params = new URLSearchParams({
                group_id: $('#examGroupFilter').val() || '',
                category_id: $('#categoryFilter').val() || '',
                subcategory_id: $('#subcategoryFilter').val() || '',
                package_id: $('#examPackageFilter').val() || ''
            });

            fetch(`${dependentOptionsUrl}?${params.toString()}`, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
                .then(response => response.json())
                .then(function (data) {
                    updateSelectOptions('#categoryFilter', 'All Categories', data.categories || [], $('#categoryFilter').val(), 'title');
                    updateSelectOptions('#subcategoryFilter', 'All Subcategories', data.subcategories || [], $('#subcategoryFilter').val(), 'title');
                    updateSelectOptions('#examPackageFilter', 'All Packages', data.packages || [], $('#examPackageFilter').val(), 'name');
                    updateSelectOptions('#examFilter', 'All Exams', data.exams || [], $('#examFilter').val(), 'name');
                    scheduleFilters();
                })
                .catch(error => console.error('Dependent filter error:', error));
        }

        // Set selected group
        if (selectedGroup) {
            $('#examGroupFilter').val(selectedGroup).trigger('change.select2');
        }
        if (selectedCategory) {
            $('#categoryFilter').val(selectedCategory).trigger('change.select2');
        }
        if (selectedSubcategory) {
            $('#subcategoryFilter').val(selectedSubcategory).trigger('change.select2');
        }
        if (selectedStatus) {
            $('#statusFilter').val(selectedStatus);
        }

        setTimeout(function() {
            bootstrappingFilters = false;
        }, 900);

        $('#examGroupFilter').on('change', function () {
            refreshDependentOptions('group');
        });

        $('#categoryFilter').on('change', function () {
            refreshDependentOptions('category');
        });

        $('#subcategoryFilter').on('change', function () {
            refreshDependentOptions('subcategory');
        });

        $("#examPackageFilter").on('change', function() {
            refreshDependentOptions('package');
        });

        // Search button click
        $('#search-btn').on('click', function() {
            applyFilters();
        });

        // Search on Enter key press
        $(document).on('keyup', '#examList #search-input', function(e) {
            if (e.key === 'Enter') {
                applyFilters();
            }
        });

        $(document).on('input', '#examList #search-input', scheduleFilters);
        $('#examFilter, #statusFilter, #examSortFilter').on('change', scheduleFilters);
        
        // The table toolbar is replaced after each AJAX request, so keep this delegated.
        $(document).on('change', '#examList #per-page-select', function() {
            applyFilters();
        });

        // Reset button click
        $('#reset-btn').on('click', function() {
            const url = new URL(window.location.href.split('?')[0]);
            $('#examGroupFilter, #categoryFilter, #subcategoryFilter, #examPackageFilter, #examFilter, #statusFilter').val('').trigger('change.select2');
            $('#examSortFilter').val('newest');
            $('#examFilterCount').addClass('d-none');
            $('#examFilterLoading').removeClass('d-none');
            window.ExamLiteAjaxFilter.loadUrl(url, ['#examList'], document.querySelector('#examList'));
        });

        $(document).on('click', '#examList .pagination a', function(event) {
            event.preventDefault();
            $('#examFilterCount').addClass('d-none');
            $('#examFilterLoading').removeClass('d-none');
            window.ExamLiteAjaxFilter.loadUrl(new URL(this.href), ['#examList'], document.querySelector('#examList'));
        });

        document.addEventListener('examelite:ajax-filtered', function () {
            $('#examFilterLoading').addClass('d-none');
            $('#examFilterCount').text($('#examList').attr('data-result-count') || '').removeClass('d-none');
        });

    });

    function toggleStatus(examId) {
        if (!@json($canEditExam)) {
            return;
        }

        fetch('{{ url('exams') }}/' + examId + '/toggle-status', {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Content-Type': 'application/json' }
        }).then(response => response.json()).then(data => {
            if (!data.success) {
                if (window.Swal) {
                    Swal.fire('Error', 'Failed to toggle status.', 'error');
                } else {
                    alert('Failed to toggle status');
                }
            }
        }).catch(error => console.error('Error:', error));
    }
    
    var deleteRecordModal = document.getElementById('deleteRecordModal');
    if (deleteRecordModal) {
        deleteRecordModal.addEventListener('show.bs.modal', function(event) {
            var button = event.relatedTarget;
            var id = button.getAttribute('data-id');
            var action = "{{ route('exams.destroy', ':id') }}".replace(':id', id);
            document.getElementById('delete-form').setAttribute('action', action);
        });
    }

    // Section Timer Logic (Retained)
    var sectionWiseTimerModal = document.getElementById('setSectionWiseTimerModal');
    if (sectionWiseTimerModal) {
        sectionWiseTimerModal.addEventListener('show.bs.modal', function(event) {
            var button = event.relatedTarget;
            var id = button.getAttribute('data-id');
            document.getElementById('examId').value = id;
            var formAction = "{{ route('exams.setSectionWiseTimer', ':id') }}".replace(':id', id);
            document.getElementById('sectionWiseTimerForm').setAttribute('action', formAction);
            var subjectDurations = document.getElementById('subjectDurations');
            subjectDurations.innerHTML = '<div class="text-center p-3"><i class="mdi mdi-loading mdi-spin fs-2"></i><p>Loading subjects...</p></div>';

            fetch('{{ url('exams') }}/' + id + '/subjects').then(response => response.json()).then(data => {
                subjectDurations.innerHTML = '';
                if (data.subjects.length === 0) { subjectDurations.innerHTML = '<div class="alert alert-warning col-12">No subjects found. Add questions first.</div>'; return; }
                data.subjects.forEach(subject => {
                    var duration = data.saved_durations[subject.id] !== undefined ? data.saved_durations[subject.id] : data.default_split;
                    var durationInput = document.createElement('div');
                    durationInput.className = 'col-md-6 mb-3';
                    durationInput.innerHTML = `<input type="hidden" name="subject_ids[]" value="${subject.id}"><label class="form-label"><strong>${subject.subject_name}</strong></label><div class="input-group"><input type="number" class="form-control" name="durations[]" min="1" value="${duration}" required><span class="input-group-text">mins</span></div>`;
                    subjectDurations.appendChild(durationInput);
                });
            }).catch(error => { console.error('Error:', error); subjectDurations.innerHTML = '<div class="text-danger p-3">Error loading data.</div>'; });
        });
    }
</script>
@endsection

@extends('layouts.master')

@section('title', $reportType === 'study_cards' ? 'Reported Study Cards' : 'Reported Questions')

@section('content')
@component('components.breadcrumb')
@slot('li_1', 'Academic')
@slot('title', $reportType === 'study_cards' ? 'Reported Study Cards' : 'Reported Questions')
@endcomponent

<style>
    .stat-card { transition: all 0.2s; border-left: 4px solid; }
    .stat-card:hover { transform: translateY(-3px); box-shadow: 0 5px 15px rgba(0,0,0,0.1); }
    .border-blue,
    .border-green { border-color: var(--vz-primary); }
    .border-red,
    .border-yellow { border-color: var(--vz-warning); }
    
    .progress-sm { height: 6px; border-radius: 3px; background-color: #e9ecef; }
    .nav-pills .nav-link.active { background-color: var(--vz-primary); }

    .question-reports-page,
    .question-reports-page .card,
    .question-reports-page .card-body,
    .question-reports-page #reportList {
        max-width: 100%;
        overflow-x: hidden;
    }

    .question-reports-page .report-table-scroll {
        width: 100%;
        max-width: 100%;
        overflow-x: auto;
        overflow-y: visible;
        -webkit-overflow-scrolling: touch;
    }

    .question-reports-page .el-report-table {
        width: 100%;
        min-width: 1360px;
        table-layout: fixed;
    }

    .question-reports-page .el-report-table th,
    .question-reports-page .el-report-table td {
        white-space: normal;
        vertical-align: middle;
    }

    .question-reports-page .el-cell-wrap {
        overflow-wrap: anywhere;
        word-break: break-word;
    }

    .question-reports-page .col-student { width: 150px; }
    .question-reports-page .col-email { width: 190px; }
    .question-reports-page .col-exam { width: 210px; }
    .question-reports-page .col-question { width: 140px; }
    .question-reports-page .col-subject { width: 150px; }
    .question-reports-page .col-issue { width: 150px; }
    .question-reports-page .col-message { width: 170px; }
    .question-reports-page .col-date { width: 120px; }
    .question-reports-page .col-status { width: 160px; }

    .question-reports-page .col-date,
    .question-reports-page .col-status,
    .question-reports-page .col-date *,
    .question-reports-page .col-status * {
        overflow-wrap: normal;
        white-space: nowrap;
        word-break: normal;
    }

    .question-reports-page .reportStatus {
        min-width: 118px;
    }


    @media (max-width: 1199.98px) {
        .question-reports-page .el-report-table {
            min-width: 1360px;
        }
    }
</style>

{{-- 1. INSIGHT CARDS (Coaching Owner View) --}}
<div class="row mb-4">
    <div class="col-xl-3 col-md-6">
        <div class="card stat-card border-blue h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <p class="fw-medium text-muted mb-0">{{ $reportType === 'study_cards' ? 'Reported Study Cards' : 'Reported Questions' }}</p>
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
                        <p class="fw-medium text-muted mb-0">Pending</p>
                        <h2 class="mt-2 ff-secondary fw-semibold">{{ $stats['pending'] }}</h2>
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
                        <p class="fw-medium text-muted mb-0">Resolved</p>
                        <h2 class="mt-2 ff-secondary fw-semibold">{{ $stats['resolved'] }}</h2>
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
                        <p class="fw-medium text-muted mb-0">Closed</p>
                        <h2 class="mt-2 ff-secondary fw-semibold">{{ $stats['closed'] }}</h2>
                    </div>
                    <div class="avatar-sm flex-shrink-0">
                        <span class="avatar-title bg-danger-subtle rounded-circle fs-3 text-danger"><i class="ri-pie-chart-line"></i></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row question-reports-page">
    <div class="col-lg-12">
        <div class="card">
            <!-- <div class="card-header border-bottom-0">
                <div class="d-flex align-items-center">
                    <h5 class="card-title mb-0 flex-grow-1">Question Reported</h5>
                </div>
            </div> -->
            
            <!-- <div class="card-body border-bottom-dashed border-bottom">
                <form method="GET" action="{{ route('exams.reports') }}">
                    <div class="row g-3">
                        <div class="col-xl-6">
                            <div class="search-box">
                                <input type="text" name="search" class="form-control search" placeholder="Search exam name..." value="{{ request('search') }}">
                                <i class="ri-search-line search-icon"></i>
                            </div>
                        </div>
                        <div class="col-xl-6">
                            <div class="row g-3">
                                <div class="col-sm-4">
                                    <ul class="nav nav-pills nav-custom-s" role="tablist">
                                        <li class="nav-item"><a class="nav-link {{ !request('filter') ? 'active' : '' }} py-2" href="{{ route('exams.reports') }}">All</a></li>
                                        <li class="nav-item"><a class="nav-link {{ request('filter') == 'pending' ? 'active' : '' }} py-2" href="{{ route('exams.reports', ['filter' => 'pending']) }}">Pending</a></li>
                                        <li class="nav-item"><a class="nav-link {{ request('filter') == 'resolved' ? 'active' : '' }} py-2" href="{{ route('exams.reports', ['filter' => 'resolved']) }}">Resolved</a></li>
                                        <li class="nav-item"><a class="nav-link {{ request('filter') == 'rejected' ? 'active' : '' }} py-2" href="{{ route('exams.reports', ['filter' => 'rejected']) }}">Rejected</a></li>
                                    </ul>
                                </div>
                                <div class="col-sm-2 ms-auto">
                                    <button type="submit" class="btn el-btn-primary w-100">Filter</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div> -->

            <div class="card-header">
                <h4 class="card-title mb-0">{{ $reportType === 'study_cards' ? 'Reported Study Cards' : 'Reported Questions' }}</h4>
                <div class="el-filter-bar mt-3">
                    @if($reportType === 'questions')
                    <div class="el-filter-field">
                        <select id="examFilter" class="form-control select2">
                            <option value="">All Exams</option>
                            @foreach($exams as $exam)
                                <option
                                    value="{{ $exam['id'] }}"
                                    {{ request()->get('exam') == $exam['id'] ? 'selected' : '' }}
                                >
                                    {{ $exam['name'] }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    @endif
                    <div class="el-filter-field">
                        <select id="statusFilter" class="form-control select2">
                            <option value="">Active reports</option>
                            <option {{ request()->get('status') == 'all' ? 'selected' : '' }} value="all">All statuses</option>
                            <option {{ request()->get('status') == 'Pending' ? 'selected' : '' }} value="Pending">Pending</option>
                            <option {{ request()->get('status') == 'In Progress' ? 'selected' : '' }} value="In Progress">In Progress</option>
                            <option {{ request()->get('status') == 'On Hold' ? 'selected' : '' }} value="On Hold">On Hold</option>
                            <option {{ request()->get('status') == 'Resolved' ? 'selected' : '' }} value="Resolved">Resolved</option>
                            <option {{ request()->get('status') == 'Closed' ? 'selected' : '' }} value="Closed">Closed</option>
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
                <div id="reportList">
                    <div class="el-table-toolbar">
                        <div class="el-table-toolbar-actions"></div>
                        <div class="el-table-toolbar-controls">
                            <div class="el-filter-search">
                                <div class="search-box">
                                    <input type="text" id="search-input" class="form-control search"
                                        placeholder="{{ $reportType === 'study_cards' ? 'Search study card reports...' : 'Search question reports...' }}" value="{{ request('search') }}">
                                    <i class="ri-search-line search-icon"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="table-responsive table-card report-table-scroll mb-1">
                        <table class="table align-middle el-table el-report-table" id="examTable">
                        <thead class="table-light text-muted">
                            <tr>
                                <th class="col-student">Student Name</th>
                                <th class="col-email">Student Email</th>
                                <th class="col-exam">{{ $reportType === 'study_cards' ? 'Package / Study Set' : 'Exam' }}</th>
                                <th class="col-question">{{ $reportType === 'study_cards' ? 'Study Card' : 'Question' }}</th>
                                <th class="col-subject">Subject</th>
                                <th class="col-issue">Issue Type</th>
                                <th class="col-message">Message</th>
                                <th class="col-date">Date</th>
                                <th class="col-status">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($reports as $report)
                            @php
                                $displayText = function ($value, $fallback = '-') {
                                    if (blank($value)) {
                                        return $fallback;
                                    }

                                    if (is_array($value)) {
                                        return $value[app()->getLocale()]
                                            ?? $value['en']
                                            ?? collect($value)->filter()->first()
                                            ?? $fallback;
                                    }

                                    if (is_string($value)) {
                                        $decoded = json_decode($value, true);
                                        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                                            return $decoded[app()->getLocale()]
                                                ?? $decoded['en']
                                                ?? collect($decoded)->filter()->first()
                                                ?? $fallback;
                                        }
                                    }

                                    return $value;
                                };
                                $isStudyCardReport = ($report->report_source ?? null) === 'flashcard' || $report->flashcard_id;
                                $itemUrl = null;
                                if ($isStudyCardReport && $report->flashcard_id && $report->flashcard_set_id) {
                                    $itemUrl = route('flashcards.cards.edit', [$report->flashcard_set_id, $report->flashcard_id]);
                                } elseif ($report->question_id) {
                                    $itemUrl = route('questions.edit', $report->question_id);
                                }
                                $studentName = $report->student->name ?? $report->guest_name ?? 'Guest';
                                $studentEmail = $report->student->email ?? $report->guest_email ?? '-';
                                $subjectName = $report->subject->subject_name ?? $report->flashcardSet?->subject?->subject_name ?? '-';
                                $contextName = $isStudyCardReport
                                    ? ($report->package_name ?: $report->flashcardSet?->package?->name ?: $report->flashcard_set_title ?: 'Study Card')
                                    : ($report->exam_name ?: '-');
                                $contextName = $displayText($contextName);
                            @endphp
                            <tr>
                                <td class="el-cell-wrap col-student">
                                    <span class="fw-medium">{{ $studentName }}</span>
                                </td>
                                <td class="el-cell-wrap col-email">
                                    {{ $studentEmail }}
                                </td>
                                <td class="el-cell-wrap col-exam">
                                    {{ $contextName }}
                                </td>
                                <td class="col-question">
                                    @if($itemUrl)
                                        <a target="_blank" href="{{ $itemUrl }}">{{ $isStudyCardReport ? 'View Study Card' : 'View Question' }}</a>
                                    @else
                                        -
                                    @endif
                                </td>
                                <td class="el-cell-wrap col-subject">
                                    {{ $subjectName }}
                                </td>
                                <td class="el-cell-wrap col-issue">
                                    {{ $report->question_type ?: '-' }}
                                </td>
                                <td class="el-cell-wrap col-message">
                                    {{ $report->message ?: '-' }}
                                </td>
                                <td class="col-date">
                                    <div class="d-flex flex-column">
                                        <span class="fs-12 text-muted"><span class="text-dark">{{ $report->created_at->format('d M, Y') }}</span></span>                                        
                                    </div>
                                </td>
                                <td class="col-status">
                                    <!-- <label class="form-check-label" for="switch">{{ ucwords($report->status) }}</label> -->
                                    <select
                                        class="form-select form-select-sm reportStatus"
                                        data-id="{{ $report->id }}"
                                        data-current-status="{{ $report->status }}"
                                    >
                                        <option {{ $report->status == 'Pending' ? 'selected' : '' }} value="Pending">Pending</option>
                                        <option {{ $report->status == 'In Progress' ? 'selected' : '' }} value="In Progress">In Progress</option>
                                        <option {{ $report->status == 'On Hold' ? 'selected' : '' }} value="On Hold">On Hold</option>
                                        <option {{ $report->status == 'Resolved' ? 'selected' : '' }} value="Resolved">Resolved</option>
                                        <option {{ $report->status == 'Closed' ? 'selected' : '' }} value="Closed">Closed</option>
                                    </select>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                        </table>
                    </div>
                    <div class="d-flex justify-content-end mt-3">
                        {{ $reports->appends(request()->query())->links('pagination::bootstrap-5') }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Section Timer Modal (Unchanged mostly, just retained) --}}
<div class="modal fade" id="setSectionWiseTimerModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Set Section Wise Timer</h5>
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

{{-- Delete Modal (Unchanged) --}}
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
@endsection

@section('script')
<script src="{{ URL::asset('build/libs/prismjs/prism.js') }}"></script>
<script src="{{ URL::asset('build/libs/sweetalert2/sweetalert2.min.js') }}"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<script>
    $(document).on('focus', '.reportStatus', function () {
        $(this).data('previous', $(this).val());
    });

    $(document).on('change', '.reportStatus', function () {

        const $select = $(this);
        const previousValue = $select.data('previous');
        const newStatus = $select.val();
        const reportId = $select.data('id');

        Swal.fire({
            title: 'Update Status?',
            text: `Change status to "${newStatus}"?`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Update'
        }).then((result) => {

            if (!result.isConfirmed) {
                $select.val(previousValue);
                return;
            }

            $.ajax({
                url: `/exams/reports/${reportId}/status`,
                type: 'PATCH',
                data: {
                    status: newStatus,
                    _token: '{{ csrf_token() }}'
                },
                success: function (response) {

                    $select.data('previous', newStatus);

                    const visibleStatusFilter = new URLSearchParams(window.location.search).get('status');
                    if (!visibleStatusFilter && ['Resolved', 'Closed'].includes(newStatus)) {
                        $select.closest('tr').fadeOut(180, function () {
                            $(this).remove();
                        });
                    }

                    Swal.fire({
                        icon: 'success',
                        title: 'Success',
                        text: response.message
                    });

                },
                error: function () {

                    $select.val(previousValue);

                    Swal.fire({
                        icon: 'error',
                        title: 'Failed',
                        text: 'Could not update status.'
                    });

                }
            });

        });

    });

    document.addEventListener('DOMContentLoaded', function() {
        $('.select2').select2();

        let filterTimer;

        function scheduleFilters() {
            clearTimeout(filterTimer);
            filterTimer = setTimeout(applyFilters, 450);
        }

        function applyFilters() {
            const exam = $('#examFilter').val();
            const status = $('#statusFilter').val();
            const search = $('#search-input').val();

            const url = new URL(window.location.href.split('?')[0]); // Base URL without old params
            if (exam) url.searchParams.set('exam', exam);
            if (status) url.searchParams.set('status', status);
            if (search) url.searchParams.set('search', search);
            
            window.ExamLiteAjaxFilter.loadUrl(url, ['#reportList'], document.querySelector('#reportList'));
        }

        const urlParams = new URLSearchParams(window.location.search);
        const selectedExam = urlParams.get('exam');
        const selectedStatus = urlParams.get('status');
        const selectedSearch = urlParams.get('search');

        // Search button click
        $('#search-btn').on('click', function() {
            applyFilters();
        });

        $("#filterBtn").click(function(event) {
            applyFilters();
        });

        // Search on Enter key press
        $(document).on('keyup', '#reportList #search-input', function(e) {
            if (e.key === 'Enter') {
                applyFilters();
            }
        });

        $(document).on('input', '#reportList #search-input', scheduleFilters);
        $('#examFilter, #statusFilter').on('change', scheduleFilters);
        
        // Per-page select change
        $('#per-page-select').on('change', function() {
            applyFilters();
        });

        // Reset button click
        $('#reset-btn').on('click', function() {
            const url = new URL(window.location.href.split('?')[0]);
            $('#examFilter, #statusFilter').val('').trigger('change.select2');
            window.ExamLiteAjaxFilter.loadUrl(url, ['#reportList'], document.querySelector('#reportList'));
        });

        $(document).on('click', '#reportList .pagination a', function(event) {
            event.preventDefault();
            window.ExamLiteAjaxFilter.loadUrl(new URL(this.href), ['#reportList'], document.querySelector('#reportList'));
        });

    });

    // function toggleStatus(examId) {
    //     fetch('{{ url('exams') }}/' + examId + '/toggle-status', {
    //         method: 'POST',
    //         headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Content-Type': 'application/json' }
    //     }).then(response => response.json()).then(data => {
    //         if (data.success) {
    //             // Optional: Show toast
    //         } else { alert('Failed to toggle status'); location.reload(); }
    //     }).catch(error => console.error('Error:', error));
    // }
    
    // document.getElementById('deleteRecordModal').addEventListener('show.bs.modal', function(event) {
    //     var button = event.relatedTarget;
    //     var id = button.getAttribute('data-id');
    //     var action = "{{ route('exams.destroy', ':id') }}".replace(':id', id);
    //     document.getElementById('delete-form').setAttribute('action', action);
    // });

    // // Section Timer Logic (Retained)
    // document.getElementById('setSectionWiseTimerModal').addEventListener('show.bs.modal', function(event) {
    //     var button = event.relatedTarget;
    //     var id = button.getAttribute('data-id');
    //     document.getElementById('examId').value = id;
    //     var formAction = "{{ route('exams.setSectionWiseTimer', ':id') }}".replace(':id', id);
    //     document.getElementById('sectionWiseTimerForm').setAttribute('action', formAction);
    //     var subjectDurations = document.getElementById('subjectDurations');
    //     subjectDurations.innerHTML = '<div class="text-center p-3"><i class="mdi mdi-loading mdi-spin fs-2"></i><p>Loading subjects...</p></div>';

    //     fetch('{{ url('exams') }}/' + id + '/subjects').then(response => response.json()).then(data => {
    //         subjectDurations.innerHTML = '';
    //         if (data.subjects.length === 0) { subjectDurations.innerHTML = '<div class="alert alert-warning col-12">No subjects found. Add questions first.</div>'; return; }
    //         data.subjects.forEach(subject => {
    //             var duration = data.saved_durations[subject.id] !== undefined ? data.saved_durations[subject.id] : data.default_split;
    //             var durationInput = document.createElement('div');
    //             durationInput.className = 'col-md-6 mb-3';
    //             durationInput.innerHTML = `<input type="hidden" name="subject_ids[]" value="${subject.id}"><label class="form-label"><strong>${subject.subject_name}</strong></label><div class="input-group"><input type="number" class="form-control" name="durations[]" min="1" value="${duration}" required><span class="input-group-text">mins</span></div>`;
    //             subjectDurations.appendChild(durationInput);
    //         });
    //     }).catch(error => { console.error('Error:', error); subjectDurations.innerHTML = '<div class="text-danger p-3">Error loading data.</div>'; });
    // });
</script>

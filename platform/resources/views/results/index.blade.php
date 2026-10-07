@extends('layouts.master')

@section('title', 'Results Dashboard')

@section('css')
<style>
    :root {
        --bz-primary: var(--el-primary, var(--vz-primary, #0f766e));
        --bz-secondary: var(--el-secondary, var(--vz-warning, #f59e0b));
        --bz-light: var(--el-table-head, #eef7f5);
        --bz-card-bg: #ffffff;
        --bz-border: var(--el-border, #d7e2df);
        --bz-text-muted: var(--el-muted, #7b8497);
        --bz-tertiary: color-mix(in srgb, var(--bz-primary) 46%, var(--bz-secondary));
    }

    /* FILTER BAR DESIGN */
    .filter-card {
        border: 1px solid var(--bz-border);
        box-shadow: var(--el-shadow-sm, 0 1px 2px rgba(15, 23, 42, 0.08));
        border-radius: var(--el-radius, 4px);
        background: #fff;
        margin-bottom: 20px;
    }
    .filter-header {
        background: var(--bz-light);
        border-bottom: 1px solid var(--bz-border);
        padding: 12px 20px;
        font-weight: 600;
        color: var(--bz-primary);
        display: flex; align-items: center; gap: 10px;
    }
    .form-control, .form-select {
        border: 1px solid #dcdfe3;
        font-size: 13px;
        padding: 0.5rem 0.9rem;
        border-radius: 4px;
    }
    .form-control:focus, .form-select:focus {
        border-color: var(--bz-primary);
        box-shadow: 0 0 0 0.2rem rgba(var(--vz-primary-rgb, 15, 118, 110), 0.12);
    }
    .form-label {
        font-size: 11px;
        text-transform: uppercase;
        font-weight: 700;
        color: var(--bz-text-muted);
        letter-spacing: 0.5px;
        margin-bottom: 5px;
    }

    /* TABLE DESIGN */
    .premium-table thead th {
        background-color: var(--bz-light);
        color: var(--el-heading, #344054);
        font-weight: 600;
        text-transform: uppercase;
        font-size: 11px;
        padding: 12px 16px;
        border-bottom: 1px solid var(--bz-border);
        white-space: nowrap;
    }
    .premium-table tbody td {
        padding: 12px 16px;
        vertical-align: middle;
        border-bottom: 1px solid var(--bz-border);
        font-size: 14px;
        color: #212529;
        white-space: normal;
    }
    .premium-table tbody tr:hover {
        background-color: var(--el-row-alt, rgba(15, 118, 110, 0.04));
    }
    
    /* RANK BADGES */
    .rank-icon { font-size: 18px; margin-right: 5px; vertical-align: middle; }
    .rank-1 { color: #ffbf00; text-shadow: 0 2px 4px rgba(255, 191, 0, 0.2); font-size: 1.2rem; } /* Gold */
    .rank-2 { color: #a5a9b4; font-size: 1.1rem; } /* Silver */
    .rank-3 { color: #cd7f32; font-size: 1.1rem; } /* Bronze */
    .rank-badge {
        font-weight: 700; color: var(--bz-primary);
        background: var(--el-primary-soft, rgba(15, 118, 110, 0.12));
        padding: 4px 8px; border-radius: var(--el-radius, 4px); font-size: 12px;
    }

    /* STATUS BADGES (Soft) */
    .badge-soft-success { background-color: var(--bz-primary); color: #fff; }
    .badge-soft-danger { background-color: var(--bz-secondary); color: #111827; }
    .badge-soft-warning { background-color: var(--bz-tertiary); color: #fff; }
    
    /* ACTION BUTTONS */
    .btn-icon-sm {
        width: 30px; height: 30px;
        padding: 0; display: inline-flex; align-items: center; justify-content: center;
        border-radius: 4px; transition: all 0.2s;
    }
    .btn-view { background: var(--el-primary-soft, rgba(15, 118, 110, 0.12)); color: var(--bz-primary); border: none; }
    .btn-view:hover { background: var(--bz-primary); color: #fff; }
    
    .btn-evaluate { background: var(--bz-secondary); color: #fff; border: none; }
    .btn-evaluate:hover { filter: brightness(0.95); color: #fff; }

    .btn-feedback { background: var(--el-primary-soft, rgba(15, 118, 110, 0.12)); color: var(--bz-primary); border: none; }
    .btn-feedback:hover { background: var(--bz-primary); color: #fff; }
    .feedback-count-badge {
        background: var(--el-primary-soft, rgba(15, 118, 110, 0.12));
        color: var(--bz-primary);
        border: 1px solid var(--bz-border);
    }

    /* STAT CARDS */
    .stat-card {
        border: 1px solid var(--bz-border);
        border-left: 4px solid transparent;
        transition: transform 0.2s;
    }
    .stat-card:hover { transform: translateY(-3px); }
    .border-primary { border-left-color: var(--bz-primary) !important; }
    .border-success { border-left-color: var(--bz-primary) !important; }
    .border-warning { border-left-color: var(--bz-secondary) !important; }
    .border-danger { border-left-color: var(--bz-secondary) !important; }

    .filter-card .el-table-toolbar {
        border-top: 1px solid var(--bz-border);
        margin-top: 14px;
        padding: 14px 0 0;
    }

    .filter-card .result-search-toolbar {
        align-items: center;
        display: grid !important;
        gap: 16px;
        grid-template-columns: 160px minmax(240px, 360px) 160px;
        justify-content: start;
        border-top: 1px solid var(--bz-border);
        margin-top: 14px;
        padding-top: 14px;
        width: 100%;
    }

    .filter-card .result-status-field {
        display: block !important;
        max-width: 100% !important;
        min-width: 0;
        visibility: visible !important;
        width: 100% !important;
    }

    .filter-card .result-status-control {
        background-color: #ffffff !important;
        border: 1px solid #dcdfe3 !important;
        display: block !important;
        min-height: 38px;
        padding: 0.35rem 0.75rem;
        width: 100% !important;
    }

    .filter-card .result-status-field .result-status-control {
        display: block !important;
        min-height: 38px;
        min-width: 0;
        opacity: 1 !important;
        visibility: visible !important;
        width: 100% !important;
    }

    .filter-card .result-search-field {
        min-width: 260px;
        width: 100%;
    }

    .filter-card .result-page-size {
        min-width: 0;
        width: 100%;
    }

    .filter-card .result-search-field,
    .filter-card .result-page-size {
        min-width: 0;
        width: 100%;
    }

    .filter-card .result-search-field .search-box {
        width: 100%;
    }

    @media (max-width: 767.98px) {
        .filter-card .result-search-toolbar {
            grid-template-columns: 1fr;
        }

        .filter-card .result-status-field,
        .filter-card .result-search-field,
        .filter-card .result-page-size {
            max-width: 100% !important;
            min-width: 0;
            width: 100% !important;
        }
    }

    .result-analytics-grid {
        display: grid;
        gap: 16px;
        grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
    }

    .result-chart-card {
        border: 1px solid var(--bz-border);
        border-radius: var(--el-radius, 4px);
        padding: 18px;
    }

    .result-bar-row {
        align-items: center;
        display: grid;
        gap: 10px;
        grid-template-columns: 80px 1fr 40px;
        margin: 12px 0;
    }

    .result-bar-track {
        background: var(--el-primary-soft, rgba(15, 118, 110, 0.12));
        height: 10px;
        overflow: hidden;
    }

    .result-bar-fill {
        background: var(--result-bar-color, var(--bz-primary));
        display: block;
        height: 100%;
        min-width: var(--result-bar-min, 0);
    }

</style>
@endsection

@section('content')
@component('components.breadcrumb')
@slot('li_1', '')
@slot('title', 'Results Dashboard')
@endcomponent

<div class="row">
    <div class="col-lg-12">
        <div class="card border-0 shadow-none bg-transparent">
            
            {{-- TAB NAVIGATION --}}
            <div class="card-header bg-white border rounded-top p-0">
                <ul class="nav nav-tabs-custom card-header-tabs border-bottom-0 mx-3 mt-2" role="tablist">
                    <li class="nav-item">
                        <a class="nav-link active py-3" data-bs-toggle="tab" href="#resultsListTab" role="tab" aria-selected="true">
                            <i class="ri-list-check align-middle me-1"></i> Result Management
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link py-3" data-bs-toggle="tab" href="#analyticsTab" role="tab" aria-selected="false">
                            <i class="ri-pie-chart-2-line align-middle me-1"></i> Analytics Overview
                        </a>
                    </li>
                </ul>
            </div>

            <div class="card-body bg-white border border-top-0 rounded-bottom p-4">
                <div class="tab-content">
                    
                    {{-- ========================================= --}}
                    {{-- TAB 1: RESULTS LIST (Main Interface) --}}
                    {{-- ========================================= --}}
                    <div class="tab-pane active" id="resultsListTab" role="tabpanel">
                        
                        {{-- 1. PROFESSIONAL FILTER BAR --}}
                        <div class="filter-card">
                            <div class="filter-header">
                                <i class="ri-filter-3-line"></i> Filter Records
                            </div>
                            <div class="p-3">
                                <form method="GET" action="{{ route('results.index') }}" id="searchForm">
                                    <div class="row g-3 align-items-end">
                                        {{-- Exam Selection --}}
                                        <div class="col-md-3">
                                            <label class="form-label">Group</label>
                                            <div class="input-group">
                                                <span class="input-group-text bg-light"><i class="ri-group-line"></i></span>
                                                <select class="form-select" id="group" name="group_id">
                                                    <option value="">All Groups</option>
                                                    @foreach($groups as $group)
                                                    @php
                                                        $isSelected = is_array($selectedGroupIds) 
                                                                      ? in_array($group->id, $selectedGroupIds) 
                                                                      : $group->id == $selectedGroupIds;
                                                    @endphp
                                                    <option value="{{ $group->id }}" {{ $isSelected ? 'selected' : '' }}>
                                                        {{ $group->group_name }}
                                                    </option>
                                                    @endforeach
                                                </select>
                                            </div>
                                        </div>

                                        <div class="col-md-3">
                                            <label class="form-label">Package</label>
                                            <div class="input-group">
                                                <span class="input-group-text bg-light"><i class="ri-stack-line"></i></span>
                                                <select class="form-select" id="package" name="package_id">
                                                    <option value="">All Packages</option>
                                                    @foreach($packages as $package)
                                                    <option value="{{ $package->id }}" {{ $package->id == $selectedPackageId ? 'selected' : '' }}>
                                                        {{ $package->name }}
                                                    </option>
                                                    @endforeach
                                                </select>
                                            </div>
                                        </div>

                                        <div class="col-md-3">
                                            <label class="form-label">Exam</label>
                                            <div class="input-group">
                                                <span class="input-group-text bg-light"><i class="ri-article-line"></i></span>
                                                <select class="form-select" id="exam" name="exam_id">
                                                    <option value="">All Exams</option>
                                                    @foreach($exams as $exam)
                                                    <option value="{{ $exam->id }}" {{ $exam->id == $selectedExamId ? 'selected' : '' }}>
                                                        {{ $exam->name }}
                                                    </option>
                                                    @endforeach
                                                </select>
                                            </div>
                                        </div>

                                        <div class="col-md-3">
                                            <div class="d-flex gap-2">
                                                <button type="submit" class="btn el-btn-primary fw-medium"><i class="ri-search-2-line me-1"></i> Search</button>
                                                <button type="button" class="btn el-btn-secondary" id="resetButton" title="Reset Filters"><i class="ri-refresh-line"></i> Reset</button>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="result-search-toolbar mb-0">
                                        <div class="result-status-field">
                                            <select class="form-select result-status-control" id="result_status_filter" name="status" aria-label="Result status">
                                                <option value="">All Status</option>
                                                <option value="Pass" {{ $selectedStatus=='Pass' ? 'selected' : '' }}>Passed</option>
                                                <option value="Fail" {{ $selectedStatus=='Fail' ? 'selected' : '' }}>Failed</option>
                                                <option value="Pending" {{ $selectedStatus=='Pending' ? 'selected' : '' }}>Pending (Manual)</option>
                                            </select>
                                        </div>
                                        <div class="result-search-field">
                                            <div class="search-box">
                                                <input type="text" class="form-control search" name="student" placeholder="Name or enroll ID" value="{{ $studentNameOrEnroll }}">
                                                <i class="ri-search-line search-icon"></i>
                                            </div>
                                        </div>
                                        <div class="result-page-size">
                                            <select class="form-select" name="per_page" id="per_page_hidden">
                                                <option value="50" {{ $perPage == 50 ? 'selected' : '' }}>50 per page</option>
                                                <option value="100" {{ $perPage == 100 ? 'selected' : '' }}>100 per page</option>
                                                <option value="500" {{ $perPage == 500 ? 'selected' : '' }}>500 per page</option>
                                            </select>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>

                        {{-- 2. RESULTS TABLE --}}
                        @if($results->isNotEmpty())
                        <div class="card border mb-0">
                            <div class="card-header bg-light d-flex justify-content-between align-items-center py-2 px-3">
                                <h6 class="mb-0 fw-bold text-dark">
                                    @if($selectedExamId) <i class="ri-trophy-line text-warning me-1"></i> Merit List @else <i class="ri-history-line text-primary me-1"></i> Recent Results @endif
                                    <span class="badge bg-secondary-subtle text-secondary ms-2">Showing {{ $results->count() }} of {{ $results->total() }}</span>
                                    <span class="badge feedback-count-badge ms-2">{{ $results->sum('feedback_count') }} Feedback</span>
                                </h6>
                            </div>
                            
                            <div class="table-responsive">
                                <table class="table align-middle premium-table mb-0">
                                    <thead>
                                        <tr>
                                            @if($selectedExamId) <th class="text-center" style="width: 80px;">Rank</th> @endif
                                            <th>Student Details</th>
                                            <th>Exam Name</th>
                                            <th class="text-center">Score</th>
                                            <th class="text-center">Time Taken</th>
                                            <th class="text-center">Status</th>
                                            <th class="text-end">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($results as $index => $result)
                                            @php
                                                $rankDisplay = ($results->currentPage() - 1) * $results->perPage() + $index + 1;
                                                $timeSec = (int)($result->total_test_time ?? 0);
                                                $timeFormatted = $timeSec < 60 ? $timeSec.'s' : floor($timeSec/60).'m '.($timeSec%60).'s';
                                            @endphp
                                        <tr>
                                            @if($selectedExamId) 
                                                <td class="text-center">
                                                    @if($rankDisplay == 1) <i class="ri-medal-fill rank-icon rank-1"></i>
                                                    @elseif($rankDisplay == 2) <i class="ri-medal-fill rank-icon rank-2"></i>
                                                    @elseif($rankDisplay == 3) <i class="ri-medal-fill rank-icon rank-3"></i>
                                                    @else <span class="rank-badge">#{{ $rankDisplay }}</span>
                                                    @endif
                                                </td> 
                                            @endif

                                            <td>
                                                <div class="d-flex flex-column">
                                                    <span class="fw-bold text-dark fs-14">{{ $result->student?->name ?? 'N/A' }}</span>
                                                    <span class="text-muted" style="font-size: 11px;">
                                                        ID: {{ $result->student?->enroll ?? 'N/A' }}
                                                    </span>
                                                </div>
                                            </td>

                                            <td>
                                                <span class="text-dark fw-medium">{{ Str::limit($result->exam?->name ?? 'N/A', 30) }}</span>
                                            </td>

                                            <td class="text-center">
                                                @if($result->result == 'Pending')
                                                    <span class="badge bg-light text-warning border border-warning">Pending</span>
                                                @else
                                                    <div class="d-flex flex-column align-items-center">
                                                        <span class="fw-bold fs-14 {{ $result->percent >= optional($result->exam)->passing_percentage ? 'text-success' : 'text-danger' }}">
                                                            {{ round($result->percent, 1) }}%
                                                        </span>
                                                        <small class="text-muted" style="font-size: 10px;">{{ number_format($result->obtained_marks, 1) }} / {{ $result->total_marks }}</small>
                                                    </div>
                                                @endif
                                            </td>

                                            <td class="text-center">
                                                <span class="badge bg-light text-dark border">{{ $timeFormatted }}</span>
                                            </td>

                                            <td class="text-center">
                                                @if($result->result == 'Pass')
                                                    <span class="badge badge-soft-success px-3 py-1 rounded-pill">PASS</span>
                                                @elseif($result->result == 'Fail')
                                                    <span class="badge badge-soft-danger px-3 py-1 rounded-pill">FAIL</span>
                                                @else
                                                    <span class="badge badge-soft-warning px-3 py-1 rounded-pill">PENDING</span>
                                                @endif
                                            </td>

                                            <td class="text-end">
                                                {{-- ✅ EVALUATE BUTTON (Visible for ALL Status now) --}}
                                                <a href="{{ route('results.evaluate', $result->id) }}" class="btn-icon-sm btn-evaluate me-1" title="Manual Evaluation / Re-check">
                                                    <i class="ri-edit-circle-line"></i>
                                                </a>

                                                <a href="{{ route('results.view', $result->id) }}" class="btn-icon-sm btn-view me-1" title="View Full Report">
                                                    <i class="ri-eye-line"></i>
                                                </a>
                                                
                                                @if($result->feedback_count > 0)
                                                    <a href="{{ route('results.feedback', $result->id) }}" class="btn-icon-sm btn-feedback" title="View Feedback">
                                                        <i class="ri-chat-smile-2-line"></i>
                                                    </a>
                                                    <span class="badge feedback-count-badge ms-1">{{ $result->feedback_count }}</span>
                                                @endif
                                            </td>
                                        </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                            
                            <div class="d-flex justify-content-end p-3 bg-light border-top">
                                {{ $results->appends(request()->query())->links('pagination::bootstrap-5') }}
                            </div>
                        </div>
                        @else
                            <div class="text-center py-5">
                                <div class="mb-3"><i class="ri-file-search-line display-4 text-muted opacity-25"></i></div>
                                <h5>No Results Found</h5>
                                <p class="text-muted">Try adjusting your filters to find records.</p>
                            </div>
                        @endif
                    </div>

                    {{-- ========================================= --}}
                    {{-- TAB 2: ANALYTICS DASHBOARD --}}
                    {{-- ========================================= --}}
                    <div class="tab-pane" id="analyticsTab" role="tabpanel">
                        @if($stats)
                            <div class="row mb-4">
                                <div class="col-xl-3 col-md-6"><div class="card card-body stat-card border-primary p-3"><p class="text-uppercase fs-12 text-muted mb-1">Total Attempts</p><h3 class="fw-bold mb-0">{{ $stats['total_results'] }}</h3></div></div>
                                <div class="col-xl-3 col-md-6"><div class="card card-body stat-card border-success p-3"><p class="text-uppercase fs-12 text-muted mb-1">Average Score</p><h3 class="fw-bold mb-0 text-success">{{ number_format($stats['average_score'], 1) }}%</h3></div></div>
                                <div class="col-xl-3 col-md-6"><div class="card card-body stat-card border-warning p-3"><p class="text-uppercase fs-12 text-muted mb-1">Pass Rate</p><h3 class="fw-bold mb-0 text-warning">{{ number_format($stats['pass_rate'], 1) }}%</h3></div></div>
                                <div class="col-xl-3 col-md-6"><div class="card card-body stat-card border-danger p-3"><p class="text-uppercase fs-12 text-muted mb-1">Lowest Score</p><h3 class="fw-bold mb-0 text-danger">{{ number_format($stats['lowest_score'], 1) }}%</h3></div></div>
                            </div>

                            <div class="result-analytics-grid mb-4">
                                <div class="result-chart-card">
                                    <h6 class="mb-3">Result Status</h6>
                                    @php $maxStatus = max($statusCounts->max() ?: 1, 1); @endphp
                                    @foreach(['Pass' => 'Passed', 'Fail' => 'Failed', 'Pending' => 'Pending'] as $key => $label)
                                        @php
                                            $count = (int) ($statusCounts[$key] ?? 0);
                                            $barColor = ['Pass' => 'var(--bz-primary)', 'Fail' => 'var(--bz-secondary)', 'Pending' => 'var(--bz-tertiary)'][$key];
                                            $barMin = $count > 0 ? '2px' : '0';
                                        @endphp
                                        <div class="result-bar-row">
                                            <span>{{ $label }}</span>
                                            <span class="result-bar-track"><span class="result-bar-fill" style="--result-bar-color: {{ $barColor }}; --result-bar-min: {{ $barMin }}; width: {{ ($count / $maxStatus) * 100 }}%"></span></span>
                                            <strong>{{ $count }}</strong>
                                        </div>
                                    @endforeach
                                </div>

                                <div class="result-chart-card">
                                    <h6 class="mb-3">Score Distribution</h6>
                                    @php $maxBand = max($scoreBands->max() ?: 1, 1); @endphp
                                    @foreach($scoreBands as $band => $count)
                                        @php
                                            $barColor = match($loop->index) {
                                                0 => 'var(--bz-secondary)',
                                                1 => 'var(--bz-tertiary)',
                                                2 => 'color-mix(in srgb, var(--bz-primary) 72%, var(--bz-secondary))',
                                                default => 'var(--bz-primary)',
                                            };
                                            $barMin = $count > 0 ? '2px' : '0';
                                        @endphp
                                        <div class="result-bar-row">
                                            <span>{{ $band }}%</span>
                                            <span class="result-bar-track"><span class="result-bar-fill" style="--result-bar-color: {{ $barColor }}; --result-bar-min: {{ $barMin }}; width: {{ ($count / $maxBand) * 100 }}%"></span></span>
                                            <strong>{{ $count }}</strong>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-lg-6">
                                    <div class="card h-100 border shadow-none">
                                        <div class="card-header bg-light"><h6 class="mb-0">🏆 Top 5 Performers</h6></div>
                                        <div class="card-body p-0">
                                            <ul class="list-group list-group-flush">
                                                @foreach($topStudents as $idx => $st)
                                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                                    <div class="d-flex align-items-center">
                                                        <span class="badge bg-light text-dark rounded-circle me-3" style="width:24px;height:24px;line-height:18px;">{{ $loop->iteration }}</span>
                                                        <span class="fw-medium">{{ $st->student?->name ?? 'N/A' }}</span>
                                                    </div>
                                                    <span class="fw-bold text-success">{{ round($st->percent,1) }}%</span>
                                                </li>
                                                @endforeach
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-lg-6">
                                    <div class="card h-100 border shadow-none">
                                        <div class="card-header bg-light"><h6 class="mb-0">📉 Needs Improvement</h6></div>
                                        <div class="card-body p-0">
                                            <ul class="list-group list-group-flush">
                                                @foreach($weakStudents as $idx => $st)
                                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                                    <div class="d-flex align-items-center">
                                                        <span class="badge bg-danger-subtle text-danger rounded-circle me-3" style="width:24px;height:24px;line-height:18px;">!</span>
                                                        <span class="fw-medium">{{ $st->student?->name ?? 'N/A' }}</span>
                                                    </div>
                                                    <span class="fw-bold text-danger">{{ round($st->percent,1) }}%</span>
                                                </li>
                                                @endforeach
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @else
                            <div class="alert alert-light border text-center mt-3">
                                <i class="ri-bar-chart-grouped-line fs-1 me-2 align-middle"></i> No result data available for analytics.
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('script')
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
    $(document).ready(function() {
        $('#resetButton').click(function() { window.location.href = "{{ route('results.index') }}"; });
        $('#searchForm').submit(function() {
            var activeTab = $('.nav-tabs-custom .nav-link.active').attr('href');
            $(this).find('input[name="tab"]').remove();
            $('<input>').attr({ type: 'hidden', name: 'tab', value: activeTab }).appendTo(this);
        });

        let resultFilterTimer;
        function submitResults(delay) {
            clearTimeout(resultFilterTimer);
            resultFilterTimer = setTimeout(function() {
                $('#searchForm .result-search-toolbar, #searchForm .row').addClass('el-filter-working');
                const form = document.getElementById('searchForm');
                if (form.requestSubmit) {
                    form.requestSubmit();
                } else {
                    $('#searchForm').trigger('submit');
                }
            }, delay);
        }

        $('#group, #package, #exam, #result_status_filter, #per_page_hidden').on('change', function() {
            submitResults(150);
        });

        $('input[name="student"]').on('input', function() {
            submitResults(650);
        });

        var urlParams = new URLSearchParams(window.location.search);
        var activeTab = urlParams.get('tab');
        if (activeTab) {
            $('.nav-tabs-custom .nav-link').removeClass('active');
            $('.tab-content .tab-pane').removeClass('active show');
            $('a[href="' + activeTab + '"]').addClass('active');
            $(activeTab).addClass('active show');
        }
    });
</script>
@endsection

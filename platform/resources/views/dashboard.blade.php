@extends('layouts.master')
@section('title') @lang('Dashboard') @endsection

@section('css')
<style>
    .insight-list .list-group-item {
        transition: background-color .2s ease-in-out;
    }
    .insight-list .list-group-item:hover {
        background-color: var(--vz-tertiary-bg);
    }
    /* Note: Quick Action CSS is technically unused now, but kept to avoid breaking anything else */
    .quick-action-card {
        display: block;
        text-align: center;
        padding: 1rem;
        border-radius: 0.5rem;
        transition: transform .2s ease-in-out, box-shadow .2s ease-in-out;
        color: var(--vz-body-color);
        background-color: var(--vz-card-bg);
    }
    .quick-action-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 7px 14px rgba(0,0,0,.08);
        color: var(--vz-body-color);
    }
    .quick-action-card i {
        font-size: 1.5rem;
    }

    .setup-check-item {
        border-color: color-mix(in srgb, var(--vz-primary) 20%, #e5e7eb) !important;
        transition: border-color .2s ease, box-shadow .2s ease, transform .2s ease;
    }
    .setup-check-item:hover {
        border-color: var(--vz-primary) !important;
        box-shadow: 0 8px 20px rgba(var(--vz-primary-rgb), .12);
        transform: translateY(-1px);
    }
    .setup-check-icon {
        background: transparent !important;
        border: 2px solid var(--vz-primary);
        color: var(--vz-primary) !important;
    }
    .setup-check-icon.is-pending {
        background: transparent !important;
        border-color: var(--vz-warning);
        color: var(--vz-warning) !important;
    }
    .setup-check-title {
        color: var(--vz-primary);
        font-weight: 750;
    }
    .dashboard-chart-card {
        border: 1px solid color-mix(in srgb, var(--vz-primary) 13%, var(--vz-border-color));
        box-shadow: 0 12px 30px rgba(15, 23, 42, .045);
    }
    .dashboard-chart-card .card-header {
        background: transparent;
        border-bottom-color: color-mix(in srgb, var(--vz-primary) 10%, var(--vz-border-color));
    }
    .dashboard-chart-kicker {
        color: var(--vz-primary);
        font-size: 11px;
        font-weight: 800;
        letter-spacing: .08em;
        text-transform: uppercase;
    }
    .dashboard-chart { min-height: 320px; width: 100%; }
    .dashboard-chart--wide { min-height: 340px; }
    .dashboard-stat-chip {
        align-items: center;
        background: color-mix(in srgb, var(--vz-primary) 8%, var(--vz-secondary-bg));
        border: 1px solid color-mix(in srgb, var(--vz-primary) 18%, var(--vz-border-color));
        border-radius: 999px;
        color: var(--vz-body-color);
        display: inline-flex;
        font-size: 12px;
        font-weight: 650;
        gap: 6px;
        padding: 6px 10px;
    }
    .dashboard-summary-grid {
        display: grid;
        gap: 12px;
        grid-template-columns: repeat(auto-fit, minmax(190px, 1fr));
    }
    .dashboard-summary-item {
        align-items: center;
        background: var(--vz-card-bg);
        border: 1px solid color-mix(in srgb, var(--vz-primary) 14%, var(--vz-border-color));
        border-radius: 12px;
        display: flex;
        gap: 12px;
        min-width: 0;
        padding: 16px;
    }
    .dashboard-summary-icon {
        align-items: center;
        background: color-mix(in srgb, var(--vz-primary) 11%, var(--vz-secondary-bg));
        border-radius: 10px;
        color: var(--vz-primary);
        display: inline-flex;
        flex: 0 0 42px;
        font-size: 21px;
        height: 42px;
        justify-content: center;
    }
    .dashboard-summary-label {
        color: var(--vz-secondary-color);
        display: block;
        font-size: 11px;
        font-weight: 750;
        letter-spacing: .055em;
        text-transform: uppercase;
    }
    .dashboard-summary-value {
        color: var(--vz-heading-color);
        display: block;
        font-size: 22px;
        font-weight: 800;
        line-height: 1.15;
    }
    .dashboard-coverage-table { color: var(--vz-body-color); }
    .dashboard-coverage-table th {
        background: color-mix(in srgb, var(--vz-primary) 8%, var(--vz-card-bg));
        border-bottom-color: var(--vz-border-color);
        color: var(--vz-secondary-color);
        font-size: 11px;
        font-weight: 800;
        letter-spacing: .045em;
        padding: 12px 14px;
        text-transform: uppercase;
        white-space: nowrap;
    }
    .dashboard-coverage-table td {
        background: var(--vz-card-bg);
        border-color: var(--vz-border-color);
        color: var(--vz-body-color);
        padding: 12px 14px;
    }
    .dashboard-coverage-table tbody tr:nth-child(even) td {
        background: color-mix(in srgb, var(--vz-primary) 3%, var(--vz-card-bg));
    }
    .dashboard-coverage-table th[data-sort-header] { cursor: pointer; user-select: none; }
    .dashboard-coverage-table th[data-sort-header]:focus-visible {
        outline: 2px solid var(--vz-primary);
        outline-offset: -3px;
    }
    .dashboard-coverage-table .dashboard-sort-icon { color: var(--vz-primary); font-size: 15px; margin-left: 4px; }

    [data-bs-theme="dark"] .dashboard-chart-card { box-shadow: none; }
    @media (max-width: 767.98px) {
        .dashboard-summary-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .dashboard-summary-item { padding: 13px; }
        .dashboard-chart,
        .dashboard-chart--wide { min-height: 300px; }
    }
    @media (max-width: 420px) {
        .dashboard-summary-grid { grid-template-columns: 1fr; }
    }
</style>
@endsection

@section('content')
@component('components.breadcrumb')
@slot('li_1') Dashboards @endslot
@slot('title') Admin Insights @endslot
@endcomponent

{{-- ✅ ADDED: Header with Date & Quick Create Dropdown --}}
<div class="row mb-3 pb-1">
    <div class="col-12">
        <div class="d-flex align-items-lg-center flex-lg-row flex-column justify-content-end">
            <div class="mt-3 mt-lg-0">
                <div class="d-flex align-items-center gap-3">
                    {{-- Date Display --}}
                    <div class="d-flex align-items-center gap-2">
                        <span class="text-muted fs-12">
                            <i class="ri-calendar-event-line align-middle me-1"></i> {{ date('d M, Y') }}
                        </span>
                    </div>

                    {{-- Quick Create Dropdown --}}
                    <div class="dropdown">
                        <button class="btn btn-soft-primary dropdown-toggle" type="button" id="quickCreateDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="ri-add-circle-line align-middle me-1"></i> Quick Create
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="quickCreateDropdown">
                            <li><h6 class="dropdown-header text-uppercase fs-11">Academics</h6></li>
                            <li><a class="dropdown-item" href="{{ route('exams.create') }}"><i class="ri-file-add-line align-middle me-1 text-primary"></i> Create Exam</a></li>
                            <li><a class="dropdown-item" href="{{ route('questions.create') }}"><i class="ri-question-answer-line align-middle me-1 text-info"></i> Add Question</a></li>
                            
                            <li><hr class="dropdown-divider"></li>
                            <li><h6 class="dropdown-header text-uppercase fs-11">Administration</h6></li>
                            {{-- ✅ FIXED: Linked to 'students.index' instead of 'create' to fix error --}}
                            <li><a class="dropdown-item" href="{{ route('students.index') }}"><i class="ri-user-settings-line align-middle me-1 text-success"></i> Manage Students</a></li>
                            <li><a class="dropdown-item" href="{{ route('packages.create') }}"><i class="ri-shopping-bag-3-line align-middle me-1 text-warning"></i> Create Package</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@php
    $completedSetupItems = collect($setupChecklist)->where('done', true)->count();
    $totalSetupItems = count($setupChecklist);
    $setupProgress = $totalSetupItems ? round(($completedSetupItems / $totalSetupItems) * 100) : 0;
@endphp
@if(!empty($setupChecklist) && $setupProgress < 100)
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-3">
                    <div>
                        <h5 class="card-title mb-1">Organization setup</h5>
                        <p class="text-muted mb-0">{{ $completedSetupItems }} of {{ $totalSetupItems }} essentials completed</p>
                    </div>
                    <div class="text-end" style="min-width: 180px;">
                        <span class="fw-semibold">{{ $setupProgress }}%</span>
                        <div class="progress progress-sm mt-2">
                            <div class="progress-bar bg-success" role="progressbar" style="width: {{ $setupProgress }}%;" aria-valuenow="{{ $setupProgress }}" aria-valuemin="0" aria-valuemax="100"></div>
                        </div>
                    </div>
                </div>
                <div class="row g-3">
                    @foreach($setupChecklist as $item)
                        <div class="col-xl-3 col-md-4 col-sm-6">
                            <a href="{{ $item['route'] }}" class="setup-check-item d-block border rounded p-3 h-100 text-reset text-decoration-none">
                                <div class="d-flex align-items-start gap-2">
                                    <span class="avatar-xs flex-shrink-0">
                                        <span class="avatar-title rounded-circle setup-check-icon {{ $item['done'] ? '' : 'is-pending' }}">
                                            <i class="{{ $item['done'] ? 'ri-check-line' : 'ri-time-line' }}"></i>
                                        </span>
                                    </span>
                                    <span>
                                        <span class="setup-check-title d-block">{{ $item['label'] }}</span>
                                        <small class="text-muted">{{ $item['hint'] }}</small>
                                    </span>
                                </div>
                            </a>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</div>
@endif

{{-- Insight Row 1: Key Performance Indicators (KPIs) --}}
<div class="row">
    <div class="col-xl-3 col-md-6">
        <div class="card card-animate">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-grow-1">
                        <p class="text-uppercase fw-medium text-muted mb-0">Total Revenue</p>
                    </div>
                </div>
                <div class="d-flex align-items-end justify-content-between mt-4">
                    <div>
                        <h4 class="fs-22 fw-semibold ff-secondary mb-4">₹<span class="counter-value">{{ number_format($totalRevenue) }}</span></h4>
                        <span class="badge bg-success me-1">{{ number_format($revenueLast30Days) }}</span> <span class="text-muted">in last 30 days</span>
                    </div>
                    <div class="avatar-sm flex-shrink-0">
                        <span class="avatar-title bg-success-subtle rounded fs-3"><i class="ri-money-dollar-circle-line text-success"></i></span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6">
        <div class="card card-animate">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-grow-1">
                        <p class="text-uppercase fw-medium text-muted mb-0">Students</p>
                    </div>
                </div>
                <div class="d-flex align-items-end justify-content-between mt-4">
                    <div>
                        <h4 class="fs-22 fw-semibold ff-secondary mb-2"><span class="counter-value">{{ $studentsCount }}</span></h4>
                        @if($showDemoStudents)
                            <div class="text-muted fs-12 mb-2">{{ number_format($demoStudentsCount) }} demo &middot; {{ number_format($totalStudentsCount) }} total</div>
                        @endif
                        <span class="badge bg-primary me-1">+{{ $newStudentsCount }}</span>
                        <span class="text-muted">real in last 7 days</span>
                    </div>
                    <div class="avatar-sm flex-shrink-0">
                        <span class="avatar-title bg-primary-subtle rounded fs-3"><i class="ri-user-3-line text-primary"></i></span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6">
        <div class="card card-animate">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-grow-1">
                        <p class="text-uppercase fw-medium text-muted mb-0">Total Exam Attempts</p>
                    </div>
                </div>
                <div class="d-flex align-items-end justify-content-between mt-4">
                    <div>
                        <h4 class="fs-22 fw-semibold ff-secondary mb-4"><span class="counter-value">{{ $overallPerformance->total_attempts ?? 0 }}</span></h4>
                        <a href="{{ route('results.index') }}" class="text-decoration-underline">View All Results</a>
                    </div>
                    <div class="avatar-sm flex-shrink-0">
                        <span class="avatar-title bg-warning-subtle rounded fs-3"><i class="ri-file-list-3-line text-warning"></i></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6">
        <div class="card card-animate">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-grow-1">
                        <p class="text-uppercase fw-medium text-muted mb-0">Average Performance</p>
                    </div>
                </div>
                <div class="d-flex align-items-end justify-content-between mt-4">
                    <div>
                        <h4 class="fs-22 fw-semibold ff-secondary mb-4"><span class="counter-value">{{ number_format($overallPerformance->average_score ?? 0, 2) }}</span>%</h4>
                        <span class="text-muted">across all exams</span>
                    </div>
                    <div class="avatar-sm flex-shrink-0">
                        <span class="avatar-title bg-info-subtle rounded fs-3"><i class="ri-line-chart-line text-info"></i></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- 🚫 REMOVED: Quick Actions Row --}}

{{-- Platform Content Summary --}}
<div class="card dashboard-chart-card">
    <div class="card-header">
        <span class="dashboard-chart-kicker">Content inventory</span>
        <h4 class="card-title mb-0 mt-1">Platform overview</h4>
    </div>
    <div class="card-body">
        <div class="dashboard-summary-grid">
            @foreach([
                ['label' => 'Groups', 'value' => $platformSummary['groups'], 'icon' => 'ri-group-2-line'],
                ['label' => 'Categories', 'value' => $platformSummary['categories'], 'icon' => 'ri-node-tree'],
                ...(($subcategoriesEnabled ?? true) ? [['label' => 'Subcategories', 'value' => $platformSummary['subcategories'], 'icon' => 'ri-git-branch-line']] : []),
                ['label' => 'Packages', 'value' => $platformSummary['packages'], 'icon' => 'ri-stack-line'],
                ['label' => 'Exams', 'value' => $platformSummary['exams'], 'icon' => 'ri-file-list-3-line'],
                ['label' => 'Questions', 'value' => $platformSummary['questions'], 'icon' => 'ri-questionnaire-line'],
            ] as $summaryItem)
                <div class="dashboard-summary-item">
                    <span class="dashboard-summary-icon"><i class="{{ $summaryItem['icon'] }}"></i></span>
                    <span>
                        <span class="dashboard-summary-value">{{ number_format($summaryItem['value']) }}</span>
                        <span class="dashboard-summary-label">{{ $summaryItem['label'] }}</span>
                    </span>
                </div>
            @endforeach
        </div>
    </div>
</div>
{{-- Activity Trend --}}
<div class="row">
    <div class="col-12">
        <div class="card dashboard-chart-card">
            <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
                <div>
                    <span class="dashboard-chart-kicker">Last 14 days</span>
                    <h4 class="card-title mb-0 mt-1">Registrations and completed attempts</h4>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <span class="dashboard-stat-chip"><i class="ri-user-add-line text-primary"></i>{{ array_sum($activityTrend['students'] ?? []) }} new students</span>
                    <span class="dashboard-stat-chip"><i class="ri-file-chart-line text-warning"></i>{{ array_sum($activityTrend['attempts'] ?? []) }} attempts</span>
                </div>
            </div>
            <div class="card-body">
                <div id="activity-trend-chart" class="dashboard-chart dashboard-chart--wide" role="img" aria-label="Student registrations and completed exam attempts over the last 14 days"></div>
            </div>
        </div>
    </div>
</div>

{{-- Hierarchy Coverage --}}
<div class="row g-3">
    <div class="col-xl-6">
        <div class="card dashboard-chart-card h-100">
            <div class="card-header">
                <span class="dashboard-chart-kicker">Group coverage</span>
                <h4 class="card-title mb-0 mt-1">Content and students by group</h4>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table align-middle mb-0 dashboard-coverage-table" data-sortable-table>
                        <thead>
                            <tr>
                                <th>Group</th>
                                <th class="text-end">Questions</th>
                                <th class="text-end">Exams</th>
                                <th class="text-end">Packages</th>
                                <th class="text-end">Real</th>
                                @if($showDemoStudents)
                                    <th class="text-end">Demo</th><th class="text-end">Total</th>
                                @endif
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($groupCoverage as $item)
                                <tr>
                                    <td class="fw-semibold">{{ $item['name'] }}</td>
                                    <td class="text-end">{{ number_format($item['questions']) }}</td>
                                    <td class="text-end">{{ number_format($item['exams']) }}</td>
                                    <td class="text-end">{{ number_format($item['packages']) }}</td>
                                    <td class="text-end">{{ number_format($item['real_students']) }}</td>
                                    @if($showDemoStudents)
                                        <td class="text-end">{{ number_format($item['demo_students']) }}</td>
                                        <td class="text-end">{{ number_format($item['total_students']) }}</td>
                                    @endif
                                </tr>
                            @empty
                                <tr><td colspan="{{ $showDemoStudents ? 7 : 5 }}" class="text-center text-muted py-4">No group coverage is available yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-6">
        <div class="card dashboard-chart-card h-100">
            <div class="card-header">
                <span class="dashboard-chart-kicker">Category coverage</span>
                <h4 class="card-title mb-0 mt-1">Content by category</h4>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table align-middle mb-0 dashboard-coverage-table" data-sortable-table>
                        <thead>
                            <tr><th>Category</th><th class="text-end">Questions</th><th class="text-end">Exams</th><th class="text-end">Packages</th></tr>
                        </thead>
                        <tbody>
                            @forelse($categoryCoverage as $item)
                                <tr>
                                    <td class="fw-semibold">{{ $item['name'] }}</td>
                                    <td class="text-end">{{ number_format($item['questions']) }}</td>
                                    <td class="text-end">{{ number_format($item['exams']) }}</td>
                                    <td class="text-end">{{ number_format($item['packages']) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-center text-muted py-4">No category coverage is available yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Content Distribution Charts --}}
<div class="row g-3">
    <div class="col-xl-7">
        <div class="card dashboard-chart-card h-100">
            <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
                <div>
                    <span class="dashboard-chart-kicker">Question bank</span>
                    <h4 class="card-title mb-0 mt-1">Questions by type</h4>
                </div>
                <span class="dashboard-stat-chip"><i class="ri-questionnaire-line text-primary"></i>{{ number_format(collect($questionCounts)->sum('total')) }} questions</span>
            </div>
            <div class="card-body">
                <div id="question-type-chart" class="dashboard-chart" role="img" aria-label="Question count by question type"></div>
            </div>
        </div>
    </div>
    <div class="col-xl-5">
        <div class="card dashboard-chart-card h-100">
            <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
                <div>
                    <span class="dashboard-chart-kicker">Audience</span>
                    <h4 class="card-title mb-0 mt-1">Students by status</h4>
                </div>
                <span class="dashboard-stat-chip"><i class="ri-team-line text-primary"></i>{{ number_format($studentsCount) }} students</span>
            </div>
            <div class="card-body">
                <div id="student-status-chart" class="dashboard-chart" role="img" aria-label="Student count by status"></div>
            </div>
        </div>
    </div>
</div>

{{-- Insight Row 2: Detailed Lists & Activity Feed --}}
<div class="row">
    <div class="col-lg-8">
        <div class="row">
            <div class="col-lg-6">
                <div class="card card-height-100">
                    <div class="card-header d-flex align-items-center">
                        <h4 class="card-title mb-0 flex-grow-1"><i class="ri-arrow-down-circle-line me-2 text-danger"></i>Most Difficult Exams</h4>
                    </div>
                    <div class="card-body">
                        <ul class="list-group list-group-flush insight-list">
                            @forelse($difficultExams as $exam)
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <div>
                                    <i class="ri-arrow-down-circle-line text-danger align-middle me-2"></i>
                                    <span class="fw-medium">{{ $exam->exam->name ?? 'Exam removed' }}</span>
                                </div>
                                <span class="badge bg-danger-subtle text-danger">{{ number_format($exam->average_percentage, 2) }}% Avg</span>
                            </li>
                            @empty
                            <li class="list-group-item text-muted text-center">No exam results to analyze yet.</li>
                            @endforelse
                        </ul>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="card card-height-100">
                    <div class="card-header d-flex align-items-center">
                        <h4 class="card-title mb-0 flex-grow-1"><i class="ri-shopping-bag-3-line me-2 text-success"></i>Top Selling Packages</h4>
                    </div>
                    <div class="card-body">
                        <ul class="list-group list-group-flush insight-list">
                            @forelse($topPackages as $orderItem)
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <div>
                                    <i class="ri-shopping-bag-3-line text-success align-middle me-2"></i>
                                    <span class="fw-medium">{{ $orderItem->package->name ?? 'Package Deleted' }}</span>
                                </div>
                                <span class="badge bg-success-subtle text-success">{{ $orderItem->sales_count }} Sales</span>
                            </li>
                            @empty
                            <li class="list-group-item text-muted text-center">No sales data available.</li>
                            @endforelse
                        </ul>
                    </div>
                </div>
            </div>
            <div class="col-lg-12 mt-3">
                 <div class="card card-height-100">
                    <div class="card-header d-flex align-items-center">
                        <h4 class="card-title mb-0 flex-grow-1"><i class="ri-error-warning-line me-2 text-warning"></i>Problematic Questions</h4>
                    </div>
                    <div class="card-body">
                        <ul class="list-group list-group-flush insight-list">
                            @forelse($problematicQuestions as $stat)
                            <li class="list-group-item">
                                <a href="{{ route('questions.edit', $stat->question_id) }}" class="text-body d-block">
                                    <i class="ri-error-warning-line text-warning align-middle me-2"></i>
                                    <span class="fw-medium">{{ Str::limit(strip_tags($stat->question->question), 40) }}</span>
                                </a>
                                <small class="text-muted ms-4">{{ $stat->wrong_answers }} wrong out of {{ $stat->total_attempts }} attempts</small>
                            </li>
                            @empty
                            <li class="list-group-item text-muted text-center">No question data to analyze.</li>
                            @endforelse
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card card-height-100">
            <div class="card-header">
                <h4 class="card-title mb-0"><i class="ri-pulse-line me-2 text-primary"></i>Recent Activity</h4>
            </div>
            <div class="card-body p-0">
                <div class="list-group list-group-flush">
                    @forelse($activityFeed as $activity)
                        <div class="list-group-item list-group-item-action">
                            <div class="d-flex align-items-center">
                                <div class="avatar-sm flex-shrink-0">
                                    @php
                                        $color = $activity->color ?? 'secondary';
                                        $icon = $activity->icon ?? 'ri-question-mark';
                                    @endphp
                                    <span class="avatar-title bg-{{$color}}-subtle text-{{$color}} rounded-circle">
                                        <i class="{{ $icon }}"></i>
                                    </span>
                                </div>
                                <div class="flex-grow-1 ms-3">
                                    <p class="mb-1">{{ $activity->description ?? 'No description available.' }}</p>
                                    <small class="text-muted">{{ isset($activity->timestamp) ? \Carbon\Carbon::parse($activity->timestamp)->diffForHumans() : 'Just now' }}</small>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="text-center text-muted p-4">No recent activity.</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>

@endsection
@section('script')
<script src="{{ URL::asset('build/libs/echarts/echarts.min.js') }}"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const chartInstances = [];

        document.querySelectorAll('[data-sortable-table]').forEach(function (table) {
            const body = table.tBodies[0];
            if (!body) return;
            table.querySelectorAll('thead th').forEach(function (header, index) {
                header.dataset.sortHeader = 'true';
                header.tabIndex = 0;
                header.insertAdjacentHTML('beforeend', '<i class="ri-expand-up-down-line dashboard-sort-icon" aria-hidden="true"></i>');

                const sortRows = function () {
                    const ascending = header.dataset.direction !== 'asc';
                    table.querySelectorAll('thead th').forEach(function (item) {
                        item.dataset.direction = '';
                        const icon = item.querySelector('.dashboard-sort-icon');
                        if (icon) icon.className = 'ri-expand-up-down-line dashboard-sort-icon';
                    });
                    header.dataset.direction = ascending ? 'asc' : 'desc';
                    const activeIcon = header.querySelector('.dashboard-sort-icon');
                    if (activeIcon) activeIcon.className = (ascending ? 'ri-arrow-up-line' : 'ri-arrow-down-line') + ' dashboard-sort-icon';
                    const rows = Array.from(body.rows).filter(function (row) { return row.cells.length > 1; });
                    rows.sort(function (left, right) {
                        const leftText = (left.cells[index]?.dataset.sortValue || left.cells[index]?.textContent || '').trim();
                        const rightText = (right.cells[index]?.dataset.sortValue || right.cells[index]?.textContent || '').trim();
                        if (index > 0) {
                            return ((parseFloat(leftText.replace(/,/g, '')) || 0) - (parseFloat(rightText.replace(/,/g, '')) || 0)) * (ascending ? 1 : -1);
                        }
                        return leftText.localeCompare(rightText, undefined, { numeric: true, sensitivity: 'base' }) * (ascending ? 1 : -1);
                    });
                    rows.forEach(function (row) { body.appendChild(row); });
                };

                header.addEventListener('click', sortRows);
                header.addEventListener('keydown', function (event) {
                    if (event.key === 'Enter' || event.key === ' ') {
                        event.preventDefault();
                        sortRows();
                    }
                });

            });
        });
        function cssVar(name, fallback) {
            return getComputedStyle(document.documentElement).getPropertyValue(name).trim() || fallback;
        }

        function themeColors() {
            return {
                primary: cssVar('--vz-primary', '#0f766e'),
                secondary: cssVar('--vz-warning', '#f59e0b'),
                text: cssVar('--vz-body-color', '#475569'),
                muted: cssVar('--vz-secondary-color', '#94a3b8'),
                border: cssVar('--vz-border-color', '#e2e8f0'),
                surface: cssVar('--vz-secondary-bg', '#ffffff'),
                success: cssVar('--vz-success', '#16a34a'),
                info: cssVar('--vz-info', '#0284c7'),
                danger: cssVar('--vz-danger', '#dc2626')
            };
        }

        function emptyOption(message, colors) {
            return {
                animation: false,
                title: {
                    text: message,
                    left: 'center',
                    top: 'middle',
                    textStyle: { color: colors.muted, fontSize: 14, fontWeight: 500 }
                }
            };
        }

        function registerChart(id, optionFactory) {
            const element = document.getElementById(id);
            if (!element) return;

            const chart = echarts.init(element);
            chart.setOption(optionFactory(themeColors()));
            chartInstances.push({ chart: chart, optionFactory: optionFactory });
        }

        function renderCharts() {
            chartInstances.splice(0).forEach(function (entry) {
                entry.chart.dispose();
            });

            const activityTrend = @json($activityTrend);
            registerChart('activity-trend-chart', function (colors) {
                const hasData = (activityTrend.students || []).some(Number) || (activityTrend.attempts || []).some(Number);
                if (!hasData) return emptyOption('Activity will appear as students register and complete exams.', colors);

                return {
                    color: [colors.primary, colors.secondary],
                    tooltip: { trigger: 'axis', axisPointer: { type: 'cross' } },
                    legend: { top: 0, textStyle: { color: colors.text } },
                    grid: { left: 44, right: 24, top: 52, bottom: 34, containLabel: true },
                    xAxis: {
                        type: 'category',
                        boundaryGap: false,
                        data: activityTrend.labels || [],
                        axisLine: { lineStyle: { color: colors.border } },
                        axisLabel: { color: colors.muted }
                    },
                    yAxis: {
                        type: 'value',
                        minInterval: 1,
                        splitLine: { lineStyle: { color: colors.border, type: 'dashed' } },
                        axisLabel: { color: colors.muted }
                    },
                    series: [
                        {
                            name: 'New students',
                            type: 'line',
                            smooth: true,
                            symbolSize: 7,
                            data: activityTrend.students || [],
                            lineStyle: { width: 3 },
                            areaStyle: { opacity: .12 }
                        },
                        {
                            name: 'Completed attempts',
                            type: 'line',
                            smooth: true,
                            symbolSize: 7,
                            data: activityTrend.attempts || [],
                            lineStyle: { width: 3 }
                        }
                    ]
                };
            });

            const questionCounts = @json($questionCounts);
            const questionData = questionCounts
                .map(function (item) {
                    return {
                        name: item.qtype && item.qtype.question_type ? item.qtype.question_type : 'Unclassified',
                        value: Number(item.total || 0)
                    };
                })
                .sort(function (a, b) { return a.value - b.value; });

            registerChart('question-type-chart', function (colors) {
                if (!questionData.length) return emptyOption('No question data available yet.', colors);

                return {
                    color: [colors.primary],
                    tooltip: { trigger: 'axis', axisPointer: { type: 'shadow' } },
                    grid: { left: window.innerWidth < 768 ? 96 : 150, right: 36, top: 12, bottom: 24, containLabel: false },
                    xAxis: {
                        type: 'value',
                        minInterval: 1,
                        splitLine: { lineStyle: { color: colors.border, type: 'dashed' } },
                        axisLabel: { color: colors.muted }
                    },
                    yAxis: {
                        type: 'category',
                        data: questionData.map(function (item) { return item.name; }),
                        axisLine: { show: false },
                        axisTick: { show: false },
                        axisLabel: {
                            color: colors.text,
                            width: window.innerWidth < 768 ? 84 : 138,
                            overflow: 'truncate'
                        }
                    },
                    series: [{
                        name: 'Questions',
                        type: 'bar',
                        barMaxWidth: 22,
                        data: questionData.map(function (item) { return item.value; }),
                        itemStyle: { borderRadius: [0, 6, 6, 0] },
                        label: { show: true, position: 'right', color: colors.text, fontWeight: 700 }
                    }]
                };
            });

            const studentStatuses = @json($studentStatusCounts);
            const statusData = studentStatuses
                .map(function (item) {
                    return { name: item.status || 'Unknown', value: Number(item.total || 0) };
                })
                .sort(function (a, b) { return a.value - b.value; });

            registerChart('student-status-chart', function (colors) {
                if (!statusData.length) return emptyOption('No student status data available yet.', colors);

                return {
                    color: [colors.secondary],
                    tooltip: { trigger: 'axis', axisPointer: { type: 'shadow' } },
                    grid: { left: 92, right: 36, top: 12, bottom: 24 },
                    xAxis: {
                        type: 'value',
                        minInterval: 1,
                        splitLine: { lineStyle: { color: colors.border, type: 'dashed' } },
                        axisLabel: { color: colors.muted }
                    },
                    yAxis: {
                        type: 'category',
                        data: statusData.map(function (item) { return item.name; }),
                        axisLine: { show: false },
                        axisTick: { show: false },
                        axisLabel: { color: colors.text, width: 80, overflow: 'truncate' }
                    },
                    series: [{
                        name: 'Students',
                        type: 'bar',
                        barMaxWidth: 25,
                        data: statusData.map(function (item) { return item.value; }),
                        itemStyle: { borderRadius: [0, 6, 6, 0] },
                        label: { show: true, position: 'right', color: colors.text, fontWeight: 700 }
                    }]
                };
            });
        }

        let resizeTimer;
        window.addEventListener('resize', function () {
            clearTimeout(resizeTimer);
            resizeTimer = setTimeout(function () {
                chartInstances.forEach(function (entry) { entry.chart.resize(); });
            }, 120);
        });

        const themeObserver = new MutationObserver(function (mutations) {
            if (mutations.some(function (mutation) { return mutation.attributeName === 'data-bs-theme'; })) {
                renderCharts();
            }
        });
        themeObserver.observe(document.documentElement, { attributes: true });

        renderCharts();
    });
</script>
@endsection

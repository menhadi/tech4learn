@extends('layouts.master')

@section('title', 'Batch Analytics: ' . $exam->name)

@section('css')
<style>
    @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap');

    :root {
        --bz-dark: #1e293b;
        --bz-grey: #64748b;
        --bz-bg: #f8fafc;
        --bz-card-bg: #ffffff;
        --bz-primary: #6366f1;
        --bz-success: #10b981;
        --bz-info: #3b82f6;
        --bz-warning: #f59e0b;
        --bz-danger: #ef4444;
    }

    body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: var(--bz-bg); }

    /* CARD STYLING */
    .analytic-card {
        background: var(--bz-card-bg);
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.05);
        height: 100%;
        display: flex;
        flex-direction: column;
        transition: transform 0.2s, box-shadow 0.2s;
    }
    .analytic-card:hover { transform: translateY(-2px); box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1); border-color: #cbd5e1; }

    .card-header-clean {
        padding: 16px 20px;
        border-bottom: 1px solid #f1f5f9;
        display: flex; align-items: center; justify-content: space-between;
        background: #fff;
        border-radius: 12px 12px 0 0;
    }
    .header-title { font-size: 14px; font-weight: 700; color: var(--bz-dark); display: flex; align-items: center; gap: 8px; margin: 0; }

    /* KPI BOXES */
    .kpi-row { display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin-bottom: 24px; }
    .kpi-card { background: #fff; padding: 20px; border-radius: 12px; border: 1px solid #e2e8f0; box-shadow: 0 1px 2px 0 rgba(0,0,0,0.05); text-align: center; }
    .kpi-val { font-size: 24px; font-weight: 800; color: var(--bz-dark); margin-bottom: 4px; line-height: 1; }
    .kpi-lbl { font-size: 11px; text-transform: uppercase; font-weight: 700; color: var(--bz-grey); letter-spacing: 0.5px; }
    
    /* SCORE DISTRIBUTION (FIXED ALIGNMENT) */
    .dist-row { display: flex; align-items: center; margin-bottom: 12px; }
    .dist-row:last-child { margin-bottom: 0; }
    .dist-label { width: 60px; font-size: 12px; font-weight: 600; color: var(--bz-dark); }
    .dist-track { flex: 1; height: 8px; background: #f1f5f9; border-radius: 4px; margin: 0 12px; overflow: hidden; }
    .dist-bar { height: 100%; border-radius: 4px; }
    .dist-val { width: 30px; text-align: right; font-size: 12px; font-weight: 700; color: var(--bz-grey); }

    /* KILLER QUESTIONS */
    .killer-item { padding: 16px 20px; border-bottom: 1px solid #f1f5f9; display: flex; gap: 16px; align-items: start; }
    .killer-badge { 
        background: #fef2f2; color: var(--bz-danger); border: 1px solid #fee2e2;
        border-radius: 8px; padding: 6px 10px; text-align: center; min-width: 60px;
    }
    .kb-val { display: block; font-weight: 800; font-size: 14px; line-height: 1; }
    .kb-lbl { display: block; font-size: 9px; text-transform: uppercase; font-weight: 700; margin-top: 2px; }
    .killer-content { flex: 1; }
    .math-box { background: #f8fafc; border: 1px solid #e2e8f0; padding: 8px 12px; border-radius: 6px; font-size: 14px; color: #334155; margin-bottom: 6px; }

    /* TOPIC PILLS (FIXED SPACING) */
    .topic-container { padding: 20px; display: flex; flex-wrap: wrap; gap: 8px; }
    .topic-pill {
        display: inline-flex; align-items: center; gap: 6px;
        padding: 6px 12px; border-radius: 20px; font-size: 12px; font-weight: 600;
        border: 1px solid transparent;
    }
    .tp-weak { background: #fef2f2; color: #b91c1c; border-color: #fecaca; }
    .tp-avg { background: #fffbeb; color: #b45309; border-color: #fde68a; }
    .tp-strong { background: #f0fdf4; color: #15803d; border-color: #bbf7d0; }
    .tp-dot { width: 6px; height: 6px; border-radius: 50%; background: currentColor; }

    /* STUDENT TABLE (SOFT BUTTONS) */
    .custom-table { width: 100%; border-collapse: collapse; }
    .custom-table th { 
        text-align: left; padding: 12px 20px; font-size: 11px; text-transform: uppercase; 
        color: var(--bz-grey); font-weight: 700; border-bottom: 1px solid #e2e8f0; background: #f8fafc;
    }
    .custom-table td { padding: 16px 20px; border-bottom: 1px solid #f1f5f9; font-size: 13px; color: var(--bz-dark); vertical-align: middle; }
    .custom-table tr:last-child td { border-bottom: none; }
    
    .btn-view {
        padding: 6px 12px; font-size: 12px; font-weight: 600; border-radius: 6px;
        background: #e0e7ff; color: #4338ca; border: 1px solid transparent; text-decoration: none; display: inline-block;
        transition: all 0.2s;
    }
    .btn-view:hover { background: #4338ca; color: #fff; }

    /* Responsive */
    @media (max-width: 992px) { .kpi-row { grid-template-columns: 1fr 1fr; } }
    mjx-container { font-size: 110% !important; color: #334155 !important; }
</style>
@endsection

@section('content')
@component('components.breadcrumb')
@slot('li_1', 'Exams')
@slot('title', 'Analytics')
@endcomponent

<div class="row mb-4 align-items-center">
    <div class="col-md-8">
        <h4 class="fw-bold text-dark mb-1">{{ $exam->name }}</h4>
        <div class="d-flex gap-3 text-muted fs-13 mt-1">
            <span><i class="ri-calendar-line me-1"></i> {{ $exam->start_date->format('d M, Y') }}</span>
            <span><i class="ri-user-line me-1"></i> {{ $batchStats['total_attempts'] }} Attempts</span>
        </div>
    </div>
    <div class="col-md-4 text-md-end mt-3 mt-md-0">
        <a href="{{ route('exams.index') }}" class="btn btn-light border fw-medium"><i class="ri-arrow-left-line me-1"></i> Back</a>
    </div>
</div>

{{-- 1. KPI STATS --}}
<div class="kpi-row">
    <div class="kpi-card">
        <div class="kpi-val text-primary">{{ number_format($batchStats['avg_score'], 1) }}%</div>
        <div class="kpi-lbl">Batch Avg</div>
    </div>
    <div class="kpi-card">
        <div class="kpi-val text-success">{{ number_format($batchStats['highest_score'], 1) }}%</div>
        <div class="kpi-lbl">Highest Score</div>
    </div>
    <div class="kpi-card">
        <div class="kpi-val text-danger">{{ number_format($batchStats['pass_count']) }}</div>
        <div class="kpi-lbl">Total Passed</div>
    </div>
    <div class="kpi-card">
        <div class="kpi-val text-warning">{{ $batchStats['avg_time'] }}m</div>
        <div class="kpi-lbl">Avg Time</div>
    </div>
</div>

<div class="row g-4">
    {{-- 2. KILLER QUESTIONS --}}
    <div class="col-lg-7">
        <div class="analytic-card">
            <div class="card-header-clean">
                <h6 class="header-title text-danger"><i class="ri-alarm-warning-fill me-2"></i> Killer Questions (< 40%)</h6>
            </div>
            <div class="card-body p-0">
                @if($killerQuestions->isNotEmpty())
                    @foreach($killerQuestions as $q)
                    <div class="killer-item">
                        <div class="killer-badge">
                            <span class="kb-val">{{ $q->accuracy }}%</span>
                            <span class="kb-lbl">Correct</span>
                        </div>
                        <div class="killer-content">
                            <div class="math-box">{!! $q->question !!}</div>
                            <div class="text-muted fs-12"><i class="ri-user-unfollow-line me-1"></i> {{ $q->total_attempts - $q->correct_count }} students failed this</div>
                        </div>
                    </div>
                    @endforeach
                    @if($killerQuestions->hasPages())
                        <div class="p-3 border-top bg-light">
                            {{ $killerQuestions->appends(request()->except('killer_page'))->links('pagination::bootstrap-5') }}
                        </div>
                    @endif
                @else
                    <div class="text-center py-5">
                        <i class="ri-checkbox-circle-fill fs-1 text-success opacity-50"></i>
                        <p class="text-muted mt-2 fw-medium">No critical questions found. Good job!</p>
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- 3. DISTRIBUTION & TOPICS --}}
    <div class="col-lg-5">
        {{-- Distribution --}}
        <div class="analytic-card mb-4" style="height: auto;">
            <div class="card-header-clean">
                <h6 class="header-title"><i class="ri-bar-chart-horizontal-line me-2"></i> Score Distribution</h6>
            </div>
            <div class="card-body p-4">
                @php $total = $batchStats['total_attempts'] > 0 ? $batchStats['total_attempts'] : 1; @endphp
                
                <div class="dist-row">
                    <div class="dist-label">0-30%</div>
                    <div class="dist-track"><div class="dist-bar bg-danger" style="width: {{ ($distribution['0-30%']/$total)*100 }}%"></div></div>
                    <div class="dist-val">{{ $distribution['0-30%'] }}</div>
                </div>
                <div class="dist-row">
                    <div class="dist-label">31-60%</div>
                    <div class="dist-track"><div class="dist-bar bg-warning" style="width: {{ ($distribution['31-60%']/$total)*100 }}%"></div></div>
                    <div class="dist-val">{{ $distribution['31-60%'] }}</div>
                </div>
                <div class="dist-row">
                    <div class="dist-label">61-80%</div>
                    <div class="dist-track"><div class="dist-bar bg-info" style="width: {{ ($distribution['61-80%']/$total)*100 }}%"></div></div>
                    <div class="dist-val">{{ $distribution['61-80%'] }}</div>
                </div>
                <div class="dist-row">
                    <div class="dist-label">81-100%</div>
                    <div class="dist-track"><div class="dist-bar bg-success" style="width: {{ ($distribution['81-100%']/$total)*100 }}%"></div></div>
                    <div class="dist-val">{{ $distribution['81-100%'] }}</div>
                </div>
            </div>
        </div>

        {{-- Topic Health --}}
        <div class="analytic-card" style="height: auto;">
            <div class="card-header-clean">
                <h6 class="header-title"><i class="ri-price-tag-3-line me-2"></i> Topic Health</h6>
            </div>
            <div class="topic-container">
                @if($topicPerformance->isNotEmpty())
                    @foreach($topicPerformance as $topic)
                        @php
                            $cls = 'tp-weak'; 
                            if($topic->accuracy > 40) $cls = 'tp-avg';
                            if($topic->accuracy > 75) $cls = 'tp-strong';
                        @endphp
                        <span class="topic-pill {{ $cls }}">
                            <span class="tp-dot"></span> {{ $topic->name }} ({{ $topic->accuracy }}%)
                        </span>
                    @endforeach
                @else
                    <p class="text-center w-100 text-muted fs-12 mb-0">No topic data available.</p>
                @endif
            </div>
        </div>
    </div>
</div>

{{-- 4. AT RISK STUDENTS --}}
<div class="row mt-4">
    <div class="col-12">
        <div class="analytic-card" style="height: auto;">
            <div class="card-header-clean">
                <h6 class="header-title text-danger"><i class="ri-error-warning-fill me-2"></i> Intervention Needed</h6>
            </div>
            <div class="table-responsive">
                <table class="custom-table">
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Enrollment</th>
                            <th>Score</th>
                            <th>Time Spent</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($atRiskStudents as $student)
                        <tr>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <div class="avatar-xs bg-danger-subtle text-danger rounded-circle d-flex align-items-center justify-content-center fw-bold">
                                        {{ substr($student->student->name ?? 'S', 0, 1) }}
                                    </div>
                                    <span class="fw-bold">{{ $student->student->name }}</span>
                                </div>
                            </td>
                            <td class="text-muted">{{ $student->student->enroll }}</td>
                            <td><span class="badge bg-danger-subtle text-danger border border-danger-subtle px-2">{{ number_format($student->percent, 1) }}%</span></td>
                            <td class="text-muted">{{ gmdate("i:s", $student->total_test_time) }}m</td>
                            <td class="text-end">
                                <a href="{{ route('results.view', $student->id) }}" class="btn-view">View Report</a>
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="5" class="text-center py-4 text-muted">Everyone is safe! 🎉</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($atRiskStudents->hasPages())
                <div class="p-3 border-top d-flex justify-content-end">
                    {{ $atRiskStudents->appends(request()->except('risk_page'))->links('pagination::bootstrap-5') }}
                </div>
            @endif
        </div>
    </div>
</div>

@endsection

@section('script')
<script>
    window.MathJax = { tex: { inlineMath: [['$', '$'], ['\\(', '\\)']] }, svg: { fontCache: 'global' }, startup: { typeset: true } };
</script>
<script id="MathJax-script" async src="https://cdn.jsdelivr.net/npm/mathjax@3/es5/tex-mml-chtml.js"></script>
@endsection
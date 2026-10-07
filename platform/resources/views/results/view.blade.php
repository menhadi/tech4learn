@extends('layouts.master')

@section('title', 'Detailed Analysis: ' . ($exam->name ?? 'Exam Result'))

@section('css')
<style>
    /* --- PREMIUM DASHBOARD VARIABLES --- */
    :root {
        --bz-primary: #405189; --bz-secondary: #3577f1; --bz-success: #0ab39c;
        --bz-danger: #f06548; --bz-warning: #f7b84b; --bz-info: #299cdb;
        --bz-dark: #212529; --bz-light: #f3f6f9; --bz-card-border: #e9ebec;
    }

    /* CARD STYLING */
    .premium-card {
        border: 1px solid var(--bz-card-border);
        box-shadow: 0 1px 2px rgba(56, 65, 74, 0.05);
        border-radius: 8px; background: #fff; margin-bottom: 24px;
        transition: transform 0.2s ease-in-out;
        overflow: hidden;
    }
    .premium-header {
        padding: 16px 20px; border-bottom: 1px solid var(--bz-card-border);
        background: linear-gradient(to right, #fff, rgba(243, 246, 249, 0.4));
        display: flex; align-items: center; justify-content: space-between;
    }
    .premium-title { font-size: 15px; font-weight: 700; color: var(--bz-dark); text-transform: uppercase; letter-spacing: 0.5px; margin: 0; }

    /* PERCENTILE GAUGE */
    .percentile-ring {
        width: 100px; height: 100px; border-radius: 50%; margin: 0 auto 10px;
        background: conic-gradient(var(--bz-primary) var(--p, 0%), #e9ecef 0);
        display: flex; align-items: center; justify-content: center;
        position: relative; box-shadow: 0 4px 12px rgba(64, 81, 137, 0.15);
    }
    .percentile-ring::before {
        content: ''; position: absolute; width: 82px; height: 82px; background: #fff; border-radius: 50%;
    }
    .percentile-content { position: relative; z-index: 2; text-align: center; }
    .p-val { font-size: 22px; font-weight: 800; color: var(--bz-primary); display: block; line-height: 1; }
    .p-lbl { font-size: 9px; font-weight: 700; text-transform: uppercase; color: #878a99; }

    /* TIME MATRIX GRID */
    .time-matrix { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; background: #f3f6f9; padding: 8px; border-radius: 8px; }
    .tm-box { background: #fff; padding: 12px; border-radius: 6px; text-align: center; box-shadow: 0 1px 2px rgba(0,0,0,0.03); }
    .tm-val { font-size: 18px; font-weight: 800; display: block; margin-bottom: 2px; }
    .tm-lbl { font-size: 10px; font-weight: 700; text-transform: uppercase; opacity: 0.7; }
    .tm-desc { font-size: 9px; color: #878a99; display: block; margin-top: 2px; }
    
    .border-l-success { border-left: 3px solid var(--bz-success); }
    .border-l-danger { border-left: 3px solid var(--bz-danger); }
    .border-l-warning { border-left: 3px solid var(--bz-warning); }
    .border-l-info { border-left: 3px solid var(--bz-info); }

    /* TOPIC STRENGTH LIST */
    .topic-item { padding: 10px 0; border-bottom: 1px dashed #eff2f7; }
    .topic-item:last-child { border-bottom: none; margin-bottom: 0; }
    .topic-info { display: flex; justify-content: space-between; font-size: 13px; font-weight: 600; margin-bottom: 6px; color: #495057; }
    .progress-thin { height: 8px; border-radius: 4px; background: #f3f6f9; overflow: hidden; }
    
    /* NAVIGATION PILLS */
    .nav-custom-outline .nav-link {
        border: 1px solid #e9ebec; color: #495057; margin-right: 5px; background: #fff; transition: all 0.2s;
    }
    .nav-custom-outline .nav-link:hover { background-color: #f3f6f9; }
    .nav-custom-outline .nav-link.active {
        background-color: var(--bz-primary); color: #fff; border-color: var(--bz-primary);
    }
    .nav-custom-outline .nav-link.active .badge {
        background-color: rgba(255,255,255,0.2) !important; color: #fff !important;
    }

    /* TABLE STYLES */
    .solution-table th { background: #f8f9fa; font-size: 12px; text-transform: uppercase; color: #495057; font-weight: 700; box-shadow: 0 2px 2px -1px rgba(0, 0, 0, 0.05); padding: 12px; }
    .status-badge { font-size: 11px; padding: 6px 10px; border-radius: 4px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; }
    .badge-correct { background: rgba(10, 179, 156, 0.1); color: #0ab39c; }
    .badge-wrong { background: rgba(240, 101, 72, 0.1); color: #f06548; }
    .badge-skip { background: rgba(247, 184, 75, 0.1); color: #f7b84b; }
    .badge-pending { background: rgba(255, 244, 222, 1); color: #b45309; } 

    /* QUESTION CONTENT STYLING (FIXED VISIBILITY) */
    .q-text p { margin-bottom: 0; } 
    .q-text img { max-width: 100%; height: auto; border-radius: 4px; margin-top: 5px; }
    
    /* Options Styling - Cleaner Look */
    .option-box {
        background: #fff;
        border: 1px solid #e2e5e8;
        padding: 8px 12px;
        border-radius: 6px;
        font-size: 13px;
        color: #212529; /* Dark text for visibility */
        display: flex;
        align-items: center;
        transition: all 0.2s;
    }
    .option-box:hover {
        background: #f8f9fa;
        border-color: #ced4da;
    }
    .option-idx {
        width: 24px; height: 24px;
        background: #eff2f7; color: #495057;
        border-radius: 50%;
        display: flex; align-items: center; justify-content: center;
        font-weight: 700; font-size: 11px; margin-right: 10px; flex-shrink: 0;
    }

    /* SCROLLBAR & IMAGES */
    .custom-scroll::-webkit-scrollbar { width: 5px; }
    .custom-scroll::-webkit-scrollbar-track { background: #f1f1f1; }
    .custom-scroll::-webkit-scrollbar-thumb { background: #d1d5db; border-radius: 4px; }
    .proctor-img { height: 120px; width: 100%; object-fit: cover; border-radius: 6px; border: 1px solid #dee2e6; transition: transform 0.2s; cursor: pointer; }
    .proctor-img:hover { transform: scale(1.05); }
    .admin-ai-insight { border-left: 5px solid var(--el-primary, #0f766e); }
    .admin-ai-insight-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 14px; }
    .admin-ai-insight-panel { border: 1px solid var(--el-border, #d7e2df); border-radius: 8px; background: var(--el-surface-muted, #f8fafc); padding: 14px; }
    .admin-ai-insight-panel h6 { color: var(--el-primary, #0f766e); font-weight: 800; }
    .admin-ai-insight-panel ul { padding-left: 18px; margin-bottom: 0; color: #475569; }
    .admin-ai-insight-panel li + li { margin-top: 6px; }
    .admin-ai-data-points { display: flex; flex-wrap: wrap; gap: 8px; margin: 0 0 18px; }
    .admin-ai-data-point {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        border: 1px solid var(--el-border, #d7e2df);
        background: var(--el-primary-soft, #e6f4f1);
        color: var(--el-primary, #0f766e);
        border-radius: 999px;
        padding: 6px 10px;
        font-size: 12px;
        font-weight: 800;
    }
    @media (max-width: 767.98px) { .admin-ai-insight-grid { grid-template-columns: 1fr; } }
</style>
@endsection

@section('content')
@component('components.breadcrumb')
@slot('li_1', 'Results')
@slot('title', 'Student Performance Report')
@endcomponent

{{-- ✅ PENDING ALERT BANNER --}}
@if($result->result === 'Pending')
<div class="alert alert-warning d-flex align-items-center mb-3 border-0 shadow-sm" role="alert">
    <i class="ri-alert-line fs-1 me-3"></i>
    <div class="flex-grow-1">
        <h6 class="alert-heading fw-bold mb-1">Result Evaluation Pending</h6>
        <p class="mb-0 fs-13">This student has subjective questions that require manual grading. The score shown below is provisional (MCQ only).</p>
    </div>
    <div class="flex-shrink-0 ms-3">
        <a href="{{ route('results.evaluate', $result->id) }}" class="btn btn-dark btn-sm fw-medium shadow-sm">
            <i class="ri-edit-circle-line me-1"></i> Grade Now
        </a>
    </div>
</div>
@endif

{{-- 1. HEADER SUMMARY CARD --}}
<div class="row">
    <div class="col-12">
        <div class="premium-card">
            <div class="card-body p-4">
                <div class="row align-items-center">
                    {{-- Student Info --}}
                    <div class="col-lg-4 border-end border-light">
                        <div class="d-flex align-items-center">
                            <div class="avatar-lg me-3">
                                <span class="avatar-title rounded-circle bg-primary-subtle text-primary fs-1">
                                    {{ substr($result->student->name ?? 'S', 0, 1) }}
                                </span>
                            </div>
                            <div>
                                <h4 class="mb-1">{{ $result->student->name ?? 'N/A' }}</h4>
                                <p class="text-muted mb-1"><i class="ri-id-card-line me-1"></i> ID: {{ $result->student->enroll ?? 'N/A' }}</p>
                                
                                @if($result->result === 'Pass')
                                    <span class="badge bg-success px-3 py-1 fs-11 text-uppercase">PASS</span>
                                @elseif($result->result === 'Fail')
                                    <span class="badge bg-danger px-3 py-1 fs-11 text-uppercase">FAIL</span>
                                @else
                                    <span class="badge bg-warning text-dark px-3 py-1 fs-11 text-uppercase">PENDING</span>
                                @endif
                            </div>
                        </div>
                    </div>
                    {{-- Exam Info --}}
                    <div class="col-lg-4 border-end border-light px-lg-5">
                        <h6 class="text-uppercase text-muted fs-11 fw-bold mb-2">Exam Details</h6>
                        <h5 class="mb-2 text-dark">{{ $result->exam->name ?? 'Unknown Exam' }}</h5>
                        <div class="d-flex justify-content-between text-muted fs-12 mt-3">
                            <span><i class="ri-calendar-line me-1"></i> {{ $result->created_at->format('d M, Y') }}</span>
                            <span><i class="ri-time-line me-1"></i> {{ $formattedTestTime }} Taken</span>
                        </div>
                    </div>
                    {{-- Actions --}}
                    <div class="col-lg-4 text-lg-end mt-3 mt-lg-0">
                        <h4 class="fw-bold text-primary mb-0">{{ number_format($result->obtained_marks, 2) }} <span class="text-muted fs-14 fw-normal">/ {{ number_format($result->total_marks, 2) }}</span></h4>
                        <p class="text-muted fs-12 mb-3">Total Score Obtained</p>
                        <div class="d-flex gap-2 justify-content-lg-end">
                            <a href="{{ route('results.feedback', $result->id) }}" class="btn el-btn-secondary btn-sm"><i class="ri-chat-smile-2-line me-1"></i> Feedback</a>
                            <a href="{{ route('results.index') }}" class="btn btn-soft-secondary btn-sm"><i class="ri-arrow-left-line me-1"></i> Back</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@if(!empty($performanceInsight))
<div class="premium-card admin-ai-insight">
    <div class="premium-header">
        <h6 class="premium-title">{{ ($performanceInsight['source'] ?? 'rules') === 'ai' ? 'AI Performance Analysis' : 'Performance Guidance' }}</h6>
    </div>
    <div class="card-body p-4">
        <p class="mb-4 text-muted">{{ $performanceInsight['summary'] }}</p>
        @if(!empty($performanceInsight['data_points']))
            <div class="admin-ai-data-points" aria-label="Analysis data points">
                @foreach($performanceInsight['data_points'] as $point)
                    <span class="admin-ai-data-point"><i class="ri-bar-chart-2-line"></i>{{ $point }}</span>
                @endforeach
            </div>
        @endif
        <div class="admin-ai-insight-grid">
            <div class="admin-ai-insight-panel">
                <h6><i class="ri-shield-check-line me-1"></i> Strengths</h6>
                <ul>
                    @foreach(($performanceInsight['strengths'] ?? []) as $item)
                        <li>{{ $item }}</li>
                    @endforeach
                </ul>
            </div>
            <div class="admin-ai-insight-panel">
                <h6><i class="ri-focus-3-line me-1"></i> Weak Areas</h6>
                <ul>
                    @foreach(($performanceInsight['weaknesses'] ?? []) as $item)
                        <li>{{ $item }}</li>
                    @endforeach
                </ul>
            </div>
            <div class="admin-ai-insight-panel">
                <h6><i class="ri-route-line me-1"></i> Improvement Plan</h6>
                <ul>
                    @foreach(($performanceInsight['improvements'] ?? []) as $item)
                        <li>{{ $item }}</li>
                    @endforeach
                </ul>
            </div>
        </div>
    </div>
</div>
@endif

{{-- 2. ANALYTICS ROW --}}
<div class="row">
    {{-- A. Standing --}}
    <div class="col-xl-3 col-md-6">
        <div class="premium-card h-100">
            <div class="premium-header"><h6 class="premium-title">Standing</h6></div>
            <div class="card-body text-center py-4">
                <div class="percentile-ring" style="--p: {{ $comparisonData['percentile'] ?? 0 }}%">
                    <div class="percentile-content">
                        <span class="p-val">{{ $comparisonData['percentile'] ?? 0 }}<small>%</small></span>
                        <span class="p-lbl">Percentile</span>
                    </div>
                </div>
                <div class="mt-3">
                    <span class="badge bg-light text-dark border px-3 py-2 fs-13">
                        <i class="ri-trophy-fill text-warning me-1"></i> Rank: <strong>{{ $comparisonData['rank'] }}</strong> / {{ $comparisonData['total_students'] }}
                    </span>
                </div>
                <p class="text-muted fs-11 mt-2 mb-0">Better than {{ $comparisonData['percentile'] }}% of students</p>
            </div>
        </div>
    </div>

    {{-- B. Time Matrix --}}
    <div class="col-xl-5 col-md-6">
        <div class="premium-card h-100">
            <div class="premium-header">
                <h6 class="premium-title">Time vs Accuracy Matrix</h6>
                <span class="badge bg-light text-muted border">Avg Speed: {{ $globalAvgTimePerQues }}s/Q</span>
            </div>
            <div class="card-body">
                <div class="time-matrix">
                    <div class="tm-box border-l-success">
                        <span class="tm-val text-success">{{ $timeAnalysis['rapid_fire'] }}</span>
                        <span class="tm-lbl">Rapid Fire</span>
                        <span class="tm-desc">Fast & Correct</span>
                    </div>
                    <div class="tm-box border-l-info">
                        <span class="tm-val text-info">{{ $timeAnalysis['struggle_win'] }}</span>
                        <span class="tm-lbl">Deep Thinker</span>
                        <span class="tm-desc">Slow but Correct</span>
                    </div>
                    <div class="tm-box border-l-warning">
                        <span class="tm-val text-warning">{{ $timeAnalysis['careless_mistake'] }}</span>
                        <span class="tm-lbl">Careless Errors</span>
                        <span class="tm-desc">Too Fast & Wrong</span>
                    </div>
                    <div class="tm-box border-l-danger">
                        <span class="tm-val text-danger">{{ $timeAnalysis['wasted_time'] }}</span>
                        <span class="tm-lbl">Time Wasted</span>
                        <span class="tm-desc">Slow & Wrong</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- C. Topic Strength --}}
    <div class="col-xl-4 col-md-12">
        <div class="premium-card h-100">
            <div class="premium-header"><h6 class="premium-title">Topic Strength</h6></div>
            <div class="card-body p-4 custom-scroll" style="max-height: 250px; overflow-y: auto;">
                @if(count($topicAnalysis) > 0)
                    @foreach($topicAnalysis as $topic)
                    <div class="topic-item">
                        <div class="topic-info">
                            <span>{{ Str::limit($topic->name, 25) }}</span>
                            <span class="{{ $topic->status == 'Strong' ? 'text-success' : ($topic->status == 'Weak' ? 'text-danger' : 'text-warning') }}">
                                {{ $topic->status }} ({{ $topic->accuracy }}%)
                            </span>
                        </div>
                        <div class="progress progress-thin">
                            <div class="progress-bar {{ $topic->status == 'Strong' ? 'bg-success' : ($topic->status == 'Weak' ? 'bg-danger' : 'bg-warning') }}" 
                                 role="progressbar" style="width: {{ $topic->accuracy }}%"></div>
                        </div>
                    </div>
                    @endforeach
                @else
                    <div class="text-center py-4 text-muted">
                        <i class="ri-price-tag-3-line fs-1 opacity-50"></i>
                        <p class="mb-0 fs-12 mt-2">No Topic Tags Available</p>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
<br><br>
{{-- 3. DETAILED QUESTION REPORT --}}
<div class="row">
    <div class="col-12">
        <div class="card shadow-none border">
            <div class="card-header bg-light border-bottom">
                <ul class="nav nav-tabs-custom card-header-tabs" role="tablist">
                    <li class="nav-item">
                        <a class="nav-link active" data-bs-toggle="tab" href="#solutionsTab" role="tab">
                            <i class="ri-file-list-3-line me-1 align-middle"></i> Question Report
                        </a>
                    </li>
                    @if(count($proctorImages) > 0)
                    <li class="nav-item">
                        <a class="nav-link" data-bs-toggle="tab" href="#proctorTab" role="tab">
                            <i class="ri-camera-lens-line me-1 align-middle"></i> Proctor Images ({{ count($proctorImages) }})
                        </a>
                    </li>
                    @endif
                </ul>
            </div>
            
            <div class="card-body p-0">
                <div class="tab-content">
                    
                    {{-- Solutions Tab --}}
                    <div class="tab-pane active" id="solutionsTab" role="tabpanel">
                        
                        {{-- Filters & Subject Tabs --}}
                        <div class="p-3 border-bottom bg-white d-flex flex-column flex-md-row justify-content-between gap-3 align-items-center">
                            
                            {{-- Subject Navigation --}}
                            <ul class="nav nav-pills nav-custom-outline gap-2" id="subjectTabs" role="tablist">
                                @php $isFirst = true; @endphp
                                @foreach($subjectNames as $subId => $subName)
                                    <li class="nav-item" role="presentation">
                                        <button class="nav-link {{ $isFirst ? 'active' : '' }} btn-sm fw-semibold" 
                                                id="tab-sub-{{ $subId }}" 
                                                data-bs-toggle="pill" 
                                                data-bs-target="#content-sub-{{ $subId }}" 
                                                type="button" role="tab">
                                            {{ $subName }} 
                                            <span class="badge bg-light text-primary ms-1 border">{{ count($groupedQuestions[$subId] ?? []) }}</span>
                                        </button>
                                    </li>
                                    @php $isFirst = false; @endphp
                                @endforeach
                            </ul>

                            {{-- Status Filters --}}
                            <div class="btn-group btn-group-sm" role="group">
                                <input type="radio" class="btn-check filter-btn" name="qFilter" id="filterAll" value="all" checked>
                                <label class="btn btn-outline-secondary" for="filterAll">All</label>

                                <input type="radio" class="btn-check filter-btn" name="qFilter" id="filterCorrect" value="R">
                                <label class="btn btn-outline-success" for="filterCorrect"><i class="ri-check-line"></i> Correct</label>

                                <input type="radio" class="btn-check filter-btn" name="qFilter" id="filterWrong" value="W">
                                <label class="btn btn-outline-danger" for="filterWrong"><i class="ri-close-line"></i> Wrong</label>

                                <input type="radio" class="btn-check filter-btn" name="qFilter" id="filterSkip" value="S">
                                <label class="btn btn-outline-warning" for="filterSkip"><i class="ri-skip-forward-line"></i> Skipped</label>
                            </div>
                        </div>

                        {{-- Subject Content --}}
                        <div class="tab-content p-0">
                            @php $isFirstContent = true; @endphp
                            @foreach($subjectNames as $subId => $subName)
                                <div class="tab-pane fade {{ $isFirstContent ? 'show active' : '' }}" id="content-sub-{{ $subId }}" role="tabpanel">
                                    <div class="table-responsive custom-scroll" style="max-height: 600px; overflow-y: auto;">
                                        <table class="table table-hover align-middle solution-table mb-0 table-fixed-header">
                                            <thead class="position-sticky top-0 z-1 bg-white">
                                                <tr>
                                                    <th class="text-center" style="width: 60px;">#</th>
                                                    <th>Question & Options</th>
                                                    <th class="text-center" style="width: 140px;">Your Answer</th>
                                                    <th class="text-center" style="width: 140px;">Correct Ans</th>
                                                    <th class="text-center" style="width: 80px;">Time</th>
                                                    <th class="text-center" style="width: 100px;">Status</th>
                                                    <th class="text-end" style="width: 80px;">Marks</th>
                                                </tr>
                                            </thead>
                                            <tbody class="question-list">
                                                @if(isset($groupedQuestions[$subId]))
                                                    @foreach($groupedQuestions[$subId] as $index => $report)
                                                        @php
                                                            $statusClass = $report->ques_status; 
                                                            if(!$report->answered) $statusClass = 'S';
                                                        @endphp
                                                        <tr class="q-row" data-status="{{ $statusClass }}">
                                                            <td class="text-center fw-bold text-muted">{{ $loop->iteration }}</td>
                                                            
                                                            {{-- ✅✅✅ FIXED DESIGN AREA START --}}
                                                            <td style="white-space: normal; padding: 20px;">
                                                                
                                                                {{-- 1. Question Text: Bigger, Darker, Bolder --}}
                                                                <div class="mb-3">
                                                                    <div class="text-dark fw-bold q-text" style="font-size: 15px; line-height: 1.5;">
                                                                        {!! $report->question->question !!}
                                                                    </div>
                                                                </div>

                                                                {{-- 2. Options List: Clean White Boxes with Dark Text --}}
                                                                <div class="d-flex flex-column gap-2">
                                                                    @for($i=1; $i<=6; $i++)
                                                                        @php 
                                                                            $optKey = 'option'.$i;
                                                                            $optVal = $report->question->$optKey;
                                                                        @endphp

                                                                        @if(!empty($optVal))
                                                                            <div class="option-box">
                                                                                <span class="option-idx">{{ chr(64+$i) }}</span> {{-- A, B, C.. --}}
                                                                                <span class="flex-grow-1">{!! $optVal !!}</span>
                                                                            </div>
                                                                        @endif
                                                                    @endfor
                                                                </div>

                                                                {{-- Topic Tag (Optional) --}}
                                                                @if(isset($report->question->topic_id) && isset($topicsMap[$report->question->topic_id]))
                                                                    <div class="mt-3">
                                                                        <span class="badge bg-info-subtle text-info border border-info-subtle">
                                                                            Topic: {{ $topicsMap[$report->question->topic_id] }}
                                                                        </span>
                                                                    </div>
                                                                @endif
                                                            </td>
                                                            {{-- ✅✅✅ FIXED DESIGN AREA END --}}

                                                            <td class="text-center">
                                                                @php
                                                                    $answerEvaluator = app(\App\Services\QuestionAnswerEvaluator::class);
                                                                    $resolvedType = $answerEvaluator->questionType($report->question);
                                                                    if (str_starts_with($resolvedType, 'multiple_choice')) {
                                                                        $myAnswers = $answerEvaluator->optionReviewValues($report->question, $answerEvaluator->selectedOptionIndices($report->question, $report));
                                                                    } else {
                                                                        $storedTextAnswer = $report->answer;
                                                                        $decodedTextAnswer = json_decode((string) $storedTextAnswer, true);
                                                                        if (is_array($decodedTextAnswer)) $storedTextAnswer = implode(' | ', $decodedTextAnswer);
                                                                        $myAnswers = [$report->true_false ?? $storedTextAnswer ?? '-'];
                                                                    }
                                                                @endphp
                                                                <div class="d-flex flex-wrap justify-content-center gap-1">@forelse(array_filter($myAnswers) as $answer)<span class="badge bg-light text-dark border text-wrap">{!! $answer !!}</span>@empty<span class="text-muted">-</span>@endforelse</div>
                                                            </td>
                                                            {{-- CORRECT ANSWER LOGIC --}}
                                                            <td class="text-center">
                                                                @php
                                                                    if (str_starts_with($resolvedType, 'multiple_choice')) {
                                                                        $finalCorrectAnswers = $answerEvaluator->optionReviewValues($report->question, $answerEvaluator->correctOptionIndices($report->question));
                                                                    } elseif ($resolvedType === 'true_false') {
                                                                        $finalCorrectAnswers = [$report->question->true_false];
                                                                    } elseif ($resolvedType === 'fill_blank') {
                                                                        $finalCorrectAnswers = [$report->question->fill_blank];
                                                                    } elseif ($resolvedType === 'nat') {
                                                                        $finalCorrectAnswers = [$answerEvaluator->correctAnswerSnapshot($report->question)];
                                                                    } else {
                                                                        $finalCorrectAnswers = [$report->ques_status === 'P' ? 'Evaluation Pending' : $report->question->si_answer1];
                                                                    }
                                                                @endphp
                                                                <div class="d-flex flex-wrap justify-content-center gap-1">@forelse(array_filter($finalCorrectAnswers) as $answer)<span class="badge bg-success-subtle text-success border border-success-subtle text-wrap">{!! $answer !!}</span>@empty<span class="text-muted">N/A</span>@endforelse</div>
                                                            </td>
                                                            <td class="text-center text-muted fs-12">
                                                                <i class="ri-time-line me-1"></i>{{ $report->time_taken }}s
                                                            </td>

                                                            <td class="text-center">
                                                                @if($report->ques_status == 'R') 
                                                                    <span class="status-badge badge-correct"><i class="ri-check-line"></i> Correct</span>
                                                                @elseif($report->ques_status == 'W') 
                                                                    <span class="status-badge badge-wrong"><i class="ri-close-line"></i> Wrong</span>
                                                                @elseif($report->ques_status == 'P') 
                                                                    <span class="status-badge badge-pending"><i class="ri-loader-4-line"></i> Pending</span>
                                                                @else 
                                                                    <span class="status-badge badge-skip"><i class="ri-subtract-line"></i> Left</span>
                                                                @endif
                                                            </td>

                                                            <td class="text-end fw-bold {{ $report->marks_obtained > 0 ? 'text-success' : ($report->ques_status == 'P' ? 'text-warning' : 'text-danger') }}">
                                                                {{ $report->marks_obtained > 0 ? '+' : '' }}{{ number_format($report->marks_obtained, 2) }}
                                                            </td>
                                                        </tr>
                                                    @endforeach
                                                @else
                                                    <tr><td colspan="7" class="text-center py-5 text-muted">No questions found for this subject.</td></tr>
                                                @endif
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                                @php $isFirstContent = false; @endphp
                            @endforeach
                        </div>
                    </div>

                    {{-- Proctor Tab --}}
                    @if(count($proctorImages) > 0)
                    <div class="tab-pane" id="proctorTab" role="tabpanel">
                        <div class="p-4">
                            <div class="row g-3">
                                @foreach($proctorImages as $img)
                                <div class="col-xxl-2 col-md-3 col-6">
                                    <a href="{{ asset('storage/'.$img->image_path) }}" target="_blank">
                                        <img src="{{ asset('storage/'.$img->image_path) }}" class="proctor-img" alt="Proctor Capture">
                                    </a>
                                    <div class="text-center fs-11 text-muted mt-1">{{ $img->created_at->format('h:i:s A') }}</div>
                                </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                    @endif

                </div>
            </div>
        </div>
    </div>
</div>

@endsection

@section('script')
{{-- MathJax --}}
<script>
    window.MathJax = { loader: { load: ['[tex]/mhchem'] }, tex: { packages: {'[+]': ['mhchem']}, inlineMath: [['$', '$'], ['\\(', '\\)']], displayMath: [['\\[', '\\]'], ['$$', '$$']], processEscapes: true }, svg: { fontCache: 'global' } };
</script>
<script id="MathJax-script" async src="https://cdn.jsdelivr.net/npm/mathjax@3/es5/tex-mml-chtml.js"></script>

{{-- Lightbox --}}
<script src="https://cdnjs.cloudflare.com/ajax/libs/lightbox2/2.11.3/js/lightbox.min.js"></script>
<link href="https://cdnjs.cloudflare.com/ajax/libs/lightbox2/2.11.3/css/lightbox.min.css" rel="stylesheet">

{{-- Filter Logic --}}
<script>
    document.addEventListener("DOMContentLoaded", function() {
        const filterBtns = document.querySelectorAll('.filter-btn');
        filterBtns.forEach(btn => {
            btn.addEventListener('change', function() {
                const filterValue = this.value;
                const rows = document.querySelectorAll('.q-row');
                rows.forEach(row => {
                    const status = row.getAttribute('data-status');
                    if (filterValue === 'all') {
                        row.style.display = '';
                    } else if (filterValue === 'S') {
                        row.style.display = (status !== 'R' && status !== 'W' && status !== 'P') ? '' : 'none';
                    } else {
                        row.style.display = (status === filterValue) ? '' : 'none';
                    }
                });
            });
        });
    });
</script>
@endsection

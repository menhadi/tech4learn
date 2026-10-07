@extends('students.layouts.app')

@section('title', 'Detailed Report: ' . $result->exam->name)

@section('content')
<style>
    @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap');

    :root {
        --st-primary: var(--el-primary);
        --st-secondary: var(--el-secondary);
        --st-success: var(--el-primary);
        --st-danger: #ef4444;
        --st-warning: var(--el-secondary);
        --st-dark: var(--el-heading, #0f172a);
        --st-grey: var(--el-muted);
        --st-border: var(--el-border);
        --bg-body: var(--el-surface-muted);
        --bg-tabs: var(--el-primary-soft);
    }

    body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: var(--bg-body); font-size: 15px; color: var(--st-dark); line-height: 1.6; }

    .explanation-content {
        position: relative;
        display: block;
        max-width: 100%;
        overflow-x: auto;
        overflow-y: visible;
        word-break: break-word;
    }

    .explanation-content img,
    .explanation-content table,
    .explanation-content iframe {
        max-width: 100%;
        height: auto;
    }

    .explanation-content table {
        width: auto !important;
        min-width: 0;
    }

    .explanation-content .MathJax_Display {
        overflow-x: auto;
        overflow-y: hidden;
    }

    /* --- PREMIUM CARDS --- */
    .premium-card {
        border: 1px solid var(--st-border);
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03);
        border-radius: 8px;
        background: #fff;
        margin-bottom: 24px;
        overflow: hidden;
    }

    /* HEADER STYLES */
    .card-header-custom {
        padding: 18px 24px;
        border-bottom: 1px solid var(--st-border);
        background: #fff;
        display: flex; align-items: center; gap: 10px;
    }
    .card-title-icon {
        width: 32px; height: 32px;
        background: var(--el-primary-soft); color: var(--st-primary);
        border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 18px;
    }
    .card-title-text { font-size: 16px; font-weight: 700; color: var(--st-dark); }

    /* HERO CARD (Score) */
    .hero-card { background: radial-gradient(circle at top right, #f8fafc, #fff); }
    .score-display { font-size: 3.5rem; font-weight: 800; color: var(--st-primary); line-height: 1; letter-spacing: -1px; }
    .score-total { font-size: 1.25rem; color: var(--st-grey); font-weight: 600; }

    /* PERCENTILE RING */
    .percentile-ring {
        width: 130px; height: 130px; border-radius: 50%; margin: 0 auto 15px;
        background: conic-gradient(var(--st-primary) var(--p, 0%), #e2e8f0 0);
        display: flex; align-items: center; justify-content: center;
        box-shadow: 0 10px 15px -3px rgba(var(--el-primary-rgb), 0.15);
    }
    .percentile-ring::before { content: ''; position: absolute; width: 106px; height: 106px; background: #fff; border-radius: 50%; }
    .percentile-content { position: relative; z-index: 2; text-align: center; }
    .p-val { font-size: 28px; font-weight: 800; color: var(--st-primary); display: block; line-height: 1; }
    .p-lbl { font-size: 11px; font-weight: 700; text-transform: uppercase; color: var(--st-grey); letter-spacing: 0.5px; }

    /* TIME MATRIX */
    .time-matrix { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
    .tm-box {
        background: #fff; padding: 16px; border-radius: 12px; text-align: center;
        border: 1px solid var(--st-border); transition: transform 0.2s;
    }
    .tm-box:hover { transform: translateY(-2px); border-color: var(--st-primary); }
    .tm-val { font-size: 22px; font-weight: 800; display: block; margin-bottom: 4px; }
    .tm-lbl { font-size: 11px; font-weight: 700; text-transform: uppercase; opacity: 0.7; }

    .text-rapid { color: var(--st-success); }
    .text-deep { color: var(--st-primary); }
    .text-careless { color: var(--st-warning); }
    .text-wasted { color: var(--st-danger); }

    /* TOPIC BARS */
    .topic-item { padding: 14px 0; border-bottom: 1px dashed var(--st-border); }
    .topic-item:last-child { border-bottom: none; }
    .topic-info { display: flex; justify-content: space-between; font-size: 14px; font-weight: 600; margin-bottom: 8px; color: #334155; }
    .progress-thin { height: 10px; border-radius: 20px; background: #f1f5f9; overflow: hidden; }

    /* --- SUBJECT TABS (Segmented Control) --- */
    .subject-nav-container {
        background-color: transparent;
        padding: 0;
        border-radius: 0;
        display: inline-flex;
        flex-wrap: wrap;
        gap: 6px;
        border: 0;
    }
    .nav-subject-link {
        border: none; color: var(--st-grey); background: transparent;
        font-size: 14px; font-weight: 700; padding: 10px 20px; border-radius: 8px;
        transition: all 0.2s ease; display: flex; align-items: center; gap: 8px;
    }
    .nav-subject-link:hover { color: var(--st-primary); background: rgba(255,255,255,0.6); }
    .nav-subject-link.active {
        background: var(--el-primary-soft); color: var(--st-primary);
        box-shadow: none; font-weight: 800;
    }
    .subject-badge {
        font-size: 11px; background: #fff; color: var(--st-primary);
        padding: 2px 8px; border-radius: 20px;
    }
    .nav-subject-link.active .subject-badge { background: var(--st-primary); color: var(--theme-button-text, #fff); }
    .nav-pills-custom .nav-link {
        border: 0 !important;
        border-radius: 6px !important;
        background: var(--el-primary-soft) !important;
        color: var(--st-primary) !important;
        box-shadow: none !important;
    }
    .nav-pills-custom .nav-link:hover,
    .nav-pills-custom .nav-link.active {
        background: var(--st-primary) !important;
        color: var(--theme-button-text, #fff) !important;
    }

    /* FILTERS */
    .filter-btn-group .btn { border-radius: 6px; font-size: 13px; font-weight: 800; padding: 8px 16px; border: 1px solid var(--st-border); background:#fff; color:var(--st-dark); }
    .filter-btn-group .btn:hover { border-color: var(--st-primary); color: var(--st-primary); background: var(--el-primary-soft); }
    .btn-check:checked + .btn-outline-secondary {
        background-color: var(--st-primary);
        color: var(--theme-button-text, #fff) !important;
        border-color: var(--st-primary);
        box-shadow: 0 6px 14px rgba(var(--el-primary-rgb), .16);
    }
    #filterWrong:checked + .btn-outline-secondary,
    #filterSkip:checked + .btn-outline-secondary {
        background-color: var(--st-secondary);
        color: var(--theme-button-text, #fff) !important;
        border-color: var(--st-secondary);
    }

    /* --- TABLE WIDTH ADJUSTMENTS (KEY FIX) --- */
    .solution-table {
        width: 100%;
        table-layout: fixed;
    }
    .solution-table th {
        background: var(--el-primary-soft); font-size: 12px; text-transform: uppercase; color: var(--st-dark); font-weight: 800;
        padding: 16px; border-bottom: 1px solid var(--st-border); letter-spacing: 0.5px;
    }
    .solution-table td { vertical-align: top; padding: 24px 16px; font-size: 15px; border-bottom: 1px solid var(--st-border); }
    .solution-table tbody tr:nth-child(even) { background: var(--el-surface-muted); }

    /* Optimized Badges */
    .status-badge { font-size: 11px; padding: 4px 8px; border-radius: 6px; font-weight: 700; text-transform: uppercase; display: inline-flex; align-items: center; gap: 4px; }
    .badge-correct { background: var(--el-primary-soft); color: var(--st-primary); }
    .badge-wrong { background: var(--el-secondary-soft); color: var(--st-secondary); }
    .badge-skip { background: #f1f5f9; color: #475569; }

    /* QUESTION TEXT */
    .solution-question-block {
        background: #fff;
        border: 1px solid var(--st-border);
        border-radius: 8px;
        padding: 16px;
    }
    .solution-section-label {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        margin-bottom: 10px;
        color: var(--st-primary);
        font-size: 11px;
        font-weight: 800;
        letter-spacing: .05em;
        text-transform: uppercase;
    }
    .q-text { font-size: 17px; font-weight: 700; color: var(--st-dark); margin-bottom: 0; line-height: 1.5; }
    .q-text p { margin-bottom: 0; }
    .q-text img { max-width: 100%; border-radius: 8px; margin-top: 12px; border: 1px solid #e2e8f0; }

    .opt-badge { padding: 7px 12px; border-radius: 6px; background: #fff; border: 1px solid var(--st-border); font-size: 14px; color: var(--st-dark); display: inline-block; margin-right: 6px; margin-bottom: 6px; }
    .solution-options-block {
        margin-top: 14px;
        padding-top: 14px;
        border-top: 1px dashed var(--st-border);
    }
    .solution-answer-section {
        margin-top: 14px;
        padding: 14px;
        border: 1px solid var(--st-border);
        border-radius: 8px;
        background: var(--el-primary-soft);
    }

    /* --- EXPLANATION BOX (WIDTH & HEIGHT FIX) --- */
    .explanation-box {
        margin-top: 15px;
        background-color: var(--el-secondary-soft);
        border: 1px solid var(--el-border);
        border-left: 5px solid var(--el-secondary);
        padding: 16px;
        border-radius: 8px;
        font-size: 15px;
        color: var(--st-dark);
        width: 100%; /* Ensure full width */
        max-width: 100%;
        overflow-x: auto;
        overflow-y: visible;
    }
    .exp-label { font-size: 11px; text-transform: uppercase; font-weight: 800; color: var(--el-secondary); display: block; margin-bottom: 4px; letter-spacing: 0.5px; }

    /* SCROLLBAR & ANIMATION */
    .custom-scroll::-webkit-scrollbar { width: 6px; }
    .custom-scroll::-webkit-scrollbar-track { background: #f1f1f1; }
    .custom-scroll::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
    .q-row { transition: background-color 0.2s; }
    .q-row:hover { background-color: #f8fafc; }
    .solution-topbar { background: var(--el-surface-muted) !important; border-bottom: 1px solid var(--st-border) !important; }
    .solution-primary-btn,
    .solution-primary-btn:hover {
        background: var(--st-primary);
        border-color: var(--st-primary);
        color: var(--theme-button-text, #fff);
        border-radius: 6px;
        font-weight: 800;
    }
    .solution-feedback-btn {
        width: auto;
        min-width: 170px;
        padding: 10px 18px;
    }
    .solution-secondary-btn,
    .solution-secondary-btn:hover {
        background: var(--st-secondary);
        border-color: var(--st-secondary);
        color: var(--theme-button-text, #fff);
        border-radius: 6px;
        font-weight: 800;
    }
    .topic-pill {
        background: var(--el-primary-soft);
        color: var(--st-primary);
        border: 1px solid var(--st-border);
        border-radius: 999px;
        padding: 5px 10px;
        font-size: 12px;
        font-weight: 800;
        display: inline-flex;
    }
    .answer-pill {
        background: #fff;
        border: 1px solid var(--st-border);
        border-radius: 6px;
        padding: 8px 10px;
        display: inline-block;
        max-width: 100%;
        word-break: break-word;
        white-space: normal;
    }
    .answer-pill.correct { color: var(--st-primary); background: var(--el-primary-soft); }
    .answer-pill.wrong { color: var(--st-secondary); background: var(--el-secondary-soft); }
    .answer-pill mjx-container,
    .answer-pill math,
    .answer-pill svg {
        max-width: 100%;
    }
    .answer-pill svg {
        height: auto;
    }
    .report-answer-img {
        display: block;
        max-width: 180px;
        max-height: 140px;
        object-fit: contain;
        border: 1px solid var(--st-border);
        border-radius: 6px;
        background: #fff;
        padding: 4px;
        margin: 0 auto;
    }
    .theme-result-pass { background: var(--el-primary-soft); color: var(--st-primary); border: 1px solid var(--st-border); }
    .theme-result-fail { background: var(--el-secondary-soft); color: var(--st-secondary); border: 1px solid var(--st-border); }
    .theme-primary-text { color: var(--st-primary) !important; }
    .theme-secondary-text { color: var(--st-secondary) !important; }
    .theme-muted-icon { color: var(--st-grey) !important; }
    .theme-star { color: var(--st-secondary) !important; }
    .topic-status-strong { color: var(--st-primary) !important; }
    .topic-status-weak { color: var(--st-secondary) !important; }
    .topic-status-review { color: var(--st-secondary) !important; }
    .topic-progress-strong { background: var(--st-primary) !important; }
    .topic-progress-weak,
    .topic-progress-review { background: var(--st-secondary) !important; }
    .solution-card-list { padding: 20px; background: var(--el-surface-muted); }
    .solution-card {
        border: 1px solid var(--st-border);
        border-radius: 8px;
        background: #fff;
        box-shadow: 0 5px 16px rgba(2, 6, 23, .05);
        overflow: hidden;
        margin-bottom: 18px;
    }
    .solution-card-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 14px;
        padding: 14px 18px;
        background: #fff;
        border-bottom: 1px solid var(--st-border);
    }
    .solution-card-number {
        width: 42px;
        height: 42px;
        border-radius: 8px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: var(--st-primary);
        color: var(--theme-button-text, #fff);
        font-weight: 800;
        flex: 0 0 auto;
    }
    .solution-card-meta {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        align-items: center;
        justify-content: flex-end;
    }
    .solution-meta-pill {
        border: 1px solid var(--st-border);
        border-radius: 999px;
        background: #fff;
        color: var(--st-dark);
        padding: 6px 10px;
        font-size: 12px;
        font-weight: 800;
        display: inline-flex;
        align-items: center;
        gap: 5px;
        white-space: nowrap;
    }
    .solution-card-body { padding: 18px; }
    .solution-answer-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 12px;
        margin-top: 16px;
    }
    .solution-answer-box {
        border: 1px solid var(--st-border);
        border-radius: 8px;
        background: #fff;
        padding: 12px;
    }
    .solution-answer-label {
        display: block;
        margin-bottom: 8px;
        font-size: 11px;
        font-weight: 800;
        text-transform: uppercase;
        color: var(--st-grey);
        letter-spacing: .04em;
    }
    .solution-card-actions {
        display: flex;
        gap: 8px;
        align-items: center;
    }
    .student-ai-insight {
        border: 1px solid var(--st-border);
        border-left: 5px solid var(--st-primary);
        background: #fff;
    }
    .student-ai-insight .insight-summary {
        color: var(--st-dark);
        font-size: 15px;
        line-height: 1.6;
    }
    .student-ai-insight-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 14px;
    }
    .student-ai-insight-panel {
        border: 1px solid var(--st-border);
        border-radius: 8px;
        padding: 14px;
        background: var(--el-surface-muted, #f8fafc);
        min-height: 100%;
    }
    .student-ai-insight-title {
        font-size: 13px;
        font-weight: 800;
        color: var(--st-dark);
        display: flex;
        align-items: center;
        gap: 7px;
        margin-bottom: 10px;
    }
    .student-ai-insight-title i {
        color: var(--st-primary);
    }
    .student-ai-insight-list {
        padding-left: 18px;
        margin-bottom: 0;
        color: #475569;
        font-size: 13px;
    }
    .student-ai-insight-list li + li {
        margin-top: 7px;
    }
    .student-ai-data-points {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        margin: 0 0 18px;
    }
    .student-ai-data-point {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        border: 1px solid var(--st-border);
        background: var(--el-primary-soft);
        color: var(--st-primary);
        border-radius: 999px;
        padding: 6px 10px;
        font-size: 12px;
        font-weight: 800;
    }
    .result-report-header {
        gap: 16px;
    }
    .result-report-title {
        color: var(--st-dark);
        font-size: 24px;
        line-height: 1.25;
        font-weight: 800;
        max-width: 820px;
    }
    .result-report-date {
        color: var(--st-secondary) !important;
        white-space: nowrap;
    }
    .btn-bookmark {
        border: 1px solid var(--st-border);
        background: #fff;
        border-radius: 6px;
        width: 36px;
        height: 36px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }
    @media (max-width: 767.98px) {
        .solution-card-head { align-items: flex-start; flex-direction: column; }
        .solution-card-meta { justify-content: flex-start; }
        .solution-answer-grid { grid-template-columns: 1fr; }
        .solution-feedback-btn { width: 100%; }
        .student-ai-insight-grid { grid-template-columns: 1fr; }
        .result-report-header {
            align-items: flex-start !important;
            flex-direction: column;
        }
        .result-report-title {
            font-size: 22px;
            line-height: 1.25;
            max-width: 100%;
        }
        .result-report-date {
            white-space: normal;
            align-self: flex-start;
        }
    }
</style>

@include('students.exams.partials.modals')

@php
    $renderReportAnswer = function ($value) {
        if (is_array($value)) {
            $value = implode(', ', array_filter($value));
        }

        $value = trim((string) ($value ?? ''));

        if ($value === '') {
            return '-';
        }

        $htmlAnswerTags = ['<img', '<table', '<p', '<span', '<div', '<strong', '<mjx-container', '<math', '<svg'];
        foreach ($htmlAnswerTags as $htmlAnswerTag) {
            if (stripos($value, $htmlAnswerTag) !== false) {
                return $value;
            }
        }

        $isImageUrl = preg_match('/^(https?:\/\/|\/|storage\/|uploads\/|data:image\/).+\.(png|jpe?g|gif|webp|svg)(\?.*)?$/i', $value)
            || preg_match('/^data:image\//i', $value);

        if ($isImageUrl) {
            $src = preg_match('/^(https?:\/\/|\/|data:image\/)/i', $value) ? $value : asset($value);
            return '<img src="' . e($src) . '" alt="Answer image" class="report-answer-img">';
        }

        return e($value);
    };
@endphp

<div class="container-fluid pb-5">

    {{-- BREADCRUMB --}}
    <div class="result-report-header d-flex justify-content-between align-items-center mb-4 mt-3">
        <div>
            <a href="{{ route('student.results') }}" class="text-decoration-none text-muted fs-13 fw-bold text-uppercase d-flex align-items-center gap-1 hover-primary">
                <i class="ri-arrow-left-line"></i> Back to Results
            </a>
            <h3 class="result-report-title mb-0 mt-2">{{ $result->exam->name }}</h3>
        </div>
        <div>
            <span class="result-report-date badge bg-white border px-3 py-2 fs-13 shadow-sm rounded-pill d-flex align-items-center gap-2">
                <i class="ri-calendar-event-line theme-primary-text"></i> {{ $result->created_at->format('d M, Y • h:i A') }}
            </span>
        </div>
    </div>

    {{-- 1. SCORE HERO SECTION --}}
    <div class="row">
        <div class="col-12">
            <div class="premium-card hero-card">
                <div class="card-body p-4 p-md-5">
                    <div class="row align-items-center g-4">
                        <div class="col-lg-4 border-end border-light text-center text-lg-start">
                            <h6 class="text-uppercase text-muted fs-12 fw-bold mb-3 tracking-wide d-flex align-items-center justify-content-center justify-content-lg-start gap-2">
                                <i class="ri-medal-line theme-secondary-text fs-16"></i> {{ __('messages.results_card_your_score') }}
                            </h6>
                            <div class="d-flex align-items-baseline justify-content-center justify-content-lg-start gap-1">
                                <span class="score-display">{{ number_format($result->obtained_marks, 2) }}</span>
                                <span class="score-total">/ {{ number_format($result->total_marks, 2) }}</span>
                            </div>
                            <div class="mt-3 d-flex align-items-center justify-content-center justify-content-lg-start gap-3">
                                @if($result->result == 'Pass')
                                    <span class="badge theme-result-pass px-3 py-2 fs-12 text-uppercase rounded-pill fw-bold">
                                        <i class="ri-checkbox-circle-fill me-1"></i> {{ __('messages.results_filter_passed') }}
                                    </span>
                                @else
                                    <span class="badge theme-result-fail px-3 py-2 fs-12 text-uppercase rounded-pill fw-bold">
                                        <i class="ri-close-circle-fill me-1"></i> {{ __('messages.results_filter_failed') }}
                                    </span>
                                @endif
                                <span class="text-muted fs-13 fw-semibold"><i class="ri-timer-2-line me-1 theme-primary-text"></i> {{ $formattedTestTime }}</span>
                            </div>
                        </div>

                        <div class="col-lg-5 border-end border-light px-lg-5">
                            <div class="row text-center">
                                @unless($isPracticeResult)
                                <div class="col-4">
                                    <div class="mb-2"><i class="ri-trophy-fill fs-24 theme-secondary-text"></i></div>
                                    <h4 class="mb-1 fw-bold text-dark">{{ $comparisonData['rank'] }}</h4>
                                    <p class="text-muted fs-11 text-uppercase fw-bold mb-0">{{ __('ui.rank') }}</p>
                                </div>
                                @endunless
                                <div class="{{ $isPracticeResult ? 'col-6' : 'col-4' }}">
                                    <div class="mb-2"><i class="ri-focus-2-fill fs-24 theme-primary-text"></i></div>
                                    <h4 class="mb-1 fw-bold theme-primary-text">{{ number_format($result->percent, 1) }}%</h4>
                                    <p class="text-muted fs-11 text-uppercase fw-bold mb-0">{{ __('messages.results_view_kpi_accuracy') }}</p>
                                </div>
                                <div class="{{ $isPracticeResult ? 'col-6' : 'col-4' }}">
                                    <div class="mb-2"><i class="ri-alert-fill fs-24 theme-secondary-text"></i></div>
                                    <h4 class="mb-1 fw-bold theme-secondary-text">{{ $toleranceCount }}</h4>
                                    <p class="text-muted fs-11 text-uppercase fw-bold mb-0">{{ __('ui.warnings') }}</p>
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-3 text-center">
                            <a href="{{ route('student.examFeedback', ['exam_result_id' => $result->id]) }}" class="btn solution-primary-btn solution-feedback-btn shadow-sm d-inline-flex align-items-center justify-content-center gap-2">
                                <i class="ri-chat-smile-3-line fs-18"></i> Give Feedback
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @if(!empty($performanceInsight))
    <div class="premium-card student-ai-insight">
        <div class="card-header-custom">
            <div class="card-title-icon"><i class="ri-sparkling-2-line"></i></div>
            <div>
                <span class="card-title-text">{{ ($performanceInsight['source'] ?? 'rules') === 'ai' ? 'AI Performance Analysis' : 'Performance Guidance' }}</span>
                <p class="mb-0 text-muted fs-12">{{ __('ui.insight_copy') }}</p>
            </div>
        </div>
        <div class="card-body p-4">
            <p class="insight-summary mb-4">{{ $performanceInsight['summary'] }}</p>
            @if(!empty($performanceInsight['data_points']))
                <div class="student-ai-data-points" aria-label="Analysis data points">
                    @foreach($performanceInsight['data_points'] as $point)
                        <span class="student-ai-data-point"><i class="ri-bar-chart-2-line"></i>{{ $point }}</span>
                    @endforeach
                </div>
            @endif
            <div class="student-ai-insight-grid">
                <div class="student-ai-insight-panel">
                    <div class="student-ai-insight-title"><i class="ri-shield-check-line"></i> {{ __('ui.strengths') }}</div>
                    <ul class="student-ai-insight-list">
                        @foreach(($performanceInsight['strengths'] ?? []) as $item)
                            <li>{{ $item }}</li>
                        @endforeach
                    </ul>
                </div>
                <div class="student-ai-insight-panel">
                    <div class="student-ai-insight-title"><i class="ri-focus-3-line"></i> {{ __('ui.weak_areas') }}</div>
                    <ul class="student-ai-insight-list">
                        @foreach(($performanceInsight['weaknesses'] ?? []) as $item)
                            <li>{{ $item }}</li>
                        @endforeach
                    </ul>
                </div>
                <div class="student-ai-insight-panel">
                    <div class="student-ai-insight-title"><i class="ri-route-line"></i> {{ __('ui.improvement_plan') }}</div>
                    <ul class="student-ai-insight-list">
                        @foreach(($performanceInsight['improvements'] ?? []) as $item)
                            <li>{{ $item }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </div>
    </div>
    @endif

    {{-- 2. INSIGHTS ROW --}}
    <div class="row">
        @unless($isPracticeResult)
        <div class="col-xl-3 col-md-6">
            <div class="premium-card h-100">
                <div class="card-header-custom">
                    <div class="card-title-icon"><i class="ri-bar-chart-box-line"></i></div>
                    <span class="card-title-text">{{ __('ui.standing') }}</span>
                </div>
                <div class="card-body text-center py-4">
                    <div class="percentile-ring" style="--p: {{ $comparisonData['percentile'] ?? 0 }}%">
                        <div class="percentile-content">
                            <span class="p-val">{{ $comparisonData['percentile'] ?? 0 }}<small>%</small></span>
                            <span class="p-lbl">Top {{ 100 - ($comparisonData['percentile'] ?? 0) }}%</span>
                        </div>
                    </div>
                    <p class="text-muted fs-13 mt-3 mb-0 px-3">{{ __('ui.performed_better_prefix') }} <strong>{{ $comparisonData['percentile'] }}%</strong> {{ __('ui.performed_better_suffix') }}</p>
                </div>
            </div>
        </div>
        @endunless

        <div class="{{ $isPracticeResult ? 'col-xl-7' : 'col-xl-5' }} col-md-6">
            <div class="premium-card h-100">
                <div class="card-header-custom">
                    <div class="d-flex align-items-center gap-2">
                        <div class="card-title-icon"><i class="ri-speed-line"></i></div>
                        <span class="card-title-text">{{ __('ui.time_accuracy') }}</span>
                    </div>
                    <span class="badge bg-light text-dark border fw-bold px-2">Avg: {{ round($globalAvgTimePerQues,1) }}s/Q</span>
                </div>
                <div class="card-body">
                    <div class="time-matrix">
                        <div class="tm-box">
                            <span class="tm-val text-rapid">{{ $timeAnalysis['rapid_fire'] }}</span>
                            <span class="tm-lbl">{{ __('ui.rapid_fire') }}</span>
                            <span class="tm-desc">{{ __('ui.fast_correct') }}</span>
                        </div>
                        <div class="tm-box">
                            <span class="tm-val text-deep">{{ $timeAnalysis['struggle_win'] }}</span>
                            <span class="tm-lbl">{{ __('ui.deep_thinker') }}</span>
                            <span class="tm-desc">{{ __('ui.slow_correct') }}</span>
                        </div>
                        <div class="tm-box">
                            <span class="tm-val text-careless">{{ $timeAnalysis['careless_mistake'] }}</span>
                            <span class="tm-lbl">{{ __('ui.careless') }}</span>
                            <span class="tm-desc">{{ __('ui.too_fast_wrong') }}</span>
                        </div>
                        <div class="tm-box">
                            <span class="tm-val text-wasted">{{ $timeAnalysis['wasted_time'] }}</span>
                            <span class="tm-lbl">{{ __('ui.wasted') }}</span>
                            <span class="tm-desc">{{ __('ui.slow_wrong') }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        @if(count($topicAnalysis) > 0)
        <div class="col-xl-4 col-md-12">
            <div class="premium-card h-100">
                <div class="card-header-custom">
                    <div class="card-title-icon"><i class="ri-pie-chart-2-line"></i></div>
                    <span class="card-title-text">{{ __('ui.topic_analysis') }}</span>
                </div>
                <div class="card-body p-4 custom-scroll" style="max-height: 320px; overflow-y: auto;">
                    @foreach($topicAnalysis as $topic)
                    <div class="topic-item">
                        <div class="topic-info">
                            <span>{{ Str::limit($topic->name, 30) }}</span>
                            <span class="{{ $topic->status == 'Strong' ? 'topic-status-strong' : ($topic->status == 'Weak' ? 'topic-status-weak' : 'topic-status-review') }}">
                                {{ $topic->status }} ({{ $topic->accuracy }}%)
                            </span>
                        </div>
                        <div class="progress progress-thin">
                            <div class="progress-bar {{ $topic->status == 'Strong' ? 'topic-progress-strong' : ($topic->status == 'Weak' ? 'topic-progress-weak' : 'topic-progress-review') }}"
                                 role="progressbar" style="width: {{ $topic->accuracy }}%"></div>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>
        @endif
    </div>

    {{-- GAP --}}
    <div class="mt-5"></div>

    {{-- 3. QUESTION REPORT SECTION --}}
    <div class="row">
        <div class="col-12">
            <div class="premium-card">

                {{-- Top Tabs --}}
                <div class="card-header bg-white border-bottom px-4 py-3">
                    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
                        <div class="d-flex align-items-center gap-3">
                            <div class="card-title-icon" style="background:var(--st-primary); color:var(--theme-button-text, #fff);"><i class="ri-file-list-3-line"></i></div>
                            <div>
                                <h5 class="mb-0 fw-bold text-dark">{{ __('ui.detailed_solutions') }}</h5>
                                <p class="mb-0 text-muted fs-12">{{ __('ui.review_answers_copy') }}</p>
                            </div>
                        </div>

                        <ul class="nav nav-pills nav-pills-custom" role="tablist">
                            <li class="nav-item">
                                <a class="nav-link active px-4 fw-bold" data-bs-toggle="tab" href="#solutionsTab" role="tab">{{ __('ui.questions') }}</a>
                            </li>
                            @if(count($proctorImages) > 0)
                            <li class="nav-item ms-2">
                                <a class="nav-link px-4 fw-bold" data-bs-toggle="tab" href="#proctorTab" role="tab">{{ __('ui.proctoring') }}</a>
                            </li>
                            @endif
                        </ul>
                    </div>
                </div>

                <div class="card-body p-0">
                    <div class="tab-content">

                        {{-- SOLUTIONS CONTENT --}}
                        <div class="tab-pane active" id="solutionsTab" role="tabpanel">

                            {{-- FILTERS ROW --}}
                            <div class="p-4 solution-topbar d-flex flex-column flex-xl-row justify-content-between gap-3 align-items-center">

                                {{-- Subject Tabs --}}
                                <div class="subject-nav-container">
                                    @php $isFirst = true; @endphp
                                    @foreach($subjectNames as $subId => $subName)
                                        <button class="nav-subject-link {{ $isFirst ? 'active' : '' }}"
                                                onclick="switchSubjectTab('{{ $subId }}', this)">
                                            {{ $subName }}
                                            <span class="subject-badge">{{ count($groupedQuestions[$subId] ?? []) }}</span>
                                        </button>
                                        @php $isFirst = false; @endphp
                                    @endforeach
                                </div>

                                {{-- Action Filters --}}
                                <div class="btn-group filter-btn-group" role="group">
                                    <input type="radio" class="btn-check filter-btn" name="qFilter" id="filterAll" value="all" checked>
                                    <label class="btn btn-outline-secondary" for="filterAll">{{ __('messages.dash_next_all_button') }}</label>

                                    <input type="radio" class="btn-check filter-btn" name="qFilter" id="filterCorrect" value="R">
                                    <label class="btn btn-outline-secondary" for="filterCorrect"><i class="ri-check-line"></i> {{ __('messages.results_view_table_correct') }}</label>

                                    <input type="radio" class="btn-check filter-btn" name="qFilter" id="filterWrong" value="W">
                                    <label class="btn btn-outline-secondary" for="filterWrong"><i class="ri-close-line"></i> {{ __('ui.wrong') }}</label>

                                    <input type="radio" class="btn-check filter-btn" name="qFilter" id="filterSkip" value="S">
                                    <label class="btn btn-outline-secondary" for="filterSkip"><i class="ri-skip-forward-line"></i> {{ __('ui.skipped') }}</label>
                                </div>
                            </div>

                            {{-- QUESTIONS CARD LIST --}}
                            <div class="tab-content-subjects">
                                @php $isFirstContent = true; @endphp
                                @foreach($subjectNames as $subId => $subName)
                                    <div class="subject-pane {{ $isFirstContent ? '' : 'd-none' }}" id="content-sub-{{ $subId }}">
                                        <div class="solution-card-list">
                                            @if(isset($groupedQuestions[$subId]))
                                                @foreach($groupedQuestions[$subId] as $index => $report)
                                                    @php
                                                        $questionTranslation = $resultTranslations->get($report->question_id);
                                                        $statusClass = $report->answered ? $report->ques_status : 'S';
                                                        $answerEvaluator = app(\App\Services\QuestionAnswerEvaluator::class);
                                                        $report->question_type = $answerEvaluator->questionType($report->question);

                                                        if (str_starts_with($report->question_type, 'multiple_choice')) {
                                                            $displayQuestionForAnswers = clone $report->question;
                                                            foreach (range(1, 6) as $optionNumber) {
                                                                $optionKey = 'option'.$optionNumber;
                                                                if (! empty($questionTranslation?->{$optionKey})) {
                                                                    $displayQuestionForAnswers->{$optionKey} = $questionTranslation->{$optionKey};
                                                                }
                                                            }
                                                            $selectedIndices = $answerEvaluator->selectedOptionIndices($report->question, $report);
                                                            $myAns = $answerEvaluator->optionReviewValues($displayQuestionForAnswers, $selectedIndices);
                                                            $corAns = $answerEvaluator->optionReviewValues($displayQuestionForAnswers, $answerEvaluator->correctOptionIndices($report->question));
                                                        } else {
                                                            $myAns = $report->true_false ?? $report->answer ?? '-';
                                                            $corAns = $report->question->true_false
                                                                ?? $questionTranslation?->fill_blank
                                                                ?? $report->question->fill_blank
                                                                ?? $report->correct_answer
                                                                ?? $questionTranslation?->si_answer1
                                                                ?? $report->question->si_answer1;
                                                        }
                                                    @endphp
                                                    <article class="solution-card q-row" data-status="{{ $statusClass }}">
                                                        <div class="solution-card-head">
                                                            <div class="d-flex align-items-center gap-3">
                                                                <span class="solution-card-number">{{ $loop->iteration }}</span>
                                                                <div>
                                                                    @if(isset($report->question->topic_id) && isset($topicsMap[$report->question->topic_id]))
                                                                        <span class="topic-pill">{{ $topicsMap[$report->question->topic_id] }}</span>
                                                                    @else
                                                                        <span class="text-muted fw-semibold">{{ __('messages.view_bookmarks_question_prefix') }}</span>
                                                                    @endif
                                                                </div>
                                                            </div>
                                                            <div class="solution-card-meta">
                                                                @if($report->ques_status == 'R')
                                                                    <span class="status-badge badge-correct"><i class="ri-checkbox-circle-fill"></i> {{ __('messages.results_view_table_correct') }}</span>
                                                                @elseif($report->ques_status == 'W')
                                                                    <span class="status-badge badge-wrong"><i class="ri-close-circle-fill"></i> {{ __('ui.wrong') }}</span>
                                                                @else
                                                                    <span class="status-badge badge-skip"><i class="ri-indeterminate-circle-fill"></i> {{ __('ui.left') }}</span>
                                                                @endif
                                                                <span class="solution-meta-pill"><i class="ri-time-line"></i> {{ $report->time_taken }}s</span>
                                                                <span class="solution-meta-pill {{ $report->marks_obtained > 0 ? 'theme-primary-text' : 'theme-secondary-text' }}"><i class="ri-medal-line"></i> {{ number_format($report->marks_obtained, 2) }}</span>
                                                                <div class="solution-card-actions">
                                                                    <button class="btn-bookmark" onclick="toggleBookmark({{ $report->question_id }}, {{ $result->id }}, {{ $report->id ?? 'null' }}, this)">
                                                                        <i class="{{ $report->bookmark ? 'ri-star-fill theme-star' : 'ri-star-line theme-muted-icon' }} fs-20"></i>
                                                                    </button>
                                                                    <button data-questionid="{{ $report->question_id }}" data-subjectid="{{ $report->subject_id }}" data-questiontype="{{ $report->question_type }}" class="btn btn-sm solution-secondary-btn px-2 py-1 report-btn" data-bs-toggle="modal" data-bs-target="#reportModal">@lang('messages.exam_sidebar_btn_report')</button>
                                                                </div>
                                                            </div>
                                                        </div>

                                                        <div class="solution-card-body">
                                                            <div class="solution-question-block">
                                                                <span class="solution-section-label"><i class="ri-question-line"></i> {{ __('messages.view_bookmarks_question_prefix') }}</span>
                                                                <div class="q-text">
                                                                    {!! $questionTranslation?->question ?: $report->question->question !!}
                                                                </div>

                                                                <div class="solution-options-block">
                                                                    @for($i=1; $i<=6; $i++)
                                                                        @php
                                                                            $optKey = 'option'.$i;
                                                                            $optVal = $questionTranslation?->{$optKey} ?: $report->question->$optKey;
                                                                        @endphp

                                                                        @if(!empty($optVal))
                                                                            <span class="opt-badge">
                                                                                <span class="fw-bold me-1 text-muted">{{ chr(64+$i) }}.</span> {!! $optVal !!}
                                                                            </span>
                                                                        @endif
                                                                    @endfor
                                                                </div>
                                                            </div>

                                                            <div class="solution-answer-section">
                                                                <span class="solution-section-label mb-2"><i class="ri-checkbox-circle-line"></i> {{ __('ui.answer_review') }}</span>
                                                                <div class="solution-answer-grid mt-0">
                                                                    <div class="solution-answer-box">
                                                                        <span class="solution-answer-label">{{ __('ui.your_answer') }}</span>
                                                                        <span class="answer-pill {{ $report->ques_status == 'W' ? 'wrong' : '' }} fw-bold fs-15">{!! $renderReportAnswer($myAns) !!}</span>
                                                                    </div>
                                                                    <div class="solution-answer-box">
                                                                        <span class="solution-answer-label">{{ __('ui.correct_answer') }}</span>
                                                                        <span class="answer-pill correct fw-bold fs-15">{!! $renderReportAnswer($corAns) !!}</span>
                                                                    </div>
                                                                </div>
                                                            </div>

                                                            @php
                                                                $displayHint = $questionTranslation?->hint ?: ($report->question->hint ?? null);
                                                                $displayExplanation = $questionTranslation?->explanation ?: ($report->question->explanation ?? null);
                                                            @endphp
                                                            @if(!empty($displayHint))
                                                                <div class="explanation-box">
                                                                    <span class="exp-label"><i class="ri-information-line me-1"></i> {{ __('ui.hint') }}</span>
                                                                    <div class="text-dark fw-medium explanation-content">{!! $displayHint !!}</div>
                                                                </div>
                                                            @endif
                                                            @if(!empty($displayExplanation))
                                                                <div class="explanation-box">
                                                                    <span class="exp-label"><i class="ri-lightbulb-flash-line me-1"></i> {{ __('ui.explanation') }}</span>
                                                                    <div class="text-dark fw-medium explanation-content">{!! $displayExplanation !!}</div>
                                                                </div>
                                                            @endif
                                                        </div>
                                                    </article>
                                                @endforeach
                                            @else
                                                <div class="text-center py-5 text-muted">{{ __('ui.no_questions_section') }}</div>
                                            @endif
                                        </div>
                                    </div>
                                    @php $isFirstContent = false; @endphp
                                @endforeach
                            </div>
                        </div>

                        {{-- PROCTOR TAB --}}
                        @if(count($proctorImages) > 0)
                        <div class="tab-pane" id="proctorTab" role="tabpanel">
                            <div class="p-4">
                                <div class="row g-3">
                                    @foreach($proctorImages as $img)
                                    <div class="col-6 col-md-3 col-xl-2">
                                        <div class="border rounded p-1 shadow-sm bg-white">
                                            <a href="{{ asset('storage/'.$img->image_path) }}" data-lightbox="proctor-set">
                                                <img src="{{ asset('storage/'.$img->image_path) }}" class="proctor-img w-100 rounded" alt="Proctor Capture">
                                            </a>
                                            <div class="text-center fs-12 fw-bold text-dark mt-2 py-1 bg-light rounded">{{ $img->created_at->format('h:i:s A') }}</div>
                                        </div>
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

<script>
    // Custom Tab Switching Logic
    function switchSubjectTab(targetId, btn) {
        document.querySelectorAll('.nav-subject-link').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        document.querySelectorAll('.subject-pane').forEach(p => p.classList.add('d-none'));
        document.getElementById('content-sub-' + targetId).classList.remove('d-none');
    }

    document.addEventListener("DOMContentLoaded", function() {
        // Filter Logic
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
                        row.style.display = (status !== 'R' && status !== 'W') ? '' : 'none';
                    } else {
                        row.style.display = (status === filterValue) ? '' : 'none';
                    }
                });
            });
        });
    });

    // Bookmark Logic
    function toggleBookmark(qid, examResultId, examStatId, btn) {
        let icon = btn.querySelector('i');
        let isBookmarked = icon.classList.contains('ri-star-fill');

        if (isBookmarked) {
            icon.className = 'ri-star-line text-muted fs-20';
        } else {
            icon.className = 'ri-star-fill theme-star fs-20';
        }

        fetch('{{ route("student.bookmarkQuestion") }}', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
            body: JSON.stringify({
                question_id: qid,
                exam_result_id: examResultId,
                exam_stat_id: examStatId,
                bookmark: !isBookmarked
            })
        }).catch(err => console.error('Bookmark Error:', err));
    }

    document.addEventListener("DOMContentLoaded", function () {

        const reportButtons = document.querySelectorAll(".report-btn");

        reportButtons.forEach(btn => {
            btn.addEventListener("click", function () {

                const questionId = btn.dataset.questionid;
                const subjectId = btn.dataset.subjectid;
                const questionType = btn.dataset.questiontype;

                // ? Set into modal
                document.getElementById("report_question_id").value = questionId;
                document.getElementById("report_subject_id").value = subjectId;
                document.getElementById("report_question_type").value = questionType;

            });
        });

    });

    document.querySelector("#reportModal .report-submit-btn").addEventListener("click", function () {

        const btn = this;
        // const messageEl = document.querySelector("textarea[name='message']");
        const messageEl = document.querySelector("select[name='message']");
        const errorElId = "report-error";

        // ? Remove old error
        let oldError = document.getElementById(errorElId);
        if (oldError) oldError.remove();

        const message = messageEl.value.trim();

        // Validation
        if (!message) {
            const errorDiv = document.createElement("div");
            errorDiv.id = errorElId;
            errorDiv.className = "text-danger mt-2";
            errorDiv.innerText = "Message is required";
            messageEl.after(errorDiv);
            return;
        }

        // ? Disable button (prevent double click)
        btn.disabled = true;
        btn.innerText = "Submitting...";

        const data = {
            question_id: document.getElementById("report_question_id").value,
            subject_id: document.getElementById("report_subject_id").value,
            question_type: document.getElementById("report_question_type").value,
            message: message,
            _token: "{{ csrf_token() }}"
        };

        fetch("{{ route('student.questionReport.store') }}", {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
                "Accept": "application/json"
            },
            body: JSON.stringify(data)
        })
        .then(async res => {
            const response = await res.json();

            if (!res.ok) {
                throw response;
            }

            return response;
        })
        .then(res => {

            // Success message
            alert("Report submitted successfully");

            // Reset form
            messageEl.value = "";

            // Close modal
            const modalEl = document.getElementById('reportModal');
            const modal = bootstrap.Modal.getInstance(modalEl);
            modal.hide();

        })
        .catch(err => {

            // Laravel validation errors
            if (err.errors) {
                const errorDiv = document.createElement("div");
                errorDiv.id = errorElId;
                errorDiv.className = "text-danger mt-2";
                errorDiv.innerText = Object.values(err.errors)[0][0];
                messageEl.after(errorDiv);
            } else {
                alert("Something went wrong");
            }

        })
        .finally(() => {
            btn.disabled = false;
            btn.innerText = "Submit";
        });

    });
</script>
@endsection

@extends('students.layouts.app')

@section('title') @lang('messages.my_exams_title') @endsection

@section('css')
{{-- Remixicon CDN --}}
<link href="https://cdn.jsdelivr.net/npm/remixicon@4.2.0/fonts/remixicon.css" rel="stylesheet"/>

<style>
    .exam-card {
        border: 1px solid var(--el-border);
        border-radius: 8px;
        box-shadow: 0 4px 14px rgba(2,6,23,.05);
        transition: box-shadow .2s ease-in-out, transform .2s ease-in-out;
    }
    .exam-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 10px 22px rgba(2,6,23,.08)!important;
    }
    .nav-tabs-custom {
        border-bottom: 0 !important;
    }
    .nav-tabs-custom .nav-link {
        border: 1px solid var(--el-border);
        border-radius: 8px;
        color: var(--el-primary);
        background: var(--el-primary-soft) !important;
        font-weight: 700;
        padding: 0.58rem 1rem;
        margin-left: 8px;
        line-height: 1.2;
    }
    .nav-tabs-custom .nav-link:hover,
    .nav-tabs-custom .nav-link:focus,
    .nav-tabs-custom .nav-link:focus-visible {
        border-color: var(--el-primary) !important;
        box-shadow: none !important;
        outline: 0 !important;
        color: var(--el-primary) !important;
    }
    .nav-tabs-custom .nav-link::after,
    .nav-tabs-custom .nav-link.active::after {
        display: none !important;
        content: none !important;
        border: 0 !important;
    }
    .nav-tabs-custom .nav-link.active,
    .nav-tabs-custom .nav-item.show .nav-link {
        color: var(--theme-button-text, #fff) !important;
        background: var(--el-primary) !important;
        border-color: var(--el-primary) !important;
    }
    .student-stats-card {
        background: #fff;
        border: 1px solid var(--el-border);
        border-radius: 8px;
        box-shadow: 0 8px 22px rgba(2,6,23,.06);
        overflow: hidden;
    }
    .student-stats-title {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        color: var(--el-heading, #111827);
        font-weight: 800;
    }
    .student-stat-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 12px;
    }
    .student-stat-tile {
        background: var(--el-primary-soft);
        border: 1px solid rgba(var(--el-primary-rgb), .16);
        border-radius: 8px;
        padding: 14px 12px;
        text-align: center;
    }
    .student-stat-value {
        color: var(--el-primary);
        font-size: 26px;
        font-weight: 800;
        line-height: 1;
        margin-bottom: 8px;
    }
    .student-stat-label {
        color: var(--el-muted);
        font-size: 12px;
        font-weight: 700;
    }
    .student-tip-panel {
        background: var(--el-secondary-soft);
        border: 1px solid rgba(var(--el-secondary-rgb), .22);
        border-radius: 8px;
        padding: 18px;
        height: 100%;
    }
    .student-tip-panel h6 {
        color: var(--el-heading, #111827);
        font-weight: 800;
    }
    .student-tip-panel p {
        color: var(--el-muted);
    }
    .student-tip-panel .btn {
        background: var(--el-secondary) !important;
        border-color: var(--el-secondary) !important;
        color: var(--theme-button-text, #fff) !important;
        border-radius: 8px;
        font-weight: 800;
        box-shadow: 0 8px 18px rgba(var(--el-secondary-rgb), .18);
    }
    .package-header {
        cursor: pointer;
        padding: 1.25rem;
        background: #ffffff;
        border: 1px solid var(--el-border);
        border-radius: 8px;
        border-left: 5px solid var(--el-primary);
        box-shadow: 0 8px 22px rgba(2,6,23,.06);
        transition: background-color .2s ease, border-color .2s ease, box-shadow .2s ease, color .2s ease;
    }
    .package-header:hover {
        background: var(--el-primary-soft);
        border-left-color: var(--el-primary);
        box-shadow: 0 12px 28px rgba(2,6,23,.09);
    }
    .package-header:hover h5 {
        color: var(--el-primary);
    }
    .package-header .arrow-icon {
        transition: transform 0.3s ease;
    }
    .package-header[aria-expanded="true"] .arrow-icon {
        transform: rotate(180deg);
    }
    .exam-details-inline {
        display: flex;
        flex-wrap: wrap;
        justify-content: space-between;
        align-items: center;
        gap: 1rem;
        padding-top: 0.75rem;
        margin-top: 0.75rem;
        border-top: 1px solid var(--el-border);
    }
    .exam-details-inline .stat-item {
        display: inline-flex;
        align-items: center;
        gap: 0.3rem;
    }
    .card-header.tabs-header {
        background-color: var(--el-surface-muted);
        border-bottom: 1px solid var(--el-border);
    }
    .student-exam-action {
        border-radius: 999px;
        font-weight: 700;
        padding: 0.64rem 1.27rem;
        border: 0 !important;
        box-shadow: 0 6px 14px rgba(2,6,23,.10);
        min-width: 112px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
    }
    .student-exam-action-primary {
        background: var(--el-primary) !important;
        border-color: var(--el-primary) !important;
        color: var(--theme-button-text, #fff) !important;
    }
    .student-exam-action-secondary {
        background: var(--el-secondary) !important;
        border-color: var(--el-secondary) !important;
        color: var(--theme-button-text, #fff) !important;
    }
    .student-exams-table .student-exam-action {
        border-radius: 999px !important;
        color: var(--theme-button-text, #fff) !important;
        font-weight: 600 !important;
        box-shadow: 0 8px 18px rgba(2,6,23,.12) !important;
    }
    .student-exams-table .student-exam-action-primary {
        background: var(--el-primary) !important;
        border-color: var(--el-primary) !important;
    }
    .student-exams-table .student-exam-action-secondary {
        background: var(--el-secondary) !important;
        border-color: var(--el-secondary) !important;
    }
    .student-exam-count {
        background: var(--el-primary);
        color: var(--theme-button-text, #fff);
    }
    .student-package-count {
        display: inline-flex;
        flex-direction: row;
        align-items: center;
        gap: 5px;
        background: var(--el-primary);
        color: var(--theme-button-text, #fff);
        border-radius: 8px;
        min-width: 62px;
        padding: 4px 7px;
        font-weight: 600;
        white-space: nowrap;
        line-height: 1.05;
        box-shadow: 0 8px 18px rgba(var(--el-primary-rgb), .22);
    }
    .student-package-count strong {
        font-size: 14px;
        line-height: 1;
    }
    .student-package-count span {
        font-size: 9px;
        letter-spacing: .02em;
        text-transform: none;
        opacity: .92;
    }
    .student-exams-table {
        margin-bottom: 0;
        table-layout: fixed;
        border: 1px solid var(--el-border);
        border-radius: 8px;
        overflow: hidden;
        background: #fff;
    }
    .student-exams-table thead th {
        background: var(--el-primary-soft);
        border-bottom: 1px solid var(--el-border);
        color: var(--el-heading, #1f2937);
        font-weight: 700;
        text-transform: uppercase;
        font-size: 12px;
        letter-spacing: .02em;
        padding: 16px 20px;
    }
    .student-exams-table td {
        vertical-align: middle;
        border-color: var(--el-border);
        padding: 22px 20px;
    }
    .student-exams-table tbody tr:nth-child(even) {
        background: rgba(var(--el-primary-rgb), .035);
    }
    .student-exams-table tbody tr:hover {
        background: rgba(var(--el-primary-rgb), .09);
    }
    .student-paper-index {
        min-width: 22px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: transparent;
        border: 0;
        color: var(--el-primary);
        font-weight: 800;
        font-size: 12px;
        flex: 0 0 auto;
    }
    .student-exams-table .student-paper-index {
        background: transparent !important;
        border: 0 !important;
        color: var(--el-primary) !important;
        box-shadow: none;
    }
    .student-exam-name {
        font-weight: 700;
        color: var(--el-heading, #111827) !important;
        font-size: 14px;
        line-height: 1.35;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .student-exam-info {
        min-width: 0;
    }
    .student-exam-meta {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        color: var(--el-muted);
        font-size: 12px;
        margin-top: 4px;
    }
    .student-status-pill {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        color: var(--el-primary);
        background: var(--el-primary-soft);
        border: 1px solid rgba(var(--el-primary-rgb), .18);
        border-radius: 999px;
        padding: 7px 12px;
        font-size: 13px;
        font-weight: 800;
    }
    .student-pass-pill {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        color: var(--el-secondary);
        background: var(--el-secondary-soft);
        border-radius: 999px;
        padding: 5px 10px;
        font-size: 12px;
        font-weight: 800;
        margin-top: 0;
    }
    .student-time-cell {
        color: var(--el-heading, #1f2937);
        font-weight: 700;
        white-space: nowrap;
    }
    .student-metric-chip {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        background: var(--el-surface-muted);
        border: 1px solid var(--el-border);
        border-radius: 999px;
        padding: 5px 10px;
        color: var(--el-heading, #111827) !important;
        font-size: 12px;
        font-weight: 500;
        white-space: nowrap;
    }
    .student-exams-table .student-metric-chip,
    .student-exams-table .student-status-pill,
    .student-exams-table .student-pass-pill,
    .student-exams-table .student-attempt-box {
        border-width: 1px !important;
        border-style: solid !important;
    }
    .student-exams-table .student-metric-chip {
        background: var(--el-surface-muted) !important;
        border-color: var(--el-border) !important;
        color: var(--el-heading, #111827) !important;
    }
    .student-exams-table .student-metric-chip i {
        color: var(--el-primary) !important;
    }
    .student-exams-table .student-status-pill,
    .student-exams-table .student-attempt-box {
        background: var(--el-primary-soft) !important;
        border-color: rgba(var(--el-primary-rgb), .20) !important;
        color: var(--el-primary) !important;
    }
    .student-exams-table .student-pass-pill {
        background: var(--el-secondary-soft) !important;
        border-color: rgba(var(--el-secondary-rgb), .20) !important;
        color: var(--el-secondary) !important;
    }
    .student-paper-details {
        display: flex;
        flex-wrap: wrap;
        gap: 9px;
        margin-top: 12px;
    }
    .student-attempt-box {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        color: var(--el-primary);
        background: var(--el-primary-soft);
        border: 1px solid rgba(var(--el-primary-rgb), .18);
        border-radius: 999px;
        padding: 5px 10px;
        font-size: 12px;
        font-weight: 500;
    }
    .student-pdf-action {
        min-width: 94px !important;
        padding: 0.52rem 0.78rem !important;
        text-decoration: none;
    }
    .student-exam-action-icon {
        width: 38px !important;
        min-width: 38px !important;
        padding-left: 0 !important;
        padding-right: 0 !important;
        justify-content: center;
    }
    .student-exam-actions {
        display: inline-flex;
        flex-wrap: wrap;
        justify-content: flex-end;
        gap: 12px;
    }
    .student-exams-table,
    .student-exams-table td,
    .student-exams-table th {
        color: var(--el-heading, #111827);
    }
    .package-management-actions {
        align-items: center;
        display: flex;
        gap: 8px;
    }
    .package-hide-form {
        display: inline-flex;
        margin: 0;
    }
    .package-hide-button {
        align-items: center;
        background: #fff;
        border: 1px solid rgba(var(--el-primary-rgb), .22);
        border-radius: 999px;
        color: var(--el-muted, #64748b);
        display: inline-flex;
        font-size: 11px;
        font-weight: 750;
        gap: 5px;
        min-height: 32px;
        padding: 5px 10px;
    }
    .package-hide-button:hover,
    .package-hide-button:focus {
        background: var(--el-primary-soft);
        border-color: var(--el-primary);
        color: var(--el-primary);
        outline: 0;
    }
    .hidden-packages-panel {
        background: var(--el-surface-muted, #f8fafc);
        border: 1px dashed rgba(var(--el-primary-rgb), .24);
        border-radius: 10px;
        overflow: hidden;
    }
    .hidden-packages-toggle {
        align-items: center;
        background: transparent;
        border: 0;
        color: var(--el-heading, #111827);
        display: flex;
        font-weight: 800;
        justify-content: space-between;
        padding: 14px 16px;
        text-align: left;
        width: 100%;
    }
    .hidden-packages-toggle .ri-arrow-down-s-line {
        transition: transform .2s ease;
    }
    .hidden-packages-toggle[aria-expanded="true"] .ri-arrow-down-s-line {
        transform: rotate(180deg);
    }
    .hidden-package-list {
        border-top: 1px solid var(--el-border);
        padding: 6px 16px;
    }
    .hidden-package-row {
        align-items: center;
        display: flex;
        gap: 12px;
        justify-content: space-between;
        padding: 12px 0;
    }
    .hidden-package-row + .hidden-package-row {
        border-top: 1px solid var(--el-border);
    }
    .package-restore-button {
        background: var(--el-primary-soft);
        border: 1px solid rgba(var(--el-primary-rgb), .22);
        border-radius: 999px;
        color: var(--el-primary);
        font-size: 11px;
        font-weight: 800;
        padding: 6px 11px;
        white-space: nowrap;
    }
    .package-restore-button:hover,
    .package-restore-button:focus {
        background: var(--el-primary);
        color: var(--theme-button-text, #fff);
    }
    .group-onboarding-modal .modal-content {
        background: #fff;
        border: 1px solid rgba(var(--el-primary-rgb), .18);
        border-radius: 18px;
        box-shadow: 0 28px 70px rgba(15, 23, 42, .22);
        overflow: hidden;
    }
    .group-onboarding-head {
        background: linear-gradient(145deg, var(--el-primary-soft), #fff 72%);
        border-bottom: 1px solid rgba(var(--el-primary-rgb), .12);
        padding: 24px 24px 20px;
        text-align: center;
    }
    .group-onboarding-icon {
        align-items: center;
        background: var(--el-primary);
        border-radius: 14px;
        box-shadow: 0 12px 28px rgba(var(--el-primary-rgb), .24);
        color: var(--theme-button-text, #fff);
        display: inline-flex;
        font-size: 26px;
        height: 54px;
        justify-content: center;
        margin-bottom: 14px;
        width: 54px;
    }
    .group-onboarding-kicker {
        color: var(--el-primary);
        display: block;
        font-size: 11px;
        font-weight: 850;
        letter-spacing: .08em;
        margin-bottom: 5px;
        text-transform: uppercase;
    }
    .group-onboarding-head h4 {
        color: var(--el-heading, #111827);
        font-size: 1.35rem;
        font-weight: 850;
        margin-bottom: 7px;
    }
    .group-onboarding-head p {
        color: var(--el-muted, #64748b);
        font-size: 13px;
        line-height: 1.55;
        margin: 0 auto;
        max-width: 390px;
    }
    .group-onboarding-body {
        padding: 22px 24px 24px;
    }
    .group-onboarding-label {
        color: var(--el-heading, #111827);
        font-size: 12px;
        font-weight: 800;
        margin-bottom: 8px;
    }
    .group-onboarding-note {
        align-items: flex-start;
        background: var(--el-primary-soft);
        border: 1px solid rgba(var(--el-primary-rgb), .14);
        border-radius: 10px;
        color: var(--el-muted, #64748b);
        display: flex;
        font-size: 11px;
        gap: 8px;
        line-height: 1.45;
        margin: 14px 0 18px;
        padding: 10px 12px;
    }
    .group-onboarding-note i {
        color: var(--el-primary);
        font-size: 16px;
        margin-top: 1px;
    }
    .group-onboarding-modal .select2-container {
        width: 100% !important;
    }
    .group-onboarding-modal .select2-container--default .select2-selection--single {
        align-items: center;
        background: #fff;
        border: 1px solid rgba(var(--el-primary-rgb), .24);
        border-radius: 11px;
        display: flex;
        min-height: 62px;
        padding: 0 42px 0 14px;
    }
    .group-onboarding-modal .select2-container--default.select2-container--focus .select2-selection--single,
    .group-onboarding-modal .select2-container--default.select2-container--open .select2-selection--single {
        border-color: var(--el-primary);
        box-shadow: 0 0 0 3px rgba(var(--el-primary-rgb), .1);
    }
    .group-onboarding-modal .select2-selection__rendered {
        color: var(--el-heading, #111827) !important;
        font-weight: 700;
        line-height: 1.35 !important;
        padding: 0 !important;
    }
    .group-option {
        display: flex;
        flex-direction: column;
        gap: 2px;
        line-height: 1.25;
    }
    .group-option-name {
        color: inherit;
        font-size: 13px;
        font-weight: 800;
    }
    .group-option-count {
        color: var(--el-muted, #64748b);
        font-size: 10px;
        font-weight: 600;
    }
    .select2-results__option--highlighted .group-option-count {
        color: rgba(255, 255, 255, .82);
    }
    .group-onboarding-modal .select2-selection__rendered .group-option-name {
        color: var(--el-heading, #111827);
    }
    .group-onboarding-modal .select2-selection__arrow {
        height: 60px !important;
        right: 10px !important;
    }    .group-onboarding-modal .select2-dropdown {
        border: 1px solid rgba(var(--el-primary-rgb), .22);
        border-radius: 11px;
        box-shadow: 0 16px 36px rgba(15, 23, 42, .14);
        overflow: hidden;
    }
    .group-onboarding-modal .select2-search--dropdown {
        background: var(--el-primary-soft);
        padding: 10px;
    }
    .group-onboarding-modal .select2-search__field {
        border: 1px solid rgba(var(--el-primary-rgb), .2) !important;
        border-radius: 8px;
        min-height: 38px;
        padding: 7px 10px !important;
    }
    .group-onboarding-modal .select2-results__option {
        color: var(--el-heading, #111827);
        font-size: 13px;
        padding: 10px 12px;
    }
    .group-onboarding-modal .select2-results__option--highlighted[aria-selected] {
        background: var(--el-primary) !important;
        color: var(--theme-button-text, #fff) !important;
    }
    .group-onboarding-modal .select2-results__option[aria-selected="true"] {
        background: var(--el-primary-soft);
        color: var(--el-primary);
        font-weight: 750;
    }
    .group-onboarding-submit {
        background: var(--el-primary) !important;
        border: 1px solid var(--el-primary) !important;
        border-radius: 11px !important;
        box-shadow: 0 10px 22px rgba(var(--el-primary-rgb), .22);
        color: var(--theme-button-text, #fff) !important;
        font-weight: 850 !important;
        min-height: 48px;
        width: 100%;
    }
    @media (max-width: 767.98px) {
        .card-header.tabs-header {
            align-items: flex-start !important;
            gap: 14px;
            flex-direction: column;
        }
        .nav-tabs-custom {
            width: 100%;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px;
        }
        .nav-tabs-custom .nav-link {
            margin-left: 0;
            text-align: center;
            justify-content: center;
        }
        .student-stat-grid {
            grid-template-columns: 1fr;
        }
        .package-header > .d-flex {
            align-items: flex-start !important;
            gap: 12px;
            flex-direction: column;
        }
        .package-management-actions {
            flex-wrap: wrap;
            width: 100%;
        }
        .hidden-package-row {
            align-items: flex-start;
            flex-direction: column;
        }
        .group-onboarding-head,
        .group-onboarding-body {
            padding-left: 18px;
            padding-right: 18px;
        }
        .student-package-count {
            min-width: 79px;
        }
        .student-exams-table {
            border: 0;
            background: transparent;
        }
        .student-exams-table thead {
            display: none;
        }
        .student-exams-table,
        .student-exams-table tbody,
        .student-exams-table tr,
        .student-exams-table td {
            display: block;
            width: 100%;
        }
        .student-exams-table tr {
            border: 1px solid var(--el-border);
            border-radius: 8px;
            overflow: hidden;
            margin-bottom: 14px;
            background: #fff;
            box-shadow: 0 8px 22px rgba(2,6,23,.06);
        }
        .student-exams-table td {
            border-bottom: 0;
            padding: 14px;
        }
        .student-exams-table td.text-end {
            text-align: left !important;
            padding-top: 0;
        }
        .student-exam-actions {
            display: grid;
            grid-template-columns: 1fr 1fr;
            width: 100%;
        }
        .student-exam-action {
            width: 100%;
            min-width: 0;
        }
    }
    .student-exam-load-more-wrap{display:flex;align-items:center;justify-content:space-between;gap:16px;padding:16px 20px;border-top:1px solid var(--el-border);background:rgba(var(--el-primary-rgb),.025)}
    .student-exam-load-more-status{color:var(--el-muted,#64748b);font-size:13px;font-weight:600}
    .student-package-load-more{border:1px solid var(--el-secondary);border-radius:10px;background:var(--el-secondary);color:var(--theme-button-text,#fff);min-height:42px;padding:9px 18px;display:inline-flex;align-items:center;justify-content:center;gap:8px;font-weight:700;box-shadow:0 7px 16px rgba(2,6,23,.12)}
    .student-package-load-more:hover:not(:disabled){color:var(--theme-button-text,#fff);transform:translateY(-1px)}
    .student-package-load-more:disabled{opacity:.7;cursor:wait}
    @media(max-width:575.98px){.student-exam-load-more-wrap{flex-direction:column;align-items:stretch}.student-exam-load-more-status{text-align:center}.student-package-load-more{width:100%}}
</style>
@endsection


@section('content')

{{-- Success/Error Messages --}}
@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="{{ __('messages.my_exams_modal_close_button') }}"></button>
    </div>
@endif
@if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        {{ session('error') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="{{ __('messages.my_exams_modal_close_button') }}"></button>
    </div>
@endif


{{-- ✅ SMART PENDING EXAM SECTION (Resume vs Finalize Logic) --}}
@if(isset($pendingExam) && $pendingExam)
    @php
        // 1. Calculations Logic
        $startTime = \Carbon\Carbon::parse($pendingExam->start_time);
        $now = \Carbon\Carbon::now();
        $exam = $pendingExam->exam;

        // Step A: Calculate 'Duration' End Time
        $deadline = null;
        if($exam->duration > 0) {
            // ✅ FIX APPLIED HERE: Added (int) casting
            $deadline = $startTime->copy()->addMinutes((int)$exam->duration);
        }

        // Step B: Calculate 'Hard Stop' (Exam Date End) - Movie Logic
        if ($exam->end_date) {
            $hardStop = \Carbon\Carbon::parse($exam->end_date);

            // Agar Duration wala time HardStop ke baad ja raha hai, to HardStop hi final hai
            if ($deadline == null || $deadline->gt($hardStop)) {
                $deadline = $hardStop;
            }
        }

        // Step C: Check if current time is BEFORE deadline
        // If deadline is null (Unlimited time), then canResume is true
        $canResume = $deadline ? $now->lt($deadline) : true;
    @endphp

    <div class="alert alert-warning" style="background-color: #fff3cd; border-color: #ffeeba; color: #856404; padding: 15px; border-radius: 5px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center;">
        <div>
            <strong>@lang('messages.my_exams_pending_title')</strong>
            <p style="margin: 0; padding: 0;">@lang('messages.my_exams_pending_desc', ['exam_name' => $pendingExam->exam->name])</p>
            @if($deadline)
                <small class="text-muted">{{ __('ui.auto_submit_at') }} {{ $deadline->format('d M, h:i A') }}</small>
            @endif
        </div>

        @if($canResume)
            {{-- ✅ RESUME BUTTON: Agar time bacha hai --}}
            <a href="{{ route('student.startExam', $pendingExam->exam->id) }}" class="btn student-exam-action student-exam-action-primary" style="padding: 10px 15px; border-radius: 999px; font-weight: bold; text-decoration: none;">
                <i class="ri-play-circle-line align-middle me-1"></i> Resume Exam
            </a>
        @else
            {{-- ✅ FINALIZE BUTTON: Agar time khatam ho gaya --}}
            <form action="{{ route('student.finalizePending') }}" method="POST" data-swal-confirm="Time is over. Submit result now?">
                @csrf
                <button type="submit" class="btn btn-danger" style="background-color: #dc3545; color: white; border: none; padding: 10px 15px; border-radius: 5px; cursor: pointer; font-weight: bold;">
                    <i class="ri-stop-circle-line align-middle me-1"></i> Finalize Result
                </button>
            </form>
        @endif
    </div>
@endif
{{-- --- END PENDING SECTION --- --}}


{{-- STATS SECTION --}}
<div class="student-stats-card">
    <div class="card-body">
        <div class="row align-items-stretch g-3">
            <div class="col-lg-7">
                <h5 class="student-stats-title mb-3"><i class="ri-bar-chart-box-line"></i> @lang('messages.my_exams_stats_title')</h5>
                <div class="student-stat-grid">
                    <div class="student-stat-tile"><div class="student-stat-value">{{ $performanceStats->total_attempts ?? 0 }}</div><div class="student-stat-label">@lang('messages.my_exams_total_attempts')</div></div>
                    <div class="student-stat-tile"><div class="student-stat-value">{{ round($performanceStats->average_score ?? 0, 1) }}%</div><div class="student-stat-label">@lang('messages.my_exams_avg_score')</div></div>
                    <div class="student-stat-tile"><div class="student-stat-value">{{ round($successRate ?? 0, 1) }}%</div><div class="student-stat-label">@lang('messages.my_exams_success_rate')</div></div>
                </div>
            </div>
            <div class="col-lg-5">
                <div class="student-tip-panel">
                    <h6 class="mb-2"><i class="ri-lightbulb-line me-1"></i> @lang('messages.my_exams_pro_tip')</h6>
                    <p class="text-muted small">
                        @if($performanceStats && $performanceStats->total_attempts > 0)
                            @if($successRate >= 75) @lang('messages.my_exams_pro_tip_good')
                            @elseif($successRate >= 50) @lang('messages.my_exams_pro_tip_ok')
                            @else @lang('messages.my_exams_pro_tip_bad')
                            @endif
                        @else @lang('messages.my_exams_pro_tip_new')
                        @endif
                    </p>
                    <a href="{{ route('student.results') }}" class="btn btn-sm">@lang('messages.my_exams_review_results_button')</a>
                </div>
            </div>
        </div>
    </div>
</div>


<div class="card mt-4">
    <div class="card-header d-flex justify-content-between align-items-center tabs-header">
        <h4 class="card-title mb-0 fw-bold">@lang('messages.my_exams_title')</h4>
        <ul class="nav nav-tabs nav-tabs-custom" role="tablist">
            <li class="nav-item" role="presentation"><a class="nav-link active" data-bs-toggle="tab" href="#coursesTab" role="tab" aria-selected="true">@lang('messages.my_exams_my_courses_tab')</a></li>
            <li class="nav-item" role="presentation"><a class="nav-link" data-bs-toggle="tab" href="#practiceTestsTab" role="tab" aria-selected="false">@lang('messages.my_exams_practice_tab')</a></li>
        </ul>
    </div>
    <div class="card-body">
        <div class="tab-content">
            <div class="tab-pane active" id="coursesTab" role="tabpanel">
                @if(($hiddenPackages ?? collect())->isNotEmpty())
                    <div class="hidden-packages-panel mb-4">
                        <button class="hidden-packages-toggle" type="button" data-bs-toggle="collapse" data-bs-target="#hiddenPackagesList" aria-expanded="false" aria-controls="hiddenPackagesList">
                            <span><i class="ri-eye-off-line me-2" style="color:var(--el-primary);"></i>{{ __('ui.hidden_packages') }} <span class="text-muted">({{ $hiddenPackages->count() }})</span></span>
                            <i class="ri-arrow-down-s-line"></i>
                        </button>
                        <div class="collapse" id="hiddenPackagesList">
                            <div class="hidden-package-list">
                                @foreach($hiddenPackages as $hiddenPackage)
                                    <div class="hidden-package-row">
                                        <div>
                                            <div class="fw-bold" style="color:var(--el-heading, #111827);">{{ $hiddenPackage->name }}</div>
                                            <div class="small text-muted">{{ $hiddenPackage->exams_count }} {{ __('ui.active_exams_access') }}</div>
                                        </div>
                                        <form method="POST" action="{{ route('student.myexams.packages.restore', $hiddenPackage) }}">
                                            @csrf
                                            <button type="submit" class="package-restore-button"><i class="ri-arrow-go-back-line me-1"></i>{{ __('ui.restore') }}</button>
                                        </form>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                @endif

                <div class="vstack">
                    @forelse ($purchasedPackages as $package)
                        @if(!$loop->first)
                            <hr class="my-3">
                        @endif
                        <div>
                            <div class="package-header rounded" data-bs-toggle="collapse" href="#package-{{ $package->id }}" role="button" aria-expanded="true" aria-controls="package-{{ $package->id }}">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h5 class="mb-1 fw-semibold">{{ $package->name }}</h5>
                                        <div class="text-muted small">{{ __('ui.open_course_papers') }}</div>
                                    </div>
                                    <div class="package-management-actions">
                                        <span class="student-package-count">
                                            <strong>{{ $package->dashboard_exam_total ?? $package->exams->count() }}</strong>
                                            <span>@lang('messages.my_exams_exams_badge')</span>
                                        </span>
                                        <form method="POST" action="{{ route('student.myexams.packages.hide', $package) }}" class="package-hide-form" data-package-name="{{ $package->name }}" onclick="event.stopPropagation()">
                                            @csrf
                                            <button type="submit" class="package-hide-button" title="Remove from My Exams">
                                                <i class="ri-eye-off-line"></i>
                                                <span>{{ __('ui.remove') }}</span>
                                            </button>
                                        </form>
                                        <i class="ri-arrow-down-s-line fs-16 align-middle arrow-icon"></i>
                                    </div>
                                </div>
                            </div>
                            <div class="collapse show" id="package-{{ $package->id }}">
                                <div class="card card-body border-top-0 rounded-0 rounded-bottom p-0">
                                    <div class="table-responsive">
                                        <table class="table student-exams-table align-middle">
                                            <thead>
                                                <tr>
                                                    <th>{{ __('messages.exam_sidebar_btn_paper') }}</th>
                                                    <th class="text-end" style="width: 440px;">{{ __('messages.dash_col_action') }}</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @forelse($package->exams as $exam)
                                                    @include('students.partials.my_exam_row', [
                                                        'exam' => $exam,
                                                        'package' => $package,
                                                        'examIndex' => $loop->iteration,
                                                    ])
                                                @empty
                                                    <tr>
                                                        <td colspan="2" class="text-center text-muted p-4">@lang('messages.my_exams_no_exams_in_course')</td>
                                                    </tr>
                                                @endforelse
                                            </tbody>
                                        </table>
                                    </div>
                                    @if(($package->dashboard_exam_total ?? $package->exams->count()) > $package->exams->count())
                                        <div class="student-exam-load-more-wrap" data-package-status>
                                            <div class="student-exam-load-more-status">
                                                {{ __('messages.dash_area_showing') }} <span data-shown>{{ $package->exams->count() }}</span> {{ __('messages.dash_area_of') }} <span data-total>{{ $package->dashboard_exam_total }}</span> {{ __('ui.exams') }}
                                            </div>
                                            <button type="button"
                                                class="student-package-load-more"
                                                data-url="{{ route('student.myexams.packages.exams', $package) }}"
                                                data-offset="{{ $package->exams->count() }}"
                                                data-target="#package-{{ $package->id }} tbody">
                                                <span class="load-more-label">{{ __('ui.load_more_exams') }}</span>
                                                <i class="ri-arrow-down-line"></i>
                                            </button>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="p-4 text-center text-muted py-5">
                            @if(($hiddenPackages ?? collect())->isNotEmpty())
                                <div class="fs-1 mb-3"><i class="ri-eye-off-line"></i></div>
                                <h4>{{ __('ui.all_packages_hidden') }}</h4>
                                <p>{{ __('ui.restore_hidden_copy') }}</p>
                            @else
                                <div class="fs-1 mb-3"><i class="ri-book-open-line"></i></div>
                                <h4>@lang('messages.my_exams_no_courses_title')</h4>
                                <p>@lang('messages.my_exams_no_courses_desc')</p>
                            @endif
                            <a href="{{ route('student.courses.index') }}" class="btn student-exam-action student-exam-action-primary mt-2">@lang('messages.my_exams_browse_courses_button')</a>
                        </div>
                    @endforelse
                </div>

            </div>

            <div class="tab-pane" id="practiceTestsTab" role="tabpanel">
                <div class="row mt-2">
                    @forelse ($generalExams as $exam)
                    <div class="col-12">
                         <div class="card exam-card mb-3">
                            <div class="card-body p-3">
                                <div class="d-flex justify-content-between align-items-start mb-1">
                                    <h5 class="card-title fw-semibold text-dark">{{ $exam->name }}</h5>
                                    <span class="badge bg-info-subtle text-info ms-2">@lang('messages.my_exams_practice_badge')</span>
                                </div>
                                <div class="exam-details-inline">
                                    <div class="d-flex align-items-center flex-wrap gap-3 text-muted small">
                                        <span class="stat-item"><i class="ri-time-line"></i> {{ $exam->duration == 0 ? __('messages.my_exams_js_unlimited') : $exam->duration . ' ' . __('messages.my_exams_mins') }}</span>
                                        <span class="stat-item"><i class="ri-file-text-line"></i> {{ $exam->questions_sum_marks ?? __('messages.my_exams_js_na') }} @lang('messages.my_exams_marks')</span>
                                        <span class="stat-item"><i class="ri-trophy-line"></i> {{ $exam->passing_percentage }}% @lang('messages.my_exams_pass')</span>
                                    </div>
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="text-center">
                                            <div class="fw-bold">{{ $exam->attempts_left }}</div>
                                            <div class="text-muted small lh-1">@lang('messages.my_exams_attempts_label')</div>
                                        </div>
                                        <div class="student-exam-actions">
                                            @if(!$exam->canAttemptOnline())
                                                <span class="badge bg-light text-dark">PDF only</span>
                                                @if(!$exam->packages()->exists() && app(App\Services\ExamPdfPublicationService::class)->source($exam))
                                                    <a class="btn btn-sm student-exam-action student-exam-action-secondary" href="{{ route('exam.print.download', ['id' => $exam->slug ?: $exam->id]) }}">Download official PDF</a>
                                                @endif
                                            @elseif($exam->exam_status == 'Live' && ($exam->attempts_left > 0 || $exam->attempts_left == 'Unlimited'))
                                                <button class="btn btn-sm student-exam-action student-exam-action-primary" onclick="attemptExam({{ $exam->id }}, {{ $exam->attempt_count }})">@lang('messages.my_exams_attempt_button')</button>
                                            @elseif($exam->latest_result_id)
                                                <a href="{{ route('student.results.view', $exam->latest_result_id) }}" class="btn btn-sm student-exam-action student-exam-action-secondary">@lang('messages.my_exams_result_button')</a>
                                            @endif
                                            @if($exam->canAttemptOnline())<button class="btn btn-sm student-exam-action student-exam-action-secondary student-exam-action-icon" onclick="showExamDetails({{ $exam->id }})" title="@lang('messages.my_exams_details_button')" aria-label="@lang('messages.my_exams_details_button')"><i class="ri-information-line"></i></button>@endif
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    @empty
                        <div class="col-12">
                            <div class="p-4 text-center text-muted py-5">
                                <div class="fs-1 mb-3">📝</div><h4>@lang('messages.my_exams_no_practice_title')</h4><p>@lang('messages.my_exams_no_practice_desc')</p>
                            </div>
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>

{{-- MODAL --}}
<div class="modal fade" id="examDetailsModal" tabindex="-1" aria-labelledby="examDetailsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="examDetailsModalLabel">@lang('messages.my_exams_modal_title')</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('messages.my_exams_modal_close_button') }}"></button>
            </div>
            <div class="modal-body">
                <div class="text-center" id="loadingProgressBar">
                    <div class="spinner-border" style="color: var(--el-primary);" role="status">
                        <span class="visually-hidden">@lang('messages.my_exams_modal_loading')</span>
                    </div>
                </div>
                <div id="examDetailsContent" style="display: none;">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <p class="mb-2"><strong>@lang('messages.my_exams_modal_name')</strong> <span id="examName"></span></p>
                            <p class="mb-2"><strong>@lang('messages.my_exams_modal_passing')</strong> <span id="passingPercentage"></span>%</p>
                            <p class="mb-2"><strong>@lang('messages.my_exams_modal_start_date')</strong> <span id="startDate"></span></p>
                            <p class="mb-0"><strong>@lang('messages.my_exams_modal_neg_marking')</strong> <span id="negativeMarking"></span></p>
                        </div>
                        <div class="col-md-6">
                            <p class="mb-2"><strong>@lang('messages.my_exams_modal_type')</strong> <span id="examType"></span></p>
                            <p class="mb-2"><strong>@lang('messages.my_exams_modal_duration')</strong> <span id="examDuration"></span></H5>
                            <p class="mb-2"><strong>@lang('messages.my_exams_modal_end_date')</strong> <span id="endDate"></span></p>
                            <p class="mb-0"><strong>@lang('messages.my_exams_modal_total_marks')</strong> <span id="totalMarks"></span></p>
                        </div>
                    </div>
                    <h5 class="mt-4">@lang('messages.my_exams_modal_subject_details')</h5>
                    <table class="table table-bordered table-sm">
                        <thead class="table-light">
                            <tr>
                                <th>@lang('messages.my_exams_modal_subject')</th>
                                <th>@lang('messages.my_exams_modal_total_questions')</th>
                            </tr>
                        </thead>
                        <tbody id="subjectDetails"></tbody>
                    </table>
                    <h5 class="mt-4">@lang('messages.my_exams_modal_syllabus')</h5>
                    <div id="syllabus" class="p-2 border rounded bg-light" style="min-height: 50px;"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">@lang('messages.my_exams_modal_close_button')</button>
                <button type="button" class="btn student-exam-action student-exam-action-primary" id="attemptNowButton">@lang('messages.my_exams_modal_attempt_button')</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade group-onboarding-modal"
     id="groupSelectionModal"
     tabindex="-1"
     data-bs-backdrop="static"
     data-bs-keyboard="false"
     aria-labelledby="groupSelectionTitle"
     aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="group-onboarding-head">
                <span class="group-onboarding-icon"><i class="ri-compass-3-line"></i></span>
                <span class="group-onboarding-kicker">{{ __('ui.personalize_my_exams') }}</span>
                <h4 id="groupSelectionTitle">{{ __('ui.choose_exam_group_your') }}</h4>
                <p>{{ __('ui.onboarding_copy') }}</p>
            </div>
            <div class="group-onboarding-body">
                <form id="selectGroupForm" method="POST" action="{{ route('student.myexams.selectGroup') }}">
                    @csrf
                    <label for="group_id" class="group-onboarding-label">{{ __('ui.search_select_group') }} <span class="text-danger">*</span></label>
                    <select class="form-select select2" id="group_id" name="group_id">
                        <option value=""></option>
                        @foreach($groups as $group)
                            <option value="{{ $group->id }}" data-package-count="{{ $group->free_packages_count ?? 0 }}">
                                {{ $group->group_name }}
                            </option>
                        @endforeach
                    </select>
                    <div class="invalid-feedback group-error"></div>
                    <div class="group-onboarding-note">
                        <i class="ri-shield-check-line"></i>
                        <span>{{ __('ui.control_packages_copy') }}</span>
                    </div>
                    <button type="submit" id="selectGroupFormBtn" class="btn group-onboarding-submit">
                        Continue to My Exams <i class="ri-arrow-right-line ms-1"></i>
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

@endsection

@push('scripts')
{{-- ========================================================================= --}}
{{-- JavaScript ke liye translations pass karna --}}
{{-- ========================================================================= --}}
<script>
    const LANG = {
        no_attempts: "@lang('messages.my_exams_js_no_attempts')",
        yes: "@lang('messages.my_exams_js_yes')",
        no: "@lang('messages.my_exams_js_no')",
        unlimited: "@lang('messages.my_exams_js_unlimited')",
        minutes: "@lang('messages.my_exams_js_minutes')",
        no_subjects: "@lang('messages.my_exams_js_no_subjects')",
        not_available: "@lang('messages.my_exams_js_not_available')",
        load_fail: "@lang('messages.my_exams_js_load_fail')",
    };

    @if(empty($hasSelectedGroup))
        document.addEventListener('DOMContentLoaded', function () {

            const modal = new bootstrap.Modal(
                document.getElementById('groupSelectionModal')
            );

            modal.show();

        });
    @endif
</script>

<script>
$(document).ready(function () {
    const groupField = $('#group_id');
    const groupModal = $('#groupSelectionModal');

    if (groupField.length && $.fn.select2) {
        if (groupField.hasClass('select2-hidden-accessible')) {
            groupField.select2('destroy');
        }

        const formatGroupOption = function (option) {
            if (!option.id) {
                return option.text;
            }

            const count = Number($(option.element).data('package-count') || 0);
            const packageLabel = count === 1 ? 'free package' : 'free packages';
            const wrapper = $('<span class="group-option"></span>');
            $('<span class="group-option-name"></span>').text(option.text.trim()).appendTo(wrapper);
            $('<span class="group-option-count"></span>').text(count + ' ' + packageLabel).appendTo(wrapper);

            return wrapper;
        };

        groupField.select2({
            dropdownParent: groupModal,
            placeholder: 'Search and select your exam group',
            templateResult: formatGroupOption,
            templateSelection: formatGroupOption,
            width: '100%'
        });
    }

    $('#selectGroupForm').on('submit', function (event) {
        event.preventDefault();

        const form = $(this);
        const button = $('#selectGroupFormBtn');
        const originalButton = button.html();

        $('.group-error').html('').hide();
        groupField.removeClass('is-invalid');

        if (!groupField.val()) {
            groupField.addClass('is-invalid');
            $('.group-error').html('Please select your exam group.').show();
            return;
        }

        button.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span>Preparing My Exams...');

        $.ajax({
            url: form.attr('action'),
            type: 'POST',
            data: form.serialize(),
            success: function (response) {
                if (!response.status) {
                    button.prop('disabled', false).html(originalButton);
                    if (window.Swal) {
                        Swal.fire({
                            icon: 'info',
                            title: 'Group not changed',
                            text: response.message || 'Please select another group.',
                            confirmButtonColor: getComputedStyle(document.documentElement).getPropertyValue('--el-primary').trim() || '#0f7f75'
                        });
                    }
                    return;
                }

                const packageCount = Number(response.packages_added || 0);
                const message = packageCount > 0
                    ? packageCount + ' free package' + (packageCount === 1 ? '' : 's') + ' added to My Exams.'
                    : 'Your group is saved. Your existing packages are ready.';

                if (window.Swal) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Your My Exams area is ready',
                        text: message,
                        confirmButtonText: 'Continue',
                        confirmButtonColor: getComputedStyle(document.documentElement).getPropertyValue('--el-primary').trim() || '#0f7f75',
                        allowOutsideClick: false
                    }).then(function () {
                        location.reload();
                    });
                } else {
                    location.reload();
                }
            },
            error: function (xhr) {
                button.prop('disabled', false).html(originalButton);
                const errors = xhr.responseJSON && xhr.responseJSON.errors ? xhr.responseJSON.errors : {};

                if (xhr.status === 422 && errors.group_id) {
                    groupField.addClass('is-invalid');
                    $('.group-error').html(errors.group_id[0]).show();
                    return;
                }

                if (window.Swal) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Could not save your group',
                        text: (xhr.responseJSON && xhr.responseJSON.message) || 'Please try again.',
                        confirmButtonColor: getComputedStyle(document.documentElement).getPropertyValue('--el-primary').trim() || '#0f7f75'
                    });
                }
            }
        });
    });

    document.querySelectorAll('.package-hide-form').forEach(function (form) {
        form.addEventListener('submit', function (event) {
            if (form.dataset.confirmed === '1' || !window.Swal) return;

            event.preventDefault();
            event.stopPropagation();

            Swal.fire({
                icon: 'question',
                title: 'Remove from My Exams?',
                text: 'This package will be hidden from My Exams. Your access, attempts and results will remain safe, and you can restore it later.',
                confirmButtonText: 'Remove from My Exams',
                showCancelButton: true,
                cancelButtonText: 'Keep package',
                confirmButtonColor: getComputedStyle(document.documentElement).getPropertyValue('--el-primary').trim() || '#0f7f75',
                cancelButtonColor: '#64748b',
                reverseButtons: true
            }).then(function (result) {
                if (result.isConfirmed) {
                    form.dataset.confirmed = '1';
                    form.submit();
                }
            });
        });
    });
});
</script>

<script>
    function attemptExam(examId, attemptCount) {
        const guidelineUrl = `{{ url('exam/instructions') }}/${examId}`;
        const checkAttemptsUrl = `{{ url('exam/check-attempts') }}/${examId}`;
        if (attemptCount === 0) {
            window.location.href = guidelineUrl;
        } else {
            fetch(checkAttemptsUrl)
                .then(response => response.json())
                .then(data => {
                    if (data.attempts_left > 0 || data.attempts_left === 'Unlimited') {
                        window.location.href = guidelineUrl;
                    } else {
                        alert(LANG.no_attempts);
                        location.reload();
                    }
                });
        }
    }
    function showExamDetails(examId) {
        const detailsUrl = `{{ url('exam-details') }}/${examId}`;
        const loadingProgressBar = document.getElementById('loadingProgressBar');
        const examDetailsContent = document.getElementById('examDetailsContent');
        const attemptNowButton = document.getElementById('attemptNowButton');
        loadingProgressBar.style.display = 'block';
        examDetailsContent.style.display = 'none';
        attemptNowButton.style.display = 'none';
        const modal = new bootstrap.Modal(document.getElementById('examDetailsModal'));
        modal.show();
        fetch(detailsUrl)
            .then(response => {
                if (!response.ok) { throw new Error('Network response was not ok'); }
                return response.json();
            })
            .then(data => {
                const exam = data.exam;
                document.getElementById('examName').innerText = exam.name;
                document.getElementById('passingPercentage').innerText = exam.passing_percentage;
                document.getElementById('startDate').innerText = new Date(exam.start_date).toLocaleString();
                document.getElementById('endDate').innerText = new Date(exam.end_date).toLocaleString();
                document.getElementById('negativeMarking').innerText = exam.negative_marking ? LANG.yes : LANG.no;
                document.getElementById('examType').innerText = exam.mode;
                document.getElementById('examDuration').innerText = exam.duration == 0 ? LANG.unlimited : exam.duration + ' ' + LANG.minutes;
                document.getElementById('totalMarks').innerText = data.total_marks;
                const subjectDetails = document.getElementById('subjectDetails');
                subjectDetails.innerHTML = '';
                if (exam.subject_details && exam.subject_details.length > 0) {
                    exam.subject_details.forEach(subject => {
                        const row = document.createElement('tr');
                        row.innerHTML = `<td>${subject.subject_name}</td><td>${subject.total_questions}</td>`;
                        subjectDetails.appendChild(row);
                    });
                } else {
                     subjectDetails.innerHTML = `<tr><td colspan="2" class="text-center">${LANG.no_subjects}</td></tr>`;
                }
                const syllabusContainer = document.getElementById('syllabus');
                syllabusContainer.innerHTML = exam.syllabus || LANG.not_available;
                loadingProgressBar.style.display = 'none';
                examDetailsContent.style.display = 'block';
                const isLive = new Date() > new Date(exam.start_date) && new Date() < new Date(exam.end_date);
                const hasAttempts = exam.attempts_left > 0 || exam.attempts_left === 'Unlimited';
                if (isLive && hasAttempts) {
                    attemptNowButton.style.display = 'block';
                    attemptNowButton.onclick = () => attemptExam(examId, exam.attempt_count);
                }
            })
            .catch(error => {
                console.error('Error fetching exam details:', error);
                loadingProgressBar.style.display = 'none';
                const errorDiv = document.createElement('div');
                errorDiv.innerHTML = `<p class="text-danger text-center">${LANG.load_fail}</p>`;
                examDetailsContent.innerHTML = '';
                examDetailsContent.appendChild(errorDiv);
                examDetailsContent.style.display = 'block';
            });
    }
</script>
<script src="{{ asset('js/student-my-exams-load-more.js') }}"></script>
@endpush

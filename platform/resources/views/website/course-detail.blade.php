@extends('website.layouts.app')

@php
    $displayText = static function ($value): string {
        if (is_array($value)) {
            $candidate = $value['en'] ?? reset($value);
            return is_scalar($candidate) ? (string) $candidate : '';
        }

        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $candidate = $decoded['en'] ?? reset($decoded);
                return is_scalar($candidate) ? (string) $candidate : '';
            }
        }

        return is_scalar($value) ? (string) $value : '';
    };

    $packageDisplayName = $displayText($package->name);
    $packageDescriptionText = $displayText($package->description);
@endphp

@section('title', $packageDisplayName . ' - ' . ($configuration->name ?? 'ExamElite'))

@section('content')

<style>
    :root {
        --teal-primary: var(--theme-primary);
        --teal-dark: var(--theme-primary);
        --teal-light: color-mix(in srgb, var(--theme-primary, #0f766e) 16%, #ffffff);
        --coral: var(--theme-secondary);
        --coral-dark: color-mix(in srgb, var(--theme-secondary, #f59e0b) 88%, #000000);
    }

    /* Premium Header - Changed Color */
    .course-header {
        background: color-mix(in srgb, var(--theme-primary, #0f766e) 7%, var(--theme-body-bg, #ffffff));
        position: relative;
        overflow: hidden;
        padding: 40px 0 35px;
        margin-top: 0;
    }
    .course-header::before,
    .course-header::after {
        display: none;
    }
    
    .course-header h1 {
        color: var(--theme-heading, #0f172a) !important;
        font-size: 1.6rem;
        line-height: 1.3;
    }
    
    /* Breadcrumb - No background */
    .breadcrumb-custom {
        background: transparent;
        padding: 0;
        margin-bottom: 20px;
    }
    .breadcrumb-custom .breadcrumb-item a {
        color: var(--theme-primary, #0f766e) !important;
        font-size: 12px;
        text-decoration: none;
    }
    .breadcrumb-custom .breadcrumb-item a:hover {
        color: var(--theme-secondary) !important;
    }
    .breadcrumb-custom .breadcrumb-item.active {
        color: var(--theme-text, #64748b) !important;
        font-size: 12px;
    }
    .breadcrumb-custom .breadcrumb-item + .breadcrumb-item::before {
        color: color-mix(in srgb, var(--theme-primary, #0f766e) 60%, transparent);
        content: "›";
        font-size: 14px;
    }
    
    .course-header .stat-text {
        color: var(--theme-text, #64748b) !important;
        font-size: 13px;
    }

    /* Main Section Background - Attractive */
    .main-section {
        background: var(--theme-body-bg, #ffffff);
        padding: 50px 0;
    }

    /* Zebra Table Styles - PROPER ALTERNATE ROWS */
    .exam-table {
        width: 100%;
        border-collapse: collapse;
        background: var(--theme-card-bg, #ffffff);
        border: 1px solid color-mix(in srgb, var(--theme-primary, #0f766e) 14%, #e2e8f0);
        border-radius: 10px;
        overflow: hidden;
        box-shadow: 0 8px 24px rgba(15, 23, 42, 0.06);
    }
    .exam-table tbody tr {
        border-bottom: 1px solid color-mix(in srgb, var(--theme-primary, #0f766e) 14%, #e2e8f0);
    }
    /* Zebra striping - even rows light gray, odd rows white */
    .exam-table tbody tr:nth-child(even) {
        background-color: color-mix(in srgb, var(--theme-primary, #0f766e) 4%, #ffffff);
    }
    .exam-table tbody tr:nth-child(odd) {
        background-color: var(--theme-card-bg, #ffffff);
    }
    .exam-table tbody tr:hover {
        background-color: color-mix(in srgb, var(--theme-primary, #0f766e) 9%, #ffffff);
    }
    .exam-table td {
        padding: 20px 14px;
        vertical-align: middle;
    }
    .exam-table td:first-child {
        padding-left: 16px;
        width: 64px;
        color: var(--theme-primary, #0f766e);
        font-size: 16px;
        font-weight: 800;
        text-align: center;
    }
    .exam-table td:last-child {
        padding-right: 16px;
        text-align: right;
        white-space: nowrap;
        width: 330px;
    }

    /* Exam Name */
    .exam-name-link {
        color: var(--theme-heading, #0f172a) !important;
        text-decoration: none;
        font-weight: 800;
        font-size: 1rem;
        display: inline-block;
        word-break: break-word;
        line-height: 1.4;
    }
    .exam-name-link:hover {
        color: var(--theme-primary, #0f766e) !important;
    }

    .exam-meta-inline {
        display: flex;
        align-items: center;
        gap: 20px;
        flex-wrap: wrap;
        margin-top: 8px;
    }
    .exam-meta-item {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        color: var(--theme-heading, #0f172a);
        font-size: 13px;
        font-weight: 700;
        background: color-mix(in srgb, var(--theme-primary, #0f766e) 8%, #ffffff);
        border: 1px solid color-mix(in srgb, var(--theme-primary, #0f766e) 14%, #e2e8f0);
        padding: 7px 13px;
        border-radius: 30px;
    }
    .exam-meta-item i {
        color: var(--theme-primary, #0f766e);
    }

    /* Buttons */
    .btn-teal {
        background: var(--theme-primary, #0f766e);
        color: var(--theme-button-text, #ffffff);
        border: none;
        transition: all 0.3s ease;
        font-weight: 700;
        border-radius: 8px;
        padding: 9px 18px;
        font-size: 13px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
        cursor: pointer;
        min-width: 118px;
    }
    .btn-teal:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 20px color-mix(in srgb, var(--theme-primary, #0f766e) 26%, transparent);
        color: var(--theme-button-text, #ffffff);
    }

    .btn-coral {
        background: color-mix(in srgb, var(--theme-secondary, #f59e0b) 12%, #ffffff);
        color: color-mix(in srgb, var(--theme-secondary, #f59e0b) 74%, #111827);
        border: 1px solid color-mix(in srgb, var(--theme-secondary, #f59e0b) 36%, #ffffff);
        transition: all 0.3s ease;
        font-weight: 600;
        border-radius: 12px;
        padding: 12px 28px;
    }
    .btn-coral:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 20px color-mix(in srgb, var(--theme-secondary, #f59e0b) 28%, transparent);
        color: color-mix(in srgb, var(--theme-secondary, #f59e0b) 74%, #111827);
    }

    .btn-pdf {
        background: color-mix(in srgb, var(--theme-secondary, #f59e0b) 10%, #ffffff);
        color: color-mix(in srgb, var(--theme-secondary, #f59e0b) 76%, #111827);
        border: 1px solid color-mix(in srgb, var(--theme-secondary, #f59e0b) 44%, #ffffff);
        transition: all 0.3s ease;
        font-weight: 700;
        padding: 8px 12px;
        border-radius: 8px;
        font-size: 13px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
        margin-left: 8px;
        cursor: pointer;
        min-width: 96px;
        text-decoration: none;
    }
    .btn-pdf:hover {
        background: color-mix(in srgb, var(--theme-secondary, #f59e0b) 16%, #ffffff);
        transform: translateY(-2px);
        color: color-mix(in srgb, var(--theme-secondary, #f59e0b) 76%, #111827);
    }

    .sidebar-card {
        background: white;
        border-radius: 20px;
        box-shadow: 0 4px 20px rgba(0,0,0,0.05);
        transition: all 0.3s ease;
    }
    .sidebar-card:hover {
        box-shadow: 0 8px 30px rgba(0,0,0,0.1);
    }

    .badge-teal {
        background: color-mix(in srgb, var(--theme-primary) 12%, transparent);
        color: var(--theme-primary);
        padding: 6px 14px;
        border-radius: 50px;
        font-size: 13px;
        font-weight: 600;
    }

    .exam-count-highlight {
        background: var(--theme-primary, #0f766e);
        color: var(--theme-button-text, #ffffff);
        padding: 8px 20px;
        border-radius: 60px;
        font-weight: 700;
        font-size: 1rem;
        display: inline-block;
    }

    .feature-icon {
        width: 42px;
        height: 42px;
        background: var(--theme-primary, #0f766e);
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: var(--theme-button-text, #ffffff);
        font-size: 20px;
    }

    /* Mobile Responsive - Fixed Number Issue */
    @media (max-width: 768px) {
        .course-header {
            padding: 25px 0 20px;
            margin-top: 10px;
        }
        .course-header h1 {
            font-size: 1.2rem !important;
        }
        .main-section {
            padding: 30px 0;
        }
        
        .sidebar-card.p-4 {
            padding: 1rem !important;
        }

        /* Mobile: Convert table to clear cards */
        .exam-table,
        .exam-table tbody,
        .exam-table tr,
        .exam-table td {
            display: block;
            width: 100%;
        }
        .exam-table tr {
            margin-bottom: 12px;
            border-radius: 10px;
            border: 1px solid color-mix(in srgb, var(--theme-primary, #0f766e) 14%, #e2e8f0);
            overflow: hidden;
            box-shadow: 0 8px 22px rgba(15, 23, 42, 0.06);
        }
        /* Zebra striping on mobile - alternate card backgrounds */
        .exam-table tbody tr:nth-child(even) {
            background-color: #f8fafc;
        }
        .exam-table tbody tr:nth-child(odd) {
            background-color: #ffffff;
        }
        .exam-table td {
            padding: 10px 16px;
            border: none;
        }
        .exam-table td:first-child {
            display: inline-flex;
            width: 38px;
            height: 38px;
            margin: 14px 0 0 14px;
            padding: 0;
            align-items: center;
            justify-content: center;
            border-radius: 999px;
            background: var(--theme-primary, #0f766e);
            color: var(--theme-button-text, #ffffff);
            font-weight: 800;
        }
        .exam-table td:nth-child(2) {
            padding-top: 10px;
        }
        .exam-table td:last-child {
            padding-bottom: 16px;
            text-align: left;
            white-space: normal;
            width: 100%;
        }
        
        .btn-teal,
        .btn-pdf {
            width: 100%;
            margin-left: 0;
            margin-top: 0;
        }
        .exam-table td:last-child {
            display: grid;
            grid-template-columns: 1fr;
            gap: 10px;
        }
        .exam-meta-inline {
            margin-top: 8px;
            gap: 10px;
        }
        .exam-name-link {
            font-size: 0.95rem;
        }
    }

    .package-title-wrap,
    .course-package-card h4,
    .course-package-card h5 {
        white-space: normal !important;
        overflow: visible !important;
        text-overflow: clip !important;
        word-break: break-word;
        overflow-wrap: anywhere;
        line-height: 1.35;
    }

    .package-sidebar-title {
        font-size: 1rem;
        line-height: 1.35;
        white-space: normal;
        overflow: visible;
        text-overflow: clip;
        word-break: break-word;
        overflow-wrap: anywhere;
    }

    .package-sidebar-cta {
        width: 100%;
        padding: 13px 18px;
        border: none;
        border-radius: 10px;
        background: var(--theme-secondary, #f59e0b);
        color: var(--theme-button-text, #ffffff);
        font-weight: 800;
        text-align: center;
        display: flex;
        align-items: center;
        justify-content: center;
        box-shadow: 0 8px 18px rgba(249, 115, 22, 0.24);
    }

    .package-sidebar-cta:hover {
        background: var(--theme-primary, #0f766e) !important;
        border-color: var(--theme-primary, #0f766e) !important;
        color: var(--theme-button-text, #ffffff) !important;
        -webkit-text-fill-color: var(--theme-button-text, #ffffff);
        filter: brightness(0.98);
        transform: translateY(-1px);
    }

    .package-sidebar-cta:active,
    .package-sidebar-cta:focus,
    .package-sidebar-cta:disabled,
    .package-sidebar-cta.disabled {
        background: var(--theme-primary, #0f766e) !important;
        border-color: var(--theme-primary, #0f766e) !important;
        color: var(--theme-button-text, #ffffff) !important;
        -webkit-text-fill-color: var(--theme-button-text, #ffffff);
    }

    .btn-teal {
        background: var(--theme-primary, #0f766e) !important;
        border: 1px solid var(--theme-primary, #0f766e) !important;
        color: var(--theme-button-text, #ffffff) !important;
    }

    .btn-pdf {
        background: var(--theme-secondary, #f59e0b) !important;
        border: 1px solid var(--theme-secondary, #f59e0b) !important;
        color: var(--theme-button-text, #ffffff) !important;
    }

    .btn-teal:hover {
        filter: brightness(0.96);
        color: var(--theme-button-text, #ffffff) !important;
    }

    .btn-pdf:hover,
    .btn-pdf:focus,
    .btn-pdf:active,
    .btn-pdf:disabled,
    .btn-pdf.disabled {
        background: color-mix(in srgb, var(--theme-secondary, #f59e0b) 88%, #000000) !important;
        border-color: color-mix(in srgb, var(--theme-secondary, #f59e0b) 88%, #000000) !important;
        color: var(--theme-button-text, #ffffff) !important;
        -webkit-text-fill-color: var(--theme-button-text, #ffffff) !important;
    }

    .course-detail-shell {
        display: grid;
        grid-template-columns: minmax(0, 1fr) 360px;
        gap: 24px;
        align-items: start;
    }

    .course-panel {
        background: var(--theme-card-bg, #ffffff);
        border: 1px solid color-mix(in srgb, var(--theme-primary, #0f766e) 12%, #e2e8f0);
        border-radius: 12px;
        box-shadow: 0 12px 28px rgba(15, 23, 42, 0.06);
        overflow: hidden;
    }

    .course-panel-head {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 16px;
        padding: 20px;
        background: color-mix(in srgb, var(--theme-primary, #0f766e) 7%, #ffffff);
        border-bottom: 1px solid color-mix(in srgb, var(--theme-primary, #0f766e) 12%, #e2e8f0);
    }

    .course-panel-title {
        margin: 0;
        color: var(--theme-heading, #0f172a);
        font-size: 1.08rem;
        font-weight: 800;
        line-height: 1.35;
    }

    .course-panel-subtitle {
        color: var(--theme-text, #64748b);
        font-size: 13px;
        margin-top: 4px;
    }

    .course-flashcard-list {
        display: grid;
        gap: 10px;
        margin: 16px 0;
    }

    .course-flashcard-row {
        display: grid;
        grid-template-columns: minmax(0, 1fr) auto;
        gap: 10px;
        align-items: center;
        padding: 12px;
        border: 1px solid color-mix(in srgb, var(--theme-primary, #0f766e) 14%, #e2e8f0);
        border-radius: 10px;
        background: color-mix(in srgb, var(--theme-primary, #0f766e) 4%, #ffffff);
    }

    .course-flashcard-row strong {
        display: block;
        color: var(--theme-heading, #0f172a);
        font-size: 0.93rem;
        line-height: 1.35;
    }

    .course-flashcard-count {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 6px 10px;
        border-radius: 999px;
        background: var(--theme-primary-soft, #e6f3f1);
        color: var(--theme-primary, #0f766e);
        font-size: 12px;
        font-weight: 800;
        white-space: nowrap;
    }


    .course-total-pill {
        flex: 0 0 auto;
        display: inline-flex;
        align-items: center;
        gap: 7px;
        border: 1px solid color-mix(in srgb, var(--theme-secondary, #f59e0b) 42%, #ffffff);
        border-radius: 8px;
        background: color-mix(in srgb, var(--theme-secondary, #f59e0b) 9%, #ffffff);
        color: color-mix(in srgb, var(--theme-secondary, #f59e0b) 72%, #111827);
        padding: 9px 13px;
        font-size: 13px;
        font-weight: 800;
        white-space: nowrap;
    }

    .course-exam-list {
        margin: 0;
        padding: 0;
        list-style: none;
    }

    .course-exam-load-more {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 10px;
        padding: 20px 16px 22px;
        border-top: 1px solid color-mix(in srgb, var(--theme-primary, #0f766e) 10%, #e2e8f0);
        background: color-mix(in srgb, var(--theme-primary-soft, #e6f3f1) 35%, #ffffff);
    }

    .course-exam-load-more p {
        margin: 0;
        color: var(--theme-text, #64748b);
        font-size: 13px;
        font-weight: 700;
    }

    .course-load-more-button {
        min-width: 210px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        border: 1px solid var(--theme-secondary, #f59e0b);
        border-radius: 10px;
        padding: 11px 18px;
        background: var(--theme-secondary, #f59e0b);
        color: #ffffff;
        font-size: 14px;
        font-weight: 800;
        box-shadow: 0 8px 18px color-mix(in srgb, var(--theme-secondary, #f59e0b) 22%, transparent);
        transition: transform .18s ease, box-shadow .18s ease, opacity .18s ease;
    }

    .course-load-more-button:hover,
    .course-load-more-button:focus {
        background: color-mix(in srgb, var(--theme-secondary, #f59e0b) 88%, #b45309);
        border-color: color-mix(in srgb, var(--theme-secondary, #f59e0b) 88%, #b45309);
        color: #ffffff;
        transform: translateY(-1px);
        box-shadow: 0 10px 22px color-mix(in srgb, var(--theme-secondary, #f59e0b) 30%, transparent);
    }

    .course-load-more-button:disabled {
        cursor: wait;
        opacity: .72;
        transform: none;
    }
    .course-exam-row {
        display: grid;
        grid-template-columns: 42px minmax(0, 1fr) auto;
        gap: 14px;
        align-items: center;
        padding: 16px 18px;
        border-bottom: 1px solid color-mix(in srgb, var(--theme-primary, #0f766e) 10%, #e2e8f0);
    }

    .course-exam-row:nth-child(even) {
        background: color-mix(in srgb, var(--theme-primary, #0f766e) 3%, #ffffff);
    }

    .course-exam-row:last-child {
        border-bottom: 0;
    }

    .course-exam-number {
        min-width: 22px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: transparent;
        border: 0;
        color: var(--theme-primary, #0f766e);
        font-weight: 800;
        font-size: 14px;
        box-shadow: none;
    }

    .course-exam-title {
        margin: 0;
        color: var(--theme-heading, #0f172a);
        font-size: clamp(0.92rem, 2vw, 1rem);
        font-weight: 750;
        line-height: 1.35;
        overflow-wrap: anywhere;
    }

    .course-exam-meta {
        display: flex;
        flex-wrap: wrap;
        gap: 7px;
        margin-top: 10px;
    }

    .course-exam-meta span {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 5px 10px;
        border: 1px solid color-mix(in srgb, var(--theme-primary, #0f766e) 14%, #e2e8f0);
        border-radius: 999px;
        background: color-mix(in srgb, var(--theme-primary, #0f766e) 5%, #ffffff);
        color: var(--theme-heading, #0f172a);
        font-size: 11.5px;
        font-weight: 500;
    }

    .course-exam-meta i {
        color: var(--theme-primary, #0f766e);
    }


    .course-side-stack {
        display: grid;
        gap: 18px;
    }

    .course-summary-list {
        display: grid;
        gap: 10px;
        margin: 18px 0;
    }

    .course-summary-item {
        display: flex;
        align-items: center;
        gap: 10px;
        color: var(--theme-text, #64748b);
        font-size: 13px;
    }

    .course-summary-item i {
        width: 34px;
        height: 34px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 8px;
        background: color-mix(in srgb, var(--theme-primary, #0f766e) 10%, #ffffff);
        color: var(--theme-primary, #0f766e);
    }

    .course-leaderboard-row {
        display: grid;
        grid-template-columns: 34px 1fr auto;
        gap: 10px;
        align-items: center;
        padding: 10px 0;
        border-bottom: 1px solid color-mix(in srgb, var(--theme-primary, #0f766e) 10%, #e2e8f0);
    }

    .course-leaderboard-row:last-child {
        border-bottom: 0;
    }

    .course-leaderboard-rank {
        width: 30px;
        height: 30px;
        border-radius: 50%;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: var(--theme-primary, #0f766e);
        color: var(--theme-button-text, #ffffff);
        font-size: 12px;
        font-weight: 800;
    }

    .course-leader-name {
        color: var(--theme-heading, #0f172a);
        font-weight: 800;
        font-size: 13px;
        line-height: 1.2;
    }

    .course-leader-score {
        color: var(--theme-primary, #0f766e);
        font-weight: 900;
        font-size: 13px;
    }

    .course-empty-state {
        padding: 34px 20px;
        text-align: center;
        color: var(--theme-text, #64748b);
    }

    .course-empty-state i {
        width: 48px;
        height: 48px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 14px;
        background: color-mix(in srgb, var(--theme-primary, #0f766e) 9%, #ffffff);
        color: var(--theme-primary, #0f766e);
        font-size: 22px;
    }

    @media (max-width: 991px) {
        .course-detail-shell {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 640px) {
        .course-panel-head {
            flex-direction: column;
            padding: 16px;
        }
        .course-exam-row {
            grid-template-columns: 38px minmax(0, 1fr);
            padding: 16px;
            gap: 12px;
        }
        .course-exam-number {
            font-size: 13px;
        }

    }

    .package-hero {
        background: color-mix(in srgb, var(--theme-primary, #0f766e) 6%, var(--theme-body-bg, #ffffff));
        border-bottom: 1px solid color-mix(in srgb, var(--theme-primary, #0f766e) 14%, #e2e8f0);
        color: var(--theme-heading, #0f172a);
        padding: 32px 0;
    }

    .package-hero-grid {
        display: grid;
        grid-template-columns: minmax(0, 1fr) auto;
        gap: 22px;
        align-items: center;
    }

    .package-hero-title {
        color: var(--theme-heading, #0f172a);
        font-size: clamp(1.35rem, 4vw, 2.15rem);
        font-weight: 900;
        line-height: 1.18;
        margin: 0 0 14px;
        overflow-wrap: anywhere;
    }

    .package-hero-meta {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
    }

    .package-hero-meta span,
    .package-hero-meta a {
        align-items: center;
        background: var(--theme-card-bg, #ffffff);
        border: 1px solid color-mix(in srgb, var(--theme-primary, #0f766e) 16%, #e2e8f0);
        border-radius: 999px;
        color: var(--theme-primary, #0f766e);
        display: inline-flex;
        font-size: 13px;
        font-weight: 800;
        gap: 7px;
        padding: 8px 12px;
        text-decoration: none;
    }

    .package-hero-meta a:hover {
        background: var(--theme-primary-soft, #e6f3f1);
        color: var(--theme-primary, #0f766e);
    }

    .package-sidebar-cta {
        background: var(--theme-primary, #0f766e);
        box-shadow: none;
    }

    .package-hero-cta {
        align-items: center;
        background: var(--theme-secondary, #f59e0b);
        border: 0;
        border-radius: 999px;
        color: var(--theme-button-text, #ffffff);
        display: inline-flex;
        font-weight: 850;
        gap: 8px;
        justify-content: center;
        min-height: 46px;
        min-width: 220px;
        padding: 11px 18px;
        text-decoration: none;
        white-space: nowrap;
    }

    .package-hero-cta:hover,
    .package-hero-cta:focus,
    .package-hero-cta:disabled {
        color: var(--theme-button-text, #ffffff);
        background: color-mix(in srgb, var(--theme-secondary, #f59e0b) 88%, #000000);
        text-decoration: none;
    }

    .course-desc-toggle {
        background: transparent;
        border: 0;
        color: var(--theme-primary, #0f766e);
        cursor: pointer;
        font-size: 12px;
        font-weight: 800;
        margin-top: 8px;
        padding: 0;
    }

    .course-about-content {
        color: var(--theme-text, #64748b);
        font-size: 13px;
        line-height: 1.65;
    }

    .course-about-content h1,
    .course-about-content h2,
    .course-about-content h3,
    .course-about-content h4,
    .course-about-content h5,
    .course-about-content h6 {
        color: var(--theme-heading, #0f172a);
        font-size: 1rem;
        font-weight: 850;
        line-height: 1.35;
        margin: 12px 0 8px;
    }

    .course-about-content p,
    .course-about-content ul,
    .course-about-content ol {
        margin-bottom: 10px;
    }

    .course-about-content * {
        max-width: 100%;
        overflow-wrap: anywhere;
    }

    .course-leaderboard-title {
        color: var(--theme-heading, #0f172a);
        font-size: 1rem;
        font-weight: 850;
        line-height: 1.3;
        margin-bottom: 8px;
        overflow-wrap: anywhere;
        white-space: normal;
    }

    .course-panel-title {
        font-size: 1.18rem;
        font-weight: 900;
    }

    .course-exam-row {
        grid-template-columns: 42px minmax(0, 1fr) minmax(220px, auto);
    }

    .course-exam-title {
        font-size: .95rem;
        font-weight: 750;
    }

    .course-exam-meta span {
        font-weight: 500;
    }

    .breadcrumb-custom .breadcrumb-item + .breadcrumb-item::before {
        color: color-mix(in srgb, var(--theme-primary, #0f766e) 60%, transparent);
        content: ">";
    }

    .course-empty-state {
        padding: 34px 18px;
    }

    .course-empty-state i {
        font-size: 22px;
    }

    .course-section-heading {
        color: var(--theme-heading, #0f172a);
        font-size: 1.2rem;
        font-weight: 900;
        margin-bottom: 8px;
    }

    .course-section-copy {
        color: var(--theme-text, #64748b);
        font-size: 13px;
        margin: 0;
    }

    .similar-courses-heading-row {
        align-items: center;
        display: flex;
        gap: 18px;
        justify-content: space-between;
        margin-bottom: 24px;
    }

    .similar-courses-heading-row .text-center {
        flex: 1;
        margin-bottom: 0 !important;
    }

    .similar-courses-controls {
        display: flex;
        gap: 8px;
    }

    .similar-courses-control {
        align-items: center;
        background: var(--theme-body-bg, #fff);
        border: 1px solid color-mix(in srgb, var(--theme-primary, #0f766e) 28%, transparent);
        border-radius: 999px;
        color: var(--theme-primary, #0f766e);
        display: inline-flex;
        font-size: 20px;
        height: 42px;
        justify-content: center;
        transition: background .2s ease, color .2s ease, opacity .2s ease;
        width: 42px;
    }

    .similar-courses-control:hover,
    .similar-courses-control:focus {
        background: var(--theme-primary, #0f766e);
        color: var(--theme-button-text, #fff);
    }

    .similar-courses-control:disabled {
        cursor: default;
        opacity: .35;
    }

    .similar-courses-viewport {
        overflow: hidden;
    }

    .similar-courses-track {
        display: flex;
        gap: 24px;
        overflow-x: auto;
        overscroll-behavior-inline: contain;
        padding: 2px 1px 18px;
        scroll-behavior: smooth;
        scroll-snap-type: x mandatory;
        scrollbar-width: none;
    }

    .similar-courses-track::-webkit-scrollbar {
        display: none;
    }

    .similar-course-slide {
        flex: 0 0 calc((100% - 48px) / 3);
        min-width: 0;
        scroll-snap-align: start;
    }

    .similar-course-slide > * {
        height: 100%;
    }

    .course-leaderboard-row:nth-of-type(1) .course-leaderboard-rank {
        background: var(--theme-primary, #0f766e);
    }

    .course-leaderboard-row:nth-of-type(2) .course-leaderboard-rank {
        background: var(--theme-secondary, #f59e0b);
    }

    .course-leaderboard-row:nth-of-type(3) .course-leaderboard-rank {
        background: var(--theme-tertiary, #38bdf8);
    }

    @media (max-width: 991px) {
        .package-hero-grid {
            grid-template-columns: 1fr;
        }

        .package-hero-cta {
            justify-self: start;
        }

        .similar-course-slide {
            flex-basis: calc((100% - 24px) / 2);
        }
    }

    @media (max-width: 640px) {
        .package-hero {
            padding: 24px 0;
        }

        .similar-courses-heading-row {
            align-items: flex-end;
        }

        .similar-courses-heading-row .text-center {
            text-align: left !important;
        }

        .similar-course-slide {
            flex-basis: 88%;
        }

        .course-exam-row {
            grid-template-columns: 34px minmax(0, 1fr);
        }

        .course-exam-title {
            font-size: 0.9rem;
        }


        .package-hero-cta {
            width: 100%;
        }
    }

    .exam-type-tabs { display:flex; gap:10px; overflow-x:auto; padding:16px 20px; border-bottom:1px solid color-mix(in srgb,var(--theme-primary,#0f766e) 12%,#e2e8f0); }
    .exam-type-tab { align-items:center; background:color-mix(in srgb,var(--theme-primary,#0f766e) 5%,#fff); border:1px solid color-mix(in srgb,var(--theme-primary,#0f766e) 18%,#e2e8f0); border-radius:999px; color:var(--theme-heading,#0f172a); cursor:pointer; display:inline-flex; flex:0 0 auto; font-size:12px; font-weight:800; gap:7px; padding:9px 14px; }
    .exam-type-tab span { background:rgba(15,23,42,.08); border-radius:999px; font-size:10px; min-width:23px; padding:2px 6px; text-align:center; }
    .exam-type-tab.is-active { background:var(--theme-primary,#0f766e); border-color:var(--theme-primary,#0f766e); color:#fff; }
    .exam-type-tab.is-active span { background:rgba(255,255,255,.2); }
    .exam-type-panel[hidden] { display:none!important; }
    .exam-topic-heading { align-items:center; color:var(--theme-text,#475569); display:flex; font-size:12px; font-weight:800; justify-content:space-between; padding:12px 20px; }
    .exam-hierarchy-nav { background:linear-gradient(180deg,color-mix(in srgb,var(--theme-primary,#0f766e) 5%,#fff),#fff); border-bottom:1px solid color-mix(in srgb,var(--theme-primary,#0f766e) 14%,#e2e8f0); padding:16px 20px; }
    .exam-filter-selection { align-items:center; background:#fff; border:1px solid color-mix(in srgb,var(--theme-primary,#0f766e) 18%,#e2e8f0); border-radius:12px; display:flex; flex-wrap:wrap; gap:8px; justify-content:space-between; margin-bottom:14px; padding:11px 13px; }
    .exam-filter-selection__path { align-items:center; display:flex; flex-wrap:wrap; gap:7px; min-width:0; }
    .exam-filter-selection__path > span { color:var(--theme-text,#64748b); font-size:11px; font-weight:750; text-transform:uppercase; }
    .exam-filter-selection__path strong { color:var(--theme-heading,#0f172a); font-size:13px; overflow-wrap:anywhere; }
    .exam-filter-selection__count { background:color-mix(in srgb,var(--theme-primary,#0f766e) 10%,#fff); border-radius:999px; color:var(--theme-primary,#0f766e); flex:0 0 auto; font-size:11px; font-weight:850; padding:5px 9px; }
    .exam-filter-row { align-items:flex-start; display:grid; gap:10px; grid-template-columns:78px minmax(0,1fr); margin-top:12px; }
    .exam-filter-row[hidden] { display:none!important; }
    .exam-filter-label { color:var(--theme-heading,#0f172a); font-size:11px; font-weight:850; padding-top:8px; text-transform:uppercase; }
    .exam-filter-options { display:flex; flex-wrap:wrap; gap:8px; }
    .exam-filter-chip { align-items:center; background:#fff; border:1px solid color-mix(in srgb,var(--theme-primary,#0f766e) 18%,#dbe5e8); border-radius:999px; color:var(--theme-text,#475569); cursor:pointer; display:inline-flex; font-size:11px; font-weight:800; gap:6px; min-height:34px; padding:7px 11px; }
    .exam-filter-chip:hover { border-color:var(--theme-primary,#0f766e); color:var(--theme-primary,#0f766e); }
    .exam-filter-chip.is-active { background:var(--theme-primary,#0f766e); border-color:var(--theme-primary,#0f766e); color:#fff; }
    .exam-filter-chip span { background:rgba(15,23,42,.07); border-radius:999px; font-size:9px; min-width:20px; padding:2px 5px; text-align:center; }
    .exam-filter-chip.is-active span { background:rgba(255,255,255,.2); }
    .exam-filter-empty { color:var(--theme-text,#64748b); padding:28px 20px; text-align:center; }
    @media (max-width:575.98px) {
        .exam-hierarchy-nav { padding:14px 12px; }
        .exam-filter-row { grid-template-columns:1fr; gap:6px; }
        .exam-filter-label { padding-top:0; }
        .exam-filter-options { flex-wrap:nowrap; margin-right:-12px; overflow-x:auto; padding-bottom:5px; padding-right:12px; }
        .exam-filter-chip { flex:0 0 auto; }
    }
</style>

{{-- HEADER SECTION --}}
@php
    $allowGuestExamAttempts = getConfiguration()->allow_guest_exam_attempts ?? true;
    $freePackageActionUrl = $allowGuestExamAttempts ? null : route('student.signin', [
        'action' => 'activate_free_package',
        'package_id' => $package->id,
        'group_id' => $groupId,
    ]);
    $isPaidPackage = $package->package_type == 'paid';
    $hasHeaderDiscount = $isPaidPackage && !empty($package->discounted_amount) && $package->discounted_amount > 0 && $package->discounted_amount < $package->amount;
    $headerPrice = $hasHeaderDiscount ? $package->discounted_amount : ($package->amount ?? 0);
    $enrollmentDisplay = ($enrollmentCount ?? 0) > 0 ? ($enrollmentCount ?? 0) . '+ students' : null;
@endphp
<section class="package-hero">
    <div class="container">
        <div class="package-hero-grid">
            <div>
                <nav class="breadcrumb-custom">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="/">{{ __('website.home') }}</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('courses.index') }}">{{ __('ui.exam_packages') }}</a></li>
                        <li class="breadcrumb-item active" aria-current="page">{{ Str::limit($packageDisplayName, 35) }}</li>
                    </ol>
                </nav>

                <h1 class="package-hero-title">{{ $packageDisplayName }}</h1>

                <div class="package-hero-meta">
                    <span><i class="ri-file-list-3-line"></i> {{ __('ui.exams_count', ['count' => $packageExamCount ?? $package->exams->count()]) }}</span>
                    @if(($flashcardCardCount ?? 0) > 0)
                        @php
                            $heroStudyCardUrl = auth('student')->check()
                                ? route('student.flashcards.show', $package)
                                : (($package->guest_flashcards_enabled ?? false) ? route('website.flashcards.show', $package->slug ?: $package->id) : route('student.signin'));
                        @endphp
                        <a href="{{ $heroStudyCardUrl }}"><i class="ri-stack-line"></i> {{ $flashcardCardCount }} study cards</a>
                    @endif
                    @if($enrollmentDisplay)
                        <span><i class="ri-user-line"></i> {{ $enrollmentDisplay }}</span>
                    @endif
                    <span><i class="{{ $isPaidPackage ? 'ri-shopping-cart-line' : 'ri-gift-line' }}"></i> {{ $isPaidPackage ? 'Paid' : 'Free access' }}</span>
                    @if($isPaidPackage)
                        <span>
                            <i class="ri-price-tag-3-line"></i>
                            @if($hasHeaderDiscount)
                                {{ $configuration_detail->currency }}{{ number_format($headerPrice, 2) }}
                            @else
                                {{ $configuration_detail->currency }}{{ number_format($package->amount ?? 0, 2) }}
                            @endif
                        </span>
                    @endif
                </div>
            </div>
            <div>
                @if(!$isPaidPackage && $freePackageActionUrl)
                    <a href="{{ $freePackageActionUrl }}" class="package-hero-cta">
                        Login to Start <i class="ri-login-circle-line"></i>
                    </a>
                @else
                    <button class="package-hero-cta addToCartBtn" data-groupid="{{ $groupId }}" data-id="{{ $package->id }}">
                        {{ $isPaidPackage ? 'Enroll Now' : 'Take All Exams Free' }}
                        <i class="{{ $isPaidPackage ? 'ri-shopping-cart-line' : 'ri-play-circle-line' }}"></i>
                    </button>
                @endif
            </div>
        </div>
    </div>
</section>

{{-- MAIN CONTENT --}}
<section class="main-section">
    <div class="container">
        <div class="course-detail-shell">
            <div class="course-panel">
                <div class="course-panel-head">
                    <div>
                        <h2 class="course-panel-title">{{ $packageDisplayName }}</h2>
                        <div class="course-panel-subtitle">{{ __('ui.choose_exam_start') }}</div>
                    </div>
                    <div class="course-total-pill">
                        <i class="ri-file-list-3-line"></i>
                        {{ $packageExamCount ?? $package->exams->count() }} Exams
                    </div>
                </div>

                @if($pypHubAvailable ?? false)
                    <a href="{{ route('pyp.index', $package->slug) }}" class="text-decoration-none d-block mb-4">
                        <div class="p-3 p-md-4 rounded-3 d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3"
                            style="border:1px solid color-mix(in srgb,var(--theme-primary,#0f766e) 28%,var(--theme-border,#dbe3ea));background:color-mix(in srgb,var(--theme-primary,#0f766e) 7%,var(--theme-card-bg,#fff));">
                            <div class="d-flex align-items-start gap-3">
                                <span class="d-inline-flex align-items-center justify-content-center rounded-3 flex-shrink-0"
                                    style="width:48px;height:48px;background:var(--theme-primary,#0f766e);color:var(--theme-button-text,#fff);font-size:1.35rem;">
                                    <i class="ri-history-line"></i>
                                </span>
                                <div>
                                    <h3 class="h5 mb-1" style="color:var(--theme-heading,#0f172a);">Previous Year Question Hub</h3>
                                    <p class="mb-0" style="color:var(--theme-text,#334155);">Browse real papers by year, subject, topic and subtopic, then explore question trends.</p>
                                </div>
                            </div>
                            <span class="fw-bold" style="color:var(--theme-primary,#0f766e);white-space:nowrap;">Explore PYP <i class="ri-arrow-right-line"></i></span>
                        </div>
                    </a>
                @endif

                @if(($package->exams ?? collect())->isNotEmpty())
                    @php
                        $visibleTypes = collect($examTypeLabels)->filter(fn ($label, $type) => ($packageExamsByType->get($type, collect()))->isNotEmpty());
                        $examNumberById = $package->exams->values()->mapWithKeys(fn ($exam, $index) => [$exam->id => $index + 1]);
                    @endphp
                    <div class="exam-type-tabs" role="tablist" aria-label="Test types">
                        <button type="button" class="exam-type-tab is-active" data-exam-type-tab="all" role="tab" aria-selected="true">{{ __('ui.all_tests') }} <span>{{ $packageExamCount }}</span></button>
                        @foreach($visibleTypes as $type => $label)
                            <button type="button" class="exam-type-tab" data-exam-type-tab="{{ $type }}" role="tab" aria-selected="false">{{ $label }} <span>{{ $packageExamsByType->get($type)->count() }}</span></button>
                        @endforeach
                    </div>
                    <div id="courseExamGroups">
                        @foreach($visibleTypes as $type => $label)
                            <div class="exam-type-panel" data-exam-type-panel="{{ $type }}">
                                <div class="exam-topic-heading"><span>{{ $label }}</span><small>{{ $packageExamsByType->get($type)->count() }} {{ Str::plural('test', $packageExamsByType->get($type)->count()) }}</small></div>
                                @include('website.partials.course-exam-type-section', ['type' => $type, 'exams' => $packageExamsByType->get($type), 'examNumberById' => $examNumberById])
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="course-empty-state">
                        <i class="ri-file-copy-line"></i>
                        <p class="mt-3 mb-1 fw-bold" style="color: var(--theme-heading, #0f172a);">{{ __('ui.no_active_exams') }}</p>
                        <p class="text-muted mb-0">{{ __('ui.check_another_course') }}</p>
                    </div>
                @endif
            </div>

            <aside class="course-side-stack">
                @if(($package->exams ?? collect())->isNotEmpty())
                    <div class="course-panel p-4" data-leaderboard-panel="package">
                        <h5 class="course-leaderboard-title">{{ $packageDisplayName }} Leaderboard</h5>
                    @include('components.leaderboard-period-toggle', ['leaderboardPanelKey' => 'package'])
                        <p class="text-muted mb-3" style="font-size: 12px;">{{ __('ui.best_scores_package') }}</p>
                        @if($packageLeaderboard->isNotEmpty())
                        @foreach($packageLeaderboard as $index => $leader)
                            <div class="course-leaderboard-row">
                                <span class="course-leaderboard-rank">{{ $index + 1 }}</span>
                                <div>
                                    <div class="course-leader-name">{{ $leader->name }}</div>
                                    <div class="text-muted" style="font-size: 11px;">{{ __('ui.best_score') }}</div>
                                </div>
                                <div class="course-leader-score">{{ number_format((float) $leader->best_percent, 1) }}%</div>
                            </div>
                        @endforeach
                        @else
                            <p class="text-muted mb-0">{{ __('ui.no_results_period') }}</p>
                        @endif
                    </div>
                @endif

                <div class="course-panel p-4">
                    <div class="feature-icon mb-3">
                        <i class="ri-graduation-cap-line"></i>
                    </div>
                    <h4 class="package-sidebar-title fw-bold mb-2">{{ $packageDisplayName }}</h4>
                    <div class="course-summary-list">
                        <div class="course-summary-item"><i class="ri-file-list-line"></i> {{ __('ui.exams_count', ['count' => $packageExamCount ?? $package->exams->count()]) }} included</div>
                        @if(($flashcardCardCount ?? 0) > 0)
                            @php
                                $sideStudyCardUrl = auth('student')->check()
                                    ? route('student.flashcards.show', $package)
                                    : (($package->guest_flashcards_enabled ?? false) ? route('website.flashcards.show', $package->slug ?: $package->id) : route('student.signin'));
                            @endphp
                            <a href="{{ $sideStudyCardUrl }}" class="course-summary-item text-decoration-none">
                                <i class="ri-stack-line"></i> {{ $flashcardCardCount }} study cards included
                            </a>
                        @endif
                        <div class="course-summary-item"><i class="ri-calendar-check-line"></i> {{ __('ui.days_validity_count', ['count' => $package->expiry_days ?? 365]) }}</div>
                        <div class="course-summary-item"><i class="ri-user-line"></i> {{ $enrollmentCount ?? 0 }} enrollments</div>
                    </div>

                    <div class="mb-3">
                        @if($package->package_type == 'paid')
                            @php
                                $hasSidebarDiscount = !empty($package->discounted_amount) && $package->discounted_amount > 0 && $package->discounted_amount < $package->amount;
                                $sidebarPrice = $hasSidebarDiscount ? $package->discounted_amount : $package->amount;
                            @endphp
                            @if($hasSidebarDiscount)
                                <span class="text-muted text-decoration-line-through d-block">{{ $configuration_detail->currency }}{{ number_format($package->amount, 2) }}</span>
                            @endif
                            <span class="fw-bold" style="font-size: 1.35rem; color: var(--theme-primary);">{{ $configuration_detail->currency }}{{ number_format($sidebarPrice, 2) }}</span>
                        @else
                            <span class="badge-teal">
                                <i class="ri-book-open-line me-1"></i> Completely Free
                            </span>
                        @endif
                    </div>

                    @if($package->package_type == 'free' && !$allowGuestExamAttempts)
                        <a href="{{ $freePackageActionUrl }}" class="btn package-sidebar-cta">{{ __('ui.take_all_exams_free') }}</a>
                    @else
                        <button class="btn package-sidebar-cta addToCartBtn" data-groupid="{{ $groupId }}" data-id="{{ $package->id }}">
                            {{ $package->package_type == 'paid' ? 'Take All Exams' : 'Take All Exams Free' }}
                        </button>
                    @endif
                </div>

                @if(($flashcardSets ?? collect())->isNotEmpty())
                    <div class="course-panel p-4">
                        <div class="feature-icon mb-3">
                            <i class="ri-stack-line"></i>
                        </div>
                        <h4 class="package-sidebar-title fw-bold mb-2">{{ __('ui.study_cards') }}</h4>
                        <p class="text-muted mb-0" style="font-size: 13px;">{{ __('ui.study_cards_copy') }}</p>
                        <div class="course-flashcard-list">
                            @foreach($flashcardSets->take(4) as $flashcardSet)
                                <div class="course-flashcard-row">
                                    <strong>{{ $flashcardSet->title }}</strong>
                                    <span class="course-flashcard-count"><i class="ri-cards-line"></i> {{ __('ui.study_cards_count', ['count' => $flashcardSet->cards_count]) }}</span>
                                </div>
                            @endforeach
                        </div>
                        @php
                            $flashcardStudyUrl = auth('student')->check()
                                ? route('student.flashcards.show', $package)
                                : (($package->guest_flashcards_enabled ?? false) ? route('website.flashcards.show', $package->slug ?: $package->id) : route('student.signin'));
                        @endphp
                        <a href="{{ $flashcardStudyUrl }}" class="btn package-sidebar-cta">
                            {{ __('ui.study_cards') }}
                        </a>
                    </div>
                @endif

                @if($packageDescriptionText)
                    <div class="course-panel p-4">
                        <h5 class="fw-bold mb-3" style="color: var(--theme-heading, #0f172a); font-size: 1rem;">{{ __('ui.about_course') }}</h5>
                        <div id="shortDesc" class="course-about-content">
                            {!! Str::limit(strip_tags($packageDescriptionText), 170) !!}
                        </div>
                        @if(strlen(strip_tags($packageDescriptionText)) > 170)
                            <div id="fullDesc" style="display: none;" class="course-about-content mt-2">
                                {!! $packageDescriptionText !!}
                            </div>
                            <button type="button" id="courseDescToggle" class="course-desc-toggle" onclick="toggleDescription()">
                                Read more <i class="ri-arrow-down-s-line"></i>
                            </button>
                        @endif
                    </div>
                @endif
            </aside>
        </div>
    </div>
</section>

{{-- SIMILAR COURSES --}}
@if($similarPackages->isNotEmpty())
<section class="py-5" style="background: var(--theme-body-bg, #ffffff);">
    <div class="container">
        <div class="similar-courses-heading-row">
            <div class="text-center">
                <h2 class="course-section-heading">{{ __('ui.similar_courses') }}</h2>
                <p class="course-section-copy">{{ __('ui.similar_courses_copy') }}</p>
            </div>
            @if($similarPackages->count() > 3)
                <div class="similar-courses-controls" aria-label="Similar course navigation">
                    <button type="button" class="similar-courses-control" id="similarCoursesPrevious" aria-label="Previous courses">
                        <i class="ri-arrow-left-s-line"></i>
                    </button>
                    <button type="button" class="similar-courses-control" id="similarCoursesNext" aria-label="Next courses">
                        <i class="ri-arrow-right-s-line"></i>
                    </button>
                </div>
            @endif
        </div>
        <div class="similar-courses-viewport">
            <div class="similar-courses-track" id="similarCoursesTrack">
                @foreach($similarPackages as $similarPackage)
                    <div class="similar-course-slide">
                        @include('website.course_card', ['package' => $similarPackage, 'configuration_detail' => $configuration_detail])
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</section>
@endif


@endsection

@push('scripts')
<script>
    const similarCoursesTrack = document.getElementById('similarCoursesTrack');
    const similarCoursesPrevious = document.getElementById('similarCoursesPrevious');
    const similarCoursesNext = document.getElementById('similarCoursesNext');

    if (similarCoursesTrack && similarCoursesPrevious && similarCoursesNext) {
        const updateSimilarCourseControls = function () {
            const maximumScroll = similarCoursesTrack.scrollWidth - similarCoursesTrack.clientWidth;
            similarCoursesPrevious.disabled = similarCoursesTrack.scrollLeft <= 2;
            similarCoursesNext.disabled = similarCoursesTrack.scrollLeft >= maximumScroll - 2;
        };

        const scrollSimilarCourses = function (direction) {
            const firstSlide = similarCoursesTrack.querySelector('.similar-course-slide');
            const trackStyles = window.getComputedStyle(similarCoursesTrack);
            const gap = parseFloat(trackStyles.columnGap || trackStyles.gap || 0);
            const distance = firstSlide ? firstSlide.getBoundingClientRect().width + gap : similarCoursesTrack.clientWidth;
            similarCoursesTrack.scrollBy({ left: direction * distance, behavior: 'smooth' });
        };

        similarCoursesPrevious.addEventListener('click', function () { scrollSimilarCourses(-1); });
        similarCoursesNext.addEventListener('click', function () { scrollSimilarCourses(1); });
        similarCoursesTrack.addEventListener('scroll', updateSimilarCourseControls, { passive: true });
        window.addEventListener('resize', updateSimilarCourseControls);
        updateSimilarCourseControls();
    }

    window.toggleDescription = function () {
        const shortDesc = document.getElementById('shortDesc');
        const fullDesc = document.getElementById('fullDesc');
        const toggleButton = document.getElementById('courseDescToggle');

        if (!shortDesc || !fullDesc) {
            return;
        }

        const isExpanded = fullDesc.style.display !== 'none';
        fullDesc.style.display = isExpanded ? 'none' : 'block';
        shortDesc.style.display = isExpanded ? 'block' : 'none';

        if (toggleButton) {
            toggleButton.innerHTML = isExpanded
                ? 'Read more <i class="ri-arrow-down-s-line"></i>'
                : 'Show less <i class="ri-arrow-up-s-line"></i>';
        }
    };

    document.querySelectorAll('[data-exam-hierarchy]').forEach(function (navigator) {
        const section = navigator.closest('[data-exam-type-section]');
        const rows = Array.from(section.querySelectorAll('[data-exam-filter-list] > .course-exam-row'));
        const buttons = Array.from(navigator.querySelectorAll('[data-filter-level]'));
        const topicRow = navigator.querySelector('[data-filter-row="topic"]');
        const subtopicRow = navigator.querySelector('[data-filter-row="subtopic"]');
        const selectedPath = navigator.querySelector('[data-selected-path]');
        const visibleCount = navigator.querySelector('[data-visible-count]');
        const emptyState = section.querySelector('[data-exam-filter-empty]');
        const level = navigator.dataset.hierarchyLevel;
        const typeLabel = navigator.dataset.typeLabel;
        const state = { subject: '', topic: '', subtopic: '' };
        const labels = { subject: '', topic: '', subtopic: '' };

        function updateHierarchy() {
            buttons.forEach(function (button) {
                const filterLevel = button.dataset.filterLevel;
                const value = button.dataset.filterValue || '';
                const parentSubject = button.dataset.parentSubject || '';
                const parentTopic = button.dataset.parentTopic || '';
                let available = true;

                if (filterLevel === 'topic' && value !== '') available = state.subject !== '' && parentSubject === state.subject;
                if (filterLevel === 'subtopic' && value !== '') available = state.topic !== '' && parentSubject === state.subject && parentTopic === state.topic;
                button.hidden = !available;
                button.classList.toggle('is-active', available && state[filterLevel] === value);
                button.setAttribute('aria-pressed', available && state[filterLevel] === value ? 'true' : 'false');
            });

            if (topicRow) topicRow.hidden = state.subject === '';
            if (subtopicRow) subtopicRow.hidden = state.topic === '';

            let matches = 0;
            rows.forEach(function (row) {
                const visible = (!state.subject || row.dataset.examSubjectId === state.subject)
                    && (!state.topic || row.dataset.examTopicId === state.topic)
                    && (!state.subtopic || row.dataset.examSubtopicId === state.subtopic);
                row.hidden = !visible;
                if (visible) matches++;
            });

            const path = ['subject', 'topic', 'subtopic'].map(function (key) {
                return state[key] ? labels[key] : '';
            }).filter(Boolean);
            selectedPath.textContent = path.length ? path.join(' › ') : typeLabel;
            visibleCount.textContent = matches + (matches === 1 ? ' test' : ' tests');
            if (emptyState) emptyState.hidden = matches !== 0;
        }

        navigator.addEventListener('click', function (event) {
            const button = event.target.closest('[data-filter-level]');
            if (!button || button.hidden) return;
            const filterLevel = button.dataset.filterLevel;
            state[filterLevel] = button.dataset.filterValue || '';
            labels[filterLevel] = state[filterLevel] ? button.dataset.filterLabel : '';

            if (filterLevel === 'subject') {
                state.topic = '';
                state.subtopic = '';
                labels.topic = '';
                labels.subtopic = '';
            } else if (filterLevel === 'topic') {
                state.subtopic = '';
                labels.subtopic = '';
            }
            updateHierarchy();
        });

        const subjectButtons = buttons.filter(function (button) {
            return button.dataset.filterLevel === 'subject' && button.dataset.filterValue;
        });
        if (subjectButtons.length === 1) {
            state.subject = subjectButtons[0].dataset.filterValue;
            labels.subject = subjectButtons[0].dataset.filterLabel;
        }
        updateHierarchy();
    });

    const examTypeTabs = Array.from(document.querySelectorAll('[data-exam-type-tab]'));
    const examTypePanels = Array.from(document.querySelectorAll('[data-exam-type-panel]'));
    examTypeTabs.forEach(function (tab) {
        tab.addEventListener('click', function () {
            const selectedType = tab.dataset.examTypeTab;
            examTypeTabs.forEach(function (candidate) {
                const active = candidate === tab;
                candidate.classList.toggle('is-active', active);
                candidate.setAttribute('aria-selected', active ? 'true' : 'false');
            });
            examTypePanels.forEach(function (panel) {
                panel.hidden = selectedType !== 'all' && panel.dataset.examTypePanel !== selectedType;
            });
        });
    });
</script>
@endpush

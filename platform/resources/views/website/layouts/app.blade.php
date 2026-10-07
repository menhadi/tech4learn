@php
    $configuration = getConfiguration();
@endphp
<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-layout="vertical" data-topbar="light" data-sidebar="dark" data-sidebar-size="lg"
    data-sidebar-image="none" data-preloader="disable">

<head>
    @include('website.partials.google-analytics')
    {{-- Meta tags, Title, Favicon --}}
    @if(!($subcategoriesEnabled ?? true))<style>[data-subcategory-ui]{display:none!important}</style>@endif
    <meta charset="utf-8" />
    @include('website.partials.seo')
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta content="{{ $configuration->name ?? 'ExamElite' }}" name="author" />
    @if (isset($configuration->favicon))
        <link rel="shortcut icon" href="{{ asset('storage/' . $configuration->favicon) }}" type="image/x-icon">
    @else
        <link rel="shortcut icon" href="{{ URL::asset('build/images/favicon.ico') }}" type="image/x-icon">
    @endif
    
    {{-- CSRF Token (AJAX ke liye zaroori) --}}
    <meta name="csrf-token" content="{{ csrf_token() }}">

    {{-- CSS Links --}}
    <script src="{{ asset('build/js/layout.js') }}"></script>
    <link href="{{ asset('build/css/bootstrap.min.css') }}" rel="stylesheet" />
    <link href="{{ asset('build/css/icons.min.css') }}" rel="stylesheet" />
    <link href="{{ asset('build/css/app.min.css') }}" rel="stylesheet" />
    <link href="{{ asset('build/css/custom.min.css') }}" rel="stylesheet" />
    
    {{-- SweetAlert CSS --}}
    <link href="{{ asset('build/libs/sweetalert2/sweetalert2.min.css') }}" rel="stylesheet" type="text/css" />
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet" />

    {{-- Layout Fix Styling (Aapke V2 code se) --}}
    <style>
        /* Top Action Bar Styling (Dark) */
        .top-action-bar { background-color: var(--theme-footer-bg, #0f172a); color: var(--theme-footer-text, #cbd5e1); padding: 0.4rem 0; font-size: 0.8rem; }
        .top-action-bar a { color: var(--theme-footer-text, #cbd5e1); text-decoration: none; margin-left: 0.8rem; transition: color 0.2s ease; }
        .top-action-bar a:hover { color: var(--theme-secondary, #f59e0b); }
        .top-action-bar .info-item i { margin-right: 0.3rem; color: var(--theme-secondary, #f59e0b); }
        .top-action-bar .currency-info { font-weight: 500; color: var(--theme-footer-text, #cbd5e1); margin-left: 1rem; }
        .top-action-bar .get-help-link { font-weight: 500; margin-left: 1rem; }
        .top-action-bar .get-help-link i { color: var(--theme-secondary, #f59e0b); }
        
        /* Navbar ki styling */
        .navbar-landing {
            background-color: var(--theme-header-bg, #ffffff) !important;
            box-shadow: 0 2px 4px rgba(0, 0, 0, .05);
            padding-top: 0.75rem;
            padding-bottom: 0.75rem;
            width: 100%; 
        }
        .navbar-landing .navbar-brand img { height: 40px; }
        .navbar-landing .navbar-nav .nav-item { position: relative; }
        
        @media (max-width: 991.98px) {
            .top-action-bar .justify-content-md-end { justify-content: center !important; margin-top: 0.3rem; }
        }

        /* Language dropdown text color fix */
        .top-action-bar .dropdown-menu .dropdown-item {
            color: var(--theme-heading, #0f172a) !important;
        }
        .top-action-bar .dropdown-menu .dropdown-item:hover {
            color: var(--theme-primary, #0f766e) !important;
            background-color: color-mix(in srgb, var(--theme-primary, #0f766e) 8%, #ffffff);
        }
        
        /* Mega Menu CSS (Aapki file se) */
        .dropdown-mega { position: static !important; }
        .dropdown-menu-mega { min-width: 900px; max-width: 90%; left: 50% !important; transform: translateX(-50%) !important; top: 100%; padding: 1.5rem 1rem; border-radius: 0 0 0.5rem 0.5rem; box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15); margin-top: 0; border: none; }
        @media (max-width: 991.98px) { .dropdown-menu-mega { width: 100%; min-width: auto; left: 0 !important; transform: none !important; } .mega-menu-content { flex-direction: column; } }
        .mega-menu-content { display: flex; width: 100%; }
        .mega-menu-column { padding: 0 1rem; }
        .mega-menu-column.categories { flex: 0 0 220px; border-right: 1px solid var(--theme-border, #e2e8f0); max-height: 400px; overflow-y: auto; }
        .mega-menu-column.categories .nav-link { display: block; padding: 0.6rem 1rem; color: var(--theme-heading, #0f172a); font-weight: 500; border-radius: 0.3rem; white-space: nowrap; cursor: pointer; }
        .mega-menu-column.categories .nav-link:hover, .mega-menu-column.categories .nav-link.active { background-color: color-mix(in srgb, var(--theme-primary, #0f766e) 10%, #ffffff); color: var(--theme-primary, #0f766e); }
        .mega-menu-column.packages { flex: 1; padding-left: 1.5rem; max-height: 400px; overflow-y: auto; }
        .tab-pane { display: none; } .tab-pane.active { display: block; }
        .package-item-card { display: flex; align-items: center; padding: 0.75rem 0.5rem; text-decoration: none; border-radius: 0.3rem; transition: background-color 0.2s ease; }
        .package-item-card:hover { background-color: color-mix(in srgb, var(--theme-primary, #0f766e) 6%, #ffffff); }
        .package-item-card img { width: 50px; height: 50px; object-fit: cover; border-radius: 0.25rem; margin-right: 1rem; }
        .package-item-card .package-details h6 { font-size: 0.95rem; font-weight: 600; color: var(--theme-heading, #0f172a); margin-bottom: 0.2rem; }
        .package-item-card .package-details span { font-size: 0.8rem; color: var(--theme-text, #64748b); }
    

        /* Unified public pagination: Explore, groups, categories and package lists */
        nav[aria-label="Pagination Navigation"],
        .pagination,
        .pagination-custom {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
            margin: 24px 0 0;
        }

        .pagination .page-item,
        .pagination li {
            display: flex;
            align-items: center;
            margin: 0 !important;
        }

        .pagination .page-link,
        .pagination span.page-link,
        .pagination a.page-link {
            width: 40px;
            height: 40px;
            min-width: 40px;
            padding: 0 !important;
            margin: 0 !important;
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            line-height: 1 !important;
            border-radius: 12px !important;
            border: 1px solid color-mix(in srgb, var(--theme-primary, #0f766e) 22%, #ffffff) !important;
            background: #ffffff !important;
            color: var(--theme-heading, #0f172a) !important;
            font-size: 14px;
            font-weight: 700;
            box-shadow: none !important;
            transform: none !important;
            position: static !important;
        }

        .pagination .page-item:first-child .page-link,
        .pagination .page-item:last-child .page-link {
            width: auto;
            min-width: 92px;
            padding: 0 14px !important;
            gap: 6px;
            white-space: nowrap;
        }

        .pagination .page-link:hover {
            background: color-mix(in srgb, var(--theme-primary, #0f766e) 10%, #ffffff) !important;
            color: var(--theme-primary, #0f766e) !important;
            border-color: var(--theme-primary, #0f766e) !important;
        }

        .pagination .page-item.active .page-link,
        .pagination .active > .page-link {
            background: var(--theme-secondary, #f59e0b) !important;
            border-color: var(--theme-secondary, #f59e0b) !important;
            color: var(--theme-button-text, #ffffff) !important;
        }

        .pagination .page-item.disabled .page-link,
        .pagination .disabled > .page-link {
            background: #f1f5f9 !important;
            color: #94a3b8 !important;
            border-color: #e2e8f0 !important;
            opacity: 1;
        }

        .pagination svg {
            width: 16px;
            height: 16px;
            display: block;
        }

        @media (max-width: 575.98px) {
            nav[aria-label="Pagination Navigation"],
            .pagination,
            .pagination-custom {
                gap: 10px;
            }

            .pagination .page-link,
            .pagination span.page-link,
            .pagination a.page-link {
                width: 38px;
                height: 38px;
                min-width: 38px;
                border-radius: 11px !important;
                font-size: 13px;
            }

            .pagination .page-item:first-child .page-link,
            .pagination .page-item:last-child .page-link {
                min-width: 104px;
                padding: 0 12px !important;
            }
        }

        .cart-item-icon {
            align-items: center;
            background: color-mix(in srgb, var(--theme-primary, #0f766e) 10%, #ffffff);
            border: 1px solid color-mix(in srgb, var(--theme-primary, #0f766e) 16%, #ffffff);
            border-radius: 12px;
            color: var(--theme-primary, #0f766e);
            display: inline-flex;
            flex: 0 0 44px;
            font-size: 21px;
            height: 44px;
            justify-content: center;
            margin-right: 10px;
            width: 44px;
        }

        .cart-action-primary,
        .cart-action-secondary {
            border-radius: 12px !important;
            font-weight: 800 !important;
            min-height: 46px;
        }

        .cart-action-primary {
            background: var(--theme-primary, #0f766e) !important;
            border-color: var(--theme-primary, #0f766e) !important;
            color: var(--theme-button-text, #ffffff) !important;
        }

        .cart-action-secondary {
            background: color-mix(in srgb, var(--theme-secondary, #f59e0b) 12%, #ffffff) !important;
            border: 1px solid color-mix(in srgb, var(--theme-secondary, #f59e0b) 48%, #ffffff) !important;
            color: color-mix(in srgb, var(--theme-secondary, #f59e0b) 72%, var(--theme-heading, #111827)) !important;
        }

    </style>

@php
    $themeConfig = $configuration ?? (function_exists('getConfiguration') ? getConfiguration() : null);
    $theme = [
        'primary' => $themeConfig->theme_primary_color ?? '#0f766e',
        'secondary' => $themeConfig->theme_secondary_color ?? '#f59e0b',
        'tertiary' => $themeConfig->theme_tertiary_color ?? '#38bdf8',
        'headerBg' => $themeConfig->theme_header_bg ?? '#ffffff',
        'headerText' => $themeConfig->theme_header_text ?? '#0f172a',
        'footerBg' => $themeConfig->theme_footer_bg ?? '#0f172a',
        'footerText' => $themeConfig->theme_footer_text ?? '#cbd5e1',
        'bodyBg' => $themeConfig->theme_body_bg ?? '#ffffff',
        'heading' => $themeConfig->theme_heading_color ?? '#0f172a',
        'text' => $themeConfig->theme_text_color ?? '#64748b',
        'cardBg' => $themeConfig->theme_card_bg ?? '#ffffff',
        'border' => $themeConfig->theme_border_color ?? '#e2e8f0',
        'buttonText' => $themeConfig->theme_button_text ?? '#ffffff',
    ];
@endphp
<style>
    :root {
        --theme-primary: {{ $theme['primary'] }};
        --theme-secondary: {{ $theme['secondary'] }};
        --theme-tertiary: {{ $theme['tertiary'] }};
        --theme-header-bg: {{ $theme['headerBg'] }};
        --theme-header-text: {{ $theme['headerText'] }};
        --theme-footer-bg: {{ $theme['footerBg'] }};
        --theme-footer-text: {{ $theme['footerText'] }};
        --theme-body-bg: {{ $theme['bodyBg'] }};
        --theme-heading: {{ $theme['heading'] }};
        --theme-text: {{ $theme['text'] }};
        --theme-card-bg: {{ $theme['cardBg'] }};
        --theme-border: {{ $theme['border'] }};
        --theme-button-text: {{ $theme['buttonText'] }};
    }

    body {
        background: var(--theme-body-bg);
    }

    h1, h2, h3, h4, h5, h6 {
        color: var(--theme-heading);
    }

    .btn-primary,
    .btn-success,
    .btn-enroll-free,
    .header-cta,
    .site-primary-btn {
        background: var(--theme-primary) !important;
        border-color: var(--theme-primary) !important;
        color: var(--theme-button-text) !important;
    }

    .text-primary,
    .navbar .nav-link.active,
    .navbar .nav-link:hover {
        color: var(--theme-primary) !important;
    }

    .navbar,
    .job-navbar {
        background: var(--theme-header-bg) !important;
    }

    .navbar .nav-link,
    .job-navbar .nav-link {
        color: var(--theme-header-text) !important;
    }

    .footer,
    footer {
        background: var(--theme-footer-bg) !important;
        color: var(--theme-footer-text) !important;
    }

    footer a,
    .footer a {
        color: var(--theme-footer-text) !important;
    }

    footer a:hover,
    .footer a:hover {
        color: var(--theme-secondary) !important;
    }

    .website-main,
    .page-content,
    .main-content {
        padding-top: 0 !important;
    }

    .navbar + *,
    .job-navbar + * {
        margin-top: 0 !important;
    }

    @media (max-width: 991.98px) {
        .website-main,
        .page-content,
        .main-content {
            padding-top: 0 !important;
        }
    }

    .swal2-popup {
        border: 1px solid var(--theme-border, #e2e8f0) !important;
        border-radius: 18px !important;
        color: var(--theme-heading, #0f172a) !important;
        font-family: inherit !important;
        padding: 1.75rem !important;
    }

    .swal2-title {
        color: var(--theme-heading, #0f172a) !important;
        font-weight: 800 !important;
    }

    .swal2-html-container {
        color: var(--theme-text, #64748b) !important;
    }

    .swal2-icon.swal2-info,
    .swal2-icon.swal2-question,
    .swal2-icon.swal2-success {
        border-color: var(--theme-primary, #0f766e) !important;
        color: var(--theme-primary, #0f766e) !important;
    }

    .swal2-icon.swal2-info .swal2-icon-content,
    .swal2-icon.swal2-question .swal2-icon-content,
    .swal2-icon.swal2-success .swal2-icon-content {
        color: var(--theme-primary, #0f766e) !important;
    }

    .swal2-styled {
        border-radius: 12px !important;
        box-shadow: none !important;
        font-weight: 800 !important;
        min-width: 94px;
        outline: none !important;
    }

    .swal2-styled.swal2-confirm {
        background: var(--theme-primary, #0f766e) !important;
        border: 1px solid var(--theme-primary, #0f766e) !important;
        color: var(--theme-button-text, #ffffff) !important;
    }

    .swal2-styled.swal2-confirm:hover,
    .swal2-styled.swal2-confirm:focus,
    .swal2-styled.swal2-confirm:active {
        background: color-mix(in srgb, var(--theme-primary, #0f766e) 88%, #000000) !important;
        border-color: color-mix(in srgb, var(--theme-primary, #0f766e) 88%, #000000) !important;
        color: var(--theme-button-text, #ffffff) !important;
    }

    .swal2-styled.swal2-cancel,
    .swal2-styled.swal2-deny {
        background: var(--theme-secondary, #f59e0b) !important;
        border: 1px solid var(--theme-secondary, #f59e0b) !important;
        color: var(--theme-button-text, #ffffff) !important;
    }

    .swal2-styled.swal2-cancel:hover,
    .swal2-styled.swal2-cancel:focus,
    .swal2-styled.swal2-deny:hover,
    .swal2-styled.swal2-deny:focus {
        background: color-mix(in srgb, var(--theme-secondary, #f59e0b) 90%, #000000) !important;
        border-color: color-mix(in srgb, var(--theme-secondary, #f59e0b) 90%, #000000) !important;
        color: var(--theme-button-text, #ffffff) !important;
    }
</style>


<style>
    body {
        scroll-padding-top: 0 !important;
    }

    #page-top-header + *,
    .navbar-landing + *,
    .examelite-header + * {
        margin-top: 0 !important;
    }

    .website-first-section,
    .hero-section {
        margin-top: 0 !important;
    }
</style>

<style>
    /* Strong public theme mapping */
    .text-success,
    .text-teal,
    .text-primary,
    a:not(.dropdown-item):not(.btn):hover {
        color: var(--theme-primary) !important;
    }

    .bg-success,
    .bg-primary,
    .badge.bg-success,
    .nav-pills .nav-link.active,
    .nav-tabs .nav-link.active,
    .rank-badge,
    .student-initial,
    .exam-count-badge,
    .course-price-badge,
    .featured-category-link:hover,
    .btn-success,
    .btn-primary,
    .btn-enroll-free,
    .startExamBtn,
    .take-all-exams-btn {
        background-color: var(--theme-primary) !important;
        border-color: var(--theme-primary) !important;
        color: var(--theme-button-text) !important;
    }

    .landing-back-top {
        background: var(--theme-primary, #0f766e) !important;
        border-color: var(--theme-primary, #0f766e) !important;
        color: var(--theme-button-text, #ffffff) !important;
        box-shadow: 0 10px 24px color-mix(in srgb, var(--theme-primary, #0f766e) 22%, transparent) !important;
    }

    .landing-back-top:hover,
    .landing-back-top:focus {
        background: var(--theme-primary, #0f766e) !important;
        border-color: var(--theme-primary, #0f766e) !important;
        color: var(--theme-button-text, #ffffff) !important;
        outline: none !important;
    }

    .addToCartBtn:active,
    .addToCartBtn:focus,
    .addToCartBtn:hover,
    .addToCartBtn:disabled,
    .addToCartBtn.disabled,
    .startExamBtn:active,
    .startExamBtn:focus,
    .startExamBtn:hover,
    .startExamBtn:disabled,
    .startExamBtn.disabled,
    .downloadPdfBtn:active,
    .downloadPdfBtn:focus,
    .downloadPdfBtn:disabled,
    .downloadPdfBtn.disabled,
    .take-all-exams-btn:active,
    .take-all-exams-btn:focus,
    .take-all-exams-btn:disabled,
    .take-all-exams-btn.disabled,
    .package-sidebar-cta:active,
    .package-sidebar-cta:focus,
    .package-sidebar-cta:disabled,
    .package-sidebar-cta.disabled,
    .package-hero-cta:active,
    .package-hero-cta:focus,
    .package-hero-cta:hover,
    .package-hero-cta:disabled,
    .package-hero-cta.disabled,
    .guest-flashcards-cta:active,
    .guest-flashcards-cta:focus,
    .guest-flashcards-cta:hover,
    .site-primary-btn:active,
    .site-primary-btn:focus,
    .site-primary-btn:hover,
    .header-cta:active,
    .header-cta:focus,
    .header-cta:hover,
    .btn-teal:active,
    .btn-teal:focus,
    .btn-teal:hover,
    .btn-teal:disabled,
    .btn-teal.disabled,
    .btn-pdf:active,
    .btn-pdf:focus,
    .btn-pdf:disabled,
    .btn-pdf.disabled {
        background: var(--theme-primary, #0f766e) !important;
        border-color: var(--theme-primary, #0f766e) !important;
        color: var(--theme-button-text, #ffffff) !important;
        opacity: .88;
        -webkit-text-fill-color: var(--theme-button-text, #ffffff);
    }

    .guest-flashcards-outline:active,
    .guest-flashcards-outline:focus,
    .guest-flashcards-outline:hover,
    .btn-outline-success:active,
    .btn-outline-success:focus,
    .btn-outline-primary:active,
    .btn-outline-primary:focus {
        background: color-mix(in srgb, var(--theme-primary, #0f766e) 9%, #ffffff) !important;
        border-color: var(--theme-primary, #0f766e) !important;
        color: var(--theme-primary, #0f766e) !important;
        -webkit-text-fill-color: var(--theme-primary, #0f766e);
    }

    .downloadPdfBtn:active,
    .downloadPdfBtn:focus,
    .downloadPdfBtn:hover,
    .btn-pdf:active,
    .btn-pdf:focus,
    .btn-pdf:hover {
        background: var(--theme-secondary, #f59e0b) !important;
        border-color: var(--theme-secondary, #f59e0b) !important;
        color: var(--theme-button-text, #ffffff) !important;
        -webkit-text-fill-color: var(--theme-button-text, #ffffff);
    }

    .btn-outline-success,
    .btn-outline-primary {
        border-color: var(--theme-primary) !important;
        color: var(--theme-primary) !important;
    }

    .btn-outline-success:hover,
    .btn-outline-primary:hover {
        background-color: var(--theme-primary) !important;
        color: var(--theme-button-text) !important;
    }

    .course-card-btn-primary:hover,
    .course-card-btn-primary:focus,
    .course-card-btn-primary:active,
    .course-card-btn-primary.active,
    .course-card-btn-primary:disabled,
    .course-card-btn-primary.disabled,
    .take-all-exams-btn:hover,
    .take-all-exams-btn:focus,
    .take-all-exams-btn:active,
    .package-sidebar-cta:hover,
    .package-sidebar-cta:focus,
    .package-sidebar-cta:active,
    .site-primary-btn:hover,
    .site-primary-btn:focus,
    .site-primary-btn:active,
    .btn-outline-success:hover,
    .btn-outline-success:focus,
    .btn-outline-success:active,
    .btn-outline-primary:hover,
    .btn-outline-primary:focus,
    .btn-outline-primary:active {
        background: var(--theme-primary, #0f766e) !important;
        border-color: var(--theme-primary, #0f766e) !important;
        color: var(--theme-button-text, #ffffff) !important;
        -webkit-text-fill-color: var(--theme-button-text, #ffffff) !important;
        text-decoration: none !important;
        opacity: 1 !important;
    }

    .course-card-btn-secondary:hover,
    .course-card-btn-secondary:focus,
    .course-card-btn-secondary:active,
    .downloadPdfBtn:hover,
    .downloadPdfBtn:focus,
    .downloadPdfBtn:active,
    .btn-pdf:hover,
    .btn-pdf:focus,
    .btn-pdf:active {
        background: var(--theme-secondary, #f59e0b) !important;
        border-color: var(--theme-secondary, #f59e0b) !important;
        color: var(--theme-button-text, #ffffff) !important;
        -webkit-text-fill-color: var(--theme-button-text, #ffffff) !important;
        text-decoration: none !important;
        opacity: 1 !important;
    }

    .course-card-btn-primary:hover *,
    .course-card-btn-primary:focus *,
    .course-card-btn-primary:active *,
    .course-card-btn-secondary:hover *,
    .course-card-btn-secondary:focus *,
    .course-card-btn-secondary:active *,
    .take-all-exams-btn:hover *,
    .take-all-exams-btn:focus *,
    .take-all-exams-btn:active *,
    .package-sidebar-cta:hover *,
    .package-sidebar-cta:focus *,
    .package-sidebar-cta:active *,
    .site-primary-btn:hover *,
    .site-primary-btn:focus *,
    .site-primary-btn:active *,
    .downloadPdfBtn:hover *,
    .downloadPdfBtn:focus *,
    .downloadPdfBtn:active *,
    .btn-pdf:hover *,
    .btn-pdf:focus *,
    .btn-pdf:active * {
        color: inherit !important;
        -webkit-text-fill-color: inherit !important;
    }

    .footer-title::after,
    .footer-subtitle::after,
    .section-title::after,
    .card-title::after {
        background: var(--theme-secondary) !important;
    }

    .examelite-header,
    .navbar-landing,
    #page-top-header {
        background: var(--theme-header-bg) !important;
    }

    .examelite-header .nav-link,
    .navbar-landing .nav-link {
        color: var(--theme-header-text) !important;
    }

    .footer,
    footer,
    .site-footer {
        background: var(--theme-footer-bg) !important;
        color: var(--theme-footer-text) !important;
    }

    .footer a,
    footer a,
    .site-footer a {
        color: var(--theme-footer-text) !important;
    }

    .footer a:hover,
    footer a:hover,
    .site-footer a:hover {
        color: var(--theme-secondary) !important;
    }

    .course-title,
    .package-title,
    .exam-title,
    .section-heading,
    .hero-section h1,
    .hero-section h2 {
        color: var(--theme-heading) !important;
    }

    .theme-page-shell,
    .theme-contact-shell {
        background: var(--theme-body-bg, #ffffff);
        padding: clamp(24px, 5vw, 52px) 0 clamp(36px, 7vw, 70px);
    }

    .theme-page-hero,
    .theme-contact-hero {
        background: color-mix(in srgb, var(--theme-primary, #0f766e) 7%, var(--theme-card-bg, #ffffff));
        border: 1px solid color-mix(in srgb, var(--theme-primary, #0f766e) 16%, var(--theme-border, #e2e8f0));
        border-radius: 16px;
        padding: clamp(22px, 5vw, 44px);
    }

    .theme-page-kicker,
    .theme-contact-kicker {
        color: var(--theme-primary, #0f766e);
        font-size: .78rem;
        font-weight: 850;
        letter-spacing: .06em;
        margin-bottom: 10px;
        text-transform: uppercase;
    }

    .theme-page-title,
    .theme-contact-title {
        color: var(--theme-heading, #0f172a);
        font-size: clamp(1.8rem, 6vw, 3rem);
        font-weight: 900;
        line-height: 1.1;
        margin: 0;
    }

    .theme-page-card,
    .theme-contact-card {
        background: var(--theme-card-bg, #ffffff);
        border: 1px solid color-mix(in srgb, var(--theme-primary, #0f766e) 14%, var(--theme-border, #e2e8f0));
        border-radius: 16px;
    }

    .theme-page-card {
        margin-top: 18px;
        padding: clamp(20px, 4.5vw, 40px);
    }

    .theme-page-content,
    .theme-contact-copy,
    .theme-contact-value {
        color: var(--theme-text, #64748b);
        overflow-wrap: anywhere;
    }

    .theme-page-content {
        font-size: 1rem;
        line-height: 1.78;
    }

    .theme-page-content > *:first-child {
        margin-top: 0 !important;
    }

    .theme-page-content > *:last-child {
        margin-bottom: 0 !important;
    }

    .theme-page-content h1,
    .theme-page-content h2,
    .theme-page-content h3,
    .theme-page-content h4,
    .theme-page-content h5,
    .theme-page-content h6 {
        color: var(--theme-heading, #0f172a);
        font-weight: 850;
        line-height: 1.2;
        margin: 1.4rem 0 .75rem;
    }

    .theme-page-content a {
        color: var(--theme-primary, #0f766e);
        font-weight: 800;
    }

    .theme-page-content img {
        border-radius: 12px;
        height: auto;
        max-width: 100%;
    }
</style>

<style>

    /* Keep the body/footer boundary clean on all public pages */
    body,
    .main-content,
    .page-content,
    main,
    .layout-wrapper,
    .website-page-wrapper {
        background-color: var(--theme-body-bg, #ffffff) !important;
    }

    .website-footer,
    footer {
        margin-top: 0 !important;
    }

    .main-content > section:last-child,
    .page-content > section:last-child,
    section:last-of-type {
        margin-bottom: 0 !important;
    }

</style>
</head>

<body data-bs-spy="scroll" data-bs-target="#navbar-example">

    <div class="layout-wrapper landing">

        {{-- Aapka Header Wrapper (FIX V2) --}}
        <header class="fixed-top" id="page-top-header">

            {{-- Top Action Bar --}}
            <div class="top-action-bar" style="display:none;">
                <div class="container-fluid custom-container">
                    <div class="row align-items-center">
                        {{-- Left Side: Contact Info --}}
                        <div class="col-lg-6 col-md-8 d-flex align-items-center justify-content-center justify-content-md-start">
                            <!-- @if(!empty($configuration->organization_phone)) 
                            <span class="info-item me-3"> <i class="ri-phone-line"></i> <a href="tel:{{ $configuration->organization_phone }}">{{ $configuration->organization_phone }}</a> </span> 
                            @endif
                            @if(!empty($configuration->email)) 
                            <span class="info-item"> <i class="ri-mail-line"></i> <a href="mailto:{{ $configuration->email }}">{{ $configuration->email }}</a> </span> 
                            @endif -->
                        </div>
                        {{-- Right Side: Currency & Get Help --}}
                        <div class="col-lg-6 col-md-4 d-flex align-items-center justify-content-center justify-content-md-end">
                            @if(!empty($configuration->currency)) <span class="currency-info">{{ __('ui.currency') }} {{ $configuration->currency }}</span> @endif
                            <a href="/contact" class="get-help-link"> <i class="ri-customer-service-2-line align-middle"></i> {{ __('website.get_help') }} </a>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Navigation (Yeh file Step 1 mein update ki thi) --}}
            @include('website.navbar')

        </header>
        {{-- END OF HEADER WRAPPER --}}


        {{-- Main content --}}
        @yield('content')

        {{-- Footer & Back to Top Button --}}
        @include('website.footer')
        @include('website.partials.social-share')
        <button onclick="topFunction()" class="btn btn-icon landing-back-top" id="back-to-top"> <i class="ri-arrow-up-line"></i> </button>
    </div>

    @include('website.partials.vector-academy-popup')

    {{-- ================================================= --}}
    {{-- ✅ FIXED: Scripts Loading Order --}}
    {{-- ================================================= --}}
    
    {{-- 1. jQuery sabse pehle --}}
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

    {{-- 2. Bootstrap & Other Libraries --}}
    <script src="{{ asset('build/libs/bootstrap/js/bootstrap.bundle.min.js') }}"></script>
    
    {{-- ❌ REMOVED: plugins.js (Document write issue fix) --}}
    
    <script src="{{ asset('build/libs/sweetalert2/sweetalert2.min.js') }}"></script>

    <script>
        $(document).ready(function() {
            
            // Layout Fix JS (Aapka V2 code)
            function adjustBodyPadding() {
                var headerHeight = $('#page-top-header').outerHeight();
                if(headerHeight > 0) {
                    $('body').css('padding-top', headerHeight + 'px');
                }
            }
            adjustBodyPadding();
            $(window).on('resize', function() {
                adjustBodyPadding();
            });

            // ✅✅✅ YEH CART KA POORA LOGIC HAI ✅✅✅
            
            // CSRF Token Setup (AJAX ke liye zaroori)
            $.ajaxSetup({
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') // CSRF token ko har AJAX request ke saath bhejo
                }
            });

            function updateCartCounter(count) {
                const normalizedCount = Math.max(0, parseInt(count, 10) || 0);
                $('#cartItemCount').text(normalizedCount);
                $('#cartNavCount').text(normalizedCount);
                $('#headerCartControl').toggleClass('d-none', normalizedCount === 0);
            }

            function loadCart() {
                $.ajax({
                    url: '{{ route("cart.index") }}',
                    type: 'GET',
                    success: function(response) {
                        updateCartCounter(response.count);
                        let itemsHtml = '';
                        if (response.count > 0) {
                            response.items.forEach(function(item) {
                                let price = parseFloat(item.price).toFixed(2);
                                let itemVisual = '<span class="cart-item-icon"><i class="ri-file-list-3-line"></i></span>';
                                
                                // ✅ 'storage/' prefix ko handle kiya

                                itemsHtml += `
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        <div class="d-flex align-items-center">
                                            ${itemVisual}
                                            <div>
                                                <h6 class="my-0 fs-14">${item.name}</h6>
                                                <small class="text-muted">Price: {{ $configuration->currency ?? '$' }}${price}</small>
                                            </div>
                                        </div>
                                        <span class="text-muted">${item.quantity}</span>
                                        <button class="btn btn-sm btn-outline-danger py-0 px-1 fs-10 removeCartItemBtn" data-id="${item.package_id}">&times;</button>
                                    </li>
                                `;
                            });
                            $('#cart-footer').html('<a href="/checkout" class="btn cart-action-primary w-100">{{ __("website.cart_checkout") }}</a><a href="/courses" class="btn cart-action-secondary w-100 mt-2">{{ __("website.cart_continue_shopping") }}</a>');
                            $('#cartTotalRow strong').text('{{ $configuration->currency ?? '$' }}' + parseFloat(response.total).toFixed(2));
                        } else {
                            itemsHtml = '<div class="text-center text-muted py-5"><p>{{ __("website.cart_empty") }}</p></div>';
                            $('#cart-footer').html('<a href="/courses" class="btn cart-action-primary w-100">{{ __("website.cart_browse_courses") }}</a>');
                            $('#cartTotalRow strong').text('{{ $configuration->currency ?? '$' }}0.00');
                        }
                        $('#cartItemsContainer').html(itemsHtml);
                    },
                    error: function() {
                        console.error('Failed to load cart.');
                    }
                });
            }

            // Remove Item
            $(document).on('click', '.removeCartItemBtn', function(e) {
                e.preventDefault();
                var packageId = $(this).data('id');
                $.ajax({
                    url: '/cart/' + packageId, // URL ko destroy route ke hisaab se set kiya
                    type: 'DELETE',
                    success: function(response) {
                        loadCart(); // Reload cart items
                        Swal.fire({ icon: 'success', title: '{{ __("website.js_item_removed") }}', showConfirmButton: false, timer: 1500 });
                    },
                    error: function() {
                        Swal.fire({ icon: 'error', title: 'Oops...', text: '{{ __("website.js_item_remove_fail") }}' });
                    }
                });
            });

            // Add Item
            $(document).on('click', '.addToCartBtn', function(e) {
                e.preventDefault();
                var packageId = $(this).data('id');
                var groupId = $(this).data('groupid');
                var examKey = $(this).data('exam') || '';
                var $this = $(this);
                var examKey = $this.data('exam') || $this.closest('tr').find('.downloadPdfBtn').data('exam-id') || '';
                var originalHtml = $this.html();
                
                $this.prop('disabled', true).html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> {{ __("website.js_adding") }}');

                $.ajax({
                    url: '{{ route("cart.store") }}',
                    type: 'POST',
                    data: { id: packageId, groupId: groupId, exam: examKey },
                    success: function(response) {
                        // if (response.redirectUrl != '') {
                        //     window.location.href = response.redirectUrl;
                        //     return;
                        // }

                        if (response.redirectUrl) {
                            $this.prop('disabled', false).html(originalHtml);
                            window.location.href = response.redirectUrl;
                            return;
                        }

                        loadCart(); // Reload cart items
                        Swal.fire({ icon: 'success', title: '{{ __("website.js_item_added") }}', showConfirmButton: false, timer: 1500 });
                        $this.prop('disabled', false).html(originalHtml);
                    },
                    // ✅✅✅ START: JAVASCRIPT ERROR HANDLER FIX ✅✅✅
                    error: function(jqXHR) { 
                        let errorMessage = '{{ __("website.js_item_add_fail") }}'; // Default error
                        let errorTitle = 'Oops...';
                        let errorIcon = 'error';

                        // if(jqXHR.responseJSON.redirectUrl != '') {
                        //     window.location.href = jqXHR.responseJSON.redirectUrl;
                        //     return;
                        // }

                        if(jqXHR.responseJSON && jqXHR.responseJSON.redirectUrl) {
                            $this.prop('disabled', false).html(originalHtml);
                            window.location.href = jqXHR.responseJSON.redirectUrl;
                            return;
                        }

                        // Check agar yeh hamara custom 409 error hai
                        if (jqXHR.status === 409 && jqXHR.responseJSON && jqXHR.responseJSON.message) {
                            errorMessage = jqXHR.responseJSON.message; // Controller se "Already in cart" message
                            errorTitle = 'Info'; // Title ko 'Info' rakhein
                            errorIcon = 'info'; // Icon ko 'info' rakhein
                        }
                        
                        // Error popup dikhayein
                        Swal.fire({ icon: errorIcon, title: errorTitle, text: errorMessage });
                        $this.prop('disabled', false).html(originalHtml);
                    }
                    // ✅✅✅ END: JAVASCRIPT ERROR HANDLER FIX ✅✅✅
                });
            });

            $(document).on('click', '.startExamBtn', function(e) {
                e.preventDefault();
                var packageId = $(this).data('id');
                var groupId = $(this).data('groupid');
                var examKey = $(this).data('exam') || '';
                var $this = $(this);
                var examKey = $this.data('exam') || $this.closest('tr').find('.downloadPdfBtn').data('exam-id') || '';
                var originalHtml = $this.html();
                
                $this.prop('disabled', true).html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> {{ __("website.js_adding") }}');

                $.ajax({
                    url: "{{ route('checkout.enroll_exam') }}",
                    type: 'POST',
                    data: { id: packageId, groupId: groupId, exam: examKey },
                    success: function(response) {

                        if (response.redirectUrl) {
                            $this.prop('disabled', false).html(originalHtml);
                            window.location.href = response.redirectUrl;
                            return;
                        }

                        //loadCart(); // Reload cart items
                        Swal.fire({ icon: 'success', title: 'You have been successfully subscribed to the exam.', showConfirmButton: false, timer: 1500 });
                        $this.prop('disabled', false).html(originalHtml);
                    },
                    // ✅✅✅ START: JAVASCRIPT ERROR HANDLER FIX ✅✅✅
                    error: function(jqXHR) { 
                        let errorMessage = '{{ __("website.js_item_add_fail") }}'; // Default error
                        let errorTitle = 'Oops...';
                        let errorIcon = 'error';

                        if (jqXHR.responseJSON && jqXHR.responseJSON.redirectUrl) {
                            $this.prop('disabled', false).html(originalHtml);
                            window.location.href = jqXHR.responseJSON.redirectUrl;
                            return;
                        }

                        if (jqXHR.responseJSON && jqXHR.responseJSON.message) {
                            errorMessage = jqXHR.responseJSON.message;
                        } else if (jqXHR.responseJSON && jqXHR.responseJSON.errors) {
                            errorMessage = Object.values(jqXHR.responseJSON.errors).flat().join('\n');
                        } else if (jqXHR.responseText) {
                            errorMessage = 'Server error ' + jqXHR.status + '. Please check Laravel log for details.';
                        }

                        // Check agar yeh hamara custom 409 error hai
                        if (jqXHR.status === 409 && jqXHR.responseJSON && jqXHR.responseJSON.message) {
                            errorMessage = jqXHR.responseJSON.message; // Controller se "Already in cart" message
                            errorTitle = 'Info'; // Title ko 'Info' rakhein
                            errorIcon = 'info'; // Icon ko 'info' rakhein
                        }
                        
                        // Error popup dikhayein
                        Swal.fire({ icon: errorIcon, title: errorTitle, text: errorMessage });
                        $this.prop('disabled', false).html(originalHtml);
                    }
                    // ✅✅✅ END: JAVASCRIPT ERROR HANDLER FIX ✅✅✅
                });
            });

            $(document).on('click', '.protectedSolutionPdfBtn[data-login-required="1"]', function(e) {
                e.preventDefault();
                e.stopPropagation();

                var activityUrl = $(this).data('solution-activity-url');
                var packageKey = $(this).data('package');
                var trackSolutionActivity = function(action) {
                    if (!activityUrl) return Promise.resolve();
                    return fetch(activityUrl, {
                        method: 'POST',
                        credentials: 'same-origin',
                        keepalive: true,
                        headers: {
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                        },
                        body: JSON.stringify({ action: action, package: packageKey })
                    }).catch(function() {});
                };
                trackSolutionActivity('prompted');
                var solutionUrl = this.href;
                Swal.fire({
                    icon: 'info',
                    title: 'Login required',
                    text: 'To download the Solution PDF, please log in or create an account.',
                    confirmButtonText: 'Login / Create Account',
                    showCancelButton: true,
                    cancelButtonText: 'Not now',
                    confirmButtonColor: '#0f7f75',
                    cancelButtonColor: '#64748b',
                    reverseButtons: true,
                    customClass: {
                        popup: 'theme-confirm-popup'
                    }
                }).then(async function(result) {
                    if (result.isConfirmed) {
                        await trackSolutionActivity('login_selected');
                        window.location.assign(solutionUrl);
                    }
                });

                return false;
            });
            $(document).on('click', '.downloadPdfBtn', async function(e) {
                e.preventDefault();
                e.stopPropagation();

                var $this = $(this);
                var pdfUrl = $this.data('pdf-url');
                var intentUrl = $this.data('pdf-intent-url');
                var originalHtml = $this.html();
                var originalStyle = $this.attr('style');
                var buttonRect = this.getBoundingClientRect();

                if (!pdfUrl && !intentUrl) {
                    Swal.fire({ icon: 'info', title: 'Info', text: 'PDF is not available for this exam.' });
                    return false;
                }
                var selectedPdfLanguage = null;
                var availablePdfLanguages = $this.data('pdf-languages') || [];
                if (typeof availablePdfLanguages === 'string') {
                    try { availablePdfLanguages = JSON.parse(availablePdfLanguages); } catch (_) { availablePdfLanguages = []; }
                }
                if (availablePdfLanguages.length > 1) {
                    var preferredCode = String($this.data('preferred-language') || 'en').toLowerCase();
                    var languageOptions = Object.fromEntries(availablePdfLanguages.map(function(language) {
                        return [String(language.id), language.name];
                    }));
                    var preferredLanguage = availablePdfLanguages.find(function(language) {
                        return String(language.code).toLowerCase() === preferredCode;
                    }) || availablePdfLanguages[0];
                    var languageChoice = await Swal.fire({
                        title: 'Select PDF language', input: 'select', inputOptions: languageOptions,
                        inputValue: String(preferredLanguage.id), showCancelButton: true,
                        confirmButtonText: 'Download PDF'
                    });
                    if (!languageChoice.isConfirmed) return false;
                    selectedPdfLanguage = languageChoice.value;
                } else if (availablePdfLanguages.length === 1) selectedPdfLanguage = availablePdfLanguages[0].id;

                $this
                    .prop('disabled', true)
                    .css({ width: buttonRect.width + 'px', height: buttonRect.height + 'px' })
                    .html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span><span class="visually-hidden">{{ __('ui.preparing_pdf') }}</span>');

                Swal.fire({
                    title: @json(__('ui.preparing_pdf')) ,
                    html: @json(__('ui.pdf_wait')) + '<br><small style="color:#667085">{{ __('ui.pdf_large') }}</small>',
                    allowOutsideClick: false,
                    allowEscapeKey: false,
                    showConfirmButton: false,
                    didOpen: function() {
                        Swal.showLoading();
                        var popup = Swal.getPopup();
                        var title = Swal.getTitle();
                        var loader = popup ? popup.querySelector('.swal2-loader') : null;
                        if (popup) {
                            popup.style.borderTop = '5px solid #0f7f75';
                            popup.style.borderRadius = '18px';
                            popup.style.fontFamily = 'inherit';
                        }
                        if (title) {
                            title.style.color = '#101828';
                            title.style.fontWeight = '700';
                        }
                        if (loader) {
                            loader.style.borderColor = '#0f7f75 transparent #0f7f75 transparent';
                        }
                    }
                });

                try {
                    if (intentUrl) {
                        var csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
                        while (!pdfUrl) {
                            var intentResponse = await fetch(intentUrl, {
                                method: 'POST', credentials: 'same-origin',
                                headers: {
                                    'Accept': 'application/json', 'Content-Type': 'application/json',
                                    'X-CSRF-TOKEN': csrfToken
                                },
                                body: JSON.stringify({ package: $this.data('package'), lang: selectedPdfLanguage })
                            });
                            var intentPayload = await intentResponse.json().catch(function() { return {}; });
                            if (!intentResponse.ok && intentResponse.status !== 202) {
                                throw new Error(intentPayload.message || ('PDF intent failed with status ' + intentResponse.status));
                            }
                            if (intentPayload.preparing) {
                                var translated = intentPayload.translated || 0;
                                var total = intentPayload.total || 0;
                                var html = `Preparing selected language: ${translated} of ${total} questions…`;
                                if (Swal.getHtmlContainer()) Swal.getHtmlContainer().innerHTML = html;
                                if (intentPayload.status === 'processing') await new Promise(function(resolve) { setTimeout(resolve, 1000); });
                                continue;
                            }
                            pdfUrl = intentPayload.download_url;
                        }
                    }

                    var response = await fetch(pdfUrl, {
                        method: 'GET',
                        credentials: 'same-origin',
                        headers: { 'Accept': 'application/pdf' }
                    });

                    if (!response.ok) {
                        throw new Error('PDF generation failed with status ' + response.status);
                    }

                    var contentType = response.headers.get('content-type') || '';
                    if (contentType.indexOf('application/pdf') === -1) {
                        throw new Error('The server did not return a PDF file.');
                    }

                    var blob = await response.blob();
                    var objectUrl = URL.createObjectURL(blob);
                    var disposition = response.headers.get('content-disposition') || '';
                    var filenameMatch = disposition.match(/filename\*?=(?:UTF-8''|["'])?([^"';]+)/i);
                    var fallbackName = (($this.data('exam-name') || 'exam-paper') + '.pdf')
                        .replace(/[^a-z0-9._-]+/gi, '-');
                    var filename = filenameMatch ? decodeURIComponent(filenameMatch[1].trim()) : fallbackName;
                    var link = document.createElement('a');

                    link.href = objectUrl;
                    link.download = filename;
                    document.body.appendChild(link);
                    link.click();
                    link.remove();
                    setTimeout(function() { URL.revokeObjectURL(objectUrl); }, 30000);

                    Swal.fire({
                        icon: 'success',
                        iconColor: '#0f7f75',
                        title: 'PDF downloaded',
                        text: response.headers.get('X-Exam-PDF-Cache') === 'OFFICIAL-SOURCE' ? 'Original official PDF downloaded in its source language.' : 'Your PDF has been downloaded successfully.',
                        timer: 1800,
                        showConfirmButton: false,
                        didOpen: function() {
                            var popup = Swal.getPopup();
                            var title = Swal.getTitle();
                            if (popup) {
                                popup.style.borderTop = '5px solid #0f7f75';
                                popup.style.borderRadius = '18px';
                                popup.style.fontFamily = 'inherit';
                            }
                            if (title) {
                                title.style.color = '#101828';
                                title.style.fontWeight = '700';
                            }
                        }
                    });
                } catch (error) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Download failed',
                        text: 'The PDF could not be prepared. Please try again.'
                    });
                } finally {
                    $this.prop('disabled', false).html(originalHtml);
                    if (originalStyle === undefined) {
                        $this.removeAttr('style');
                    } else {
                        $this.attr('style', originalStyle);
                    }
                }

                return false;
            });
            //header search
            $("#navbarSupportedContent .form-select").change(function(event) {
                $('.navbar-search-form').submit();
            });

            // Initial Load
            loadCart();

            // Baaki ka JS (Navbar, Back to top)
            var currentPath = window.location.pathname; 
            var pillsTab = document.getElementById('v-pills-tab');
            if (pillsTab) { /* ... */ }
            $('.dropdown-menu-mega').on('click', function (e) { /* ... */ });
            var mybutton = document.getElementById("back-to-top");
            window.onscroll = function () { scrollFunction() };
            function scrollFunction() { if (document.body.scrollTop > 100 || document.documentElement.scrollTop > 100) { mybutton.style.display = "block"; } else { mybutton.style.display = "none"; } }
            window.topFunction = function() { document.body.scrollTop = 0; document.documentElement.scrollTop = 0; }

        });

        document.querySelectorAll('.dropdown-menu').forEach(menu => {
            menu.addEventListener('click', function (e) {
                e.stopPropagation();
            });
        });
    </script>

    {{-- JS Translations ke liye --}}
    <script>
        // Pass translations to JS
        window.translations = @json(__('website'));
    </script>

    @stack('scripts')
</body>
</html>

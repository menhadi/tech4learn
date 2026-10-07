<!doctype html>
{{-- ========== ⭐ YEH LINE CHANGE HUI HAI (RTL ke liye) ⭐ ========== --}}
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ session('direction', 'ltr') }}" data-layout="vertical" data-topbar="light"
    data-sidebar="dark" data-sidebar-size="lg" data-sidebar-image="none" data-preloader="disable">
@php
$configuration = $configuration ?? getConfiguration();
$themePrimary = $configuration->theme_primary_color ?? '#0f766e';
$themeSecondary = $configuration->theme_secondary_color ?? '#f59e0b';
$themeRgb = function ($hex, $fallback) {
    $hex = trim((string) $hex);
    if (substr($hex, 0, 1) === '#') {
        $hex = substr($hex, 1);
    }
    if (strlen($hex) === 3) {
        $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
    }
    if (! preg_match('/^[0-9a-fA-F]{6}$/', $hex)) {
        return $fallback;
    }

    return hexdec(substr($hex, 0, 2)) . ', ' . hexdec(substr($hex, 2, 2)) . ', ' . hexdec(substr($hex, 4, 2));
};
$themePrimaryRgb = $themeRgb($themePrimary, '15, 118, 110');
$themeSecondaryRgb = $themeRgb($themeSecondary, '245, 158, 11');
@endphp

<head>
    <meta charset="utf-8" />
    <title>@yield('title') | {{ $configuration->name ?? '' }} </title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta content="Premium Multipurpose " name="description" />
    <meta content="ExamFrame" name="author" />
    @if(isset($configuration->favicon))
    <link rel="shortcut icon" href="{{ asset('storage/' . $configuration->favicon) }}" type="image/x-icon">
    @else
    <link rel="shortcut icon" href="{{ URL::asset('build/images/favicon.ico') }}" type="image/x-icon">
    @endif
    <link href="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/css/select2.min.css" rel="stylesheet" />
    <link href="{{ URL::asset('build/libs/sweetalert2/sweetalert2.min.css') }}" rel="stylesheet" type="text/css" />
    @include('layouts.head-css')
    <style>
        :root {
            --vz-primary: {{ $themePrimary }};
            --vz-primary-rgb: {{ $themePrimaryRgb }};
            --vz-success: {{ $themePrimary }};
            --vz-success-rgb: {{ $themePrimaryRgb }};
            --vz-info: {{ $themeSecondary }};
            --vz-info-rgb: {{ $themeSecondaryRgb }};
            --vz-secondary: {{ $themeSecondary }};
            --vz-secondary-rgb: {{ $themeSecondaryRgb }};
            --vz-warning: {{ $themeSecondary }};
            --vz-warning-rgb: {{ $themeSecondaryRgb }};
            --vz-link-color: {{ $themePrimary }};
            --vz-link-hover-color: {{ $themePrimary }};
            --el-primary: {{ $themePrimary }};
            --el-primary-rgb: {{ $themePrimaryRgb }};
            --el-secondary: {{ $themeSecondary }};
            --el-secondary-rgb: {{ $themeSecondaryRgb }};
            --el-primary-soft: rgba({{ $themePrimaryRgb }}, 0.12);
            --el-secondary-soft: rgba({{ $themeSecondaryRgb }}, 0.14);
            --el-border: #d7e2df;
            --el-muted: #7b8497;
            --el-heading: #111827;
            --el-surface: #ffffff;
            --el-surface-muted: #f8fafc;
            --el-body-bg: #f8fafc;
            --el-radius: 4px;
        }

        .btn-primary,
        .bg-primary,
        .btn-info,
        .bg-info,
        .btn-success,
        .bg-success {
            --vz-btn-bg: var(--el-primary);
            --vz-btn-border-color: var(--el-primary);
            --vz-btn-hover-bg: var(--el-primary);
            --vz-btn-hover-border-color: var(--el-primary);
            background-color: var(--el-primary) !important;
            border-color: var(--el-primary) !important;
            color: var(--theme-button-text, #fff) !important;
        }

        .btn-warning,
        .bg-warning {
            --vz-btn-bg: var(--el-secondary);
            --vz-btn-border-color: var(--el-secondary);
            --vz-btn-hover-bg: var(--el-secondary);
            --vz-btn-hover-border-color: var(--el-secondary);
            background-color: var(--el-secondary) !important;
            border-color: var(--el-secondary) !important;
        }

        .btn-outline-primary,
        .btn-outline-info,
        .btn-outline-success {
            --vz-btn-color: var(--el-primary);
            --vz-btn-border-color: var(--el-primary);
            --vz-btn-hover-bg: var(--el-primary);
            --vz-btn-hover-border-color: var(--el-primary);
            --vz-btn-hover-color: var(--theme-button-text, #fff);
            color: var(--el-primary) !important;
            border-color: var(--el-primary) !important;
        }

        .btn-outline-primary:hover,
        .btn-outline-info:hover,
        .btn-outline-success:hover,
        .btn-outline-primary:focus,
        .btn-outline-info:focus,
        .btn-outline-success:focus {
            background-color: var(--el-primary) !important;
            border-color: var(--el-primary) !important;
            color: var(--theme-button-text, #fff) !important;
        }

        .text-primary,
        .text-info,
        .text-success {
            color: var(--el-primary) !important;
        }

        .text-warning {
            color: var(--el-secondary) !important;
        }
    </style>
</head>

<body class="@yield('body-class')">
    <div id="layout-wrapper">
        @yield('content')
    </div>
    @include('layouts.vendor-scripts')
    <script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/js/select2.min.js"></script>
    <script src="{{ URL::asset('build/libs/sweetalert2/sweetalert2.min.js') }}"></script>
    <script>
        window.alert = function (message) {
            return Swal.fire({ icon: 'warning', title: 'Notice', text: String(message ?? '') });
        };
    </script>
    <script src="{{ URL::asset('build/js/app.js') }}"></script>


    {{-- ✅✅✅ YEH LINE MISSING THI ✅✅✅ --}}
    @stack('scripts')
    {{-- ✅✅✅ YEH LINE MISSING THI ✅✅✅ --}}
    
</body>

</html>

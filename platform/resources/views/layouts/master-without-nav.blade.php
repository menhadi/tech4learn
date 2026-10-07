<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-topbar="light">
@php
$configuration = getConfiguration();
$themePrimary = $configuration->theme_primary_color ?? '#0f766e';
$themeSecondary = $configuration->theme_secondary_color ?? '#f59e0b';
$themeRgb = static function ($hex, $fallback) {
    $hex = trim((string) $hex, '#');
    if (strlen($hex) === 3) {
        $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
    }
    if (!preg_match('/^[0-9a-fA-F]{6}$/', $hex)) {
        return $fallback;
    }
    return hexdec(substr($hex, 0, 2)).', '.hexdec(substr($hex, 2, 2)).', '.hexdec(substr($hex, 4, 2));
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
    <!-- App favicon -->
    @if(isset($configuration->favicon))
    <link rel="shortcut icon" href="{{ asset('storage/' . $configuration->favicon) }}" type="image/x-icon">
    @else
    <link rel="shortcut icon" href="{{ URL::asset('build/images/favicon.ico') }}" type="image/x-icon">
    @endif
    @include('layouts.head-css')
    <style>
        :root {
            --vz-primary: {{ $themePrimary }};
            --vz-primary-rgb: {{ $themePrimaryRgb }};
            --vz-secondary: {{ $themeSecondary }};
            --vz-secondary-rgb: {{ $themeSecondaryRgb }};
            --el-primary: {{ $themePrimary }};
            --el-primary-rgb: {{ $themePrimaryRgb }};
            --el-secondary: {{ $themeSecondary }};
            --el-secondary-rgb: {{ $themeSecondaryRgb }};
            --el-primary-soft: rgba({{ $themePrimaryRgb }}, .12);
            --el-secondary-soft: rgba({{ $themeSecondaryRgb }}, .14);
            --el-bg: #f7faf9;
            --el-text: #0f172a;
            --el-muted: #64748b;
            --el-border: #d7e2df;
        }

        .auth-page-wrapper {
            background:
                radial-gradient(circle at 14% 12%, rgba(var(--el-primary-rgb), .14), transparent 30%),
                radial-gradient(circle at 86% 90%, rgba(var(--el-secondary-rgb), .12), transparent 32%),
                linear-gradient(135deg, var(--el-bg), #eef7f5);
            color: var(--el-text);
            padding: 20px;
        }

        .auth-themed-card {
            background: rgba(255, 255, 255, .98);
            border: 1px solid rgba(var(--el-primary-rgb), .16) !important;
            border-radius: 24px;
            box-shadow: 0 16px 40px rgba(15, 23, 42, .10);
        }

        .auth-themed-header {
            background: var(--el-primary);
            color: #fff;
            padding: 25px;
            text-align: center;
        }

        .auth-themed-header a,
        .auth-themed-header a:hover,
        .auth-themed-header a:focus {
            color: #fff;
            text-decoration: none;
        }

        .auth-themed-title {
            color: #fff;
            font-size: 1.5rem;
            font-weight: 800;
            margin: 0;
        }

        .auth-themed-subtitle {
            color: #fff;
            font-size: .82rem;
            margin: 8px 0 0;
            opacity: .9;
        }

        .auth-themed-copy {
            color: var(--el-muted);
            font-size: .85rem;
        }

        .auth-themed-label {
            color: var(--el-text);
            font-size: .78rem;
            font-weight: 700;
        }

        .auth-themed-icon {
            color: var(--el-primary);
            font-size: 14px;
            left: 14px;
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            z-index: 1;
        }

        .auth-themed-input {
            background: #f8fafc;
            border: 2px solid rgba(var(--el-primary-rgb), .16);
            border-radius: 12px;
            font-size: 14px;
            height: 44px;
            padding: 12px 14px 12px 42px;
        }

        .auth-themed-input:focus {
            background: #fff;
            border-color: var(--el-primary);
            box-shadow: 0 0 0 3px rgba(var(--el-primary-rgb), .12);
        }

        .auth-themed-submit {
            background: var(--el-primary);
            border: 1px solid var(--el-primary);
            border-radius: 50px;
            box-shadow: 0 8px 18px rgba(var(--el-primary-rgb), .22);
            color: #fff;
            font-size: .92rem;
            font-weight: 800;
            height: 44px;
            padding: 11px;
        }

        .auth-themed-submit:hover,
        .auth-themed-submit:focus {
            background: var(--el-secondary);
            border-color: var(--el-secondary);
            color: #fff;
        }

        .auth-themed-link {
            color: var(--el-primary);
            font-size: .82rem;
            font-weight: 700;
            text-decoration: none;
        }

        .auth-themed-link:hover,
        .auth-themed-link:focus {
            color: var(--el-secondary);
            text-decoration: none;
        }

        .auth-themed-footer {
            color: var(--el-muted);
            font-size: .7rem;
        }
    </style>
</head>

@yield('body')

@yield('content')

@include('layouts.vendor-scripts')
</body>

</html>

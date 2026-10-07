<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-layout="vertical" data-topbar="light" data-sidebar="dark"
    data-sidebar-size="lg" data-sidebar-image="none" data-preloader="disable">

<head>
    <meta charset="utf-8" />
    <title>@yield('title') | Exam Frame Installer</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta content="Exam Frame Installation" name="description" />
    <meta content="Skill Stride Ventures Pvt. Ltd." name="author" />
    @if (isset($configuration->favicon))
        {{-- YEH LINE FIX KAR DI HAI --}}
        <link rel="shortcut icon" href="{{ asset('storage/' . $configuration->favicon) }}" type="image/x-icon">
    @else
        <link rel="shortcut icon" href="{{ URL::asset('build/images/favicon.ico') }}" type="image/x-icon">
    @endif
    <link href="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/css/select2.min.css" rel="stylesheet" />
    <link href="{{ URL::asset('build/libs/sweetalert2/sweetalert2.min.css') }}" rel="stylesheet" type="text/css" />
    @include('layouts.head-css')

    {{-- MODERN UI KE LIYE CUSTOM CSS --}}
    <style>
        body {
            background-color: #f3f4f6; /* Modern light gray background */
        }

        .installer-container {
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 2rem 1rem;
        }

        .installer-card {
            border: none;
            border-radius: 0.75rem; /* Softer edges */
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.05), 0 5px 10px rgba(0, 0, 0, 0.02);
            width: 100%;
        }

        /* Floating label ke andar password toggle button ke liye */
        .password-toggle-btn {
            position: absolute;
            top: 0;
            right: 0;
            height: 100%;
            z-index: 5;
            border: 0;
            background: transparent;
            color: #6c757d;
            padding-right: 1.25rem;
            display: flex;
            align-items: center;
        }
        .password-toggle-btn:focus {
            outline: none;
            box-shadow: none;
        }

        .license-content {
            height: 400px;
            overflow-y: auto;
            border: 1px solid #dee2e6;
            background-color: #f8f9fa;
            padding: 1rem;
            border-radius: 0.25rem;
            font-family: monospace;
            font-size: 0.85rem;
            white-space: pre-wrap; /* Text wrap ke liye */
        }

        .trust-footer {
            font-size: 0.9rem;
            color: #6c757d;
        }
        .trust-footer a {
            color: #495057;
            text-decoration: none;
            font-weight: 500;
        }
        .trust-footer a:hover {
            text-decoration: underline;
        }
        .trust-badge {
            font-size: 0.8rem;
            font-weight: 600;
            padding: 0.25rem 0.6rem;
            border-radius: 20px;
            background-color: #e0e7ff;
            color: #4338ca;
            margin: 0 0.25rem;
        }
    </style>
</head>

@section('body')

    <body>
    @show
    <div id="layout-wrapper">
        {{-- Yield content ko installer-container me wrap kiya --}}
        <div class="installer-container">
            @yield('content')
        </div>
        </div>
    @include('layouts.vendor-scripts')
    <script src="{{ URL::asset('build/libs/sweetalert2/sweetalert2.min.js') }}"></script>

    @yield('script') {{-- Child pages me specific script ke liye --}}

</body>

</html>
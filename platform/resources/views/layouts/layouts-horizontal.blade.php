<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-layout="horizontal" data-topbar="light"
    data-sidebar="dark" data-sidebar-size="lg">
@php
$configuration = getConfiguration();
@endphp

<head>
    <meta charset="utf-8" />
    <title> @yield('title')| {{ $configuration->name ?? '' }}</title>
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
</head>

<body>

    <!-- Begin page -->
    <div id="layout-wrapper">
        @include('layouts.topbar')
        @include('layouts.sidebar')
        <!-- ============================================================== -->
        <!-- Start right Content here -->
        <!-- ============================================================== -->
        <div class="main-content">
            <div class="page-content">
                <!-- Start content -->
                <div class="container-fluid">
                    @yield('content')
                </div> <!-- content -->
            </div>
            @include('layouts.footer')
        </div>
        <!-- ============================================================== -->
        <!-- End Right content here -->
        <!-- ============================================================== -->
    </div>
    <!-- END wrapper -->

    <!-- Right Sidebar -->

    <!-- END Right Sidebar -->

    @include('layouts.vendor-scripts')
</body>

</html>
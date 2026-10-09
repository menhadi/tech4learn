@extends('layouts.master')
@php
    $workspaceTitle = request()->routeIs('attendance.*') ? 'Daily attendance' : match(request('view')) {
        'centres' => 'Centres',
        'structure' => 'Classes and sections',
        default => 'Student enrolment',
    };
@endphp
@section('title', $workspaceTitle)
@section('content')
@component('components.breadcrumb')
@slot('li_1', 'Enrolment & Attendance')
@slot('title', $workspaceTitle)
@endcomponent
<div id="foundation-attendance"><p role="status">Loading workspace…</p></div>
<noscript><p>Enable JavaScript to capture and review attendance.</p></noscript>
@endsection
@push('scripts')
@foreach(($attendanceAssets['css'] ?? []) as $css)
<link rel="stylesheet" href="{{ asset('attendance-ui/'.$css) }}">
@endforeach
<script type="module" src="{{ asset('attendance-ui/'.$attendanceAssets['file']) }}"></script>
@endpush

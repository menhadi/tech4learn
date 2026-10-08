@extends('layouts.master')
@section('title', request()->routeIs('enrolment.*') ? 'Student enrolment' : 'Attendance')
@section('content')
<div id="foundation-attendance"><p role="status">Loading attendance…</p></div>
<noscript><p>Enable JavaScript to capture and review attendance.</p></noscript>
@endsection
@push('scripts')
@foreach(($attendanceAssets['css'] ?? []) as $css)
<link rel="stylesheet" href="{{ asset('attendance-ui/'.$css) }}">
@endforeach
<script type="module" src="{{ asset('attendance-ui/'.$attendanceAssets['file']) }}"></script>
@endpush

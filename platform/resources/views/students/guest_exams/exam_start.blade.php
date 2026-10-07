@extends('students.layouts.simple')
@section('title', $exam->name)
{{-- Remove bg-white if you want default background --}}
{{-- @section('body-class', 'bg-white') --}}

{{-- ✅ ADDED: Inline styles for full height layout --}}
@section('content')
{{-- ✅ MODIFIED: Added exam-wrapper div --}}
<div class="exam-wrapper">
    @if(session('error') || session('warning'))
    <div class="alert alert-{{ session('error') ? 'danger' : 'warning' }} alert-dismissible shadow fade show" role="alert" style="flex-shrink: 0;">
        {{ session('error') ?: session('warning') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="{{ __('messages.my_exams_modal_close_button') }}"></button>
    </div>
    @endif

    {{-- ✅ MODIFIED: Added exam-header class --}}
    <div class="exam-header">
        @include('students.guest_exams.partials.header')
    </div>

    {{-- ✅ MODIFIED: Changed class to exam-main-row, removed margin-top --}}
    <div class="row exam-main-row">
        {{-- Includes will now be direct children of the flex row --}}
        @include('students.guest_exams.partials.questions')
        @include('students.guest_exams.partials.sidebar')
    </div>
</div>

@include('students.guest_exams.partials.modals')
@include('students.guest_exams.partials.scripts')

@endsection

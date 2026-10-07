@extends('students.layouts.app')

@section('title') Quick Quizzes @endsection

@section('content')
@component('components.breadcrumb')
    @slot('li_1') Student @endslot
    @slot('title') Quick Quizzes @endslot
@endcomponent

<div class="student-quick-quiz-page">
    @include('website.partials.quick-quiz', [
        'quizGroups' => $quizGroups,
        'quickQuizDefaultGroupId' => $quickQuizDefaultGroupId,
        'quickQuizPageMode' => true,
    ])
</div>
@endsection

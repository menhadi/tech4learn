@extends('students.layouts.simple')
@section('title') Exam Instructions @endsection

@section('content')
@include('students.exams.shared.instructions_content', ['mode' => 'student'])
@endsection

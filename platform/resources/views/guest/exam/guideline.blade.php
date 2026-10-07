@extends('website.layouts.app')

@section('title', $exam->name . ' - Exam Guidelines')
@section('meta_description', 'Guidelines for ' . $exam->name)

@section('content')
<div class="container py-4">
    <div class="row">
        <div class="col-md-8 mx-auto">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h1 class="h3 mb-0">{{ $exam->name }}</h1>
                </div>
                <div class="card-body">
                    <h4>Exam Guidelines</h4>
                    <ul>
                        <li>Read each question carefully</li>
                        <li>Select the best answer option</li>
                        <li>You can navigate between questions</li>
                        <li>Submit only when you're finished</li>
                    </ul>
                    <a href="/guest/exam/instructions/{{ $exam->slug }}" class="btn btn-primary">
                        Proceed to Instructions
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

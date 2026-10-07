@extends('layouts.master') {{-- Make sure this points to your admin layout file (e.g., admin.layouts.app) --}}

@section('title', 'Exam Feedback')

@section('content')
@component('components.breadcrumb')
    @slot('li_1', 'Results')
    @slot('title', 'View Feedback')
@endcomponent

<div class="container-fluid">

    {{-- Page Title - Agar aapke layout mein breadcrumb nahi hai toh isko uncomment kar sakte hain --}}
    {{--
    <div class="row">
        <div class="col-12">
            <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                <h4 class="mb-sm-0">Exam Feedback</h4>
                <div class="page-title-right">
                    <ol class="breadcrumb m-0">
                        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li> {{-- Adjust route name if needed --}}
                        {{-- <li class="breadcrumb-item"><a href="{{ route('results.index') }}">Results</a></li>
                        <li class="breadcrumb-item active">Feedback</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>
    --}}

    <div class="row">
        <div class="col-lg-12">
            <div class="card">
                <div class="card-header align-items-center d-flex">
                    <h5 class="card-title mb-0 flex-grow-1">Feedback for Exam: {{ $examResult->exam->name ?? 'N/A' }} (Student: {{ $examResult->student->name ?? 'N/A' }})</h5>
                    <div class="flex-shrink-0">
                         <a href="{{ route('results.view', $examResult->id) }}" class="btn btn-secondary btn-sm"><i class="mdi mdi-arrow-left"></i> Back to Result</a>
                    </div>
                </div>
                <div class="card-body">
                    <p class="text-muted mb-3">
                        Result ID: {{ $examResult->id }} |
                        Submitted on: {{ $examResult->end_time ? \Carbon\Carbon::parse($examResult->end_time)->format('d M Y, h:i A') : 'N/A' }}
                    </p>

                    @if($feedbacks->isEmpty())
                        <div class="alert alert-warning text-center" role="alert">
                            <i class="mdi mdi-alert-circle-outline me-2"></i> No feedback submitted for this exam result.
                        </div>
                    @else
                        {{-- Agar ek se zyada feedback hain (rare case), toh loop chalega --}}
                        @foreach($feedbacks as $feedback)
                            <div class="table-responsive mb-3 border rounded p-3 @if(!$loop->last) border-bottom-0 rounded-bottom-0 @endif">
                                <h6 class="mb-3">Feedback Entry #{{ $feedback->id }} (Submitted at: {{ $feedback->created_at ? \Carbon\Carbon::parse($feedback->created_at)->format('d M Y, h:i A') : 'N/A' }})</h6>
                                <table class="table table-bordered table-sm table-striped" style="width: 100%;">
                                    <tbody>
                                        <tr>
                                            <th class="bg-light" style="width: 30%;">Test Instructions Clarity</th>
                                            <td>{{ $feedback->test_instructions ?? 'N/A' }}</td>
                                        </tr>
                                        <tr>
                                            <th class="bg-light">Language of Questions Appropriate</th>
                                            <td>{{ $feedback->language_of_questions ?? 'N/A' }}</td>
                                        </tr>
                                        <tr>
                                            <th class="bg-light">Overall Test Experience</th>
                                            <td>{{ $feedback->text_experience ?? 'N/A' }}</td>
                                        </tr>
                                        <tr>
                                            <th class="bg-light">Feedback Comment</th>
                                            <td>
                                                @if($feedback->feedback)
                                                    {!! nl2br(e($feedback->feedback)) !!} {{-- nl2br ensures line breaks are shown --}}
                                                @else
                                                    N/A
                                                @endif
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        @endforeach
                    @endif

                </div></div></div></div></div> @endsection

@section('script')
    {{-- Agar is page ke liye koi specific JavaScript chahiye toh yahan add karein --}}
@endsection
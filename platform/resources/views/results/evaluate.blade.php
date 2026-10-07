@extends('layouts.master')

@section('title', 'Manual Evaluation')

@section('css')
<style>
    .question-card {
        border: 1px solid #e9ebec;
        border-radius: 8px;
        background: #fff;
        margin-bottom: 20px;
        box-shadow: 0 1px 2px rgba(56, 65, 74, 0.05);
    }
    .q-header {
        background: #f8f9fa;
        padding: 15px;
        border-bottom: 1px solid #e9ebec;
        display: flex; justify-content: space-between; align-items: center;
    }
    .q-body { padding: 20px; }
    .student-answer-box {
        background: #f3f6f9;
        border-left: 4px solid #405189;
        padding: 15px;
        margin-top: 10px;
        border-radius: 4px;
    }
    .correct-answer-box {
        background: #d1fae5;
        border-left: 4px solid #0ab39c;
        padding: 15px;
        margin-top: 10px;
        border-radius: 4px;
        font-size: 0.9em;
        color: #064e3b;
    }
    .marks-input {
        max-width: 150px;
        font-weight: bold;
        font-size: 1.1em;
        text-align: center;
    }
</style>
@endsection

@section('content')
@component('components.breadcrumb')
    @slot('li_1', 'Results')
    @slot('title', 'Evaluate Subjective Answers')
@endcomponent

<div class="row">
    <div class="col-12">
        {{-- Header Info --}}
        <div class="card mb-3">
            <div class="card-body d-flex justify-content-between align-items-center">
                <div>
                    @php
                        $student = optional($result->student);
                        $exam = optional($result->exam);
                    @endphp
                    <h5 class="mb-1">
                        Student: {{ $student->name ?: 'Student record missing' }}
                        <span class="text-muted fs-14">({{ $student->enroll ?: 'No enrollment' }})</span>
                    </h5>
                    <p class="mb-0 text-muted">Exam: {{ $exam->name ?: 'Exam record missing' }}</p>
                </div>
                <div>
                    <a href="{{ route('results.index') }}" class="btn btn-light"><i class="ri-arrow-left-line me-1"></i> Back</a>
                </div>
            </div>
        </div>

        {{-- Evaluation Form --}}
        @if($questions->isEmpty())
            <div class="alert alert-success text-center p-4">
                <i class="ri-check-double-line display-4 text-success"></i>
                <h5 class="mt-2">No Pending Questions!</h5>
                <p>All subjective questions have been evaluated for this student.</p>
                <a href="{{ route('results.view', $result->id) }}" class="btn btn-primary mt-2">View Final Report</a>
            </div>
        @else
            <form action="{{ route('results.saveEvaluation', $result->id) }}" method="POST">
                @csrf
                
                @foreach($questions as $index => $stat)
                <div class="question-card">
                    <div class="q-header">
                        <h6 class="mb-0">Question #{{ $loop->iteration }}</h6>
                        <span class="badge bg-warning text-dark">Max Marks: {{ $stat->marks }}</span>
                    </div>
                    <div class="q-body">
                        {{-- Question Text --}}
                        <div class="mb-3">
                            <h6 class="fw-bold text-muted text-uppercase fs-11">QUESTION</h6>
                            <div class="fs-15 text-dark">{!! optional($stat->question)->question ?: '<span class="text-muted">Question record missing</span>' !!}</div>
                        </div>

                        <div class="row">
                            {{-- Answers Column --}}
                            <div class="col-md-8">
                                {{-- Student Answer --}}
                                <div class="mb-3">
                                    <h6 class="fw-bold text-primary text-uppercase fs-11">STUDENT'S ANSWER</h6>
                                    <div class="student-answer-box">
                                        @if($stat->answer)
                                            {!! nl2br(e($stat->answer)) !!}
                                        @else
                                            <span class="text-muted fst-italic">Student did not attempt this question.</span>
                                        @endif
                                    </div>
                                </div>

                                {{-- Reference Answer (Fix Applied Here) --}}
                                @if($stat->correct_answer)
                                <div class="mb-3">
                                    <h6 class="fw-bold text-success text-uppercase fs-11">REFERENCE / CORRECT ANSWER</h6>
                                    <div class="correct-answer-box">
                                        @php
                                            $rawAns = $stat->correct_answer;
                                            // Decode JSON if it looks like JSON array [null, "val", ...]
                                            $decoded = json_decode($rawAns, true);
                                            $finalDisplay = '';

                                            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                                                // Filter out nulls and empty strings
                                                $filtered = array_filter($decoded, function($val) {
                                                    return !is_null($val) && $val !== '';
                                                });
                                                // Join array elements nicely
                                                $finalDisplay = implode(', ', $filtered);
                                            } else {
                                                // If not JSON, use raw string
                                                $finalDisplay = $rawAns;
                                            }
                                        @endphp
                                        
                                        {{-- Render HTML tags properly --}}
                                        {!! $finalDisplay !!}
                                    </div>
                                </div>
                                @endif
                            </div>

                            {{-- Grading Column --}}
                            <div class="col-md-4 border-start">
                                <div class="p-3 bg-light h-100 rounded text-center d-flex flex-column justify-content-center">
                                    <label class="form-label fw-bold">Assign Marks</label>
                                    <div class="d-flex justify-content-center">
                                        <input type="number" 
                                               name="marks[{{ $stat->id }}]" 
                                               class="form-control marks-input" 
                                               step="0.5" 
                                               min="0" 
                                               max="{{ $stat->marks }}" 
                                               required 
                                               placeholder="0.0">
                                    </div>
                                    <div class="form-text mt-2">Enter marks between 0 and {{ $stat->marks }}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                @endforeach

                {{-- Action Bar --}}
                <div class="card bg-primary text-white">
                    <div class="card-body d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="text-white mb-1">Finish Evaluation</h5>
                            <p class="mb-0 text-white-50">Clicking save will update the result status from 'Pending' to 'Pass/Fail'.</p>
                        </div>
                        <button type="submit" class="btn btn-light btn-lg fw-bold text-primary">
                            <i class="ri-save-3-line me-1"></i> Save & Publish Result
                        </button>
                    </div>
                </div>

            </form>
        @endif
    </div>
</div>
@endsection

@section('script')
<script>
    // Initialize MathJax for rendering equations in questions/answers
    window.MathJax = { loader: { load: ['[tex]/mhchem'] }, tex: { packages: {'[+]': ['mhchem']}, inlineMath: [['$', '$'], ['\\(', '\\)']], displayMath: [['\\[', '\\]'], ['$$', '$$']], processEscapes: true }, svg: { fontCache: 'global' } };
</script>
<script id="MathJax-script" async src="https://cdn.jsdelivr.net/npm/mathjax@3/es5/tex-mml-chtml.js"></script>
@endsection

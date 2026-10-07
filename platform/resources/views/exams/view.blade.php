@extends('layouts.master')

@section('title', 'View Exam')

@section('content')
@component('components.breadcrumb')
@slot('li_1', 'Exams')
@slot('title', 'View Exam')
@endcomponent

<div class="row">
    <div class="col-lg-12">
        <div class="card">
            <div class="card-header">
                <h4 class="card-title mb-0">Exam Details</h4>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <tbody>
                            <tr>
                                <th>Exam Name:</th>
                                <td>{{ $exam->name }}</td>
                                <th>Passing Percentage:</th>
                                <td>{{ $exam->passing_percentage }}</td>
                            </tr>
                            <tr>
                                <th>Math Editor:</th>
                                <td>{{ $exam->math_editor ? 'Yes' : 'No' }}</td>
                                <th>Start Date:</th>
                                <td>{{ $exam->start_date }}</td>
                            </tr>
                            <tr>
                                <th>End Date:</th>
                                <td>{{ $exam->end_date }}</td>
                                <th>Show Answer Sheet:</th>
                                <td>{{ $exam->show_answer_sheet ? 'Yes' : 'No' }}</td>
                            </tr>
                            <tr>
                                <th>Browser Tolerance:</th>
                                <td>{{ $exam->browser_tolerance ? 'Yes' : 'No' }}</td>
                                <th>Result After Finish:</th>
                                <td>{{ $exam->result_after_finish ? 'Yes' : 'No' }}</td>
                            </tr>
                            <tr>
                                <th>Mode:</th>
                                <td>{{ $exam->mode }}</td>
                                <th>Duration:</th>
                                <td>{{ $exam->duration == 0 ? 'Unlimited' : $exam->duration }}</td>
                            </tr>
                            <tr>
                                <th>Multi Language:</th>
                                <td>{{ $exam->multi_language ? 'Yes' : 'No' }}</td>
                                <th>Instant Result:</th>
                                <td>{{ $exam->instant_result ? 'Yes' : 'No' }}</td>
                            </tr>
                            <tr>
                                <th>Option Shuffle:</th>
                                <td>{{ $exam->option_shuffle ? 'Yes' : 'No' }}</td>
                                <th>Attempt Count:</th>
                                <td>{{ $exam->attempt_count == 0 ? 'Unlimited' : $exam->attempt_count }}</td>
                            </tr>
                            <tr>
                                <th>Answer Changes:</th>
                                <td>{{ ($exam->allow_answer_change ?? true) ? 'Allowed until submission' : 'Locked after leaving a saved question' }}</td>
                                <th></th><td></td>
                            </tr>
                            <tr>
                                <th>Packages:</th>
                                <td colspan="3">{{ $exam->packages_list }}</td>
                            </tr>
                            <tr>
                                <th>Groups:</th>
                                <td colspan="3">{{ $exam->groups->pluck('group_name')->implode(' | ') }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="d-flex justify-content-end">
                    <a href="{{ route('exams.index') }}" class="btn btn-light">Back</a>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
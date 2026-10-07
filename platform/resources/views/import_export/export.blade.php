@extends('layouts.master')
@section('title', 'Import/Export Questions')

@section('css')
<link href="{{ URL::asset('build/libs/sweetalert2/sweetalert2.min.css') }}" rel="stylesheet" type="text/css" />
<link href="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/css/select2.min.css" rel="stylesheet" />
<style>
    .dropdown-column {
        margin-bottom: 15px;
    }
</style>
@endsection

@section('content')
@component('components.breadcrumb')
@slot('li_1', '')
@slot('title', 'Export Questions')
@endcomponent

<div class="row">
    <div class="col-lg-12">
        <div class="card">
            <div class="card-header">
                <h4 class="card-title mb-0">Export Questions</h4>
                <div class="row mt-3">
                    <div class="col-sm-auto">
                        <div>
                            <a href="{{ route('questions.index') }}" class="btn btn-secondary add-btn">
                                <i class="ri-arrow-left-line align-bottom me-1"></i> Back to Questions
                            </a>
                            <a href="{{ route('questions.importExport') }}" class="btn btn-info add-btn">
                                <i class="ri-file-upload-line align-bottom me-1"></i> Import Questions
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <div class="row mt-3">
                    <div class="col-12 dropdown-column">
                        <label for="subject">Select Subject</label>
                        <select id="subject" class="form-control select2">
                            <option value="">Select Subject</option>
                            @foreach($subjects as $subject)
                            <option value="{{ $subject->id }}">{{ $subject->subject_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12 dropdown-column">
                        <label for="topic">Select Topic</label>
                        <select id="topic" class="form-control select2" disabled>
                            <option value="">Select Topic</option>
                        </select>
                    </div>
                    <div class="col-12 dropdown-column">
                        <label for="subtopic">Select Subtopic</label>
                        <select id="subtopic" class="form-control select2" disabled>
                            <option value="">Select Subtopic</option>
                        </select>
                    </div>
                </div>
                <div class="col-12 dropdown-column">
                    <button id="export-btn" class="btn btn-primary">Export Questions</button>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('script')
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="{{ URL::asset('build/libs/sweetalert2/sweetalert2.min.js') }}"></script>

<script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/js/select2.min.js"></script>
<script>
    $(document).ready(function() {
        $('.select2').select2();

        $('#subject').change(function() {
            var subjectId = $(this).val();
            if (subjectId) {
                $.ajax({
                    url: '/get-topics-by-subject/' + subjectId,
                    type: 'GET',
                    success: function(data) {
                        $('#topic').empty().append('<option value="">Select Topic</option>');
                        $.each(data, function(key, value) {
                            $('#topic').append('<option value="' + key + '">' + value + '</option>');
                        });
                        $('#topic').prop('disabled', false);
                    }
                });
            } else {
                $('#topic').empty().append('<option value="">Select Topic</option>').prop('disabled', true);
                $('#subtopic').empty().append('<option value="">Select Subtopic</option>').prop('disabled', true);
            }
        });

        $('#topic').change(function() {
            var topicId = $(this).val();
            if (topicId) {
                $.ajax({
                    url: '/get-subtopics-by-topic/' + topicId,
                    type: 'GET',
                    success: function(data) {
                        $('#subtopic').empty().append('<option value="">Select Subtopic</option>');
                        $.each(data, function(key, value) {
                            $('#subtopic').append('<option value="' + key + '">' + value + '</option>');
                        });
                        $('#subtopic').prop('disabled', false);
                    }
                });
            } else {
                $('#subtopic').empty().append('<option value="">Select Subtopic</option>').prop('disabled', true);
            }
        });
        
        $('#export-btn').click(function() {
            var subjectId = $('#subject').val();
            var topicId = $('#topic').val();
            var subtopicId = $('#subtopic').val();

            if (subjectId && topicId && subtopicId) {
                window.location.href = '/export-questions/' + subjectId + '/' + topicId + '/' + subtopicId;
            } else {
                alert('Please select subject, topic, and subtopic.');
            }
        });
    });
</script>
@endsection
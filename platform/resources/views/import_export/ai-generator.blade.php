@extends('layouts.master')
@section('title', 'AI Question Generator')

@section('content')
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h4>AI Question Generator</h4>
                <p class="text-muted mb-0">Generate questions using AI based on your curriculum</p>
            </div>
            <div class="card-body">
                @if(session('success'))
                    <div class="alert alert-success">{{ session('success') }}</div>
                @endif
                @if(session('error'))
                    <div class="alert alert-danger">{{ session('error') }}</div>
                @endif

                <form action="{{ route('ai.generator.run') }}" method="POST" id="aiGeneratorForm">
                    @csrf
                    
                    <div class="form-group">
                        <label>Groups <span class="text-danger">*</span></label>
                        <select name="group_ids[]" multiple class="form-control select2" id="group_select">
                            @foreach($groups as $group)
                                <option value="{{ $group->id }}">{{ $group->group_name }}</option>
                            @endforeach
                        </select>
                        <small class="text-muted">Select groups where questions will be available</small>
                    </div>

                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Subject</label>
                                <select name="subject_id" id="subject_select" class="form-control select2">
                                    <option value="">Select Subject (Optional)</option>
                                    @foreach($subjects as $subject)
                                        <option value="{{ $subject->id }}">{{ $subject->subject_name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Topic <span class="text-muted">(Optional)</span></label>
                                <select name="topic_id" id="topic_select" class="form-control select2">
                                    <option value="">Select Topic</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Sub Topic <span class="text-muted">(Optional)</span></label>
                                <select name="stopic_id" id="subtopic_select" class="form-control select2">
                                    <option value="">Select Sub Topic</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Main Topic / Subject Matter <span class="text-danger">*</span></label>
                        <input type="text" name="main_topic" class="form-control" placeholder="e.g., Newton's Laws of Motion, Photosynthesis, PHP Arrays">
                        <small class="text-muted">Be specific for better quality questions</small>
                    </div>

                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Question Type <span class="text-danger">*</span></label>
                                <select name="qtype_id" class="form-control select2">
                                    <option value="">Select Type</option>
                                    @foreach($qtypes as $qtype)
                                        <option value="{{ $qtype->id }}">{{ $qtype->question_type }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Difficulty Level</label>
                                <select name="diff_id" class="form-control select2">
                                    <option value="">Select Difficulty (Optional)</option>
                                    @foreach($diffs as $diff)
                                        <option value="{{ $diff->id }}">{{ $diff->diff_level }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Language <span class="text-danger">*</span></label>
                                <select name="language_id" class="form-control select2">
                                    <option value="">Select Language</option>
                                    @foreach($languages as $language)
                                        <option value="{{ $language->id }}">{{ $language->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Number of Questions</label>
                                <input type="number" name="num_questions" class="form-control" value="5" min="1" max="10">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Marks per Question</label>
                                <input type="number" name="marks" class="form-control" value="1" step="0.5">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Negative Marks</label>
                                <input type="number" name="negative_marks" class="form-control" value="0" step="0.5">
                            </div>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary">Generate Questions</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@section('script')
<script>
    $(document).ready(function() {
        $('.select2').select2({ placeholder: "Select", allowClear: true });
        
        $('#group_select').on('change', function() {
            var groupIds = $(this).val();
            var subjectSelect = $('#subject_select');
            var topicSelect = $('#topic_select');
            var subtopicSelect = $('#subtopic_select');
            
            topicSelect.html('<option value="">Select Topic</option>');
            subtopicSelect.html('<option value="">Select Sub Topic</option>');
            
            if (groupIds && groupIds.length > 0) {
                subjectSelect.html('<option value="">Loading...</option>').prop('disabled', true);
                
                $.ajax({
                    url: '{{ route("subjects.by.groups") }}',
                    type: 'POST',
                    data: { group_ids: groupIds, _token: '{{ csrf_token() }}' },
                    success: function(response) {
                        subjectSelect.html('<option value="">Select Subject (Optional)</option>');
                        if (response.length > 0) {
                            $.each(response, function(key, subject) {
                                subjectSelect.append('<option value="' + subject.id + '">' + subject.subject_name + '</option>');
                            });
                            subjectSelect.prop('disabled', false);
                        } else {
                            subjectSelect.html('<option value="">No subjects found</option>').prop('disabled', true);
                        }
                        subjectSelect.trigger('change.select2');
                    }
                });
            } else {
                subjectSelect.html('<option value="">Select Subject (Optional)</option>').prop('disabled', false);
                @foreach($subjects as $subject)
                subjectSelect.append('<option value="{{ $subject->id }}">{{ $subject->subject_name }}</option>');
                @endforeach
                subjectSelect.trigger('change.select2');
            }
        });
        
        $('#subject_select').on('change', function() {
            var subjectId = $(this).val();
            var topicSelect = $('#topic_select');
            var subtopicSelect = $('#subtopic_select');
            
            subtopicSelect.html('<option value="">Select Sub Topic</option>');
            
            if (subjectId) {
                topicSelect.html('<option value="">Loading...</option>').prop('disabled', true);
                
                $.ajax({
                    url: '{{ route("topics.by.subject") }}',
                    type: 'POST',
                    data: { subject_id: subjectId, group_ids: $('#group_select').val() || [], _token: '{{ csrf_token() }}' },
                    success: function(response) {
                        topicSelect.html('<option value="">Select Topic</option>');
                        if (response.length > 0) {
                            $.each(response, function(key, topic) {
                                topicSelect.append('<option value="' + topic.id + '">' + topic.name + '</option>');
                            });
                            topicSelect.prop('disabled', false);
                        } else {
                            topicSelect.html('<option value="">No topics found</option>').prop('disabled', true);
                        }
                        topicSelect.trigger('change.select2');
                    }
                });
            } else {
                topicSelect.html('<option value="">Select Topic</option>').prop('disabled', false);
                topicSelect.trigger('change.select2');
            }
        });
        
        $('#topic_select').on('change', function() {
            var topicId = $(this).val();
            var subtopicSelect = $('#subtopic_select');
            
            if (topicId) {
                subtopicSelect.html('<option value="">Loading...</option>').prop('disabled', true);
                
                $.ajax({
                    url: '{{ route("subtopics.by.topic") }}',
                    type: 'POST',
                    data: { topic_id: topicId, _token: '{{ csrf_token() }}' },
                    success: function(response) {
                        subtopicSelect.html('<option value="">Select Sub Topic</option>');
                        if (response.length > 0) {
                            $.each(response, function(key, subtopic) {
                                subtopicSelect.append('<option value="' + subtopic.id + '">' + subtopic.name + '</option>');
                            });
                            subtopicSelect.prop('disabled', false);
                        } else {
                            subtopicSelect.html('<option value="">No subtopics found</option>').prop('disabled', true);
                        }
                        subtopicSelect.trigger('change.select2');
                    }
                });
            } else {
                subtopicSelect.html('<option value="">Select Sub Topic</option>').prop('disabled', false);
                subtopicSelect.trigger('change.select2');
            }
        });
        
        $('#aiGeneratorForm').on('submit', function() {
            $('button[type="submit"]').html('<i class="fa fa-spinner fa-spin"></i> Generating...').prop('disabled', true);
        });
    });
</script>
@endsection

@extends('layouts.master')
@section('rich-editor', true)
@section('title', 'Send Email')

@section('css')
<link href="{{ URL::asset('build/libs/sweetalert2/sweetalert2.min.css') }}" rel="stylesheet" type="text/css" />
<link href="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/css/select2.min.css" rel="stylesheet" />
@endsection

@section('content')
@component('components.breadcrumb')
@slot('li_1', 'Email')
@slot('title', 'Send Email')
@endcomponent

<div class="row">
    <div class="col-lg-12">
        <div class="card">
            <div class="card-header">
                <h4 class="card-title mb-0">Send Email</h4>
            </div>
            <div class="card-body">
                @if(session('success'))
                <div class="alert alert-success">
                    {{ session('success') }}
                </div>
                @endif
                @if(session('error'))
                <div class="alert alert-danger">
                    {{ session('error') }}
                </div>
                @endif
                <form action="{{ route('send-email') }}" method="POST">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label">Type</label>
                        <div>
                            <input type="radio" id="type-student" name="type" value="student" {{ old('type')=='student'
                                ? 'checked' : '' }} required>
                            <label for="type-student" class="me-3">Student</label>
                            <input type="radio" id="type-any" name="type" value="any" {{ old('type')=='any' ? 'checked'
                                : '' }} required>
                            <label for="type-any">Any Email</label>
                        </div>
                        @if ($errors->has('type'))
                        <div class="invalid-feedback">{{ $errors->first('type') }}</div>
                        @endif
                    </div>

                    <div id="student-fields" style="display: none;">
                        <div class="mb-3">
                            <label for="student-emails-field" class="form-label">Student Emails</label>
                            <select id="student-emails-field" name="student_emails[]" class="form-control"
                                multiple="multiple"></select>
                            <small class="form-text text-muted">Default: All students. If you add manually, then search
                                student email.</small>
                            @if ($errors->has('student_emails'))
                            <div class="invalid-feedback">{{ $errors->first('student_emails') }}</div>
                            @endif
                        </div>
                    </div>

                    <div id="any-email-field" style="display: none;">
                        <div class="mb-3">
                            <label for="any-emails-field" class="form-label">Any Emails</label>
                            <input type="text" id="any-emails-field" name="any_emails" class="form-control"
                                placeholder="Enter Emails (comma separated)" value="{{ old('any_emails') }}" />
                            @if ($errors->has('any_emails'))
                            <div class="invalid-feedback">{{ $errors->first('any_emails') }}</div>
                            @endif
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="subject-field" class="form-label">Subject</label>
                        <input type="text" id="subject-field" name="subject" class="form-control"
                            placeholder="Enter Subject" value="{{ old('subject') }}" required />
                        @if ($errors->has('subject'))
                        <div class="invalid-feedback">{{ $errors->first('subject') }}</div>
                        @endif
                    </div>

                    <div class="mb-3">
                        <label for="template-field" class="form-label">Select Email Template</label>
                        <select id="template-field" name="template_id" class="form-control">
                            <option value="">Select Template</option>
                            @foreach($emailTemplates as $template)
                            <option value="{{ $template->id }}"
                                data-description="{{ $template->description }}"
                                data-subject="{{ $template->subject ?? '' }}"
                                {{ old('template_id')==$template->id ? 'selected' : '' }}>{{ $template->name }}</option>
                            @endforeach
                        </select>
                        @if ($errors->has('template_id'))
                        <div class="invalid-feedback">{{ $errors->first('template_id') }}</div>
                        @endif
                    </div>

                    <div class="mb-3">
                        <x-textarea-editor id="email-template-field" name="email_template" label="Email Template"
                            placeholder="If you do not want to select an email template, then simply type the email message. Once you load the editor, you cannot select a template. Use the reset button to start over."
                            :value="old('email_template')" required />
                        @if ($errors->has('email_template'))
                        <div class="invalid-feedback">{{ $errors->first('email_template') }}</div>
                        @endif
                    </div>

                    <div class="d-flex justify-content-end">
                        <button type="button" class="btn btn-light" onclick="location.reload();">Reset</button>
                        <button type="submit" class="btn btn-success ms-2">Send Email</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        function toggleEmailFields() {
            const studentFields = document.getElementById('student-fields');
            const anyEmailField = document.getElementById('any-email-field');
            const typeStudent = document.getElementById('type-student').checked;
            studentFields.style.display = typeStudent ? 'block' : 'none';
            anyEmailField.style.display = !typeStudent ? 'block' : 'none';
        }

        document.getElementById('type-student').addEventListener('change', toggleEmailFields);
        document.getElementById('type-any').addEventListener('change', toggleEmailFields);

        toggleEmailFields();

        // Prefill email template description
        const templateField = document.getElementById('template-field');
        const emailTemplateField = document.getElementById('email-template-field');
        
        templateField.addEventListener('change', function() {
            const selectedOption = templateField.options[templateField.selectedIndex];
            const description = selectedOption.getAttribute('data-description');
            const subject = selectedOption.getAttribute('data-subject');
            emailTemplateField.value = description || '';
            if (subject) {
                document.getElementById('subject-field').value = subject;
            }
        });

        templateField.dispatchEvent(new Event('change'));

        // Initialize Select2 for student emails field
        $('#student-emails-field').select2({
            placeholder: "Search for student emails",
            minimumInputLength: 2,
            ajax: {
                url: '{{ route("students.email.search") }}',
                dataType: 'json',
                delay: 250,
                data: function(params) {
                    return {
                        query: params.term
                    };
                },
                processResults: function(data) {
                    return {
                        results: data.map(function(student) {
                            return {
                                id: student.email,
                                text: student.email
                            };
                        })
                    };
                },
                cache: true
            }
        });
    });
</script>
@endsection

@section('script')
<script src="{{ URL::asset('build/libs/sweetalert2/sweetalert2.min.js') }}"></script>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        @if(session('success'))
        Swal.fire({
            icon: 'success',
            title: 'Success',
            text: '{{ session('success') }}',
            timer: 3000,
            showConfirmButton: false
        });
        @endif

        @if(session('error'))
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: '{{ session('error') }}',
            timer: 3000,
            showConfirmButton: false
        });
        @endif

        @if($errors->any())
        Swal.fire({
            icon: 'error',
            title: 'Validation Error',
            text: '{{ implode(", ", $errors->all()) }}',
            timer: 5000,
            showConfirmButton: true
        });
        @endif
    });
</script>
@endsection

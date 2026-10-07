@extends('layouts.master')
@section('rich-editor', true)
@section('title', isset($emailTemplate) ? 'Edit Email Template' : 'Add Email Template')

@section('css')
<link href="{{ URL::asset('build/libs/sweetalert2/sweetalert2.min.css') }}" rel="stylesheet" type="text/css" />
@endsection

@section('content')
@component('components.breadcrumb')
@slot('li_1', '')
@slot('title', isset($emailTemplate) ? 'Edit Email Template' : 'Add Email Template')
@endcomponent

<div class="row">
    <div class="col-lg-12">
        <div class="card">
            <div class="card-header">
                <h4 class="card-title mb-0">{{ isset($emailTemplate) ? 'Edit Email Template' : 'Add Email Template' }}
                </h4>
            </div>
            <div class="card-body">
                <form method="POST"
                    action="{{ isset($emailTemplate) ? route('email-templates.update', $emailTemplate->id) : route('email-templates.store') }}">
                    @csrf
                    @if(isset($emailTemplate))
                    @method('PUT')
                    @endif

                    <div class="mb-3">
                        <label for="name-field" class="form-label">Template Name</label>
                        <input type="text" id="name-field" name="name" class="form-control"
                            placeholder="Enter Template Name"
                            value="{{ old('name', isset($emailTemplate) ? $emailTemplate->name : '') }}" required />
                        @if ($errors->has('name'))
                        <div class="invalid-feedback">{{ $errors->first('name') }}</div>
                        @endif
                    </div>

                    <div class="mb-3">
                        <label for="subject-field" class="form-label">Email Subject</label>
                        <input type="text" id="subject-field" name="subject" class="form-control"
                            placeholder="Example: Welcome to {#siteName#}"
                            value="{{ old('subject', isset($emailTemplate) ? ($emailTemplate->subject ?? '') : '') }}" />
                        <small class="text-muted">You can use placeholders such as {#studentName#}, {#siteName#}, and {#organizationName#}.</small>
                        @if ($errors->has('subject'))
                        <div class="invalid-feedback">{{ $errors->first('subject') }}</div>
                        @endif
                    </div>

                    <div class="mb-3">
                        <label for="type-field" class="form-label">Template Use</label>
                        <select id="type-field" name="type" class="form-control">
                            @php $selectedType = old('type', isset($emailTemplate) ? ($emailTemplate->type ?? '') : ''); @endphp
                            <option value="" {{ $selectedType === '' ? 'selected' : '' }}>Manual / General Email</option>
                            <option value="student_welcome" {{ $selectedType === 'student_welcome' ? 'selected' : '' }}>Automatic Student Welcome Email</option>
                            <option value="student_pending_admin_alert" {{ $selectedType === 'student_pending_admin_alert' ? 'selected' : '' }}>Pending OTP Admin Alert</option>
                            <option value="student_admin_activated" {{ $selectedType === 'student_admin_activated' ? 'selected' : '' }}>Manual Activation Student Email</option>
                            <option value="student_founder_followup" {{ $selectedType === 'student_founder_followup' ? 'selected' : '' }}>Founder Follow-up Email</option>
                            <option value="guest_contact_welcome" {{ $selectedType === 'guest_contact_welcome' ? 'selected' : '' }}>Guest Contact Welcome Email</option>
                            <option value="password_reset" {{ $selectedType === 'password_reset' ? 'selected' : '' }}>Password Reset Email</option>
                            <option value="otp" {{ $selectedType === 'otp' ? 'selected' : '' }}>OTP Email</option>
                        </select>
                        <small class="text-muted">Choose the automatic use case for welcome, pending OTP alert, or manual activation emails.</small>
                    </div>

                    <div class="mb-3">
                        <x-textarea-editor id="description-field" name="description" label="Email Body"
                            placeholder="Enter Email Body"
                            :value="old('description', isset($emailTemplate) ? $emailTemplate->description : '')"
                            required />
                        @if ($errors->has('description'))
                        <div class="invalid-feedback">{{ $errors->first('description') }}</div>
                        @endif
                        <div class="mt-2 small text-muted">
                            Available placeholders: {#studentName#}, {#studentEmail#}, {#studentPhone#}, {#studentRegCode#}, {#siteName#}, {#organizationName#}, {#myExamsUrl#}, {#coursesUrl#}, {#loginUrl#}, {#resetPasswordUrl#}, {#studentsAdminUrl#}, {#groupNames#}, {#groupBlock#}, {#primaryColor#}, {#secondaryColor#}.
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="status-field" class="form-label">Status</label>
                        <select id="status-field" name="status" class="form-control" required>
                            <option value="Active" {{ old('status', isset($emailTemplate) ? $emailTemplate->status : '')
                                == 'Active' ? 'selected' : '' }}>Active</option>
                            <option value="Inactive" {{ old('status', isset($emailTemplate) ? $emailTemplate->status :
                                '') == 'Inactive' ? 'selected' : '' }}>Inactive</option>
                        </select>
                        @if ($errors->has('status'))
                        <div class="invalid-feedback">{{ $errors->first('status') }}</div>
                        @endif
                    </div>

                    <div class="d-flex justify-content-end">
                        <a href="{{ route('email-templates.index') }}" class="btn btn-light">Cancel</a>
                        <button type="submit" class="btn btn-success ms-2">{{ isset($emailTemplate) ? 'Update Template'
                            : 'Add Template' }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@section('script')
<script src="{{ URL::asset('build/libs/sweetalert2/sweetalert2.min.js') }}"></script>


<script>
    document.addEventListener('DOMContentLoaded', function() {
        @if(session('success'))
        Swal.fire({
            icon: 'success'
            , title: 'Success'
            , text: '{{ session('success') }}'
            , timer: 3000
            , showConfirmButton: false
        });
        @endif

        @if(session('error'))
        Swal.fire({
            icon: 'error'
            , title: 'Error'
            , text: '{{ session('error') }}'
            , timer: 3000
            , showConfirmButton: false
        });
        @endif

        @if($errors->any())
        Swal.fire({
            icon: 'error'
            , title: 'Validation Error'
            , text: '{{ implode(", ", $errors->all()) }}'
            , timer: 5000
            , showConfirmButton: true
        });
        @endif
    });
</script>
@endsection

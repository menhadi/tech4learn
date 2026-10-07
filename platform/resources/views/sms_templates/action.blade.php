@extends('layouts.master')
@section('title', isset($smsTemplate) ? 'Edit SMS Template' : 'Add SMS Template')

@section('css')
<link href="{{ URL::asset('build/libs/sweetalert2/sweetalert2.min.css') }}" rel="stylesheet" type="text/css" />
@endsection

@section('content')
@component('components.breadcrumb')
@slot('li_1', '')
@slot('title', isset($smsTemplate) ? 'Edit SMS Template' : 'Add SMS Template')
@endcomponent

<div class="row">
    <div class="col-lg-12">
        <div class="card">
            <div class="card-header">
                <h4 class="card-title mb-0">{{ isset($smsTemplate) ? 'Edit SMS Template' : 'Add SMS Template' }}
                </h4>
            </div>
            <div class="card-body">
                <form method="POST"
                    action="{{ isset($smsTemplate) ? route('sms-templates.update', $smsTemplate->id) : route('sms-templates.store') }}">
                    @csrf
                    @if(isset($smsTemplate))
                    @method('PUT')
                    @endif
                    @php
                        $templateType = old('type', isset($smsTemplate) ? $smsTemplate->type : '');
                    @endphp
                    <input type="hidden" name="type" value="{{ $templateType }}">

                    @if($templateType === \App\Models\SmsTemplate::TYPE_STUDENT_OTP_SMS)
                        <div class="alert alert-info">
                            <strong>This is the ready-made SMS OTP text.</strong>
                            Twilio SMS uses it directly. MSG91 and 2Factor use their approved provider template, so keep that provider text matched to this message. Keep <code>{#otp#}</code>; you may also use <code>{#siteName#}</code> and <code>{#studentName#}</code>.
                        </div>
                    @elseif($templateType === \App\Models\SmsTemplate::TYPE_STUDENT_OTP_WHATSAPP)
                        <div class="alert alert-info">
                            <strong>This is the ready-made WhatsApp OTP text.</strong>
                            It is used for Twilio's direct-body fallback and provided as the reference for approved MSG91/Twilio templates. Production WhatsApp delivery still uses the approved template name or Content SID from Messaging Settings.
                        </div>
                    @endif

                    <div class="mb-3">
                        <label for="name-field" class="form-label">Template Name</label>
                        <input type="text" id="name-field" name="name" class="form-control"
                            placeholder="Enter Template Name"
                            value="{{ old('name', isset($smsTemplate) ? $smsTemplate->name : '') }}" required />
                        @if ($errors->has('name'))
                        <div class="invalid-feedback">{{ $errors->first('name') }}</div>
                        @endif
                    </div>

                    <div class="mb-3">
                        <label for="description-field" class="form-label">Plain-text message</label>
                        <textarea id="description-field" name="description" class="form-control" rows="5" maxlength="1000" required>{{ old('description', isset($smsTemplate) ? $smsTemplate->description : '') }}</textarea>
                        <div class="form-text">Available placeholders: <code>{#otp#}</code>, <code>{#siteName#}</code>, and <code>{#studentName#}</code>. HTML is removed before sending.</div>
                        @if ($errors->has('description'))
                        <div class="invalid-feedback d-block">{{ $errors->first('description') }}</div>
                        @endif
                    </div>

                    @if($templateType === \App\Models\SmsTemplate::TYPE_STUDENT_OTP_SMS)
                        <div class="card border mb-3">
                            <div class="card-body">
                                <h6>Provider/DLT approval starter</h6>
                                <p class="text-muted small">Copy this to MSG91 or your DLT portal, then replace the second variable with your approved brand name if required.</p>
                                <code id="provider-template-example">{#var#} is your OTP for {#var#}. Valid for 10 minutes. Do not share it.</code>
                                <button type="button" class="btn btn-sm btn-outline-primary ms-2 copy-template-button" data-copy-target="provider-template-example">Copy</button>
                            </div>
                        </div>
                    @elseif($templateType === \App\Models\SmsTemplate::TYPE_STUDENT_OTP_WHATSAPP)
                        <div class="card border mb-3">
                            <div class="card-body">
                                <h6>WhatsApp approval starter</h6>
                                <p class="text-muted small">Copy this when creating an Authentication template in MSG91 or Twilio. Enter the approved template name or Content SID in Messaging Settings.</p>
                                <code id="provider-template-example">Your verification code is &#123;&#123;1&#125;&#125;. It expires in 10 minutes. Do not share it.</code>
                                <button type="button" class="btn btn-sm btn-outline-primary ms-2 copy-template-button" data-copy-target="provider-template-example">Copy</button>
                            </div>
                        </div>
                    @endif

                    <div class="mb-3">
                        <label for="status-field" class="form-label">Status</label>
                        <select id="status-field" name="status" class="form-control" required>
                            <option value="Active" {{ old('status', isset($smsTemplate) ? $smsTemplate->status : '')
                                == 'Active' ? 'selected' : '' }}>Active</option>
                            <option value="Inactive" {{ old('status', isset($smsTemplate) ? $smsTemplate->status :
                                '') == 'Inactive' ? 'selected' : '' }}>Inactive</option>
                        </select>
                        @if ($errors->has('status'))
                        <div class="invalid-feedback">{{ $errors->first('status') }}</div>
                        @endif
                    </div>

                    <div class="mb-3">
                        <label for="dlt_template_id-field" class="form-label">DLT Template ID</label>
                        <input type="text" id="dlt_template_id-field" name="dlt_template_id" class="form-control"
                            placeholder="Enter DLT Template ID"
                            value="{{ old('dlt_template_id', isset($smsTemplate) ? $smsTemplate->dlt_template_id : '') }}" />
                        @if ($errors->has('dlt_template_id'))
                        <div class="invalid-feedback">{{ $errors->first('dlt_template_id') }}</div>
                        @endif
                    </div>

                    <div class="d-flex justify-content-end">
                        <a href="{{ route('sms-templates.index') }}" class="btn btn-light">Cancel</a>
                        <button type="submit" class="btn btn-success ms-2">{{ isset($smsTemplate) ? 'Update Template'
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
        document.querySelectorAll('.copy-template-button').forEach(function(button) {
            button.addEventListener('click', function() {
                const target = document.getElementById(button.dataset.copyTarget);
                if (!target) return;
                navigator.clipboard.writeText(target.textContent.trim()).then(function() {
                    const original = button.textContent;
                    button.textContent = 'Copied';
                    window.setTimeout(function() { button.textContent = original; }, 1500);
                });
            });
        });

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
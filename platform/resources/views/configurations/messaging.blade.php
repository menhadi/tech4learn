@extends('layouts.master')
@section('title', 'Messaging Settings')

@section('content')
@component('components.breadcrumb')
    @slot('li_1', 'Email & Messaging')
    @slot('title', 'Messaging Settings')
@endcomponent

@if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif

@php
    $credentials = $configuration->messaging_credentials ?: [];
    $settings = $configuration->messaging_settings ?: [];
@endphp

<form action="{{ route('configurations.messaging.update') }}" method="POST">
    @csrf
    @method('PUT')

    <div class="card">
        <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
            <div>
                <h4 class="card-title mb-1">Student OTP delivery</h4>
                <p class="text-muted mb-0">Choose the preferred route and configure the providers available to students.</p>
            </div>
            <a href="{{ route('sms-templates.index') }}" class="btn btn-outline-primary btn-sm">
                <i class="ri-file-text-line me-1"></i> Edit ready-made OTP templates
            </a>
        </div>
        <div class="card-body">
            <div class="alert alert-info mb-4">
                <strong>Preferred OTP channel is the first choice, not the only channel.</strong>
                A student who enters an email receives email. A student who enters a mobile number uses the preferred configured phone channel; if it is unavailable, the system falls back to another configured phone channel. On the verification page, the student can choose any channel available for that account.
            </div>

            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Preferred OTP channel</label>
                    <select name="student_otp_channel" class="form-select">
                        <option value="email" @selected(old('student_otp_channel', $configuration->student_otp_channel) === 'email')>Email</option>
                        <option value="sms" @selected(old('student_otp_channel', $configuration->student_otp_channel) === 'sms')>SMS</option>
                        <option value="whatsapp" @selected(old('student_otp_channel', $configuration->student_otp_channel) === 'whatsapp')>WhatsApp</option>
                    </select>
                    <div class="form-text">This controls the first attempted channel only. It does not disable the others.</div>
                </div>
                <div class="col-md-4">
                    <label class="form-label">SMS provider</label>
                    <select name="sms_provider" class="form-select">
                        <option value="">Not configured</option>
                        <option value="msg91" @selected(old('sms_provider', $configuration->sms_provider) === 'msg91')>MSG91</option>
                        <option value="twilio" @selected(old('sms_provider', $configuration->sms_provider) === 'twilio')>Twilio</option>
                        <option value="twofactor" @selected(old('sms_provider', $configuration->sms_provider) === 'twofactor')>2Factor</option>
                    </select>
                    <div class="form-text">Used only for SMS delivery.</div>
                </div>
                <div class="col-md-4">
                    <label class="form-label">WhatsApp provider</label>
                    <select name="whatsapp_provider" class="form-select">
                        <option value="">Not configured</option>
                        <option value="msg91" @selected(old('whatsapp_provider', $configuration->whatsapp_provider) === 'msg91')>MSG91</option>
                        <option value="twilio" @selected(old('whatsapp_provider', $configuration->whatsapp_provider) === 'twilio')>Twilio</option>
                    </select>
                    <div class="form-text">Used only for WhatsApp template messages.</div>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Default country code</label>
                    <input name="default_country_code" class="form-control" value="{{ old('default_country_code', $configuration->default_country_code ?: '+91') }}" placeholder="+91">
                    <div class="form-text">This is only the country prefix, not a mobile number. Example: enter <strong>+91</strong> for India.</div>
                </div>
                <div class="col-md-6">
                    <label class="form-label">SMS message template</label>
                    <select name="settings[sms_template_id]" class="form-select">
                        <option value="">Use the ready-made Student OTP - SMS template</option>
                        @foreach($messageTemplates as $messageTemplate)
                            <option value="{{ $messageTemplate->id }}" @selected((string) old('settings.sms_template_id', $settings['sms_template_id'] ?? '') === (string) $messageTemplate->id)>{{ $messageTemplate->name }}</option>
                        @endforeach
                    </select>
                    <div class="form-text">Twilio SMS sends this text directly. For MSG91 or 2Factor, keep the approved provider template matched to this selection.</div>
                </div>
                <div class="col-md-6">
                    <label class="form-label">WhatsApp message template</label>
                    <select name="settings[whatsapp_template_id]" class="form-select">
                        <option value="">Use the ready-made Student OTP - WhatsApp template</option>
                        @foreach($messageTemplates as $messageTemplate)
                            <option value="{{ $messageTemplate->id }}" @selected((string) old('settings.whatsapp_template_id', $settings['whatsapp_template_id'] ?? '') === (string) $messageTemplate->id)>{{ $messageTemplate->name }}</option>
                        @endforeach
                    </select>
                    <div class="form-text">Used for Twilio direct-body fallback and as the reference text for your approved WhatsApp provider template.</div>
                </div>
            </div>
        </div>
    </div>


    <div class="card">
        <div class="card-header">
            <h4 class="card-title mb-1">Provider credentials</h4>
            <p class="text-muted mb-0">Secrets are stored encrypted. Leave an already-saved secret blank to keep its current value.</p>
        </div>
        <div class="card-body">
            <h5 class="mb-1">MSG91</h5>
            <p class="text-muted small">The auth key connects your MSG91 account. Template IDs and names must match approved templates in MSG91.</p>
            <div class="row g-3 mb-4">
                <div class="col-md-6">
                    <label class="form-label">Auth key</label>
                    <input type="password" name="credentials[msg91_auth_key]" class="form-control" autocomplete="new-password" placeholder="{{ isset($credentials['msg91_auth_key']) ? 'Saved - leave blank to keep' : 'MSG91 auth key' }}">
                </div>
                <div class="col-md-6">
                    <label class="form-label">SMS OTP template ID</label>
                    <input name="settings[msg91_otp_template_id]" class="form-control" value="{{ old('settings.msg91_otp_template_id', $settings['msg91_otp_template_id'] ?? '') }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label">MSG91 WhatsApp Business sender number</label>
                    <input name="settings[msg91_whatsapp_number]" class="form-control" value="{{ old('settings.msg91_whatsapp_number', $settings['msg91_whatsapp_number'] ?? '') }}" placeholder="919876543210">
                    <div class="form-text">Enter the WhatsApp Business API number onboarded in MSG91, with country code and digits only. Do not enter your personal mobile unless MSG91 has approved it as this sender.</div>
                </div>
                <div class="col-md-4">
                    <label class="form-label">WhatsApp template name</label>
                    <input name="settings[msg91_whatsapp_template]" class="form-control" value="{{ old('settings.msg91_whatsapp_template', $settings['msg91_whatsapp_template'] ?? '') }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Template language</label>
                    <input name="settings[msg91_whatsapp_language]" class="form-control" value="{{ old('settings.msg91_whatsapp_language', $settings['msg91_whatsapp_language'] ?? 'en') }}" placeholder="en">
                </div>
            </div>

            <h5 class="mb-1">Twilio</h5>
            <p class="text-muted small">The Account SID and token authenticate Twilio. Sender numbers must be enabled in your Twilio account. WhatsApp OTP should use an approved Content SID.</p>
            <div class="row g-3 mb-4">
                <div class="col-md-6">
                    <label class="form-label">Account SID</label>
                    <input type="password" name="credentials[twilio_account_sid]" class="form-control" autocomplete="new-password" placeholder="{{ isset($credentials['twilio_account_sid']) ? 'Saved - leave blank to keep' : 'Starts with AC' }}">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Auth token</label>
                    <input type="password" name="credentials[twilio_auth_token]" class="form-control" autocomplete="new-password" placeholder="{{ isset($credentials['twilio_auth_token']) ? 'Saved - leave blank to keep' : 'Twilio auth token' }}">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Twilio SMS sender number</label>
                    <input name="settings[twilio_sms_from]" class="form-control" value="{{ old('settings.twilio_sms_from', $settings['twilio_sms_from'] ?? '') }}" placeholder="+1234567890">
                    <div class="form-text">Enter an SMS-capable number purchased or verified in Twilio. This is not your personal or student's mobile number.</div>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Twilio WhatsApp sender number</label>
                    <input name="settings[twilio_whatsapp_from]" class="form-control" value="{{ old('settings.twilio_whatsapp_from', $settings['twilio_whatsapp_from'] ?? '') }}" placeholder="+1234567890">
                    <div class="form-text">Enter the WhatsApp sender approved in Twilio (or the Twilio Sandbox sender while testing), not your personal mobile number.</div>
                </div>
                <div class="col-md-6">
                    <label class="form-label">WhatsApp Content SID</label>
                    <input name="settings[twilio_whatsapp_content_sid]" class="form-control" value="{{ old('settings.twilio_whatsapp_content_sid', $settings['twilio_whatsapp_content_sid'] ?? '') }}" placeholder="Approved template SID starting with HX">
                    <div class="form-text">Recommended for a production WhatsApp authentication template.</div>
                </div>
            </div>

            <h5 class="mb-1">2Factor</h5>
            <p class="text-muted small">2Factor is available for SMS OTP only.</p>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">API key</label>
                    <input type="password" name="credentials[twofactor_api_key]" class="form-control" autocomplete="new-password" placeholder="{{ isset($credentials['twofactor_api_key']) ? 'Saved - leave blank to keep' : '2Factor API key' }}">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Template name (optional)</label>
                    <input name="settings[twofactor_template_name]" class="form-control" value="{{ old('settings.twofactor_template_name', $settings['twofactor_template_name'] ?? '') }}">
                </div>
            </div>
        </div>
        <div class="card-footer">
            <button class="btn btn-primary" type="submit">Save Messaging Settings</button>
        </div>
    </div>
</form>
@endsection
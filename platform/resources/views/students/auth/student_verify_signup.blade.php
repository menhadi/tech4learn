@php
    $configuration = $configuration ?? (function_exists('getConfiguration') ? getConfiguration() : null);
    $brandName = $configuration->name ?? config('app.name', 'ExamElite');
@endphp
@extends('layouts.master-without-nav')
@section('title', 'Verify Account')

@section('content')
<div class="student-otp-page">
    <div class="otp-card">
        <a href="{{ url('/') }}" class="otp-header">
            <h2>{{ $brandName }}</h2>
            <p>{{ __('ui.verify_account') }}</p>
        </a>

        <div class="otp-body">
            <div class="otp-copy">
                <p>We sent a verification code by {{ ucfirst(session('verify_channel', $student->otp_channel ?: 'email')) }} to</p>
                <strong>{{ $destination }}</strong>
            </div>

            @if (session('error'))
                <div class="otp-alert otp-alert-danger">{{ session('error') }}</div>
            @endif
            @if (session('success'))
                <div class="otp-alert otp-alert-success">{{ session('success') }}</div>
            @endif

            <form action="{{ route('student.verify.post') }}" method="POST">
                @csrf
                <div class="otp-field">
                    <label>{{ __('messages.auth_placeholder_otp') }}</label>
                    <input type="text" name="otp" placeholder="000000" maxlength="6" inputmode="numeric" autocomplete="one-time-code" required>
                </div>

                <div class="otp-note">
                    {{ __('ui.otp_security_copy') }}
                </div>

                <button type="submit" class="otp-submit">{{ __('ui.verify_login') }}</button>
            </form>

            <div class="otp-links">
                <p>{{ __('ui.didnt_receive_code') }}</p>
                @if(($whatsAppAttempts ?? 0) >= 2 && count($channels) > 1)
                    <div class="otp-alert otp-alert-info">{{ __('ui.whatsapp_limit_copy') }}</div>
                @endif
                @foreach($channels as $channel => $label)
                    <form method="POST" action="{{ route('student.resend_otp') }}" style="display:inline-block;margin:3px;">
                        @csrf
                        <input type="hidden" name="channel" value="{{ $channel }}">
                        <button type="submit"
                                class="btn btn-sm btn-outline-primary otp-resend-button"
                                data-label="Send by {{ $label }}"
                                @disabled(($resendAfter ?? 0) > 0)>Send by {{ $label }}</button>
                    </form>
                @endforeach
                <p class="otp-resend-help" aria-live="polite"></p>
                <p class="mt-3">Wrong details? <a href="{{ route('student.signin') }}">Back to Login</a></p>
            </div>
        </div>
    </div>
</div>

<style>
    :root {
        --auth-primary: {{ $configuration->theme_primary_color ?? $configuration->primary_color ?? 'var(--theme-primary, #0f766e)' }};
        --auth-secondary: {{ $configuration->theme_secondary_color ?? $configuration->secondary_color ?? 'var(--theme-secondary, #f59e0b)' }};
        --auth-heading: {{ $configuration->theme_heading_color ?? $configuration->heading_color ?? 'var(--theme-heading, #0f172a)' }};
        --auth-bg: {{ $configuration->theme_body_bg ?? 'var(--theme-body-bg, #f7faf9)' }};
        --auth-muted: var(--theme-muted, #64748b);
        --auth-border: var(--theme-border, #e2e8f0);
        --auth-soft: var(--theme-primary-soft, #e6f4f1);
    }
    html, body {
        min-height: 100%;
        margin: 0;
        padding: 0;
    }
    .student-otp-page {
        min-height: 100vh;
        background: color-mix(in srgb, var(--auth-primary) 8%, var(--auth-bg));
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 18px;
        font-family: 'Inter', sans-serif;
    }
    .otp-card {
        max-width: 430px;
        width: 100%;
        background: #fff;
        border-radius: 18px;
        box-shadow: 0 18px 40px -22px rgba(15,23,42,0.45);
        border: 1px solid var(--auth-border);
        overflow: hidden;
    }
    .otp-header {
        display: block;
        text-decoration: none;
        background: var(--auth-primary);
        color: white;
        text-align: center;
        padding: 22px;
    }
    .otp-header h2 {
        margin: 0 0 4px;
        font-size: 1.35rem;
        font-weight: 800;
        color: white;
    }
    .otp-header p {
        margin: 0;
        color: white;
        opacity: 0.92;
        font-size: 0.8rem;
    }
    .otp-body { padding: 24px; }
    .otp-copy {
        text-align: center;
        margin-bottom: 16px;
    }
    .otp-copy p {
        margin: 0 0 5px;
        color: var(--auth-muted);
        font-size: 0.84rem;
    }
    .otp-copy strong {
        color: var(--auth-primary);
        font-size: 0.9rem;
        word-break: break-word;
    }
    .otp-alert {
        padding: 10px 12px;
        border-radius: 12px;
        font-size: 0.78rem;
        margin-bottom: 14px;
    }
    .otp-alert-danger { background: #fee2e2; color: #991b1b; border-left: 3px solid #ef4444; }
    .otp-alert-success { background: var(--auth-soft); color: var(--auth-primary); border-left: 3px solid var(--auth-primary); }
    .otp-alert-info { background: #eff6ff; color: #1e40af; border-left: 3px solid #3b82f6; margin-top: 10px; }
    .otp-resend-button:disabled { opacity: 0.55; cursor: not-allowed; }
    .otp-resend-help { min-height: 18px; font-weight: 600; }
    .otp-field { margin-bottom: 14px; }
    .otp-field label {
        font-size: 0.76rem;
        font-weight: 800;
        color: var(--auth-heading);
        margin-bottom: 6px;
        display: block;
    }
    .otp-field input {
        width: 100%;
        padding: 12px;
        border: 2px solid var(--auth-border);
        border-radius: 12px;
        font-size: 16px;
        letter-spacing: 0.35em;
        text-align: center;
        font-weight: 800;
        background: color-mix(in srgb, var(--auth-primary) 4%, #fff);
    }
    .otp-field input:focus {
        outline: none;
        border-color: var(--auth-primary);
        box-shadow: 0 0 0 3px color-mix(in srgb, var(--auth-primary) 16%, transparent);
        background: #fff;
    }
    .otp-note {
        background: color-mix(in srgb, var(--auth-secondary) 14%, #fff);
        color: var(--auth-heading);
        padding: 10px 12px;
        border-radius: 12px;
        font-size: 0.74rem;
        line-height: 1.45;
        margin-bottom: 16px;
    }
    .otp-submit {
        width: 100%;
        background: var(--auth-secondary);
        color: var(--auth-heading);
        border: none;
        padding: 12px;
        border-radius: 12px;
        font-weight: 800;
        font-size: 0.9rem;
        cursor: pointer;
        box-shadow: 0 10px 20px -14px var(--auth-secondary);
    }
    .otp-submit:hover,
    .otp-submit:focus,
    .otp-submit:active {
        background: color-mix(in srgb, var(--auth-secondary) 88%, #000);
        color: var(--auth-heading);
    }
    .otp-links {
        margin-top: 16px;
        text-align: center;
    }
    .otp-links p {
        font-size: 0.78rem;
        margin: 5px 0;
        color: var(--auth-muted);
    }
    .otp-links a {
        color: var(--auth-primary);
        font-weight: 700;
        text-decoration: none;
    }
    .otp-links a:hover {
        color: var(--auth-secondary);
        text-decoration: underline;
    }
</style>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        let remaining = Number(@json($resendAfter ?? 0));
        const buttons = Array.from(document.querySelectorAll('.otp-resend-button'));
        const help = document.querySelector('.otp-resend-help');

        const render = function () {
            buttons.forEach(function (button) {
                button.disabled = remaining > 0;
                button.textContent = remaining > 0
                    ? 'Resend in ' + remaining + 's'
                    : button.dataset.label;
            });
            if (help) {
                help.textContent = remaining > 0
                    ? 'For your security, another code can be requested in ' + remaining + ' seconds.'
                    : 'You can request a new code now.';
            }
        };

        render();
        if (remaining > 0) {
            const timer = window.setInterval(function () {
                remaining = Math.max(0, remaining - 1);
                render();
                if (remaining === 0) {
                    window.clearInterval(timer);
                }
            }, 1000);
        }
    });
</script>
@endsection

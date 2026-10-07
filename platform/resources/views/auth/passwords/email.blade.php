@php
    if (!isset($configuration) && class_exists(\App\Models\Configuration::class)) {
        $configuration = \App\Models\Configuration::first();
    }
    $brandName = $configuration->name ?? config('app.name','ExamElite');
    $logoPath  = $configuration->logo ?? null;
    $logoDarkUrl  = $logoPath ? asset('storage/' . $logoPath) : asset('build/images/logo-dark.png');
@endphp

@extends('layouts.master-without-nav')
@section('title', 'Forgot Password')
@section('content')
<div class="auth-page-wrapper d-flex justify-content-center align-items-center min-vh-100" style="padding: 20px;">
    <div class="auth-page-content">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-5">
                    <div class="card overflow-hidden shadow-lg border-0" style="border-radius: 28px; background: rgba(255,255,255,0.98);">
                        {{-- Header --}}
                        <div style="background: linear-gradient(135deg, #0d9488, #0f766e); padding: 25px; text-align: center; color: white;">
                            <a href="{{ url('/') }}" style="text-decoration: none; color: white; display: inline-flex; align-items: center; gap: 10px; transition: all 0.3s ease;">
                                <span style="font-weight: 700; font-size: 1.5rem; color: white;">ExamElite</span>
                            </a>
                            <p style="font-size: 0.8rem; opacity: 0.9; margin: 0; color: white; margin-top: 8px;">Forgot Password?</p>
                        </div>

                        <div class="p-lg-4 p-4">
                            @if (session('status'))
                                <div class="alert alert-success text-center mb-3" style="border-radius: 12px;">{{ session('status') }}</div>
                            @endif
                            @if ($errors->any())
                                <div class="alert alert-danger mb-3" style="border-radius: 12px; border-left: 3px solid #ef4444;">
                                    @foreach ($errors->all() as $error) <p class="mb-0">{{ $error }}</p> @endforeach
                                </div>
                            @endif

                            <p style="color: #64748b; font-size: 0.8rem; text-align: center; margin-bottom: 18px;">Enter your email to receive an OTP</p>

                            <form method="POST" action="{{ route('password.sendOtp') }}">
                                @csrf
                                <div class="mb-3">
                                    <label class="form-label fw-semibold" style="color: #1e293b; font-size: 0.75rem;">Email Address</label>
                                    <div class="position-relative">
                                        <i class="fas fa-envelope" style="position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: #0d9488; font-size: 14px;"></i>
                                        <input type="email" class="form-control" name="email" placeholder="Enter your email" value="{{ old('email') }}" required
                                               style="padding: 12px 14px 12px 42px; border-radius: 12px; border: 2px solid #e2e8f0; font-size: 14px; background: #f8fafc; height: 44px;">
                                    </div>
                                </div>

                                <button type="submit" class="btn w-100 auth-secondary-submit">
                                    <i class="fas fa-paper-plane me-2"></i> Send OTP
                                </button>
                            </form>

                            <div class="mt-3 text-center">
                                <a href="{{ route('login') }}" style="color: #0d9488; text-decoration: none; font-size: 0.8rem;">← Back to Sign In</a>
                            </div>

                            <div class="mt-3 text-center">
                                <p style="font-size: 0.65rem; color: #94a3b8;">&copy; {{ date('Y') }} {{ $brandName }}. All rights reserved.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .form-control:focus {
        outline: none !important;
        border-color: #0d9488 !important;
        box-shadow: 0 0 0 3px rgba(13,148,136,0.1) !important;
        background: white !important;
    }
    .auth-secondary-submit {
        background: var(--el-secondary, #f59e0b) !important;
        border: 1px solid var(--el-secondary, #f59e0b) !important;
        border-radius: 50px !important;
        box-shadow: 0 4px 12px color-mix(in srgb, var(--el-secondary, #f59e0b) 24%, transparent) !important;
        color: var(--el-text, #0f172a) !important;
        font-size: 0.9rem;
        font-weight: 700;
        height: 44px;
        padding: 11px;
    }
    .auth-secondary-submit:hover,
    .auth-secondary-submit:focus,
    .auth-secondary-submit:active {
        background: color-mix(in srgb, var(--el-secondary, #f59e0b) 88%, #000) !important;
        border-color: color-mix(in srgb, var(--el-secondary, #f59e0b) 88%, #000) !important;
        color: var(--el-text, #0f172a) !important;
    }
    .alert {
        border: none;
    }
</style>
@endsection

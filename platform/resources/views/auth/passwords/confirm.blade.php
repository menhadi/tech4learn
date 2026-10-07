@php
    $configuration = function_exists('getConfiguration') ? getConfiguration() : null;
    $brandName = $configuration->name ?? config('app.name','Exam Frame');
    $logoUrl   = !empty($configuration?->logo) ? asset('storage/'.$configuration->logo) : asset('build/images/logo-light.png');

    // Route autodetect for OTP verify
    $verifyRoute = \Illuminate\Support\Facades\Route::has('password.otp.verify') ? 'password.otp.verify'
                 : (\Illuminate\Support\Facades\Route::has('password.confirm') ? 'password.confirm' : null);
@endphp

@extends('layouts.master-without-nav')
@section('title','Verify OTP')
@section('content')

<style>
.auth-one-bg-position,.auth-one-bg,.bg-overlay,.shape,#auth-particles{display:none!important}
.ef-auth-wrap{min-height:100vh;display:grid;place-items:center;padding:24px;background:radial-gradient(circle at top left, rgba(var(--el-primary-rgb, 15,118,110), .14), transparent 34%), radial-gradient(circle at bottom right, rgba(var(--el-secondary-rgb, 245,158,11), .14), transparent 30%), linear-gradient(135deg, var(--el-bg, #f7faf9) 0%, var(--el-bg-soft, #eef7f5) 100%);font-family:Inter,system-ui,-apple-system,Segoe UI,Roboto,"Helvetica Neue",Arial,sans-serif;color:#0f172a}
.ef-card{width:100%;max-width:400px;background:rgba(255,255,255,.92);backdrop-filter:blur(8px);border:1px solid rgba(15,23,42,.06);border-radius:16px;
box-shadow:0 6px 16px rgba(0,0,0,.05),0 12px 24px rgba(0,0,0,.06);padding:22px 24px;transition:transform .25s,box-shadow .25s,border-color .25s}
.ef-card:hover{transform:translateY(-4px);box-shadow:0 10px 25px rgba(15,23,42,.08),0 20px 40px rgba(15,23,42,.05),0 0 0 6px rgba(56,189,248,.10);border-color:rgba(56,189,248,.35)}
.ef-brand{display:flex;flex-direction:column;align-items:center;text-align:center;margin-bottom:10px;gap:4px}
.ef-brand .ef-logo img{height:48px;width:auto;object-fit:contain}
.ef-brand .ef-name{font-weight:800;font-size:20px;letter-spacing:.3px}
.ef-title{text-align:center;font-size:22px;font-weight:800;margin:4px 0 6px}
.ef-sub{text-align:center;color:#475569;font-size:13px;margin:0 0 12px}
.ef-field{margin-bottom:14px}
.ef-label{display:block;font-size:12.5px;color:#334155;margin:0 0 6px;font-weight:600}
.ef-input{width:100%;background:#fff;border:1px solid #e2e8f0;border-radius:10px;padding:10px 12px;font-size:14px;outline:none;transition:.15s border,.15s box-shadow}
.ef-input:focus{border-color:#38bdf8;box-shadow:0 0 0 3px rgba(56,189,248,.2)}
.ef-btn{width:100%;border:0;border-radius:12px;padding:12px 16px;font-weight:800;font-size:15px;background:linear-gradient(180deg,#14b8a6 0%,#0d9488 70%,#16a34a 100%);color:#fff;cursor:pointer;
transition:transform .2s,filter .2s,box-shadow .2s;box-shadow:0 6px 16px rgba(13,148,136,.25)}
.ef-btn:hover{filter:brightness(1.05);box-shadow:0 8px 20px rgba(13,148,136,.35);transform:translateY(-1px)}
.ef-errors{background:#fef2f2;border:1px solid #fecaca;color:#7f1d1d;border-radius:10px;padding:10px 12px;font-size:13px;margin-bottom:12px}
.ef-link{color:#2563eb;text-decoration:none} .ef-link:hover{text-decoration:underline}
</style>

<div class="ef-auth-wrap">
    <div class="ef-card">
        <div class="ef-brand">
            <div class="ef-logo"><img src="{{ $logoUrl }}" alt="{{ $brandName }}" onerror="this.style.display='none'"></div>
            <div class="ef-name">{{ $brandName }}</div>
        </div>

        <div class="ef-title">Verify OTP</div>
        <div class="ef-sub">Enter the 6-digit code sent to your email</div>

        @if ($errors->any())
            <div class="ef-errors">
                <strong>Whoops!</strong> Please fix the following:
                <ul style="margin:6px 0 0 18px">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
            </div>
        @endif

        <form method="POST" action="{{ $verifyRoute ? route($verifyRoute) : '' }}">
            @csrf
            {{-- keep names generic; backend can read email+otp --}}
            <div class="ef-field">
                <label class="ef-label" for="email">Email</label>
                <input id="email" type="email" class="ef-input" name="email" value="{{ old('email') }}" required autocomplete="email">
            </div>
            <div class="ef-field">
                <label class="ef-label" for="otp">OTP</label>
                <input id="otp" type="text" class="ef-input" name="otp" placeholder="Enter OTP" required inputmode="numeric" autocomplete="one-time-code" maxlength="6">
            </div>
            <button type="submit" class="ef-btn">Verify & Continue</button>
        </form>

        <div style="text-align:center;margin-top:12px;font-size:13px">
            Didn’t receive the code? 
            @if(Route::has('password.resend.otp'))
                <a class="ef-link" href="{{ route('password.resend.otp') }}">Resend OTP</a>
            @endif
        </div>
    </div>
</div>
@endsection

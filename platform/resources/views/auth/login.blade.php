@php
    if (!isset($configuration) && function_exists('getConfiguration')) {
        $configuration = getConfiguration();
    }
    $brandName = $configuration->name ?? config('app.name','ExamElite');
    $logoPath  = $configuration->logo ?? null;
    $logoLightUrl = $logoPath ? asset('storage/' . $logoPath) : asset('build/images/logo-light.png');
    $logoDarkUrl  = $logoPath ? asset('storage/' . $logoPath) : asset('build/images/logo-dark.png');
    $poweredBy   = $configuration->powered_by ?? null;
    $poweredLink = $configuration->powered_link ?? null;
@endphp

@extends('layouts.master-without-nav')
@section('title', 'Admin Sign In')
@section('content')
<div class="auth-page-wrapper d-flex justify-content-center align-items-center min-vh-100">
    <div class="auth-page-content">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-5">
                    <div class="card auth-card overflow-hidden shadow-lg border-0">
                        {{-- Header --}}
                        <div class="auth-brand-header">
                            <a href="{{ url('/') }}" class="auth-brand-link">
                                <span class="auth-brand-name">{{ $brandName }}</span>
                            </a>
                            <p class="auth-brand-subtitle">Admin Panel Access</p>
                        </div>

                        <div class="p-lg-4 p-4">
                            @if (session('status'))
                                <div class="alert alert-success text-center mb-3 auth-alert">{{ session('status') }}</div>
                            @endif
                            @if ($errors->any())
                                <div class="alert alert-danger alert-dismissible shadow fade show mb-3 auth-alert auth-alert-danger">
                                    @foreach ($errors->all() as $error) <p class="mb-0">{{ $error }}</p> @endforeach
                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                </div>
                            @endif

                            <form action="{{ route('login') }}" method="POST">
                                @csrf
                                <div class="mb-3">
                                    <label class="form-label fw-semibold auth-label">Username or Email</label>
                                    <div class="position-relative">
                                        <i class="fas fa-envelope auth-input-icon"></i>
                                        <input type="text" class="form-control auth-input @error('login', 'username', 'email') is-invalid @enderror"
                                               name="login" placeholder="Enter username or email"
                                               value="{{ old('login') }}" required autofocus>
                                    </div>
                                    @error('login') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                    @error('username') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                    @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>

                                <div class="mb-3">
                                    <div class="float-end">
                                        @if (Route::has('password.request'))
                                            <a href="{{ route('password.request') }}" class="auth-link">Forgot password?</a>
                                        @endif
                                    </div>
                                    <label class="form-label fw-semibold auth-label">Password</label>
                                    <div class="position-relative">
                                        <i class="fas fa-lock auth-input-icon"></i>
                                        <input type="password" class="form-control auth-input pe-5 password-input @error('password') is-invalid @enderror"
                                               placeholder="Enter password" id="password-input" name="password" required>
                                        <button class="btn btn-link position-absolute end-0 top-0 text-decoration-none shadow-none text-muted password-addon"
                                                type="button" id="password-addon" data-password-toggle>
                                            <i class="ri-eye-fill align-middle"></i>
                                        </button>
                                        @error('password') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                    </div>
                                </div>

                                <div class="form-check mb-3">
                                    <input class="form-check-input auth-check" type="checkbox" name="remember" id="remember" {{ old('remember') ? 'checked' : '' }}>
                                    <label class="form-check-label auth-check-label" for="remember">Remember me</label>
                                </div>

                                <button type="submit" class="btn w-100 auth-submit">
                                    <i class="fas fa-sign-in-alt me-2"></i> Sign In
                                </button>
                            </form>

                            <div class="mt-3 text-center">
                                <p class="auth-footer">&copy; {{ date('Y') }} {{ $brandName }}. All rights reserved.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .auth-page-wrapper {
        --auth-primary: var(--el-primary, #0f766e);
        --auth-secondary: var(--el-secondary, #f59e0b);
        --auth-bg: var(--el-bg, #f7faf9);
        --auth-text: var(--el-text, #0f172a);
        --auth-muted: var(--el-muted, #64748b);
        background:
            radial-gradient(circle at 15% 12%, color-mix(in srgb, var(--auth-primary) 14%, transparent), transparent 30%),
            radial-gradient(circle at 85% 88%, color-mix(in srgb, var(--auth-secondary) 12%, transparent), transparent 32%),
            linear-gradient(135deg, var(--auth-bg), #eef7f5);
        padding: 20px;
    }

    .auth-card {
        border-radius: 24px;
        background: rgba(255, 255, 255, 0.98);
        border: 1px solid color-mix(in srgb, var(--auth-primary) 16%, transparent) !important;
    }

    .auth-brand-header {
        background: var(--auth-primary);
        padding: 25px;
        text-align: center;
        color: #fff;
    }

    .auth-brand-link {
        text-decoration: none;
        color: #fff;
        display: inline-flex;
        align-items: center;
        gap: 10px;
    }

    .auth-brand-name {
        font-weight: 700;
        font-size: 1.5rem;
        color: #fff;
    }

    .auth-brand-subtitle {
        font-size: 0.8rem;
        opacity: 0.9;
        margin: 8px 0 0;
        color: #fff;
    }

    .auth-label {
        color: var(--auth-text);
        font-size: 0.75rem;
    }

    .auth-input {
        padding: 12px 14px 12px 42px;
        border-radius: 12px;
        border: 2px solid color-mix(in srgb, var(--auth-primary) 14%, transparent);
        font-size: 14px;
        background: #f8fafc;
        height: 44px;
    }

    .auth-input-icon {
        position: absolute;
        left: 14px;
        top: 50%;
        transform: translateY(-50%);
        color: var(--auth-primary);
        font-size: 14px;
        z-index: 1;
    }

    .password-addon {
        align-items: center;
        border-radius: 50% !important;
        color: var(--auth-primary) !important;
        display: inline-flex;
        font-size: 20px;
        height: 38px;
        justify-content: center;
        padding: 0 !important;
        right: 4px !important;
        top: 50% !important;
        transform: translateY(-50%);
        width: 38px;
    }

    .password-addon:hover,
    .password-addon:focus {
        background: color-mix(in srgb, var(--auth-primary) 10%, transparent) !important;
        color: var(--auth-primary) !important;
    }

    .auth-link {
        color: var(--auth-primary);
        text-decoration: none;
        font-size: 0.75rem;
        font-weight: 600;
    }

    .auth-link:hover,
    .auth-link:focus {
        color: var(--auth-secondary);
    }

    .auth-check {
        accent-color: var(--auth-primary);
    }

    .auth-check:checked {
        background-color: var(--auth-primary);
        border-color: var(--auth-primary);
    }

    .auth-check-label {
        font-size: 0.75rem;
        color: var(--auth-muted);
    }

    .auth-submit {
        background: var(--auth-secondary);
        color: var(--auth-text);
        border: 1px solid var(--auth-secondary);
        padding: 11px;
        border-radius: 50px;
        font-weight: 700;
        font-size: 0.9rem;
        box-shadow: 0 4px 12px color-mix(in srgb, var(--auth-secondary) 24%, transparent);
        height: 44px;
    }

    .auth-submit:hover,
    .auth-submit:focus {
        background: color-mix(in srgb, var(--auth-secondary) 88%, #000);
        border-color: color-mix(in srgb, var(--auth-secondary) 88%, #000);
        color: var(--auth-text);
    }

    .auth-footer {
        font-size: 0.7rem;
        color: var(--auth-muted);
    }

    .auth-alert {
        border-radius: 12px;
    }

    .auth-alert-danger {
        border-left: 3px solid #ef4444;
    }

    .auth-input:focus {
        outline: none !important;
        border-color: var(--auth-primary) !important;
        box-shadow: 0 0 0 3px color-mix(in srgb, var(--auth-primary) 12%, transparent) !important;
        background: white !important;
    }
    .alert {
        border: none;
    }
</style>

<script>
    document.getElementById('password-addon')?.addEventListener('click', function() {
        var passwordInput = document.getElementById('password-input');
        var icon = this.querySelector('i');
        if (passwordInput.type === 'password') {
            passwordInput.type = 'text';
            icon.classList.remove('ri-eye-fill');
            icon.classList.add('ri-eye-off-fill');
        } else {
            passwordInput.type = 'password';
            icon.classList.remove('ri-eye-off-fill');
            icon.classList.add('ri-eye-fill');
        }
    });
</script>
@endsection

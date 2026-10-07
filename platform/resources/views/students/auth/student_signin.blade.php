@php
    $configuration = $configuration ?? (function_exists('getConfiguration') ? getConfiguration() : null);
    $brandName = $configuration->name ?? config('app.name', 'ExamElite');
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>Student Login | {{ $brandName }}</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        :root {
            --auth-primary: {{ $configuration->theme_primary_color ?? $configuration->primary_color ?? 'var(--theme-primary, #0f766e)' }};
            --auth-secondary: {{ $configuration->theme_secondary_color ?? $configuration->secondary_color ?? 'var(--theme-secondary, #f59e0b)' }};
            --auth-heading: {{ $configuration->theme_heading_color ?? $configuration->heading_color ?? 'var(--theme-heading, #0f172a)' }};
            --auth-bg: {{ $configuration->theme_body_bg ?? 'var(--theme-body-bg, #f7faf9)' }};
            --auth-muted: var(--theme-muted, #64748b);
            --auth-border: var(--theme-border, #e2e8f0);
            --auth-soft: var(--theme-primary-soft, #e6f4f1);
        }
        body {
            font-family: 'Inter', sans-serif;
            background: color-mix(in srgb, var(--auth-primary) 8%, var(--auth-bg));
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            position: relative;
            overflow-y: auto;
        }
        .login-container {
            max-width: 450px;
            width: 100%;
            background: #fff;
            border-radius: 18px;
            box-shadow: 0 18px 40px -22px rgba(15,23,42,0.45);
            overflow: hidden;
            position: relative;
            z-index: 10;
            border: 1px solid var(--auth-border);
        }
        .login-header {
            background: var(--auth-primary);
            padding: 22px;
            text-align: center;
            color: white;
            cursor: pointer;
            transition: opacity 0.3s ease;
        }
        .login-header:hover { opacity: 0.95; }
        .login-header h2 { font-size: 1.5rem; font-weight: 700; margin-bottom: 5px; }
        .login-header p { font-size: 0.8rem; opacity: 0.9; }
        .login-body { padding: 25px; background: white; }
        .google-btn {
            width: 100%;
            background: var(--auth-soft);
            border: 1px solid var(--auth-border);
            padding: 12px;
            border-radius: 50px;
            font-size: 0.9rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            color: var(--auth-heading);
            text-decoration: none;
            margin-bottom: 15px;
        }
        .google-btn:hover {
            background: color-mix(in srgb, var(--auth-primary) 10%, #fff);
            border-color: var(--auth-primary);
            color: var(--auth-heading);
            transform: translateY(-2px);
            box-shadow: 0 8px 20px color-mix(in srgb, var(--auth-primary) 28%, transparent);
        }
        .google-btn i { color: #db4437; font-size: 18px; background: white; padding: 4px; border-radius: 50%; }
        .divider {
            display: flex;
            align-items: center;
            text-align: center;
            margin: 15px 0;
            color: var(--auth-muted);
            font-size: 0.75rem;
        }
        .divider::before, .divider::after {
            content: '';
            flex: 1;
            border-bottom: 1px solid var(--auth-border);
        }
        .divider::before { margin-right: 12px; }
        .divider::after { margin-left: 12px; }
        .form-group { margin-bottom: 18px; }
        .form-group label { font-size: 0.75rem; font-weight: 700; color: var(--auth-heading); margin-bottom: 5px; display: block; }
        .input-wrapper { position: relative; }
        .input-wrapper i {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--auth-primary);
            font-size: 14px;
        }
        .form-control {
            width: 100%;
            padding: 12px 14px 12px 42px;
            border: 2px solid var(--auth-border);
            border-radius: 12px;
            font-size: 14px;
            transition: all 0.3s ease;
            background: color-mix(in srgb, var(--auth-primary) 4%, #fff);
        }
        .form-control:focus {
            outline: none;
            border-color: var(--auth-primary);
            box-shadow: 0 0 0 3px color-mix(in srgb, var(--auth-primary) 16%, transparent);
            background: white;
        }
        .password-wrapper { position: relative; }
        .password-toggle {
            position: absolute;
            right: 14px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: var(--auth-muted);
            background: none;
            border: none;
            font-size: 14px;
        }
        .password-toggle:hover { color: var(--auth-primary); }
        .checkbox-group {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 22px;
        }
        .checkbox-group label { margin-bottom: 0; font-size: 0.75rem; color: #475569; cursor: pointer; }
        .checkbox-group input { margin-right: 6px; accent-color: var(--auth-primary); cursor: pointer; }
        .forgot-link { color: var(--auth-primary); text-decoration: none; font-size: 0.75rem; font-weight: 500; }
        .forgot-link:hover { text-decoration: underline; color: var(--auth-secondary); }
        .btn-login {
            width: 100%;
            background: var(--auth-secondary);
            color: var(--auth-heading);
            border: none;
            padding: 12px;
            border-radius: 12px;
            font-size: 0.9rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 10px 20px -14px var(--auth-secondary);
        }
        .btn-login:hover {
            background: color-mix(in srgb, var(--auth-secondary) 88%, #000);
            color: var(--auth-heading);
            transform: translateY(-2px);
            box-shadow: 0 14px 24px -16px var(--auth-secondary);
        }
        .signup-link {
            text-align: center;
            margin-top: 18px;
            font-size: 0.8rem;
            color: var(--auth-muted);
        }
        .signup-link a { color: var(--auth-primary); text-decoration: none; font-weight: 600; }
        .signup-link a:hover { text-decoration: underline; color: var(--auth-secondary); }
        .footer-text {
            text-align: center;
            margin-top: 18px;
            padding-top: 12px;
            border-top: 1px solid var(--auth-border);
            font-size: 0.65rem;
            color: var(--auth-muted);
        }
        .alert {
            padding: 10px 14px;
            border-radius: 12px;
            margin-bottom: 15px;
            font-size: 0.8rem;
        }
        .alert-danger { background: #fee2e2; color: #991b1b; border-left: 3px solid #ef4444; }
        .alert-success { background: var(--auth-soft); color: var(--auth-primary); border-left: 3px solid var(--auth-primary); }
        .alert-info { background: var(--auth-soft); color: var(--auth-heading); border-left: 3px solid var(--auth-primary); }
        .alert-info a { color: var(--auth-primary); font-weight: 700; }
        @media (max-width: 480px) {
            .login-body { padding: 18px; }
            .login-header { padding: 18px; }
            .login-header h2 { font-size: 1.3rem; }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <a href="{{ url('/') }}" style="text-decoration: none;">
            <div class="login-header">
                <h2>{{ $brandName }}</h2>
                <p>{{ __('ui.welcome_signin') }}</p>
            </div>
        </a>
        <div class="login-body">
            @if(session('status'))
                <div class="alert alert-success">{{ session('status') }}</div>
            @endif
            @if($errors->any())
                <div class="alert alert-danger">
                    @foreach($errors->all() as $error)
                        {{ $error }}<br>
                    @endforeach
                </div>
            @endif

            @if(! \App\Support\SaasAccess::isPlatformOrganization())
                <div class="alert alert-info" style="font-size: 0.78rem; line-height: 1.45;">
                    If your institute has registered you here, you can use the same student login on
                    <a href="{{ \App\Support\SaasAccess::defaultWebsiteUrl() }}" target="_blank" rel="noopener">ExamElite</a>
                    to explore platform packages and exams.
                </div>
            @endif

            @if(\App\Support\SaasAccess::isPlatformOrganization())
                <a href="{{ route('google.login') }}" class="google-btn">
                    <i class="fab fa-google"></i> Continue with Google
                </a>

                <div class="divider">{{ __('ui.signin_divider') }}</div>
            @endif

            <form method="POST" action="{{ route('student.signin') }}">
                @csrf
                @if(request('redirect'))
                    <input type="hidden" name="redirect" value="{{ request('redirect') }}">
                @endif
                
                <div class="form-group">
                    <label>{{ __('ui.login_identifier') }}</label>
                    <div class="input-wrapper">
                        <i class="fas fa-envelope"></i>
                        <input type="text" class="form-control" name="login" placeholder="Enter mobile, email or student ID" value="{{ old('login') }}" required>
                    </div>
                    @if(! \App\Support\SaasAccess::isPlatformOrganization())
                        <small style="display: block; margin-top: 4px; color: var(--auth-muted); font-size: 0.68rem;">
                            School students can use the ID provided by their institute.
                        </small>
                    @endif
                </div>

                <div class="form-group">
                    <label>{{ __('messages.auth_label_password') }}</label>
                    <div class="password-wrapper">
                        <i class="fas fa-lock" style="position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: var(--auth-primary); z-index: 1;"></i>
                        <input type="password" class="form-control" name="password" id="password" placeholder="Enter your password" required style="padding-left: 42px;">
                        <button type="button" class="password-toggle" onclick="togglePassword('password', this)">
                            <i class="fas fa-eye-slash"></i>
                        </button>
                    </div>
                </div>

                <div class="checkbox-group">
                    <label class="d-flex align-items-center">
                        <input type="checkbox" name="remember">
                        <span>{{ __('ui.remember_me') }}</span>
                    </label>
                    <a href="{{ route('student.password.request') }}" class="forgot-link">{{ __('messages.auth_forgot_title') }}</a>
                </div>

                <button type="submit" class="btn-login">
                    <i class="fas fa-sign-in-alt me-2"></i> {{ __('messages.auth_signin_title') }}
                </button>
            </form>

            @if(\App\Support\SaasAccess::featureEnabled('student_self_registration'))
                <div class="signup-link">
                    {{ __('messages.auth_no_account') }} <a href="{{ route('student.signup') }}">Create Account</a>
                </div>
            @endif

            <div class="footer-text">
                &copy; {{ date('Y') }} {{ $brandName }}. All rights reserved.
            </div>
        </div>
    </div>

    <script>
        function togglePassword(fieldId, btnElement) {
            const field = document.getElementById(fieldId);
            const icon = btnElement.querySelector('i');
            if (field.type === 'password') {
                field.type = 'text';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            } else {
                field.type = 'password';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            }
        }
    </script>
</body>
</html>

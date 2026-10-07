@php
    $configuration = $configuration ?? (function_exists('getConfiguration') ? getConfiguration() : null);
    $brandName = $configuration->name ?? config('app.name', 'ExamElite');
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>Reset Password | {{ $brandName }}</title>
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
            padding: 18px;
            overflow-y: auto;
        }
        .reset-container {
            max-width: 450px;
            width: 100%;
            background: #fff;
            border-radius: 18px;
            box-shadow: 0 18px 40px -22px rgba(15,23,42,0.45);
            overflow: hidden;
            border: 1px solid var(--auth-border);
        }
        .reset-header {
            background: var(--auth-primary);
            padding: 22px;
            text-align: center;
            color: white;
        }
        .reset-header h2 { font-size: 1.35rem; font-weight: 800; margin-bottom: 4px; color: white; }
        .reset-header p { font-size: 0.8rem; opacity: 0.92; color: white; margin: 0; }
        .reset-body { padding: 24px; background: white; }
        .form-group { margin-bottom: 16px; }
        .form-group label { font-size: 0.76rem; font-weight: 800; color: var(--auth-heading); margin-bottom: 6px; display: block; }
        .input-wrapper, .password-wrapper { position: relative; }
        .input-wrapper i,
        .password-wrapper > i {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--auth-primary);
            font-size: 14px;
            z-index: 1;
        }
        .form-control {
            width: 100%;
            padding: 12px 42px;
            border: 2px solid var(--auth-border);
            border-radius: 12px;
            font-size: 14px;
            transition: all 0.2s ease;
            background: color-mix(in srgb, var(--auth-primary) 4%, #fff);
        }
        .form-control:focus {
            outline: none;
            border-color: var(--auth-primary);
            box-shadow: 0 0 0 3px color-mix(in srgb, var(--auth-primary) 16%, transparent);
            background: white;
        }
        .otp-input {
            letter-spacing: 0.35em;
            text-align: center;
            font-weight: 800;
        }
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
        .btn-reset {
            width: 100%;
            background: var(--auth-secondary);
            color: var(--auth-heading);
            border: none;
            padding: 12px;
            border-radius: 12px;
            font-size: 0.9rem;
            font-weight: 800;
            cursor: pointer;
            transition: all 0.2s ease;
            box-shadow: 0 10px 20px -14px var(--auth-secondary);
            margin-top: 8px;
        }
        .btn-reset:hover,
        .btn-reset:focus,
        .btn-reset:active {
            background: color-mix(in srgb, var(--auth-secondary) 88%, #000);
            color: var(--auth-heading);
            transform: translateY(-1px);
        }
        .back-link { text-align: center; margin-top: 16px; font-size: 0.8rem; color: var(--auth-muted); }
        .back-link a { color: var(--auth-primary); text-decoration: none; font-weight: 700; }
        .back-link a:hover { color: var(--auth-secondary); text-decoration: underline; }
        .alert { padding: 10px 14px; border-radius: 12px; margin-bottom: 15px; font-size: 0.8rem; }
        .alert-danger { background: #fee2e2; color: #991b1b; border-left: 3px solid #ef4444; }
        .alert-success { background: var(--auth-soft); color: var(--auth-primary); border-left: 3px solid var(--auth-primary); }
        @media (max-width: 480px) {
            .reset-body { padding: 20px; }
            .reset-header { padding: 20px; }
        }
    </style>
</head>
<body>
    <div class="reset-container">
        <a href="{{ url('/') }}" style="text-decoration: none;">
            <div class="reset-header">
                <h2>{{ $brandName }}</h2>
                <p>{{ __('ui.otp_new_password') }}</p>
            </div>
        </a>
        <div class="reset-body">
            @if(session('error'))
                <div class="alert alert-danger">{{ session('error') }}</div>
            @endif
            @if(session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif
            @if($errors->any())
                <div class="alert alert-danger">
                    @foreach($errors->all() as $error)
                        {{ $error }}<br>
                    @endforeach
                </div>
            @endif

            <form method="POST" action="{{ route('student.password.verifyOtp') }}">
                @csrf
                <div class="alert alert-info py-2">
                    Code sent by {{ ucfirst(session('reset_channel', $student->otp_channel ?: 'email')) }} to <strong>{{ $destination }}</strong>.
                </div>

                <div class="form-group">
                    <label>{{ __('ui.otp_code') }}</label>
                    <div class="input-wrapper">
                        <i class="fas fa-shield-alt"></i>
                        <input type="text" class="form-control otp-input" name="otp" placeholder="000000" maxlength="6" inputmode="numeric" autocomplete="one-time-code" required>
                    </div>
                </div>

                <div class="form-group">
                    <label>{{ __('messages.change_pass_label_new') }}</label>
                    <div class="password-wrapper">
                        <i class="fas fa-lock"></i>
                        <input type="password" class="form-control" name="password" id="password" placeholder="Enter new password" required>
                        <button type="button" class="password-toggle" onclick="togglePassword('password', this)">
                            <i class="fas fa-eye-slash"></i>
                        </button>
                    </div>
                </div>

                <div class="form-group">
                    <label>{{ __('messages.auth_label_confirm_password') }}</label>
                    <div class="password-wrapper">
                        <i class="fas fa-lock"></i>
                        <input type="password" class="form-control" name="password_confirmation" id="password_confirmation" placeholder="Confirm your password" required>
                        <button type="button" class="password-toggle" onclick="togglePassword('password_confirmation', this)">
                            <i class="fas fa-eye-slash"></i>
                        </button>
                    </div>
                </div>

                <button type="submit" class="btn-reset">
                    <i class="fas fa-check-circle me-2"></i> {{ __('messages.auth_reset_pass_button') }}
                </button>
            </form>

            <div class="back-link">
                <a href="{{ route('student.signin') }}">&larr; Back to Sign In</a>
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

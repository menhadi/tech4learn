@php
    $configuration = $configuration ?? (function_exists('getConfiguration') ? getConfiguration() : null);
    $brandName = $configuration->name ?? config('app.name', 'ExamElite');
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>Create Account | {{ $brandName }}</title>
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
            padding: 15px;
            position: relative;
            overflow-y: auto;
        }
        .signup-container {
            max-width: 480px;
            width: 100%;
            background: #fff;
            border-radius: 18px;
            box-shadow: 0 18px 40px -22px rgba(15,23,42,0.45);
            overflow: hidden;
            position: relative;
            z-index: 10;
            border: 1px solid var(--auth-border);
        }
        .signup-header {
            background: var(--auth-primary);
            padding: 18px;
            text-align: center;
            color: white;
            position: relative;
            overflow: hidden;
            cursor: pointer;
            transition: opacity 0.3s ease;
        }
        .signup-header:hover { opacity: 0.95; }
        .signup-header h2 { font-size: 1.3rem; font-weight: 700; margin-bottom: 3px; letter-spacing: -0.5px; }
        .signup-header p { font-size: 0.7rem; opacity: 0.9; }
        .signup-body { padding: 18px 22px; background: white; }
        .google-btn {
            width: 100%;
            background: var(--auth-soft);
            border: 1px solid var(--auth-border);
            padding: 10px;
            border-radius: 50px;
            font-size: 0.8rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            color: var(--auth-heading);
            text-decoration: none;
            margin-bottom: 12px;
        }
        .google-btn:hover {
            background: color-mix(in srgb, var(--auth-primary) 10%, #fff);
            border-color: var(--auth-primary);
            color: var(--auth-heading);
            transform: translateY(-2px);
            box-shadow: 0 8px 20px color-mix(in srgb, var(--auth-primary) 28%, transparent);
        }
        .google-btn i { color: #db4437; font-size: 16px; background: white; padding: 4px; border-radius: 50%; }
        .divider {
            display: flex;
            align-items: center;
            text-align: center;
            margin: 12px 0;
            color: var(--auth-muted);
            font-size: 0.65rem;
        }
        .divider::before, .divider::after {
            content: '';
            flex: 1;
            border-bottom: 1px solid var(--auth-border);
        }
        .divider::before { margin-right: 10px; }
        .divider::after { margin-left: 10px; }
        .form-group { margin-bottom: 12px; }
        .form-group label { font-size: 0.65rem; font-weight: 700; color: var(--auth-heading); margin-bottom: 3px; display: block; }
        .input-wrapper { position: relative; }
        .input-wrapper i {
            position: absolute;
            left: 10px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--auth-primary);
            font-size: 12px;
        }
        .form-control, .form-select {
            width: 100%;
            padding: 8px 10px 8px 34px;
            border: 2px solid var(--auth-border);
            border-radius: 10px;
            font-size: 12px;
            transition: all 0.3s ease;
            background: color-mix(in srgb, var(--auth-primary) 4%, #fff);
        }
        .form-select {
            padding: 8px 10px 8px 34px;
            cursor: pointer;
        }
        .form-control:focus, .form-select:focus {
            outline: none;
            border-color: var(--auth-primary);
            box-shadow: 0 0 0 3px color-mix(in srgb, var(--auth-primary) 16%, transparent);
            background: white;
        }
        .password-wrapper { position: relative; }
        .password-toggle {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: var(--auth-muted);
            background: none;
            border: none;
            font-size: 12px;
        }
        .btn-signup {
            width: 100%;
            background: var(--auth-secondary);
            color: var(--auth-heading);
            border: none;
            padding: 9px;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 8px;
            box-shadow: 0 10px 20px -14px var(--auth-secondary);
        }
        .btn-signup:hover {
            background: color-mix(in srgb, var(--auth-secondary) 88%, #000);
            color: var(--auth-heading);
            transform: translateY(-2px);
            box-shadow: 0 14px 24px -16px var(--auth-secondary);
        }
        .login-link {
            text-align: center;
            margin-top: 12px;
            font-size: 0.7rem;
            color: var(--auth-muted);
        }
        .login-link a { color: var(--auth-primary); text-decoration: none; font-weight: 600; font-size: 0.7rem; }
        .login-link a:hover { text-decoration: underline; color: var(--auth-secondary); }
        .footer-text {
            text-align: center;
            margin-top: 12px;
            padding-top: 8px;
            border-top: 1px solid var(--auth-border);
            font-size: 0.55rem;
            color: var(--auth-muted);
        }
        .invalid-feedback {
            color: #ef4444;
            font-size: 9px;
            margin-top: 2px;
            display: block;
        }
        @media (max-width: 480px) {
            .signup-body { padding: 15px 18px; }
            .signup-header { padding: 15px; }
            .signup-header h2 { font-size: 1.2rem; }
        }
    </style>
</head>
<body>
    <div class="signup-container">
        <a href="{{ url('/') }}" style="text-decoration: none;">
            <div class="signup-header">
                <h2>{{ $brandName }}</h2>
                <p>{{ __('ui.create_account_learning') }}</p>
            </div>
        </a>
        <div class="signup-body">
            @if(\App\Support\SaasAccess::isPlatformOrganization())
                {{-- Google Login First --}}
                <a href="{{ route('google.login') }}" class="google-btn">
                    <i class="fab fa-google"></i> Continue with Google
                </a>

                <div class="divider">{{ __('ui.signup_divider') }}</div>
            @endif

            <form method="POST" action="{{ route('student.signup') }}">
                @csrf

                <div class="form-group">
                    <label>{{ __('ui.full_name') }} <span style="color: #ef4444;">*</span></label>
                    <div class="input-wrapper">
                        <i class="fas fa-user"></i>
                        <input type="text" class="form-control @error('name') is-invalid @enderror"
                               name="name" value="{{ old('name') }}" placeholder="Full name" required>
                    </div>
                    @error('name') <span class="invalid-feedback">{{ $message }}</span> @enderror
                </div>

                <div class="form-group">
                    <label>{{ __('ui.email_mobile') }} <span style="color:#ef4444;">*</span></label>
                    <div class="input-wrapper">
                        <i class="fas fa-at"></i>
                        <input type="text"
                               class="form-control @error('contact') is-invalid @enderror @error('email') is-invalid @enderror @error('phone') is-invalid @enderror"
                               name="contact"
                               value="{{ old('contact', old('email', old('phone'))) }}"
                               placeholder="Email address or 10-digit mobile number"
                               inputmode="email"
                               autocomplete="username"
                               autocapitalize="none"
                               required>
                    </div>
                    @error('contact') <span class="invalid-feedback">{{ $message }}</span> @enderror
                    @error('email') <span class="invalid-feedback">{{ $message }}</span> @enderror
                    @error('phone') <span class="invalid-feedback">{{ $message }}</span> @enderror
                    <small style="display:block;margin-top:6px;color:var(--auth-muted);">{{ __('ui.auto_send_code') }}</small>
                </div>

                <div class="form-group">
                    @php $contextGroup = isset($contextualGroupId) ? $groups->firstWhere('id', (int) $contextualGroupId) : null; @endphp
                    @if($contextGroup)
                        <label>{{ __('ui.exam_group') }}</label>
                        <input type="hidden" name="group_id" value="{{ $contextGroup->id }}">
                        <div style="padding: 10px 12px; border: 2px solid var(--auth-primary); border-radius: 10px; background: var(--auth-soft); color: var(--auth-heading); font-size: 12px; font-weight: 700;">
                            <i class="fas fa-layer-group" style="color: var(--auth-primary); margin-right: 7px;"></i>
                            {{ $contextGroup->group_name }}
                            <small style="display:block; margin:4px 0 0 21px; color:var(--auth-muted); font-weight:500;">{{ __('ui.selected_automatically') }}</small>
                        </div>
                    @else
                        <label>{{ __('ui.select_group') }} <span style="color: #ef4444;">*</span></label>
                        <div class="input-wrapper">
                            <i class="fas fa-layer-group"></i>
                            <select class="form-select @error('group_id') is-invalid @enderror" name="group_id" required>
                                <option value="" selected disabled>{{ __('ui.choose_exam_group_your') }}</option>
                                @foreach ($groups ?? [] as $group)
                                    <option value="{{ $group->id }}" {{ old('group_id') == $group->id ? 'selected' : '' }}>{{ $group->group_name }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif
                    @error('group_id') <span class="invalid-feedback">{{ $message }}</span> @enderror
                </div>

                <div class="form-group">
                    <label>{{ __('messages.auth_label_password') }} <span style="color: #ef4444;">*</span></label>
                    <div class="password-wrapper">
                        <i class="fas fa-lock" style="position: absolute; left: 10px; top: 50%; transform: translateY(-50%); color: var(--auth-primary); z-index: 1;"></i>
                        <input type="password" class="form-control @error('password') is-invalid @enderror"
                               name="password" id="password" placeholder="Create password" required style="padding-left: 34px;">
                        <button type="button" class="password-toggle" onclick="togglePassword('password', this)">
                            <i class="fas fa-eye-slash"></i>
                        </button>
                    </div>
                    @error('password') <span class="invalid-feedback">{{ $message }}</span> @enderror
                </div>

                <button type="submit" class="btn-signup">
                    <i class="fas fa-user-plus me-2"></i> {{ __('messages.auth_signup_button') }}
                </button>
            </form>

            <div class="login-link">
                {{ __('messages.auth_already_account') }} <a href="{{ route('student.signin') }}">{{ __('messages.auth_signin_title') }}</a>
            </div>

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

@php
    $configuration = $configuration ?? (function_exists('getConfiguration') ? getConfiguration() : null);
    $brandName = $configuration->name ?? config('app.name', 'ExamElite');
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>Forgot Password | {{ $brandName }}</title>
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
        .forgot-container {
            max-width: 430px;
            width: 100%;
            background: #fff;
            border-radius: 18px;
            box-shadow: 0 18px 40px -22px rgba(15,23,42,0.45);
            overflow: hidden;
            border: 1px solid var(--auth-border);
        }
        .forgot-header {
            background: var(--auth-primary);
            padding: 22px;
            text-align: center;
            color: white;
            text-decoration: none;
            display: block;
        }
        .forgot-header h2 { font-size: 1.35rem; font-weight: 800; margin-bottom: 4px; color: white; }
        .forgot-header p { font-size: 0.8rem; opacity: 0.92; color: white; margin: 0; }
        .forgot-body { padding: 24px; background: white; }
        .forgot-icon {
            width: 58px;
            height: 58px;
            border-radius: 16px;
            background: var(--auth-soft);
            color: var(--auth-primary);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 16px;
            font-size: 24px;
        }
        .info-text { text-align: center; margin-bottom: 20px; }
        .info-text p { color: var(--auth-muted); font-size: 0.86rem; line-height: 1.5; }
        .form-group { margin-bottom: 16px; }
        .form-group label { font-size: 0.76rem; font-weight: 800; color: var(--auth-heading); margin-bottom: 6px; display: block; }
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
            transition: all 0.2s ease;
            background: color-mix(in srgb, var(--auth-primary) 4%, #fff);
        }
        .form-control:focus {
            outline: none;
            border-color: var(--auth-primary);
            box-shadow: 0 0 0 3px color-mix(in srgb, var(--auth-primary) 16%, transparent);
            background: white;
        }
        .btn-send {
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
        }
        .btn-send:hover,
        .btn-send:focus,
        .btn-send:active {
            background: color-mix(in srgb, var(--auth-secondary) 88%, #000);
            color: var(--auth-heading);
            transform: translateY(-1px);
        }
        .back-link { text-align: center; margin-top: 16px; font-size: 0.8rem; color: var(--auth-muted); }
        .back-link a { color: var(--auth-primary); text-decoration: none; font-weight: 700; }
        .back-link a:hover { color: var(--auth-secondary); text-decoration: underline; }
        .footer-text {
            text-align: center;
            margin-top: 16px;
            padding-top: 12px;
            border-top: 1px solid var(--auth-border);
            font-size: 0.64rem;
            color: var(--auth-muted);
        }
        .alert { padding: 10px 14px; border-radius: 12px; margin-bottom: 15px; font-size: 0.8rem; }
        .alert-danger { background: #fee2e2; color: #991b1b; border-left: 3px solid #ef4444; }
        .alert-success { background: var(--auth-soft); color: var(--auth-primary); border-left: 3px solid var(--auth-primary); }
        @media (max-width: 480px) {
            .forgot-body { padding: 20px; }
            .forgot-header { padding: 20px; }
        }
    </style>
</head>
<body>
    <div class="forgot-container">
        <a href="{{ url('/') }}" class="forgot-header">
            <h2>{{ $brandName }}</h2>
            <p>{{ __('ui.reset_password') }}</p>
        </a>
        <div class="forgot-body">
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

            <div class="forgot-icon"><i class="fas fa-key"></i></div>
            <div class="info-text">
                <p>{{ __('ui.reset_contact_copy') }}</p>
            </div>

            <form method="POST" action="{{ route('student.password.sendOtp') }}">
                @csrf
                <div class="form-group">
                    <label>{{ __('ui.email_or_mobile') }}</label>
                    <div class="input-wrapper">
                        <i class="fas fa-user"></i>
                        <input type="text" class="form-control @error('identifier') is-invalid @enderror" name="identifier" placeholder="Email address or 10-digit mobile" value="{{ old('identifier') }}" required autofocus autocomplete="username">
                    </div>
                    @error('identifier')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                </div>
                @if(($configuration->sms_provider ?? null) || ($configuration->whatsapp_provider ?? null))
                <div class="form-group">
                    <label>{{ __('ui.send_code_by') }}</label>
                    <select name="channel" class="form-control">
                        <option value="">{{ __('ui.recommended_method') }}</option>
                        <option value="email">{{ __('messages.profile_label_email') }}</option>
                        @if($configuration->sms_provider)<option value="sms">SMS</option>@endif
                        @if($configuration->whatsapp_provider)<option value="whatsapp">WhatsApp</option>@endif
                    </select>
                </div>
                @endif
                <button type="submit" class="btn-send">
                    <i class="fas fa-paper-plane me-2"></i> Send verification code
                </button>
            </form>

            <div class="back-link">
                <a href="{{ route('student.signin') }}">&larr; Back to Sign In</a>
            </div>

            <div class="footer-text">
                &copy; {{ date('Y') }} {{ $brandName }}. All rights reserved.
            </div>
        </div>
    </div>
</body>
</html>

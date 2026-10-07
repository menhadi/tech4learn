@extends('website.layouts.app')

@section('title', '419 - Session Expired | ExamElite')

@section('content')
<style>
    .error-wrapper {
        min-height: 70vh;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 3rem 1.5rem;
        background: linear-gradient(135deg, #f8fafc 0%, #f0fdf4 100%);
    }
    .error-card {
        max-width: 600px;
        width: 100%;
        background: white;
        border-radius: 2rem;
        box-shadow: 0 20px 40px -12px rgba(0,0,0,0.1);
        padding: 3rem 2rem;
        text-align: center;
        border: 1px solid rgba(13,148,136,0.1);
    }
    .error-code {
        font-size: 8rem;
        font-weight: 800;
        background: linear-gradient(135deg, #0d9488 0%, #14b8a6 50%, #99f6e4 100%);
        -webkit-background-clip: text;
        background-clip: text;
        color: transparent;
        line-height: 1;
        margin-bottom: 1rem;
        text-shadow: 2px 2px 20px rgba(13,148,136,0.2);
    }
    .error-title {
        font-size: 1.8rem;
        font-weight: 700;
        color: #1e293b;
        margin-bottom: 1rem;
    }
    .error-message {
        color: #64748b;
        font-size: 1rem;
        line-height: 1.6;
        margin-bottom: 2rem;
    }
    .btn-error {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.75rem 1.8rem;
        border-radius: 50px;
        font-weight: 600;
        font-size: 0.9rem;
        transition: all 0.3s ease;
        text-decoration: none;
        margin: 0.25rem;
    }
    .btn-primary-error {
        background: linear-gradient(135deg, #0d9488, #0f766e);
        color: white;
        border: none;
    }
    .btn-primary-error:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(13,148,136,0.3);
        color: white;
    }
    .btn-outline-error {
        background: white;
        color: #0d9488;
        border: 1px solid #ccfbf1;
    }
    .btn-outline-error:hover {
        background: #ccfbf1;
        transform: translateY(-2px);
        color: #0f766e;
    }
    .error-links {
        margin-top: 2rem;
        display: flex;
        justify-content: center;
        gap: 1rem;
        flex-wrap: wrap;
    }
    .error-links a {
        color: #94a3b8;
        text-decoration: none;
        font-size: 0.85rem;
        transition: color 0.2s;
    }
    .error-links a:hover {
        color: #0d9488;
    }
    @media (max-width: 550px) {
        .error-code { font-size: 5rem; }
        .error-title { font-size: 1.3rem; }
        .error-card { padding: 2rem 1.5rem; }
    }
</style>

<div class="error-wrapper">
    <div class="error-card">
        <div class="error-code">419</div>
        <div class="error-title">Session Expired</div>
        <div class="error-message">
            Your session has expired due to inactivity.<br>
            Please refresh the page and try again.
        </div>
        <div>
            <a href="{{ url('/') }}" class="btn-error btn-primary-error">
                <i class="ri-home-4-line"></i> Go to Homepage
            </a>
            <button onclick="location.reload()" class="btn-error btn-outline-error">
                <i class="ri-refresh-line"></i> Refresh Page
            </button>
        </div>
        <div class="error-links">
            <a href="{{ route('login') }}"><i class="ri-login-box-line"></i> Login Again</a>
            <a href="{{ url('/contact') }}"><i class="ri-mail-line"></i> Need Help?</a>
        </div>
    </div>
</div>

<script>
    // Auto redirect to login after 5 seconds
    setTimeout(function() {
        window.location.href = "{{ route('login') }}";
    }, 5000);
</script>
@endsection

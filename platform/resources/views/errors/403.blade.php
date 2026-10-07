@extends('website.layouts.app')

@section('title', '403 - Access Denied | ExamElite')

@section('content')
<style>
    .error-wrapper {
        min-height: 70vh;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 3rem 1.5rem;
        background: linear-gradient(135deg, #f8fafc 0%, #fef2f2 100%);
    }
    .error-card {
        max-width: 600px;
        width: 100%;
        background: white;
        border-radius: 2rem;
        box-shadow: 0 20px 40px -12px rgba(0,0,0,0.1);
        padding: 3rem 2rem;
        text-align: center;
        border: 1px solid rgba(239,68,68,0.1);
    }
    .error-code {
        font-size: 8rem;
        font-weight: 800;
        background: linear-gradient(135deg, #ef4444 0%, #dc2626 50%, #fca5a5 100%);
        -webkit-background-clip: text;
        background-clip: text;
        color: transparent;
        line-height: 1;
        margin-bottom: 1rem;
        text-shadow: 2px 2px 20px rgba(239,68,68,0.2);
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
        background: linear-gradient(135deg, #ef4444, #dc2626);
        color: white;
        border: none;
    }
    .btn-primary-error:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(239,68,68,0.3);
        color: white;
    }
    .btn-outline-error {
        background: white;
        color: #ef4444;
        border: 1px solid #fecaca;
    }
    .btn-outline-error:hover {
        background: #fef2f2;
        transform: translateY(-2px);
        color: #dc2626;
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
        color: #ef4444;
    }
    @media (max-width: 550px) {
        .error-code { font-size: 5rem; }
        .error-title { font-size: 1.3rem; }
        .error-card { padding: 2rem 1.5rem; }
    }
</style>

<div class="error-wrapper">
    <div class="error-card">
        <div class="error-code">403</div>
        <div class="error-title">Access Denied</div>
        <div class="error-message">
            You don't have permission to access this page.<br>
            Please contact support if you believe this is an error.
        </div>
        <div>
            <a href="{{ url('/') }}" class="btn-error btn-primary-error">
                <i class="ri-home-4-line"></i> Back to Home
            </a>
            <a href="{{ url('/dashboard') }}" class="btn-error btn-outline-error">
                <i class="ri-dashboard-line"></i> Go to Dashboard
            </a>
        </div>
        <div class="error-links">
            <a href="{{ route('login') }}"><i class="ri-login-box-line"></i> Login to Different Account</a>
            <a href="{{ url('/contact') }}"><i class="ri-mail-line"></i> Contact Support</a>
        </div>
    </div>
</div>
@endsection

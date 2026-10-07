@extends('layouts.master-without-nav')
@section('title')
    @lang('messages.auth_signin_title')
@endsection

{{-- Naya CSS (Flag Dropdown ke liye) --}}
@push('styles')
<style>
    .auth-lang-btn {
        position: absolute;
        top: 1.5rem;
        right: 1.5rem;
        z-index: 10; /* Taaki yeh form ke uppar dikhe */
    }
    .auth-lang-btn .dropdown-menu {
        min-width: auto;
    }
    .auth-lang-btn .btn-ghost-secondary {
        color: #6c757d;
    }
    .auth-lang-btn .btn-ghost-secondary:hover {
        background-color: rgba(0,0,0,0.05);
    }
</style>
@endpush

@section('content')
    {{-- Humne yahaan default light background use kiya hai --}}
    <div class="auth-page-wrapper py-5 d-flex justify-content-center align-items-center min-vh-100"
        style="background: #f3f3f9;">

        {{-- Removed bg-overlay --}}

        <div class="auth-page-content overflow-hidden pt-lg-5">
            <div class="container">
                <div class="row justify-content-center">
                    {{-- Column width thodi badha di hai taaki split layout fit ho sake --}}
                    <div class="col-lg-10">
                        <div class="card overflow-hidden shadow-lg border-0">
                            <div class="row g-0">
                                {{-- ======== Left Side (Quote & Logo) - Desktop Only ======== --}}
                                <div class="col-lg-6 d-none d-lg-block"
                                    style="background: linear-gradient(135deg, #405189, #5e73c8); color: white; padding: 3.5rem; position: relative;">

                                    {{-- 1. Logo (Light version) --}}
                                    <div>
                                        {{-- ✅✅✅ LOGO LINK FIX (Desktop) ✅✅✅ --}}
                                        <a href="/" class="d-inline-block auth-logo">
                                            @if (isset($configuration->logo))
                                                <img src="{{ asset('storage/' . $configuration->logo) }}" alt=""
                                                    height="50">
                                            @else
                                                <img src="{{ URL::asset('build/images/logo-light.png') }}" alt=""
                                                    height="25">
                                            @endif
                                        </a>
                                    </div>

                                    {{-- 2. Motivational Quote --}}
                                    <div class="mt-5">
                                        <h3 class="text-white">@lang('messages.auth_quote')</h3>
                                        <p class="text-white-75 mt-3 fs-16">@lang('messages.auth_quote_author')</p>
                                    </div>

                                    {{-- 3. Student Icon (Subtle) --}}
                                    <div style="position: absolute; bottom: 2rem; left: 2rem; opacity: 0.1;">
                                        <i class="ri-graduation-cap-line" style="font-size: 150px; line-height: 1;"></i>
                                    </div>
                                </div>

                                {{-- ======== Right Side (Sign-in Form) ======== --}}
                                <div class="col-lg-6" style="position: relative;"> {{-- Relative position add kiya --}}
                                
                                    {{-- ⭐ Language Switcher (Updated) ⭐ --}}
                                    <div class="auth-lang-btn dropdown">
                                        <button type="button" class="btn btn-icon btn-ghost-secondary rounded-circle shadow-none"
                                            data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                            
                                            @php $locale = session('locale', 'en'); @endphp
                                            
                                            @if($locale == 'hi')
                                                <img src="{{ asset('build/images/flags/in.svg') }}" alt="Hindi" height="20" class="rounded">
                                            @else
                                                <img src="{{ asset('build/images/flags/us.svg') }}" alt="English" height="20" class="rounded">
                                            @endif
                                        </button>
                                        <div class="dropdown-menu dropdown-menu-end">
                                            {{-- English --}}
                                            <a href="{{ route('lang.swap', ['locale' => 'en']) }}" class="dropdown-item notify-item language py-2">
                                                <img src="{{ asset('build/images/flags/us.svg') }}" alt="user-image" class="me-2 rounded" height="18">
                                                <span class="align-middle">English</span>
                                            </a>
                                            {{-- Hindi --}}
                                            <a href="{{ route('lang.swap', ['locale' => 'hi']) }}" class="dropdown-item notify-item language py-2">
                                                <img src="{{ asset('build/images/flags/in.svg') }}" alt="user-image" class="me-2 rounded" height="18">
                                                <span class="align-middle">हिंदी (Hindi)</span>
                                            </a>
                                        </div>
                                    </div>
                                    {{-- ⭐ Language Switcher End ⭐ --}}

                                    <div class="p-lg-5 p-4">

                                        {{-- Logo for Mobile view --}}
                                        <div class="d-lg-none text-center mb-4">
                                            {{-- ✅✅✅ LOGO LINK FIX (Mobile) ✅✅✅ --}}
                                            <a href="/" class="d-inline-block auth-logo">
                                                @if (isset($configuration->logo))
                                                    <img src="{{ asset('storage/' . $configuration->logo) }}" alt=""
                                                        height="50">
                                                @else
                                                    <img src="{{ URL::asset('build/images/logo-dark.png') }}" alt=""
                                                        height="25">
                                                @endif
                                            </a>
                                        </div>

                                        <div>
                                            <h5 class="text-primary">@lang('messages.auth_welcome_back')</h5>
                                            <p class="text-muted">@lang('messages.auth_signin_continue')</p>
                                        </div>

                                        <div class="mt-4">

                                            @if (session('success'))
                                                <div class="alert alert-success alert-dismissible shadow fade show"
                                                    role="alert">
                                                    {{ session('success') }}
                                                    <button type="button" class="btn-close" data-bs-dismiss="alert"
                                                        aria-label="Close"></button>
                                                </div>
                                            @endif

                                            @if ($errors->any())
                                                <div class="alert alert-danger alert-dismissible shadow fade show"
                                                    role="alert">
                                                    @foreach ($errors->all() as $error)
                                                        <p class="mb-0">{{ $error }}</p>
                                                    @endforeach
                                                    <button type="button" class="btn-close" data-bs-dismiss="alert"
                                                        aria-label="Close"></button>
                                                </div>
                                            @endif

                                            <form action="{{ route('student.signin') }}" method="POST">
                                                @csrf
                                                <div class="mb-3">
                                                    <label for="login" class="form-label">@lang('messages.auth_label_email')</label>
                                                    <input type="text" class="form-control" id="login"
                                                        name="login" placeholder="@lang('messages.auth_placeholder_email')" required>
                                                </div>

                                                <div class="mb-3">
                                                    <div class="float-end">
                                                        <a href="{{ route('student.password.request') }}"
                                                            class="text-muted">@lang('messages.auth_forgot_password')</a>
                                                    </div>
                                                    <label class="form-label" for="password-input">@lang('messages.auth_label_password')</label>
                                                    <div class="position-relative auth-pass-inputgroup mb-3">
                                                        <input type="password"
                                                            class="form-control pe-5 password-input"
                                                            placeholder="@lang('messages.auth_placeholder_password')" id="password-input"
                                                            name="password" required>
                                                        <button
                                                            class="btn btn-link position-absolute end-0 top-0 text-decoration-none shadow-none text-muted password-addon"
                                                            type="button" id="password-addon"><i
                                                                class="ri-eye-fill align-middle"></i></button>
                                                    </div>
                                                </div>

                                                <div class="mt-4">
                                                    <button class="btn btn-success w-100" type="submit">@lang('messages.auth_signin_button')</button>
                                                </div>
                                            </form>
                                        </div>

                                        <div class="mt-5 text-center">
                                            <p class="mb-0">@lang('messages.auth_no_account') <a
                                                    href="{{ route('student.signup') }}"
                                                    class="fw-semibold text-primary text-decoration-underline"> @lang('messages.auth_signup_link')</a>
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        </div>
                    </div>
                </div>
            </div>
        <footer class="footer bg-transparent">
            <div class="container">
                <div class="row">
                    <div class="col-lg-12">
                        <div class="text-center">
                            <p class="mb-0 text-muted">&copy;
                                <script>
                                    document.write(new Date().getFullYear())
                                </script> Examframe. @lang('messages.auth_footer_crafted') <i
                                    class="mdi mdi-heart text-danger"></i> by ExamFrame
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </footer>
        </div>
@endsection
@section('script')
    <script src="{{ URL::asset('build/js/pages/form-validation.init.js') }}"></script>
    {{-- Password toggle script (No change) --}}
    <script>
        document.getElementById('password-addon').addEventListener('click', function() {
            var passwordInput = document.getElementById('password-input');
            var passwordAddonIcon = this.querySelector('i');
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                passwordAddonIcon.classList.remove('ri-eye-fill');
                passwordAddonIcon.classList.add('ri-eye-off-fill');
            } else {
                passwordInput.type = 'password';
                passwordAddonIcon.classList.remove('ri-eye-off-fill');
                passwordAddonIcon.classList.add('ri-eye-fill');
            }
        });
    </script>
@endsection
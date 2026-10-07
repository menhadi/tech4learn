@extends('students.layouts.app')

@section('title') @lang('messages.change_pass_title') @endsection

@section('css')
<style>
    .student-profile-card {
        border: 1px solid var(--el-border);
        border-radius: 8px;
        box-shadow: none;
        background: #fff;
        overflow: hidden;
    }
    .student-profile-card .card-header {
        background: #fff;
        border-bottom: 1px solid var(--el-border);
        padding: 18px 22px;
    }
    .student-profile-nav .nav-link {
        border-radius: 6px;
        color: var(--el-heading);
        font-weight: 700;
        padding: 10px 12px;
        margin-bottom: 6px;
    }
    .student-profile-nav .nav-link:hover,
    .student-profile-nav .nav-link.active {
        background: var(--el-primary) !important;
        color: var(--theme-button-text, #fff) !important;
    }
    .student-profile-form .form-label {
        color: var(--el-heading);
        font-weight: 700;
    }
    .student-profile-form .form-control {
        min-height: 44px;
        border-color: var(--el-border);
        color: var(--el-heading);
    }
    .student-profile-form .form-control:focus {
        border-color: var(--el-primary);
        box-shadow: 0 0 0 .16rem rgba(var(--el-primary-rgb), .14);
    }
    .btn.student-profile-primary,
    .btn.student-profile-primary:hover,
    .btn.student-profile-primary:focus {
        background: var(--el-primary) !important;
        border-color: var(--el-primary) !important;
        color: var(--theme-button-text, #fff) !important;
        border-radius: 6px;
        font-weight: 800;
        box-shadow: none;
    }
</style>
@endsection

@section('content')
<div class="row">
    {{-- === SIDEBAR (Consistent UI) === --}}
    <div class="col-lg-3">
        <div class="card sticky-top student-profile-card" style="top: 80px;">
            <div class="card-header">
                <h4 class="card-title mb-0">@lang('messages.profile_title')</h4>
            </div>
            <div class="card-body">
                <ul class="nav flex-column nav-pills student-profile-nav">
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('student.profile') }}">
                            <i class="ri-user-line align-bottom me-1"></i> @lang('messages.edit_profile_nav_profile')
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('student.editProfile') }}">
                             <i class="ri-edit-line align-bottom me-1"></i> @lang('messages.edit_profile_nav_edit')
                        </a>
                    </li>
                    <li class="nav-item">
                        {{-- Added 'active' class --}}
                        <a class="nav-link active" href="{{ route('student.changePassword') }}">
                            <i class="ri-key-2-line align-bottom me-1"></i> @lang('messages.edit_profile_nav_change_pass')
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </div>

    {{-- Right Content Area --}}
    <div class="col-lg-9">
        <div class="card student-profile-card">
            <div class="card-header">
                <h4 class="card-title mb-0">@lang('messages.change_pass_card_title')</h4>
            </div>
            <div class="card-body">
                {{-- Note: Error messages are handled by Laravel validation, no translation needed here --}}
                @if ($errors->any())
                <div class="alert alert-danger alert-dismissible shadow fade show" role="alert">
                    @foreach ($errors->all() as $error)
                    <p>{{ $error }}</p>
                    @endforeach
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="{{ __('messages.my_exams_modal_close_button') }}"></button>
                </div>
                @endif
                <form method="POST" action="{{ route('student.changePassword') }}" class="student-profile-form">
                    @csrf
                    <div class="mb-3">
                        <label for="old_password" class="form-label">@lang('messages.change_pass_label_old')</label>
                        <div class="position-relative auth-pass-inputgroup mb-3">
                            <input type="password" class="form-control pe-5 password-input" id="old_password"
                                name="old_password" required>
                            
                            {{-- ⭐ FIX: Yahaan 'class="..."' ko theek kar diya hai --}}
                            <button
                                class="btn btn-link position-absolute end-0 top-0 text-decoration-none shadow-none text-muted password-addon"
                                type="button"><i class="ri-eye-fill align-middle"></i></button>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="password" class="form-label">@lang('messages.change_pass_label_new')</label>
                        <div class="position-relative auth-pass-inputgroup mb-3">
                            <input type="password" class="form-control pe-5 password-input" id="password"
                                name="password" required>
                                
                            {{-- ⭐ FIX: Yahaan 'class="..."' ko theek kar diya hai --}}
                            <button
                                class="btn btn-link position-absolute end-0 top-0 text-decoration-none shadow-none text-muted password-addon"
                                type="button"><i class="ri-eye-fill align-middle"></i></button>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="password_confirmation" class="form-label">@lang('messages.change_pass_label_confirm')</label>
                        <div class="position-relative auth-pass-inputgroup mb-3">
                            <input type="password" class="form-control pe-5 password-input" id="password_confirmation"
                                name="password_confirmation" required>
                                
                            {{-- ⭐ FIX: Yahaan 'class="..."' ko theek kar diya hai --}}
                            <button
                                class="btn btn-link position-absolute end-0 top-0 text-decoration-none shadow-none text-muted password-addon"
                                type="button"><i class="ri-eye-fill align-middle"></i></button>
                        </div>
                    </div>
                    <button type="submit" class="btn student-profile-primary">@lang('messages.change_pass_submit_button')</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@section('script')
<script>
    // Password toggle script (ab yeh 'password-addon' class ko dhoondh payega)
    document.querySelectorAll('.password-addon').forEach(function (button) {
        button.addEventListener('click', function () {
            var passwordInput = this.previousElementSibling;
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
    });
</script>
@endsection

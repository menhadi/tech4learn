@extends('students.layouts.app')

@section('title') @lang('messages.edit_profile_title') @endsection

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
    .student-profile-nav .nav-link:hover {
        color: var(--theme-button-text, #fff) !important;
        background: var(--el-primary) !important;
    }
    .student-profile-nav .nav-link.active {
        background: var(--el-primary) !important;
        color: var(--theme-button-text, #fff) !important;
        box-shadow: none;
    }
    .student-profile-form .form-label,
    .student-profile-form .col-form-label {
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
    .btn.student-profile-secondary,
    .btn.student-profile-secondary:hover,
    .btn.student-profile-secondary:focus {
        background: var(--el-secondary) !important;
        border-color: var(--el-secondary) !important;
        color: var(--theme-button-text, #fff) !important;
        border-radius: 6px;
        font-weight: 800;
        box-shadow: none;
    }
</style>
@endsection

@section('content')
<div class="row">
    {{-- Left Sidebar Menu (Consistent look ke liye profile page jaisa rakha hai) --}}
    <div class="col-lg-3">
        <div class="card sticky-top student-profile-card" style="top: 80px;"> {{-- Added sticky-top --}}
            <div class="card-header">
                <h4 class="card-title mb-0">@lang('messages.profile_title')</h4>
            </div>
            <div class="card-body">
                <ul class="nav flex-column nav-pills student-profile-nav"> {{-- Used nav-pills for active state --}}
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('student.profile') }}">
                            <i class="ri-user-line align-bottom me-1"></i> @lang('messages.edit_profile_nav_profile')
                        </a>
                    </li>
                    <li class="nav-item">
                        {{-- Added 'active' class --}}
                        <a class="nav-link active" href="{{ route('student.editProfile') }}">
                             <i class="ri-edit-line align-bottom me-1"></i> @lang('messages.edit_profile_nav_edit')
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('student.changePassword') }}">
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
                <h4 class="card-title mb-0">@lang('messages.edit_profile_card_title')</h4>
            </div>
            <div class="card-body">
                {{-- ✅ FORM TAG UPDATED: Added enctype for file uploads --}}
                <form method="POST" action="{{ route('student.editProfile') }}" enctype="multipart/form-data" class="student-profile-form">
                    @csrf

                    {{-- === ✅ NEW PHOTO UPLOAD SECTION === --}}
                    <div class="mb-4 row align-items-center">
                        <label for="photo" class="col-sm-3 col-form-label">@lang('messages.edit_profile_label_photo')</label>
                        <div class="col-sm-9 d-flex align-items-center">
                            @php
                                $avatar = $student->photo ?? null;
                                $defaultAvatar = 'https://ui-avatars.com/api/?name=' . urlencode($student->name) . '&size=100&background=eef0f5&color=667085';
                            @endphp
                            <img src="{{ $avatar ? asset('storage/' . $avatar) : $defaultAvatar }}" alt="Current Avatar"
                                 class="rounded-circle me-3" style="width: 60px; height: 60px; object-fit: cover;">
                            <div>
                                <input type="file" class="form-control @error('photo') is-invalid @enderror"
                                       id="photo" name="photo" accept="image/png, image/jpeg, image/gif">
                                <small class="text-muted">@lang('messages.edit_profile_photo_hint')</small>
                                @error('photo')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>
                    {{-- === END PHOTO UPLOAD SECTION === --}}


                    <div class="mb-3 row">
                        <label for="enroll" class="col-sm-3 col-form-label">@lang('messages.edit_profile_label_enrollment')</label>
                        <div class="col-sm-9">
                            <input type="text" class="form-control @error('enroll') is-invalid @enderror"
                                   id="enroll" name="enroll" value="{{ old('enroll', $student->enroll) }}" required>
                             @error('enroll') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>

                    <div class="mb-3 row">
                        <label for="guardian_phone" class="col-sm-3 col-form-label">@lang('messages.edit_profile_label_alt_phone')</label>
                         <div class="col-sm-9">
                            <input type="text" class="form-control @error('guardian_phone') is-invalid @enderror"
                                   id="guardian_phone" name="guardian_phone" value="{{ old('guardian_phone', $student->guardian_phone) }}">
                             @error('guardian_phone') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>

                    <div class="mb-3 row">
                        <label for="address" class="col-sm-3 col-form-label">@lang('messages.edit_profile_label_address')</label>
                         <div class="col-sm-9">
                            <textarea class="form-control @error('address') is-invalid @enderror"
                                      id="address" name="address" rows="3" required>{{ old('address', $student->address) }}</textarea>
                             @error('address') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>

                    <div class="row mt-4">
                        <div class="col-sm-9 offset-sm-3">
                             <button type="submit" class="btn student-profile-primary">
                                <i class="ri-save-line align-bottom me-1"></i> @lang('messages.edit_profile_save_button')
                             </button>
                             <a href="{{ route('student.profile') }}" class="btn student-profile-secondary ms-2">@lang('messages.edit_profile_cancel_button')</a>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

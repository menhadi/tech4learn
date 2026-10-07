@extends('students.layouts.app')

@section('title') @lang('messages.profile_title') @endsection

@php
    // Controller se $student variable aa raha hai
    $avatar = $student->photo ?? null;
    $defaultAvatar = 'https://ui-avatars.com/api/?name=' . urlencode($student->name) . '&size=220&background=0f766e&color=fff';
@endphp

@section('css')
    {{-- Page ko modern look dene ke liye naya CSS --}}
    <style>
        .profile-cover {
            position: relative;
            height: 180px;
            border-radius: 8px;
            background: var(--el-primary);
            overflow: hidden;
            border: 1px solid var(--el-border);
            box-shadow: none;
        }

        .profile-cover::after {
            content: none;
            position: absolute;
            inset: 0;
        }

        .avatar-xl {
            width: 110px;
            height: 110px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid #fff;
            box-shadow: none;
            background: #fff;
        }

        .soft-card {
            border: 1px solid var(--el-border);
            border-radius: 8px;
            box-shadow: none;
            background: #fff;
        }
        .soft-card .card-title,
        .soft-card h4,
        .soft-card h6 {
            color: var(--el-heading);
        }

        .soft-card .card-body {
            padding: 1.2rem 1.2rem;
        }

        .label-sm {
            font-size: .835rem;
            color: var(--el-muted);
            font-weight: 700;
        }

        .value-lg {
            font-weight: 700;
            color: var(--el-heading);
        }

        .grid-info {
            display: grid;
            grid-template-columns: 200px 1fr;
            gap: .5rem 1.25rem;
        }

        @media(max-width:768px) {
            .grid-info {
                grid-template-columns: 1fr;
                gap: .75rem;
            }
            .label-sm { font-size: .8rem; }
            .value-lg { font-size: .95rem; }
        }

        .chip {
            display: inline-flex;
            align-items: center;
            gap: .4rem;
            padding: .35rem .6rem;
            border-radius: 6px;
            background: var(--el-secondary);
            color: var(--theme-button-text, #fff);
            font-size: .78rem;
            font-weight: 800;
        }
        .btn.profile-action-primary,
        .btn.profile-action-primary:hover,
        .btn.profile-action-primary:focus {
            background: var(--el-primary) !important;
            border-color: var(--el-primary) !important;
            color: var(--theme-button-text, #fff) !important;
            font-weight: 700;
            border-radius: 6px;
            box-shadow: none;
        }
        .btn.profile-action-secondary,
        .btn.profile-action-secondary:hover,
        .btn.profile-action-secondary:focus {
            background: var(--el-secondary) !important;
            border-color: var(--el-secondary) !important;
            color: var(--theme-button-text, #fff) !important;
            font-weight: 700;
            border-radius: 6px;
            box-shadow: none;
        }
    </style>
@endsection

@section('content')
    <div class="row">
        <div class="col-12">
            {{-- ======== COVER IMAGE ======== --}}
            <div class="profile-cover mb-5 position-relative">
                <div class="position-absolute bottom-0 start-0 translate-middle-y" style="left:2rem;">
                    
                    {{-- ✅ AVATAR FIX:
                        Aapka avatar $student->photo mein hai.
                        Database mein path relative hai (e.g., 'students/profile_photos/...')
                        isliye hum asset('storage/' . ...) use karenge. --}}
                    <img class="avatar-xl"
                        src="{{ $avatar ? asset('storage/' . $avatar) : $defaultAvatar }}"
                        alt="Avatar">
                </div>
                <div class="position-absolute bottom-0 end-0 p-3">
                    <span class="chip"><i class="ri-shield-check-line"></i> @lang('messages.profile_chip_secured')</span>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        {{-- ======== LEFT: Profile Details ======== --}}
        <div class="col-lg-8">
            <div class="card soft-card">
                <div class="card-body">
                    
                    {{-- ======== HEADER + ACTION BUTTONS ======== --}}
                    <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap">
                        <div>
                            <h4 class="mb-0">{{ $student->name }}</h4>
                            
                            {{-- ✅ DATA FIX: Database mein column 'enroll' hai --}}
                            <div class="text-muted">@lang('messages.profile_enrollment_no') <strong>{{ $student->enroll ?? '—' }}</strong></div>
                        </div>
                        <div class="d-flex gap-2 mt-2 mt-md-0">
                            
                            {{-- ✅ LINK FIX:
                                Routes `student_routes.php` se liye gaye hain.
                                'student.editProfile' aur 'student.changePassword' sahi naam hain. --}}
                            <a href="{{ route('student.editProfile') }}" class="btn profile-action-primary btn-sm">
                                <i class="ri-edit-line align-bottom"></i> @lang('messages.profile_edit_button')
                            </a>
                            <a href="{{ route('student.changePassword') }}" class="btn profile-action-secondary btn-sm">
                                <i class="ri-key-2-line align-bottom"></i> @lang('messages.profile_change_pass_button')
                            </a>
                        </div>
                    </div>

                    <hr class="mb-4" />

                    {{-- ======== INFO GRID ======== --}}
                    {{-- ✅ DATA FIX: Saare variables $student object se liye gaye hain (DB ke according) --}}
                    <div class="grid-info">
                        <div class="label-sm">@lang('messages.profile_label_email')</div>
                        <div class="value-lg">{{ $student->email ?? '—' }}</div>

                        <div class="label-sm">@lang('messages.profile_label_phone')</div>
                        <div class="value-lg">{{ $student->phone ?? '—' }}</div>

                        <div class="label-sm">@lang('messages.profile_label_alt_phone')</div>
                        <div class="value-lg">{{ $student->guardian_phone ?? '—' }}</div>

                        <div class="label-sm">@lang('messages.profile_label_admission_date')</div>
                        <div class="value-lg">
                            {{ $student->created_at ? $student->created_at->format('d M, Y') : '—' }}
                        </div>

                        <div class="label-sm">@lang('messages.profile_label_address')</div>
                        <div class="value-lg">{{ $student->address ?? '—' }}</div>
                    </div>
                </div>
            </div>
        </div>

        {{-- ======== RIGHT: Quick Actions ======== --}}
        <div class="col-lg-4">
            <div class="card soft-card">
                <div class="card-body">
                    <h6 class="mb-3">@lang('messages.profile_quick_actions_title')</h6>
                    
                    {{-- ✅ LINK FIX: Routes `student_routes.php` se liye gaye hain --}}
                    <div class="d-grid gap-2">
                        <a class="btn profile-action-primary" href="{{ route('student.courses.index') }}">@lang('messages.profile_quick_buy_course')</a>
                        <a class="btn profile-action-secondary" href="{{ route('student.myexams') }}">@lang('messages.profile_quick_purchased')</a>
                        <a class="btn profile-action-primary" href="{{ route('student.results') }}">@lang('messages.profile_quick_results')</a>
                        <a class="btn profile-action-secondary" href="{{ route('student.bookmarks') }}">@lang('messages.profile_quick_bookmarks')</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

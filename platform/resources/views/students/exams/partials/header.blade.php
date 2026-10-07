<style>
    .exam-topbar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        padding: .75rem 1rem;
        background: var(--el-card-bg, #fff);
        border: 1px solid var(--el-border);
        border-radius: 14px;
        box-shadow: 0 10px 28px rgba(15, 23, 42, .08);
    }

    .exam-header-btn,
    .exam-header-btn:hover,
    .exam-header-btn:focus {
        background: var(--el-primary);
        border-color: var(--el-primary);
        color: var(--theme-button-text, #fff);
        font-weight: 800;
        box-shadow: none;
    }

    .exam-student-avatar {
        height: 40px;
        width: 40px;
        object-fit: cover;
        border: 2px solid var(--el-primary-soft);
    }

    .exam-language-control { min-width: 150px; }
    .exam-language-control .form-select { min-width: 150px; }
    .exam-language-control small { display: block; max-width: 220px; margin-top: 2px; }
</style>

<div class="exam-topbar">
    @include('partials.brand-logo', ['brandHref' => url('/'), 'brandClass' => 'is-exam'])

    @include('students.exams.shared.language_switcher', ['examLanguageMode' => 'student'])

    <button class="btn exam-header-btn" id="fullscreenButton">@lang('messages.exam_header_fullscreen_enter')</button>

    @if (Auth::guard('student')->check())
        <img src="{{ Auth::guard('student')->user()->photo ? asset('storage/' . Auth::guard('student')->user()->photo) : asset('storage/images/default-avatar.jpg') }}"
            alt="@lang('messages.exam_header_student_photo_alt')" class="rounded-circle exam-student-avatar">
    @else
        <img src="{{ asset('storage/images/default-avatar.jpg') }}" alt="@lang('messages.exam_header_default_avatar_alt')" class="rounded-circle exam-student-avatar">
    @endif
</div>

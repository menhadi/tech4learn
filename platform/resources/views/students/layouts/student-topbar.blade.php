<header id="page-topbar">
    <div class="layout-width">
        <div class="navbar-header">
            <div class="d-flex">
                <button type="button"
                    class="btn btn-sm px-3 fs-16 header-item vertical-menu-btn topnav-hamburger shadow-none"
                    id="student-topnav-hamburger-icon"
                    aria-controls="navbar-nav"
                    aria-expanded="true"
                    aria-label="{{ __('ui.collapse_sidebar') }}"
                    title="{{ __('ui.collapse_sidebar') }}">
                    <i class="ri-menu-fold-line fs-22 sidebar-toggle-icon" aria-hidden="true"></i>
                    <span class="visually-hidden sidebar-toggle-text">{{ __('ui.collapse_sidebar') }}</span>
                </button>
            </div>

            <div class="d-flex align-items-center justify-content-end">

                @include('partials.language-switcher')
                <div class="dropdown">
                    <button type="button" class="btn btn-link dropdown-toggle" id="studentDropdown"
                        data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false"
                        style="box-shadow: none !important;">
                        @php
                            $student = Auth::guard('student')->user();

                            $photo = $student->provider === 'google'
                                ? $student->photo
                                : ($student->photo
                                    ? asset('storage/' . $student->photo)
                                    : asset('storage/images/default-avatar.jpg'));
                        @endphp

                        <img src="{{ $photo }}" alt="Student Photo" class="rounded-circle" height="40">
                        <span class="ms-2">{{ Auth::guard('student')->user()->name }}</span>
                    </button>
                    <div class="dropdown-menu dropdown-menu-end" aria-labelledby="studentDropdown">
                        <a class="dropdown-item" href="{{ route('student.profile') }}">{{ __('messages.edit_profile_nav_profile') }}</a>
                        <form id="signout-form" action="{{ route('student.signout') }}" method="POST">
                            @csrf
                            <button type="button" class="dropdown-item" onclick="confirmSignout()">{{ __('ui.signout') }}</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</header>

<script>
    function confirmSignout() {
        Swal.fire({ icon: 'question', title: 'Sign out?', text: 'Are you sure you want to sign out?', showCancelButton: true, confirmButtonText: 'Sign out' })
            .then(result => { if (result.isConfirmed) document.getElementById('signout-form').submit(); });
    }
</script>
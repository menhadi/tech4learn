<div class="app-menu navbar-menu">
    <style>
        .app-menu.navbar-menu {
            --student-sidebar-bg: var(--el-bg, #f7faf9);
            --student-sidebar-text: #000000;
            --student-sidebar-muted: #000000;
            --student-sidebar-hover: var(--el-primary-soft, rgba(15, 118, 110, .12));
            --student-sidebar-active: var(--el-primary-soft, rgba(15, 118, 110, .12));
            background: var(--student-sidebar-bg) !important;
            border-right: 1px solid var(--el-border, #d7e2df) !important;
            display: flex !important;
            flex-direction: column;
            padding: 0 !important;
        }
        html[data-bs-theme="dark"] .app-menu.navbar-menu {
            --student-sidebar-bg: #0d1727;
            --student-sidebar-text: #f3f6fb;
            --student-sidebar-muted: #b7c3d6;
            --student-sidebar-hover: color-mix(in srgb, var(--el-primary) 18%, #121e31);
            --student-sidebar-active: color-mix(in srgb, var(--el-primary) 28%, #121e31);
            border-right-color: #2b3a51 !important;
        }

        .app-menu.navbar-menu .navbar-brand-box {
            background: transparent !important;
            border-bottom: 0 !important;
            flex: 0 0 auto;
            position: relative !important;
            width: 100%;
        }

        .app-menu.navbar-menu > .scrollable-sidebar {
            flex: 1 1 auto;
            height: auto !important;
            min-height: 0;
            overflow-y: auto !important;
            overscroll-behavior-y: contain;
            padding-bottom: 24px;
        }

        .app-menu.navbar-menu #scrollbar,
        .app-menu.navbar-menu .container-fluid,
        .app-menu.navbar-menu .sidebar-background {
            background: transparent !important;
        }

        .app-menu.navbar-menu .navbar-nav {
            padding: 14px 10px;
        }

        .app-menu.navbar-menu .nav-link.menu-link {
            color: var(--student-sidebar-muted) !important;
            border-radius: 6px;
            border-left: 3px solid transparent;
            margin: 3px 0;
            padding: 11px 14px !important;
            font-weight: 600;
            transition: background-color .18s ease, color .18s ease, border-color .18s ease;
        }

        .app-menu.navbar-menu .nav-link.menu-link i {
            color: var(--student-sidebar-muted) !important;
            margin-right: 10px;
            font-size: 18px;
        }

        .app-menu.navbar-menu .nav-link.menu-link:hover,
        .app-menu.navbar-menu .nav-link.menu-link.active {
            background: var(--student-sidebar-hover) !important;
            color: var(--student-sidebar-text) !important;
            border-left-color: var(--el-secondary, #f59e0b);
        }

        .app-menu.navbar-menu .nav-link.menu-link:hover i,
        .app-menu.navbar-menu .nav-link.menu-link.active i {
            color: var(--student-sidebar-text) !important;
        }

        .app-menu.navbar-menu .nav-link.menu-link::after,
        .app-menu.navbar-menu .nav-link.menu-link:hover::after,
        .app-menu.navbar-menu .nav-link.menu-link.active::after,
        .app-menu.navbar-menu .nav-link.menu-link .menu-arrow,
        .app-menu.navbar-menu .nav-link.menu-link:hover .menu-arrow,
        .app-menu.navbar-menu .nav-link.menu-link.active .menu-arrow {
            color: var(--student-sidebar-text) !important;
            opacity: 1 !important;
        }

        .app-menu.navbar-menu .nav-link.menu-link.active {
            background: var(--student-sidebar-active) !important;
            box-shadow: none;
        }

        @media (min-width: 992px) {
            html[data-sidebar-size="sm"] .app-menu.navbar-menu .navbar-brand-box {
                padding-inline: 7px !important;
            }
            html[data-sidebar-size="sm"] .app-menu.navbar-menu .navbar-brand-box .examelite-brand-card {
                padding-inline: 0;
            }
            html[data-sidebar-size="sm"] .app-menu.navbar-menu .navbar-brand-box .examelite-brand-card__text,
            html[data-sidebar-size="sm"] .app-menu.navbar-menu #vertical-hover {
                display: none !important;
            }
            html[data-sidebar-size="sm"] .app-menu.navbar-menu .navbar-brand-box .examelite-brand-card__image {
                height: 38px;
                max-width: 52px;
                object-fit: contain;
            }
            html[data-sidebar-size="sm"] .app-menu.navbar-menu .navbar-nav {
                padding-inline: 9px;
            }
            html[data-sidebar-size="sm"] .app-menu.navbar-menu .nav-link.menu-link {
                align-items: center;
                border-left-width: 0;
                justify-content: center;
                min-height: 48px;
                overflow: visible;
                padding-inline: 8px !important;
            }
            html[data-sidebar-size="sm"] .app-menu.navbar-menu .nav-link.menu-link i {
                font-size: 22px !important;
                margin-right: 0 !important;
            }
            html[data-sidebar-size="sm"] .app-menu.navbar-menu .nav-link.menu-link span {
                display: none !important;
            }
        }
    </style>
    <div class="navbar-brand-box">
        @include('partials.brand-logo', ['brandHref' => route('student.dashboard'), 'brandClass' => 'is-sidebar'])
        <button type="button" class="btn btn-sm p-0 fs-20 header-item float-end btn-vertical-sm-hover"
            id="vertical-hover">
            <i class="ri-record-circle-line"></i>
        </button>
    </div>

    <div id="scrollbar" class="scrollable-sidebar">
        <div class="container-fluid">
            <ul class="navbar-nav" id="navbar-nav">
                <li class="nav-item">
                    <a class="nav-link menu-link" href="{{ route('student.leaderboard') }}">
                        <i class="mdi mdi-trophy-outline"></i> <span>{{ __('ui.leaderboard') }}</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link menu-link" href="{{ route('student.dashboard') }}">
                        <i class="mdi mdi-speedometer"></i> <span>@lang('messages.sidebar_dashboard')</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link menu-link" href="{{ route('student.myexams') }}">
                        <i class="mdi mdi-book-open"></i> <span>@lang('messages.sidebar_my_exams')</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link menu-link" href="{{ route('student.quick-quizzes') }}">
                        <i class="ri-flashlight-line"></i> <span>{{ __('ui.quick_quiz') }}</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link menu-link" href="{{ route('student.practice-builder.index') }}">
                        <i class="mdi mdi-tune-variant"></i> <span>{{ __('ui.practice_builder') }}</span>
                    </a>
                </li>
                @if(\App\Support\SaasAccess::featureEnabled('flashcards') && \Illuminate\Support\Facades\Route::has('student.flashcards.index'))
                <li class="nav-item">
                    <a class="nav-link menu-link" href="{{ route('student.flashcards.index') }}">
                        <i class="mdi mdi-cards-outline"></i> <span>{{ __('ui.study_cards') }}</span>
                    </a>
                </li>
                @endif
                <li class="nav-item">
                    <a class="nav-link menu-link" href="{{ route('student.results') }}">
                        <i class="mdi mdi-chart-bar"></i> <span>@lang('messages.sidebar_my_result')</span>
                    </a>
                </li>


                <li class="nav-item">
                    <a class="nav-link menu-link" href="{{ route('student.courses.index') }}">
                        <i class="mdi mdi-shopping-outline"></i> <span>{{ __('messages.my_exams_browse_courses_button') }}</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link menu-link" href="{{ route('student.orders.index') }}">
                        <i class="mdi mdi-receipt-text-outline"></i> <span>{{ __('ui.my_purchases') }}</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link menu-link" href="{{ route('student.bookmarks') }}">
                        <i class="mdi mdi-bookmark"></i> <span>@lang('messages.sidebar_my_bookmark')</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link menu-link" href="{{ route('student.help') }}">
                        <i class="mdi mdi-help-circle"></i> <span>@lang('messages.sidebar_help')</span>
                    </a>
                </li>
            </ul>
        </div>
        </div>
    <div class="sidebar-background"></div>
</div>
<div class="vertical-overlay"></div>

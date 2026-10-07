<div class="app-menu navbar-menu">
    <div class="navbar-brand-box">
        @include('partials.brand-logo', ['brandHref' => route('dashboard'), 'brandClass' => 'is-sidebar'])
    </div>

    <style>
        :root {
            --el-sidebar-bg: color-mix(in srgb, var(--el-primary, #0f766e) 6%, #ffffff);
            --el-sidebar-border: color-mix(in srgb, var(--el-primary, #0f766e) 18%, #d7e2df);
            --el-sidebar-text: #17212b;
            --el-sidebar-muted: #334155;
            --el-sidebar-hover-bg: color-mix(in srgb, var(--el-primary, #0f766e) 11%, #ffffff);
            --el-sidebar-hover-text: var(--el-primary, #0f766e);
            --el-sidebar-active-bg: color-mix(in srgb, var(--el-primary, #0f766e) 17%, #ffffff);
            --el-sidebar-active-text: color-mix(in srgb, var(--el-primary, #0f766e) 88%, #071b18);
            --el-sidebar-sub-active-text: var(--el-primary, #0f766e);
            --el-sidebar-scrollbar: rgba(var(--el-primary-rgb, 15, 118, 110), 0.34);
        }

        [data-bs-theme="dark"] {
            --el-sidebar-bg: color-mix(in srgb, var(--el-primary, #0f766e) 13%, #0b1220);
            --el-sidebar-border: color-mix(in srgb, var(--el-primary, #0f766e) 28%, #1e293b);
            --el-sidebar-text: #f8fafc;
            --el-sidebar-muted: #cbd5e1;
            --el-sidebar-hover-bg: color-mix(in srgb, var(--el-primary, #0f766e) 25%, #111827);
            --el-sidebar-hover-text: #ffffff;
            --el-sidebar-active-bg: color-mix(in srgb, var(--el-primary, #0f766e) 36%, #111827);
            --el-sidebar-active-text: #ffffff;
            --el-sidebar-sub-active-text: #ffffff;
            --el-sidebar-scrollbar: rgba(255, 255, 255, 0.24);
        }

        .navbar-menu {
            background: var(--el-sidebar-bg) !important;
            border-right: 1px solid var(--el-sidebar-border) !important;
            display: flex !important;
            flex-direction: column;
            padding: 0 !important;
        }
        .navbar-brand-box {
            background: transparent !important;
            border-bottom: 1px solid color-mix(in srgb, var(--el-sidebar-border) 70%, transparent) !important;
            flex: 0 0 auto;
            position: relative !important;
            width: 100%;
        }
        .navbar-menu > .scrollable-sidebar {
            flex: 1 1 auto;
            height: auto !important;
            min-height: 0;
            overflow-y: auto !important;
            overscroll-behavior-y: contain;
            padding-bottom: 24px;
        }
        .navbar-brand-box::before,
        .navbar-brand-box::after {
            content: none !important;
            display: none !important;
        }
        .navbar-brand-box .examelite-brand-card__name { color: var(--el-sidebar-text) !important; }
        .navbar-brand-box .examelite-brand-card__tagline { color: var(--el-sidebar-hover-text) !important; }
        .navbar-nav { padding: 18px 12px; }
        .nav-item { margin-bottom: 5px; }
        .navbar-nav .nav-link {
            border: 0 !important;
            border-radius: 9px;
            color: var(--el-sidebar-muted) !important;
            font-size: 14.5px !important;
            font-weight: 550;
            overflow: hidden;
            padding: 11px 15px !important;
            position: relative;
            transition: background-color .2s ease, color .2s ease, transform .2s ease;
        }
        .navbar-nav .nav-link i {
            color: currentColor !important;
            font-size: 19px !important;
            margin-right: 13px !important;
            transition: color .2s ease, transform .2s ease;
            vertical-align: middle;
        }
        .navbar-nav .nav-link:hover {
            background: var(--el-sidebar-hover-bg) !important;
            color: var(--el-sidebar-hover-text) !important;
        }
        .navbar-nav .nav-link:hover i { transform: translateY(-1px); }
        .navbar-nav .nav-link.active,
        .navbar-nav .nav-link[aria-expanded="true"] {
            background: var(--el-sidebar-active-bg) !important;
            box-shadow: 0 7px 18px rgba(var(--el-primary-rgb, 15, 118, 110), .10);
            color: var(--el-sidebar-active-text) !important;
            font-weight: 700;
        }
        .navbar-nav .nav-link::before,
        .navbar-nav .nav-link:hover::before,
        .navbar-nav .nav-link.active::before,
        .menu-dropdown .nav-link::before {
            content: none !important;
            display: none !important;
        }
        .navbar-nav .nav-link::after,
        .navbar-nav .nav-link .menu-arrow {
            color: currentColor !important;
            opacity: .85 !important;
        }
        .menu-dropdown .nav-link {
            background: transparent !important;
            box-shadow: none !important;
            color: var(--el-sidebar-muted) !important;
            font-size: 13.5px !important;
            padding-left: 53px !important;
        }
        .menu-dropdown .menu-dropdown .nav-link { padding-left: 69px !important; }
        .menu-dropdown .nav-link:hover {
            background: var(--el-sidebar-hover-bg) !important;
            color: var(--el-sidebar-hover-text) !important;
            transform: translateX(2px);
        }
        .menu-dropdown .nav-link.active {
            background: var(--el-sidebar-active-bg) !important;
            color: var(--el-sidebar-sub-active-text) !important;
            font-weight: 700;
        }
        .nav-link.plan-feature-locked { opacity: .62; }
        .nav-link.plan-feature-locked::after {
            content: "\F472" !important;
            display: block !important;
            float: right;
            font-family: remixicon;
            font-size: 14px;
            opacity: .9;
        }
        .scrollable-sidebar::-webkit-scrollbar { width: 4px; }
        .scrollable-sidebar::-webkit-scrollbar-thumb {
            background-color: var(--el-sidebar-scrollbar);
            border-radius: 10px;
        }

        @media (min-width: 768px) {
            html[data-sidebar-size="sm"] .navbar-brand-box { padding: 0 7px !important; }
            html[data-sidebar-size="sm"] .navbar-brand-box .examelite-brand-card { padding-inline: 0; }
            html[data-sidebar-size="sm"] .navbar-brand-box .examelite-brand-card__text { display: none !important; }
            html[data-sidebar-size="sm"] .navbar-brand-box .examelite-brand-card__image {
                height: 38px;
                max-width: 52px;
                object-fit: contain;
            }
            html[data-sidebar-size="sm"] .navbar-brand-box .examelite-brand-card__mark {
                height: 40px;
                width: 40px;
            }
            html[data-sidebar-size="sm"] .navbar-nav { padding-inline: 9px; }
            html[data-sidebar-size="sm"] .navbar-nav .nav-link {
                align-items: center;
                justify-content: center;
                min-height: 48px;
                overflow: visible;
                padding: 11px 8px !important;
            }
            html[data-sidebar-size="sm"] .navbar-nav .nav-link i {
                font-size: 22px !important;
                margin-right: 0 !important;
            }
            html[data-sidebar-size="sm"] .navbar-nav .nav-link span,
            html[data-sidebar-size="sm"] .navbar-nav .nav-link::after,
            html[data-sidebar-size="sm"] .navbar-nav .menu-dropdown {
                display: none !important;
            }
        }
    </style>

    <div id="scrollbar" class="scrollable-sidebar">
        <div class="container-fluid">
            <ul class="navbar-nav" id="navbar-nav">
                @if(!empty($isPlatformOwner) && \Illuminate\Support\Facades\Route::has('saas.index'))
                    <li class="nav-item">
                        <a class="nav-link menu-link {{ request()->routeIs('saas.*') ? 'active' : '' }}" href="{{ route('saas.index') }}" title="SaaS Control Center">
                            <i class="ri-building-4-line"></i>
                            <span>SaaS Control Center</span>
                        </a>
                    </li>
                @endif

                @foreach(($sidebarGroups ?? collect([['label' => 'Menu', 'icon' => 'ri-menu-line', 'pages' => $pages]])) as $groupIndex => $group)
                    @php
                        $groupPages = collect($group['pages'] ?? []);
                        $isActiveGroup = $groupPages->contains(function ($page) {
                            if ($page->children->isEmpty()) {
                                return !blank($page->action_name) && request()->routeIs($page->action_name . '*');
                            }

                            return $page->children->pluck('action_name')->contains(fn($name) => !blank($name) && request()->routeIs($name . '*'));
                        });
                        $isAcademicsGroup = in_array(($group['label'] ?? ''), ['Academics', 'Academic Structure'], true);
                        $isAssessmentsGroup = ($group['label'] ?? '') === 'Exams & Questions';
                        $isPackagesGroup = in_array(($group['label'] ?? ''), ['Packages', 'Packages & Sales'], true);
                        $isFlashcardsGroup = in_array(($group['label'] ?? ''), ['Flashcards', 'Study Tools'], true);
                        $isStudentsGroup = $groupIndex === 'students' || ($group['label'] ?? '') === 'Students & Results';
                        $isAiGroup = $groupIndex === 'ai' || ($group['label'] ?? '') === 'AI Tools';
                        $isActiveGroup = $isActiveGroup || ($isAssessmentsGroup && request()->routeIs('question-tags.*'));
                        $isActiveGroup = $isActiveGroup || ($isAcademicsGroup && request()->routeIs('sections.*'));
                        $isActiveGroup = $isActiveGroup || ($isPackagesGroup && request()->routeIs('package-tags.*'));
                        $isActiveGroup = $isActiveGroup || ($isFlashcardsGroup && request()->routeIs('flashcards.*'));
                        $isActiveGroup = $isActiveGroup || ($isStudentsGroup && request()->routeIs('demo-students.*'));
                        $isActiveGroup = $isActiveGroup || ($isAiGroup && (request()->routeIs('admin.ai-content.*') || request()->routeIs('admin.seo.*')));
                        $groupId = 'sidebarGroup' . $groupIndex;
                    @endphp

                    <li class="nav-item">
                        <a class="nav-link menu-link {{ $isActiveGroup ? 'active' : '' }}" href="#{{ $groupId }}" data-bs-toggle="collapse" role="button" aria-expanded="{{ $isActiveGroup ? 'true' : 'false' }}" aria-controls="{{ $groupId }}" title="{{ $group['label'] ?? 'Menu' }}">
                            <i class="{{ $group['icon'] ?? 'ri-menu-line' }}"></i>
                            <span>{{ $group['label'] ?? 'Menu' }}</span>
                        </a>
                        <div class="collapse menu-dropdown {{ $isActiveGroup ? 'show' : '' }}" id="{{ $groupId }}">
                            <ul class="nav nav-sm flex-column">
                                @foreach($groupPages->sortBy('ordering') as $page)
                                    @continue(blank($page->action_name) && $page->children->isEmpty())

                                    @if($page->children->isEmpty())
                                        @continue(! \Illuminate\Support\Facades\Route::has($page->action_name))
                                        @continue($page->action_name === 'payment-gateway.index' && empty($isPlatformOwner))
                                        @continue($page->action_name === 'configurations.ai' && ! \App\Support\SaasAccess::featureEnabled('ai_settings'))
                                        @php
                                            $featureKey = \App\Support\SaasAccess::featureForRoute($page->action_name);
                                            $isPlanLocked = $featureKey && ! \App\Support\SaasAccess::featureEnabled($featureKey);
                                            $displayPageName = $page->action_name === 'exams.reports' ? 'Reported Questions' : $page->page_name;
                                        @endphp
                                        <li class="nav-item">
                                            <a href="{{ route($page->action_name) }}" class="nav-link {{ (request()->routeIs($page->action_name.'*') || ($isFlashcardsGroup && request()->routeIs('flashcards.*'))) ? 'active' : '' }} {{ $isPlanLocked ? 'plan-feature-locked js-plan-feature-locked' : '' }}" data-plan-feature="{{ $featureKey }}">
                                                {{ $displayPageName }}
                                            </a>
                                        </li>
                                    @else
                                        @php
                                            $isActiveParent = $page->children->pluck('action_name')->contains(fn($name) => !blank($name) && request()->routeIs($name.'*'));
                                            $childMenuId = 'sidebarPage' . $page->id;
                                        @endphp
                                        <li class="nav-item">
                                            <a class="nav-link {{ $isActiveParent ? 'active' : '' }}" href="#{{ $childMenuId }}" data-bs-toggle="collapse" role="button" aria-expanded="{{ $isActiveParent ? 'true' : 'false' }}" aria-controls="{{ $childMenuId }}">
                                                {{ $page->page_name }}
                                            </a>
                                            <div class="collapse menu-dropdown {{ $isActiveParent ? 'show' : '' }}" id="{{ $childMenuId }}">
                                                <ul class="nav nav-sm flex-column">
                                                    @foreach($page->children->sortBy('ordering') as $child)
                                                        @continue(blank($child->action_name) || ! \Illuminate\Support\Facades\Route::has($child->action_name))
                                                        @continue($child->action_name === 'payment-gateway.index' && empty($isPlatformOwner))
                                                        @continue($child->action_name === 'configurations.ai' && ! \App\Support\SaasAccess::featureEnabled('ai_settings'))
                                                        @php
                                                            $featureKey = \App\Support\SaasAccess::featureForRoute($child->action_name);
                                                            $isPlanLocked = $featureKey && ! \App\Support\SaasAccess::featureEnabled($featureKey);
                                                            $displayChildName = $child->action_name === 'exams.reports' ? 'Reported Questions' : $child->page_name;
                                                        @endphp
                                                        <li class="nav-item">
                                                            <a href="{{ route($child->action_name) }}" class="nav-link {{ request()->routeIs($child->action_name.'*') ? 'active' : '' }} {{ $isPlanLocked ? 'plan-feature-locked js-plan-feature-locked' : '' }}" data-plan-feature="{{ $featureKey }}">
                                                                {{ $displayChildName }}
                                                            </a>
                                                        </li>
                                                    @endforeach
                                                </ul>
                                            </div>
                                        </li>
                                    @endif
                                @endforeach
                                @if($isPackagesGroup && \Illuminate\Support\Facades\Route::has('package-tags.index'))
                                    <li class="nav-item">
                                        <a href="{{ route('package-tags.index') }}" class="nav-link {{ request()->routeIs('package-tags.*') ? 'active' : '' }}">
                                            Package Tags
                                        </a>
                                    </li>
                                @endif
                                @if($isAcademicsGroup && \Illuminate\Support\Facades\Route::has('sections.index'))
                                    <li class="nav-item">
                                        <a href="{{ route('sections.index') }}" class="nav-link {{ request()->routeIs('sections.*') ? 'active' : '' }}">
                                            Sections
                                        </a>
                                    </li>
                                @endif
                                @if($isAssessmentsGroup && \Illuminate\Support\Facades\Route::has('exam-documents.index'))
                                    <li class="nav-item">
                                        <a href="{{ route('exam-documents.index') }}" class="nav-link {{ request()->routeIs('exam-documents.*') ? 'active' : '' }}">
                                            Translations &amp; PDFs
                                        </a>
                                    </li>
                                @endif
                                @if($isAssessmentsGroup && \Illuminate\Support\Facades\Route::has('source-question-import.index'))
                                    <li class="nav-item">
                                        <a href="{{ route('source-question-import.index') }}" class="nav-link {{ request()->routeIs('source-question-import.*') ? 'active' : '' }}">
                                            Source URL Question Import
                                        </a>
                                    </li>
                                @endif
                                @if($isAssessmentsGroup && \Illuminate\Support\Facades\Route::has('question-tags.index'))
                                    <li class="nav-item">
                                        <a href="{{ route('question-tags.index') }}" class="nav-link {{ request()->routeIs('question-tags.*') ? 'active' : '' }}">
                                            Question Tags
                                        </a>
                                    </li>
                                @endif
                                @if($isAiGroup && \Illuminate\Support\Facades\Route::has('admin.ai-content.bulk'))
                                    @php $isPlanLocked = ! \App\Support\SaasAccess::featureEnabled('ai_content_generation'); @endphp
                                    <li class="nav-item">
                                        <a class="nav-link {{ request()->routeIs('admin.ai-content.*') ? 'active' : '' }} {{ $isPlanLocked ? 'plan-feature-locked js-plan-feature-locked' : '' }}" href="{{ route('admin.ai-content.bulk') }}" data-plan-feature="ai_content_generation">
                                            Bulk AI Content Generator
                                        </a>
                                    </li>
                                @endif
                                @if($isAiGroup && \Illuminate\Support\Facades\Route::has('admin.seo.bulk'))
                                    @php $isPlanLocked = ! \App\Support\SaasAccess::featureEnabled('ai_seo'); @endphp
                                    <li class="nav-item">
                                        <a class="nav-link {{ request()->routeIs('admin.seo.dashboard') ? 'active' : '' }} {{ $isPlanLocked ? 'plan-feature-locked js-plan-feature-locked' : '' }}" href="{{ route('admin.seo.dashboard') }}" data-plan-feature="ai_seo">
                                            Search &amp; AI Visibility
                                        </a>
                                    </li>
                                    <li class="nav-item">
                                        <a class="nav-link {{ request()->routeIs('admin.seo.bulk*') ? 'active' : '' }} {{ $isPlanLocked ? 'plan-feature-locked js-plan-feature-locked' : '' }}" href="{{ route('admin.seo.bulk') }}" data-plan-feature="ai_seo">
                                            Bulk SEO Generator
                                        </a>
                                    </li>
                                @endif
                            </ul>
                        </div>
                    </li>
                @endforeach
            </ul>
        </div>
    </div>
    <div class="sidebar-background"></div>
</div>
<div class="vertical-overlay"></div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.js-plan-feature-locked').forEach(function (link) {
        link.addEventListener('click', function (event) {
            event.preventDefault();

            if (window.Swal) {
                const styles = getComputedStyle(document.documentElement);
                const primaryColor = (styles.getPropertyValue('--el-primary') || styles.getPropertyValue('--vz-primary') || '#0f766e').trim();
                const secondaryColor = (styles.getPropertyValue('--el-secondary') || styles.getPropertyValue('--vz-warning') || '#f59e0b').trim();

                Swal.fire({
                    icon: 'info',
                    title: 'Not included in your plan',
                    text: 'Please contact the platform administrator to enable this feature for your organization.',
                    confirmButtonText: 'OK',
                    confirmButtonColor: primaryColor,
                    cancelButtonColor: secondaryColor
                });
                return;
            }

            alert('This feature is not included in your organization plan.');
        });
    });
});
</script>

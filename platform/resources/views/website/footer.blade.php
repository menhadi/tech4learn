@php
    $footerText = function ($value) {
        if (is_array($value)) {
            return $value['en'] ?? reset($value) ?: '';
        }

        if (is_string($value) && str_starts_with(trim($value), '{')) {
            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                return $decoded['en'] ?? reset($decoded) ?: $value;
            }
        }

        return $value;
    };

    $footerPageLinks = collect($footerPages ?? [])->map(function ($page) use ($footerText) {
        $label = $footerText($page->title) ?: $footerText($page->short_title);
        $slug = \Illuminate\Support\Str::slug($footerText($page->short_title));

        if ($label === '') {
            return null;
        }

        return [
            'label' => $label,
            'url' => route('page.show', $slug ?: $page->id),
        ];
    })->filter()->values();
    $footerSocial = is_array($configuration->social_sharing_settings ?? null) ? ($configuration->social_sharing_settings['profiles'] ?? []) : [];
    $footerSocialMeta = ['facebook' => ['Facebook', 'ri-facebook-fill'], 'instagram' => ['Instagram', 'ri-instagram-line'], 'x' => ['X', 'ri-twitter-x-line'], 'linkedin' => ['LinkedIn', 'ri-linkedin-fill'], 'youtube' => ['YouTube', 'ri-youtube-fill'], 'telegram' => ['Telegram', 'ri-telegram-line']];
@endphp

<footer class="exam-footer">
    <div class="container">
        <div class="row g-4">
            <div class="col-lg-3 col-md-6">
                <div class="footer-brand-block">
                    <div class="mb-3">
                        @if (!empty($configuration->logo))
                            <img src="{{ asset('storage/' . $configuration->logo) }}" class="footer-logo" alt="ExamElite">
                        @else
                            <h4 class="text-white mb-0">{{ ($customFooterBrandLabel ?? null) ?: ($configuration->name ?? 'ExamElite') }}</h4>
                        @endif
                    </div>

                    <p class="footer-about">
                        {{ $configuration->organization_tagline ?? 'Practice exams, mock tests, and previous year questions online with ExamElite.' }}
                    </p>

                    <ul class="list-unstyled footer-contact">
                        @if(!empty($configuration->organization_phone))
                            <li>
                                <i class="ri-phone-line"></i>
                                <a href="tel:{{ $configuration->organization_phone }}">{{ $configuration->organization_phone }}</a>
                            </li>
                        @endif

                        @if(!empty($configuration->email))
                            <li>
                                <i class="ri-mail-line"></i>
                                <a href="mailto:{{ $configuration->email }}">{{ $configuration->email }}</a>
                            </li>
                        @endif
                    </ul>
                    @if(count($footerSocial))
                        <div class="footer-social" aria-label="Official social media profiles">
                            @foreach($footerSocial as $network => $profileUrl)
                                @if(isset($footerSocialMeta[$network]) && $profileUrl)
                                    <a href="{{ $profileUrl }}" target="_blank" rel="noopener noreferrer" aria-label="{{ $footerSocialMeta[$network][0] }}"><i class="{{ $footerSocialMeta[$network][1] }}"></i></a>
                                @endif
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>

            @if(collect($customFooterNavigation ?? [])->isNotEmpty())
                @foreach(collect($customFooterNavigation)->groupBy(fn($item) => $item->section_label ?: 'Links') as $section => $footerItems)
                    <div class="col-lg-3 col-md-6 col-sm-6"><h5 class="footer-title">{{ $section }}</h5><ul class="list-unstyled footer-list">
                        @foreach($footerItems as $footerItem)<li><a href="{{ $footerItem->resolvedUrl() }}" target="{{ $footerItem->target }}">@if($footerItem->icon)<i class="{{ $footerItem->icon }} me-1"></i>@endif{{ $footerItem->label }}</a></li>@endforeach
                    </ul></div>
                @endforeach
            @else            <div class="col-lg-3 col-md-6 col-sm-6">
                <h5 class="footer-title">{{ __('ui.popular_exams') }}</h5>
                <ul class="list-unstyled footer-list footer-list-compact">
                    @foreach(($footerPopularGroups ?? collect())->take(6) as $group)
                        @php $groupName = $footerText($group->group_name); @endphp
                        <li>
                            <a href="{{ url('/exam-groups/' . \Illuminate\Support\Str::slug($groupName)) }}">
                                {{ $groupName }}
                            </a>
                        </li>
                    @endforeach
                </ul>

                <h5 class="footer-subtitle">{{ __('ui.exam_categories') }}</h5>
                <ul class="list-unstyled footer-list footer-list-compact">
                    @foreach(($footerCategoryLinks ?? collect())->take(6) as $link)
                        <li>
                            <a href="{{ $link['url'] }}">{{ $link['title'] }}</a>
                        </li>
                    @endforeach
                </ul>
            </div>

            <div class="col-lg-3 col-md-6 col-sm-6">
                <h5 class="footer-title">{{ __('ui.exam_packages') }}</h5>
                <ul class="list-unstyled footer-list">
                    @forelse(($footerPopularPackages ?? collect())->take(10) as $package)
                        <li>
                            <a href="{{ route('courses.detail', $package->slug) }}">
                                {{ $footerText($package->name) }}
                            </a>
                        </li>
                    @empty
                        <li><a href="{{ route('courses.index') }}">{{ __('ui.all_packages') }}</a></li>
                    @endforelse
                </ul>
            </div>

            <div class="col-lg-2 col-md-6 col-sm-6 footer-compact-column">
                <h5 class="footer-title">{{ __('ui.student_links') }}</h5>
                <ul class="list-unstyled footer-list footer-list-compact">
                    <li><a href="{{ route('website.exams.index') }}">{{ __('ui.all_exams') }}</a></li>
                    <li><a href="{{ route('courses.index') }}">{{ __('ui.exam_packages') }}</a></li>
                    <li><a href="/courses">{{ __('ui.free_exams') }}</a></li>

                    @if (Auth::guard('student')->check())
                        <li><a href="/student/dashboard">{{ __('messages.sidebar_dashboard') }}</a></li>
                        <li><a href="/student/my-exams">{{ __('website.my_exams') }}</a></li>
                    @else
                        <li><a href="/student/signin">{{ __('ui.student_login') }}</a></li>
                    @endif
                </ul>

                <h5 class="footer-subtitle">{{ __('ui.useful_links') }}</h5>
                <ul class="list-unstyled footer-list footer-list-compact">
                    <li><a href="/">{{ __('website.home') }}</a></li>
                    <li><a href="/about">{{ __('ui.about_examelite') }}</a></li>
                    <li><a href="/contact">{{ __('website.contact') }}</a></li>
                    @foreach($footerPageLinks as $pageLink)
                        <li>
                            <a href="{{ $pageLink['url'] }}">
                                {{ $pageLink['label'] }}
                            </a>
                        </li>
                    @endforeach

                    <li><a href="/sitemap.xml">{{ __('ui.sitemap') }}</a></li>
                </ul>
            </div>
            @endif
        </div>

        <div class="footer-bottom">
            <p class="mb-0">
                &copy; <script>document.write(new Date().getFullYear())</script>
                {{ $configuration->name ?? 'ExamElite' }}. All rights reserved.
            </p>
        </div>
    </div>
</footer>

<style>
    .exam-footer {
        background: var(--theme-footer-bg, #0f172a);
        color: var(--theme-footer-text, #cbd5e1);
        padding: 56px 0 24px;
        margin-top: 48px;
    }

    .exam-footer .footer-logo {
        max-height: 52px;
        max-width: 190px;
        background: color-mix(in srgb, var(--theme-footer-text, #cbd5e1) 10%, #ffffff);
        border-radius: 8px;
        padding: 8px;
    }
    .exam-footer .footer-social{display:flex;flex-wrap:wrap;gap:9px;margin-top:18px}.exam-footer .footer-social a{align-items:center;border:1px solid color-mix(in srgb,var(--theme-footer-text,#cbd5e1) 28%,transparent);border-radius:50%;display:inline-flex;height:36px;justify-content:center;text-decoration:none;width:36px}.exam-footer .footer-social a:hover{background:color-mix(in srgb,var(--theme-footer-text,#cbd5e1) 14%,transparent);transform:translateY(-2px)}.exam-footer .footer-social i{font-size:18px}

    .exam-footer .footer-about {
        color: color-mix(in srgb, var(--theme-footer-text, #cbd5e1) 92%, transparent);
        font-size: 14px;
        line-height: 1.7;
        max-width: 340px;
        margin-bottom: 18px;
    }

    .exam-footer .footer-title {
        color: color-mix(in srgb, var(--theme-footer-text, #cbd5e1) 82%, #ffffff);
        font-size: 15px;
        font-weight: 700;
        margin-bottom: 14px;
        padding-bottom: 8px;
        position: relative;
    }

    .exam-footer .footer-subtitle {
        color: color-mix(in srgb, var(--theme-footer-text, #cbd5e1) 82%, #ffffff);
        font-size: 16px;
        font-weight: 700;
        margin: 22px 0 16px;
        padding-bottom: 10px;
        position: relative;
        display: inline-block;
    }

    .exam-footer .footer-title::after,
    .exam-footer .footer-subtitle::after {
        content: "";
        width: 38px;
        height: 2px;
        background: var(--theme-primary);
        position: absolute;
        left: 0;
        bottom: 0;
    }

    .exam-footer .footer-list {
        margin: 0;
        padding: 0;
    }

    .exam-footer .footer-list li {
        margin-bottom: 9px;
    }

    .exam-footer .footer-list-compact li {
        margin-bottom: 7px;
    }

    .exam-footer a {
        color: var(--theme-footer-text, #cbd5e1);
        text-decoration: none;
        font-size: 14px;
        line-height: 1.4;
        transition: color .2s ease, padding-left .2s ease;
    }

    .exam-footer a:hover,
    .exam-footer .footer-contact a:hover {
        color: var(--theme-secondary);
        padding-left: 3px;
    }

    .exam-footer .footer-contact li {
        display: flex;
        align-items: center;
        gap: 8px;
        margin-bottom: 8px;
        color: var(--theme-footer-text, #cbd5e1);
    }

    .exam-footer .footer-contact i {
        color: var(--theme-primary);
    }

    .exam-footer .footer-compact-column {
        flex: 0 0 20%;
        max-width: 20%;
    }

    @media (max-width: 991.98px) {
        .exam-footer .footer-compact-column {
            flex: 0 0 50%;
            max-width: 50%;
        }
    }

    @media (max-width: 575.98px) {
        .exam-footer .footer-compact-column {
            flex: 0 0 100%;
            max-width: 100%;
        }
    }

    .exam-footer .footer-bottom {
        border-top: 1px solid color-mix(in srgb, var(--theme-footer-text, #cbd5e1) 18%, transparent);
        margin-top: 36px;
        padding-top: 20px;
        text-align: center;
        color: color-mix(in srgb, var(--theme-footer-text, #cbd5e1) 72%, transparent);
        font-size: 13px;
    }

    @media (max-width: 767.98px) {
        .exam-footer {
            padding: 40px 0 22px;
        }

        .exam-footer .footer-title {
            margin-top: 8px;
        }
    }
</style>

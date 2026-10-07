@php
    $uiLanguages = app(App\Services\UiLanguageService::class)->available();
    $currentUiLocale = strtolower(app()->getLocale());
    $currentUiLanguage = $uiLanguages->firstWhere('code', $currentUiLocale) ?: $uiLanguages->first();
    $languageSwitcherVariant = $variant ?? 'default';
    $languageSwitcherIconOnly = $languageSwitcherVariant === 'mobile-icon';
    $languageSwitcherPlacement = $languageSwitcherIconOnly ? 'website-mobile-language' : ($languageSwitcherVariant === 'desktop' ? 'website-desktop-language d-none d-lg-block' : '');
    $languageFlagMap = ['en' => 'us', 'hi' => 'in'];
    $currentLanguageFlag = $languageFlagMap[$currentUiLocale] ?? null;
@endphp
@if($uiLanguages->count() > 1)
    @once
        <style>
            .language-switcher-toggle {
                align-items: center;
                background: color-mix(in srgb, var(--theme-card-bg, #fff) 92%, var(--theme-primary, #0f766e));
                border: 0;
                border-radius: 999px;
                color: var(--theme-heading, #0f172a);
                display: inline-flex;
                font-size: .82rem;
                font-weight: 700;
                height: 44px;
                justify-content: center;
                min-height: 44px;
                padding: 0;
                width: 44px;
                white-space: nowrap;
            }
            .language-switcher-toggle:hover,
            .language-switcher-toggle:focus {
                background: color-mix(in srgb, var(--theme-primary, #0f766e) 10%, #fff);
                color: var(--theme-primary, #0f766e);
            }
            .language-switcher-toggle::after { display: none; }
            .language-switcher-flag { border-radius: 3px; display: block; height: 20px; object-fit: cover; width: 28px; }
            .language-switcher-toggle .language-switcher-flag { border-radius: 2px; height: 20px; width: 28px; }
            .language-switcher-menu { border-radius: 12px; min-width: 190px; padding: .55rem; }
            .language-switcher-menu .dropdown-item { align-items: center; border-radius: 8px; display: flex; gap: .7rem; justify-content: flex-start; padding: .62rem .72rem !important; }
            .language-switcher-menu .dropdown-item .ri-check-line { margin-left: auto; }
            .language-switcher-menu .dropdown-item.active { background: color-mix(in srgb, var(--theme-primary, #0f766e) 11%, #fff); color: var(--theme-primary, #0f766e); }
            .website-mobile-language { display: none; }
            .website-desktop-language {
                align-items: center;
                align-self: center;
                display: flex;
                height: 42px !important;
                margin-left: 0 !important;
                margin-bottom: 0 !important;
                margin-top: 0 !important;
            }
            .website-desktop-language .language-switcher-toggle {
                border-radius: 12px;
                height: 42px;
                min-height: 42px;
                width: 56px;
            }
            @media (max-width: 991.98px) {
                .website-mobile-language {
                    align-items: center;
                    align-self: center;
                    display: flex;
                    flex: 0 0 38px;
                    height: 38px;
                    margin: 0 6px 0 0 !important;
                    order: 3;
                    width: 38px;
                }
                .website-mobile-language .language-switcher-toggle {
                    border-radius: 12px;
                    justify-content: center;
                    font-size: 18px;
                    height: 38px;
                    min-height: 38px;
                    padding: 0;
                    width: 38px;
                }
                .website-mobile-language .language-switcher-menu {
                    left: auto !important;
                    max-width: calc(100vw - 24px);
                    min-width: 190px;
                    right: 0 !important;
                    width: 190px;
                }
                .website-mobile-language .dropdown-item {
                    font-size: .86rem;
                    padding: .5rem .6rem !important;
                }
            }
        </style>
    @endonce
    <div class="dropdown language-switcher ms-1 topbar-head-dropdown header-item {{ $languageSwitcherPlacement }}">
        <button type="button" class="language-switcher-toggle dropdown-toggle shadow-none {{ $languageSwitcherIconOnly ? 'language-switcher-icon-only' : '' }}"
            data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false" aria-label="{{ __('ui.change_language') }}: {{ $currentUiLanguage?->native_name ?? strtoupper($currentUiLocale) }}">
            @if($currentLanguageFlag)
                <img class="language-switcher-flag" src="{{ asset('build/images/flags/'.$currentLanguageFlag.'.svg') }}" alt="">
            @else
                <i class="ri-global-line" aria-hidden="true"></i>
            @endif
        </button>
        <div class="dropdown-menu dropdown-menu-end language-switcher-menu">
            @foreach($uiLanguages as $uiLanguage)
                @php
                    $languageFlag = $languageFlagMap[$uiLanguage->code] ?? null;
                @endphp
                <a href="{{ route('lang.swap', ['locale' => $uiLanguage->code]) }}"
                    class="dropdown-item language py-2 {{ $currentUiLocale === $uiLanguage->code ? 'active' : '' }}"
                    lang="{{ $uiLanguage->code }}" dir="{{ $uiLanguage->direction }}">
                    @if($languageFlag)
                        <img class="language-switcher-flag" src="{{ asset('build/images/flags/'.$languageFlag.'.svg') }}" alt="">
                    @else
                        <i class="ri-global-line" aria-hidden="true"></i>
                    @endif
                    <span>
                        {{ $uiLanguage->native_name }}
                        @if($uiLanguage->native_name !== $uiLanguage->name)
                            <small class="text-muted">({{ $uiLanguage->name }})</small>
                        @endif
                    </span>
                    @if($currentUiLocale === $uiLanguage->code)<i class="ri-check-line" aria-hidden="true"></i>@endif
                </a>
            @endforeach
        </div>
    </div>
@endif

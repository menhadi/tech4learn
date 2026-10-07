@php
    $websiteNavbarText = function ($value) {
        if (is_array($value)) {
            return $value['en'] ?? reset($value) ?: '';
        }

        if (is_string($value)) {
            $decoded = json_decode($value, true);

            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $decoded['en'] ?? reset($decoded) ?: '';
            }
        }

        return (string) ($value ?? '');
    };

    $headerNavigation = collect($customHeaderNavigation ?? []);
    $secondaryNavigation = collect($customSecondaryNavigation ?? []);
    $megaNavigationItem = $headerNavigation->firstWhere('link_type', 'mega_exams');
    $showInstitutesInHeader = collect($navbarPages ?? [])->contains(function ($page) use ($websiteNavbarText) {
        return \Illuminate\Support\Str::slug($websiteNavbarText($page->short_title ?? '')) === 'for-institutes';
    });
@endphp
<style>
    :root {
        --ew-primary: var(--theme-primary, #08786c);
        --ew-primary-dark: color-mix(in srgb, var(--theme-primary, #0f766e) 80%, var(--theme-header-text, #0f172a));
        --ew-accent: var(--theme-secondary, #f47a10);
        --ew-ink: var(--theme-heading, #101828);
        --ew-text: var(--theme-text, #475467);
        --ew-surface: var(--theme-card-bg, #ffffff);
        --ew-canvas: var(--theme-body-bg, #f6f9f9);
        --ew-border: var(--theme-border, #d0d5dd);
        --ew-radius: 16px;
        --ew-shadow: 0 16px 40px rgba(16, 40, 38, .09);
    }
    .custom-btn {
        font-size: 15px;
        font-family: Inter, sans-serif;
    }

    .package-item-card {
        box-shadow: 0 3px 3px #38414a1a;
        margin: 10px 0;
        border: 1px solid #38414a1a;
    }

    .examelite-header {
        background: color-mix(in srgb, var(--theme-header-bg, #ffffff) 96%, var(--theme-primary, #0f766e));
        border-bottom: 1px solid color-mix(in srgb, var(--theme-primary, #0f766e) 18%, transparent);
        box-shadow: 0 10px 28px rgba(15, 23, 42, 0.06);
        padding: 14px 0;
        z-index: 1030;
    }

    .examelite-header::after {
        content: "";
        position: absolute;
        left: 0;
        right: 0;
        bottom: -1px;
        height: 1px;
        background: color-mix(in srgb, var(--theme-primary, #0f766e) 24%, transparent);
        opacity: 1;
    }

    .examelite-header.has-secondary-navigation {
        border-bottom: 0;
        box-shadow: none;
    }

    .examelite-header.has-secondary-navigation::after {
        display: none;
    }

    .examelite-header .custom-container {
        align-items: center;
        max-width: none;
        padding-inline: clamp(32px, 4vw, 80px);
        width: 100%;
    }

    .examelite-header .navbar-brand {
        align-items: center;
        background: color-mix(in srgb, var(--theme-card-bg, #ffffff) 88%, var(--theme-primary, #0f766e));
        border: 1px solid color-mix(in srgb, var(--theme-primary, #0f766e) 10%, transparent);
        border-radius: 14px;
        display: inline-flex;
        flex: 0 0 auto;
        min-height: 54px;
        padding: 6px 9px;
        width: fit-content;
        max-width: max-content;
    }

    .examelite-header .navbar-brand img {
        max-height: 42px;
        width: auto;
    }

    .examelite-brand-fallback {
        align-items: center;
        display: inline-flex;
        gap: 9px;
        min-width: 0;
        text-decoration: none;
    }

    .examelite-brand-mark {
        align-items: center;
        background: var(--theme-primary, #0f766e);
        border-radius: 12px;
        box-shadow: 0 10px 20px color-mix(in srgb, var(--theme-primary, #0f766e) 18%, transparent);
        color: var(--theme-button-text, #ffffff);
        display: inline-flex;
        flex: 0 0 42px;
        font-size: 22px;
        height: 42px;
        justify-content: center;
        width: 42px;
    }

    .examelite-brand-text {
        color: var(--theme-header-text, #0f172a);
        display: inline-flex;
        flex-direction: column;
        font-weight: 900;
        letter-spacing: 0;
        line-height: 1;
        min-width: 0;
        text-decoration: none;
    }

    .examelite-brand-text span {
        color: var(--theme-header-text, #0f172a);
        font-size: clamp(1.15rem, 1.7vw, 1.38rem);
        max-width: 180px;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .examelite-brand-text small {
        color: var(--theme-primary, #0f766e);
        font-size: 9px;
        font-weight: 900;
        letter-spacing: .06em;
        margin-top: 4px;
        text-transform: uppercase;
        white-space: nowrap;
    }

    .examelite-header .navbar-toggler {
        align-items: center;
        background: var(--theme-primary, #0f766e);
        border: 0;
        border-radius: 12px;
        color: var(--theme-button-text, #ffffff) !important;
        display: none;
        height: 44px;
        justify-content: center;
        width: 44px;
    }

    .examelite-header .navbar-toggler:focus {
        box-shadow: 0 0 0 3px color-mix(in srgb, var(--theme-primary, #0f766e) 18%, transparent);
    }

    .examelite-header .navbar-toggler i {
        color: var(--theme-button-text, #ffffff);
        font-size: 24px;
    }
    .browse-exams-menu {
        min-width: 380px;
        padding: 14px;
        border-radius: 14px !important;
    }

    .browse-menu-title {
        font-size: 13px;
        font-weight: 800;
        color: var(--theme-heading, #0f172a);
        padding: 4px 8px 10px;
        border-bottom: 1px solid #e2e8f0;
        margin-bottom: 8px;
        text-transform: uppercase;
        letter-spacing: .04em;
    }
    .browse-menu-card {
        display: grid;
        grid-template-columns: 42px 1fr;
        align-items: center;
        gap: 12px;
        padding: 11px 10px;
        color: color-mix(in srgb, var(--theme-heading, #0f172a) 82%, #64748b);
        text-decoration: none;
        font-weight: 700;
        border-radius: 10px;
        border: 1px solid transparent;
    }

    .browse-menu-card + .browse-menu-card {
        border-top: 1px solid #f1f5f9;
    }

    .browse-menu-card:hover {
        color: var(--theme-primary);
        background: color-mix(in srgb, var(--theme-primary, #0f766e) 8%, #ffffff);
        border-color: color-mix(in srgb, var(--theme-primary, #0f766e) 20%, transparent);
        transform: translateX(2px);
    }

    .browse-menu-icon {
        width: 38px;
        height: 38px;
        border-radius: 10px;
        background: color-mix(in srgb, var(--theme-primary, #0f766e) 10%, #ffffff);
        color: var(--theme-primary);
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 20px;
    }

    .browse-menu-copy {
        display: flex;
        flex-direction: column;
        gap: 2px;
    }

    .browse-menu-copy small {
        color: var(--theme-text, #64748b);
        font-weight: 500;
        font-size: 12px;
    }

    .examelite-header .navbar-nav {
        align-items: center;
        background: var(--theme-card-bg, #ffffff);
        border: 0;
        border-radius: 999px;
        gap: 12px;
        padding: 4px;
    }

    .examelite-header .nav-link {
        border: 1px solid transparent;
        color: var(--theme-header-text, #0f172a);
        font-weight: 800;
        font-size: 16px;
        line-height: 1;
        padding: 11px 13px !important;
        border-radius: 999px;
        transition: background-color .18s ease, border-color .18s ease, color .18s ease;
        white-space: nowrap;
        -webkit-text-fill-color: var(--theme-header-text, #0f172a);
    }

    .examelite-header .nav-link:hover,
    .examelite-header .nav-link:focus,
    .examelite-header .nav-link.active,
    .examelite-header .navbar-nav .show > .nav-link {
        background: color-mix(in srgb, var(--theme-primary, #0f766e) 12%, #ffffff) !important;
        border-color: color-mix(in srgb, var(--theme-primary, #0f766e) 24%, #ffffff) !important;
        box-shadow: none !important;
        color: var(--theme-primary, #0f766e) !important;
        -webkit-text-fill-color: var(--theme-primary, #0f766e) !important;
    }

    .examelite-header .dropdown-menu {
        background: var(--theme-card-bg, #ffffff);
        border: 1px solid var(--theme-border, #d0d5dd);
        box-shadow: 0 18px 45px rgba(15, 23, 42, 0.12);
        border-radius: 10px;
    }

    .examelite-header .dropdown-item {
        color: color-mix(in srgb, var(--theme-heading, #0f172a) 82%, #64748b);
        font-weight: 600;
        padding: 9px 14px;
    }

    .examelite-header .dropdown-item:hover,
    .examelite-header .dropdown-item:focus,
    .examelite-header .dropdown-item.active {
        color: var(--theme-primary, #0f766e) !important;
        background: color-mix(in srgb, var(--theme-primary, #0f766e) 10%, #ffffff) !important;
        -webkit-text-fill-color: var(--theme-primary, #0f766e) !important;
    }

    .examelite-mega {
        min-width: 760px;
        padding: 18px;
    }

    .examelite-mega-grid {
        display: grid;
        grid-template-columns: 220px 1fr;
        gap: 18px;
    }

    .examelite-mega-groups {
        border-right: 1px solid var(--theme-border, #e2e8f0);
        padding-right: 14px;
        max-height: 350px;
        overflow-y: auto;
    }

    .examelite-mega-packages {
        max-height: 350px;
        overflow-y: auto;
    }

    .examelite-mega .nav-pills .nav-link {
        width: 100%;
        text-align: left;
        color: var(--theme-header-text, #0f172a);
        font-size: 14px;
        padding: 9px 10px !important;
    }

    .examelite-mega .nav-pills .nav-link:hover,
    .examelite-mega .nav-pills .nav-link:focus {
        background: color-mix(in srgb, var(--theme-primary, #0f766e) 10%, #ffffff) !important;
        color: var(--theme-primary, #0f766e) !important;
        -webkit-text-fill-color: var(--theme-primary, #0f766e) !important;
    }

    .examelite-mega .nav-pills .nav-link.active {
        background: var(--theme-primary, #0f766e) !important;
        color: var(--theme-button-text, #ffffff) !important;
        -webkit-text-fill-color: var(--theme-button-text, #ffffff) !important;
    }

    .examelite-package-link {
        display: flex;
        justify-content: space-between;
        gap: 12px;
        padding: 10px 12px;
        margin-bottom: 8px;
        border: 1px solid var(--theme-border, #e2e8f0);
        border-radius: 8px;
        color: var(--theme-heading, #0f172a);
        text-decoration: none;
        background: var(--theme-card-bg, #ffffff);
    }

    .examelite-package-link:hover,
    .examelite-package-link:focus {
        border-color: color-mix(in srgb, var(--theme-primary, #0f766e) 35%, #ffffff);
        color: var(--theme-primary, #0f766e) !important;
        background: color-mix(in srgb, var(--theme-primary, #0f766e) 8%, #ffffff);
        -webkit-text-fill-color: var(--theme-primary, #0f766e);
    }

    .examelite-package-link small {
        white-space: nowrap;
        color: var(--theme-text, #64748b);
    }

    .examelite-mega {
        left: 50% !important;
        max-height: min(68vh, 560px);
        overflow: hidden;
        transform: translateX(-50%) !important;
        width: min(1080px, calc(100vw - 40px));
    }

    .examelite-mega-grid { grid-template-columns: 190px minmax(0, 1fr); }
    .examelite-mega-content { min-width: 0; }
    .mega-eyebrow { color: var(--ew-primary); font-size: 11px; font-weight: 900; letter-spacing: .08em; margin-bottom: 10px; text-transform: uppercase; }
    .examelite-mega-groups .nav-link { align-items: center; display: flex; justify-content: space-between; }
    .mega-panel-head { align-items: center; border-bottom: 1px solid var(--ew-border); display: flex; justify-content: space-between; margin-bottom: 14px; padding-bottom: 12px; }
    .mega-panel-head h3 { color: var(--ew-ink); font-size: 1.15rem; font-weight: 900; margin: 0; }
    .mega-panel-head > a, .mega-view-all { color: var(--ew-primary); font-size: 12px; font-weight: 850; text-decoration: none; }
    .mega-panel-grid { align-items: start; display: grid; gap: 16px; grid-template-columns: minmax(0, 1fr) 250px; }
    .mega-category-grid { align-content: start; display: grid; gap: 9px; grid-auto-rows: max-content; grid-template-columns: repeat(2, minmax(0, 1fr)); max-height: 350px; overflow: auto; padding-right: 4px; }
    .mega-category-card { align-self: start; background: color-mix(in srgb, var(--ew-primary) 4%, var(--ew-surface)); border: 1px solid var(--ew-border); border-radius: 13px; min-height: 0; padding: 10px 12px; }
    .mega-category-card:only-child { grid-column: 1 / -1; }
    .mega-category-title { align-items: center; color: var(--ew-ink); display: flex; font-size: 14px; font-weight: 900; gap: 9px; text-decoration: none; }
    .mega-category-title:hover { color: var(--ew-primary); }
    .mega-category-icon { align-items: center; background: color-mix(in srgb, var(--ew-primary) 11%, var(--ew-surface)); border-radius: 10px; color: var(--ew-primary); display: inline-flex; flex: 0 0 34px; height: 34px; justify-content: center; }
    .mega-category-card p, .mega-muted { color: var(--ew-text); font-size: 11px; line-height: 1.4; margin: 6px 0; }
    .mega-subcategories { display: flex; flex-wrap: wrap; gap: 5px; }
    .mega-subcategories a { background: var(--ew-surface); border: 1px solid var(--ew-border); border-radius: 999px; color: var(--ew-text); font-size: 10px; font-weight: 750; padding: 5px 8px; text-decoration: none; }
    .mega-subcategories a:hover { border-color: var(--ew-primary); color: var(--ew-primary); }
    .mega-featured-packages { align-self: start; background: color-mix(in srgb, var(--ew-accent) 5%, var(--ew-surface)); border: 1px solid color-mix(in srgb, var(--ew-accent) 18%, var(--ew-surface)); border-radius: 14px; max-height: 350px; overflow: auto; padding: 11px; }
    .examelite-package-link { align-items: center; background: var(--ew-surface); border-radius: 11px; flex-direction: row; margin-bottom: 6px; padding: 8px 9px; }
    .examelite-package-link span { display: flex; flex-direction: column; min-width: 0; }
    .examelite-package-link strong { font-size: 11px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .examelite-package-link small { font-size: 10px; }
    .examelite-package-link em { color: var(--ew-primary); font-size: 10px; font-style: normal; font-weight: 800; white-space: nowrap; }
    .mega-footer-links { background: color-mix(in srgb, var(--ew-primary) 5%, var(--ew-surface)); border-radius: 12px; display: flex; gap: 8px; justify-content: center; margin-top: 14px; padding: 9px; }
    .mega-footer-links a { color: var(--ew-ink); font-size: 12px; font-weight: 800; padding: 6px 12px; text-decoration: none; }
    .mega-footer-links i { color: var(--ew-accent); margin-right: 4px; }
    .mega-fallback { display: flex; gap: 12px; justify-content: center; padding: 30px; }
    .mega-fallback a, .mega-empty { color: var(--ew-primary); font-weight: 800; }
    .header-cart-link {
        align-items: center;
        background: transparent;
        border: 0;
        border-radius: 999px;
        color: var(--theme-primary, #0f766e);
        display: inline-flex;
        height: 42px;
        justify-content: center;
        text-decoration: none;
        width: 46px;
    }

    .header-cart-link i {
        color: currentColor !important;
    }

    .header-cart-link:hover,
    .header-cart-link:focus,
    .header-cart-link:active,
    .header-cart-link[aria-expanded="true"] {
        background: color-mix(in srgb, var(--theme-primary, #0f766e) 12%, var(--theme-card-bg, #ffffff)) !important;
        border-color: color-mix(in srgb, var(--theme-primary, #0f766e) 24%, #ffffff) !important;
        color: var(--theme-primary, #0f766e) !important;
        outline: none;
        -webkit-text-fill-color: var(--theme-primary, #0f766e) !important;
    }

    .header-cart-link:hover i,
    .header-cart-link:focus i,
    .header-cart-link:active i,
    .header-cart-link[aria-expanded="true"] i {
        color: var(--theme-primary, #0f766e) !important;
    }

    .header-cart-badge {
        background: var(--theme-secondary, #f59e0b) !important;
        color: var(--theme-button-text, #ffffff) !important;
        font-size: 0.65rem;
        line-height: 1;
        padding: 0.3em 0.5em;
    }

    .header-cta {
        align-items: center;
        background: transparent !important;
        border: 0 !important;
        color: var(--theme-header-text, #0f172a) !important;
        display: inline-flex;
        font-weight: 800;
        height: 42px;
        justify-content: center;
        min-width: 92px;
        white-space: nowrap;
    }

    .header-cta:hover,
    .header-cta:focus,
    .header-cta:active {
        background: color-mix(in srgb, var(--theme-primary, #0f766e) 12%, #ffffff) !important;
        border-color: color-mix(in srgb, var(--theme-primary, #0f766e) 24%, #ffffff) !important;
        color: var(--theme-primary, #0f766e) !important;
        -webkit-text-fill-color: var(--theme-primary, #0f766e) !important;
    }

    .header-login {
        align-items: center;
        background: var(--theme-primary, #0f766e) !important;
        border: 1px solid var(--theme-primary, #0f766e) !important;
        color: var(--theme-button-text, #ffffff) !important;
        display: inline-flex;
        font-weight: 800;
        height: 42px;
        justify-content: center;
        min-width: 82px;
        white-space: nowrap;
    }

    .header-login:hover,
    .header-login:focus,
    .header-login:active {
        background: color-mix(in srgb, var(--theme-primary, #0f766e) 88%, #000000) !important;
        border-color: color-mix(in srgb, var(--theme-primary, #0f766e) 88%, #000000) !important;
        color: var(--theme-button-text, #ffffff) !important;
        -webkit-text-fill-color: var(--theme-button-text, #ffffff) !important;
    }

    .header-login i {
        color: inherit !important;
    }

    .header-actions {
        align-items: center;
        background: transparent;
        border: 0;
        border-radius: 999px;
        display: inline-flex;
        gap: 10px;
        padding: 0;
    }

    .header-actions .btn,
    .header-actions .header-cart-link {
        border-radius: 999px !important;
        box-shadow: none !important;
        font-size: 16px;
        padding-left: 13px !important;
        padding-right: 13px !important;
    }

    .header-actions .header-cart-link {
        padding-left: 0 !important;
        padding-right: 0 !important;
    }

    @media (min-width: 992px) and (max-width: 1399.98px) {
        .examelite-header .custom-container {
            flex-wrap: nowrap;
            padding-inline: 24px;
        }
        .examelite-header .navbar-brand {
            min-height: 50px;
            padding: 5px 7px;
        }
        .examelite-brand-mark {
            flex-basis: 40px;
            height: 40px;
            width: 40px;
        }
        .examelite-brand-text span {
            font-size: 1.2rem;
        }
        .examelite-header .navbar-nav {
            flex-wrap: nowrap;
            gap: 5px;
            padding: 2px;
        }
        .examelite-header .nav-link {
            font-size: 15px;
            padding: 10px 9px !important;
        }
        .examelite-header .navbar-collapse,
        .header-actions,
        .header-actions > .d-flex {
            flex-wrap: nowrap !important;
        }
        .header-search-trigger-desktop {
            margin-left: 4px;
            margin-right: 4px;
            min-width: 112px;
            padding-inline: 8px;
        }
        .header-actions {
            gap: 8px;
        }
    }

    @media (min-width: 1400px) {
        .examelite-header .navbar-collapse { column-gap: 18px; }
        .header-actions { gap: 12px; }
    }

    @media (max-width: 991.98px) {
        .examelite-header {
            padding: 10px 0;
        }

        .examelite-header .navbar-brand {
            min-height: 48px;
        }

        .examelite-header .navbar-brand img {
            max-height: 36px;
        }

        .examelite-brand-mark {
            flex-basis: 38px;
            font-size: 20px;
            height: 38px;
            width: 38px;
        }

        .examelite-brand-text span {
            max-width: 160px;
        }

        .examelite-header .navbar-toggler {
            display: inline-flex;
        }

        .examelite-header .navbar-collapse {
            background: var(--theme-card-bg, #ffffff);
            border: 1px solid color-mix(in srgb, var(--theme-primary, #0f766e) 12%, #e2e8f0);
            border-radius: 18px;
            box-shadow: 0 16px 36px rgba(15, 23, 42, 0.10);
            margin-top: 12px;
            padding: 14px;
        }

        .examelite-mega {
            left: auto !important;
            max-height: 68vh;
            min-width: 100%;
            overflow: auto;
            padding: 12px;
            transform: none !important;
            width: 100%;
        }

        .examelite-mega-grid {
            grid-template-columns: 1fr;
        }

        .mega-panel-grid, .mega-category-grid { grid-template-columns: 1fr; }
        .mega-featured-packages { display: none; }
        .mega-footer-links { flex-wrap: wrap; justify-content: flex-start; }

        .examelite-mega-groups {
            border-right: 0;
            border-bottom: 1px solid #e2e8f0;
            padding-right: 0;
            padding-bottom: 12px;
        }

        .examelite-header .navbar-nav {
            align-items: stretch;
            background: transparent;
            border: 0;
            border-radius: 0;
            padding: 0;
        }

        .examelite-header .nav-link {
            align-items: center;
            background: color-mix(in srgb, var(--theme-primary, #0f766e) 4%, var(--theme-card-bg, #ffffff));
            border: 1px solid color-mix(in srgb, var(--theme-primary, #0f766e) 10%, #e2e8f0);
            border-radius: 12px;
            display: flex;
            justify-content: space-between;
            line-height: 1.2;
            margin-bottom: 8px;
            min-height: 52px;
            padding: 14px 16px !important;
            width: 100%;
        }

        .examelite-header .nav-link::after {
            color: currentColor;
        }

        .browse-exams-menu {
            border-radius: 14px !important;
            min-width: 100%;
            padding: 10px;
        }

        .examelite-header .d-flex.align-items-center.ms-lg-auto {
            align-items: stretch !important;
            gap: 10px;
            padding-top: 10px;
            flex-wrap: wrap;
        }

        .examelite-header .nav-item.align-self-center {
            margin-right: 0 !important;
        }

    .header-cart-link {
            background: transparent;
            border: 1px solid color-mix(in srgb, var(--theme-primary, #0f766e) 22%, #e2e8f0);
            color: var(--theme-primary, #0f766e);
            gap: 10px;
            height: 46px;
            justify-content: center;
            margin: 2px 0 0;
            padding-left: 16px !important;
            padding-right: 16px !important;
            width: min(100%, 240px);
        }

        .header-cart-link i {
            order: 1;
        }

        .header-cart-link::after {
            content: "Cart";
            color: var(--theme-heading, #0f172a);
            font-size: 15px;
            font-weight: 800;
            order: 2;
        }

        .header-cart-link:hover,
        .header-cart-link:focus,
        .header-cart-link:active,
        .header-cart-link[aria-expanded="true"] {
            background: color-mix(in srgb, var(--theme-primary, #0f766e) 10%, var(--theme-card-bg, #ffffff));
            border-color: color-mix(in srgb, var(--theme-primary, #0f766e) 24%, #e2e8f0);
            color: var(--theme-primary, #0f766e) !important;
        }

        .header-cart-link:hover i,
        .header-cart-link:focus i,
        .header-cart-link:active i,
        .header-cart-link[aria-expanded="true"] i {
            color: var(--theme-primary, #0f766e) !important;
        }

        .header-cart-badge {
            left: auto !important;
            margin-left: 2px;
            order: 3;
            position: static !important;
            top: auto !important;
            transform: none !important;
        }

        .header-actions {
            align-items: stretch;
            background: transparent;
            border: 0;
            border-radius: 0;
            flex-direction: column;
            gap: 8px;
            padding: 0;
            width: 100%;
        }

        .examelite-header .nav-link:hover,
        .examelite-header .nav-link:focus,
        .examelite-header .nav-link.active {
            background: color-mix(in srgb, var(--theme-primary, #0f766e) 12%, #ffffff) !important;
            border-color: color-mix(in srgb, var(--theme-primary, #0f766e) 24%, #ffffff) !important;
            color: var(--theme-primary, #0f766e) !important;
            -webkit-text-fill-color: var(--theme-primary, #0f766e) !important;
        }

        .examelite-header .custom-btn {
            width: 100%;
            justify-content: center;
            min-height: 50px;
        }

        .examelite-header .mt-3.mt-lg-0 {
            width: 100%;
            flex-direction: column;
            align-items: stretch !important;
        }
    }

    /* Responsive header refinement */
    .examelite-header .nav-link:hover,
    .examelite-header .nav-link:focus,
    .examelite-header .nav-link.active,
    .examelite-header .navbar-nav .show > .nav-link,
    .examelite-header .nav-link[aria-expanded="true"] {
        background: var(--theme-primary, #0f766e) !important;
        border-color: var(--theme-primary, #0f766e) !important;
        box-shadow: 0 7px 16px color-mix(in srgb, var(--theme-primary, #0f766e) 20%, transparent) !important;
        color: var(--theme-button-text, #ffffff) !important;
        -webkit-text-fill-color: var(--theme-button-text, #ffffff) !important;
    }

    @media (min-width: 992px) {
        .examelite-mega {
            background: var(--ew-surface);
            border: 1px solid color-mix(in srgb, var(--ew-primary) 16%, var(--ew-border));
            border-top: 3px solid var(--ew-primary);
            border-radius: 16px;
            box-shadow: 0 24px 60px rgba(15, 23, 42, .16);
            max-height: min(72vh, 620px);
            padding: 20px;
            width: min(1120px, calc(100vw - 40px));
        }

        .examelite-mega-grid {
            gap: 20px;
            grid-template-columns: 210px minmax(0, 1fr);
        }

        .examelite-mega-groups {
            background: color-mix(in srgb, var(--ew-primary) 4%, var(--ew-surface));
            border: 1px solid var(--ew-border);
            border-radius: 14px;
            max-height: 430px;
            padding: 12px;
        }

        .examelite-mega-groups .nav-link {
            margin-bottom: 4px;
        }

        .examelite-mega-groups .mega-view-all {
            display: inline-flex;
            margin: 9px 8px 0;
        }

        .mega-category-card {
            padding: 11px 12px;
            transition: border-color .18s ease, box-shadow .18s ease, transform .18s ease;
        }

        .mega-category-card:hover {
            border-color: color-mix(in srgb, var(--ew-primary) 38%, var(--ew-border));
            box-shadow: 0 10px 24px rgba(15, 23, 42, .08);
            transform: translateY(-2px);
        }

        .mega-footer-links a {
            border-radius: 9px;
            transition: background-color .18s ease, color .18s ease;
        }

        .mega-footer-links a:hover,
        .mega-footer-links a:focus {
            background: var(--ew-primary);
            color: var(--theme-button-text, #ffffff);
        }

        .mega-footer-links a:hover i,
        .mega-footer-links a:focus i {
            color: inherit;
        }
    }

    @media (max-width: 991.98px) {
        .examelite-header .custom-container {
            justify-content: flex-start;
        }

        .examelite-header .navbar-toggler {
            flex: 0 0 44px;
            margin-left: auto;
            margin-right: 0;
            order: 2;
        }

        .examelite-header .navbar-brand {
            flex: 0 1 auto;
            justify-content: flex-start;
            margin-right: auto;
            min-width: 0;
            order: 1;
            width: auto;
        }

        .examelite-header .navbar-collapse {
            border-radius: 18px;
            flex-basis: auto;
            left: 50%;
            margin: 0;
            max-height: calc(100vh - 72px);
            max-width: min(92vw, 430px);
            order: 4;
            overflow-y: auto;
            position: absolute;
            text-align: left;
            top: 100%;
            transform: translateX(-50%);
            width: min(92vw, 430px);
        }

        .examelite-header .navbar-nav {
            align-items: stretch;
            width: 100%;
        }

        .examelite-header .dropdown-menu.examelite-mega {
            inset: auto auto auto 0 !important;
            margin-left: 0 !important;
            margin-right: auto !important;
            max-height: 72vh;
            position: relative !important;
            width: 100%;
        }

        .examelite-header .examelite-mega-grid {
            display: block;
        }

        .examelite-header .examelite-mega-groups {
            background: color-mix(in srgb, var(--theme-primary, #0f766e) 4%, #ffffff);
            border: 1px solid var(--ew-border);
            border-radius: 12px;
            margin-bottom: 12px;
            max-height: none;
            overflow: visible;
            padding: 10px;
        }

        .examelite-header .examelite-mega-groups .nav {
            display: flex;
            flex-direction: column !important;
            flex-wrap: nowrap;
            gap: 5px;
            max-height: 34vh;
            overflow-x: hidden;
            overflow-y: auto;
            overscroll-behavior: contain;
            padding: 0 4px 0 0;
            scrollbar-width: thin;
        }

        .examelite-header .examelite-mega-groups .nav-link {
            flex: 0 0 auto;
            justify-content: space-between;
            margin: 0;
            min-height: 44px;
            padding: 11px 13px !important;
            text-align: left;
            width: 100%;
        }

        .examelite-header .examelite-mega-groups .nav-link i {
            display: inline-block;
        }

        .examelite-header .examelite-mega-groups .mega-view-all {
            display: inline-flex;
            margin: 7px 4px 0;
        }

        .examelite-header .mega-panel-head {
            align-items: flex-start;
            gap: 10px;
        }

        .examelite-header .mega-panel-head h3 {
            font-size: 1rem;
        }

        .examelite-header .mega-category-grid {
            max-height: none;
            overflow: visible;
            padding-right: 0;
        }

        .examelite-header .mega-featured-packages {
            display: block;
            margin-top: 12px;
            max-height: none;
            overflow: visible;
        }

        .examelite-header .mega-category-card {
            background: var(--theme-card-bg, #ffffff);
            padding: 11px;
        }

        .examelite-header .mega-category-card p {
            display: none;
        }

        .examelite-header .mega-subcategories {
            gap: 6px;
            margin-top: 9px;
        }

        .examelite-header .mega-subcategories a {
            background: color-mix(in srgb, var(--theme-primary, #0f766e) 6%, #ffffff);
            border-color: color-mix(in srgb, var(--theme-primary, #0f766e) 18%, var(--ew-border));
            color: var(--theme-heading, #0f172a);
            font-size: 11px;
            padding: 7px 9px;
        }

        .examelite-header .nav-link {
            justify-content: flex-start;
            text-align: left;
        }

        .examelite-header .nav-link::after {
            margin-left: auto;
        }

        .examelite-header .nav-link.active,
        .examelite-header .navbar-nav .show > .nav-link,
        .examelite-header .nav-link[aria-expanded="true"] {
            background: var(--theme-primary, #0f766e) !important;
            border-color: var(--theme-primary, #0f766e) !important;
            color: var(--theme-button-text, #ffffff) !important;
            -webkit-text-fill-color: var(--theme-button-text, #ffffff) !important;
        }

        .header-actions {
            align-items: stretch;
            flex-direction: column;
            gap: 9px;
            padding-top: 2px;
            width: 100%;
        }

        .header-actions > .d-flex {
            flex: 0 0 auto;
            min-width: 0;
            width: 100%;
        }

        .header-actions #headerCartControl {
            align-self: flex-start !important;
        }

        .header-actions .header-cart-link {
            background: color-mix(in srgb, var(--theme-primary, #0f766e) 7%, var(--theme-card-bg, #ffffff));
            flex: 0 0 48px;
            height: 48px;
            margin: 0;
            padding: 0 !important;
            width: 48px;
        }

        .header-actions .header-cart-link::after {
            content: none;
        }

        .header-actions .header-cart-link i {
            order: initial;
        }

        .header-actions .header-cart-link:hover,
        .header-actions .header-cart-link:focus,
        .header-actions .header-cart-link:active,
        .header-actions .header-cart-link[aria-expanded="true"] {
            background: var(--theme-primary, #0f766e) !important;
            border-color: var(--theme-primary, #0f766e) !important;
            color: var(--theme-button-text, #ffffff) !important;
            -webkit-text-fill-color: var(--theme-button-text, #ffffff) !important;
        }

        .header-actions .header-cart-link:hover i,
        .header-actions .header-cart-link:focus i,
        .header-actions .header-cart-link:active i,
        .header-actions .header-cart-link[aria-expanded="true"] i {
            color: inherit !important;
        }

        .header-actions .header-cart-badge {
            left: auto !important;
            margin: 0;
            position: absolute !important;
            right: -5px;
            top: -5px !important;
            transform: none !important;
        }

        .examelite-header .custom-btn {
            min-height: 48px;
            width: 100%;
        }
    }
    .header-search-trigger {
        align-items: center;
        background: var(--theme-card-bg, #ffffff);
        border: 0;
        border-radius: 999px;
        color: var(--theme-header-text, #0f172a);
        display: inline-flex;
        flex: 0 0 auto;
        font-size: 21px;
        font-weight: 800;
        gap: 7px;
        height: 44px;
        justify-content: center;
        margin: 0 10px;
        min-width: 108px;
        padding: 0 15px;
        transition: background-color .2s ease, border-color .2s ease, color .2s ease, box-shadow .2s ease;
        white-space: nowrap;
    }
    .header-search-trigger span { font-size: 15px; }
    .header-search-trigger-desktop { margin-left: 12px; margin-right: 12px; min-width: 150px; }
    .header-search-trigger:hover,
    .header-search-trigger:focus,
    .header-search-trigger[aria-expanded="true"] {
        background: color-mix(in srgb, var(--theme-secondary, #f59e0b) 14%, transparent);
        box-shadow: none;
        color: var(--theme-secondary, #f59e0b);
        outline: 0;
    }
    .header-search-overlay {
        background: var(--theme-card-bg, #ffffff);
        border-top: 1px solid var(--theme-border, #e2e8f0);
        box-shadow: 0 18px 36px rgba(15, 23, 42, .16);
        left: 0;
        opacity: 0;
        padding: 18px 20px 22px;
        pointer-events: none;
        position: absolute;
        right: 0;
        top: 100%;
        transform: translateY(-8px);
        transition: opacity .2s ease, transform .2s ease, visibility .2s ease;
        visibility: hidden;
        z-index: 1090;
    }
    .header-search-overlay.is-open {
        opacity: 1;
        pointer-events: auto;
        transform: translateY(0);
        visibility: visible;
    }
    .header-search-overlay-inner {
        margin: 0 auto;
        max-width: 860px;
        position: relative;
    }
    .header-site-search-form {
        align-items: center;
        background: var(--theme-card-bg, #ffffff);
        border: 2px solid var(--theme-secondary, #f59e0b);
        border-radius: 10px;
        box-shadow: 0 0 0 4px color-mix(in srgb, var(--theme-secondary, #f59e0b) 9%, transparent);
        display: flex;
        height: 54px;
        padding: 0 14px;
        transition: box-shadow .2s ease;
        width: 100%;
    }
    .header-site-search-form:focus-within {
        box-shadow: 0 0 0 5px color-mix(in srgb, var(--theme-secondary, #f59e0b) 15%, transparent);
    }
    .header-site-search-form > i {
        color: var(--theme-primary, #0f766e);
        flex: 0 0 auto;
        font-size: 23px;
    }
    .header-site-search-input {
        background: transparent;
        border: 0;
        color: var(--theme-heading, #0f172a);
        flex: 1 1 auto;
        font-size: 16px;
        font-weight: 700;
        min-width: 0;
        outline: 0;
        padding: 0 12px;
        width: 100%;
    }
    .header-site-search-input::placeholder {
        color: var(--theme-text, #64748b);
        font-weight: 600;
    }
    .header-site-search-spinner {
        color: var(--theme-secondary, #f59e0b);
        display: none;
        flex: 0 0 auto;
        margin-right: 8px;
    }
    .header-search-overlay.is-loading .header-site-search-spinner { display: inline-block; }
    .header-search-close {
        align-items: center;
        background: transparent;
        border: 0;
        border-radius: 50%;
        color: var(--theme-text, #64748b);
        display: inline-flex;
        flex: 0 0 36px;
        font-size: 22px;
        height: 36px;
        justify-content: center;
        padding: 0;
        width: 36px;
    }
    .header-search-close:hover,
    .header-search-close:focus {
        background: color-mix(in srgb, var(--theme-secondary, #f59e0b) 13%, transparent);
        color: var(--theme-secondary, #f59e0b);
        outline: 0;
    }
    .header-site-search-results {
        background: var(--theme-card-bg, #ffffff);
        border: 1px solid var(--theme-border, #e2e8f0);
        border-radius: 12px;
        box-shadow: 0 18px 44px rgba(15, 23, 42, .18);
        display: none;
        left: 0;
        max-height: min(440px, 58vh);
        overflow-y: auto;
        padding: 7px;
        position: absolute;
        right: 0;
        top: calc(100% + 8px);
    }
    .header-site-search-results.is-open { display: block; }
    .header-search-result {
        align-items: center;
        border-radius: 9px;
        color: var(--theme-heading, #0f172a);
        display: grid;
        gap: 10px;
        grid-template-columns: 36px minmax(0, 1fr) auto;
        padding: 9px;
        text-decoration: none;
    }
    .header-search-result:hover,
    .header-search-result.is-active {
        background: color-mix(in srgb, var(--theme-primary, #0f766e) 9%, var(--theme-card-bg, #ffffff));
        color: var(--theme-primary, #0f766e) !important;
    }
    .header-search-result-icon {
        align-items: center;
        background: color-mix(in srgb, var(--theme-primary, #0f766e) 11%, var(--theme-card-bg, #ffffff));
        border-radius: 8px;
        color: var(--theme-primary, #0f766e);
        display: inline-flex;
        font-size: 17px;
        height: 36px;
        justify-content: center;
        width: 36px;
    }
    .header-search-result-label { font-size: 13px; font-weight: 800; line-height: 1.25; min-width: 0; }
    .header-search-result-type { color: var(--theme-text, #64748b); font-size: 11px; font-weight: 700; }
    .header-search-message { color: var(--theme-text, #64748b); font-size: 13px; padding: 13px; text-align: center; }
    .header-search-more {
        align-items: center;
        background: color-mix(in srgb, var(--theme-secondary, #f97316) 8%, var(--theme-card-bg, #ffffff));
        border: 1px solid color-mix(in srgb, var(--theme-secondary, #f97316) 45%, transparent);
        border-radius: 9px;
        color: var(--theme-secondary, #f97316);
        cursor: pointer;
        display: flex;
        font-size: 12px;
        font-weight: 800;
        justify-content: center;
        margin: 7px 2px 2px;
        padding: 10px 12px;
        width: calc(100% - 4px);
    }
    .header-search-more:hover,
    .header-search-more:focus {
        background: var(--theme-secondary, #f97316);
        color: #ffffff;
    }
    @media (max-width: 991.98px) {
        .examelite-header .custom-container {
            align-items: center;
            flex-wrap: nowrap;
            padding-inline: 12px;
        }
        .examelite-header .navbar-brand {
            background: transparent;
            border: 0;
            border-radius: 0;
            margin-right: auto;
            max-width: calc(100% - 126px);
            min-height: auto;
            padding: 0;
            width: fit-content;
        }
        .examelite-brand-fallback {
            gap: 6px;
        }
        .header-search-trigger-mobile {
            align-self: center;
            flex: 0 0 38px;
            font-size: 20px;
            height: 38px;
            margin: 0 6px 0 0;
            min-width: 38px;
            order: 2;
            padding: 0;
            width: 38px;
        }
        .header-search-trigger-mobile span { display: none; }
        .examelite-header .navbar-toggler {
            align-self: center;
            flex: 0 0 38px;
            height: 38px;
            margin-left: 0;
            margin-right: 0;
            order: 4;
            padding: 0 !important;
            width: 38px;
        }
        .examelite-header .navbar-toggler i {
            font-size: 22px;
        }
        .examelite-header .navbar-collapse {
            order: 5;
        }
        .header-search-overlay { padding: 12px 10px 16px; }
        .header-site-search-form { height: 50px; padding: 0 10px; }
        .header-site-search-input { font-size: 14px; padding: 0 8px; }
        .header-site-search-results { max-height: 55vh; }
        .header-search-result { grid-template-columns: 34px minmax(0, 1fr); }
        .header-search-result-type { display: none; }
    }
    @media (max-width: 575.98px) {
        .examelite-brand-mark {
            flex-basis: 36px;
            font-size: 19px;
            height: 36px;
            width: 36px;
        }
        .examelite-brand-text span {
            font-size: 1.05rem;
            max-width: 112px;
        }
    }
    @php
        $normalizeSecondaryMenuColor = static function ($value): ?string {
            $color = strtolower(trim((string) $value));

            return preg_match('/^#[0-9a-f]{6}$/', $color) ? $color : null;
        };
        $secondaryMenuBackgroundColor = $normalizeSecondaryMenuColor($configuration->secondary_menu_bg_color)
            ?: $normalizeSecondaryMenuColor($configuration->theme_heading_color)
            ?: '#101828';
        $secondaryMenuTopLineColor = $normalizeSecondaryMenuColor($configuration->secondary_menu_top_border_color);
        $secondaryMenuBottomLineColor = $normalizeSecondaryMenuColor($configuration->secondary_menu_bottom_border_color);
        $secondaryMenuTopBorder = $secondaryMenuTopLineColor && $secondaryMenuTopLineColor !== $secondaryMenuBackgroundColor
            ? "2px solid {$secondaryMenuTopLineColor}" : '0';
        $secondaryMenuBottomBorder = $secondaryMenuBottomLineColor && $secondaryMenuBottomLineColor !== $secondaryMenuBackgroundColor
            ? "2px solid {$secondaryMenuBottomLineColor}" : '0';
        $secondaryMenuHoverColor = $normalizeSecondaryMenuColor($configuration->secondary_menu_hover_color)
            ?: $normalizeSecondaryMenuColor($configuration->theme_secondary_color)
            ?: '#f59e0b';
        $secondaryMenuHoverChannels = array_map(static function ($offset) use ($secondaryMenuHoverColor) {
            $channel = hexdec(substr($secondaryMenuHoverColor, $offset, 2)) / 255;
            return $channel <= 0.04045 ? $channel / 12.92 : pow(($channel + 0.055) / 1.055, 2.4);
        }, [1, 3, 5]);
        $secondaryMenuHoverLuminance = 0.2126 * $secondaryMenuHoverChannels[0]
            + 0.7152 * $secondaryMenuHoverChannels[1] + 0.0722 * $secondaryMenuHoverChannels[2];
        $secondaryMenuHoverText = $secondaryMenuHoverLuminance > 0.179 ? '#000000' : '#ffffff';
    @endphp
    .examelite-secondary-nav {
        background: {{ $secondaryMenuBackgroundColor }};
        border-top: {{ $secondaryMenuTopBorder }};
        border-bottom: {{ $secondaryMenuBottomBorder }};
        position: relative;
        z-index: 1020;
    }
    .examelite-secondary-inner {
        align-items: stretch;
        display: flex;
        gap: 8px;
        justify-content: flex-start;
        margin: 0 auto;
        max-width: 1440px;
        overflow-x: auto;
        padding: 0 24px;
        scrollbar-width: none;
    }
    .examelite-secondary-inner::-webkit-scrollbar { display: none; }
    .examelite-secondary-link {
        align-items: center;
        border: 1px solid transparent;
        border-radius: 0;
        color: {{ $configuration->secondary_menu_text_color ?: 'var(--theme-card-bg, #ffffff)' }};
        display: inline-flex;
        flex: 0 0 auto;
        font-size: 14px;
        font-weight: 700;
        gap: 6px;
        line-height: 1.2;
        padding: 15px 14px;
        text-decoration: none;
        transition: background-color .2s ease, border-color .2s ease, color .2s ease, transform .2s ease;
        white-space: nowrap;
    }
    .examelite-secondary-link:hover,
    .examelite-secondary-link:focus,
    .examelite-secondary-link:active,
    .examelite-secondary-link.is-button {
        background: {{ $secondaryMenuHoverColor }};
        border-color: {{ $secondaryMenuHoverColor }};
        color: {{ $secondaryMenuHoverText }} !important;
        -webkit-text-fill-color: {{ $secondaryMenuHoverText }} !important;
        transform: none;
        box-shadow: none;
    }
    .examelite-secondary-link:hover span,
    .examelite-secondary-link:focus span,
    .examelite-secondary-link:active span,
    .examelite-secondary-link.is-button span,
    .examelite-secondary-link:hover i,
    .examelite-secondary-link:focus i,
    .examelite-secondary-link:active i,
    .examelite-secondary-link.is-button i {
        color: inherit !important;
        -webkit-text-fill-color: inherit !important;
    }
    @media (max-width: 991.98px) {
        .examelite-secondary-inner { gap: 6px; padding: 0 16px; scroll-padding-inline: 16px; scroll-snap-type: x proximity; }
        .examelite-secondary-link { font-size: 13px; min-height: 58px; max-width: 82vw; overflow: hidden; padding: 17px 13px; scroll-snap-align: start; text-overflow: ellipsis; }
        .examelite-secondary-link span { overflow: hidden; text-overflow: ellipsis; }
    }
</style>

{{-- Cart Offcanvas --}}
<div class="offcanvas offcanvas-end" data-bs-scroll="true" tabindex="-1" id="offcanvasCart" aria-labelledby="offcanvasCartLabel">
    <div class="offcanvas-header border-bottom">
        <h5 class="offcanvas-title fw-semibold" id="offcanvasCartLabel">{{ __('website.cart_my_cart') }}</h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>

    <div class="offcanvas-body d-flex flex-column">
        <div class="flex-grow-1">
            <h5 class="d-flex justify-content-between align-items-center mb-4">
                <span>{{ __('website.cart_your_cart') }}</span>
                <span class="badge rounded-pill header-cart-badge fs-6" id="cartItemCount">0</span>
            </h5>
            <ul class="list-group mb-4" id="cartItemsContainer">
                <div class="text-center text-muted py-5"><p>{{ __('website.cart_empty') }}</p></div>
            </ul>
        </div>

        <div class="mt-auto">
            <ul class="list-group mb-4">
                <li class="list-group-item d-flex justify-content-between align-items-center py-3 border-top fw-bold fs-6" id="cartTotalRow">
                    <span>{{ __('website.cart_total') }}</span>
                    <strong>{{ $configuration->currency ?? '$' }}0.00</strong>
                </li>
            </ul>
            <div class="d-grid" id="cart-footer"></div>
        </div>
    </div>
</div>

<nav class="navbar navbar-expand-lg navbar-landing examelite-header {{ $secondaryNavigation->isNotEmpty() ? 'has-secondary-navigation' : '' }}" id="navbar">
    <div class="container-fluid custom-container">
        <a class="navbar-brand" href="/" aria-label="ExamElite Home">
            @if (!empty($configuration->logo))
                <img src="{{ asset('storage/' . $configuration->logo) }}" alt="ExamElite">
            @else
                <span class="examelite-brand-fallback" aria-label="{{ $configuration->name ?? 'ExamElite' }}">
                    <span class="examelite-brand-mark"><i class="ri-graduation-cap-line"></i></span>
                    <span class="examelite-brand-text">
                        <span>{{ $configuration->name ?? 'ExamElite' }}</span>
                        <small>{{ __('ui.brand_tagline') }}</small>
                    </span>
                </span>
            @endif
        </a>

        <button class="header-search-trigger header-search-trigger-mobile d-lg-none" type="button" aria-label="{{ __('ui.open_search') }}" aria-expanded="false" aria-controls="headerSearchOverlay" data-header-search-trigger>
            <i class="ri-search-line" aria-hidden="true"></i><span>{{ __('website.search') }}</span>
        </button>

        @include('partials.language-switcher', ['variant' => 'mobile-icon'])

        <button class="navbar-toggler py-0 fs-20 text-body" type="button" data-bs-toggle="collapse" data-bs-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="{{ __('ui.toggle_navigation') }}">
            <i class="mdi mdi-menu"></i>
        </button>

        <div class="collapse navbar-collapse" id="navbarSupportedContent">
            <ul class="navbar-nav mx-lg-auto mb-2 mb-lg-0" id="navbar-example">
                @if($headerNavigation->isEmpty() || $megaNavigationItem)
                <li class="nav-item dropdown position-static">
                    <a class="nav-link dropdown-toggle" href="#" id="exploreExamsMega" role="button" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false">
                        {{ $megaNavigationItem?->label ?: __('ui.explore_exams') }}
                    </a>
                    <div class="dropdown-menu examelite-mega" aria-labelledby="exploreExamsMega">
                        @if(collect($menuHierarchy ?? [])->isNotEmpty())
                            <div class="examelite-mega-grid">
                                <div class="examelite-mega-groups">
                                    <div class="mega-eyebrow">{{ __('ui.choose_exam_group') }}</div>
                                    <div class="nav nav-pills flex-column" role="tablist">
                                        @foreach($menuHierarchy as $menuIndex => $menuGroup)
                                            <button class="nav-link {{ $menuIndex === 0 ? 'active' : '' }}" data-bs-toggle="pill" data-bs-target="#mega-group-{{ $menuGroup['id'] }}" type="button" role="tab">
                                                {{ $menuGroup['name'] }} <i class="ri-arrow-right-s-line"></i>
                                            </button>
                                        @endforeach
                                    </div>
                                    <a class="mega-view-all" href="{{ route('website.exams.index') }}">{{ __('ui.view_every_group') }} <i class="ri-arrow-right-line"></i></a>
                                </div>
                                <div class="tab-content examelite-mega-content">
                                    @foreach($menuHierarchy as $menuIndex => $menuGroup)
                                        <div class="tab-pane fade {{ $menuIndex === 0 ? 'show active' : '' }}" id="mega-group-{{ $menuGroup['id'] }}" role="tabpanel">
                                            <div class="mega-panel-head">
                                                <div><span class="mega-eyebrow">{{ $menuGroup['name'] }}</span></div>
                                                <a href="{{ route('website.exams.index', ['group' => $menuGroup['slug']]) }}">{{ __('ui.view_group') }}</a>
                                            </div>
                                            <div class="mega-panel-grid">
                                                <div class="mega-category-grid">
                                                    @forelse($menuGroup['categories'] as $menuCategory)
                                                        <div class="mega-category-card">
                                                            <a class="mega-category-title" href="{{ url('/exam-groups/'.$menuGroup['slug'].'/'.$menuCategory['slug']) }}">
                                                                <span class="mega-category-icon"><i class="ri-book-open-line"></i></span>
                                                                <span>{{ $menuCategory['title'] }}</span>
                                                            </a>
                                                            @if($menuCategory['description'])<p>{{ $menuCategory['description'] }}</p>@endif
                                                            @if(!empty($menuCategory['children']))
                                                                <div class="mega-subcategories" data-subcategory-ui>
                                                                    @foreach($menuCategory['children'] as $menuChild)
                                                                        <a href="{{ url('/exam-groups/'.$menuGroup['slug'].'/'.$menuCategory['slug'].'/'.$menuChild['slug']) }}">{{ $menuChild['title'] }}</a>
                                                                    @endforeach
                                                                </div>
                                                            @endif
                                                        </div>
                                                    @empty
                                                        <a class="mega-empty" href="{{ route('website.exams.index', ['group' => $menuGroup['slug']]) }}">{{ __('ui.browse_all_group_exams', ['group' => $menuGroup['name']]) }}</a>
                                                    @endforelse
                                                </div>
                                                <aside class="mega-featured-packages">
                                                    <div class="mega-eyebrow">{{ __('ui.featured_packages') }}</div>
                                                    @forelse($menuGroup['packages'] as $menuPackage)
                                                        <a class="examelite-package-link" href="{{ route('courses.detail', $menuPackage['slug']) }}">
                                                            <span><strong>{{ $menuPackage['name'] }}</strong><small>{{ $menuPackage['category'] ?: $menuGroup['name'] }}</small></span>
                                                            <em>{{ __('ui.exams_count', ['count' => $menuPackage['exams_count']]) }}</em>
                                                        </a>
                                                    @empty
                                                        <p class="mega-muted">{{ __('ui.packages_automatic') }}</p>
                                                    @endforelse
                                                    <a class="mega-view-all" href="{{ route('courses.index', ['group' => $menuGroup['id']]) }}">{{ __('ui.all_packages') }} <i class="ri-arrow-right-line"></i></a>
                                                </aside>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @else
                            <div class="mega-fallback">
                                <a href="{{ route('website.exams.index') }}">{{ __('ui.browse_all_exams') }}</a>
                                <a href="{{ route('courses.index') }}">{{ __('ui.browse_packages') }}</a>
                            </div>
                        @endif
                        <div class="mega-footer-links">
                            <a href="{{ url('/exam-groups/all') }}"><i class="ri-layout-grid-line"></i> {{ __('website.course_categories') }}</a>
                            <a href="{{ route('courses.index') }}"><i class="ri-stack-line"></i> {{ __('ui.packages') }}</a>
                            <a href="{{ route('website.exams.index') }}"><i class="ri-file-list-3-line"></i> {{ __('ui.all_exams') }}</a>
                        </div>
                    </div>
                </li>

                @endif

                @if($headerNavigation->isNotEmpty())
                    @foreach($headerNavigation->where('link_type','!=','mega_exams') as $navItem)
                        @if($navItem->children->isNotEmpty())
                            <li class="nav-item dropdown {{ !$navItem->desktop_visible ? 'd-lg-none' : '' }} {{ !$navItem->mobile_visible ? 'd-none d-lg-block' : '' }}">
                                <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">{{ $navItem->label }}</a>
                                <div class="dropdown-menu">@foreach($navItem->children as $child)<a class="dropdown-item" href="{{ $child->resolvedUrl() }}" target="{{ $child->target }}" @if($child->link_type === 'quick_quiz' && !auth('student')->check()) data-quick-quiz-open data-qq-source="header_dropdown" @endif>@if($child->icon)<i class="{{ $child->icon }} me-2"></i>@endif{{ $child->label }}</a>@endforeach</div>
                            </li>
                        @else
                            <li class="nav-item {{ !$navItem->desktop_visible ? 'd-lg-none' : '' }} {{ !$navItem->mobile_visible ? 'd-none d-lg-block' : '' }}"><a class="nav-link {{ $navItem->style === 'button' ? 'header-nav-button' : '' }}" href="{{ $navItem->resolvedUrl() }}" target="{{ $navItem->target }}" @if($navItem->link_type === 'quick_quiz' && !auth('student')->check()) data-quick-quiz-open data-qq-source="header_navigation" @endif>@if($navItem->icon)<i class="{{ $navItem->icon }} me-1"></i>@endif{{ $navItem->label }}</a></li>
                        @endif
                    @endforeach
                @else
                    <li class="nav-item"><a class="nav-link" href="{{ route('courses.index') }}">{{ __('ui.explore_packages') }}</a></li>
                    <li class="nav-item"><a class="nav-link" href="{{ route('website.exams.index') }}">{{ __('ui.all_exams') }}</a></li>
                    <li class="nav-item"><a class="nav-link" href="{{ auth('student')->check() ? route('student.quick-quizzes') : route('home', ['open_quick_quiz' => 1]) }}" @if(!auth('student')->check()) data-quick-quiz-open data-qq-source="default_header" @endif>{{ __('ui.quizzes') }}</a></li>
                    @if(collect($navbarPages ?? [])->isNotEmpty())
                        <li class="nav-item dropdown"><a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">{{ __('website.more') }}</a><div class="dropdown-menu dropdown-menu-end">@foreach(collect($navbarPages)->take(10) as $navbarPage) @php $navbarLabel=$websiteNavbarText($navbarPage->title)?:$websiteNavbarText($navbarPage->short_title); $navbarSlug=\Illuminate\Support\Str::slug($websiteNavbarText($navbarPage->short_title)); @endphp @if($navbarLabel&&$navbarSlug)<a class="dropdown-item" href="{{ route('page.show',['slug'=>$navbarSlug]) }}">{{ $navbarLabel }}</a>@endif @endforeach</div></li>
                    @endif
                @endif
            </ul>


            <button class="header-search-trigger header-search-trigger-desktop d-none d-lg-inline-flex" type="button" aria-label="{{ __('ui.open_search') }}" aria-expanded="false" aria-controls="headerSearchOverlay" data-header-search-trigger>
                <i class="ri-search-line" aria-hidden="true"></i><span>{{ __('website.search') }}</span>
            </button>

            <div class="header-actions ms-lg-auto">
                <div class="nav-item align-self-center position-relative d-none" id="headerCartControl">
                    <a href="#" class="position-relative d-flex align-items-center header-cart-link" data-bs-toggle="offcanvas" data-bs-target="#offcanvasCart" aria-controls="offcanvasCart">
                        <i class="mdi mdi-cart fs-2"></i>
                        <span id="cartNavCount" class="position-absolute top-0 start-100 translate-middle badge rounded-circle header-cart-badge">0</span>
                    </a>
                </div>

                <div class="d-flex align-items-center gap-2">
                    @if (Auth::guard('student')->check())
                        <a href="/student/dashboard" class="btn btn-sm header-login rounded-pill px-3 custom-btn">
                            <i class="ri-dashboard-line align-bottom me-1"></i> {{ __('messages.sidebar_dashboard') }}
                        </a>
                    @else
                        <a href="/student/signin" class="btn btn-sm header-login rounded-pill px-3 custom-btn">
                            <i class="ri-user-line"></i> {{ __('ui.login_register') }}
                        </a>
                    @endif
                    @include('partials.language-switcher', ['variant' => 'desktop'])
                </div>
            </div>

        </div>
    </div>
    <div class="header-search-overlay" id="headerSearchOverlay" data-header-search-overlay aria-hidden="true">
        <div class="header-search-overlay-inner">
            <form class="header-site-search-form" action="{{ route('courses.index') }}" method="GET" role="search" data-search-form>
                <i class="ri-search-line" aria-hidden="true"></i>
                <input class="header-site-search-input" name="search" type="search"
                    placeholder="{{ __('ui.search_placeholder') }}" autocomplete="off"
                    aria-label="{{ __('ui.search_placeholder') }}"
                    aria-autocomplete="list" aria-controls="headerSiteSearchResults" aria-expanded="false" data-search-input>
                <span class="spinner-border spinner-border-sm header-site-search-spinner" aria-hidden="true"></span>
                <button class="header-search-close" type="button" aria-label="{{ __('ui.close_search') }}" data-header-search-close><i class="ri-close-line" aria-hidden="true"></i></button>
            </form>
            <div class="header-site-search-results" id="headerSiteSearchResults" role="listbox" data-search-results></div>
        </div>
    </div>
</nav>
@if($secondaryNavigation->isNotEmpty())
<nav class="examelite-secondary-nav" aria-label="Featured exam navigation">
    <div class="examelite-secondary-inner">
        @foreach($secondaryNavigation as $navItem)
            <a class="examelite-secondary-link {{ $navItem->style === 'button' ? 'is-button' : '' }} {{ !$navItem->mobile_visible ? 'd-none d-lg-inline-flex' : '' }} {{ !$navItem->desktop_visible ? 'd-lg-none' : '' }}" href="{{ $navItem->resolvedUrl() }}" target="{{ $navItem->target }}" @if($navItem->link_type === 'quick_quiz') data-quick-quiz-open data-qq-source="secondary_navigation" @endif>
                @if($navItem->icon)<i class="{{ $navItem->icon }}"></i>@endif
                <span>{{ $navItem->label }}</span>
            </a>
        @endforeach
    </div>
</nav>
@endif

<script>
document.addEventListener('DOMContentLoaded', function () {
    const endpoint = @json(route('website.header.search'));
    const searchOverlay = document.querySelector('[data-header-search-overlay]');
    const searchTriggers = Array.from(document.querySelectorAll('[data-header-search-trigger]'));
    const searchForm = searchOverlay?.querySelector('[data-search-form]');
    const searchInput = searchOverlay?.querySelector('[data-search-input]');
    const searchResults = searchOverlay?.querySelector('[data-search-results]');
    const searchClose = searchOverlay?.querySelector('[data-header-search-close]');
    let searchDebounceTimer = null;
    let searchRequestController = null;
    let headerSearchResults = [];
    let headerSearchActiveIndex = -1;
    let headerSearchExamLimit = 30;

    const setSearchOpen = function (open) {
        if (!searchOverlay) return;
        searchOverlay.classList.toggle('is-open', open);
        searchOverlay.setAttribute('aria-hidden', open ? 'false' : 'true');
        searchTriggers.forEach(trigger => trigger.setAttribute('aria-expanded', open ? 'true' : 'false'));
        if (open) {
            window.setTimeout(() => searchInput?.focus(), 60);
        } else {
            searchResults?.classList.remove('is-open');
            searchInput?.setAttribute('aria-expanded', 'false');
            headerSearchActiveIndex = -1;
        }
    };

    const setActiveSearchResult = function (index) {
        const links = Array.from(searchResults.querySelectorAll('.header-search-result'));
        links.forEach(link => link.classList.remove('is-active'));
        if (!links.length) return;
        headerSearchActiveIndex = (index + links.length) % links.length;
        links[headerSearchActiveIndex].classList.add('is-active');
        links[headerSearchActiveIndex].scrollIntoView({ block: 'nearest' });
    };

    const renderSearchMessage = function (message) {
        searchResults.replaceChildren();
        const node = document.createElement('div');
        node.className = 'header-search-message';
        node.textContent = message;
        searchResults.appendChild(node);
        searchResults.classList.add('is-open');
        searchInput.setAttribute('aria-expanded', 'true');
    };

    const renderHeaderSearchResults = function (payload, preserveScroll) {
        const previousScrollTop = preserveScroll ? searchResults.scrollTop : 0;
        const items = payload.results || [];
        const meta = payload.meta || {};

        searchResults.replaceChildren();
        headerSearchResults = items;
        headerSearchActiveIndex = -1;

        if (!items.length) {
            renderSearchMessage('No matching public exams or courses found.');
            return;
        }

        items.forEach(function (item) {
            const link = document.createElement('a');
            const iconWrap = document.createElement('span');
            const icon = document.createElement('i');
            const label = document.createElement('span');
            const type = document.createElement('span');

            link.className = 'header-search-result';
            link.href = item.url;
            link.setAttribute('role', 'option');
            iconWrap.className = 'header-search-result-icon';
            icon.className = item.icon || 'ri-search-line';
            label.className = 'header-search-result-label';
            label.textContent = item.label;
            type.className = 'header-search-result-type';
            type.textContent = item.type;
            iconWrap.appendChild(icon);
            link.append(iconWrap, label, type);
            searchResults.appendChild(link);
        });

        if (meta.has_more_exams) {
            const remaining = Math.max((meta.exam_total || 0) - (meta.exam_limit || 0), 0);
            const moreButton = document.createElement('button');
            moreButton.className = 'header-search-more';
            moreButton.type = 'button';
            moreButton.textContent = 'Show all matching exams' + (remaining ? ' (' + remaining + ' more)' : '');
            moreButton.addEventListener('click', function () {
                headerSearchExamLimit = Math.min(meta.exam_total || (headerSearchExamLimit + 30), 5000);
                fetchHeaderSuggestions(true);
            });
            searchResults.appendChild(moreButton);
        }

        searchResults.classList.add('is-open');
        searchInput.setAttribute('aria-expanded', 'true');
        if (preserveScroll) searchResults.scrollTop = previousScrollTop;
    };

    const fetchHeaderSuggestions = function (preserveScroll) {
        const term = searchInput.value.trim();
        if (term.length < 2) {
            headerSearchResults = [];
            searchResults.classList.remove('is-open');
            searchInput.setAttribute('aria-expanded', 'false');
            return;
        }

        if (searchRequestController) searchRequestController.abort();
        searchRequestController = new AbortController();
        searchOverlay.classList.add('is-loading');

        fetch(endpoint + '?q=' + encodeURIComponent(term) + '&exam_limit=' + headerSearchExamLimit, {
            headers: { 'Accept': 'application/json' },
            signal: searchRequestController.signal
        })
            .then(response => {
                if (!response.ok) throw new Error('Search request failed');
                return response.json();
            })
            .then(payload => renderHeaderSearchResults(payload, Boolean(preserveScroll)))
            .catch(error => {
                if (error.name !== 'AbortError') renderSearchMessage('Search is temporarily unavailable.');
            })
            .finally(() => searchOverlay.classList.remove('is-loading'));
    };
    searchTriggers.forEach(function (trigger) {
        trigger.addEventListener('click', function (event) {
            event.stopPropagation();
            setSearchOpen(!searchOverlay.classList.contains('is-open'));
        });
    });

    searchClose?.addEventListener('click', function () { setSearchOpen(false); });
    searchInput?.addEventListener('input', function () {
        headerSearchExamLimit = 30;
        window.clearTimeout(searchDebounceTimer);
        searchDebounceTimer = window.setTimeout(fetchHeaderSuggestions, 220);
    });
    searchInput?.addEventListener('keydown', function (event) {
        if (event.key === 'ArrowDown') {
            event.preventDefault();
            setActiveSearchResult(headerSearchActiveIndex + 1);
        } else if (event.key === 'ArrowUp') {
            event.preventDefault();
            setActiveSearchResult(headerSearchActiveIndex - 1);
        } else if (event.key === 'Escape') {
            setSearchOpen(false);
        } else if (event.key === 'Enter' && headerSearchActiveIndex >= 0) {
            event.preventDefault();
            const activeLink = searchResults.querySelectorAll('.header-search-result')[headerSearchActiveIndex];
            if (activeLink) window.location.assign(activeLink.href);
        }
    });
    searchForm?.addEventListener('submit', function (event) {
        if (headerSearchResults.length) {
            event.preventDefault();
            window.location.assign(headerSearchResults[Math.max(headerSearchActiveIndex, 0)].url);
        }
    });
    document.addEventListener('click', function (event) {
        if (searchOverlay?.classList.contains('is-open') && !searchOverlay.contains(event.target)) {
            setSearchOpen(false);
        }
    });
    if (window.matchMedia('(min-width: 992px)').matches && window.bootstrap?.Tab) {
        document.querySelectorAll('.examelite-mega-groups [data-bs-toggle="pill"]').forEach(function (button) {
            button.addEventListener('mouseenter', function () {
                bootstrap.Tab.getOrCreateInstance(button).show();
            });
        });
    }
});
</script>

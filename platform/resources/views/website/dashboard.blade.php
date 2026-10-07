@extends('website.layouts.app')

@section('title', 'Examelite - Smart Mock Tests for School & College Students')

@section('content')

<style>
    .home-shell {
        background: color-mix(in srgb, var(--theme-primary, #0f766e) 4%, var(--theme-body-bg, #ffffff));
    }

    .home-hero {
        padding: 28px 0 30px;
        position: relative;
        overflow: visible;
        z-index: 2;
    }


    .home-hero.has-background { background-image:var(--hero-desktop-image); background-position:center; background-repeat:no-repeat; background-size:cover; }
    .home-hero.has-background::before { background:rgba(2,6,23,var(--hero-overlay,.35)); content:""; inset:0; position:absolute; z-index:0; }
    .home-hero.has-background > .container { position:relative; z-index:1; }
    .home-hero.has-background .home-title,.home-hero.has-background .home-copy { color:#fff; text-shadow:0 2px 12px rgba(0,0,0,.35); }
    .home-hero.has-background .home-title span { color:#fff; }
    .home-hero.is-left .home-copy { margin-left:0; margin-right:0; }
    .home-hero.is-left .home-search-card { margin-left:0; }
    .home-hero.is-left .home-quick-links { justify-content:flex-start; }    .home-kicker {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 7px 12px;
        border-radius: 999px;
        background: color-mix(in srgb, var(--theme-primary, #0f766e) 10%, #ffffff);
        color: var(--theme-primary, #0f766e);
        font-size: 12px;
        font-weight: 800;
        letter-spacing: .02em;
        text-transform: uppercase;
    }

    .home-title {
        color: var(--theme-heading, #0f172a);
        font-size: clamp(2rem, 9vw, 4.4rem);
        font-weight: 900;
        line-height: .98;
        letter-spacing: 0;
        margin: 14px 0 14px;
    }

    .home-title span {
        color: var(--theme-primary, #0f766e);
    }

    .home-typewriter {
        display: inline-flex;
        align-items: center;
        min-height: 1.05em;
    }

    .home-typewriter::after {
        content: "";
        display: inline-block;
        width: 3px;
        height: .82em;
        margin-left: 8px;
        border-radius: 999px;
        background: var(--theme-secondary, #f59e0b);
        animation: homeCursorBlink .9s steps(1) infinite;
    }

    @keyframes homeCursorBlink {
        50% { opacity: 0; }
    }

    .home-copy {
        color: var(--theme-text, #64748b);
        font-size: clamp(.98rem, 3.8vw, 1.18rem);
        line-height: 1.65;
        max-width: 720px;
        margin: 0 auto 18px;
    }

    .home-search-card {
        background: var(--theme-card-bg, #ffffff);
        border: 1px solid color-mix(in srgb, var(--theme-primary, #0f766e) 14%, #ffffff);
        border-radius: 14px;
        box-shadow: 0 18px 50px rgba(15, 23, 42, .1);
        padding: 10px;
        max-width: 900px;
        margin: 0 auto;
    }

    .home-search-form {
        display: grid;
        grid-template-columns: 1fr auto;
        gap: 10px;
        align-items: center;
    }

    .home-search-form.has-quiz-action {
        grid-template-columns: minmax(0, 1fr) auto auto;
    }

    .home-search-box {
        min-height: 54px;
        border: 0;
        background: color-mix(in srgb, var(--theme-primary, #0f766e) 4%, #ffffff);
        border-radius: 10px;
        padding: 0 16px 0 48px;
        color: var(--theme-heading, #0f172a);
        font-weight: 600;
        width: 100%;
    }

    .home-search-icon {
        position: absolute;
        left: 18px;
        top: 50%;
        transform: translateY(-50%);
        color: var(--theme-primary, #0f766e);
        font-size: 20px;
    }

    .home-smart-search {
        position: relative;
    }

    .home-suggestion-panel {
        background: var(--theme-card-bg, #ffffff);
        border: 1px solid color-mix(in srgb, var(--theme-primary, #0f766e) 16%, #e2e8f0);
        border-radius: 12px;
        box-shadow: 0 18px 38px rgba(15, 23, 42, .12);
        display: none;
        left: 0;
        margin-top: 8px;
        max-height: 270px;
        overflow: auto;
        padding: 8px;
        position: absolute;
        right: 0;
        text-align: left;
        top: 100%;
        z-index: 20;
    }

    .home-suggestion-panel.is-open {
        display: block;
    }

    .home-suggestion-item {
        align-items: center;
        background: transparent;
        border: 0;
        border-radius: 10px;
        color: var(--theme-heading, #0f172a);
        display: flex;
        font-weight: 750;
        gap: 10px;
        padding: 10px 12px;
        text-align: left;
        width: 100%;
    }

    .home-suggestion-item i {
        color: var(--theme-primary, #0f766e);
    }

    .home-suggestion-item:hover,
    .home-suggestion-item:focus {
        background: color-mix(in srgb, var(--theme-primary, #0f766e) 8%, #ffffff);
        color: var(--theme-primary, #0f766e);
        outline: none;
    }

    .home-suggestion-empty {
        color: var(--theme-text, #64748b);
        font-weight: 700;
        padding: 10px 12px;
    }

    .home-primary-btn,
    .home-secondary-btn {
        min-height: 54px;
        border-radius: 10px;
        border: 0;
        padding: 0 20px;
        font-weight: 800;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        text-decoration: none;
        white-space: nowrap;
    }

    .home-primary-btn {
        background: var(--theme-primary, #0f766e);
        color: var(--theme-button-text, #ffffff);
    }

    .home-secondary-btn {
        background: var(--theme-secondary, #f59e0b);
        color: var(--theme-button-text, #ffffff);
    }

    .home-quick-links {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        flex-wrap: wrap;
        margin-top: 14px;
    }

    .home-chip {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        border-radius: 999px;
        padding: 8px 12px;
        background: var(--theme-card-bg, #ffffff);
        border: 1px solid color-mix(in srgb, var(--theme-primary, #0f766e) 14%, #e2e8f0);
        color: var(--theme-heading, #0f172a);
        font-size: 13px;
        font-weight: 750;
        text-decoration: none;
    }

    .home-chip:hover {
        color: var(--theme-primary, #0f766e);
        border-color: color-mix(in srgb, var(--theme-primary, #0f766e) 35%, #ffffff);
        background: color-mix(in srgb, var(--theme-primary, #0f766e) 6%, #ffffff);
    }

    .home-step-card {
        background: var(--theme-card-bg, #ffffff);
        border: 1px solid color-mix(in srgb, var(--theme-primary, #0f766e) 13%, #e2e8f0);
        border-radius: 14px;
        box-shadow: 0 10px 28px rgba(15, 23, 42, .045);
        height: 100%;
        padding: 18px;
        transition: border-color .18s ease, transform .18s ease;
    }

    .home-step-card:hover {
        border-color: color-mix(in srgb, var(--theme-primary, #0f766e) 28%, #e2e8f0);
        transform: translateY(-2px);
    }


    .home-discovery-card { align-items:center; background:var(--theme-card-bg,#fff); border:1px solid color-mix(in srgb,var(--theme-primary,#0f766e) 18%,#dbe5e3); border-radius:16px; box-shadow:0 8px 24px rgba(15,23,42,.05); color:var(--theme-heading,#0f172a); display:flex; gap:16px; height:100%; min-height:130px; padding:24px; text-decoration:none; transition:transform .2s ease,box-shadow .2s ease,border-color .2s ease; }
    .home-discovery-card.is-category { background:linear-gradient(145deg,#fff,color-mix(in srgb,var(--theme-primary,#0f766e) 4%,#fff)); border-left:4px solid color-mix(in srgb,var(--theme-primary,#0f766e) 55%,#fff); min-height:118px; position:relative; overflow:hidden; }
    .home-discovery-card.is-category .home-discovery-icon { background:color-mix(in srgb,var(--theme-primary,#0f766e) 12%,#fff); color:var(--theme-primary,#0f766e); }
    .home-discovery-card:hover { border-color:color-mix(in srgb,var(--theme-primary,#0f766e) 38%,#fff); box-shadow:0 16px 34px rgba(15,23,42,.1); color:var(--theme-heading,#0f172a); transform:translateY(-3px); }
    .home-discovery-icon { align-items:center; background:var(--theme-primary,#0f766e); border-radius:14px; color:var(--theme-button-text,#fff); display:inline-flex; flex:0 0 48px; font-size:23px; height:48px; justify-content:center; width:48px; }
    .home-discovery-content { flex:1; min-width:0; }
    .home-discovery-title { color:var(--theme-heading,#0f172a); font-size:1.05rem; font-weight:900; line-height:1.3; margin:0 0 6px; overflow-wrap:anywhere; }
    .home-discovery-title.is-long { font-size:.96rem; }
    .home-discovery-title.is-very-long { font-size:.86rem; }
    .home-discovery-meta { color:var(--theme-text,#64748b); font-size:13px; line-height:1.5; }
    .home-discovery-meta strong { color:var(--theme-primary,#0f766e); }
    .home-discovery-arrow { color:var(--theme-primary,#0f766e); flex:0 0 auto; font-size:1.25rem; }
    .home-packages-section { margin:72px 0 0; padding:18px 0 72px; }
    .home-leaderboard-section { margin:72px 0; padding:8px 0 72px; }
    .performer-card .student-initial.gold { background:#f59e0b !important; box-shadow:0 0 0 6px rgba(245,158,11,.14); }
    .performer-card .student-initial.silver { background:#94a3b8 !important; box-shadow:0 0 0 6px rgba(148,163,184,.16); }
    .performer-card .student-initial.bronze { background:#b87333 !important; box-shadow:0 0 0 6px rgba(184,115,51,.14); }
    .performer-card .student-avatar.gold { border:5px solid #f59e0b; background:#f59e0b; padding:2px; }
    .performer-card .student-avatar.silver { border:5px solid #94a3b8; background:#94a3b8; padding:2px; }
    .performer-card .student-avatar.bronze { border:5px solid #b87333; background:#b87333; padding:2px; }
    .home-category-card {
        background: linear-gradient(145deg, var(--ew-surface, #fff), color-mix(in srgb, var(--ew-primary, #08786c) 5%, #fff));
        border: 1px solid var(--ew-border, #d8e5e3);
        border-radius: 18px;
        box-shadow: var(--ew-shadow, 0 16px 40px rgba(16, 40, 38, .09));
        color: var(--ew-ink, #101828);
        display: flex;
        flex-direction: column;
        min-height: 210px;
        overflow: hidden;
        padding: 22px;
        position: relative;
        text-decoration: none;
        transition: transform .2s ease, border-color .2s ease, box-shadow .2s ease;
    }
    .home-category-card::before { background: var(--ew-accent, #f47a10); content: ""; height: 5px; left: 0; position: absolute; right: 0; top: 0; }
    .home-category-card:hover { border-color: color-mix(in srgb, var(--ew-primary, #08786c) 38%, #fff); box-shadow: 0 22px 48px rgba(16, 40, 38, .14); color: var(--ew-ink, #101828); transform: translateY(-4px); }
    .home-category-top { align-items: flex-start; display: flex; justify-content: space-between; }
    .home-category-icon { align-items: center; background: color-mix(in srgb, var(--ew-primary, #08786c) 12%, #fff); border-radius: 14px; color: var(--ew-primary, #08786c); display: inline-flex; font-size: 24px; height: 48px; justify-content: center; width: 48px; }
    .home-category-number { color: color-mix(in srgb, var(--ew-primary, #08786c) 25%, #fff); font-size: 2rem; font-weight: 950; }
    .home-category-card h3 { color: var(--ew-ink, #101828); font-size: 1.12rem; font-weight: 900; line-height: 1.3; margin: 26px 0 8px; overflow-wrap: anywhere; white-space: normal; }
    .home-category-card h3.is-long { font-size: 1rem; }
    .home-category-card h3.is-very-long { font-size: .9rem; line-height: 1.35; }
    .home-category-card p { color: var(--ew-text, #475467); font-size: 13px; line-height: 1.5; margin: 0; }
    .home-category-meta { align-items: center; color: var(--ew-primary, #08786c); display: flex; font-size: 12px; font-weight: 850; justify-content: space-between; margin-top: auto; padding-top: 18px; }

    .home-subcategory-grid { display: grid; gap: 12px; grid-template-columns: repeat(3, minmax(0, 1fr)); }
    .home-subcategory-card { align-items:center; background:linear-gradient(135deg,#fff,color-mix(in srgb,var(--ew-primary,#08786c) 3%,#fff)); border:1px solid var(--ew-border,#d8e5e3); border-left:4px solid color-mix(in srgb,var(--ew-primary,#08786c) 55%,#fff); border-radius:14px; box-shadow:0 7px 20px rgba(15,23,42,.04); color:var(--ew-ink,#101828); display:grid; gap:14px; grid-template-columns:44px minmax(0,1fr) auto; min-height:76px; padding:14px 16px; text-decoration:none; transition:background .18s ease,border-color .18s ease,transform .18s ease,box-shadow .18s ease; }
    .home-subcategory-card:hover { background:color-mix(in srgb,var(--ew-accent,#f47a10) 5%,#fff); border-color:color-mix(in srgb,var(--ew-accent,#f47a10) 38%,#fff); box-shadow:0 12px 26px rgba(15,23,42,.08); color:var(--ew-ink,#101828); transform:translateY(-2px); }
    .home-subcategory-icon { align-items: center; background: color-mix(in srgb, var(--ew-accent, #f47a10) 12%, #fff); border-radius: 11px; color: var(--ew-accent, #f47a10); display: inline-flex; font-size: 19px; height: 42px; justify-content: center; width: 42px; }
    .home-subcategory-card strong { display: block; font-size: 13px; font-weight: 900; overflow-wrap: anywhere; }
    .home-subcategory-card small { color: var(--ew-text, #475467); display: block; font-size: 11px; margin-top: 3px; }
    .home-subcategory-card > i { color: var(--ew-primary, #08786c); }
    .home-info-grid {
        display: grid;
        gap: 16px;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        margin-top: 24px;
    }

    .home-flow-grid {
        display: grid;
        gap: 18px;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        margin-bottom: 48px;
    }

    .home-card-number {
        color: var(--theme-heading, #0f172a);
        font-size: clamp(1.35rem, 3vw, 1.75rem);
        font-weight: 900;
        line-height: 1;
    }

    .home-card-label,
    .home-card-copy {
        color: var(--theme-text, #64748b);
        font-size: 14px;
        line-height: 1.55;
        margin: 0;
    }

    .home-card-label {
        font-size: 13px;
        font-weight: 750;
        margin-top: 6px;
    }

    .home-card-title {
        color: var(--theme-heading, #0f172a);
        font-size: 1.02rem;
        font-weight: 900;
        margin: 0 0 7px;
    }

    .home-step-icon {
        width: 42px;
        height: 42px;
        border-radius: 14px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        color: var(--theme-button-text, #ffffff);
        background: var(--theme-primary, #0f766e);
        font-size: 22px;
        margin-bottom: 10px;
    }

    .home-section-title {
        color: var(--theme-heading, #0f172a);
        font-size: clamp(1.35rem, 5vw, 2rem);
        font-weight: 900;
        margin-bottom: 8px;
    }

    .home-section-copy {
        color: var(--theme-text, #64748b);
        margin: 0;
    }

    .home-leaderboard-card {
        background: var(--theme-card-bg, #ffffff);
        border: 1px solid color-mix(in srgb, var(--theme-primary, #0f766e) 13%, #e2e8f0);
        border-radius: 12px;
        padding: 16px;
        height: 100%;
        box-shadow: 0 10px 28px rgba(15, 23, 42, .05);
    }

    .home-leader-row {
        display: grid;
        grid-template-columns: 42px 1fr auto;
        align-items: center;
        gap: 12px;
        padding: 10px 0;
        border-top: 1px solid color-mix(in srgb, var(--theme-primary, #0f766e) 10%, #e2e8f0);
    }

    .home-leader-avatar {
        width: 38px;
        height: 38px;
        border-radius: 50%;
        background: color-mix(in srgb, var(--theme-primary, #0f766e) 12%, #ffffff);
        color: var(--theme-primary, #0f766e);
        border: 1px solid color-mix(in srgb, var(--theme-primary, #0f766e) 22%, #ffffff);
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-weight: 900;
    }

        @media (max-width: 767.98px) { .home-hero.has-background { background-image:var(--hero-mobile-image,var(--hero-desktop-image)); } }

@media (max-width: 767.98px) {
        .home-hero {
            padding-top: 18px;
        }

        .home-search-form {
            grid-template-columns: 1fr;
        }

        .home-search-form.has-quiz-action {
            grid-template-columns: 1fr;
        }

        .home-primary-btn,
        .home-secondary-btn {
            width: 100%;
        }

        .home-search-box {
            min-height: 52px;
            font-size: 14px;
        }

        .home-info-grid,
        .home-flow-grid {
            grid-template-columns: 1fr;
            gap: 12px;
        }

        .home-discovery-card.is-category { min-height:96px; padding:15px 16px; }
        .home-discovery-card.is-category .home-discovery-icon { flex-basis:44px; height:44px; width:44px; }
        .home-subcategory-grid { grid-template-columns: 1fr; }
        .home-category-card { min-height: 180px; padding: 18px; }
        .home-step-card {
            align-items: center;
            display: grid;
            grid-template-columns: 50px minmax(0, 1fr);
            min-height: 0;
            padding: 14px;
            text-align: left;
        }

        .home-step-icon {
            grid-row: span 2;
            margin: 0;
        }

        .home-card-title,
        .home-card-number {
            align-self: end;
        }

        .home-card-copy,
        .home-card-label {
            align-self: start;
            margin-top: 2px;
        }
    }

    @keyframes float {
        0%, 100% { transform: translateY(0px); }
        50% { transform: translateY(-20px); }
    }
    .stat-number {
        font-size: 2.5rem;
        font-weight: 800;
        background: var(--theme-primary, #0f766e);
        -webkit-background-clip: text;
        background-clip: text;
        color: transparent;
    }
    
    /* Homepage leaderboard */
    .performer-card {
        background: var(--theme-card-bg, #ffffff);
        border: 1px solid color-mix(in srgb, var(--theme-primary, #0f766e) 13%, #e2e8f0);
        border-radius: 12px;
        padding: 18px 14px;
        text-align: center;
        box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        transition: transform .2s ease, box-shadow .2s ease, border-color .2s ease;
        height: 100%;
        position: relative;
    }
    .performer-card:hover {
        transform: translateY(-5px);
        border-color: color-mix(in srgb, var(--theme-primary, #0f766e) 32%, #e2e8f0);
        box-shadow: 0 15px 30px color-mix(in srgb, var(--theme-primary, #0f766e) 16%, transparent);
    }
    .rank-badge {
        position: absolute;
        top: -10px;
        left: 15px;
        width: 30px;
        height: 30px;
        background: var(--theme-primary, #0f766e);
        color: var(--theme-button-text, #ffffff);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: bold;
        font-size: 12px;
    }
    .rank-badge.gold { background: var(--theme-primary, #0f766e); }
    .rank-badge.silver { background: var(--theme-secondary, #f59e0b); }
    .rank-badge.bronze { background: var(--theme-tertiary, #38bdf8); }
    
    .student-avatar {
        width: 70px;
        height: 70px;
        border-radius: 50%;
        object-fit: cover;
        margin: 0 auto 12px;
        border: 3px solid var(--theme-primary);
    }
    .student-initial {
        width: 70px;
        height: 70px;
        border-radius: 50%;
        background: var(--theme-primary, #0f766e);
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 12px;
        font-size: 28px;
        font-weight: bold;
        color: var(--theme-button-text, #ffffff);
    }
    .performer-card h5 {
        font-size: 0.95rem;
        font-weight: 700;
        margin-bottom: 5px;
        color: var(--theme-heading, #0f172a);
    }
    .exam-name {
        font-size: 0.7rem;
        color: var(--theme-text, #64748b);
        display: block;
        margin-bottom: 10px;
    }
    .score {
        font-size: 1.3rem;
        font-weight: 800;
        color: var(--theme-primary);
    }
    .score span {
        font-size: 0.8rem;
        font-weight: normal;
    }
    
    /* Tabs Styling */
    .nav-tabs-custom {
        border-bottom: 1px solid color-mix(in srgb, var(--theme-primary, #0f766e) 12%, #e2e8f0);
        margin-bottom: 30px;
        flex-wrap: wrap;
        justify-content: center;
    }
    .nav-tabs-custom .nav-link {
        background: transparent;
        border: 1px solid transparent !important;
        border-bottom-color: transparent !important;
        box-shadow: none !important;
        padding: 10px 24px;
        font-weight: 600;
        color: var(--theme-text, #64748b);
        border-radius: 10px;
        transition: all 0.3s ease;
        margin: 0 4px;
    }
    .nav-tabs-custom .nav-link:hover {
        color: var(--theme-primary, #0f766e);
        background: color-mix(in srgb, var(--theme-primary, #0f766e) 10%, transparent);
        border-color: color-mix(in srgb, var(--theme-primary, #0f766e) 18%, transparent) !important;
    }
    .nav-tabs-custom .nav-link:focus {
        color: var(--theme-primary, #0f766e);
        box-shadow: none !important;
        border-color: var(--theme-primary, #0f766e) !important;
        outline: none !important;
    }
    .nav-tabs-custom .nav-link.active,
    .nav-tabs-custom .nav-link.active:hover,
    .nav-tabs-custom .nav-link.active:focus,
    .nav-tabs-custom .nav-link:active {
        background: var(--theme-primary, #0f766e);
        border-color: var(--theme-primary, #0f766e) !important;
        color: var(--theme-button-text, #ffffff) !important;
    }

    .nav-tabs-custom .nav-link::before,
    .nav-tabs-custom .nav-link::after {
        display: none !important;
    }

    .home-leaderboard-link {
        color: var(--theme-primary, #0f766e);
        font-size: 13px;
        font-weight: 800;
        text-decoration: none;
    }

    .home-leaderboard-empty {
        background: var(--theme-card-bg, #ffffff);
        border: 1px solid color-mix(in srgb, var(--theme-primary, #0f766e) 14%, #e2e8f0);
        border-radius: 12px;
        color: var(--theme-text, #64748b);
        padding: 24px;
        text-align: center;
    }
    
    @media (max-width: 768px) {
        .stat-number {
            font-size: 1.5rem;
        }
        .nav-tabs-custom .nav-link {
            padding: 6px 14px;
            font-size: 12px;
            margin: 0 2px;
        }
        .performer-card {
            padding: 15px 10px;
        }
        .student-avatar, .student-initial {
            width: 50px;
            height: 50px;
            font-size: 20px;
        }
        .score {
            font-size: 1rem;
        }
    }
</style>

@php
    $homeText = function ($value) {
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

    $homepageConfig = $configuration_detail ?? $configuration ?? (function_exists('getConfiguration') ? getConfiguration() : null);
    $homepageSectionKeys = [
        'homepage_show_hero',
        'homepage_show_featured_packages',
        'homepage_show_top_performers',
        'homepage_show_features',
        'homepage_show_testimonials',
        'homepage_show_counters',
    ];
    $hasEnabledHomepageSection = collect($homepageSectionKeys)->contains(function ($key) use ($homepageConfig) {
        return (bool) data_get($homepageConfig, $key, false);
    });
    $fallbackHomepageSections = ! $hasEnabledHomepageSection;

    $showHomepageSection = function ($key, $default = true) use ($homepageConfig, $fallbackHomepageSections) {
        if ($fallbackHomepageSections && in_array($key, [
            'homepage_show_hero',
            'homepage_show_featured_packages',
            'homepage_show_top_performers',
            'homepage_show_features',
            'homepage_show_counters',
        ], true)) {
            return true;
        }

        return (bool) data_get($homepageConfig, $key, $default);
    };
@endphp


{{-- Hero Section --}}
@php
    $quickExamGroups = collect($groups ?? [])->take(8);
    $homeSearchSuggestions = collect($homeSearchSuggestions ?? []);
    $heroTitle = trim((string) data_get($homepageConfig, 'homepage_hero_title')) ?: 'Find Your Exam.';
    $heroPhrases = collect(preg_split('/\s*\|\s*/', (string) data_get($homepageConfig, 'homepage_hero_phrases'), -1, PREG_SPLIT_NO_EMPTY));
    if ($heroPhrases->isEmpty()) $heroPhrases = collect(['Start Practice Fast','Open PYP Papers','Take Mock Tests','Practice with Study Cards','Check Your Rank']);
    $heroDescription = trim((string) data_get($homepageConfig, 'homepage_hero_description')) ?: 'Search mock tests, PYP, exams by name, subject or groups.';
    $heroSearchPlaceholder = trim((string) data_get($homepageConfig, 'homepage_search_placeholder')) ?: 'Search JEE, NEET, CAT, GATE, SSC...';
    $heroSearchButton = trim((string) data_get($homepageConfig, 'homepage_search_button_text')) ?: 'Search Exams';
    $heroSearchEmptyText = trim((string) data_get($homepageConfig, 'homepage_search_empty_text')) ?: 'Type an exam, subject, or group name.';
    $heroDesktopImage = data_get($homepageConfig, 'homepage_hero_desktop_image');
    $heroMobileImage = data_get($homepageConfig, 'homepage_hero_mobile_image');
    $heroHasImage = filled($heroDesktopImage) || filled($heroMobileImage);
    $heroOverlay = max(0, min(85, (int) (data_get($homepageConfig, 'homepage_hero_overlay') ?? 35))) / 100;
    $heroAlignment = data_get($homepageConfig, 'homepage_hero_alignment') === 'left' ? 'left' : 'center';
    $quickQuizShowHeroSetting = data_get($homepageConfig, 'quick_quiz_show_hero');
    $showQuickQuizHero = $quickQuizShowHeroSetting === null ? true : (bool) $quickQuizShowHeroSetting;
@endphp

@if($showHomepageSection('homepage_show_hero', true))
<div class="home-shell">
    <section class="home-hero {{ $heroHasImage ? 'has-background' : '' }} {{ $heroAlignment === 'left' ? 'is-left' : '' }}" @if($heroHasImage) style="--hero-desktop-image:url('{{ $heroDesktopImage ? asset('storage/'.$heroDesktopImage) : asset('storage/'.$heroMobileImage) }}');--hero-mobile-image:url('{{ $heroMobileImage ? asset('storage/'.$heroMobileImage) : asset('storage/'.$heroDesktopImage) }}');--hero-overlay:{{ $heroOverlay }}" @endif>
        <div class="container">
            <div class="{{ $heroAlignment === 'left' ? 'text-start' : 'text-center' }}">
                <h1 class="home-title">
                    {{ $heroTitle }}<br>
                    <span class="home-typewriter" data-phrases="{{ $heroPhrases->implode('|') }}">{{ $heroPhrases->first() }}</span>
                </h1>

                <p class="home-copy">
                    {{ $heroDescription }}
                </p>

                <div class="home-search-card">
                    <form class="home-search-form js-home-search {{ $showQuickQuizHero ? 'has-quiz-action' : '' }}" action="{{ route('courses.index') }}" method="GET">
                        <div class="home-smart-search">
                            <i class="ri-search-line home-search-icon"></i>
                            <input
                                type="search"
                                name="search"
                                class="home-search-box"
                                placeholder="{{ $heroSearchPlaceholder }}"
                                aria-label="Search exams and courses"
                                autocomplete="off"
                                data-suggestion-input>
                            <div class="home-suggestion-panel" data-suggestion-panel>
                                @if($homeSearchSuggestions->isNotEmpty())
                                    @foreach($homeSearchSuggestions as $suggestion)
                                        <button type="button" class="home-suggestion-item" data-suggestion="{{ $suggestion }}">
                                            <i class="ri-search-line"></i>
                                            <span>{{ $suggestion }}</span>
                                        </button>
                                    @endforeach
                                @else
                                    <div class="home-suggestion-empty">{{ $heroSearchEmptyText }}</div>
                                @endif
                            </div>
                        </div>
                        <button class="home-primary-btn" type="submit">
                            {{ $heroSearchButton }} <i class="ri-arrow-right-line"></i>
                        </button>
                        @if($showQuickQuizHero)
                            <button type="button" class="home-secondary-btn" data-quick-quiz-open data-qq-source="hero_button">
                                <i class="ri-flashlight-line"></i> {{ __('ui.quick_quiz') }}
                            </button>
                        @endif
                    </form>
                </div>
                @if($quickExamGroups->isNotEmpty())
                    <div class="home-quick-links" aria-label="Popular exam groups">
                        @foreach($quickExamGroups as $quickGroup)
                            @php
                                $quickGroupName = $homeText($quickGroup->group_name);
                            @endphp
                            <a class="home-chip" href="{{ route('website.exams.index', ['group' => Str::slug($quickGroupName)]) }}">
                                {{ $quickGroupName }}
                            </a>
                        @endforeach
                    </div>
                @endif


            </div>
        </div>
    </section>
</div>
@endif

{{-- Main Content --}}
<div style="background: var(--theme-body-bg, #ffffff); padding: 50px 0;">
    <div class="container">

        @if(collect($groups ?? [])->isNotEmpty())
        <section class="mb-5 pb-4" aria-labelledby="home-exam-groups-title">
            <div class="d-flex flex-column flex-md-row align-items-md-end justify-content-between gap-3 mb-4">
                <div>
                    <span class="home-kicker">{{ __('ui.explore_by_goal') }}</span>
                    <h2 class="home-section-title mt-2" id="home-exam-groups-title">{{ __('ui.choose_your_exam_group') }}</h2>
                    <p class="home-section-copy">{{ __('ui.group_discovery_copy') }}</p>
                </div>
                <a href="{{ route('website.exams.index') }}" class="home-secondary-btn d-none d-md-inline-flex">{{ __('ui.explore_all_groups') }} <i class="ri-arrow-right-line"></i></a>
            </div>
            <div class="row g-3 g-lg-4">
                @foreach(collect($groups)->take(12) as $groupIndex => $group)
                    @php
                        $groupName = $homeText($group->group_name);
                        $groupIcons = ['ri-graduation-cap-line','ri-stethoscope-line','ri-tools-line','ri-government-line','ri-bank-line','ri-book-open-line','ri-building-4-line','ri-briefcase-4-line'];
                        $packageCount = $group->packages?->count() ?? 0;
                        $examCount = $group->packages?->sum(fn($package) => (int) ($package->exams_count ?? 0)) ?? 0;
                    @endphp
                    <div class="col-xl-3 col-lg-4 col-md-6">
                        <a href="{{ route('website.exams.index', ['group' => $group->slug ?: Str::slug($groupName)]) }}" class="d-flex h-100 align-items-center gap-3 text-decoration-none p-3 p-lg-4" style="background:var(--theme-card-bg,#fff);border:1px solid color-mix(in srgb,var(--theme-primary,#0f766e) 18%,#dbe5e3);border-radius:16px;box-shadow:0 8px 24px rgba(15,23,42,.05);transition:transform .2s ease,box-shadow .2s ease;">
                            <span class="home-step-icon flex-shrink-0"><i class="{{ $groupIcons[$groupIndex % count($groupIcons)] }}"></i></span>
                            <span class="min-w-0 flex-grow-1">
                                <strong class="d-block text-truncate" style="font-size:1.05rem;color:var(--theme-heading,#0f172a);">{{ $groupName }}</strong>
                                <small style="color:var(--theme-text,#64748b);">{{ $packageCount }} packages @if($examCount) &middot; {{ $examCount }} exams @endif</small>
                            </span>
                            <i class="ri-arrow-right-line flex-shrink-0" style="color:var(--theme-primary,#0f766e);font-size:1.25rem;"></i>
                        </a>
                    </div>
                @endforeach
            </div>
            <div class="d-md-none mt-3"><a href="{{ route('website.exams.index') }}" class="home-secondary-btn">{{ __('ui.explore_all_groups') }} <i class="ri-arrow-right-line"></i></a></div>
        </section>
        @endif
        
        {{-- Category and subcategory discovery --}}
        @if(($homepageCategories ?? collect())->isNotEmpty())
        <section class="mt-5 mb-5 pt-3" aria-labelledby="home-categories-title">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-end gap-3 mb-4">
                <div>
                    <span class="home-kicker">{{ __('website.course_categories') }}</span>
                    <h2 class="home-section-title mt-2" id="home-categories-title">{{ __('ui.choose_category') }}</h2>
                    <p class="home-section-copy">{{ __('ui.category_discovery_copy') }}</p>
                </div>
                <a href="{{ route('website.exams.index', ['group' => 'all']) }}" class="home-secondary-btn d-none d-md-inline-flex">{{ __('ui.explore_all_categories') }} <i class="ri-arrow-right-line"></i></a>
            </div>
            <div class="row g-4">
                @foreach($homepageCategories as $categoryIndex => $category)
                    @php
                        $categoryIcons = ['ri-calculator-line', 'ri-microscope-line', 'ri-government-line', 'ri-briefcase-4-line', 'ri-building-4-line', 'ri-graduation-cap-line'];
                        $categoryDescription = trim(strip_tags($homeText($category->description)));
                        $categoryTitle = $homeText($category->title);
                        $categoryTitleClass = mb_strlen($categoryTitle) > 42 ? 'is-very-long' : (mb_strlen($categoryTitle) > 26 ? 'is-long' : '');
                    @endphp
                    <div class="col-xl-4 col-lg-4 col-md-6">
                        <a href="{{ url('/exam-groups/all/' . $category->slug) }}" class="home-discovery-card is-category">
                            <span class="home-discovery-icon"><i class="{{ $categoryIcons[$categoryIndex % count($categoryIcons)] }}"></i></span>
                            <span class="home-discovery-content">
                                <h3 class="home-discovery-title {{ $categoryTitleClass }}">{{ $categoryTitle }}</h3>
                                <span class="home-discovery-meta"><strong>{{ $category->packages_count }}</strong> {{ \Illuminate\Support\Str::plural('package', $category->packages_count) }}</span>
                            </span>
                            <i class="ri-arrow-right-line home-discovery-arrow"></i>
                        </a>
                    </div>
                @endforeach
            </div>
            <div class="d-md-none mt-3"><a href="{{ route('website.exams.index', ['group' => 'all']) }}" class="home-secondary-btn">{{ __('ui.explore_all_categories') }} <i class="ri-arrow-right-line"></i></a></div>
        </section>
        @endif

        @if(($homepageSubcategories ?? collect())->isNotEmpty())
        <div class="mb-5" data-subcategory-ui>
            <div class="mb-4">
                <span class="home-kicker">{{ __('ui.subcategories') }}</span>
                <h2 class="home-section-title mt-2">{{ __('ui.explore_subcategories') }}</h2>
                <p class="home-section-copy">{{ __('ui.subcategory_copy') }}</p>
            </div>
            <div class="home-subcategory-grid">
                @foreach($homepageSubcategories as $subcategory)
                    <a href="{{ url('/exam-groups/all/' . $subcategory->parent?->slug . '/' . $subcategory->slug) }}" class="home-subcategory-card">
                        <span class="home-subcategory-icon"><i class="ri-node-tree"></i></span>
                        <span>
                            <strong>{{ $homeText($subcategory->title) }}</strong>
                            <small>{{ $homeText($subcategory->parent?->title) }} &middot; {{ $subcategory->subcategory_packages_count }} {{ \Illuminate\Support\Str::plural('package', $subcategory->subcategory_packages_count) }}</small>
                        </span>
                        <i class="ri-arrow-right-s-line"></i>
                    </a>
                @endforeach
            </div>
            <div class="d-md-none mt-3"><a href="{{ route('website.exams.index', ['group' => 'all']) }}" class="home-secondary-btn">{{ __('ui.explore_all_subcategories') }} <i class="ri-arrow-right-line"></i></a></div>
        </div>
        @endif
        {{-- Featured Exam Packages Section --}}
        @if($showHomepageSection('homepage_show_featured_packages'))
        <section id="features" class="home-packages-section">
            <div class="d-flex flex-column flex-md-row align-items-md-end justify-content-between gap-3 mb-4">
                <div>
                    <h2 class="home-section-title">{{ $Packages_Titles->title ?? __('ui.popular_mock_tests') }}</h2>
                    <p class="home-section-copy">{{ $Packages_Titles->description ?? 'Choose a package and see all available papers before starting.' }}</p>
                </div>
                <a href="{{ route('courses.index') }}" class="home-secondary-btn d-none d-md-inline-flex">
                    {{ __('website.view_all_courses') }} <i class="ri-arrow-right-line"></i>
                </a>
            </div>
            
            <div class="row g-4">
                @if(isset($allPackages) && $allPackages->count() > 0)
                    @foreach($allPackages->take(9) as $package)
                        <div class="col-xl-4 col-lg-4 col-md-6">
                            @include('website.course_card', ['package' => $package, 'configuration_detail' => $configuration_detail ?? []])
                        </div>
                    @endforeach
                @else
                    <div class="col-12 text-center">
                        <p style="color: var(--theme-text, #64748b);">{{ __('ui.no_courses_yet') }}</p>
                    </div>

                @endif
            </div>
            <div class="d-md-none mt-3"><a href="{{ route('courses.index') }}" class="home-secondary-btn">{{ __('ui.explore_all_packages') }} <i class="ri-arrow-right-line"></i></a></div>
        </section>
        @endif

        {{-- Group Wise Performers Section --}}
        @php
            $displayGroupWisePerformers = collect($groupWisePerformers ?? []);
            if ($displayGroupWisePerformers->isEmpty()) {
                $displayGroupWisePerformers = collect($fallbackPerformerGroups ?? []);
            }
        @endphp
        @if($showHomepageSection('homepage_show_top_performers') && $displayGroupWisePerformers->isNotEmpty())
        <div class="home-leaderboard-section" data-leaderboard-panel="home">
            <div class="d-flex flex-column flex-md-row align-items-md-end justify-content-between gap-3 mb-4">
                <div>
                    <span class="home-kicker">{{ __('ui.leaderboard') }}</span>
                    @include('components.leaderboard-period-toggle', ['leaderboardPanelKey' => 'home'])
                    <h2 class="home-section-title mt-2">{{ __('ui.top_performers_group') }}</h2>
                    <p class="home-section-copy">{{ __('ui.leaderboard_copy') }}</p>
                </div>
            </div>
            
            <ul class="nav nav-tabs-custom" id="groupTabs" role="tablist">
                @foreach($displayGroupWisePerformers as $index => $groupData)
                    @php
                        $leaderGroupName = $homeText($groupData['group']['name'] ?? 'Exam Group');
                    @endphp
                    <li class="nav-item" role="presentation">
                        <button
                            class="nav-link {{ $index === 0 ? 'active' : '' }}"
                            id="tab-{{ $groupData['group']['id'] }}"
                            data-bs-toggle="tab"
                            data-bs-target="#content-{{ $groupData['group']['id'] }}"
                            type="button"
                            role="tab">
                            {{ $leaderGroupName }}
                        </button>
                    </li>
                @endforeach
            </ul>

            <div class="tab-content" id="groupTabsContent">
                @foreach($displayGroupWisePerformers as $index => $groupData)
                    @php
                        $leaderGroupName = $homeText($groupData['group']['name'] ?? 'Exam Group');
                        $leaderGroupUrl = route('website.exams.index', ['group' => Str::slug($leaderGroupName)]);
                    @endphp
                    <div
                        class="tab-pane fade {{ $index === 0 ? 'show active' : '' }}"
                        id="content-{{ $groupData['group']['id'] }}"
                        role="tabpanel">
                        <div class="d-flex justify-content-end mb-3">
                            <a class="home-leaderboard-link" href="{{ $leaderGroupUrl }}">
                                View {{ $leaderGroupName }} packages <i class="ri-arrow-right-line"></i>
                            </a>
                        </div>
                        <div class="row g-4">
                            @forelse(collect($groupData['top_performers'] ?? []) as $rank => $student)
                                @php
                                    $rankNum = $rank + 1;
                                    $badgeClass = '';
                                    if ($rankNum == 1) $badgeClass = 'gold';
                                    elseif ($rankNum == 2) $badgeClass = 'silver';
                                    elseif ($rankNum == 3) $badgeClass = 'bronze';
                                @endphp
                                <div class="col-lg col-md-4 col-sm-6">
                                    <div class="performer-card">
                                        <span class="rank-badge">#{{ $rankNum }}</span>

                                        @if($student['student_photo'])
                                            <img src="{{ asset('storage/' . $student['student_photo']) }}" alt="{{ $student['student_name'] }}" class="student-avatar {{ $badgeClass }}" width="70" height="70" loading="lazy" decoding="async">
                                        @else
                                            <div class="student-initial {{ $badgeClass }}">
                                                {{ strtoupper(substr($student['student_name'], 0, 1)) }}
                                            </div>
                                        @endif

                                        <h5>{{ $student['student_name'] }}</h5>
                                        <span class="exam-name">{{ $student['exam_name'] ?? '-' }}</span>
                                        <div class="score">{{ number_format($student['score_percent'] ?? $student['avg_percentile'], 2) }}<span>%</span></div>
                                            <div class="student-percentile">
                                                {{ number_format($student['percentile'] ?? 0, 2) }} Percentile
                                        </div>
                                    </div>
                                </div>
                            @empty
                                <div class="col-12">
                                    <div class="home-leaderboard-empty">
                                        <i class="ri-bar-chart-grouped-line" style="font-size: 32px; color: var(--theme-primary);"></i>
                                        <p style="margin: 10px 0 0;">{{ __('ui.leaderboard_empty') }}</p>
                                    </div>
                                </div>
                            @endforelse
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
        @endif

        {{-- Why ExamElite / Features Section --}}
        @if($showHomepageSection('homepage_show_features'))
        @php
            $homepageFeatures = isset($Features) ? $Features->take(4) : collect();

            $fallbackFeatures = collect([
                ['title' => '500+ Mock Tests', 'description' => 'For JEE, NEET, GATE, UPSC, CAT and more', 'icon' => 'ri-file-list-3-line'],
                ['title' => 'Smart Analytics', 'description' => 'Track progress and weak areas', 'icon' => 'ri-bar-chart-box-line'],
                ['title' => 'Mobile Ready', 'description' => 'Practice anytime, anywhere', 'icon' => 'ri-smartphone-line'],
                ['title' => 'Detailed Solutions', 'description' => 'Step-by-step explanations', 'icon' => 'ri-question-answer-line'],
            ]);

            $featureIcons = ['ri-file-list-3-line', 'ri-bar-chart-box-line', 'ri-smartphone-line', 'ri-question-answer-line'];
            $featureGradients = [
                'var(--theme-primary, #0f766e)',
                'var(--theme-secondary, #f59e0b)',
                'var(--theme-tertiary, #38bdf8)',
                'color-mix(in srgb, var(--theme-primary, #0f766e) 78%, var(--theme-secondary, #f59e0b))',
            ];
        @endphp

        <div style="margin-bottom: 60px;">
            <div class="text-center mb-4">
                <h2 style="font-size: clamp(1.5rem, 4vw, 1.8rem); font-weight: 800; color: var(--theme-heading, #0f172a);">
                    {{ $Features_Titles->title ?? 'Why ExamElite?' }}
                </h2>
                <p style="color: var(--theme-text, #64748b);">{{ $Features_Titles->description ?? 'The complete platform to master your exams and achieve your goals' }}</p>
            </div>

            <div class="row g-4">
                @forelse($homepageFeatures as $featureIndex => $feature)
                    @php
                        $gradient = $featureGradients[$featureIndex % count($featureGradients)];
                        $icon = $feature->icon ?: $featureIcons[$featureIndex % count($featureIcons)];
                    @endphp
                    <div class="col-md-3 col-6">
                        <div style="background: var(--theme-card-bg, #ffffff); border: 1px solid color-mix(in srgb, var(--theme-primary, #0f766e) 13%, #e2e8f0); border-radius: 12px; padding: 20px 15px; text-align: center; box-shadow: 0 5px 15px rgba(0,0,0,0.05); height: 100%;">
                            <div style="width: 58px; height: 58px; background: {{ $gradient }}; border-radius: 18px; display: flex; align-items: center; justify-content: center; margin: 0 auto 12px;">
                                @if($feature->icon)
                                    <i class="{{ $icon }}" style="font-size: 28px; color: white;"></i>
                                @elseif(!empty($feature->image_url))
                                    <img src="{{ asset($feature->image_url) }}" alt="{{ $feature->title }}" width="32" height="32" loading="lazy" decoding="async" style="width: 32px; height: 32px; object-fit: contain;">
                                @else
                                    <i class="{{ $icon }}" style="font-size: 28px; color: white;"></i>
                                @endif
                            </div>
                            <h3 style="font-size: 0.9rem; font-weight: 700; margin-bottom: 8px;">{{ $feature->title }}</h3>
                            <p style="color: var(--theme-text, #64748b); font-size: 0.7rem;">{{ $feature->description }}</p>
                        </div>
                    </div>
                @empty
                    @foreach($fallbackFeatures as $featureIndex => $feature)
                        @php $gradient = $featureGradients[$featureIndex % count($featureGradients)]; @endphp
                        <div class="col-md-3 col-6">
                            <div style="background: var(--theme-card-bg, #ffffff); border: 1px solid color-mix(in srgb, var(--theme-primary, #0f766e) 13%, #e2e8f0); border-radius: 12px; padding: 20px 15px; text-align: center; box-shadow: 0 5px 15px rgba(0,0,0,0.05); height: 100%;">
                                <div style="width: 58px; height: 58px; background: {{ $gradient }}; border-radius: 18px; display: flex; align-items: center; justify-content: center; margin: 0 auto 12px;">
                                    <i class="{{ $feature['icon'] }}" style="font-size: 28px; color: white;"></i>
                                </div>
                                <h3 style="font-size: 0.9rem; font-weight: 700; margin-bottom: 8px;">{{ $feature['title'] }}</h3>
                                <p style="color: var(--theme-text, #64748b); font-size: 0.7rem;">{{ $feature['description'] }}</p>
                            </div>
                        </div>
                    @endforeach
                @endforelse
            </div>
        </div>
        @endif

        {{-- Testimonials Section --}}
        @if($showHomepageSection('homepage_show_testimonials') && isset($Testimonial) && $Testimonial->count() > 0)
        <div style="margin-bottom: 60px;">
            <div class="text-center mb-4">
                <h2 style="font-size: clamp(1.5rem, 4vw, 1.8rem); font-weight: 800; color: var(--theme-heading, #0f172a);">{{ $Testimonial_Titles->title ?? __('ui.what_students_say') }}</h2>
                <p style="color: var(--theme-text, #64748b);">{{ $Testimonial_Titles->description ?? __('ui.join_successful_students') }}</p>
            </div>
            
            <div class="row g-4">
                @foreach($Testimonial->take(3) as $testimonial)
                <div class="col-md-4">
                    <div style="background: var(--theme-card-bg, #ffffff); border: 1px solid color-mix(in srgb, var(--theme-primary, #0f766e) 13%, #e2e8f0); padding: 25px; border-radius: 12px; box-shadow: 0 5px 15px rgba(0,0,0,0.05); text-align: center; height: 100%;">
                        <div style="width: 60px; height: 60px; background: var(--theme-primary, #0f766e); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 15px;">
                            <i class="ri-user-line" style="font-size: 28px; color: white;"></i>
                        </div>
                        <p style="color: var(--theme-text, #64748b); font-style: italic; line-height: 1.5; font-size: 0.85rem;">"{{ $testimonial->feedback }}"</p>
                        <h4 style="font-size: 1rem; font-weight: 700; margin-top: 12px;">{{ $testimonial->name }}</h4>
                        
                        <div style="display: flex; justify-content: center; gap: 3px; margin-top: 8px;">
                            <i class="ri-star-fill" style="color: var(--theme-secondary); font-size: 12px;"></i>
                            <i class="ri-star-fill" style="color: var(--theme-secondary); font-size: 12px;"></i>
                            <i class="ri-star-fill" style="color: var(--theme-secondary); font-size: 12px;"></i>
                            <i class="ri-star-fill" style="color: var(--theme-secondary); font-size: 12px;"></i>
                            <i class="ri-star-fill" style="color: var(--theme-secondary); font-size: 12px;"></i>
                        </div>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
        @endif

        {{-- Stats Section --}}
        @if($showHomepageSection('homepage_show_counters'))
        @php
            $homepageCounters = isset($Counter) ? $Counter->take(4) : collect();

            $fallbackCounters = collect([
                ['number' => '15,000+', 'title' => 'Total Exams'],
                ['number' => '500+', 'title' => 'Mock Tests'],
                ['number' => '400,000+', 'title' => 'Questions'],
                ['number' => '50,000+', 'title' => 'Happy Students'],
            ]);
        @endphp

        <div style="background: var(--theme-card-bg, #ffffff); border: 1px solid color-mix(in srgb, var(--theme-primary, #0f766e) 13%, #e2e8f0); border-radius: 12px; padding: 40px 20px; margin-bottom: 40px; box-shadow: 0 5px 15px rgba(0,0,0,0.05);">
            <div class="text-center mb-4">
                <h2 style="font-size: clamp(1.5rem, 4vw, 1.8rem); font-weight: 800; color: var(--theme-heading, #0f172a); margin-bottom: 8px;">{{ $Counter_Titles->title ?? 'Platform at a glance' }}</h2>
                <p style="color: var(--theme-text, #64748b); margin-bottom: 0;">{{ $Counter_Titles->sub_title ?? 'Trusted numbers that show the scale of learning on our platform.' }}</p>
            </div>
            <div class="row text-center g-4">
                @forelse($homepageCounters as $counter)
                    <div class="col-md-3 col-6">
                        @php
                            $counterIcons = ['ri-file-list-3-line', 'ri-user-smile-line', 'ri-question-answer-line', 'ri-award-line'];
                            $counterIcon = $counter->icon ?: $counterIcons[$loop->index % count($counterIcons)];
                        @endphp
                        <div style="width: 42px; height: 42px; border-radius: 14px; background: color-mix(in srgb, var(--theme-primary) 12%, transparent); color: var(--theme-primary); display: flex; align-items: center; justify-content: center; margin: 0 auto 10px;">
                            @if($counter->icon)
                                <i class="{{ $counterIcon }}" style="font-size: 24px;"></i>
                            @elseif(!empty($counter->image_url))
                                <img src="{{ asset($counter->image_url) }}" alt="{{ $counter->title }}" width="28" height="28" loading="lazy" decoding="async" style="height: 28px; width: 28px; object-fit: contain;">
                            @else
                                <i class="{{ $counterIcon }}" style="font-size: 24px;"></i>
                            @endif
                        </div>
                        <div class="stat-number">{{ $counter->number }}</div>
                        <p style="color: var(--theme-text, #64748b); font-weight: 500; margin-top: 8px;">{{ $counter->title }}</p>
                    </div>
                @empty
                    @foreach($fallbackCounters as $counter)
                        <div class="col-md-3 col-6">
                            <div class="stat-number">{{ $counter['number'] }}</div>
                            <p style="color: var(--theme-text, #64748b); font-weight: 500; margin-top: 8px;">{{ $counter['title'] }}</p>
                        </div>
                    @endforeach
                @endforelse
            </div>
        </div>
        @endif

        {{-- CTA Section --}}
        <div style="background: var(--theme-primary, #0f766e); padding: 50px 30px; border-radius: 24px; position: relative; overflow: hidden; text-align: center;">
            <div style="position: relative; z-index: 2;">
                <h2 style="color: white; font-size: clamp(1.5rem, 4vw, 1.8rem); font-weight: 800; margin-bottom: 15px;">{{ $Banner_Titles->title ?? __('ui.ready_to_ace') }}</h2>
                <p style="color: rgba(255,255,255,0.9); font-size: 0.95rem; margin-bottom: 25px; max-width: 600px; margin-left: auto; margin-right: auto;">
                    {{ $Banner_Titles->description ?? __('ui.join_successful_students') }}
                </p>
                <a href="{{ route('courses.index') }}" style="background: var(--theme-secondary); color: white; padding: 14px 40px; border-radius: 50px; text-decoration: none; font-weight: 700; font-size: 1rem; display: inline-flex; align-items: center; gap: 8px; transition: all 0.3s ease;">
                    Start Your Journey <i class="ri-arrow-right-line"></i>
                </a>
            </div>
        </div>
        
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const typewriter = document.querySelector('.home-typewriter');
        if (!typewriter || window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
            return;
        }

        const phrases = (typewriter.dataset.phrases || '')
            .split('|')
            .map((phrase) => phrase.trim())
            .filter(Boolean);

        if (phrases.length < 2) {
            return;
        }

        let phraseIndex = 0;
        let charIndex = phrases[0].length;
        let deleting = true;

        const tick = function () {
            const phrase = phrases[phraseIndex];
            typewriter.textContent = phrase.slice(0, charIndex);

            if (deleting) {
                charIndex -= 1;
                if (charIndex < 0) {
                    deleting = false;
                    phraseIndex = (phraseIndex + 1) % phrases.length;
                    window.setTimeout(tick, 220);
                    return;
                }
            } else {
                charIndex += 1;
                if (charIndex > phrases[phraseIndex].length) {
                    deleting = true;
                    window.setTimeout(tick, 1400);
                    return;
                }
            }

            window.setTimeout(tick, deleting ? 36 : 68);
        };

        window.setTimeout(tick, 1400);
    });

    document.addEventListener('DOMContentLoaded', function () {
        const form = document.querySelector('.js-home-search');
        if (!form) return;

        const input = form.querySelector('[data-suggestion-input]');
        const panel = form.querySelector('[data-suggestion-panel]');
        if (!input || !panel) return;

        const items = Array.from(panel.querySelectorAll('[data-suggestion]'));
        const updatePanel = function () {
            const query = input.value.trim().toLowerCase();
            let visibleCount = 0;

            items.forEach(function (item) {
                const value = (item.dataset.suggestion || '').toLowerCase();
                const visible = !query || value.includes(query);
                item.hidden = !visible;
                if (visible) visibleCount += 1;
            });

            panel.classList.toggle('is-open', document.activeElement === input && visibleCount > 0);
        };

        input.addEventListener('focus', updatePanel);
        input.addEventListener('input', updatePanel);

        items.forEach(function (item) {
            item.addEventListener('click', function () {
                input.value = item.dataset.suggestion || '';
                panel.classList.remove('is-open');
                form.submit();
            });
        });

        document.addEventListener('click', function (event) {
            if (!form.contains(event.target)) {
                panel.classList.remove('is-open');
            }
        });
    });
</script>

@include('website.partials.quick-quiz', ['quizGroups' => collect($groups ?? [])->take(12)])
@endsection

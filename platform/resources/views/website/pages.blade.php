@extends('website.layouts.app')

@php
    $displayText = static function ($value): string {
        if (is_array($value)) {
            $candidate = $value['en'] ?? reset($value);
            return is_scalar($candidate) ? (string) $candidate : '';
        }

        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $candidate = $decoded['en'] ?? reset($decoded);
                return is_scalar($candidate) ? (string) $candidate : $value;
            }
        }

        return is_scalar($value) ? (string) $value : '';
    };

    $pageTitle = trim($displayText($page->title ?? '')) ?: 'Page';
    $pageDescription = trim($displayText($page->description ?? ''));
    $isInstitutePage = str_contains($pageDescription, 'institute-hero')
        || str_contains($pageDescription, 'plan-grid')
        || str_contains($pageDescription, 'comparison-table')
        || str_contains($pageDescription, 'saas-form-grid');
@endphp

@section('title', $pageTitle)

@section('content')
<style>
    .theme-page-shell {
        background: var(--theme-body-bg, #ffffff);
        padding: clamp(24px, 5vw, 52px) 0 clamp(36px, 7vw, 70px);
    }

    .theme-page-shell--custom {
        padding-top: 0;
    }

    .theme-page-custom-container {
        margin: 0 auto;
        max-width: 1180px;
        padding: 0 16px;
    }

    .theme-page-hero,
    .institute-hero,
    .institute-section {
        background: var(--theme-card-bg, #ffffff);
        border: 1px solid color-mix(in srgb, var(--theme-primary, #0f766e) 15%, #e2e8f0);
        border-radius: 18px;
    }

    .theme-page-hero {
        background: color-mix(in srgb, var(--theme-primary, #0f766e) 7%, var(--theme-card-bg, #ffffff));
        padding: clamp(22px, 5vw, 44px);
    }

    .theme-page-kicker,
    .eyebrow {
        color: var(--theme-primary, #0f766e);
        font-size: .78rem;
        font-weight: 850;
        letter-spacing: .06em;
        margin-bottom: 10px;
        text-transform: uppercase;
    }

    .theme-page-title,
    .institute-hero h1 {
        color: var(--theme-heading, #0f172a);
        font-size: clamp(1.8rem, 6vw, 3.4rem);
        font-weight: 900;
        letter-spacing: 0;
        line-height: 1.08;
        margin: 0;
    }

    .theme-page-card {
        background: var(--theme-card-bg, #ffffff);
        border: 1px solid color-mix(in srgb, var(--theme-primary, #0f766e) 14%, #e2e8f0);
        border-radius: 16px;
        margin-top: 18px;
        padding: clamp(20px, 4.5vw, 40px);
    }

    .theme-page-content {
        color: var(--theme-text, #64748b);
        font-size: 1rem;
        line-height: 1.78;
        overflow-wrap: anywhere;
    }

    .theme-page-content > *:first-child {
        margin-top: 0 !important;
    }

    .theme-page-content > *:last-child {
        margin-bottom: 0 !important;
    }

    .theme-page-content h1,
    .theme-page-content h2,
    .theme-page-content h3,
    .theme-page-content h4,
    .theme-page-content h5,
    .theme-page-content h6 {
        color: var(--theme-heading, #0f172a);
        font-weight: 850;
        line-height: 1.2;
        margin: 1.4rem 0 .75rem;
    }

    .theme-page-content a {
        color: var(--theme-primary, #0f766e);
        font-weight: 800;
    }

    .theme-page-content img {
        border-radius: 12px;
        height: auto;
        max-width: 100%;
    }

    .theme-page-custom-content {
        color: var(--theme-text, #64748b);
        font-size: 1rem;
        line-height: 1.65;
    }

    .institute-hero {
        background: linear-gradient(135deg, color-mix(in srgb, var(--theme-primary, #0f766e) 10%, #ffffff), #ffffff 62%);
        margin-top: clamp(18px, 4vw, 34px);
        padding: clamp(28px, 6vw, 70px);
    }

    .institute-hero .lead {
        color: var(--theme-text, #64748b);
        font-size: clamp(1.02rem, 2.5vw, 1.28rem);
        line-height: 1.65;
        margin: 18px 0 0;
        max-width: 850px;
    }

    .institute-section {
        margin-top: 22px;
        padding: clamp(22px, 4.5vw, 42px);
    }

    .institute-section > h2 {
        color: var(--theme-heading, #0f172a);
        font-size: clamp(1.55rem, 4vw, 2.25rem);
        font-weight: 900;
        margin: 0 0 8px;
    }

    .institute-section > p {
        color: var(--theme-text, #64748b);
        font-size: 1rem;
        margin: 0 0 22px;
        max-width: 760px;
    }

    .plan-grid,
    .feature-grid {
        display: grid;
        gap: 18px;
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }

    .plan-card,
    .feature-grid > div {
        background: color-mix(in srgb, var(--theme-primary, #0f766e) 4%, #ffffff);
        border: 1px solid color-mix(in srgb, var(--theme-primary, #0f766e) 15%, #e2e8f0);
        border-radius: 16px;
        padding: 22px;
    }

    .plan-card.featured {
        background: #ffffff;
        border-color: var(--theme-primary, #0f766e);
        box-shadow: 0 16px 36px color-mix(in srgb, var(--theme-primary, #0f766e) 16%, transparent);
        position: relative;
    }

    .plan-badge {
        align-items: center;
        background: color-mix(in srgb, var(--theme-secondary, #f59e0b) 18%, #ffffff);
        border-radius: 999px;
        color: color-mix(in srgb, var(--theme-secondary, #f59e0b) 75%, #111827);
        display: inline-flex;
        font-size: .78rem;
        font-weight: 850;
        margin: 0 0 14px;
        padding: 6px 11px;
    }

    .plan-card h3,
    .feature-grid h3 {
        color: var(--theme-heading, #0f172a);
        font-size: 1.22rem;
        font-weight: 900;
        margin: 0 0 6px;
    }

    .plan-note,
    .feature-grid p {
        color: var(--theme-text, #64748b);
        margin: 0;
    }

    .plan-price {
        color: var(--theme-primary, #0f766e);
        font-size: 1.55rem;
        font-weight: 900;
        margin: 18px 0 16px;
    }

    .plan-card ul {
        display: grid;
        gap: 10px;
        list-style: none;
        margin: 0;
        padding: 0;
    }

    .plan-card li {
        color: var(--theme-heading, #0f172a);
        padding-left: 24px;
        position: relative;
    }

    .plan-card li::before {
        color: var(--theme-primary, #0f766e);
        content: '?';
        font-weight: 900;
        left: 0;
        position: absolute;
        top: 0;
    }

    .comparison-wrap {
        border: 1px solid color-mix(in srgb, var(--theme-primary, #0f766e) 14%, #e2e8f0);
        border-radius: 16px;
        overflow-x: auto;
    }

    .comparison-table {
        border-collapse: collapse;
        min-width: 760px;
        width: 100%;
    }

    .comparison-table th,
    .comparison-table td {
        border-bottom: 1px solid color-mix(in srgb, var(--theme-primary, #0f766e) 10%, #e2e8f0);
        padding: 15px 16px;
        text-align: left;
        vertical-align: top;
    }

    .comparison-table th {
        background: color-mix(in srgb, var(--theme-primary, #0f766e) 10%, #ffffff);
        color: var(--theme-heading, #0f172a);
        font-weight: 900;
    }

    .comparison-table td:first-child {
        color: var(--theme-heading, #0f172a);
        font-weight: 800;
    }

    .comparison-table tr:last-child td {
        border-bottom: 0;
    }

    .saas-alert {
        border-radius: 12px;
        font-weight: 750;
        margin-bottom: 16px;
        padding: 13px 15px;
    }

    .saas-alert-success {
        background: color-mix(in srgb, var(--theme-primary, #0f766e) 12%, #ffffff);
        border: 1px solid color-mix(in srgb, var(--theme-primary, #0f766e) 22%, #d1fae5);
        color: var(--theme-primary, #0f766e);
    }

    .saas-form-grid {
        display: grid;
        gap: 16px;
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .saas-field,
    .saas-field-full {
        display: flex;
        flex-direction: column;
        gap: 7px;
    }

    .saas-field-full {
        grid-column: 1 / -1;
    }

    .saas-field label,
    .saas-field-full label {
        color: var(--theme-heading, #0f172a);
        font-weight: 850;
    }

    .saas-field input,
    .saas-field select,
    .saas-field-full textarea {
        background: #ffffff;
        border: 1px solid color-mix(in srgb, var(--theme-primary, #0f766e) 18%, #cbd5e1);
        border-radius: 12px;
        color: var(--theme-heading, #0f172a);
        font: inherit;
        min-height: 48px;
        padding: 12px 14px;
        width: 100%;
    }

    .saas-field input:focus,
    .saas-field select:focus,
    .saas-field-full textarea:focus {
        border-color: var(--theme-primary, #0f766e);
        box-shadow: 0 0 0 3px color-mix(in srgb, var(--theme-primary, #0f766e) 16%, transparent);
        outline: none;
    }

    .saas-error {
        color: #dc2626;
        font-size: .88rem;
        font-weight: 750;
    }

    .saas-actions {
        align-items: center;
        display: flex;
        gap: 12px;
        grid-column: 1 / -1;
        justify-content: flex-start;
    }

    .saas-btn {
        align-items: center;
        border-radius: 999px;
        display: inline-flex;
        font-weight: 900;
        justify-content: center;
        min-height: 48px;
        padding: 12px 22px;
        text-decoration: none;
        transition: transform .18s ease, box-shadow .18s ease, background-color .18s ease, color .18s ease;
    }

    .saas-btn:hover {
        transform: translateY(-1px);
    }

    .saas-btn-primary {
        background: var(--theme-primary, #0f766e);
        border: 1px solid var(--theme-primary, #0f766e);
        color: #ffffff !important;
    }

    .saas-btn-primary:hover,
    .saas-btn-primary:focus {
        background: color-mix(in srgb, var(--theme-primary, #0f766e) 88%, #000000);
        color: #ffffff !important;
    }

    .saas-btn-outline {
        background: #ffffff;
        border: 1px solid var(--theme-primary, #0f766e);
        color: var(--theme-primary, #0f766e) !important;
    }

    .saas-btn-outline:hover,
    .saas-btn-outline:focus {
        background: color-mix(in srgb, var(--theme-primary, #0f766e) 8%, #ffffff);
        color: var(--theme-primary, #0f766e) !important;
    }

    @media (max-width: 991px) {
        .plan-grid,
        .feature-grid {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 640px) {
        .theme-page-custom-container {
            padding: 0 12px;
        }

        .institute-hero,
        .institute-section {
            border-radius: 14px;
        }

        .saas-form-grid {
            grid-template-columns: 1fr;
        }

        .saas-actions {
            align-items: stretch;
            flex-direction: column;
        }

        .saas-btn {
            width: 100%;
        }
    }
</style>

<section class="theme-page-shell {{ $isInstitutePage ? 'theme-page-shell--custom' : '' }}">
    <div class="{{ $isInstitutePage ? 'theme-page-custom-container' : 'container' }}">
        @unless($isInstitutePage)
            <div class="theme-page-hero">
                <div class="theme-page-kicker">{{ __('website.information') }}</div>
                <h1 class="theme-page-title">{{ $pageTitle }}</h1>
            </div>
        @endunless

        @if($pageDescription !== '')
            @if($isInstitutePage)
                <div class="theme-page-content theme-page-custom-content">
                    {!! updateImageSrcWithAsset($pageDescription) !!}
                </div>
            @else
                <div class="theme-page-card">
                    <div class="theme-page-content">
                        {!! updateImageSrcWithAsset($pageDescription) !!}
                    </div>
                </div>
            @endif
        @endif
    </div>
</section>
@endsection
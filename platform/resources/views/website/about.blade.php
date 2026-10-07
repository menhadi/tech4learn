@extends('website.layouts.app')

@section('title', 'About Us')

@section('content')
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

    $organizationName = $configuration_detail->organization_name ?? config('app.name', 'ExamElite');
    $aboutTitle = trim($displayText($AboutUs->title ?? '')) ?: 'About ' . $organizationName;
    $aboutDescription = trim($displayText($AboutUs->description ?? ''));
@endphp

<style>
    .theme-page-shell {
        background: var(--theme-body-bg, #ffffff);
        padding: clamp(24px, 5vw, 52px) 0 clamp(36px, 7vw, 70px);
    }

    .theme-page-hero {
        background: color-mix(in srgb, var(--theme-primary, #0f766e) 7%, var(--theme-card-bg, #ffffff));
        border: 1px solid color-mix(in srgb, var(--theme-primary, #0f766e) 16%, #e2e8f0);
        border-radius: 16px;
        padding: clamp(22px, 5vw, 44px);
    }

    .theme-page-kicker {
        color: var(--theme-primary, #0f766e);
        font-size: .78rem;
        font-weight: 850;
        letter-spacing: .06em;
        margin-bottom: 10px;
        text-transform: uppercase;
    }

    .theme-page-title {
        color: var(--theme-heading, #0f172a);
        font-size: clamp(1.8rem, 6vw, 3rem);
        font-weight: 900;
        line-height: 1.1;
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
</style>

<section class="theme-page-shell">
    <div class="container">
        <div class="theme-page-hero">
            <div class="theme-page-kicker">{{ __('ui.about') }}</div>
            <h1 class="theme-page-title">{{ $aboutTitle }}</h1>
        </div>

        @if($aboutDescription !== '')
            <div class="theme-page-card">
                <div class="theme-page-content">
                    {!! updateImageSrcWithAsset($aboutDescription) !!}
                </div>
            </div>
        @endif
    </div>
</section>
@endsection

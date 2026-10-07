@extends('website.layouts.app')

@section('title', 'Contact Us')

@section('content')
@php
    $contactItems = collect([
        [
            'label' => 'Address',
            'value' => $configuration_detail->organization_address ?? null,
            'icon' => 'ri-map-pin-line',
        ],
        [
            'label' => 'Email',
            'value' => $configuration_detail->email ?? null,
            'icon' => 'ri-mail-line',
            'href' => !empty($configuration_detail->email) ? 'mailto:' . $configuration_detail->email : null,
        ],
        [
            'label' => 'Phone',
            'value' => $configuration_detail->organization_phone ?? null,
            'icon' => 'ri-phone-line',
            'href' => !empty($configuration_detail->organization_phone) ? 'tel:' . preg_replace('/\s+/', '', $configuration_detail->organization_phone) : null,
        ],
    ])->filter(fn ($item) => filled($item['value'] ?? null));

    $organizationName = $configuration_detail->organization_name ?? config('app.name', 'ExamElite');
@endphp

<style>
    .theme-contact-shell {
        background: var(--theme-body-bg, #ffffff);
        padding: clamp(24px, 5vw, 52px) 0 clamp(36px, 7vw, 70px);
    }

    .theme-contact-hero {
        background: color-mix(in srgb, var(--theme-primary, #0f766e) 7%, var(--theme-card-bg, #ffffff));
        border: 1px solid color-mix(in srgb, var(--theme-primary, #0f766e) 16%, #e2e8f0);
        border-radius: 16px;
        padding: clamp(22px, 5vw, 44px);
    }

    .theme-contact-kicker {
        color: var(--theme-primary, #0f766e);
        font-size: .78rem;
        font-weight: 850;
        letter-spacing: .06em;
        margin-bottom: 10px;
        text-transform: uppercase;
    }

    .theme-contact-title {
        color: var(--theme-heading, #0f172a);
        font-size: clamp(1.8rem, 6vw, 3rem);
        font-weight: 900;
        line-height: 1.1;
        margin: 0;
    }

    .theme-contact-copy {
        color: var(--theme-text, #64748b);
        font-size: 1rem;
        line-height: 1.65;
        margin: 14px 0 0;
        max-width: 680px;
    }

    .theme-contact-grid {
        display: grid;
        gap: 16px;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        margin-top: 18px;
    }

    .theme-contact-card {
        background: var(--theme-card-bg, #ffffff);
        border: 1px solid color-mix(in srgb, var(--theme-primary, #0f766e) 14%, #e2e8f0);
        border-radius: 16px;
        color: inherit;
        display: block;
        height: 100%;
        padding: 20px;
        text-decoration: none;
    }

    .theme-contact-icon {
        align-items: center;
        background: var(--theme-primary, #0f766e);
        border-radius: 14px;
        color: var(--theme-button-text, #ffffff);
        display: inline-flex;
        font-size: 1.35rem;
        height: 50px;
        justify-content: center;
        margin-bottom: 16px;
        width: 50px;
    }

    .theme-contact-label {
        color: var(--theme-heading, #0f172a);
        font-size: 1rem;
        font-weight: 850;
        margin-bottom: 8px;
    }

    .theme-contact-value {
        color: var(--theme-text, #64748b);
        line-height: 1.6;
        margin: 0;
        overflow-wrap: anywhere;
    }

    .theme-contact-card[href]:hover .theme-contact-value {
        color: var(--theme-primary, #0f766e);
    }

    @media (max-width: 991.98px) {
        .theme-contact-grid {
            grid-template-columns: 1fr;
        }
    }
</style>

<section class="theme-contact-shell">
    <div class="container">
        <div class="theme-contact-hero">
            <div class="theme-contact-kicker">{{ __('website.contact') }}</div>
            <h1 class="theme-contact-title">Contact {{ $organizationName }}</h1>
            <p class="theme-contact-copy">{{ __('ui.contact_support_copy') }}</p>
        </div>

        @if($contactItems->isNotEmpty())
            <div class="theme-contact-grid">
                @foreach($contactItems as $item)
                    @if(!empty($item['href']))
                        <a class="theme-contact-card" href="{{ $item['href'] }}">
                            <span class="theme-contact-icon">
                                <i class="{{ $item['icon'] }}"></i>
                            </span>
                            <div class="theme-contact-label">{{ $item['label'] }}</div>
                            <p class="theme-contact-value">{{ $item['value'] }}</p>
                        </a>
                    @else
                        <div class="theme-contact-card">
                            <span class="theme-contact-icon">
                                <i class="{{ $item['icon'] }}"></i>
                            </span>
                            <div class="theme-contact-label">{{ $item['label'] }}</div>
                            <p class="theme-contact-value">{{ $item['value'] }}</p>
                        </div>
                    @endif
                @endforeach
            </div>
        @endif
    </div>
</section>
@endsection

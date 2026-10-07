@php
    $brandConfiguration = $brandConfiguration ?? ($configuration ?? (function_exists('getConfiguration') ? getConfiguration() : null));
    $brandName = $brandName ?? ($brandConfiguration->brand_fallback_name ?: ($brandConfiguration->name ?? config('app.name', 'ExamElite')));
    $brandHref = $brandHref ?? url('/');
    $brandLogo = $brandLogo ?? ($brandConfiguration->logo ?? null);
    $brandClass = $brandClass ?? '';
    $brandTagline = $brandTagline ?? ($brandConfiguration->brand_fallback_tagline ?: 'Online Exams Platform');
    $brandIcon = $brandIcon ?? ($brandConfiguration->brand_fallback_icon ?: 'ri-graduation-cap-line');
@endphp

@once
    <style>
        .examelite-brand-card {
            align-items: center;
            background: color-mix(in srgb, var(--el-primary, var(--theme-primary, #0f766e)) 8%, #ffffff);
            border: 1px solid color-mix(in srgb, var(--el-primary, var(--theme-primary, #0f766e)) 16%, transparent);
            border-radius: 14px;
            color: var(--el-text, var(--theme-heading, #0f172a));
            display: inline-flex;
            gap: 10px;
            max-width: 100%;
            min-height: 52px;
            padding: 7px 10px;
            text-decoration: none;
        }

        .examelite-brand-card:hover,
        .examelite-brand-card:focus {
            color: var(--el-text, var(--theme-heading, #0f172a));
            text-decoration: none;
        }

        .examelite-brand-card__image {
            max-height: 42px;
            max-width: 190px;
            object-fit: contain;
            width: auto;
        }

        .examelite-brand-card__mark {
            align-items: center;
            background: var(--el-primary, var(--theme-primary, #0f766e));
            border-radius: 12px;
            color: var(--theme-button-text, #ffffff);
            display: inline-flex;
            flex: 0 0 42px;
            font-size: 22px;
            height: 42px;
            justify-content: center;
            width: 42px;
        }

        .examelite-brand-card__text {
            display: inline-flex;
            flex-direction: column;
            font-weight: 900;
            line-height: 1;
            min-width: 0;
        }

        .examelite-brand-card__name {
            color: var(--el-text, var(--theme-heading, #0f172a));
            font-size: 1.24rem;
            max-width: 190px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .examelite-brand-card__tagline {
            color: var(--el-primary, var(--theme-primary, #0f766e));
            font-size: 9px;
            font-weight: 900;
            letter-spacing: .08em;
            margin-top: 5px;
            text-transform: uppercase;
            white-space: nowrap;
        }

        .examelite-brand-card.is-sidebar {
            background: transparent;
            border-color: transparent;
            justify-content: center;
            min-height: 58px;
            width: 100%;
        }

        .examelite-brand-card.is-sidebar .examelite-brand-card__name {
            font-size: 1.08rem;
            max-width: 150px;
        }

        .examelite-brand-card.is-exam {
            background: transparent;
            border-color: transparent;
            min-height: 44px;
            padding: 0;
        }

        .examelite-brand-card.is-exam .examelite-brand-card__image {
            max-height: 40px;
            max-width: 170px;
        }

        .examelite-brand-card.is-exam .examelite-brand-card__mark {
            flex-basis: 38px;
            font-size: 20px;
            height: 38px;
            width: 38px;
        }

        .examelite-brand-card.is-exam .examelite-brand-card__name {
            font-size: 1.05rem;
            max-width: 140px;
        }
    </style>
@endonce

<a href="{{ $brandHref }}" class="examelite-brand-card {{ $brandClass }}" aria-label="{{ $brandName }}">
    @if(!empty($brandLogo))
        <img src="{{ asset('storage/' . $brandLogo) }}" class="examelite-brand-card__image" alt="{{ $brandName }}">
    @else
        <span class="examelite-brand-card__mark"><i class="{{ $brandIcon }}"></i></span>
        <span class="examelite-brand-card__text">
            <span class="examelite-brand-card__name">{{ $brandName }}</span>
            <small class="examelite-brand-card__tagline">{{ $brandTagline }}</small>
        </span>
    @endif
</a>

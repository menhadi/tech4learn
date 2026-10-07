@php
    $partnerPopupDefaults = [
        'enabled' => true,
        'scope' => 'all_pages',
        'delay_seconds' => 60,
        'repeat_days' => 7,
        'eyebrow' => 'Free in-person support',
        'title' => 'Classroom coaching in Bengaluru',
        'message' => 'Vector Academy provides free competitive-exam coaching for students from financially disadvantaged backgrounds.',
        'button_label' => 'Explore Vector Academy',
        'url' => 'https://vectoracademy.net/',
        'position' => 'bottom_right',
        'accent_color' => '#0f7f75',
        'background_color' => '#ffffff',
        'icon' => 'school',
        'open_new_tab' => false,
    ];
    $savedPartnerPopup = $configuration->partner_popup_settings ?? [];
    $partnerPopup = array_replace(
        $partnerPopupDefaults,
        is_array($savedPartnerPopup) ? $savedPartnerPopup : []
    );

    $partnerPopupEnabled = (bool) $partnerPopup['enabled'];
    $partnerPopupScope = in_array($partnerPopup['scope'], ['all_pages', 'homepage'], true)
        ? $partnerPopup['scope']
        : $partnerPopupDefaults['scope'];
    $partnerPopupIsHomepage = request()->routeIs('home') || request()->path() === '/';
    $partnerPopupShouldRender = $partnerPopupEnabled
        && ($partnerPopupScope === 'all_pages' || $partnerPopupIsHomepage);

    $partnerPopupDelay = max(0, min(600, (int) $partnerPopup['delay_seconds']));
    $partnerPopupRepeatDays = max(0, min(365, (int) $partnerPopup['repeat_days']));
    $partnerPopupPosition = in_array($partnerPopup['position'], ['bottom_right', 'bottom_left', 'center'], true)
        ? $partnerPopup['position']
        : $partnerPopupDefaults['position'];
    $partnerPopupIcons = [
        'school' => 'ri-school-line',
        'book' => 'ri-book-open-line',
        'heart' => 'ri-heart-3-line',
        'community' => 'ri-team-line',
    ];
    $partnerPopupIcon = $partnerPopupIcons[$partnerPopup['icon']] ?? $partnerPopupIcons['school'];
    $partnerPopupAccent = preg_match('/^#[0-9a-fA-F]{6}$/', (string) $partnerPopup['accent_color'])
        ? strtolower($partnerPopup['accent_color'])
        : $partnerPopupDefaults['accent_color'];
    $partnerPopupBackground = preg_match('/^#[0-9a-fA-F]{6}$/', (string) $partnerPopup['background_color'])
        ? strtolower($partnerPopup['background_color'])
        : $partnerPopupDefaults['background_color'];

    $backgroundRed = hexdec(substr($partnerPopupBackground, 1, 2));
    $backgroundGreen = hexdec(substr($partnerPopupBackground, 3, 2));
    $backgroundBlue = hexdec(substr($partnerPopupBackground, 5, 2));
    $backgroundBrightness = (($backgroundRed * 299) + ($backgroundGreen * 587) + ($backgroundBlue * 114)) / 1000;
    $partnerPopupHeadingColor = $backgroundBrightness < 145 ? '#f8fafc' : '#101828';
    $partnerPopupTextColor = $backgroundBrightness < 145 ? '#e2e8f0' : '#475467';

    $partnerPopupUrl = preg_match('#^https?://#i', (string) $partnerPopup['url'])
        ? $partnerPopup['url']
        : $partnerPopupDefaults['url'];
    $partnerPopupOpenNewTab = (bool) $partnerPopup['open_new_tab'];
    $partnerPopupFingerprint = substr(sha1(json_encode($partnerPopup)), 0, 12);
@endphp

@if($partnerPopupShouldRender)
<aside
    class="vector-academy-popup vector-academy-popup--{{ str_replace('_', '-', $partnerPopupPosition) }}"
    id="vector-academy-popup"
    role="dialog"
    aria-modal="false"
    aria-live="polite"
    aria-labelledby="vector-academy-popup-title"
    aria-describedby="vector-academy-popup-description"
    aria-hidden="true"
    style="--partner-popup-accent: {{ $partnerPopupAccent }}; --partner-popup-background: {{ $partnerPopupBackground }}; --partner-popup-heading: {{ $partnerPopupHeadingColor }}; --partner-popup-text: {{ $partnerPopupTextColor }};"
    hidden
>
    <button
        class="vector-academy-popup__close"
        id="vector-academy-popup-close"
        type="button"
        aria-label="Close promotion message"
    >
        <i class="ri-close-line" aria-hidden="true"></i>
    </button>
    <div class="vector-academy-popup__icon" aria-hidden="true">
        <i class="{{ $partnerPopupIcon }}"></i>
    </div>
    <div class="vector-academy-popup__content">
        @if(trim((string) $partnerPopup['eyebrow']) !== '')
            <span class="vector-academy-popup__eyebrow">{{ $partnerPopup['eyebrow'] }}</span>
        @endif
        <h2 id="vector-academy-popup-title">{{ $partnerPopup['title'] }}</h2>
        <p id="vector-academy-popup-description">{{ $partnerPopup['message'] }}</p>
        <a
            class="vector-academy-popup__action"
            id="vector-academy-popup-action"
            href="{{ $partnerPopupUrl }}"
            target="{{ $partnerPopupOpenNewTab ? '_blank' : '_self' }}"
            rel="{{ $partnerPopupOpenNewTab ? 'external noopener noreferrer' : 'external' }}"
        >
            {{ $partnerPopup['button_label'] }}
            <i class="ri-arrow-right-line" aria-hidden="true"></i>
        </a>
    </div>
</aside>

<style>
    .vector-academy-popup {
        position: fixed;
        right: 24px;
        bottom: 84px;
        z-index: 1090;
        display: flex;
        width: min(410px, calc(100vw - 32px));
        gap: 14px;
        padding: 22px;
        border: 1px solid color-mix(in srgb, var(--partner-popup-accent) 24%, var(--partner-popup-background));
        border-radius: 20px;
        background: var(--partner-popup-background);
        box-shadow: 0 22px 55px rgba(15, 23, 42, 0.2);
        color: var(--partner-popup-text);
        opacity: 0;
        transform: translateY(18px) scale(0.98);
        transition: opacity 220ms ease, transform 220ms ease;
    }

    .vector-academy-popup[hidden] { display: none; }

    .vector-academy-popup.is-visible {
        opacity: 1;
        transform: translateY(0) scale(1);
    }

    .vector-academy-popup--bottom-left {
        right: auto;
        left: 24px;
    }

    .vector-academy-popup--center {
        top: 50%;
        right: auto;
        bottom: auto;
        left: 50%;
        transform: translate(-50%, calc(-50% + 18px)) scale(0.98);
    }

    .vector-academy-popup--center.is-visible {
        transform: translate(-50%, -50%) scale(1);
    }

    .vector-academy-popup__close {
        position: absolute;
        top: 10px;
        right: 10px;
        display: inline-flex;
        width: 34px;
        height: 34px;
        align-items: center;
        justify-content: center;
        border: 0;
        border-radius: 50%;
        background: transparent;
        color: var(--partner-popup-text);
        font-size: 22px;
        cursor: pointer;
    }

    .vector-academy-popup__close:hover,
    .vector-academy-popup__close:focus-visible {
        background: color-mix(in srgb, var(--partner-popup-text) 10%, transparent);
        color: var(--partner-popup-heading);
        outline: none;
    }

    .vector-academy-popup__icon {
        display: inline-flex;
        width: 48px;
        height: 48px;
        flex: 0 0 48px;
        align-items: center;
        justify-content: center;
        border-radius: 14px;
        background: color-mix(in srgb, var(--partner-popup-accent) 14%, var(--partner-popup-background));
        color: var(--partner-popup-accent);
        font-size: 25px;
    }

    .vector-academy-popup__content {
        min-width: 0;
        padding-right: 16px;
    }

    .vector-academy-popup__eyebrow {
        display: block;
        margin-bottom: 4px;
        color: var(--partner-popup-accent);
        font-size: 0.74rem;
        font-weight: 800;
        letter-spacing: 0.08em;
        line-height: 1.3;
        text-transform: uppercase;
    }

    .vector-academy-popup h2 {
        margin: 0 0 8px;
        color: var(--partner-popup-heading);
        font-size: 1.12rem;
        font-weight: 800;
        line-height: 1.3;
    }

    .vector-academy-popup p {
        margin: 0 0 14px;
        color: var(--partner-popup-text);
        font-size: 0.9rem;
        line-height: 1.55;
    }

    .vector-academy-popup__action {
        display: inline-flex;
        align-items: center;
        gap: 7px;
        border-radius: 10px;
        background: var(--partner-popup-accent);
        color: #ffffff !important;
        font-size: 0.88rem;
        font-weight: 700;
        padding: 10px 14px;
        text-decoration: none;
        transition: filter 160ms ease, transform 160ms ease;
    }

    .vector-academy-popup__action:hover,
    .vector-academy-popup__action:focus-visible {
        color: #ffffff !important;
        filter: brightness(0.92);
        outline: none;
        transform: translateY(-1px);
    }

    @media (max-width: 575.98px) {
        .vector-academy-popup {
            right: 16px;
            bottom: 76px;
            padding: 18px;
        }

        .vector-academy-popup--bottom-left {
            right: auto;
            left: 16px;
        }

        .vector-academy-popup--center {
            top: 50%;
            right: auto;
            bottom: auto;
            left: 50%;
        }

        .vector-academy-popup__icon {
            width: 42px;
            height: 42px;
            flex-basis: 42px;
            font-size: 22px;
        }
    }

    @media (prefers-reduced-motion: reduce) {
        .vector-academy-popup,
        .vector-academy-popup__action { transition: none; }
    }
</style>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        var popup = document.getElementById('vector-academy-popup');
        var closeButton = document.getElementById('vector-academy-popup-close');
        var actionLink = document.getElementById('vector-academy-popup-action');

        if (!popup || !closeButton || !actionLink) {
            return;
        }

        var storageKey = @json('examelite.partnerPopup.' . $partnerPopupFingerprint . '.dismissedAt');
        var displayDelay = @json($partnerPopupDelay * 1000);
        var repeatDays = @json($partnerPopupRepeatDays);
        var dismissalCooldown = repeatDays * 24 * 60 * 60 * 1000;
        var waitingForVisibility = false;
        var storage = null;
        var dismissedAt = 0;

        try {
            storage = repeatDays === 0 ? window.sessionStorage : window.localStorage;
            dismissedAt = Number(storage.getItem(storageKey) || 0);
        } catch (error) {
            storage = null;
        }

        if (dismissedAt && (repeatDays === 0 || Date.now() - dismissedAt < dismissalCooldown)) {
            return;
        }

        function rememberDismissal() {
            if (!storage) {
                return;
            }

            try {
                storage.setItem(storageKey, String(Date.now()));
            } catch (error) {
                // The popup still works when browser storage is unavailable.
            }
        }

        function showPopup() {
            if (document.hidden) {
                waitingForVisibility = true;
                return;
            }

            popup.hidden = false;
            popup.setAttribute('aria-hidden', 'false');
            window.requestAnimationFrame(function () {
                popup.classList.add('is-visible');
            });
        }

        function dismissPopup() {
            popup.classList.remove('is-visible');
            popup.setAttribute('aria-hidden', 'true');
            rememberDismissal();

            window.setTimeout(function () {
                popup.hidden = true;
            }, 220);
        }

        window.setTimeout(showPopup, displayDelay);

        document.addEventListener('visibilitychange', function () {
            if (!document.hidden && waitingForVisibility) {
                waitingForVisibility = false;
                showPopup();
            }
        });

        closeButton.addEventListener('click', dismissPopup);
        actionLink.addEventListener('click', rememberDismissal);

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && !popup.hidden) {
                dismissPopup();
            }
        });
    });
</script>
@endif

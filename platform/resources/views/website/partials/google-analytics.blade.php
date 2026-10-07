@php
    $analytics = app(\App\Services\GoogleAnalyticsSettings::class)->current(request()->getHost());
@endphp
@if($analytics['enabled'] && preg_match('/^G-[A-Z0-9]{4,20}$/D', $analytics['measurement_id']))
<script async src="https://www.googletagmanager.com/gtag/js?id={{ $analytics['measurement_id'] }}"></script>
<script>
window.dataLayer = window.dataLayer || [];
function gtag(){dataLayer.push(arguments);}
gtag('js', new Date());
gtag('config', @json($analytics['measurement_id']), {
    page_location: @json(request()->url()),
    page_referrer: document.referrer ? document.referrer.split('?')[0].split('#')[0] : '',
    allow_google_signals: false,
    allow_ad_personalization_signals: false
});
</script>
@endif

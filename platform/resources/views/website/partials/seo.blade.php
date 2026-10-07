@php
    $siteName = $configuration->name ?? config('app.name', 'ExamElite');
    $seoData = $seo ?? [];

    $sectionTitle = trim($__env->yieldContent('title', ''));
    $titleText = html_entity_decode($seoData['title'] ?? ($sectionTitle ?: $siteName), ENT_QUOTES, 'UTF-8');
    $siteName = html_entity_decode($siteName, ENT_QUOTES, 'UTF-8');
    $fullTitle = str_contains($titleText, $siteName) ? $titleText : $titleText . ' | ' . $siteName;

    $rawDescription = $seoData['description']
        ?? ($configuration->meta_content ?? 'Practice online exams, mock tests, previous year papers and scholarship tests.');
    $description = \Illuminate\Support\Str::limit(html_entity_decode(trim(strip_tags($rawDescription)), ENT_QUOTES, 'UTF-8'), 160, '');

    $keywords = $seoData['keywords'] ?? null;
    $canonical = $seoData['canonical'] ?? url()->current();
    $robots = $seoData['robots'] ?? 'index,follow';
    $type = $seoData['type'] ?? 'website';

    $image = $seoData['image'] ?? null;
    if (!empty($image) && !\Illuminate\Support\Str::startsWith($image, ['http://', 'https://'])) {
        $image = asset($image);
    }

    $schema = $seoData['schema'] ?? null;
    if (is_array($schema)) {
        $schema = json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
@endphp

<title>{{ $fullTitle }}</title>
<meta name="description" content="{{ $description }}">
@if(!empty($keywords))
    <meta name="keywords" content="{{ $keywords }}">
@endif
<meta name="robots" content="{{ $robots }}">
<link rel="canonical" href="{{ $canonical }}">

<meta property="og:title" content="{{ $seoData['og_title'] ?? $titleText }}">
<meta property="og:description" content="{{ $seoData['og_description'] ?? $description }}">
<meta property="og:type" content="{{ $type }}">
<meta property="og:url" content="{{ $canonical }}">
<meta property="og:site_name" content="{{ $siteName }}">
@if(!empty($image))
    <meta property="og:image" content="{{ $image }}">
@endif

<meta name="twitter:card" content="{{ !empty($image) ? 'summary_large_image' : 'summary' }}">
<meta name="twitter:title" content="{{ $seoData['og_title'] ?? $titleText }}">
<meta name="twitter:description" content="{{ $seoData['og_description'] ?? $description }}">
@if(!empty($image))
    <meta name="twitter:image" content="{{ $image }}">
@endif

@if(!empty($schema))
    <script type="application/ld+json">{!! $schema !!}</script>
@endif

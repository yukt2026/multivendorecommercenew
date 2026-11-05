@props([
    'meta' => null,
    'type' => 'website',
    'model' => null,
    'overrides' => [],
    'paginator' => null,
])

@php
    $seoService = app(\App\Services\Seo\SeoService::class);
    $meta = $meta ?? $seoService->resolveMetadata($type, $model, $overrides);
@endphp

<!-- Primary Meta Tags -->
<title>{{ $meta['title'] }}</title>
<meta name="title" content="{{ $meta['title'] }}">
<meta name="description" content="{{ $meta['description'] }}">
<meta name="robots" content="{{ $meta['robots'] }}">

<!-- Favicons -->
<link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
<link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon.png') }}">
<link rel="shortcut icon" href="{{ asset('favicon.ico') }}">

<!-- Canonical URL -->
<link rel="canonical" href="{{ $meta['canonical'] }}">

<!-- Pagination Rel Tags (Blueprint Section 32) -->
@if($paginator instanceof \Illuminate\Pagination\AbstractPaginator || $paginator instanceof \Illuminate\Pagination\LengthAwarePaginator)
    @if($paginator->currentPage() > 1)
        <link rel="prev" href="{{ $paginator->previousPageUrl() }}">
    @endif
    @if($paginator->hasMorePages())
        <link rel="next" href="{{ $paginator->nextPageUrl() }}">
    @endif
@endif

<!-- Open Graph / Facebook -->
<meta property="og:type" content="{{ $meta['og']['type'] ?? 'website' }}">
<meta property="og:site_name" content="{{ $meta['og']['site_name'] ?? config('app.name', 'OneStall') }}">
<meta property="og:url" content="{{ $meta['og']['url'] ?? $meta['canonical'] }}">
<meta property="og:title" content="{{ $meta['og']['title'] ?? $meta['title'] }}">
<meta property="og:description" content="{{ $meta['og']['description'] ?? $meta['description'] }}">
<meta property="og:image" content="{{ $meta['og']['image'] }}">

<!-- Twitter Card -->
<meta name="twitter:card" content="{{ $meta['twitter']['card'] ?? 'summary_large_image' }}">
<meta name="twitter:url" content="{{ $meta['og']['url'] ?? $meta['canonical'] }}">
<meta name="twitter:title" content="{{ $meta['twitter']['title'] ?? $meta['title'] }}">
<meta name="twitter:description" content="{{ $meta['twitter']['description'] ?? $meta['description'] }}">
<meta name="twitter:image" content="{{ $meta['twitter']['image'] ?? $meta['og']['image'] }}">

<!-- Webmaster & Search Console Verification -->
@if(!empty($meta['google_verification']))
    <meta name="google-site-verification" content="{{ $meta['google_verification'] }}">
@endif
@if(!empty($meta['bing_verification']))
    <meta name="msvalidate.01" content="{{ $meta['bing_verification'] }}">
@endif

<!-- JSON-LD Structured Data Schemas -->
@if(!empty($meta['jsonLd']))
    @foreach($meta['jsonLd'] as $schema)
        <script type="application/ld+json">
{!! json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) !!}
        </script>
    @endforeach
@endif

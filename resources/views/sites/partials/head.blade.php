{{-- Shared <head> of the public sites. Expects $site and $seo (App\Support\Seo). --}}
@php
    // Only the Material Symbols the themes use (sorted, as Google Fonts requires).
    $icons = collect([
        'arrow_back', 'arrow_forward', 'calendar_today', 'call', 'campaign', 'chat', 'chevron_right', 'close',
        'description', 'east', 'expand_more', 'location_on', 'mail', 'menu', 'menu_book', 'mosque',
        'music_note', 'person', 'photo_camera', 'play_circle', 'public', 'schedule', 'school', 'search',
        'star', 'upload', 'verified',
    ])->sort()->join(',');
    $favicon = $site->brandingUrl('favicon');
@endphp
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ $seo->title }}</title>
@if ($seo->description !== '')
    <meta name="description" content="{{ Str::limit($seo->description, 300) }}">
@endif
@if ($seo->noindex)
    <meta name="robots" content="noindex, nofollow">
@endif
<link rel="canonical" href="{{ $seo->canonical }}">
<meta property="og:site_name" content="{{ $site->name }}">
<meta property="og:type" content="{{ $seo->type }}">
<meta property="og:title" content="{{ $seo->title }}">
<meta property="og:url" content="{{ $seo->canonical }}">
@if ($seo->description !== '')
    <meta property="og:description" content="{{ Str::limit($seo->description, 300) }}">
@endif
@if ($seo->image !== '')
    <meta property="og:image" content="{{ $seo->image }}">
    <meta name="twitter:card" content="summary_large_image">
@endif
<meta name="theme-color" content="{{ $site->setting('theme.primary') }}">
@if ($favicon !== '')
    <link rel="icon" href="{{ $favicon }}">
@else
    <link rel="icon" href="/favicon.ico" sizes="any">
@endif

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Amiri:wght@400;700&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..24,400,0..1,0&icon_names={{ $icons }}&display=block">

@vite(['resources/css/sites/'.$site->key->value.'.css', 'resources/js/site.js'])

{{-- Brand colours from site settings override the theme defaults. --}}
<style>
    :root {
        --color-primary: {{ $site->setting('theme.primary') }};
        --color-primary-container: {{ $site->setting('theme.primary_container') }};
        --color-secondary: {{ $site->setting('theme.secondary') }};
    }
</style>

@if ($gaId = $site->setting('analytics.ga_measurement_id'))
    <script async src="https://www.googletagmanager.com/gtag/js?id={{ $gaId }}"></script>
    <script>
        window.dataLayer = window.dataLayer || [];
        function gtag() { dataLayer.push(arguments); }
        gtag('js', new Date());
        gtag('config', @js($gaId));
    </script>
@endif

{{-- Trusted snippets entered by a superadmin in site settings. --}}
{!! $site->setting('analytics.head_code') !!}

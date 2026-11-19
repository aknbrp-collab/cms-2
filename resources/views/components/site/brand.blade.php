@props([
    'site',
    'logoClass' => 'size-12 shrink-0 object-contain',
    'calligraphyClass' => 'h-10 w-auto max-w-[220px] object-contain',
    'fallbackIcon' => 'mosque',
    'fallbackIconClass' => 'flex size-12 shrink-0 items-center justify-center rounded-md bg-primary text-on-primary',
    'nameClass' => 'truncate text-xl font-bold text-brand',
])

@php
    $logo = $site->brandingUrl('logo');
    $logoDark = $site->brandingUrl('logo_dark');
    $calligraphy = $site->brandingUrl('calligraphy');
    $calligraphyDark = $site->brandingUrl('calligraphy_dark');
@endphp

{{-- Header brand: logo and name calligraphy (each with an optional dark-mode
     version). The site name stays for screen readers and only shows when
     neither a logo nor a calligraphy is uploaded. --}}
<a href="{{ route('site.home') }}" {{ $attributes->class('flex min-w-0 items-center gap-3') }}>
    <span class="sr-only">{{ $site->name }}</span>
    @if ($logo)
        <img src="{{ $logo }}" alt="" @class([$logoClass, 'dark:hidden' => $logoDark])>
        @if ($logoDark)
            <img src="{{ $logoDark }}" alt="" class="hidden {{ $logoClass }} dark:block">
        @endif
    @else
        <span class="{{ $fallbackIconClass }}"><x-site.icon :name="$fallbackIcon" class="text-2xl!" /></span>
    @endif
    @if ($calligraphy)
        <img src="{{ $calligraphy }}" alt="" @class([$calligraphyClass, 'dark:hidden' => $calligraphyDark])>
        @if ($calligraphyDark)
            <img src="{{ $calligraphyDark }}" alt="" class="hidden {{ $calligraphyClass }} dark:block">
        @endif
    @elseif ($calligraphyDark)
        {{-- Only a dark-mode version uploaded: it is made for dark backgrounds. --}}
        <img src="{{ $calligraphyDark }}" alt="" class="hidden {{ $calligraphyClass }} dark:block">
    @elseif (! $logo)
        {{-- Nothing uploaded yet: show the name so the header is not empty. --}}
        <span class="{{ $nameClass }}" aria-hidden="true">{{ $site->name }}</span>
    @endif
</a>

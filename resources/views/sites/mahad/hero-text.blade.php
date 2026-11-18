{{-- Text column of the Ma'had hero. Expects $title, $text, $buttonLabel, $buttonUrl and $heading (h1 or not). --}}
@php
    $hasCta = $site->setting('header.cta_url') && $site->setting('header.cta_label');
    $titleClass = 'mt-6 font-display text-[32px] leading-[38px] font-bold tracking-[-0.25px] text-balance text-on-primary md:text-5xl md:leading-[54px] md:tracking-[-0.5px]';
@endphp

@if ($heading)
    <h1 class="{{ $titleClass }}">{{ $title }}</h1>
@else
    <p class="{{ $titleClass }}">{{ $title }}</p>
@endif

@if ($text)
    <p class="mt-6 max-w-prose text-base leading-[26px] text-pretty text-inverse-on-surface">{{ $text }}</p>
@endif

<div class="mt-8 flex flex-wrap items-center gap-4">
    @if ($buttonLabel && $buttonUrl)
        <a href="{{ $buttonUrl }}" class="site-btn-accent py-3">
            <x-site.icon name="school" class="text-[18px]!" /> {{ $buttonLabel }}
        </a>
    @elseif ($hasCta)
        <a href="{{ $site->setting('header.cta_url') }}" class="site-btn-accent py-3">
            <x-site.icon name="school" class="text-[18px]!" /> {{ $site->setting('header.cta_label') }}
        </a>
    @endif
    <a href="{{ route('site.posts.index') }}" class="inline-flex items-center justify-center gap-2 rounded-[8px] border border-on-primary/40 bg-primary-container/60 px-6 py-3 text-sm font-bold text-on-primary transition-colors duration-150 hover:bg-primary-container">
        {{ __('Berita Akademik') }}
    </a>
</div>

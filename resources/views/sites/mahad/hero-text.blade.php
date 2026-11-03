{{-- Text column of the Ma'had hero. Expects $title, $text, $buttonLabel, $buttonUrl and $heading (h1 or not). --}}
@php
    $hasCta = $site->setting('header.cta_url') && $site->setting('header.cta_label');
    $titleClass = 'mt-5 text-4xl leading-tight font-bold tracking-tight drop-shadow-sm md:text-5xl';
@endphp

@if ($heading)
    <h1 class="{{ $titleClass }}">{{ $title }}</h1>
@else
    <p class="{{ $titleClass }}">{{ $title }}</p>
@endif

@if ($text)
    <p class="mt-5 max-w-2xl text-lg leading-relaxed text-on-primary/85">{{ $text }}</p>
@endif

<div class="mt-8 flex flex-wrap gap-3">
    @if ($buttonLabel && $buttonUrl)
        <a href="{{ $buttonUrl }}" class="inline-flex items-center gap-2 rounded-lg bg-secondary px-5 py-3 font-semibold text-on-secondary shadow-sm transition hover:brightness-110">
            {{ $buttonLabel }} <x-site.icon name="arrow_forward" class="text-[18px]!" />
        </a>
    @elseif ($hasCta)
        <a href="{{ $site->setting('header.cta_url') }}" class="inline-flex items-center gap-2 rounded-lg bg-secondary px-5 py-3 font-semibold text-on-secondary shadow-sm transition hover:brightness-110">
            {{ $site->setting('header.cta_label') }} <x-site.icon name="arrow_forward" class="text-[18px]!" />
        </a>
    @endif
    <a href="{{ route('site.posts.index') }}" class="inline-flex items-center gap-2 rounded-lg border border-on-primary/30 px-5 py-3 font-semibold transition hover:bg-on-primary/10">
        {{ __('Berita Akademik') }}
    </a>
</div>

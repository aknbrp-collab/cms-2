{{-- Homepage section title with a "more" link. Expects $title and $url. --}}
<div class="flex items-center justify-between gap-4">
    <h2 class="site-section-title">{{ $title }}</h2>
    <a href="{{ $url }}" class="site-link shrink-0">
        {{ __('Selengkapnya') }} <x-site.icon name="east" class="text-[16px]!" />
    </a>
</div>

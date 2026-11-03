{{-- Homepage section title with a "more" link. Expects $title and $url. --}}
<div class="flex items-center justify-between">
    <div class="flex items-center gap-3">
        <div class="h-7 w-2.5 rounded-full bg-primary"></div>
        <h2 class="text-xl font-bold tracking-tight text-on-surface uppercase md:text-2xl">{{ $title }}</h2>
    </div>
    <a href="{{ $url }}" class="inline-flex items-center gap-1 rounded-lg bg-surface-low px-3.5 py-1.5 text-sm font-semibold text-brand transition-colors hover:bg-surface-container">
        {{ __('Selengkapnya') }} <x-site.icon name="east" class="text-[16px]!" />
    </a>
</div>

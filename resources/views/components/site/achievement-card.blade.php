@props(['achievement'])

<article class="site-card site-card-hover flex h-full flex-col overflow-hidden">
    <div class="relative">
        <x-site.image :asset="$achievement->image" conversion="thumb" :alt="$achievement->title" icon="star" class="aspect-[16/10] w-full" />
        <span class="absolute start-3 top-3 site-chip shadow-sm">{{ $achievement->level->label() }}</span>
    </div>
    <div class="flex flex-1 flex-col gap-1 p-5">
        <h3 class="site-title text-lg! leading-snug">{{ $achievement->title }}</h3>
        <p class="text-sm font-medium text-brand">{{ $achievement->student_name }}</p>
        @if ($achievement->event_name)
            <p class="text-sm text-on-surface-variant">{{ $achievement->event_name }}</p>
        @endif
        <p class="site-meta mt-auto pt-2 text-xs!">{{ $achievement->achieved_on->translatedFormat('d F Y') }}</p>
    </div>
</article>

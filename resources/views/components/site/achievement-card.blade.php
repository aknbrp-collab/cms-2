@props(['achievement'])

<article class="flex flex-col overflow-hidden rounded-xl bg-surface-card shadow-sm">
    <div class="relative">
        <x-site.image :asset="$achievement->image" conversion="thumb" :alt="$achievement->title" icon="star" class="aspect-[16/10] w-full" />
        <span class="absolute start-3 top-3 rounded-full bg-secondary px-2.5 py-0.5 text-[11px] font-bold text-on-secondary shadow">{{ $achievement->level->label() }}</span>
    </div>
    <div class="flex flex-1 flex-col gap-1 p-5">
        <h3 class="leading-snug font-semibold text-on-surface">{{ $achievement->title }}</h3>
        <p class="text-sm font-medium text-brand">{{ $achievement->student_name }}</p>
        @if ($achievement->event_name)
            <p class="text-sm text-on-surface-variant">{{ $achievement->event_name }}</p>
        @endif
        <p class="mt-auto pt-2 text-xs text-muted">{{ $achievement->achieved_on->translatedFormat('d F Y') }}</p>
    </div>
</article>

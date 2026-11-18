@props(['publication'])

<article class="group site-card site-card-hover flex h-full flex-col gap-2 overflow-hidden">
    @if ($publication->image)
        <x-site.image :asset="$publication->image" conversion="thumb" :alt="$publication->image_caption ?: $publication->title" class="aspect-[16/9] w-full" />
    @endif
    <div class="flex flex-1 flex-col gap-2 p-6">
        <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
            <span class="site-eyebrow text-tertiary!">{{ $publication->type->label() }}</span>
            <span class="site-meta">{{ $publication->year }}</span>
            @if ($publication->journal)
                <span class="text-xs text-muted">· {{ $publication->journal->name }}</span>
            @endif
        </div>
        <h3 class="site-title transition-colors group-hover:text-brand">
            <a href="{{ route('site.publications.show', $publication->slug) }}">{{ $publication->title }}</a>
        </h3>
        <p class="text-sm font-bold text-brand">{{ $publication->authors }}</p>
        @if ($publication->abstract)
            <p class="line-clamp-3 text-sm leading-[22px] text-on-surface-variant">{{ $publication->abstract }}</p>
        @endif
    </div>
</article>

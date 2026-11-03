@props(['post', 'layout' => 'grid'])

{{-- Post teaser. Layouts: "grid" (image on top) or "row" (image left from md up). --}}
<article @class([
    'group overflow-hidden rounded-xl bg-surface-card shadow-sm transition hover:shadow-md',
    'flex flex-col' => $layout === 'grid',
    'flex flex-col gap-5 p-4 md:flex-row md:p-5' => $layout === 'row',
])>
    <a href="{{ route('site.posts.show', $post->slug) }}" tabindex="-1" @class([
        'relative block shrink-0 overflow-hidden bg-surface-container',
        'aspect-[16/10]' => $layout === 'grid',
        'h-44 rounded-lg md:w-56' => $layout === 'row',
    ])>
        <x-site.image :asset="$post->featuredImage" conversion="thumb" :alt="$post->title" class="h-full w-full transition duration-300 group-hover:scale-105" />
        @if ($post->category && ! $post->category->isHidden())
            <span class="absolute start-2.5 top-2.5 rounded-full bg-primary/90 px-2.5 py-0.5 text-[11px] font-bold tracking-wide text-on-primary">{{ $post->category->name }}</span>
        @endif
    </a>

    <div @class(['flex flex-1 flex-col gap-2', 'p-5' => $layout === 'grid', 'py-1' => $layout === 'row'])>
        <div class="flex items-center gap-1 text-xs font-medium text-muted">
            <x-site.icon name="calendar_today" class="text-[14px]!" />
            <time datetime="{{ $post->published_at?->toDateString() }}">{{ $post->published_at?->translatedFormat('d F Y') }}</time>
        </div>
        <h3 class="text-base leading-snug font-semibold text-on-surface transition group-hover:text-brand">
            <a href="{{ route('site.posts.show', $post->slug) }}">{{ $post->title }}</a>
        </h3>
        @if ($post->excerpt)
            <p class="line-clamp-2 text-sm text-on-surface-variant">{{ $post->excerpt }}</p>
        @endif
        <span class="mt-auto inline-flex items-center gap-1 pt-2 text-sm font-semibold text-brand">
            {{ __('Baca selengkapnya') }} <x-site.icon name="arrow_forward" class="text-[16px]! transition group-hover:translate-x-1" />
        </span>
    </div>
</article>

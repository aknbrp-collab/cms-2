@props(['post', 'layout' => 'grid'])

{{-- Post teaser, styled per theme. Layouts: "grid" (image on top) or "row" (image left from md up). --}}
<article @class([
    'group site-card site-card-hover overflow-hidden',
    'flex h-full flex-col' => $layout === 'grid',
    'flex flex-col gap-5 p-4 md:flex-row md:p-5' => $layout === 'row',
])>
    <a href="{{ route('site.posts.show', $post->slug) }}" tabindex="-1" @class([
        'relative block shrink-0 overflow-hidden bg-surface-container',
        'aspect-[16/10]' => $layout === 'grid',
        'aspect-[16/10] rounded-md md:w-60' => $layout === 'row',
    ])>
        <x-site.image :asset="$post->featuredImage" conversion="thumb" :alt="$post->title" class="h-full w-full transition duration-300 group-hover:scale-[1.03]" />
        @if ($post->category && ! $post->category->isHidden())
            <span class="site-chip absolute start-3 top-3 shadow-sm">{{ $post->category->name }}</span>
        @endif
    </a>

    <div @class(['flex flex-1 flex-col gap-2', 'p-6' => $layout === 'grid', 'py-1' => $layout === 'row'])>
        <time datetime="{{ $post->published_at?->toDateString() }}" class="site-meta">{{ $post->published_at?->translatedFormat('d F Y') }}</time>
        <h3 class="site-title transition-colors group-hover:text-brand">
            <a href="{{ route('site.posts.show', $post->slug) }}">{{ $post->title }}</a>
        </h3>
        @if ($post->excerpt)
            <p class="line-clamp-3 text-sm leading-[22px] text-on-surface-variant">{{ $post->excerpt }}</p>
        @endif
        <span class="site-link mt-auto pt-2">
            {{ __('Baca selengkapnya') }} <x-site.icon name="arrow_forward" class="text-[16px]! transition group-hover:translate-x-1" />
        </span>
    </div>
</article>

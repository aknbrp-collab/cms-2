@php
    $featured = $posts->onFirstPage() ? $posts->first() : null;
    $rest = $featured ? $posts->getCollection()->skip(1) : $posts->getCollection();
@endphp

<x-site.layout :site="$site" :seo="$seo">
    <x-site.page-header :title="$title" :description="$description ?? null" />

    <div class="mx-auto flex max-w-7xl flex-col gap-10 px-4 py-10 sm:px-6 md:py-14 lg:px-10">
        @if ($posts->isEmpty())
            <p class="site-card p-10 text-center text-on-surface-variant">{{ __('Belum ada tulisan.') }}</p>
        @else
            {{-- Newest post as a wide lead card on the first page. --}}
            @if ($featured)
                <article class="group site-card site-card-hover grid overflow-hidden lg:grid-cols-12">
                    <a href="{{ route('site.posts.show', $featured->slug) }}" tabindex="-1" class="relative block aspect-[16/10] overflow-hidden bg-surface-container lg:col-span-7 lg:aspect-auto lg:min-h-80">
                        <x-site.image :asset="$featured->featuredImage" :alt="$featured->title" class="absolute inset-0 h-full w-full transition duration-300 group-hover:scale-[1.03]" />
                    </a>
                    <div class="flex flex-col gap-3 p-6 md:p-8 lg:col-span-5 lg:justify-center">
                        <div class="flex flex-wrap items-center gap-3">
                            @if ($featured->category && ! $featured->category->isHidden())
                                <span class="site-chip">{{ $featured->category->name }}</span>
                            @endif
                            <time datetime="{{ $featured->published_at?->toDateString() }}" class="site-meta">{{ $featured->published_at?->translatedFormat('d F Y') }}</time>
                        </div>
                        <h2 class="site-heading text-balance transition-colors group-hover:text-brand">
                            <a href="{{ route('site.posts.show', $featured->slug) }}">{{ $featured->title }}</a>
                        </h2>
                        @if ($featured->excerpt)
                            <p class="line-clamp-4 text-base leading-[26px] text-on-surface-variant">{{ $featured->excerpt }}</p>
                        @endif
                        <span class="site-link pt-2">{{ __('Baca selengkapnya') }} <x-site.icon name="arrow_forward" class="text-[16px]! transition group-hover:translate-x-1" /></span>
                    </div>
                </article>
            @endif

            @if ($rest->isNotEmpty())
                <div class="grid items-stretch gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($rest as $post)
                        <x-site.post-card :post="$post" />
                    @endforeach
                </div>
            @endif

            <div>
                {{ $posts->links() }}
            </div>
        @endif
    </div>
</x-site.layout>

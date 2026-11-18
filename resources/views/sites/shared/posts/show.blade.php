@php
    $isAnnouncement = $post->type === App\Enums\PostType::Announcement;
    $showCategory = $post->category && ! $post->category->isHidden();
    $hasCta = $site->setting('header.cta_url') && $site->setting('header.cta_label');
@endphp

<x-site.layout :site="$site" :seo="$seo">
    <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 md:py-12 lg:px-10">
        {{-- Back link and breadcrumb. --}}
        <nav aria-label="Breadcrumb" class="mb-6 flex flex-wrap items-center gap-1.5 text-xs font-bold text-on-surface-variant">
            <a href="{{ $isAnnouncement ? route('site.announcements') : route('site.posts.index') }}" class="me-3 inline-flex items-center gap-1 text-brand hover:underline">
                <x-site.icon name="arrow_back" class="text-[16px]!" /> {{ $isAnnouncement ? __('Pengumuman') : __('Berita') }}
            </a>
            <a href="{{ route('site.home') }}" class="hover:text-brand">{{ __('Beranda') }}</a>
            <span aria-hidden="true">/</span>
            @if ($isAnnouncement)
                <a href="{{ route('site.announcements') }}" class="hover:text-brand">{{ __('Pengumuman') }}</a>
            @else
                <a href="{{ route('site.posts.index') }}" class="hover:text-brand">{{ __('Berita') }}</a>
            @endif
            @if ($showCategory)
                <span aria-hidden="true">/</span>
                <a href="{{ route('site.categories.show', $post->category->slug) }}" class="hover:text-brand">{{ $post->category->name }}</a>
            @endif
        </nav>

        <div class="grid items-start gap-8 lg:grid-cols-12">
            <article class="site-card site-accent-top overflow-hidden lg:col-span-8">
                <header class="flex flex-col gap-4 p-6 md:p-10 md:pb-6">
                    <div class="flex flex-wrap items-center gap-3">
                        @if ($showCategory)
                            <span class="site-chip">{{ $post->category->name }}</span>
                        @endif
                        <span class="site-meta inline-flex items-center gap-1.5">
                            <x-site.icon name="calendar_today" class="text-[15px]!" />
                            <time datetime="{{ $post->published_at?->toIso8601String() }}">{{ $post->published_at?->translatedFormat('l, d F Y') }}</time>
                        </span>
                    </div>
                    <h1 class="site-heading text-balance md:text-[40px]! md:leading-[48px]!">{{ $post->title }}</h1>
                    @if ($post->author)
                        <div class="flex items-center gap-3 border-t border-border-subtle pt-4">
                            <span class="flex size-10 items-center justify-center rounded-full bg-surface-low text-brand"><x-site.icon name="person" /></span>
                            <span class="text-sm font-bold text-on-surface">{{ $post->author->name }}</span>
                        </div>
                    @endif
                </header>

                @if ($post->featuredImage)
                    <figure class="px-6 md:px-10">
                        <x-site.image :asset="$post->featuredImage" :alt="$post->title" class="aspect-[16/9] w-full rounded-md" />
                        @if ($post->featuredImage->caption)
                            <figcaption class="mt-2 text-sm text-muted italic">{{ $post->featuredImage->caption }}</figcaption>
                        @endif
                    </figure>
                @endif

                <div class="p-6 md:p-10">
                    @if ($post->excerpt)
                        <p class="mb-6 text-lg leading-relaxed font-medium text-on-surface">{{ $post->excerpt }}</p>
                    @endif

                    {{-- Sanitised by App\Support\RichText when saved. --}}
                    <div class="prose-site">{!! $post->body !!}</div>

                    <div class="mt-10 flex flex-wrap items-center gap-2 border-t border-border-subtle pt-6 text-sm">
                        <span class="font-semibold text-on-surface">{{ __('Bagikan:') }}</span>
                        <a class="rounded-full border border-border-subtle px-3.5 py-1.5 font-semibold text-brand transition-colors hover:border-brand hover:bg-surface-low" target="_blank" rel="noopener" href="https://wa.me/?text={{ urlencode($post->title.' '.url()->current()) }}">WhatsApp</a>
                        <a class="rounded-full border border-border-subtle px-3.5 py-1.5 font-semibold text-brand transition-colors hover:border-brand hover:bg-surface-low" target="_blank" rel="noopener" href="https://www.facebook.com/sharer/sharer.php?u={{ urlencode(url()->current()) }}">Facebook</a>
                        <a class="rounded-full border border-border-subtle px-3.5 py-1.5 font-semibold text-brand transition-colors hover:border-brand hover:bg-surface-low" target="_blank" rel="noopener" href="https://x.com/intent/post?url={{ urlencode(url()->current()) }}&text={{ urlencode($post->title) }}">X</a>
                    </div>
                </div>
            </article>

            <aside class="flex flex-col gap-6 lg:sticky lg:top-28 lg:col-span-4">
                @if ($related->isNotEmpty())
                    <section class="site-card p-6">
                        <h2 class="site-section-title mb-4">{{ __('Baca juga') }}</h2>
                        <ul class="flex flex-col divide-y divide-border-subtle">
                            @foreach ($related as $item)
                                <li>
                                    <a href="{{ route('site.posts.show', $item->slug) }}" class="group flex gap-4 py-4 first:pt-0 last:pb-0">
                                        <x-site.image :asset="$item->featuredImage" conversion="thumb" :alt="$item->title" class="size-20 shrink-0 rounded-md" />
                                        <span class="flex min-w-0 flex-col gap-1">
                                            <time datetime="{{ $item->published_at?->toDateString() }}" class="site-meta text-xs!">{{ $item->published_at?->translatedFormat('d M Y') }}</time>
                                            <span class="line-clamp-3 text-sm leading-snug font-bold text-on-surface group-hover:text-brand">{{ $item->title }}</span>
                                        </span>
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </section>
                @endif

                @if ($hasCta)
                    <section class="rounded-lg bg-primary p-6 text-on-primary">
                        @if ($site->setting('header.announcement'))
                            <p class="text-lg leading-snug font-bold">{{ $site->setting('header.announcement') }}</p>
                        @endif
                        @if ($site->setting('contact.whatsapp'))
                            <p class="mt-2 text-sm opacity-80">{{ __('Helpdesk WhatsApp: :number', ['number' => $site->setting('contact.whatsapp')]) }}</p>
                        @endif
                        <a href="{{ $site->setting('header.cta_url') }}" class="site-btn-accent mt-5 w-full">{{ $site->setting('header.cta_label') }} <x-site.icon name="arrow_forward" class="text-[18px]!" /></a>
                    </section>
                @endif
            </aside>
        </div>
    </div>
</x-site.layout>

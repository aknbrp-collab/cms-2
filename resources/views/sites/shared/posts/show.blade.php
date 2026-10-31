<x-site.layout :site="$site" :seo="$seo">
    <article>
        <header class="bg-primary text-on-primary">
            <div class="mx-auto max-w-4xl px-4 py-10 sm:px-6 md:py-14">
                <nav aria-label="Breadcrumb" class="mb-4 flex flex-wrap items-center gap-1 text-sm text-on-primary/70">
                    <a href="{{ route('site.home') }}" class="hover:text-on-primary">{{ __('Beranda') }}</a>
                    <x-site.icon name="chevron_right" class="text-[16px]!" />
                    @if ($post->type === App\Enums\PostType::Announcement)
                        <a href="{{ route('site.announcements') }}" class="hover:text-on-primary">{{ __('Pengumuman') }}</a>
                    @else
                        <a href="{{ route('site.posts.index') }}" class="hover:text-on-primary">{{ __('Berita') }}</a>
                    @endif
                    @if ($post->category)
                        <x-site.icon name="chevron_right" class="text-[16px]!" />
                        <a href="{{ route('site.categories.show', $post->category->slug) }}" class="hover:text-on-primary">{{ $post->category->name }}</a>
                    @endif
                </nav>
                <h1 class="text-3xl leading-tight font-bold tracking-tight md:text-4xl">{{ $post->title }}</h1>
                <div class="mt-4 flex flex-wrap items-center gap-x-4 gap-y-1 text-sm text-on-primary/80">
                    <span class="inline-flex items-center gap-1">
                        <x-site.icon name="calendar_today" class="text-[16px]!" />
                        <time datetime="{{ $post->published_at?->toIso8601String() }}">{{ $post->published_at?->translatedFormat('l, d F Y') }}</time>
                    </span>
                    @if ($post->author)
                        <span class="inline-flex items-center gap-1"><x-site.icon name="person" class="text-[16px]!" /> {{ $post->author->name }}</span>
                    @endif
                </div>
            </div>
        </header>

        <div class="mx-auto max-w-4xl px-4 py-10 sm:px-6">
            @if ($post->featuredImage)
                <figure class="-mt-20 mb-8 overflow-hidden rounded-2xl shadow-lg md:-mt-24">
                    <x-site.image :asset="$post->featuredImage" :alt="$post->title" class="aspect-[16/9] w-full" />
                    @if ($post->featuredImage->caption)
                        <figcaption class="bg-surface-card px-4 py-2 text-sm text-muted">{{ $post->featuredImage->caption }}</figcaption>
                    @endif
                </figure>
            @endif

            @if ($post->excerpt)
                <p class="mb-6 text-lg font-medium text-on-surface">{{ $post->excerpt }}</p>
            @endif

            {{-- Sanitised by App\Support\RichText when saved. --}}
            <div class="prose-site">{!! $post->body !!}</div>

            <div class="mt-10 flex flex-wrap items-center gap-2 border-t border-border-subtle pt-6 text-sm">
                <span class="font-semibold text-on-surface">{{ __('Bagikan:') }}</span>
                <a class="rounded-lg bg-surface-low px-3 py-1.5 text-primary hover:bg-surface-container" target="_blank" rel="noopener" href="https://wa.me/?text={{ urlencode($post->title.' '.url()->current()) }}">WhatsApp</a>
                <a class="rounded-lg bg-surface-low px-3 py-1.5 text-primary hover:bg-surface-container" target="_blank" rel="noopener" href="https://www.facebook.com/sharer/sharer.php?u={{ urlencode(url()->current()) }}">Facebook</a>
                <a class="rounded-lg bg-surface-low px-3 py-1.5 text-primary hover:bg-surface-container" target="_blank" rel="noopener" href="https://x.com/intent/post?url={{ urlencode(url()->current()) }}&text={{ urlencode($post->title) }}">X</a>
            </div>
        </div>
    </article>

    @if ($related->isNotEmpty())
        <section class="bg-surface-low">
            <div class="mx-auto max-w-7xl px-4 py-12 sm:px-6 lg:px-10">
                <h2 class="mb-6 text-2xl font-bold text-on-surface">{{ __('Baca juga') }}</h2>
                <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($related as $item)
                        <x-site.post-card :post="$item" />
                    @endforeach
                </div>
            </div>
        </section>
    @endif
</x-site.layout>

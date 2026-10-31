<x-site.layout :site="$site" :seo="$seo">
    <h1 class="sr-only">{{ $site->name }}</h1>

    @if ($ticker = $announcements->first())
        <section class="border-b border-border-subtle bg-surface-card py-2.5 shadow-sm">
            <div class="mx-auto flex max-w-7xl items-center justify-between gap-4 px-4 sm:px-6 lg:px-10">
                <div class="flex min-w-0 items-center gap-3">
                    <span class="inline-flex shrink-0 items-center gap-1 rounded-md bg-primary px-2.5 py-1 text-xs font-bold text-on-primary">
                        <x-site.icon name="campaign" class="text-[16px]!" /> {{ __('Warta Terkini') }}
                    </span>
                    <a href="{{ route('site.posts.show', $ticker->slug) }}" class="truncate text-sm font-medium text-on-surface hover:text-primary">{{ $ticker->title }}</a>
                </div>
                <span class="hidden shrink-0 items-center gap-1 text-sm text-on-surface-variant md:flex">
                    <x-site.icon name="calendar_today" class="text-[16px]!" /> {{ now()->translatedFormat('d F Y') }}
                </span>
            </div>
        </section>
    @endif

    <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 md:py-10 lg:px-10">
        <div class="grid items-start gap-8 lg:grid-cols-12">
            <div class="flex flex-col gap-10 lg:col-span-8">
                @if ($featured)
                    <article class="group overflow-hidden rounded-xl bg-surface-card shadow-md transition hover:shadow-xl">
                        <a href="{{ route('site.posts.show', $featured->slug) }}" class="relative block aspect-[16/9] overflow-hidden bg-surface-container md:aspect-[16/8]">
                            <x-site.image :asset="$featured->featuredImage" :alt="$featured->title" class="h-full w-full transition duration-500 group-hover:scale-105" />
                            <div class="absolute inset-0 bg-gradient-to-t from-black/80 via-black/30 to-transparent"></div>
                            @if ($featured->category)
                                <span class="absolute start-4 top-4 rounded-full bg-primary-container px-3 py-1 text-xs font-bold text-on-primary shadow-md">{{ $featured->category->name }}</span>
                            @endif
                            <div class="absolute inset-x-0 bottom-0 flex flex-col gap-2 p-5 text-white md:p-6">
                                <span class="flex items-center gap-1 text-xs text-white/90"><x-site.icon name="schedule" class="text-[15px]!" /> {{ $featured->published_at?->translatedFormat('l, d F Y') }}</span>
                                <h2 class="text-2xl leading-tight font-bold drop-shadow-sm transition group-hover:text-gold-light md:text-3xl">{{ $featured->title }}</h2>
                            </div>
                        </a>
                        @if ($featured->excerpt)
                            <div class="flex flex-col gap-4 p-6 md:p-7">
                                <p class="line-clamp-3 leading-relaxed text-on-surface-variant">{{ $featured->excerpt }}</p>
                                <a href="{{ route('site.posts.show', $featured->slug) }}" class="inline-flex w-fit items-center gap-2 rounded-lg bg-primary px-5 py-2.5 text-sm font-semibold text-on-primary shadow-sm transition hover:bg-deep">
                                    {{ __('Baca Selengkapnya') }} <x-site.icon name="arrow_forward" class="text-[16px]!" />
                                </a>
                            </div>
                        @endif
                    </article>
                @endif

                @php $rest = $latest->reject(fn ($post) => $featured && $post->is($featured))->take(4); @endphp
                @if ($rest->isNotEmpty())
                    <section class="flex flex-col gap-6">
                        @include('sites.pondok.section-heading', ['title' => __('Artikel Terkini'), 'url' => route('site.posts.index')])
                        <div class="grid gap-5">
                            @foreach ($rest as $post)
                                <x-site.post-card :post="$post" layout="row" />
                            @endforeach
                        </div>
                    </section>
                @endif

                @foreach ($homeCategories as $category)
                    <section class="flex flex-col gap-6">
                        @include('sites.pondok.section-heading', ['title' => $category->name, 'url' => route('site.categories.show', $category->slug)])
                        <div class="grid gap-5 sm:grid-cols-2">
                            @foreach ($category->posts as $post)
                                <x-site.post-card :post="$post" />
                            @endforeach
                        </div>
                    </section>
                @endforeach

                @if (! $featured)
                    <p class="rounded-xl bg-surface-card p-10 text-center text-on-surface-variant shadow-sm">{{ __('Belum ada berita yang diterbitkan.') }}</p>
                @endif
            </div>

            <aside class="flex flex-col gap-6 lg:sticky lg:top-28 lg:col-span-4">
                @if ($site->setting('header.cta_url') && $site->setting('header.cta_label'))
                    <div class="relative overflow-hidden rounded-xl bg-primary p-6 text-on-primary shadow-md">
                        <div class="absolute -end-8 -top-8 size-32 rounded-full bg-secondary/25"></div>
                        <x-site.icon name="school" class="relative text-4xl! text-secondary" />
                        <p class="relative mt-3 text-lg font-bold">{{ $site->setting('header.announcement') ?: $site->setting('header.cta_label') }}</p>
                        <a href="{{ $site->setting('header.cta_url') }}" class="relative mt-4 inline-flex items-center gap-2 rounded-lg bg-secondary px-4 py-2 text-sm font-bold text-on-secondary hover:brightness-110">
                            {{ $site->setting('header.cta_label') }} <x-site.icon name="arrow_forward" class="text-[16px]!" />
                        </a>
                    </div>
                @endif

                @if ($announcements->isNotEmpty())
                    <div class="rounded-xl bg-surface-card p-6 shadow-sm">
                        <h2 class="mb-4 flex items-center gap-2 font-bold text-on-surface"><x-site.icon name="campaign" class="text-primary" /> {{ __('Pengumuman') }}</h2>
                        <ul class="divide-y divide-border-subtle">
                            @foreach ($announcements as $announcement)
                                <li class="py-3 first:pt-0">
                                    <a href="{{ route('site.posts.show', $announcement->slug) }}" class="text-sm font-semibold text-on-surface hover:text-primary">{{ $announcement->title }}</a>
                                    <p class="mt-1 text-xs text-muted">{{ $announcement->published_at?->translatedFormat('d F Y') }}</p>
                                </li>
                            @endforeach
                        </ul>
                        <a href="{{ route('site.announcements') }}" class="mt-3 inline-flex items-center gap-1 text-sm font-semibold text-primary">{{ __('Semua pengumuman') }} <x-site.icon name="east" class="text-[16px]!" /></a>
                    </div>
                @endif

                @include('sites.shared.contact-card')
            </aside>
        </div>
    </div>
</x-site.layout>

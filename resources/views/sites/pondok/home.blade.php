<x-site.layout :site="$site" :seo="$seo">
    <h1 class="sr-only">{{ $site->name }}</h1>

    @if ($ticker = $announcements->first())
        <section class="border-b border-border-subtle bg-surface-card py-2.5 shadow-sm">
            <div class="mx-auto flex max-w-7xl items-center justify-between gap-4 px-4 sm:px-6 lg:px-10">
                <div class="flex min-w-0 items-center gap-3">
                    <span class="inline-flex shrink-0 items-center gap-1.5 rounded-full bg-primary px-3 py-1 text-xs font-bold tracking-wider text-on-primary uppercase">
                        <x-site.icon name="campaign" class="text-[16px]!" /> {{ __('Warta Terkini') }}
                    </span>
                    <a href="{{ route('site.posts.show', $ticker->slug) }}" class="truncate text-sm font-medium text-on-surface hover:text-brand">{{ $ticker->title }}</a>
                </div>
                <span class="site-meta hidden shrink-0 items-center gap-1 text-on-surface-variant! md:flex">
                    <x-site.icon name="calendar_today" class="text-[16px]!" /> {{ now()->translatedFormat('d F Y') }}
                </span>
            </div>
        </section>
    @endif

    <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 md:py-10 lg:px-10">
        <div class="grid items-start gap-8 lg:grid-cols-12">
            <div class="flex flex-col gap-10 lg:col-span-8">
                @if ($featured)
                    <article class="group site-card site-card-hover overflow-hidden rounded-xl! shadow-level-2">
                        <a href="{{ route('site.posts.show', $featured->slug) }}" class="relative block aspect-[16/9] overflow-hidden bg-surface-container md:aspect-[16/8]">
                            <x-site.image :asset="$featured->featuredImage" :alt="$featured->title" class="h-full w-full transition duration-500 group-hover:scale-105" />
                            <div class="absolute inset-0 bg-gradient-to-t from-black/80 via-black/30 to-transparent"></div>
                            @if ($featured->category && ! $featured->category->isHidden())
                                <span class="absolute start-4 top-4 rounded-full bg-primary-container px-3 py-1 text-xs font-bold text-on-primary shadow-md">{{ $featured->category->name }}</span>
                            @endif
                            <div class="absolute inset-x-0 bottom-0 flex flex-col gap-2 p-5 text-white md:p-6">
                                <span class="flex items-center gap-1 font-mono text-xs text-white/90 tabular-nums"><x-site.icon name="schedule" class="text-[15px]!" /> {{ $featured->published_at?->translatedFormat('l, d F Y') }}</span>
                                <h2 class="text-2xl leading-tight font-bold drop-shadow-sm transition group-hover:text-gold-light md:text-3xl">{{ $featured->title }}</h2>
                            </div>
                        </a>
                        @if ($featured->excerpt)
                            <div class="flex flex-col gap-4 p-6 md:p-7">
                                <p class="line-clamp-3 leading-relaxed text-on-surface-variant">{{ $featured->excerpt }}</p>
                                <a href="{{ route('site.posts.show', $featured->slug) }}" class="site-btn-primary w-fit shadow-sm">
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

                @if ($achievements->isNotEmpty())
                    <section class="flex flex-col gap-6">
                        @include('sites.pondok.section-heading', ['title' => __('Prestasi'), 'url' => route('site.achievements')])
                        <div class="grid gap-5 sm:grid-cols-2">
                            @foreach ($achievements as $achievement)
                                <x-site.achievement-card :achievement="$achievement" />
                            @endforeach
                        </div>
                    </section>
                @endif

                @if ($videos->isNotEmpty())
                    <section class="flex flex-col gap-6">
                        @include('sites.pondok.section-heading', ['title' => $videos->first()->gallery->title, 'url' => route('site.galleries.index', ['jenis' => 'video'])])
                        <div class="overflow-hidden rounded-xl bg-deep shadow-level-2">
                            <iframe src="{{ $videos->first()->embedUrl() }}" class="aspect-video w-full" loading="lazy" allowfullscreen referrerpolicy="strict-origin-when-cross-origin" title="{{ $videos->first()->caption ?: $videos->first()->gallery->title }}"></iframe>
                        </div>
                        @if ($videos->count() > 1)
                            <div class="grid gap-4 sm:grid-cols-2">
                                @foreach ($videos->skip(1) as $video)
                                    <a href="{{ route('site.galleries.show', $video->gallery->slug) }}" class="group site-card site-card-hover flex items-center gap-3 p-3">
                                        <span class="relative block w-32 shrink-0 overflow-hidden rounded-lg">
                                            <img src="{{ $video->videoThumbnail() }}" alt="" loading="lazy" class="aspect-video w-full object-cover">
                                            <span class="absolute inset-0 flex items-center justify-center"><x-site.icon name="play_circle" filled class="text-3xl! text-white drop-shadow" /></span>
                                        </span>
                                        <span class="text-sm font-semibold text-on-surface group-hover:text-brand">{{ $video->caption ?: $video->gallery->title }}</span>
                                    </a>
                                @endforeach
                            </div>
                        @endif
                    </section>
                @endif

                @if (! $featured)
                    <p class="site-card p-10 text-center text-on-surface-variant">{{ __('Belum ada berita yang diterbitkan.') }}</p>
                @endif
            </div>

            <aside class="flex flex-col gap-6 lg:sticky lg:top-28 lg:col-span-4">
                @if ($admission)
                    <div class="relative overflow-hidden rounded-xl bg-deep p-6 text-on-primary shadow-level-2">
                        <div class="absolute -end-8 -top-8 size-32 rounded-full bg-secondary/25"></div>
                        <x-site.icon name="school" class="relative text-4xl! text-secondary" />
                        <p class="relative mt-3 text-sm font-semibold text-gold-light">{{ __('Penerimaan :year', ['year' => $admission->academic_year]) }}</p>
                        <p class="relative mt-1 text-lg font-bold">{{ $admission->title }}</p>
                        <p class="relative mt-1 text-sm text-on-primary/80">{{ __('Dibuka hingga :date', ['date' => $admission->closes_on->translatedFormat('d F Y')]) }}</p>
                        <div class="relative mt-4 flex flex-wrap gap-2">
                            <a href="{{ $admission->acceptsRegistrations() ? route('site.admissions.register') : route('site.admissions.show') }}" class="site-btn-accent">
                                {{ $admission->acceptsRegistrations() ? __('Daftar Online') : __('Info Pendaftaran') }} <x-site.icon name="arrow_forward" class="text-[16px]!" />
                            </a>
                        </div>
                    </div>
                @elseif ($site->setting('header.cta_url') && $site->setting('header.cta_label'))
                    <div class="relative overflow-hidden rounded-xl bg-deep p-6 text-on-primary shadow-level-2">
                        <div class="absolute -end-8 -top-8 size-32 rounded-full bg-secondary/25"></div>
                        <x-site.icon name="school" class="relative text-4xl! text-secondary" />
                        <p class="relative mt-3 text-lg font-bold">{{ $site->setting('header.announcement') ?: $site->setting('header.cta_label') }}</p>
                        <a href="{{ $site->setting('header.cta_url') }}" class="site-btn-accent relative mt-4">
                            {{ $site->setting('header.cta_label') }} <x-site.icon name="arrow_forward" class="text-[16px]!" />
                        </a>
                    </div>
                @endif

                @if ($events->isNotEmpty())
                    <div class="site-card site-accent-gold p-6">
                        <h2 class="mb-4 flex items-center gap-2 text-lg font-bold text-on-surface"><x-site.icon name="calendar_today" class="text-brand" /> {{ __('Agenda') }}</h2>
                        <ul class="space-y-3">
                            @foreach ($events as $event)
                                <li>
                                    <a href="{{ route('site.events.show', $event->slug) }}" class="group flex gap-3">
                                        <span class="flex w-12 shrink-0 flex-col items-center rounded-lg bg-mint py-1.5 text-brand">
                                            <span class="font-mono text-lg leading-none font-bold tabular-nums">{{ $event->starts_at->format('d') }}</span>
                                            <span class="text-[11px] uppercase">{{ $event->starts_at->translatedFormat('M') }}</span>
                                        </span>
                                        <span class="text-sm font-semibold text-on-surface group-hover:text-brand">{{ $event->title }}</span>
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                        <a href="{{ route('site.events.index') }}" class="site-link mt-4">{{ __('Semua agenda') }} <x-site.icon name="east" class="text-[16px]!" /></a>
                    </div>
                @endif

                @if ($announcements->isNotEmpty())
                    <div class="site-card site-accent-top p-6">
                        <h2 class="mb-4 flex items-center gap-2 text-lg font-bold text-on-surface"><x-site.icon name="campaign" class="text-brand" /> {{ __('Pengumuman') }}</h2>
                        <ul class="divide-y divide-border-subtle">
                            @foreach ($announcements as $announcement)
                                <li class="py-3 first:pt-0">
                                    <a href="{{ route('site.posts.show', $announcement->slug) }}" class="text-sm font-semibold text-on-surface hover:text-brand">{{ $announcement->title }}</a>
                                    <p class="site-meta mt-1 text-xs!">{{ $announcement->published_at?->translatedFormat('d F Y') }}</p>
                                </li>
                            @endforeach
                        </ul>
                        <a href="{{ route('site.announcements') }}" class="site-link mt-4">{{ __('Semua pengumuman') }} <x-site.icon name="east" class="text-[16px]!" /></a>
                    </div>
                @endif

                @include('sites.shared.contact-card')
            </aside>
        </div>
    </div>
</x-site.layout>

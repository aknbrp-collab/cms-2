@php
    use App\Enums\MenuLocation;

    $quickLinks = app(App\Support\SiteTheme::class)->menu(MenuLocation::TopBar)->take(4);
    $quickIcons = ['menu_book', 'school', 'description', 'public'];
    $calligraphy = $site->brandingUrl('calligraphy');
    $hasCta = $site->setting('header.cta_url') && $site->setting('header.cta_label');
@endphp

<x-site.layout :site="$site" :seo="$seo">
    <section
        @class(['relative overflow-hidden bg-primary pt-12 pb-16 text-on-primary md:pt-16', 'lg:min-h-[34rem]' => $slides->isNotEmpty()])
        @if ($slides->count() > 1)
            aria-roledescription="carousel"
            aria-label="{{ __('Slide beranda') }}"
            x-data="{
                current: 0,
                count: {{ $slides->count() }},
                paused: false,
                timer: null,
                touchX: null,
                reduced: window.matchMedia('(prefers-reduced-motion: reduce)').matches,
                init() {
                    this.paused = this.reduced;
                    this.play();
                    document.addEventListener('visibilitychange', () => document.hidden ? this.halt() : this.play());
                },
                play() { this.halt(); if (! this.paused) this.timer = setInterval(() => this.next(), 6000); },
                halt() { clearInterval(this.timer); this.timer = null; },
                next() { this.current = (this.current + 1) % this.count; },
                prev() { this.current = (this.current - 1 + this.count) % this.count; },
                go(index) { this.current = index; this.play(); },
                togglePause() { this.paused = ! this.paused; this.play(); },
                swipe(endX) { if (this.touchX === null) return; const distance = endX - this.touchX; if (Math.abs(distance) > 40) { distance < 0 ? this.next() : this.prev(); this.play(); } this.touchX = null; },
            }"
            x-on:mouseenter="halt()"
            x-on:mouseleave="play()"
            x-on:focusin="halt()"
            x-on:focusout="play()"
            x-on:keydown.arrow-right="next()"
            x-on:keydown.arrow-left="prev()"
            x-on:touchstart.passive="touchX = $event.touches[0].clientX"
            x-on:touchend="swipe($event.changedTouches[0].clientX)"
        @endif
    >
        @if ($slides->isNotEmpty())
            {{-- Slide pictures behind a gradient that keeps the text on the left readable. --}}
            <div class="pointer-events-none absolute inset-0" aria-hidden="true">
                @foreach ($slides as $slide)
                    <img
                        src="{{ $slide->image->url('large') }}"
                        alt=""
                        @if ($loop->first) fetchpriority="high" @else loading="lazy" @endif
                        @if ($slides->count() > 1) x-bind:class="current === {{ $loop->index }} ? 'opacity-100' : 'opacity-0'" @endif
                        @class(['absolute inset-0 h-full w-full object-cover transition-opacity duration-1000 motion-reduce:transition-none', 'opacity-0' => ! $loop->first])
                    >
                @endforeach
                <div class="absolute inset-0 bg-gradient-to-r from-primary via-primary/85 to-primary/35"></div>
                <div class="absolute inset-0 bg-gradient-to-t from-primary/70 to-transparent lg:hidden"></div>
            </div>
        @else
            <div class="pointer-events-none absolute inset-0 opacity-[0.07]" style="background-image: radial-gradient(circle at 1px 1px, currentColor 1px, transparent 0); background-size: 22px 22px;"></div>
        @endif

        <div class="relative mx-auto grid max-w-7xl items-center gap-10 px-4 sm:px-6 lg:grid-cols-12 lg:px-10">
            <div class="lg:col-span-7">
                @if ($site->setting('identity.tagline'))
                    <span class="inline-flex items-center gap-2 rounded-full border border-gold-light/30 bg-primary-container/60 px-3 py-1 text-xs font-semibold text-gold-light">
                        <x-site.icon name="verified" class="text-[14px]!" /> {{ $site->setting('identity.tagline') }}
                    </span>
                @endif

                @if ($slides->isEmpty())
                    @include('sites.mahad.hero-text', ['title' => $site->name, 'text' => $site->setting('identity.description'), 'buttonLabel' => null, 'buttonUrl' => null, 'heading' => true])
                @else
                    <h1 class="sr-only">{{ $site->name }}</h1>
                    @foreach ($slides as $slide)
                        <div
                            @if ($slides->count() > 1)
                                x-show="current === {{ $loop->index }}"
                                @unless ($loop->first) x-cloak @endunless
                                role="group"
                                aria-roledescription="slide"
                                aria-label="{{ __(':n dari :total', ['n' => $loop->iteration, 'total' => $slides->count()]) }}"
                            @endif
                        >
                            @include('sites.mahad.hero-text', [
                                'title' => $slide->title ?: $site->name,
                                'text' => $slide->text ?: $site->setting('identity.description'),
                                'buttonLabel' => $slide->button_label,
                                'buttonUrl' => $slide->button_url,
                                'heading' => false,
                            ])
                        </div>
                    @endforeach
                @endif

                @if ($slides->count() > 1)
                    <div class="mt-10 flex items-center gap-3">
                        <button type="button" x-on:click="prev(); play()" class="flex size-9 items-center justify-center rounded-full border border-on-primary/30 transition hover:bg-on-primary/10" aria-label="{{ __('Slide sebelumnya') }}">
                            <x-site.icon name="chevron_left" class="text-[20px]!" />
                        </button>
                        <div class="flex items-center gap-2">
                            @foreach ($slides as $slide)
                                <button
                                    type="button"
                                    x-on:click="go({{ $loop->index }})"
                                    x-bind:aria-current="current === {{ $loop->index }}"
                                    x-bind:class="current === {{ $loop->index }} ? 'w-7 bg-gold-light' : 'w-2.5 bg-on-primary/40 hover:bg-on-primary/70'"
                                    @class(['h-2.5 rounded-full transition-all', 'w-7 bg-gold-light' => $loop->first, 'w-2.5 bg-on-primary/40' => ! $loop->first])
                                    aria-label="{{ __('Tampilkan slide :n', ['n' => $loop->iteration]) }}"
                                ></button>
                            @endforeach
                        </div>
                        <button type="button" x-on:click="next(); play()" class="flex size-9 items-center justify-center rounded-full border border-on-primary/30 transition hover:bg-on-primary/10" aria-label="{{ __('Slide berikutnya') }}">
                            <x-site.icon name="chevron_right" class="text-[20px]!" />
                        </button>
                        <button type="button" x-on:click="togglePause()" class="ms-1 flex size-9 items-center justify-center rounded-full transition hover:bg-on-primary/10" x-bind:aria-label="paused ? @js(__('Putar slide otomatis')) : @js(__('Jeda slide otomatis'))">
                            <x-site.icon name="pause" class="text-[20px]!" x-show="! paused" />
                            <x-site.icon name="play_arrow" class="text-[20px]!" x-show="paused" x-cloak />
                        </button>
                    </div>
                @endif
            </div>
            <div class="hidden justify-center lg:col-span-5 lg:flex">
                <div class="flex aspect-square w-full max-w-sm flex-col items-center justify-center gap-4 rounded-3xl border border-gold-light/20 bg-primary-container/50 p-10 text-center shadow-2xl">
                    @if ($calligraphy)
                        <img src="{{ $calligraphy }}" alt="{{ $site->name }}" class="max-h-48 w-auto object-contain">
                    @else
                        <p class="font-arabic text-4xl leading-relaxed text-gold-light" lang="ar" dir="rtl">تَفَقُّهٌ فِي الدِّينِ</p>
                    @endif
                    <p class="text-sm tracking-widest text-on-primary/70 uppercase">{{ $site->name }}</p>
                </div>
            </div>
        </div>
    </section>

    @if ($quickLinks->isNotEmpty())
        <section class="bg-surface-low py-10">
            <div class="mx-auto grid max-w-7xl gap-4 px-4 sm:grid-cols-2 sm:px-6 lg:grid-cols-4 lg:px-10">
                @foreach ($quickLinks as $link)
                    <a href="{{ $link->href() }}" @if ($link->open_in_new_tab) target="_blank" rel="noopener" @endif class="group flex items-center gap-4 rounded-xl border border-brand/10 bg-surface-card p-5 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md">
                        <span class="flex size-12 shrink-0 items-center justify-center rounded-xl bg-mint text-primary-container transition group-hover:bg-primary-container group-hover:text-on-primary">
                            <x-site.icon :name="$quickIcons[$loop->index] ?? 'east'" />
                        </span>
                        <span class="font-semibold text-on-surface">{{ $link->label }}</span>
                    </a>
                @endforeach
            </div>
        </section>
    @endif

    @if ($programs->isNotEmpty())
        <section class="py-16 md:py-20" id="takhassus">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-10">
                <div class="mb-10 text-center">
                    <p class="text-sm font-bold tracking-wider text-secondary uppercase">{{ __('Program Takhassus') }}</p>
                    <h2 class="mt-1 text-3xl font-bold text-brand">{{ __('Disiplin Keilmuan Pesantren Tinggi') }}</h2>
                </div>
                <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
                    @foreach ($programs as $program)
                        <x-site.program-card :program="$program" />
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    <section @class(['py-16 md:py-20', 'bg-surface-low' => $programs->isNotEmpty()])>
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-10">
            <div class="mb-10 flex flex-wrap items-end justify-between gap-4">
                <div>
                    <p class="text-sm font-bold tracking-wider text-secondary uppercase">{{ __('Kabar Kampus') }}</p>
                    <h2 class="mt-1 text-3xl font-bold text-brand">{{ __('Berita Akademik') }}</h2>
                </div>
                <a href="{{ route('site.posts.index') }}" class="inline-flex items-center gap-1 font-semibold text-brand hover:underline">{{ __('Semua berita') }} <x-site.icon name="east" class="text-[18px]!" /></a>
            </div>

            @if ($latest->isEmpty())
                <p class="rounded-xl bg-surface-card p-10 text-center text-on-surface-variant shadow-sm">{{ __('Belum ada berita yang diterbitkan.') }}</p>
            @else
                <div class="grid gap-6 lg:grid-cols-12">
                    <div class="grid gap-6 sm:grid-cols-2 lg:col-span-8">
                        @foreach ($latest->take(4) as $post)
                            <x-site.post-card :post="$post" />
                        @endforeach
                    </div>
                    <aside class="lg:col-span-4">
                        <div class="rounded-xl border border-brand/10 bg-surface-card p-6 shadow-sm">
                            <h3 class="mb-4 flex items-center gap-2 font-bold text-brand"><x-site.icon name="campaign" /> {{ __('Pengumuman') }}</h3>
                            @forelse ($announcements as $announcement)
                                <a href="{{ route('site.posts.show', $announcement->slug) }}" class="group flex gap-4 border-b border-border-subtle py-3 last:border-0">
                                    <span class="flex w-12 shrink-0 flex-col items-center rounded-lg bg-surface-low py-1.5 text-brand">
                                        <span class="text-lg leading-none font-bold">{{ $announcement->published_at?->format('d') }}</span>
                                        <span class="text-[11px] uppercase">{{ $announcement->published_at?->translatedFormat('M') }}</span>
                                    </span>
                                    <span class="text-sm font-semibold text-on-surface group-hover:text-brand">{{ $announcement->title }}</span>
                                </a>
                            @empty
                                <p class="text-sm text-muted">{{ __('Belum ada pengumuman.') }}</p>
                            @endforelse
                        </div>
                    </aside>
                </div>
            @endif
        </div>
    </section>

    @foreach ($homeCategories as $category)
        <section @class(['py-16', 'bg-surface-low' => $loop->odd])>
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-10">
                <div class="mb-8 flex flex-wrap items-end justify-between gap-4">
                    <h2 class="text-2xl font-bold text-brand">{{ $category->name }}</h2>
                    <a href="{{ route('site.categories.show', $category->slug) }}" class="inline-flex items-center gap-1 font-semibold text-brand hover:underline">{{ __('Selengkapnya') }} <x-site.icon name="east" class="text-[18px]!" /></a>
                </div>
                <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
                    @foreach ($category->posts as $post)
                        <x-site.post-card :post="$post" />
                    @endforeach
                </div>
            </div>
        </section>
    @endforeach

    @if ($publications->isNotEmpty())
        <section class="py-16 md:py-20">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-10">
                <div class="mb-10 flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <p class="text-sm font-bold tracking-wider text-secondary uppercase">{{ __('Publikasi & Riset Ilmiah') }}</p>
                        <h2 class="mt-1 text-3xl font-bold text-brand">{{ __('Bahtsul Masail, Jurnal & Repositori') }}</h2>
                    </div>
                    <a href="{{ route('site.publications.index') }}" class="inline-flex items-center gap-1 font-semibold text-brand hover:underline">{{ __('Kunjungi repositori') }} <x-site.icon name="east" class="text-[18px]!" /></a>
                </div>
                <div class="grid gap-5 lg:grid-cols-3">
                    @foreach ($publications as $publication)
                        <x-site.publication-item :publication="$publication" />
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    @if ($site->hasModule('legal_questions'))
        <section class="bg-surface-low py-16 md:py-20">
            <div class="mx-auto grid max-w-7xl gap-10 px-4 sm:px-6 lg:grid-cols-12 lg:px-10">
                <div class="lg:col-span-4">
                    <p class="text-sm font-bold tracking-wider text-secondary uppercase">{{ __('Konsultasi Hukum Islam') }}</p>
                    <h2 class="mt-1 text-3xl font-bold text-brand">{{ __('Tanya Hukum') }}</h2>
                    <p class="mt-4 text-on-surface-variant">{{ __('Ajukan pertanyaan seputar fiqh. Jawaban dikaji oleh dewan asatidz Ma\'had Aly.') }}</p>
                    <div class="mt-6 flex flex-wrap gap-3">
                        <a href="{{ route('site.legal-questions.create') }}" class="inline-flex items-center gap-2 rounded-lg bg-primary px-5 py-2.5 font-semibold text-on-primary hover:bg-primary-container">{{ __('Ajukan Pertanyaan') }}</a>
                        <a href="{{ route('site.legal-questions.index') }}" class="inline-flex items-center gap-1 px-2 py-2.5 font-semibold text-brand hover:underline">{{ __('Arsip jawaban') }} <x-site.icon name="east" class="text-[18px]!" /></a>
                    </div>
                </div>
                <div class="space-y-4 lg:col-span-8">
                    @forelse ($legalQuestions as $question)
                        <a href="{{ route('site.legal-questions.show', $question->slug) }}" class="block rounded-xl border border-brand/10 bg-surface-card p-5 shadow-sm transition hover:shadow-md">
                            <span class="rounded-full bg-mint px-2.5 py-0.5 text-xs font-bold text-brand">{{ $question->category->label() }}</span>
                            <h3 class="mt-2 font-semibold text-on-surface">{{ $question->title }}</h3>
                            <p class="mt-1 line-clamp-2 text-sm text-on-surface-variant">{{ $question->public_question }}</p>
                        </a>
                    @empty
                        <p class="rounded-xl bg-surface-card p-8 text-center text-on-surface-variant shadow-sm">{{ __('Jadilah yang pertama bertanya.') }}</p>
                    @endforelse
                </div>
            </div>
        </section>
    @endif

    @if ($leaders->isNotEmpty())
        <section class="bg-surface-low py-16 md:py-20">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-10">
                <div class="mb-10 flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <p class="text-sm font-bold tracking-wider text-secondary uppercase">{{ __('Dewan Pengasuh & Pendidik') }}</p>
                        <h2 class="mt-1 text-3xl font-bold text-brand">{{ __('Pimpinan & Masyayikh') }}</h2>
                    </div>
                    <a href="{{ route('site.leaders') }}" class="inline-flex items-center gap-1 font-semibold text-brand hover:underline">{{ __('Lihat semua') }} <x-site.icon name="east" class="text-[18px]!" /></a>
                </div>
                <div class="grid gap-6 md:grid-cols-3">
                    @foreach ($leaders as $leader)
                        <article class="rounded-2xl border border-brand/10 bg-surface-card p-6 text-center shadow-sm">
                            <x-site.image :asset="$leader->photo" conversion="thumb" :alt="$leader->name" icon="person" class="mx-auto size-28 rounded-full ring-4 ring-mint" />
                            <p class="mt-4 text-xs font-bold tracking-wider text-secondary uppercase">{{ $leader->position }}</p>
                            <h3 class="mt-1 text-lg font-bold text-on-surface">{{ $leader->name }}</h3>
                            @if ($leader->expertise)
                                <p class="mt-2 text-sm text-on-surface-variant">{{ $leader->expertise }}</p>
                            @endif
                        </article>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    @if ($events->isNotEmpty())
        <section class="py-16 md:py-20">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-10">
                <div class="mb-10 flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <p class="text-sm font-bold tracking-wider text-secondary uppercase">{{ __('Kegiatan Kampus') }}</p>
                        <h2 class="mt-1 text-3xl font-bold text-brand">{{ __('Agenda & Halaqah Ilmiah') }}</h2>
                    </div>
                    <a href="{{ route('site.events.index') }}" class="inline-flex items-center gap-1 font-semibold text-brand hover:underline">{{ __('Kalender lengkap') }} <x-site.icon name="east" class="text-[18px]!" /></a>
                </div>
                <div class="grid gap-5 lg:grid-cols-3">
                    @foreach ($events as $event)
                        <x-site.event-card :event="$event" />
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    @if ($admission)
        <section class="bg-surface-low py-16 md:py-20">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-10">
                <div class="grid gap-10 lg:grid-cols-12">
                    <div class="lg:col-span-5">
                        <p class="text-sm font-bold tracking-wider text-secondary uppercase">{{ __('Penerimaan Mahasantri Baru') }}</p>
                        <h2 class="mt-1 text-3xl font-bold text-brand">{{ $admission->title }} {{ $admission->academic_year }}</h2>
                        @if ($admission->summary)
                            <p class="mt-4 text-on-surface-variant">{{ $admission->summary }}</p>
                        @endif
                        <p class="mt-4 inline-flex items-center gap-2 rounded-lg bg-surface-card px-4 py-2 text-sm font-semibold text-brand shadow-sm">
                            <x-site.icon name="calendar_today" class="text-[18px]!" />
                            {{ $admission->opens_on->translatedFormat('d F') }} – {{ $admission->closes_on->translatedFormat('d F Y') }}
                        </p>
                        <div class="mt-6">
                            <a href="{{ $admission->acceptsRegistrations() ? route('site.admissions.register') : route('site.admissions.show') }}" class="inline-flex items-center gap-2 rounded-lg bg-secondary px-6 py-3 font-semibold text-on-secondary hover:brightness-110">
                                {{ $admission->acceptsRegistrations() ? __('Daftar Sekarang') : __('Informasi Pendaftaran') }} <x-site.icon name="arrow_forward" class="text-[18px]!" />
                            </a>
                        </div>
                    </div>
                    @if ($admission->steps)
                        <ol class="grid gap-4 sm:grid-cols-2 lg:col-span-7">
                            @foreach (array_slice($admission->steps, 0, 4) as $step)
                                <li class="rounded-xl border border-brand/10 bg-surface-card p-5 shadow-sm">
                                    <span class="text-3xl font-bold text-brand/20">{{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}</span>
                                    <h3 class="mt-2 font-semibold text-on-surface">{{ $step['title'] }}</h3>
                                    @if ($step['description'] ?? '')
                                        <p class="mt-1 text-sm text-on-surface-variant">{{ $step['description'] }}</p>
                                    @endif
                                </li>
                            @endforeach
                        </ol>
                    @endif
                </div>
            </div>
        </section>
    @elseif ($hasCta)
        <section class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-10">
            <div class="relative overflow-hidden rounded-3xl bg-primary-container px-8 py-12 text-on-primary md:px-14">
                <div class="absolute -end-16 -bottom-16 size-64 rounded-full bg-secondary/20"></div>
                <div class="relative flex flex-col gap-6 md:flex-row md:items-center md:justify-between">
                    <div>
                        <h2 class="text-3xl font-bold">{{ $site->setting('header.announcement') ?: $site->setting('header.cta_label') }}</h2>
                        @if ($site->setting('contact.whatsapp'))
                            <p class="mt-2 text-on-primary/80">{{ __('Helpdesk WhatsApp: :number', ['number' => $site->setting('contact.whatsapp')]) }}</p>
                        @endif
                    </div>
                    <a href="{{ $site->setting('header.cta_url') }}" class="inline-flex shrink-0 items-center gap-2 rounded-lg bg-secondary px-6 py-3 font-semibold text-on-secondary hover:brightness-110">
                        {{ $site->setting('header.cta_label') }} <x-site.icon name="arrow_forward" class="text-[18px]!" />
                    </a>
                </div>
            </div>
        </section>
    @endif
</x-site.layout>

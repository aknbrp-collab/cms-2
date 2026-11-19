@php
    use App\Enums\MenuLocation;

    $quickLinks = app(App\Support\SiteTheme::class)->menu(MenuLocation::TopBar)->take(4);
    $quickIcons = ['menu_book', 'school', 'description', 'public'];
    $hasCta = $site->setting('header.cta_url') && $site->setting('header.cta_label');
    $spotlight = $programs->first();
    $leader = $leaders->first();
    $container = 'mx-auto max-w-7xl px-4 sm:px-6 lg:px-10';
@endphp

<x-site.layout :site="$site" :seo="$seo">
    {{-- Hero: slides (when set) behind a gradient; text on the left, the photo left clear on the right. --}}
    <section
        @class(['relative overflow-hidden bg-primary py-16 text-on-primary lg:py-24', 'lg:min-h-[34rem]' => $slides->isNotEmpty()])
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
                <div class="absolute inset-0 bg-gradient-to-r from-primary via-primary/75 to-transparent"></div>
                <div class="absolute inset-0 bg-gradient-to-t from-primary/70 to-transparent lg:hidden"></div>
            </div>
        @else
            <div class="pointer-events-none absolute inset-0 opacity-[0.06]" style="background-image: radial-gradient(circle at 1px 1px, currentColor 1px, transparent 0); background-size: 22px 22px;"></div>
        @endif

        <div class="relative {{ $container }} grid items-center gap-12 lg:grid-cols-12">
            <div class="flex max-w-2xl flex-col items-start lg:col-span-7">
                @if ($site->setting('identity.tagline'))
                    <span class="site-chip px-3.5! py-1.5!">
                        <span class="size-2 rounded-full bg-secondary" aria-hidden="true"></span> {{ $site->setting('identity.tagline') }}
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
                                    x-bind:class="current === {{ $loop->index }} ? 'w-7 bg-secondary-container' : 'w-2.5 bg-on-primary/40 hover:bg-on-primary/70'"
                                    {{-- Width and colour come only from x-bind: Alpine never removes static classes. --}}
                                    class="h-2.5 rounded-full transition-all"
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
        </div>
    </section>

    {{-- Quick links strip under the hero. --}}
    @if ($quickLinks->isNotEmpty())
        <section aria-label="{{ __('Tautan cepat') }}" class="border-b border-border-subtle bg-surface-card">
            <div class="{{ $container }} grid grid-cols-2 gap-6 py-8 lg:grid-cols-4">
                @foreach ($quickLinks as $link)
                    <a href="{{ $link->href() }}" @if ($link->open_in_new_tab) target="_blank" rel="noopener" @endif class="group flex items-start gap-3">
                        <span class="flex size-11 shrink-0 items-center justify-center rounded-md bg-surface-low text-brand transition-colors group-hover:bg-primary group-hover:text-on-primary">
                            <x-site.icon :name="$quickIcons[$loop->index] ?? 'east'" />
                        </span>
                        <span class="flex flex-col gap-0.5">
                            <span class="font-mono text-[13px] text-secondary tabular-nums">{{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}</span>
                            <span class="font-display text-xl leading-tight font-semibold text-brand group-hover:underline md:text-2xl md:leading-[30px]">{{ $link->label }}</span>
                        </span>
                    </a>
                @endforeach
            </div>
        </section>
    @endif

    {{-- Program spotlight: the first takhassus, then the others. --}}
    @if ($spotlight)
        <section class="bg-surface py-16" id="takhassus">
            <div class="{{ $container }} flex flex-col gap-12">
                <div class="grid items-center gap-10 lg:grid-cols-12">
                    <div class="flex flex-col gap-6 lg:col-span-7">
                        <div class="flex flex-col gap-3">
                            <div class="flex flex-wrap items-center gap-3">
                                <span class="site-eyebrow">{{ __('Program Takhassus') }}</span>
                                @if ($spotlight->accreditation)
                                    <span class="site-chip">{{ $spotlight->accreditation }}</span>
                                @endif
                            </div>
                            <h2 class="site-heading text-balance">{{ $spotlight->name }}</h2>
                            <p class="text-sm font-bold text-on-surface-variant">{{ __('Disiplin Keilmuan Pesantren Tinggi') }}</p>
                            @if ($spotlight->summary)
                                <p class="max-w-prose text-base leading-[26px] text-pretty text-on-surface-variant">{{ $spotlight->summary }}</p>
                            @endif
                        </div>

                        @if ($books = array_slice($spotlight->coreBookList(), 0, 3))
                            <div class="grid gap-4 sm:grid-cols-3">
                                @foreach ($books as $book)
                                    <div class="site-card flex flex-col gap-1.5 p-4">
                                        <x-site.icon name="menu_book" class="text-2xl! text-brand" />
                                        <p class="font-bold text-on-surface">{{ $book }}</p>
                                        <p class="text-xs font-bold tracking-wide text-muted uppercase">{{ __('Kitab Muqarrar') }}</p>
                                    </div>
                                @endforeach
                            </div>
                        @endif

                        <div class="flex flex-wrap items-center gap-4 pt-2">
                            <a href="{{ route('site.programs.show', $spotlight->slug) }}" class="site-btn-primary">
                                {{ __('Kurikulum & silabus') }} <x-site.icon name="arrow_forward" class="text-[18px]!" />
                            </a>
                        </div>
                    </div>

                    <div class="lg:col-span-5">
                        <div class="site-card overflow-hidden rounded-lg!">
                            <x-site.image :asset="$spotlight->image" :alt="$spotlight->name" icon="menu_book" class="h-56 w-full" />
                            <div class="flex flex-col gap-4 p-6">
                                <div class="flex items-center justify-between gap-2 border-b border-border-subtle pb-3">
                                    <span class="site-title">{{ $spotlight->level->label() }}</span>
                                    @if ($spotlight->accreditation)
                                        <span class="site-meta">{{ $spotlight->accreditation }}</span>
                                    @endif
                                </div>
                                <dl class="flex flex-col gap-3 text-sm">
                                    @if ($spotlight->degree)
                                        <div class="flex items-center justify-between gap-4">
                                            <dt class="text-on-surface-variant">{{ __('Gelar') }}</dt>
                                            <dd class="text-right font-bold text-on-surface">{{ $spotlight->degree }}</dd>
                                        </div>
                                    @endif
                                    @foreach (array_slice($spotlight->coreBookList(), 3, 2) as $book)
                                        <div class="flex items-center justify-between gap-4">
                                            <dt class="text-on-surface-variant">{{ __('Kitab Muqarrar') }}</dt>
                                            <dd class="text-right text-on-surface">{{ $book }}</dd>
                                        </div>
                                    @endforeach
                                </dl>
                            </div>
                        </div>
                    </div>
                </div>

                @if ($programs->count() > 1)
                    <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
                        @foreach ($programs->skip(1) as $program)
                            <x-site.program-card :program="$program" />
                        @endforeach
                    </div>
                @endif
            </div>
        </section>
    @endif

    {{-- Leadership: the first profile large, the others beside it. --}}
    @if ($leader)
        <section class="border-y border-border-subtle bg-surface-low py-16">
            <div class="{{ $container }} grid items-center gap-10 lg:grid-cols-12">
                <div class="lg:col-span-5">
                    <div class="site-card overflow-hidden rounded-lg!">
                        <x-site.image :asset="$leader->photo" :alt="$leader->name" icon="person" class="h-96 w-full object-top" />
                        <div class="border-t border-border-subtle p-4">
                            <p class="font-bold text-on-surface">{{ $leader->name }}</p>
                            <p class="text-sm text-on-surface-variant">{{ $leader->position }}</p>
                        </div>
                    </div>
                </div>

                <div class="flex flex-col gap-6 lg:col-span-7">
                    <div class="flex flex-col gap-2">
                        <span class="site-eyebrow">{{ __('Dewan Pengasuh & Pendidik') }}</span>
                        <h2 class="site-heading text-balance">{{ __('Pimpinan & Masyayikh') }}</h2>
                    </div>

                    @if ($leader->expertise)
                        <blockquote class="border-s-2 border-secondary ps-5 font-display text-2xl leading-[30px] font-semibold text-pretty text-brand">{{ $leader->expertise }}</blockquote>
                    @endif

                    @if ($leaders->count() > 1)
                        <div class="grid gap-4 pt-2 sm:grid-cols-2">
                            @foreach ($leaders->skip(1) as $other)
                                <div class="site-card flex items-center gap-4 p-4">
                                    <x-site.image :asset="$other->photo" conversion="thumb" :alt="$other->name" icon="person" class="size-14 shrink-0 rounded-full border border-border-subtle object-top" />
                                    <div class="min-w-0">
                                        <p class="font-bold text-brand">{{ $other->name }}</p>
                                        <p class="text-sm text-on-surface-variant">{{ $other->position }}</p>
                                        @if ($other->expertise)
                                            <p class="mt-0.5 line-clamp-1 text-xs text-muted">{{ $other->expertise }}</p>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif

                    <div>
                        <a href="{{ route('site.leaders') }}" class="site-btn-outline">{{ __('Lihat semua') }} <x-site.icon name="arrow_forward" class="text-[18px]!" /></a>
                    </div>
                </div>
            </div>
        </section>
    @endif

    {{-- Research and publications. --}}
    @if ($publications->isNotEmpty())
        <section class="bg-surface py-16">
            <div class="{{ $container }} flex flex-col gap-8">
                <div class="flex flex-wrap items-end justify-between gap-4">
                    <div class="flex max-w-2xl flex-col gap-2">
                        <span class="site-eyebrow">{{ __('Publikasi & Riset Ilmiah') }}</span>
                        <h2 class="site-heading text-balance">{{ __('Bahtsul Masail, Jurnal & Repositori') }}</h2>
                    </div>
                    <a href="{{ route('site.publications.index') }}" class="site-link">{{ __('Kunjungi repositori') }} <x-site.icon name="arrow_forward" class="text-[18px]!" /></a>
                </div>
                <div class="grid items-stretch gap-6 md:grid-cols-2 lg:grid-cols-3">
                    @foreach ($publications as $publication)
                        <x-site.publication-item :publication="$publication" />
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    {{-- News (8 columns) with announcements and the agenda (4 columns). --}}
    <section class="border-t border-border-subtle bg-surface-low py-16">
        <div class="{{ $container }} grid gap-8 lg:grid-cols-12">
            <div class="flex flex-col gap-6 lg:col-span-8">
                <div class="flex items-end justify-between gap-4">
                    <div>
                        <span class="site-eyebrow">{{ __('Kabar Kampus') }}</span>
                        <h2 class="site-heading">{{ __('Berita Akademik') }}</h2>
                    </div>
                    <a href="{{ route('site.posts.index') }}" class="site-link shrink-0">{{ __('Semua berita') }}</a>
                </div>

                @if ($latest->isEmpty())
                    <p class="site-card p-10 text-center text-on-surface-variant">{{ __('Belum ada berita yang diterbitkan.') }}</p>
                @else
                    <div class="grid items-stretch gap-6 md:grid-cols-2">
                        @foreach ($latest->take(4) as $post)
                            <x-site.post-card :post="$post" />
                        @endforeach
                    </div>
                @endif
            </div>

            <aside class="flex flex-col gap-6 lg:col-span-4">
                <div class="flex items-center justify-between gap-2">
                    <div>
                        <span class="site-eyebrow">{{ __('Pengumuman') }}</span>
                        <h2 class="font-display text-[32px] leading-[38px] font-semibold text-on-surface">{{ __('Agenda & Halaqah Ilmiah') }}</h2>
                    </div>
                    @if ($announcements->isNotEmpty())
                        <span class="inline-flex h-6 min-w-6 items-center justify-center rounded-full bg-primary px-2 text-xs font-bold text-on-primary tabular-nums">{{ $announcements->count() }}</span>
                    @endif
                </div>

                <div class="flex flex-col gap-4">
                    @forelse ($announcements as $announcement)
                        <a href="{{ route('site.posts.show', $announcement->slug) }}" class="site-card group flex flex-col gap-1.5 p-5 transition-colors duration-150 hover:border-brand hover:bg-surface-container">
                            <span class="flex items-center justify-between gap-2">
                                <span class="site-meta uppercase">{{ $announcement->published_at?->translatedFormat('d M Y') }}</span>
                                <x-site.icon name="arrow_outward" class="text-[18px]! text-brand" />
                            </span>
                            <span class="font-bold text-on-surface">{{ $announcement->title }}</span>
                        </a>
                    @empty
                        <p class="text-sm text-muted">{{ __('Belum ada pengumuman.') }}</p>
                    @endforelse

                    @foreach ($events as $event)
                        <a href="{{ route('site.events.show', $event->slug) }}" class="site-card group flex flex-col gap-1.5 p-5 transition-colors duration-150 hover:border-brand hover:bg-surface-container">
                            <span class="flex items-center justify-between gap-2">
                                <span class="site-meta uppercase">{{ $event->starts_at->translatedFormat('d M Y') }}</span>
                                <x-site.icon name="arrow_outward" class="text-[18px]! text-brand" />
                            </span>
                            <span class="font-bold text-on-surface">{{ $event->title }}</span>
                            @if ($event->summary)
                                <span class="line-clamp-2 text-sm text-on-surface-variant">{{ $event->summary }}</span>
                            @endif
                        </a>
                    @endforeach

                    @if ($events->isNotEmpty())
                        <a href="{{ route('site.events.index') }}" class="site-link">{{ __('Kalender lengkap') }} <x-site.icon name="arrow_forward" class="text-[18px]!" /></a>
                    @endif
                </div>
            </aside>
        </div>
    </section>

    {{-- Category sections chosen in the CMS. --}}
    @foreach ($homeCategories as $category)
        <section @class(['py-16', 'bg-surface' => $loop->odd, 'bg-surface-low' => $loop->even])>
            <div class="{{ $container }}">
                <div class="mb-8 flex flex-wrap items-end justify-between gap-4">
                    <h2 class="site-heading">{{ $category->name }}</h2>
                    <a href="{{ route('site.categories.show', $category->slug) }}" class="site-link">{{ __('Selengkapnya') }} <x-site.icon name="arrow_forward" class="text-[18px]!" /></a>
                </div>
                <div class="grid items-stretch gap-6 sm:grid-cols-2 lg:grid-cols-4">
                    @foreach ($category->posts as $post)
                        <x-site.post-card :post="$post" />
                    @endforeach
                </div>
            </div>
        </section>
    @endforeach

    {{-- Tanya Hukum. --}}
    @if ($site->hasModule('legal_questions'))
        <section class="border-t border-border-subtle bg-surface py-16">
            <div class="{{ $container }} grid gap-10 lg:grid-cols-12">
                <div class="flex flex-col gap-3 lg:col-span-4">
                    <span class="site-eyebrow">{{ __('Konsultasi Hukum Islam') }}</span>
                    <h2 class="site-heading">{{ __('Tanya Hukum') }}</h2>
                    <p class="text-base leading-[26px] text-on-surface-variant">{{ __('Ajukan pertanyaan seputar fiqh. Jawaban dikaji oleh dewan asatidz Ma\'had Aly.') }}</p>
                    <div class="flex flex-wrap items-center gap-4 pt-3">
                        <a href="{{ route('site.legal-questions.create') }}" class="site-btn-primary">{{ __('Ajukan Pertanyaan') }}</a>
                        <a href="{{ route('site.legal-questions.index') }}" class="site-link">{{ __('Arsip jawaban') }} <x-site.icon name="arrow_forward" class="text-[18px]!" /></a>
                    </div>
                </div>
                <div class="flex flex-col gap-4 lg:col-span-8">
                    @forelse ($legalQuestions as $question)
                        <a href="{{ route('site.legal-questions.show', $question->slug) }}" class="site-card site-card-hover block p-6">
                            <span class="site-eyebrow text-tertiary!">{{ $question->category->label() }}</span>
                            <h3 class="site-title mt-1">{{ $question->title }}</h3>
                            <p class="mt-1 line-clamp-2 text-sm leading-[22px] text-on-surface-variant">{{ $question->public_question }}</p>
                        </a>
                    @empty
                        <p class="site-card p-8 text-center text-on-surface-variant">{{ __('Jadilah yang pertama bertanya.') }}</p>
                    @endforelse
                </div>
            </div>
        </section>
    @endif

    {{-- Admissions call-out. --}}
    @if ($admission)
        <section class="bg-primary py-16 text-on-primary" id="pmb">
            <div class="{{ $container }} flex flex-col gap-10">
                <div class="flex flex-col items-start justify-between gap-8 lg:flex-row lg:items-center">
                    <div class="flex max-w-2xl flex-col gap-3">
                        <span class="text-xs font-bold tracking-wider text-secondary-container uppercase">{{ __('Penerimaan Mahasantri Baru') }}</span>
                        <h2 class="font-display text-[28px] leading-[34px] font-bold text-balance md:text-4xl md:leading-[42px]">{{ $admission->title }} {{ $admission->academic_year }}</h2>
                        @if ($admission->summary)
                            <p class="text-base leading-[26px] text-pretty text-inverse-on-surface">{{ $admission->summary }}</p>
                        @endif
                        <p class="inline-flex items-center gap-2 font-mono text-[13px] text-secondary-container tabular-nums">
                            <x-site.icon name="calendar_today" class="text-[16px]!" />
                            {{ $admission->opens_on->translatedFormat('d F') }} – {{ $admission->closes_on->translatedFormat('d F Y') }}
                        </p>
                    </div>
                    <a href="{{ $admission->acceptsRegistrations() ? route('site.admissions.register') : route('site.admissions.show') }}" class="site-btn-accent shrink-0 py-3.5">
                        <x-site.icon name="edit_document" class="text-[18px]!" />
                        {{ $admission->acceptsRegistrations() ? __('Daftar Sekarang') : __('Informasi Pendaftaran') }}
                    </a>
                </div>

                @if ($admission->steps)
                    <ol class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        @foreach (array_slice($admission->steps, 0, 4) as $step)
                            <li class="rounded-md border border-on-primary/20 bg-primary-container/60 p-5">
                                <span class="font-mono text-[13px] text-secondary-container tabular-nums">{{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}</span>
                                <h3 class="mt-1 font-bold">{{ $step['title'] }}</h3>
                                @if ($step['description'] ?? '')
                                    <p class="mt-1 text-sm text-inverse-on-surface">{{ $step['description'] }}</p>
                                @endif
                            </li>
                        @endforeach
                    </ol>
                @endif
            </div>
        </section>
    @elseif ($hasCta)
        <section class="bg-primary py-16 text-on-primary">
            <div class="{{ $container }} flex flex-col items-start justify-between gap-8 lg:flex-row lg:items-center">
                <div class="flex max-w-2xl flex-col gap-3">
                    <h2 class="font-display text-[28px] leading-[34px] font-bold text-balance md:text-4xl md:leading-[42px]">{{ $site->setting('header.announcement') ?: $site->setting('header.cta_label') }}</h2>
                    @if ($site->setting('contact.whatsapp'))
                        <p class="text-base text-inverse-on-surface">{{ __('Helpdesk WhatsApp: :number', ['number' => $site->setting('contact.whatsapp')]) }}</p>
                    @endif
                </div>
                <a href="{{ $site->setting('header.cta_url') }}" class="site-btn-accent shrink-0 py-3.5">
                    {{ $site->setting('header.cta_label') }} <x-site.icon name="arrow_forward" class="text-[18px]!" />
                </a>
            </div>
        </section>
    @endif
</x-site.layout>

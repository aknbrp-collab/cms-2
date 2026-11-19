{{-- Pondok home hero (design: beranda_pondok_pesantren_darul_amanah, from the PSB badge to the
     highlights). Text, buttons and highlights come from Site settings → Home hero; the photos from
     the Homepage slider. Expects $site, $hero (App\Support\PondokHero) and $slides. --}}
@php
    use App\Support\PondokHero;

    $stats = $hero->stats();
    $brochureUrl = $hero->brochureUrl();
    $statColumns = ['md:grid-cols-1', 'md:grid-cols-2', 'md:grid-cols-3', 'md:grid-cols-4'][max(count($stats), 1) - 1];
@endphp

<section class="w-full border-b border-border-subtle bg-gradient-to-t from-surface-tint via-background-tint to-surface-card py-8 md:py-12">
    <div class="mx-auto flex max-w-7xl flex-col gap-8 px-4 sm:px-6 lg:px-10">
        <div class="grid grid-cols-1 items-center gap-8 lg:grid-cols-12">
            <div @class(['flex flex-col gap-5', 'lg:col-span-7' => $slides->isNotEmpty(), 'lg:col-span-12 lg:max-w-3xl' => $slides->isEmpty()])>
                @if ($badge = $hero->get('badge'))
                    <div class="inline-flex items-center gap-2 self-start rounded-full border border-brand/20 bg-primary/10 px-3.5 py-1.5 text-[13px] leading-[18px] font-semibold tracking-[0.01em] text-brand">
                        <span class="size-2.5 rounded-full bg-emerald motion-safe:animate-pulse" aria-hidden="true"></span>
                        <span>{{ $badge }}</span>
                    </div>
                @endif

                <h1 class="font-display text-[30px] leading-tight font-bold tracking-tight text-balance text-on-surface md:text-[32px] md:leading-10 lg:text-[40px] lg:leading-tight">{{ $hero->get('title') }}</h1>

                @if ($text = $hero->get('text'))
                    <p class="max-w-2xl text-base leading-relaxed text-pretty text-on-surface-variant">{{ $text }}</p>
                @endif

                @php
                    $primaryLabel = $hero->get('primary_label');
                    $brochureLabel = $hero->get('brochure_label');
                    $tourLabel = $hero->get('tour_label');
                @endphp
                @if (($primaryLabel && $hero->get('primary_url')) || ($brochureLabel && $brochureUrl) || ($tourLabel && $hero->get('tour_url')))
                    <div class="flex flex-wrap items-center gap-3 pt-2">
                        @if ($primaryLabel && $hero->get('primary_url'))
                            <a href="{{ $hero->get('primary_url') }}" @if ($site->setting('hero.primary_new_tab')) target="_blank" rel="noopener noreferrer" @endif class="inline-flex items-center gap-2.5 rounded-xl bg-primary px-6 py-3.5 text-[13px] leading-[18px] font-semibold tracking-[0.01em] text-on-primary shadow-md transition-all hover:bg-deep hover:shadow-lg">
                                <x-site.icon name="how_to_reg" class="text-[20px]!" />{{ $primaryLabel }}<x-site.icon name="arrow_forward" class="text-[18px]!" />
                            </a>
                        @endif
                        @if ($brochureLabel && $brochureUrl)
                            <a href="{{ $brochureUrl }}" target="_blank" rel="noopener" class="inline-flex items-center gap-2 rounded-xl border border-border-subtle bg-surface-card px-5 py-3.5 text-[13px] leading-[18px] font-semibold tracking-[0.01em] text-on-surface shadow-sm transition-colors hover:border-brand hover:text-brand">
                                <x-site.icon name="download" class="text-[20px]! text-brand" />{{ $brochureLabel }}
                            </a>
                        @endif
                        @if ($tourLabel && $hero->get('tour_url'))
                            <a href="{{ $hero->get('tour_url') }}" @if (str_starts_with($hero->get('tour_url'), 'http')) target="_blank" rel="noopener" @endif class="inline-flex items-center gap-1.5 rounded-xl px-4 py-3.5 text-[13px] leading-[18px] font-semibold tracking-[0.01em] text-brand transition-colors hover:text-deep dark:hover:text-on-surface">
                                <x-site.icon name="play_circle" class="text-[20px]!" />{{ $tourLabel }}
                            </a>
                        @endif
                    </div>
                @endif
            </div>

            @if ($slides->isNotEmpty())
                <div class="w-full lg:col-span-5">
                    <div
                        class="group relative w-full overflow-hidden rounded-2xl border border-border-subtle bg-surface-card shadow-2xl"
                        @if ($slides->count() > 1)
                            aria-roledescription="carousel"
                            aria-label="{{ __('Slide beranda') }}"
                            x-data="{
                                current: 0,
                                count: {{ $slides->count() }},
                                timer: null,
                                touchX: null,
                                reduced: window.matchMedia('(prefers-reduced-motion: reduce)').matches,
                                init() { this.play(); document.addEventListener('visibilitychange', () => document.hidden ? this.halt() : this.play()); },
                                play() { this.halt(); if (! this.reduced) this.timer = setInterval(() => this.next(), 5000); },
                                halt() { clearInterval(this.timer); this.timer = null; },
                                next() { this.current = (this.current + 1) % this.count; },
                                prev() { this.current = (this.current - 1 + this.count) % this.count; },
                                go(index) { this.current = index; this.play(); },
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
                        <div class="relative aspect-[4/3] w-full overflow-hidden">
                            @foreach ($slides as $slide)
                                <div
                                    @if ($slides->count() > 1)
                                        role="group"
                                        aria-roledescription="slide"
                                        aria-label="{{ __(':n dari :total', ['n' => $loop->iteration, 'total' => $slides->count()]) }}"
                                        x-bind:class="current === {{ $loop->index }} ? 'opacity-100' : 'opacity-0 pointer-events-none'"
                                        x-bind:aria-hidden="current !== {{ $loop->index }}"
                                    @endif
                                    @class(['absolute inset-0 transition-opacity duration-700 motion-reduce:transition-none', 'opacity-0 pointer-events-none' => ! $loop->first])
                                >
                                    <img
                                        src="{{ $slide->image->url('large') }}"
                                        alt="{{ $slide->title ?: $slide->label ?: $site->name }}"
                                        @if ($loop->first) fetchpriority="high" @else loading="lazy" @endif
                                        class="h-full w-full object-cover"
                                    >
                                    <div class="absolute inset-0 bg-gradient-to-t from-black/80 via-black/20 to-transparent"></div>
                                    @if ($slide->label || $slide->title)
                                        <div class="absolute inset-x-0 bottom-0 p-5 pe-24 text-white">
                                            @if ($slide->label)
                                                <span class="mb-2 inline-block rounded-full px-2.5 py-0.5 text-[11px] leading-[14px] font-bold tracking-[0.04em] {{ PondokHero::CHIPS[$loop->index % count(PondokHero::CHIPS)] }}">{{ $slide->label }}</span>
                                            @endif
                                            @if ($slide->title)
                                                <p class="text-base leading-snug font-bold text-white drop-shadow-sm">{{ $slide->title }}</p>
                                            @endif
                                        </div>
                                    @endif
                                    @if ($slide->button_url)
                                        <a href="{{ $slide->button_url }}" class="absolute inset-0" aria-label="{{ $slide->title ?: $slide->label ?: __('Selengkapnya') }}" @if ($slides->count() > 1) x-bind:tabindex="current === {{ $loop->index }} ? 0 : -1" @endif></a>
                                    @endif
                                </div>
                            @endforeach
                        </div>

                        @if ($slides->count() > 1)
                            <button type="button" x-on:click="prev(); play()" class="absolute start-3 top-1/2 flex size-10 -translate-y-1/2 items-center justify-center rounded-full bg-black/40 text-white transition-colors hover:bg-black/70" aria-label="{{ __('Slide sebelumnya') }}">
                                <x-site.icon name="chevron_left" class="text-[20px]!" />
                            </button>
                            <button type="button" x-on:click="next(); play()" class="absolute end-3 top-1/2 flex size-10 -translate-y-1/2 items-center justify-center rounded-full bg-black/40 text-white transition-colors hover:bg-black/70" aria-label="{{ __('Slide berikutnya') }}">
                                <x-site.icon name="chevron_right" class="text-[20px]!" />
                            </button>
                        @endif

                        @if ($sliderBadge = $hero->get('slider_badge'))
                            <div class="absolute end-3 top-3 flex items-center gap-1.5 rounded-full bg-black/50 px-3 py-1 text-[11px] leading-[14px] font-bold tracking-[0.04em] text-white backdrop-blur-md">
                                <x-site.icon name="verified" class="text-[14px]! text-secondary" />{{ $sliderBadge }}
                            </div>
                        @endif

                        @if ($slides->count() > 1)
                            <div class="absolute end-4 bottom-3 z-10 flex items-center gap-1.5">
                                @foreach ($slides as $slide)
                                    <button
                                        type="button"
                                        x-on:click="go({{ $loop->index }})"
                                        x-bind:aria-current="current === {{ $loop->index }}"
                                        x-bind:class="current === {{ $loop->index }} ? 'w-6 bg-white' : 'w-2 bg-white/50'"
                                        {{-- Width and colour come only from x-bind: Alpine never removes static classes. --}}
                                        class="h-2 rounded-full transition-all"
                                        aria-label="{{ __('Tampilkan slide :n', ['n' => $loop->iteration]) }}"
                                    ></button>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>
            @endif
        </div>

        @if ($stats)
            <div class="grid grid-cols-2 gap-4 rounded-2xl border border-border-subtle bg-surface-card p-4 shadow-sm md:p-6 {{ $statColumns }}">
                @foreach ($stats as $stat)
                    <div class="flex items-center gap-3.5 p-2">
                        <div class="flex size-12 shrink-0 items-center justify-center rounded-xl {{ $stat['tone'] }}">
                            <x-site.icon :name="$stat['icon']" class="text-[26px]!" />
                        </div>
                        <div class="min-w-0">
                            <h3 class="text-base leading-6 font-bold text-on-surface">{{ $stat['title'] }}</h3>
                            @if ($stat['text'])
                                <p class="text-xs leading-[18px] text-muted">{{ $stat['text'] }}</p>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</section>

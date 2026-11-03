<x-site.layout :site="$site" :seo="$seo">
    @if (! $period)
        <x-site.page-header :title="__('Penerimaan')" />
        <div class="mx-auto max-w-3xl px-4 py-16 text-center sm:px-6">
            <p class="rounded-xl bg-surface-card p-10 text-on-surface-variant shadow-sm">{{ __('Informasi pendaftaran belum tersedia. Silakan hubungi kami untuk informasi lebih lanjut.') }}</p>
        </div>
    @else
        <section class="relative overflow-hidden bg-primary text-on-primary">
            <div class="absolute -end-20 -top-20 size-72 rounded-full bg-secondary/20"></div>
            <div class="relative mx-auto max-w-7xl px-4 py-12 sm:px-6 md:py-16 lg:px-10">
                <p class="text-sm font-bold tracking-wider text-gold-light uppercase">{{ __('Penerimaan Tahun Ajaran :year', ['year' => $period->academic_year]) }}</p>
                <h1 class="mt-2 text-3xl font-bold md:text-4xl">{{ $period->title }}</h1>
                @if ($period->summary)
                    <p class="mt-4 max-w-2xl text-on-primary/80">{{ $period->summary }}</p>
                @endif
                <div class="mt-6 flex flex-wrap items-center gap-3">
                    <span class="inline-flex items-center gap-2 rounded-lg bg-primary-container px-4 py-2 text-sm font-semibold">
                        <x-site.icon name="calendar_today" class="text-[18px]!" />
                        {{ $period->opens_on->translatedFormat('d F') }} – {{ $period->closes_on->translatedFormat('d F Y') }}
                    </span>
                    @if ($period->acceptsRegistrations())
                        <a href="{{ route('site.admissions.register') }}" class="inline-flex items-center gap-2 rounded-lg bg-secondary px-5 py-2.5 font-semibold text-on-secondary shadow-sm hover:brightness-110">
                            {{ __('Daftar Online Sekarang') }} <x-site.icon name="arrow_forward" class="text-[18px]!" />
                        </a>
                    @elseif (! $period->isOpen())
                        <span class="rounded-lg bg-white/10 px-4 py-2 text-sm">{{ $period->closes_on->isPast() ? __('Pendaftaran telah ditutup') : __('Pendaftaran belum dibuka') }}</span>
                    @endif
                    @if ($period->accepts_online_registration && $period->documentRequirements()->exists())
                        <a href="{{ route('site.admissions.access') }}" class="inline-flex items-center gap-2 rounded-lg border border-on-primary/30 px-4 py-2 text-sm font-semibold hover:bg-white/10">
                            <x-site.icon name="upload" class="text-[18px]!" /> {{ __('Lengkapi Berkas') }}
                        </a>
                    @endif
                    @if ($period->brochure)
                        <a href="{{ $period->brochure->url() }}" target="_blank" rel="noopener" class="inline-flex items-center gap-2 rounded-lg border border-on-primary/30 px-4 py-2 text-sm font-semibold hover:bg-white/10">
                            <x-site.icon name="description" class="text-[18px]!" /> {{ __('Unduh Brosur') }}
                        </a>
                    @endif
                </div>
            </div>
        </section>

        <div class="mx-auto grid max-w-7xl gap-10 px-4 py-12 sm:px-6 lg:grid-cols-12 lg:px-10">
            <div class="space-y-12 lg:col-span-8">
                @if ($period->steps)
                    <section>
                        <h2 class="mb-6 text-2xl font-bold text-on-surface">{{ __('Alur Pendaftaran') }}</h2>
                        <ol class="grid gap-4 sm:grid-cols-2">
                            @foreach ($period->steps as $step)
                                <li class="flex gap-4 rounded-xl bg-surface-card p-5 shadow-sm">
                                    <span class="flex size-10 shrink-0 items-center justify-center rounded-full bg-primary font-bold text-on-primary">{{ $loop->iteration }}</span>
                                    <div>
                                        <h3 class="font-semibold text-on-surface">{{ $step['title'] }}</h3>
                                        @if ($step['description'] ?? '')
                                            <p class="mt-1 text-sm text-on-surface-variant">{{ $step['description'] }}</p>
                                        @endif
                                    </div>
                                </li>
                            @endforeach
                        </ol>
                    </section>
                @endif

                @if ($period->requirements)
                    <section>
                        <h2 class="mb-4 text-2xl font-bold text-on-surface">{{ __('Persyaratan') }}</h2>
                        <div class="prose-site rounded-xl bg-surface-card p-6 shadow-sm">{!! $period->requirements !!}</div>
                    </section>
                @endif

                @if ($period->fees)
                    <section>
                        <h2 class="mb-4 text-2xl font-bold text-on-surface">{{ __('Biaya & Beasiswa') }}</h2>
                        <div class="prose-site rounded-xl bg-surface-card p-6 shadow-sm">{!! $period->fees !!}</div>
                    </section>
                @endif

                @if ($faqs->isNotEmpty())
                    <section>
                        <h2 class="mb-4 text-2xl font-bold text-on-surface">{{ __('Pertanyaan Umum') }}</h2>
                        @include('sites.shared.faq-list', ['faqs' => $faqs])
                    </section>
                @endif
            </div>

            <aside class="space-y-6 lg:col-span-4">
                @if ($period->programs)
                    <div class="rounded-xl bg-surface-card p-6 shadow-sm">
                        <h2 class="mb-3 font-bold text-on-surface">{{ __('Program') }}</h2>
                        <ul class="space-y-2 text-sm text-on-surface-variant">
                            @foreach ($period->programs as $program)
                                <li class="flex items-center gap-2"><x-site.icon name="school" class="text-[18px]! text-brand" /> {{ $program }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
                @include('sites.shared.contact-card')
            </aside>
        </div>
    @endif
</x-site.layout>

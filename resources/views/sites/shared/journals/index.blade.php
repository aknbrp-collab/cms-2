<x-site.layout :site="$site" :seo="$seo">
    <x-site.page-header :title="__('Jurnal Ilmiah')" :description="__('Jurnal yang diterbitkan oleh lembaga.')" />

    {{-- Journal profiles only; articles live on each journal's own website. --}}
    <div class="mx-auto max-w-7xl px-4 py-10 sm:px-6 md:py-14 lg:px-10">
        @if ($journals->isEmpty())
            <p class="site-card p-10 text-center text-on-surface-variant">{{ __('Belum ada jurnal.') }}</p>
        @else
            <div class="grid items-stretch gap-6 md:grid-cols-2">
                @foreach ($journals as $journal)
                    <article class="site-card flex h-full flex-col gap-4 p-6 md:p-8">
                        <div class="flex flex-wrap items-center gap-3">
                            @if ($journal->accreditation)
                                <span class="site-chip">{{ $journal->accreditation }}</span>
                            @endif
                            @if ($journal->e_issn)
                                <span class="site-meta">E-ISSN {{ $journal->e_issn }}</span>
                            @endif
                        </div>
                        <h2 class="site-heading text-balance md:text-3xl!">{{ $journal->name }}</h2>
                        @if ($journal->description)
                            <p class="text-base leading-[26px] text-pretty text-on-surface-variant">{{ $journal->description }}</p>
                        @endif
                        @if ($journal->url)
                            <div class="mt-auto pt-2">
                                <a href="{{ $journal->url }}" target="_blank" rel="noopener" class="site-btn-primary">
                                    {{ __('Kunjungi situs jurnal') }} <x-site.icon name="arrow_outward" class="text-[18px]!" />
                                </a>
                            </div>
                        @endif
                    </article>
                @endforeach
            </div>
        @endif
    </div>
</x-site.layout>

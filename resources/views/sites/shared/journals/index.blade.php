<x-site.layout :site="$site" :seo="$seo">
    <x-site.page-header :title="__('Jurnal Ilmiah')" :description="__('Jurnal yang diterbitkan oleh lembaga.')" />

    <div class="mx-auto max-w-6xl px-4 py-10 sm:px-6 md:py-14 lg:px-10">
        @if ($journals->isEmpty())
            <p class="site-card p-10 text-center text-on-surface-variant">{{ __('Belum ada jurnal.') }}</p>
        @else
            <div class="space-y-6">
                @foreach ($journals as $journal)
                    <article class="site-card flex flex-col gap-6 p-6 sm:flex-row">
                        <x-site.image :asset="$journal->cover" conversion="thumb" :alt="$journal->name" icon="menu_book" class="aspect-[3/4] w-32 shrink-0 rounded-lg" />
                        <div class="space-y-2">
                            @if ($journal->accreditation)
                                <span class="inline-block rounded-full bg-gold-light px-3 py-1 text-xs font-bold text-on-surface">{{ $journal->accreditation }}</span>
                            @endif
                            <h2 class="text-xl font-bold text-brand"><a href="{{ route('site.journals.show', $journal->slug) }}">{{ $journal->name }}</a></h2>
                            <p class="text-sm text-muted">
                                @if ($journal->issn) ISSN {{ $journal->issn }} @endif
                                @if ($journal->e_issn) · E-ISSN {{ $journal->e_issn }} @endif
                            </p>
                            @if ($journal->description)
                                <p class="text-on-surface-variant">{{ $journal->description }}</p>
                            @endif
                            <div class="flex flex-wrap gap-3 pt-2">
                                <a href="{{ route('site.journals.show', $journal->slug) }}" class="text-sm font-semibold text-brand">{{ __('Daftar artikel') }}</a>
                                @if ($journal->url)
                                    <a href="{{ $journal->url }}" target="_blank" rel="noopener" class="text-sm font-semibold text-brand">{{ __('Situs jurnal (OJS)') }} ↗</a>
                                @endif
                            </div>
                        </div>
                    </article>
                @endforeach
            </div>
        @endif
    </div>
</x-site.layout>

<x-site.layout :site="$site" :seo="$seo">
    <x-site.page-header :title="$journal->name" :description="$journal->description" :crumbs="[__('Jurnal') => route('site.journals.index')]" />

    <div class="mx-auto max-w-5xl px-4 py-10 sm:px-6 md:py-14">
        <div class="mb-8 flex flex-wrap items-center gap-3 text-sm">
            @if ($journal->accreditation)
                <span class="rounded-full bg-gold-light px-3 py-1 font-bold text-on-surface">{{ $journal->accreditation }}</span>
            @endif
            @if ($journal->issn)
                <span class="text-muted">ISSN {{ $journal->issn }}</span>
            @endif
            @if ($journal->e_issn)
                <span class="text-muted">E-ISSN {{ $journal->e_issn }}</span>
            @endif
            @if ($journal->url)
                <a href="{{ $journal->url }}" target="_blank" rel="noopener" class="font-semibold text-brand">{{ __('Kunjungi situs jurnal') }} ↗</a>
            @endif
        </div>

        @if ($articles->isEmpty())
            <p class="site-card p-10 text-center text-on-surface-variant">{{ __('Belum ada artikel di repositori.') }}</p>
        @else
            <div class="space-y-4">
                @foreach ($articles as $publication)
                    <x-site.publication-item :publication="$publication" />
                @endforeach
            </div>
            <div class="mt-10">{{ $articles->links() }}</div>
        @endif
    </div>
</x-site.layout>

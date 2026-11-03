@php use App\Enums\PublicationType; @endphp

<x-site.layout :site="$site" :seo="$seo">
    <x-site.page-header :title="$type?->label() ?? __('Publikasi & Riset')" :description="__('Repositori karya ilmiah, hasil Bahtsul Masail, dan artikel jurnal.')" />

    <div class="mx-auto grid max-w-7xl gap-10 px-4 py-10 sm:px-6 md:py-14 lg:grid-cols-12 lg:px-10">
        <aside class="lg:col-span-3">
            <form method="GET" class="space-y-5 rounded-xl bg-surface-card p-5 shadow-sm lg:sticky lg:top-28" role="search">
                <div>
                    <label for="q" class="block text-sm font-semibold text-on-surface">{{ __('Cari') }}</label>
                    <input id="q" name="q" value="{{ $search }}" placeholder="{{ __('Judul, penulis, kata kunci') }}" class="mt-1 w-full rounded-lg border border-border-subtle px-3 py-2 text-sm focus:border-brand focus:outline-none">
                </div>
                <div>
                    <label for="jenis" class="block text-sm font-semibold text-on-surface">{{ __('Jenis') }}</label>
                    <select id="jenis" name="jenis" class="mt-1 w-full rounded-lg border border-border-subtle px-3 py-2 text-sm">
                        <option value="">{{ __('Semua jenis') }}</option>
                        @foreach (PublicationType::cases() as $case)
                            <option value="{{ $case->value }}" @selected($type === $case)>{{ $case->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="tahun" class="block text-sm font-semibold text-on-surface">{{ __('Tahun') }}</label>
                    <select id="tahun" name="tahun" class="mt-1 w-full rounded-lg border border-border-subtle px-3 py-2 text-sm">
                        <option value="">{{ __('Semua tahun') }}</option>
                        @foreach ($years as $item)
                            <option value="{{ $item }}" @selected($year === $item)>{{ $item }}</option>
                        @endforeach
                    </select>
                </div>
                <button class="w-full rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-on-primary">{{ __('Terapkan') }}</button>
                @if ($type || $year || $search !== '')
                    <a href="{{ route('site.publications.index') }}" class="block text-center text-sm text-brand">{{ __('Reset filter') }}</a>
                @endif
            </form>
        </aside>

        <div class="lg:col-span-9">
            @if ($publications->isEmpty())
                <p class="rounded-xl bg-surface-card p-10 text-center text-on-surface-variant shadow-sm">{{ __('Publikasi tidak ditemukan.') }}</p>
            @else
                <div class="space-y-4">
                    @foreach ($publications as $publication)
                        <x-site.publication-item :publication="$publication" />
                    @endforeach
                </div>
                <div class="mt-10">{{ $publications->links() }}</div>
            @endif
        </div>
    </div>
</x-site.layout>

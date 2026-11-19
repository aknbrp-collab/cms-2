<x-site.layout :site="$site" :seo="$seo">
    <x-site.page-header :title="__('Data Alumni')" :description="__('Direktori alumni berdasarkan tahun lulus dan kota.')" :crumbs="[__('Alumni') => route('site.alumni.index')]" />

    <div class="mx-auto flex max-w-5xl flex-col gap-6 px-4 py-10 sm:px-6 md:py-14 lg:px-10">
        <form method="GET" role="search" class="site-card flex flex-col gap-3 p-3 sm:flex-row sm:items-center">
            <label for="q" class="sr-only">{{ __('Cari alumni') }}</label>
            <div class="flex min-w-0 flex-1 items-center gap-2 px-2">
                <x-site.icon name="search" class="text-muted" />
                <input id="q" name="q" value="{{ $search }}" placeholder="{{ __('Cari nama atau kota…') }}" class="min-w-0 flex-1 bg-transparent py-2 text-on-surface placeholder:text-muted focus:outline-none">
            </div>
            <label for="angkatan" class="sr-only">{{ __('Tahun lulus') }}</label>
            <select id="angkatan" name="angkatan" class="rounded-lg border border-border-subtle bg-surface-card px-3 py-2.5 text-sm text-on-surface">
                <option value="">{{ __('Semua angkatan') }}</option>
                @foreach ($years as $option)
                    <option value="{{ $option }}" @selected($year === $option)>{{ $option }}</option>
                @endforeach
            </select>
            <button class="site-btn-primary">{{ __('Cari') }}</button>
        </form>

        <div class="flex flex-wrap items-center justify-between gap-3">
            <p class="site-meta">{{ __(':count alumni', ['count' => number_format($alumni->total(), 0, ',', '.')]) }}@if ($search !== '' || $year) <span class="text-muted">/ {{ number_format($total, 0, ',', '.') }}</span>@endif</p>
            @if ($site->hasModule('testimonials'))
                <a href="{{ route('site.alumni.testimonials') }}" class="site-link">{{ __('Testimoni Alumni') }} <x-site.icon name="arrow_forward" class="text-[16px]!" /></a>
            @endif
        </div>

        @if ($alumni->isEmpty())
            <p class="site-card p-10 text-center text-on-surface-variant">{{ $search !== '' || $year ? __('Alumni tidak ditemukan.') : __('Belum ada data alumni.') }}</p>
        @else
            <div class="site-card overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-surface-low text-xs tracking-wide text-muted uppercase">
                            <tr>
                                <th class="px-5 py-3 text-start">{{ __('Nama') }}</th>
                                <th class="px-5 py-3 text-start">{{ __('Tahun Lulus') }}</th>
                                <th class="px-5 py-3 text-start">{{ __('Kota') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border-subtle">
                            @foreach ($alumni as $alumnus)
                                <tr>
                                    <td class="px-5 py-3 font-semibold text-on-surface">{{ $alumnus->name }}</td>
                                    <td class="px-5 py-3 font-mono text-on-surface-variant tabular-nums">{{ $alumnus->graduation_year }}</td>
                                    <td class="px-5 py-3 text-on-surface-variant">{{ $alumnus->city ?: '–' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <div>{{ $alumni->links() }}</div>
        @endif
    </div>
</x-site.layout>

<x-site.layout :site="$site" :seo="$seo">
    <x-site.page-header :title="__('Direktori Dosen & Asatidzah')" :description="__('Para pengajar dengan sanad keilmuan yang bersambung.')" />

    <div class="mx-auto max-w-7xl px-4 py-10 sm:px-6 md:py-14 lg:px-10">
        <form method="GET" class="mb-8 flex max-w-md gap-2" role="search">
            <label for="q" class="sr-only">{{ __('Cari dosen') }}</label>
            <input id="q" name="q" value="{{ $search }}" placeholder="{{ __('Cari nama atau bidang keahlian…') }}" class="flex-1 rounded-lg border border-border-subtle bg-surface-card px-3 py-2.5 shadow-sm focus:border-primary focus:outline-none">
            <button class="rounded-lg bg-primary px-4 py-2.5 font-semibold text-on-primary"><x-site.icon name="search" /></button>
        </form>

        @if ($lecturers->isEmpty())
            <p class="rounded-xl bg-surface-card p-10 text-center text-on-surface-variant shadow-sm">{{ __('Dosen tidak ditemukan.') }}</p>
        @else
            <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ($lecturers as $lecturer)
                    <a href="{{ route('site.lecturers.show', $lecturer->slug) }}" class="group rounded-2xl border border-primary/10 bg-surface-card p-6 text-center shadow-sm transition hover:-translate-y-0.5 hover:shadow-md">
                        <x-site.image :asset="$lecturer->photo" conversion="thumb" :alt="$lecturer->name" icon="person" class="mx-auto size-24 rounded-full ring-4 ring-mint" />
                        <h2 class="mt-4 font-semibold text-on-surface group-hover:text-primary">{{ $lecturer->name }}</h2>
                        @if ($lecturer->position)
                            <p class="mt-1 text-xs font-bold tracking-wide text-secondary uppercase">{{ $lecturer->position }}</p>
                        @endif
                        @if ($lecturer->expertise)
                            <p class="mt-2 text-sm text-on-surface-variant">{{ $lecturer->expertise }}</p>
                        @endif
                    </a>
                @endforeach
            </div>

            <div class="mt-10">{{ $lecturers->links() }}</div>
        @endif
    </div>
</x-site.layout>

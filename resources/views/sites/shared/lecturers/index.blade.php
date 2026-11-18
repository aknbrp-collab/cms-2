<x-site.layout :site="$site" :seo="$seo">
    <x-site.page-header :title="__('Direktori Dosen & Asatidzah')" :description="__('Para pengajar dengan sanad keilmuan yang bersambung.')" />

    <div class="mx-auto flex max-w-7xl flex-col gap-8 px-4 py-10 sm:px-6 md:py-14 lg:px-10">
        <form method="GET" class="site-card flex max-w-xl items-center gap-2 p-2" role="search">
            <label for="q" class="sr-only">{{ __('Cari dosen') }}</label>
            <x-site.icon name="search" class="ms-2 text-muted" />
            <input id="q" name="q" value="{{ $search }}" placeholder="{{ __('Cari nama atau bidang keahlian…') }}" class="min-w-0 flex-1 bg-transparent px-1 py-2 text-on-surface placeholder:text-muted focus:outline-none">
            <button class="site-btn-primary">{{ __('Cari') }}</button>
        </form>

        @if ($lecturers->isEmpty())
            <p class="site-card p-10 text-center text-on-surface-variant">{{ __('Dosen tidak ditemukan.') }}</p>
        @else
            <div class="grid items-stretch gap-6 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ($lecturers as $lecturer)
                    <a href="{{ route('site.lecturers.show', $lecturer->slug) }}" class="group site-card site-card-hover flex h-full flex-col items-center p-6 text-center">
                        <x-site.image :asset="$lecturer->photo" conversion="thumb" :alt="$lecturer->name" icon="person" class="size-24 rounded-full border-4 border-surface-low object-top" />
                        <h2 class="site-title mt-4 text-xl! transition-colors group-hover:text-brand">{{ $lecturer->name }}</h2>
                        @if ($lecturer->position)
                            <p class="site-eyebrow mt-1">{{ $lecturer->position }}</p>
                        @endif
                        @if ($lecturer->expertise)
                            <p class="mt-3 text-sm text-on-surface-variant">{{ $lecturer->expertise }}</p>
                        @endif
                    </a>
                @endforeach
            </div>

            <div>{{ $lecturers->links() }}</div>
        @endif
    </div>
</x-site.layout>

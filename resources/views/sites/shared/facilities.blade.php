<x-site.layout :site="$site" :seo="$seo">
    <x-site.page-header :title="__('Sarana & Prasarana')" :description="__('Fasilitas yang mendukung kegiatan belajar dan kehidupan santri.')" />

    <div class="mx-auto max-w-7xl px-4 py-10 sm:px-6 md:py-14 lg:px-10">
        @if ($facilities->isEmpty())
            <p class="site-card p-10 text-center text-on-surface-variant">{{ __('Belum ada data fasilitas.') }}</p>
        @else
            <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($facilities as $facility)
                    <article class="site-card overflow-hidden">
                        <x-site.image :asset="$facility->image" conversion="thumb" :alt="$facility->name" class="aspect-[16/10] w-full" />
                        <div class="p-5">
                            <h2 class="font-semibold text-on-surface">{{ $facility->name }}</h2>
                            @if ($facility->description)
                                <p class="mt-2 text-sm text-on-surface-variant">{{ $facility->description }}</p>
                            @endif
                        </div>
                    </article>
                @endforeach
            </div>
        @endif
    </div>
</x-site.layout>

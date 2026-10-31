<x-site.layout :site="$site" :seo="$seo">
    <x-site.page-header :title="__('Ekstrakurikuler')" :description="__('Wadah pengembangan bakat, minat, dan kepemimpinan santri.')" />

    <div class="mx-auto max-w-7xl px-4 py-10 sm:px-6 md:py-14 lg:px-10">
        @forelse ($groups as $category => $items)
            <section class="mb-12">
                <h2 class="mb-5 text-xl font-bold text-primary">{{ $category }}</h2>
                <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($items as $item)
                        <article class="flex gap-4 rounded-xl bg-surface-card p-4 shadow-sm">
                            <x-site.image :asset="$item->image" conversion="thumb" :alt="$item->name" icon="star" class="size-24 shrink-0 rounded-lg" />
                            <div class="min-w-0">
                                <h3 class="font-semibold text-on-surface">{{ $item->name }}</h3>
                                @if ($item->description)
                                    <p class="mt-1 line-clamp-3 text-sm text-on-surface-variant">{{ $item->description }}</p>
                                @endif
                                <div class="mt-2 space-y-0.5 text-xs text-muted">
                                    @if ($item->schedule)
                                        <p class="flex items-center gap-1"><x-site.icon name="schedule" class="text-[14px]!" /> {{ $item->schedule }}</p>
                                    @endif
                                    @if ($item->coach)
                                        <p class="flex items-center gap-1"><x-site.icon name="person" class="text-[14px]!" /> {{ $item->coach }}</p>
                                    @endif
                                </div>
                            </div>
                        </article>
                    @endforeach
                </div>
            </section>
        @empty
            <p class="rounded-xl bg-surface-card p-10 text-center text-on-surface-variant shadow-sm">{{ __('Belum ada data ekstrakurikuler.') }}</p>
        @endforelse
    </div>
</x-site.layout>

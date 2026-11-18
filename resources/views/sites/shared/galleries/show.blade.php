@php
    use App\Enums\GalleryType;

    $photos = $gallery->items->filter(fn ($item) => $item->mediaAsset?->isImage())->values();
    $videos = $gallery->items->filter(fn ($item) => $item->embedUrl())->values();
    $lightbox = $photos->map(fn ($item) => ['src' => $item->mediaAsset->url('large'), 'caption' => (string) $item->caption])->all();
@endphp

<x-site.layout :site="$site" :seo="$seo">
    <x-site.page-header :title="$gallery->title" :description="$gallery->description" :crumbs="[__('Galeri') => route('site.galleries.index')]" />

    <div class="mx-auto max-w-7xl px-4 py-10 sm:px-6 md:py-14 lg:px-10">
        @if ($gallery->type === GalleryType::Video)
            <div class="grid gap-6 md:grid-cols-2">
                @foreach ($videos as $item)
                    <figure class="site-card overflow-hidden">
                        <iframe src="{{ $item->embedUrl() }}" class="aspect-video w-full" loading="lazy" allowfullscreen referrerpolicy="strict-origin-when-cross-origin" title="{{ $item->caption ?: $gallery->title }}"></iframe>
                        @if ($item->caption)
                            <figcaption class="px-4 py-3 text-sm font-medium text-on-surface">{{ $item->caption }}</figcaption>
                        @endif
                    </figure>
                @endforeach
            </div>
        @else
            <div x-data="{ items: @js($lightbox), index: null }" x-on:keydown.escape.window="index = null" x-on:keydown.arrow-right.window="index !== null && (index = (index + 1) % items.length)" x-on:keydown.arrow-left.window="index !== null && (index = (index - 1 + items.length) % items.length)">
                <div class="columns-2 gap-4 md:columns-3 lg:columns-4">
                    @foreach ($photos as $item)
                        <button type="button" class="site-card site-card-hover mb-4 block w-full overflow-hidden" x-on:click="index = {{ $loop->index }}">
                            <img src="{{ $item->mediaAsset->url('thumb') }}" alt="{{ $item->caption ?: $item->mediaAsset->alt }}" loading="lazy" class="w-full">
                            @if ($item->caption)
                                <span class="block px-3 py-2 text-start text-xs text-on-surface-variant">{{ $item->caption }}</span>
                            @endif
                        </button>
                    @endforeach
                </div>

                <div x-cloak x-show="index !== null" x-transition.opacity class="fixed inset-0 z-[80] flex flex-col items-center justify-center bg-black/90 p-4" role="dialog" aria-modal="true" x-on:click.self="index = null">
                    <button type="button" class="absolute end-4 top-4 rounded-full bg-white/10 p-2 text-white hover:bg-white/20" x-on:click="index = null" aria-label="{{ __('Tutup') }}"><x-site.icon name="close" /></button>
                    <img x-bind:src="index !== null ? items[index].src : ''" alt="" class="max-h-[80vh] max-w-full rounded-lg object-contain">
                    <p class="mt-3 text-sm text-white/80" x-text="index !== null ? items[index].caption : ''"></p>
                    <div class="mt-4 flex gap-3">
                        <button type="button" class="rounded-full bg-white/10 p-2 text-white hover:bg-white/20" x-on:click="index = (index - 1 + items.length) % items.length" aria-label="{{ __('Sebelumnya') }}"><x-site.icon name="arrow_back" /></button>
                        <button type="button" class="rounded-full bg-white/10 p-2 text-white hover:bg-white/20" x-on:click="index = (index + 1) % items.length" aria-label="{{ __('Berikutnya') }}"><x-site.icon name="arrow_forward" /></button>
                    </div>
                </div>
            </div>
        @endif

        @if ($gallery->items->isEmpty())
            <p class="site-card p-10 text-center text-on-surface-variant">{{ __('Galeri ini masih kosong.') }}</p>
        @endif
    </div>
</x-site.layout>

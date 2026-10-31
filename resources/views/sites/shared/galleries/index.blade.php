@php use App\Enums\GalleryType; @endphp

<x-site.layout :site="$site" :seo="$seo">
    <x-site.page-header :title="$type === GalleryType::Video ? __('Video') : __('Galeri')" :description="__('Dokumentasi kegiatan dalam foto dan video.')" />

    <div class="mx-auto max-w-7xl px-4 py-10 sm:px-6 md:py-14 lg:px-10">
        <div class="mb-8 inline-flex rounded-lg bg-surface-low p-1 text-sm font-semibold">
            @foreach ([null => __('Semua'), 'photo' => __('Foto'), 'video' => __('Video')] as $value => $label)
                @php $active = ($type?->value ?? '') === (string) $value; @endphp
                <a href="{{ route('site.galleries.index', array_filter(['jenis' => $value])) }}" @class(['rounded-md px-4 py-2', 'bg-primary text-on-primary shadow-sm' => $active, 'text-on-surface-variant hover:text-primary' => ! $active])>{{ $label }}</a>
            @endforeach
        </div>

        @if ($galleries->isEmpty())
            <p class="rounded-xl bg-surface-card p-10 text-center text-on-surface-variant shadow-sm">{{ __('Belum ada galeri.') }}</p>
        @else
            <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($galleries as $gallery)
                    @php
                        $cover = $gallery->cover();
                        $thumb = $cover ? null : $gallery->items->first()?->videoThumbnail();
                    @endphp
                    <a href="{{ route('site.galleries.show', $gallery->slug) }}" class="group overflow-hidden rounded-xl bg-surface-card shadow-sm transition hover:shadow-md">
                        <div class="relative aspect-[16/10] overflow-hidden bg-surface-container">
                            @if ($thumb)
                                <img src="{{ $thumb }}" alt="{{ $gallery->title }}" loading="lazy" class="h-full w-full object-cover transition duration-300 group-hover:scale-105">
                            @else
                                <x-site.image :asset="$cover" conversion="thumb" :alt="$gallery->title" :icon="$gallery->type === GalleryType::Video ? 'play_circle' : 'photo_camera'" class="h-full w-full transition duration-300 group-hover:scale-105" />
                            @endif
                            @if ($gallery->type === GalleryType::Video)
                                <span class="absolute inset-0 flex items-center justify-center"><x-site.icon name="play_circle" filled class="text-6xl! text-white drop-shadow-lg" /></span>
                            @endif
                            <span class="absolute end-3 bottom-3 rounded-full bg-black/60 px-2.5 py-0.5 text-xs font-semibold text-white">{{ $gallery->items->count() }} {{ $gallery->type === GalleryType::Video ? __('video') : __('foto') }}</span>
                        </div>
                        <div class="p-5">
                            <h2 class="font-semibold text-on-surface group-hover:text-primary">{{ $gallery->title }}</h2>
                            @if ($gallery->taken_on)
                                <p class="mt-1 text-xs text-muted">{{ $gallery->taken_on->translatedFormat('d F Y') }}</p>
                            @endif
                        </div>
                    </a>
                @endforeach
            </div>

            <div class="mt-10">{{ $galleries->links() }}</div>
        @endif
    </div>
</x-site.layout>

@props(['asset' => null, 'conversion' => 'large', 'alt' => '', 'icon' => 'mosque'])

{{-- A media library image, or a patterned placeholder when there is none. --}}
@if ($asset && $asset->isImage())
    <img src="{{ $asset->url($conversion) }}" alt="{{ $asset->alt ?: $alt }}" loading="lazy" {{ $attributes->class('object-cover') }}>
@else
    <div {{ $attributes->class('flex items-center justify-center bg-gradient-to-br from-primary-container to-primary') }} role="img" aria-label="{{ $alt }}">
        <x-site.icon :name="$icon" class="text-5xl! text-on-primary/30" />
    </div>
@endif

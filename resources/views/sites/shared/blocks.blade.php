{{-- Renders page builder blocks (App\Enums\BlockType). Expects $blocks. --}}
@php
    use App\Enums\BlockType;
    use App\Models\MediaAsset;
    use App\Support\Embed;

    $assets = MediaAsset::query()
        ->with('media')
        ->whereKey(collect($blocks)->pluck('data.media_asset_id')->filter()->all())
        ->get()
        ->keyBy('id');
@endphp

@foreach ($blocks as $block)
    @switch(BlockType::tryFrom($block['type'] ?? ''))
        @case(BlockType::RichText)
            {{-- Sanitised by App\Support\RichText when saved. --}}
            <div class="prose-site">{!! $block['data']['html'] ?? '' !!}</div>
            @break

        @case(BlockType::Image)
            @if ($asset = $assets->get($block['data']['media_asset_id'] ?? ''))
                <figure class="overflow-hidden rounded-2xl bg-surface-card shadow-sm">
                    <x-site.image :asset="$asset" class="w-full" />
                    @if (filled($block['data']['caption'] ?? null))
                        <figcaption class="px-4 py-2 text-sm text-muted">{{ $block['data']['caption'] }}</figcaption>
                    @endif
                </figure>
            @endif
            @break

        @case(BlockType::CallToAction)
            <div class="relative overflow-hidden rounded-2xl bg-primary p-8 text-on-primary shadow-md md:p-10">
                <div class="absolute -end-10 -top-10 size-40 rounded-full bg-secondary/20"></div>
                <div class="relative flex flex-col gap-5 md:flex-row md:items-center md:justify-between">
                    <div>
                        @if (filled($block['data']['title'] ?? null))
                            <h2 class="text-2xl font-bold">{{ $block['data']['title'] }}</h2>
                        @endif
                        @if (filled($block['data']['text'] ?? null))
                            <p class="mt-2 max-w-xl text-on-primary/80">{{ $block['data']['text'] }}</p>
                        @endif
                    </div>
                    @if (filled($block['data']['button_url'] ?? null) && filled($block['data']['button_label'] ?? null))
                        <a href="{{ $block['data']['button_url'] }}" class="inline-flex shrink-0 items-center gap-2 rounded-lg bg-secondary px-5 py-3 font-semibold text-on-secondary shadow-sm transition hover:brightness-110">
                            {{ $block['data']['button_label'] }} <x-site.icon name="arrow_forward" class="text-[18px]!" />
                        </a>
                    @endif
                </div>
            </div>
            @break

        @case(BlockType::Embed)
            @if ($src = Embed::toEmbedUrl($block['data']['url'] ?? null))
                <figure class="overflow-hidden rounded-2xl bg-surface-card shadow-sm">
                    <iframe src="{{ $src }}" class="aspect-video w-full" loading="lazy" allowfullscreen referrerpolicy="strict-origin-when-cross-origin" title="{{ $block['data']['caption'] ?? __('Video') }}"></iframe>
                    @if (filled($block['data']['caption'] ?? null))
                        <figcaption class="px-4 py-2 text-sm text-muted">{{ $block['data']['caption'] }}</figcaption>
                    @endif
                </figure>
            @endif
            @break
    @endswitch
@endforeach

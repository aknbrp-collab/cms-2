<x-site.layout :site="$site" :seo="$seo">
    <x-site.page-header :title="__('Pimpinan')" :description="__('Para pengasuh dan pimpinan yang membimbing lembaga.')" />

    <div class="mx-auto max-w-6xl px-4 py-10 sm:px-6 md:py-14 lg:px-10">
        @if ($leaders->isEmpty())
            <p class="rounded-xl bg-surface-card p-10 text-center text-on-surface-variant shadow-sm">{{ __('Belum ada profil pimpinan.') }}</p>
        @else
            <div class="space-y-8">
                @foreach ($leaders as $leader)
                    <article class="flex flex-col gap-6 overflow-hidden rounded-2xl bg-surface-card p-6 shadow-sm md:flex-row md:p-8">
                        <div class="shrink-0">
                            <x-site.image :asset="$leader->photo" conversion="thumb" :alt="$leader->name" icon="person" class="size-40 rounded-2xl" />
                        </div>
                        <div class="space-y-3">
                            <p class="text-sm font-bold tracking-wide text-secondary uppercase">{{ $leader->position }}</p>
                            <h2 class="text-2xl font-bold text-on-surface">{{ $leader->name }}</h2>
                            @if ($leader->expertise)
                                <p class="inline-flex items-center gap-1 rounded-full bg-mint px-3 py-1 text-xs font-semibold text-brand"><x-site.icon name="verified" class="text-[14px]!" /> {{ $leader->expertise }}</p>
                            @endif
                            @if ($leader->bio)
                                {{-- Sanitised by App\Support\RichText when saved. --}}
                                <div class="prose-site">{!! $leader->bio !!}</div>
                            @endif
                        </div>
                    </article>
                @endforeach
            </div>
        @endif
    </div>
</x-site.layout>

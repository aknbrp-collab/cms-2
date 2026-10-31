<x-site.layout :site="$site" :seo="$seo">
    <x-site.page-header :title="__('Organisasi Mahasantri')" :description="__('Dewan Mahasantri dan unit kegiatan sebagai wadah pengembangan diri.')" />

    <div class="mx-auto max-w-6xl px-4 py-10 sm:px-6 md:py-14 lg:px-10">
        @if ($organizations->isEmpty())
            <p class="rounded-xl bg-surface-card p-10 text-center text-on-surface-variant shadow-sm">{{ __('Belum ada data organisasi.') }}</p>
        @else
            <div class="grid gap-6 md:grid-cols-2">
                @foreach ($organizations as $organization)
                    <article id="{{ $organization->slug }}" class="flex gap-5 rounded-2xl bg-surface-card p-6 shadow-sm">
                        <x-site.image :asset="$organization->logo" conversion="thumb" :alt="$organization->name" icon="public" class="size-20 shrink-0 rounded-xl" />
                        <div class="min-w-0 space-y-1.5">
                            <span class="inline-block rounded-full bg-mint px-2.5 py-0.5 text-[11px] font-bold text-primary">{{ $organization->type->label() }}</span>
                            <h2 class="text-lg font-bold text-on-surface">{{ $organization->name }}</h2>
                            @if ($organization->chairperson)
                                <p class="text-sm text-on-surface-variant">{{ __('Ketua: :name', ['name' => $organization->chairperson]) }}@if ($organization->period) ({{ $organization->period }})@endif</p>
                            @endif
                            @if ($organization->description)
                                <p class="text-sm text-on-surface-variant">{{ $organization->description }}</p>
                            @endif
                            @if ($organization->instagram_url)
                                <a href="{{ $organization->instagram_url }}" target="_blank" rel="noopener" class="inline-flex items-center gap-1 text-sm font-semibold text-primary"><x-site.icon name="photo_camera" class="text-[16px]!" /> Instagram</a>
                            @endif
                        </div>
                    </article>
                @endforeach
            </div>
        @endif
    </div>
</x-site.layout>

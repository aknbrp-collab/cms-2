<x-site.layout :site="$site" :seo="$seo">
    <x-site.page-header :title="__('Agenda')" :description="__('Kegiatan, kajian, dan acara penting yang akan datang.')" />

    <div class="mx-auto max-w-5xl px-4 py-10 sm:px-6 md:py-14">
        <div class="mb-8 inline-flex rounded-lg bg-surface-low p-1 text-sm font-semibold">
            <a href="{{ route('site.events.index') }}" @class(['rounded-md px-4 py-2', 'bg-primary text-on-primary shadow-sm' => ! $past, 'text-on-surface-variant hover:text-brand' => $past])>{{ __('Mendatang') }}</a>
            <a href="{{ route('site.events.index', ['arsip' => 1]) }}" @class(['rounded-md px-4 py-2', 'bg-primary text-on-primary shadow-sm' => $past, 'text-on-surface-variant hover:text-brand' => ! $past])>{{ __('Arsip') }}</a>
        </div>

        @if ($events->isEmpty())
            <p class="site-card p-10 text-center text-on-surface-variant">{{ $past ? __('Belum ada arsip kegiatan.') : __('Belum ada agenda mendatang.') }}</p>
        @else
            <div class="grid gap-5 md:grid-cols-2">
                @foreach ($events as $event)
                    <x-site.event-card :event="$event" />
                @endforeach
            </div>

            <div class="mt-10">{{ $events->links() }}</div>
        @endif
    </div>
</x-site.layout>

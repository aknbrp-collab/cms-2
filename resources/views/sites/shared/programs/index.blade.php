<x-site.layout :site="$site" :seo="$seo">
    <x-site.page-header :title="__('Program Takhassus')" :description="__('Disiplin keilmuan dengan kurikulum kitab mu\'tabarah.')" />

    <div class="mx-auto max-w-7xl px-4 py-10 sm:px-6 md:py-14 lg:px-10">
        @if ($programs->isEmpty())
            <p class="site-card p-10 text-center text-on-surface-variant">{{ __('Belum ada program.') }}</p>
        @else
            <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
                @foreach ($programs as $program)
                    <x-site.program-card :program="$program" />
                @endforeach
            </div>
        @endif
    </div>
</x-site.layout>

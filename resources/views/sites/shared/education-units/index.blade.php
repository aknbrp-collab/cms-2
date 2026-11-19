<x-site.layout :site="$site" :seo="$seo">
    <x-site.page-header :title="__('Program Pendidikan')" :description="__('Jenjang dan program pendidikan beserta kurikulumnya.')" />

    <div class="mx-auto max-w-7xl px-4 py-10 sm:px-6 md:py-14 lg:px-10">
        @if ($units->isEmpty())
            <p class="site-card p-10 text-center text-on-surface-variant">{{ __('Belum ada program pendidikan.') }}</p>
        @else
            <div class="grid items-stretch gap-6 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($units as $unit)
                    <a href="{{ route('site.education-units.show', $unit->slug) }}" class="group site-card site-card-hover flex h-full flex-col overflow-hidden">
                        <x-site.image :asset="$unit->image" conversion="thumb" :alt="$unit->name" icon="school" class="aspect-[16/10] w-full" />
                        <div class="flex flex-1 flex-col gap-2 p-6">
                            <h2 class="site-title transition-colors group-hover:text-brand">{{ $unit->name }}</h2>
                            @if ($unit->summary)
                                <p class="line-clamp-3 text-sm leading-[22px] text-on-surface-variant">{{ $unit->summary }}</p>
                            @endif
                            <div class="site-meta mt-auto flex flex-wrap gap-x-4 gap-y-1 pt-3">
                                @if ($unit->student_count > 0)
                                    <span class="inline-flex items-center gap-1"><x-site.icon name="person" class="text-[15px]!" /> {{ __(':count santri', ['count' => number_format($unit->student_count, 0, ',', '.')]) }}</span>
                                @endif
                                @if ($unit->subjects_count > 0)
                                    <span class="inline-flex items-center gap-1"><x-site.icon name="menu_book" class="text-[15px]!" /> {{ __(':count mata pelajaran', ['count' => $unit->subjects_count]) }}</span>
                                @endif
                            </div>
                        </div>
                    </a>
                @endforeach
            </div>
        @endif
    </div>
</x-site.layout>

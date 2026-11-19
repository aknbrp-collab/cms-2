<x-site.layout :site="$site" :seo="$seo">
    <x-site.page-header :title="$unit->name" :description="$unit->summary" :crumbs="[__('Program Pendidikan') => route('site.education-units.index')]" />

    <div class="mx-auto grid max-w-7xl items-start gap-8 px-4 py-10 sm:px-6 md:py-14 lg:grid-cols-12 lg:px-10">
        <div class="flex flex-col gap-10 lg:col-span-8">
            @if ($unit->image)
                <x-site.image :asset="$unit->image" :alt="$unit->name" class="aspect-[16/9] w-full rounded-lg" />
            @endif

            @if ($unit->description)
                {{-- Sanitised by App\Support\RichText when saved. --}}
                <div class="prose-site">{!! $unit->description !!}</div>
            @endif

            <section class="flex flex-col gap-5">
                <h2 class="site-section-title">{{ __('Kurikulum') }}</h2>
                @forelse ($curriculum as $grade => $subjects)
                    <div class="site-card overflow-hidden">
                        <div class="flex items-center justify-between gap-3 bg-primary px-5 py-3 text-on-primary">
                            <h3 class="font-semibold">{{ $grade }}</h3>
                            <span class="font-mono text-xs text-on-primary/80 tabular-nums">{{ __(':count mata pelajaran', ['count' => $subjects->count()]) }}</span>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead class="bg-surface-low text-xs tracking-wide text-muted uppercase">
                                    <tr>
                                        <th class="w-12 px-5 py-2 text-start">#</th>
                                        <th class="px-5 py-2 text-start">{{ __('Mata Pelajaran') }}</th>
                                        <th class="px-5 py-2 text-start">{{ __('Kitab') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-border-subtle">
                                    @foreach ($subjects as $subject)
                                        <tr>
                                            <td class="px-5 py-2.5 font-mono text-xs text-muted tabular-nums">{{ $loop->iteration }}</td>
                                            <td class="px-5 py-2.5 font-medium text-on-surface">{{ $subject->name }}</td>
                                            <td class="px-5 py-2.5 text-on-surface-variant">{{ $subject->books }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @empty
                    <p class="site-card p-6 text-on-surface-variant">{{ __('Kurikulum belum tersedia.') }}</p>
                @endforelse
            </section>
        </div>

        <aside class="flex flex-col gap-6 lg:sticky lg:top-28 lg:col-span-4">
            <div class="site-card site-accent-top grid grid-cols-2 divide-x divide-border-subtle">
                <div class="flex flex-col gap-1 p-6">
                    <span class="text-xs font-bold tracking-wide text-muted uppercase">{{ __('Jumlah Santri') }}</span>
                    <span class="text-3xl font-bold text-brand tabular-nums">{{ $unit->student_count > 0 ? number_format($unit->student_count, 0, ',', '.') : '–' }}</span>
                </div>
                <div class="flex flex-col gap-1 p-6">
                    <span class="text-xs font-bold tracking-wide text-muted uppercase">{{ __('Mata Pelajaran') }}</span>
                    <span class="text-3xl font-bold text-brand tabular-nums">{{ $curriculum->flatten()->count() }}</span>
                </div>
            </div>

            @if ($others->isNotEmpty())
                <div class="site-card p-6">
                    <h2 class="site-section-title mb-4 text-lg!">{{ __('Program Lainnya') }}</h2>
                    <ul class="flex flex-col divide-y divide-border-subtle">
                        @foreach ($others as $other)
                            <li>
                                <a href="{{ route('site.education-units.show', $other->slug) }}" class="flex items-center justify-between gap-3 py-3 text-sm font-semibold text-on-surface first:pt-0 last:pb-0 hover:text-brand">
                                    {{ $other->name }} <x-site.icon name="chevron_right" class="text-[18px]! text-brand" />
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </aside>
    </div>
</x-site.layout>

<x-site.layout :site="$site" :seo="$seo">
    <x-site.page-header :title="$program->name" :description="$program->summary" :crumbs="[__('Program') => route('site.programs.index')]" />

    <div class="mx-auto grid max-w-7xl gap-10 px-4 py-10 sm:px-6 md:py-14 lg:grid-cols-12 lg:px-10">
        <div class="space-y-10 lg:col-span-8">
            @if ($program->description)
                {{-- Sanitised by App\Support\RichText when saved. --}}
                <div class="prose-site">{!! $program->description !!}</div>
            @endif

            <section>
                <h2 class="mb-4 text-2xl font-bold text-on-surface">{{ __('Kurikulum') }}</h2>
                @forelse ($semesters as $semester => $courses)
                    <div class="site-card mb-6 overflow-hidden">
                        <div class="flex items-center justify-between bg-primary px-5 py-3 text-on-primary">
                            <h3 class="font-semibold">{{ __('Semester :n', ['n' => $semester]) }}</h3>
                            <span class="text-sm text-on-primary/80">{{ $courses->sum('credits') }} SKS</span>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead class="bg-surface-low text-start text-xs tracking-wide text-muted uppercase">
                                    <tr>
                                        <th class="px-5 py-2 text-start">{{ __('Kode') }}</th>
                                        <th class="px-5 py-2 text-start">{{ __('Mata Kuliah') }}</th>
                                        <th class="px-5 py-2 text-start">{{ __('Kitab') }}</th>
                                        <th class="px-5 py-2 text-end">SKS</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-border-subtle">
                                    @foreach ($courses as $course)
                                        <tr>
                                            <td class="px-5 py-2.5 font-mono text-xs text-muted">{{ $course->code }}</td>
                                            <td class="px-5 py-2.5 font-medium text-on-surface">{{ $course->name }}</td>
                                            <td class="px-5 py-2.5 text-on-surface-variant">{{ $course->books }}</td>
                                            <td class="px-5 py-2.5 text-end">{{ $course->credits }}</td>
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

        <aside class="space-y-6 lg:col-span-4">
            <div class="site-card space-y-3 p-6">
                <p><span class="text-sm text-muted">{{ __('Jenjang') }}</span><br><span class="font-semibold text-on-surface">{{ $program->level->label() }}</span></p>
                @if ($program->degree)
                    <p><span class="text-sm text-muted">{{ __('Gelar') }}</span><br><span class="font-semibold text-on-surface">{{ $program->degree }}</span></p>
                @endif
                @if ($program->accreditation)
                    <p><span class="text-sm text-muted">{{ __('Akreditasi') }}</span><br><span class="font-semibold text-on-surface">{{ $program->accreditation }}</span></p>
                @endif
                <p><span class="text-sm text-muted">{{ __('Total') }}</span><br><span class="font-semibold text-on-surface">{{ $semesters->flatten()->sum('credits') }} SKS</span></p>
            </div>

            @if ($books = $program->coreBookList())
                <div class="site-card p-6">
                    <h2 class="mb-3 font-bold text-on-surface">{{ __('Kitab Muqarrar Induk') }}</h2>
                    <ul class="space-y-2 text-sm text-on-surface-variant">
                        @foreach ($books as $book)
                            <li class="flex gap-2"><x-site.icon name="menu_book" class="text-[18px]! text-brand" /> {{ $book }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </aside>
    </div>
</x-site.layout>

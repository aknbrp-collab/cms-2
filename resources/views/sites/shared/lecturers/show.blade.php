<x-site.layout :site="$site" :seo="$seo">
    <x-site.page-header :title="$lecturer->name" :description="$lecturer->position" :crumbs="[__('Dosen') => route('site.lecturers.index')]" />

    <div class="mx-auto grid max-w-6xl gap-10 px-4 py-10 sm:px-6 md:py-14 lg:grid-cols-12 lg:px-10">
        <aside class="lg:col-span-4">
            <div class="space-y-4 rounded-2xl bg-surface-card p-6 text-center shadow-sm">
                <x-site.image :asset="$lecturer->photo" :alt="$lecturer->name" icon="person" class="mx-auto aspect-square w-48 rounded-2xl" />
                @if ($lecturer->expertise)
                    <p class="inline-flex items-center gap-1 rounded-full bg-mint px-3 py-1 text-xs font-semibold text-brand"><x-site.icon name="verified" class="text-[14px]!" /> {{ $lecturer->expertise }}</p>
                @endif
                <dl class="space-y-2 text-start text-sm">
                    @if ($lecturer->nidn)
                        <div><dt class="text-muted">{{ __('NIDN') }}</dt><dd class="font-medium text-on-surface">{{ $lecturer->nidn }}</dd></div>
                    @endif
                    @if ($lecturer->email)
                        <div><dt class="text-muted">{{ __('Email') }}</dt><dd><a href="mailto:{{ $lecturer->email }}" class="font-medium text-brand">{{ $lecturer->email }}</a></dd></div>
                    @endif
                </dl>
            </div>
        </aside>

        <div class="space-y-10 lg:col-span-8">
            @if ($lecturer->education)
                <section>
                    <h2 class="mb-3 text-xl font-bold text-on-surface">{{ __('Pendidikan') }}</h2>
                    <ul class="space-y-2">
                        @foreach (array_filter(array_map('trim', explode("\n", $lecturer->education))) as $line)
                            <li class="flex gap-2 text-on-surface-variant"><x-site.icon name="school" class="text-brand" /> {{ $line }}</li>
                        @endforeach
                    </ul>
                </section>
            @endif

            @if ($lecturer->bio)
                <section>
                    <h2 class="mb-3 text-xl font-bold text-on-surface">{{ __('Profil') }}</h2>
                    {{-- Sanitised by App\Support\RichText when saved. --}}
                    <div class="prose-site">{!! $lecturer->bio !!}</div>
                </section>
            @endif

            @if ($publications->isNotEmpty())
                <section>
                    <h2 class="mb-3 text-xl font-bold text-on-surface">{{ __('Publikasi Terkait') }}</h2>
                    <ul class="divide-y divide-border-subtle rounded-xl bg-surface-card shadow-sm">
                        @foreach ($publications as $publication)
                            <li class="p-4">
                                <a href="{{ route('site.publications.show', $publication->slug) }}" class="font-semibold text-on-surface hover:text-brand">{{ $publication->title }}</a>
                                <p class="mt-1 text-xs text-muted">{{ $publication->type->label() }} · {{ $publication->year }}</p>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif
        </div>
    </div>
</x-site.layout>

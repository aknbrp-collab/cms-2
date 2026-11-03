@props(['program'])

<article class="flex flex-col rounded-2xl border border-brand/10 bg-surface-card p-6 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md">
    <div class="flex items-start justify-between gap-3">
        <span class="rounded-full bg-mint px-3 py-1 text-xs font-bold text-brand">{{ $program->level->label() }}</span>
        @if ($program->accreditation)
            <span class="rounded-full bg-gold-light px-3 py-1 text-xs font-bold text-on-surface">{{ $program->accreditation }}</span>
        @endif
    </div>
    <h3 class="mt-4 text-xl font-bold text-brand">{{ $program->name }}</h3>
    @if ($program->degree)
        <p class="mt-1 text-sm text-muted">{{ __('Gelar: :degree', ['degree' => $program->degree]) }}</p>
    @endif
    @if ($program->summary)
        <p class="mt-3 text-sm leading-relaxed text-on-surface-variant">{{ $program->summary }}</p>
    @endif
    @if ($books = array_slice($program->coreBookList(), 0, 3))
        <div class="mt-4 rounded-lg bg-surface-low p-3">
            <p class="text-xs font-bold tracking-wide text-on-surface uppercase">{{ __('Kitab Muqarrar') }}</p>
            <ul class="mt-1 space-y-0.5 text-sm text-on-surface-variant">
                @foreach ($books as $book)
                    <li class="flex gap-1.5"><x-site.icon name="menu_book" class="text-[16px]! text-brand" /> {{ $book }}</li>
                @endforeach
            </ul>
        </div>
    @endif
    <a href="{{ route('site.programs.show', $program->slug) }}" class="mt-auto inline-flex items-center gap-1 pt-5 text-sm font-semibold text-brand">
        {{ __('Kurikulum & silabus') }} <x-site.icon name="arrow_forward" class="text-[16px]!" />
    </a>
</article>

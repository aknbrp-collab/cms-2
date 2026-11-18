@props(['program'])

<article class="site-card site-card-hover flex h-full flex-col p-6">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <span class="site-eyebrow">{{ $program->level->label() }}</span>
        @if ($program->accreditation)
            <span class="site-chip">{{ $program->accreditation }}</span>
        @endif
    </div>
    <h3 class="site-title mt-3">{{ $program->name }}</h3>
    @if ($program->degree)
        <p class="site-meta mt-1">{{ __('Gelar: :degree', ['degree' => $program->degree]) }}</p>
    @endif
    @if ($program->summary)
        <p class="mt-3 text-sm leading-[22px] text-on-surface-variant">{{ $program->summary }}</p>
    @endif
    @if ($books = array_slice($program->coreBookList(), 0, 3))
        <div class="mt-4 rounded-md border border-border-subtle bg-surface-low p-4">
            <p class="text-xs font-bold tracking-wide text-on-surface uppercase">{{ __('Kitab Muqarrar') }}</p>
            <ul class="mt-2 space-y-1 text-sm text-on-surface-variant">
                @foreach ($books as $book)
                    <li class="flex gap-1.5"><x-site.icon name="menu_book" class="text-[16px]! text-brand" /> {{ $book }}</li>
                @endforeach
            </ul>
        </div>
    @endif
    <a href="{{ route('site.programs.show', $program->slug) }}" class="site-link mt-auto pt-5">
        {{ __('Kurikulum & silabus') }} <x-site.icon name="arrow_forward" class="text-[16px]!" />
    </a>
</article>
